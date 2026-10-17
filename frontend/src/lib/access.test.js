import test from 'node:test';
import assert from 'node:assert/strict';
import { getAccess, roles, roleCode } from './access.js';

test('each of the eight documented staff roles has a workspace', () => {
  assert.equal(Object.keys(roles).length, 8);
  for (const role of Object.keys(roles)) assert.ok(getAccess({ role }).nav.includes('Overview'));
});
test('administrators do not inherit collection or laboratory access', () => {
  for (const role of ['administrator', 'bank_admin']) {
    const nav = getAccess({ role }).nav;
    for (const denied of ['Donors', 'Donations', 'Screening results', 'Release decisions']) assert.ok(!nav.includes(denied));
  }
});
test('hospital navigation excludes bank stock and staff administration', () => {
  const nav = getAccess({ role: 'hospital_requester' }).nav;
  assert.ok(nav.includes('Requests'));
  assert.ok(!nav.includes('Inventory'));
  assert.ok(!nav.includes('Staff & access'));
});
test('unknown roles fail closed and resource/object roles resolve', () => {
  assert.equal(getAccess({ role: 'donor' }), null);
  assert.equal(getAccess(null), null);
  assert.equal(roleCode({ role: { code: 'bank_admin' } }), 'bank_admin');
  assert.equal(roleCode({ role: 'roles/bank_admin' }), 'bank_admin');
});
