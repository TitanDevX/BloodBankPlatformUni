import test from 'node:test';
import assert from 'node:assert/strict';
import { createServer } from 'node:http';
import { once } from 'node:events';
import { createApiClient } from './api.js';

test('session login and logout use the documented JSON API and bearer token', async (context) => {
  const requests = [];
  const server = createServer(async (request, response) => {
    let body = '';
    for await (const chunk of request) body += chunk;
    requests.push({ method: request.method, path: request.url, headers: request.headers, body });
    response.setHeader('Content-Type', 'application/json');
    if (request.url === '/v1/sessions') {
      response.end(JSON.stringify({ accessToken: 'test-token', user: { role: 'roles/administrator', active: true } }));
    } else {
      response.end();
    }
  });
  server.listen(0, '127.0.0.1');
  await once(server, 'listening');
  context.after(() => server.close());

  const api = createApiClient(`http://127.0.0.1:${server.address().port}/v1`);
  const session = await api('/sessions', { method: 'POST', body: JSON.stringify({ email: 'admin@demo.com', password: '123456' }) });
  assert.equal(session.user.role, 'roles/administrator');
  assert.equal(requests[0].method, 'POST');
  assert.equal(requests[0].path, '/v1/sessions');
  assert.equal(requests[0].headers['content-type'], 'application/json');
  assert.deepEqual(JSON.parse(requests[0].body), { email: 'admin@demo.com', password: '123456' });

  assert.equal(await api('/sessions/current', { method: 'DELETE', token: session.accessToken }), null);
  assert.equal(requests[1].method, 'DELETE');
  assert.equal(requests[1].headers.authorization, 'Bearer test-token');
});

test('failed login and unreachable API show actionable errors', async () => {
  const unauthorized = createApiClient('/v1', async () => new Response('', { status: 401 }));
  await assert.rejects(unauthorized('/sessions', { method: 'POST' }), /email or password is incorrect/);

  const unavailable = createApiClient('/v1', async () => { throw new Error('network failure'); });
  await assert.rejects(unavailable('/sessions', { method: 'POST' }), /couldn’t reach the service/);
});
