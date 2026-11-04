import test from 'node:test';
import assert from 'node:assert/strict';
import { publicFetch } from './public-fetch.mjs';

for (const url of ['http://127.0.0.1/', 'http://[::1]/', 'http://169.254.169.254/', 'http://10.0.0.1/', 'http://[::ffff:127.0.0.1]/', 'file:///etc/passwd', 'http://user:password@example.com/', 'https://example.com:3000/']) {
  test(`blocks ${url}`, async () => {
    await assert.rejects(publicFetch(url));
  });
}
