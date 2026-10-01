const vm = require('node:vm');
const fs = require('node:fs');
const assert = require('node:assert/strict');
const source = fs.readFileSync('zona-simple-views/assets/views.js', 'utf8');
async function test(pageId, ids, {hidden = false, fail = false} = {}) {
    const elements = ids.map(id => ({dataset: {zsvPost: String(id)}, counter: {textContent: 'old'}, querySelector() {return this.counter;}}));
    const calls = [];
    let listener;
    const document = {
        visibilityState: hidden ? 'hidden' : 'visible',
        querySelectorAll() {return elements;},
        addEventListener(name, fn) {listener = fn;},
        removeEventListener() {listener = undefined;}
    };
    vm.runInNewContext(source, {
        window: {ZonaViews: {url: '/wp-admin/admin-ajax.php', postId: pageId}},
        document, URLSearchParams, Set, Promise,
        fetch: async (url, options) => {
            const data = new URLSearchParams(options.body);
            calls.push(Object.fromEntries(data));
            assert.equal(options.credentials, 'same-origin');
            assert.equal(options.cache, 'no-store');
            return {ok: !fail, json: async () => ({success: true, data: {formatted: data.get('action') === 'zsv_count' ? '1.001' : '1.000', nonce: 'fresh'}})};
        }
    });
    await new Promise(resolve => setImmediate(resolve));
    if (hidden) {
        assert.equal(calls.filter(c => +c.post_id === pageId).length, 0);
        document.visibilityState = 'visible'; listener();
        await new Promise(resolve => setImmediate(resolve));
    }
    return {calls, elements};
}
(async () => {
    let result = await test(42, [42, 42, 99]);
    assert.equal(result.calls.filter(c => c.action === 'zsv_count').length, 1);
    assert.equal(result.calls.find(c => c.action === 'zsv_count').post_id, '42');
    assert.equal(result.calls.find(c => c.action === 'zsv_count').nonce, 'fresh');
    assert.equal(result.elements[0].counter.textContent, '1.001');
    assert.equal(result.elements[1].counter.textContent, '1.001');
    assert.equal(result.elements[2].counter.textContent, '1.000');
    result = await test(0, [99]);
    assert.equal(result.calls.length, 1);
    assert.equal(result.calls[0].action, 'zsv_boot');
    result = await test(42, []);
    assert.equal(result.calls.length, 2, 'count even when category hides display');
    result = await test(0, []);
    assert.equal(result.calls.length, 0);
    result = await test(42, [42], {hidden: true});
    assert.equal(result.calls.length, 2);
    result = await test(42, [42], {fail: true});
    assert.equal(result.elements[0].counter.textContent, 'old');
    console.log('PASS: 6 frontend scenarios (single count, other IDs, hidden category, empty page, visibility, failure fallback).');
})().catch(error => {console.error(error); process.exit(1);});
