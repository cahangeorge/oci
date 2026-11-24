import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';

test('reinit reopens the existing consent box without initializing another banner', () => {
  const source = readFileSync(new URL('../../resources/consent/js/conzent.script.js', import.meta.url), 'utf8');
  const method = source.match(/reinit:\s*\(\)\s*=>\s*\{([\s\S]*?)\n\s*\},/);
  assert.ok(method, 'public reinit method exists');
  let opened = 0;
  let initialized = 0;
  vm.runInNewContext(`(() => {${method[1]}})()`, {
    conzentOpenBox: () => { opened++; },
    ConzentFN: () => { initialized++; },
  });
  assert.equal(opened, 1);
  assert.equal(initialized, 0);
});
