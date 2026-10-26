const form = document.querySelector('[data-patrol-assignment]');

if (form) {
    const area = form.elements.forest_area_id;
    const list = form.querySelector('[data-checkpoints]');
    const state = form.querySelector('[data-checkpoint-state]');
    const submit = form.querySelector('[data-assign-submit]');
    let pending;

    const updateSubmit = () => {
        submit.disabled = !form.elements.ranger_id.value || !list.querySelector('input:checked');
    };
    form.addEventListener('change', updateSubmit);
    area.addEventListener('change', async () => {
        pending?.abort();
        const current = new AbortController();
        pending = current;
        list.replaceChildren();
        submit.disabled = true;
        const url = area.selectedOptions[0]?.dataset.checkpointsUrl;
        state.textContent = url ? 'Memuat checkpoint…' : 'Pilih area hutan.';
        if (!url) return;
        try {
            const response = await fetch(url, { signal: current.signal, headers: { Accept: 'application/json' } });
            if (!response.ok) throw new Error('Checkpoint tidak dapat dimuat. Pilih kembali area untuk mencoba lagi.');
            const checkpoints = await response.json();
            if (pending !== current) return;
            checkpoints.forEach((checkpoint, index) => {
                const label = document.createElement('label');
                label.className = 'flex items-start gap-2';
                const input = document.createElement('input');
                input.type = 'checkbox';
                input.name = 'checkpoint_ids[]';
                input.value = checkpoint.id;
                const text = document.createElement('span');
                text.textContent = `${index + 1}. ${checkpoint.code} — ${checkpoint.name}`;
                label.append(input, text);
                list.append(label);
            });
            state.textContent = checkpoints.length ? '' : 'Area ini belum memiliki checkpoint.';
        } catch (error) {
            if (error.name !== 'AbortError' && pending === current) state.textContent = error.message;
        }
        updateSubmit();
    });
    form.addEventListener('submit', () => {
        submit.disabled = true;
        submit.textContent = 'Menyimpan penugasan…';
    });
    window.addEventListener('pageshow', updateSubmit);
    updateSubmit();
}
