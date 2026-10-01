const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const source = fs.readFileSync('zona-simple-views/assets/quick-edit.js', 'utf8');
function setup({fail = false, marker = true, deferred = false} = {}) {
    const fields = Object.fromEntries(['zsv_total', 'zsv_nonce', 'zsv_baseline', 'zsv_original'].map(name => [name, {value: 'old', disabled: false}]));
    const status = {textContent: ''}, save = {disabled: false};
    const box = {isConnected: true, querySelector(selector) {return selector === '.zsv-quick-status' ? status : fields[selector.match(/name="(.*?)"/)[1]];}};
    const edit = {querySelector(selector) {return selector === '.save' ? save : box;}};
    const row = {querySelector() {return marker ? {dataset: {zsvNonce: 'row-nonce'}} : null;}};
    const calls = [], requests = [];
    let originalCalls = 0;
    const controller = {edit() {originalCalls++; return 'original-result';}, getId() {return 42;}};
    const context = {
        window: {inlineEditPost: controller, ZonaViewsQuickEdit: {url: '/wp-admin/admin-ajax.php'}},
        document: {getElementById(id) {return id === 'post-42' ? row : id === 'edit-42' ? edit : null;}},
        URLSearchParams,
        fetch: (url, options) => {
            calls.push(Object.fromEntries(new URLSearchParams(options.body)));
            const response = {ok: !fail, json: async () => ({success: !fail, data: fail ? {message: 'Token kedaluwarsa'} : {actual: 5005, total: 5105, nonce: 'fresh-nonce', description: 'Views otomatis: 5.005; penyesuaian: 100.'}})};
            if (!deferred) return Promise.resolve(response);
            return new Promise(resolve => requests.push(() => resolve(response)));
        }
    };
    vm.runInNewContext(source, context);
    return {controller, fields, box, status, save, calls, requests, originalCalls: () => originalCalls};
}
const flush = () => new Promise(resolve => setImmediate(resolve));
(async () => {
    let s = setup();
    assert.equal(s.controller.edit(42), 'original-result');
    assert.equal(s.originalCalls(), 1);
    assert.equal(s.save.disabled, true);
    assert.equal(s.fields.zsv_total.disabled, true);
    await flush();
    assert.equal(s.fields.zsv_total.value, 5105);
    assert.equal(s.fields.zsv_baseline.value, 5005);
    assert.equal(s.fields.zsv_nonce.value, 'fresh-nonce');
    assert.equal(s.save.disabled, false);
    assert.equal(s.calls[0].nonce, 'row-nonce');
    s = setup(); s.controller.edit({button: true}); await flush();
    assert.equal(s.calls[0].post_id, '42');
    s = setup({fail: true}); s.controller.edit(42); await flush();
    assert.equal(s.fields.zsv_total.disabled, true);
    assert.equal(s.fields.zsv_nonce.value, '');
    assert.equal(s.save.disabled, false);
    assert.equal(s.status.textContent, 'Token kedaluwarsa');
    s = setup({marker: false}); s.controller.edit(42); await flush();
    assert.equal(s.calls.length, 0);
    s = setup({deferred: true}); s.controller.edit(42); s.box.isConnected = false; s.requests[0](); await flush();
    assert.equal(s.fields.zsv_nonce.value, '');
    s = setup({deferred: true}); s.controller.edit(42); s.controller.edit(42);
    s.requests[0](); await flush();
    assert.equal(s.fields.zsv_nonce.value, '');
    assert.equal(s.save.disabled, true);
    s.requests[1](); await flush();
    assert.equal(s.fields.zsv_nonce.value, 'fresh-nonce');
    console.log('PASS: 6 quick-edit UI scenarios (fresh values, object ID, errors, permission, closed row, stale response).');
})().catch(error => {console.error(error); process.exit(1);});
