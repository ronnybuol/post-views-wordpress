(function () {
    'use strict';
    const button = document.getElementById('zsv-import');
    const status = document.getElementById('zsv-import-status');
    if (!button || !status || !window.ZonaViewsImport) return;
    const config = window.ZonaViewsImport;
    const number = new Intl.NumberFormat('id-ID');
    async function request(mode, run) {
        const body = new URLSearchParams({action: 'zsv_import', nonce: config.nonce, mode: mode});
        if (run) body.set('run', run);
        const response = await fetch(config.url, {
            method: 'POST', credentials: 'same-origin', cache: 'no-store',
            headers: {'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8'},
            body: body.toString()
        });
        const result = await response.json();
        if (!response.ok || !result.success) throw new Error(result.data && result.data.message || 'Impor terputus. Klik Lanjutkan impor untuk mencoba lagi.');
        return result.data;
    }
    function progress(state) {
        status.textContent = (state.running ? 'Memproses: ' : 'Selesai: ') + number.format(state.processed) + ' dari ' + number.format(state.total) + ' artikel diperiksa; ' + number.format(state.copied) + ' artikel disalin, ' + number.format(state.skipped) + ' sudah pernah diimpor.';
    }
    button.addEventListener('click', async function () {
        button.disabled = true;
        status.textContent = 'Membaca data Post Views Counter…';
        try {
            let state = await request('start');
            progress(state);
            while (state.running) {
                state = await request('step', state.run);
                progress(state);
            }
            button.textContent = 'Periksa ulang / impor artikel baru';
        } catch (error) {
            status.textContent = error.message;
            button.textContent = 'Lanjutkan impor';
        } finally {
            button.disabled = false;
        }
    });
}());
