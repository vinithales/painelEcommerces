import test from 'node:test';
import assert from 'node:assert/strict';
import { chromium } from 'playwright';
import { captureErrors } from './capture.mjs';
import { startProxy } from './proxy.mjs';
import http from 'node:http';

test('captures console errors and uncaught exceptions with bounded storage', async () => {
  const browser = await chromium.launch();
  try {
    const page = await browser.newPage();
    const observed = { console_errors: [], page_errors: [], console_error_count: 0, page_error_count: 0 };
    captureErrors(page, observed);
    const errorReceived = page.waitForEvent('pageerror');
    await page.setContent('<script>console.log("ignored"); for(let i=0;i<105;i++)console.error("widget failed"); throw new Error("checkout script failed");</script>');
    await errorReceived;
    assert.equal(observed.console_error_count, 105);
    assert.equal(observed.console_errors.length, 100);
    assert.equal(observed.page_error_count, 1);
    assert.match(observed.page_errors[0].stack, /checkout script failed/);
    assert.equal(observed.console_errors[0].message, 'widget failed');
  } finally {
    await browser.close();
  }
});

test('redirects use the validating proxy and keep scripts observable', async () => {
  const fixture = http.createServer((_req, res) => res.end('<script src="/widget.js"></script>'));
  await new Promise(resolve => fixture.listen(0, '127.0.0.1', resolve));
  const proxied = [];
  const proxy = await startProxy(async url => {
    proxied.push(url);
    return { address: '127.0.0.1', port: fixture.address().port };
  });
  const browser = await chromium.launch({ proxy: { server: proxy.server, bypass: '<-loopback>' } });
  try {
    const context = await browser.newContext({ serviceWorkers: 'block' });
    await context.route('**/*', async route => {
      if (route.request().url() === 'http://example.com/') {
        await route.fulfill({ status: 302, headers: { location: 'http://example.org/' } });
      } else {
        await route.fulfill({ contentType: 'application/javascript', body: 'console.error("redirect widget")' });
      }
    });
    const page = await context.newPage();
    const observed = { console_errors: [], page_errors: [], console_error_count: 0, page_error_count: 0 };
    captureErrors(page, observed);
    await page.goto('http://example.com/');
    assert.equal(page.url(), 'http://example.org/');
    assert.ok(proxied.includes('http://example.org/'));
    assert.equal(observed.console_errors[0].message, 'redirect widget');
  } finally {
    await browser.close();
    await proxy.close();
    await new Promise(resolve => fixture.close(resolve));
  }
});
