import http from 'node:http';
import { chromium } from 'playwright';
import { publicFetch } from './public-fetch.mjs';
import { captureErrors } from './capture.mjs';
import { startProxy } from './proxy.mjs';

const token = process.env.BROWSER_CHECK_TOKEN;
if (!token) throw new Error('BROWSER_CHECK_TOKEN obrigatorio');
let busy = false;

http.createServer(async (req, res) => {
  res.setHeader('content-type', 'application/json');
  if (req.method !== 'POST' || req.url !== '/check' || req.headers.authorization !== `Bearer ${token}`) {
    res.writeHead(403).end('{}'); return;
  }
  if (busy) { res.writeHead(503).end('{}'); return; }
  busy = true;
  let browser;
  let proxy;
  const consoleErrors = [], pageErrors = [], blocked = [];
  const observed = { console_errors: consoleErrors, page_errors: pageErrors, console_error_count: 0, page_error_count: 0 };
  const deadline = setTimeout(() => browser?.close(), 40000);
  try {
    let input = '';
    for await (const chunk of req) {
      input += chunk;
      if (input.length > 8192) throw new Error('Entrada acima do limite');
    }
    const config = JSON.parse(input);
    if (!Number.isInteger(config.timeout) || config.timeout < 1 || config.timeout > 30 || !Number.isInteger(config.wait_ms) || config.wait_ms < 0 || config.wait_ms > 10000) throw new Error('Configuracao invalida');
    proxy = await startProxy();
    browser = await chromium.launch({ headless: true, proxy: { server: proxy.server, bypass: '<-loopback>' }, args: ['--disable-quic', '--force-webrtc-ip-handling-policy=disable_non_proxied_udp'] });
    const context = await browser.newContext({ serviceWorkers: 'block', acceptDownloads: false });
    await context.routeWebSocket('**/*', socket => socket.close());
    let resourceCount = 0;
    let totalBytes = 0;
    await context.route('**/*', async route => {
      try {
        if (++resourceCount > 1000) throw new Error('Pagina acima de 1000 recursos');
        const request = route.request();
        const body = request.postDataBuffer();
        if (body && body.length > 1024 * 1024) throw new Error('Requisicao acima de 1 MB');
        const response = await publicFetch(request.url(), request.headers(), config.timeout * 1000, request.method(), body);
        totalBytes += response.body.length;
        if (totalBytes > 30 * 1024 * 1024) throw new Error('Pagina acima de 30 MB');
        await route.fulfill(response);
      } catch (error) {
        if (blocked.length < 100) blocked.push({ url: route.request().url().slice(0, 2048), message: error.message });
        await route.abort().catch(() => {});
      }
    });
    const page = await context.newPage();
    captureErrors(page, observed);
    const start = Date.now();
    const response = await page.goto(config.url, { waitUntil: 'load', timeout: config.timeout * 1000 });
    if (!response) throw new Error('Navegacao sem resposta HTTP');
    const responseTime = Date.now() - start;
    await page.waitForTimeout(config.wait_ms);
    const headers = await response.allHeaders();
    delete headers['set-cookie'];
    const body = (await page.content()).slice(0, 1024 * 1024);
    res.end(JSON.stringify({ status_code: response.status(), response_time: responseTime, body,
      url: page.url(), headers, ...observed, blocked_requests: blocked,
      resource_count: resourceCount, downloaded_bytes: totalBytes,
      ...(blocked.some(row => /^(Pagina acima|Recurso acima|Requisicao acima|Destino)/.test(row.message))
        ? { observation_error: 'Observacao incompleta: recursos bloqueados por limites ou politica de destino do monitor. Consulte as requisicoes bloqueadas.' } : {}),
      observation_ms: config.wait_ms, errors_truncated: observed.console_error_count > 100 || observed.page_error_count > 100 }));
  } catch (error) {
    res.end(JSON.stringify({ observation_error: error.message.slice(0, 4000),
      ...observed, blocked_requests: blocked }));
  } finally {
    clearTimeout(deadline);
    await browser?.close();
    await proxy?.close();
    busy = false;
  }
}).listen(3000, '0.0.0.0');
