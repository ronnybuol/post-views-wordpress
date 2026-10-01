(function () {
    'use strict';
    if (!window.ZonaViews) return;
    const config = window.ZonaViews;
    const elements = Array.from(document.querySelectorAll('[data-zsv-post]'));
    const ids = new Set(elements.map(el => Number(el.dataset.zsvPost)).filter(id => id > 0));
    const pageId = Number(config.postId);
    if (pageId > 0) ids.add(pageId);

    function update(id, data) {
        elements.filter(el => Number(el.dataset.zsvPost) === id).forEach(el => {
            const count = el.querySelector('.zsv-count');
            if (count && typeof data.formatted === 'string') count.textContent = data.formatted;
        });
    }

    async function request(action, id, nonce) {
        const body = new URLSearchParams({action: action, post_id: String(id)});
        if (nonce) body.set('nonce', nonce);
        const response = await fetch(config.url, {
            method: 'POST', credentials: 'same-origin', cache: 'no-store',
            headers: {'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8'},
            body: body.toString()
        });
        if (!response.ok) throw new Error('Views request failed');
        const result = await response.json();
        if (!result.success) throw new Error('Views request rejected');
        return result.data;
    }

    function whenVisible() {
        return new Promise(resolve => {
            if (document.visibilityState === 'visible') { resolve(); return; }
            function visible() {
                if (document.visibilityState === 'visible') {
                    document.removeEventListener('visibilitychange', visible);
                    resolve();
                }
            }
            document.addEventListener('visibilitychange', visible);
        });
    }

    ids.forEach(async id => {
        try {
            if (id === pageId) await whenVisible();
            // Obtain a fresh token outside the cached article HTML.
            const initial = await request('zsv_boot', id);
            update(id, initial);
            // Shortcodes for other articles read their total without counting a visit.
            if (id !== pageId) return;
            const counted = await request('zsv_count', id, initial.nonce);
            update(id, counted);
        } catch (error) {
            // Keep the server-rendered number when the endpoint is unavailable.
        }
    });
}());
