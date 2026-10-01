const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const source = fs.readFileSync('zona-simple-views/assets/import.js', 'utf8');
async function run(fail = false) {
    let click;
    const button = {disabled: false, addEventListener(name, fn) {click = fn;}};
    const status = {textContent: ''};
    const calls = [];
    vm.runInNewContext(source, {
        window: {ZonaViewsImport: {url: '/wp-admin/admin-ajax.php', nonce: 'admin-nonce'}},
        document: {getElementById(id) {return id === 'zsv-import' ? button : status;}},
        Intl, URLSearchParams,
        fetch: async (url, options) => {
            const data = Object.fromEntries(new URLSearchParams(options.body));
            calls.push(data);
            assert.equal(options.credentials, 'same-origin');
            assert.equal(data.nonce, 'admin-nonce');
            if (fail && data.mode === 'step') return {ok: false, json: async () => ({success: false, data: {message: 'Terputus; lanjutkan impor.'}})};
            const state = {run: 'stable-run', running: calls.length < 3, processed: (calls.length - 1) * 200, total: 400, copied: (calls.length - 1) * 200, skipped: 0};
            return {ok: true, json: async () => ({success: true, data: state})};
        }
    });
    await click();
    assert.equal(button.disabled, false);
    assert.equal(calls[0].mode, 'start');
    assert.equal(calls[1].run, 'stable-run');
    return {button, status, calls};
}
(async () => {
    let result = await run();
    assert.equal(result.calls.length, 3);
    assert.match(result.status.textContent, /^Selesai: 400 dari 400/);
    result = await run(true);
    assert.equal(result.calls.length, 2);
    assert.equal(result.button.textContent, 'Lanjutkan impor');
    assert.equal(result.status.textContent, 'Terputus; lanjutkan impor.');
    console.log('PASS: import progress, token continuity, completion, and retry UI.');
})().catch(error => {console.error(error); process.exit(1);});
