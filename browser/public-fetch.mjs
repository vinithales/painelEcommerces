import dns from 'node:dns/promises';
import http from 'node:http';
import https from 'node:https';
import ipaddr from 'ipaddr.js';

export async function publicAddress(rawUrl) {
  const url = new URL(rawUrl);
  if (!['http:', 'https:'].includes(url.protocol) || url.username || url.password || (url.port && !['80', '443'].includes(url.port))) {
    throw new Error('Destino HTTP publico invalido');
  }
  const host = url.hostname.replace(/^\[|\]$/g, '');
  const addresses = await dns.lookup(host, { all: true });
  if (!addresses.length || addresses.some(({ address }) => ipaddr.parse(address).range() !== 'unicast')) {
    throw new Error('Destino privado ou reservado bloqueado');
  }
  return { ...addresses[0], host, port: Number(url.port || (url.protocol === 'https:' ? 443 : 80)) };
}

export async function publicFetch(rawUrl, headers = {}, timeout = 10000, method = 'GET', body = null) {
  const url = new URL(rawUrl);
  const address = await publicAddress(rawUrl);
  // Pin the validated address: no second DNS resolution and no automatic redirects.
  const safeHeaders = { ...headers, 'accept-encoding': 'identity' };
  delete safeHeaders.host;
  delete safeHeaders.connection;
  return new Promise((resolve, reject) => {
    let deadline;
    const request = (url.protocol === 'https:' ? https : http).request(url, {
      method,
      headers: safeHeaders,
      lookup: (_hostname, options, callback) => options.all
        ? callback(null, [address]) : callback(null, address.address, address.family),
    }, response => {
      const chunks = [];
      let size = 0;
      response.on('data', chunk => {
        size += chunk.length;
        if (size > 5 * 1024 * 1024) response.destroy(new Error('Recurso acima de 5 MB'));
        else chunks.push(chunk);
      });
      response.on('error', reject);
      response.on('end', () => {
        clearTimeout(deadline);
        const headers = { ...response.headers };
        for (const key of ['transfer-encoding', 'connection', 'content-length']) delete headers[key];
        for (const [key, value] of Object.entries(headers)) headers[key] = Array.isArray(value) ? value.join('\n') : String(value);
        resolve({ status: response.statusCode, headers, body: Buffer.concat(chunks) });
      });
    });
    deadline = setTimeout(() => request.destroy(new Error('Tempo limite do recurso')), timeout);
    request.on('error', error => {
      clearTimeout(deadline);
      reject(error);
    });
    request.end(body);
  });
}
