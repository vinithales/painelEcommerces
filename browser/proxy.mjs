import http from 'node:http';
import net from 'node:net';
import { publicAddress } from './public-fetch.mjs';

// Redirects may bypass Playwright routing. All browser network traffic also
// passes through this proxy, which validates DNS and pins every connection.
export async function startProxy(resolveAddress = publicAddress) {
  const sockets = new Set();
  let bytes = 0;
  const track = socket => {
    sockets.add(socket);
    socket.on('close', () => sockets.delete(socket));
    socket.setTimeout(15000, () => socket.destroy());
    socket.on('error', () => {});
    socket.on('data', chunk => {
      bytes += chunk.length;
      if (bytes > 30 * 1024 * 1024) for (const current of sockets) current.destroy();
    });
  };
  const proxy = http.createServer(async (req, res) => {
    try {
      const url = new URL(req.url);
      if (url.protocol !== 'http:') throw new Error('HTTP only');
      const target = await resolveAddress(url.href);
      const headers = { ...req.headers };
      delete headers['proxy-authorization'];
      delete headers['proxy-connection'];
      const upstream = http.request({ hostname: target.address, port: target.port,
        path: url.pathname + url.search, method: req.method, headers }, response => {
        res.writeHead(response.statusCode, response.headers);
        response.pipe(res);
      });
      upstream.on('socket', track);
      upstream.on('error', () => res.destroy());
      req.pipe(upstream);
    } catch {
      res.writeHead(403).end();
    }
  });
  proxy.on('connect', async (req, client, head) => {
    try {
      const target = await resolveAddress(`https://${req.url}/`);
      const upstream = net.connect(target.port, target.address, () => {
        client.write('HTTP/1.1 200 Connection Established\r\n\r\n');
        if (head.length) upstream.write(head);
        client.pipe(upstream);
        upstream.pipe(client);
      });
      track(upstream);
      client.on('close', () => upstream.destroy());
      upstream.on('close', () => client.destroy());
    } catch {
      client.end('HTTP/1.1 403 Forbidden\r\nConnection: close\r\n\r\n');
    }
  });
  proxy.on('connection', track);
  await new Promise(resolve => proxy.listen(0, '127.0.0.1', resolve));
  return {
    server: `http://127.0.0.1:${proxy.address().port}`,
    close: async () => {
      for (const socket of sockets) socket.destroy();
      await new Promise(resolve => proxy.close(resolve));
    },
  };
}
