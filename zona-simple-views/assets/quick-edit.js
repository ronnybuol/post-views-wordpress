(function () {
    'use strict';
    if (!window.inlineEditPost || !window.ZonaViewsQuickEdit) return;
    const original = window.inlineEditPost.edit;
    let sequence = 0;
    window.inlineEditPost.edit = function (id) {
        const result = original.apply(this, arguments);
        const postId = parseInt(typeof id === 'object' ? this.getId(id) : id, 10);
        const row = document.getElementById('post-' + postId);
        const edit = document.getElementById('edit-' + postId);
        const box = edit && edit.querySelector('.zsv-quick-box');
        if (!box) return result;
        const marker = row && row.querySelector('.zsv-row-data');
        const input = box.querySelector('[name="zsv_total"]');
        const nonce = box.querySelector('[name="zsv_nonce"]');
        const baseline = box.querySelector('[name="zsv_baseline"]');
        const initial = box.querySelector('[name="zsv_original"]');
        const status = box.querySelector('.zsv-quick-status');
        const save = edit.querySelector('.save');
        const token = ++sequence;
        box.zsvToken = token;
        input.value = ''; input.disabled = true;
        nonce.value = ''; baseline.value = ''; initial.value = '';
        if (!marker) {
            status.textContent = 'Anda tidak memiliki izin mengubah views artikel ini.';
            return result;
        }
        status.textContent = 'Memuat views terbaru…';
        if (save) save.disabled = true;
        const body = new URLSearchParams({action: 'zsv_quick_data', post_id: String(postId), nonce: marker.dataset.zsvNonce});
        fetch(window.ZonaViewsQuickEdit.url, {
            method: 'POST', credentials: 'same-origin', cache: 'no-store',
            headers: {'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8'},
            body: body.toString()
        }).then(async response => {
            const payload = await response.json();
            if (!response.ok || !payload.success) throw new Error(payload.data && payload.data.message || 'Tidak dapat memuat views. Tutup Quick Edit lalu coba lagi.');
            if (!box.isConnected || box.zsvToken !== token) return;
            input.value = payload.data.total;
            baseline.value = payload.data.actual;
            initial.value = payload.data.total;
            nonce.value = payload.data.nonce;
            input.disabled = false;
            status.textContent = payload.data.description;
        }).catch(error => {
            if (box.isConnected && box.zsvToken === token) status.textContent = error.message;
        }).finally(() => {
            if (box.isConnected && box.zsvToken === token && save) save.disabled = false;
        });
        return result;
    };
}());
