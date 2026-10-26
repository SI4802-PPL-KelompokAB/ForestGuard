const execution = document.querySelector('[data-patrol-execution]');
const timer = document.querySelector('[data-patrol-duration]');

if (timer?.dataset.running === 'true') {
    const initialSeconds = Number(timer.dataset.seconds);
    const initialTime = performance.now();
    setInterval(() => {
        const seconds = initialSeconds + Math.max(0, Math.floor((performance.now() - initialTime) / 1000));
        timer.textContent = [Math.floor(seconds / 3600), Math.floor(seconds / 60) % 60, seconds % 60]
            .map(value => String(value).padStart(2, '0')).join(':');
    }, 1000);
}

if (execution) {
    const message = execution.querySelector('[data-execution-message]');
    const refresh = execution.querySelector('[data-state-refresh]');
    const dialog = execution.querySelector('[data-finish-dialog]');
    const controls = execution.querySelectorAll('button');
    let busy = false;
    const warning = 'Perubahan belum dapat dikonfirmasi. Periksa kembali status patroli setelah koneksi tersedia.';
    const setDisabled = value => controls.forEach(button => { button.disabled = value; });
    const readState = async () => {
        const response = await fetch(execution.dataset.stateUrl, {
            headers: { Accept: 'application/json' }, cache: 'no-store', signal: AbortSignal.timeout(15000),
        });
        if (!response.ok) throw new Error(warning);
        return response.json();
    };
    execution.querySelector('[data-finish-open]')?.addEventListener('click', () => dialog.showModal());
    execution.querySelector('[data-finish-cancel]')?.addEventListener('click', () => dialog.close());
    execution.querySelectorAll('[data-patrol-mutation]').forEach(form => {
        form.addEventListener('submit', async event => {
            event.preventDefault();
            if (busy) return;
            busy = true;
            setDisabled(true);
            dialog?.close();
            message.textContent = 'Memeriksa kondisi terbaru…';
            try {
                const current = await readState();
                if (current.patrol.status !== execution.dataset.status) {
                    window.location.reload();
                    return;
                }
                message.textContent = 'Menunggu konfirmasi server…';
                const response = await fetch(form.action, {
                    method: 'POST', body: new FormData(form), headers: { Accept: 'application/json' },
                    signal: AbortSignal.timeout(15000),
                });
                const result = await response.json();
                if (!response.ok) throw new Error(result.message || warning);
                window.location.assign(execution.dataset.stateUrl);
            } catch (error) {
                message.textContent = error.name === 'TimeoutError' || error instanceof TypeError || error instanceof SyntaxError ? warning : error.message;
                refresh.hidden = false;
                busy = false;
                setDisabled(!navigator.onLine);
            }
        });
    });
    window.addEventListener('offline', () => {
        setDisabled(true);
        message.textContent = warning;
        refresh.hidden = false;
    });
    window.addEventListener('online', async () => {
        try {
            await readState();
            window.location.reload();
        } catch {
            message.textContent = warning;
        }
    });
}
