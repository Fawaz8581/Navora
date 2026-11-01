(() => {
    const locationsUrl = document.body?.dataset.rajaongkirLocationsUrl;
    if (!locationsUrl) {
        return;
    }

    const pendingLookups = new WeakMap();

    const clearSuggestions = (picker) => {
        picker.querySelector('[data-rajaongkir-suggestions]')?.remove();
    };

    const renderSuggestions = (picker, destinations, hiddenInput, textInput) => {
        clearSuggestions(picker);
        const list = document.createElement('div');
        list.className = 'navora-location-suggestions';
        list.dataset.rajaongkirSuggestions = '';
        list.setAttribute('role', 'listbox');

        destinations.forEach((destination) => {
            const option = document.createElement('button');
            option.type = 'button';
            option.className = 'navora-location-option';
            option.textContent = destination.label;
            option.setAttribute('role', 'option');
            option.addEventListener('click', () => {
                textInput.value = destination.label;
                hiddenInput.value = destination.id;
                textInput.removeAttribute('aria-invalid');
                picker.querySelector('[data-location-validation]')?.setAttribute('hidden', '');
                clearSuggestions(picker);
            });
            list.append(option);
        });

        if (destinations.length === 0) {
            const empty = document.createElement('p');
            empty.className = 'navora-location-empty';
            empty.textContent = 'Lokasi tidak ditemukan. Coba nama kecamatan atau kota lain.';
            list.append(empty);
        }

        picker.append(list);
    };

    document.addEventListener('input', (event) => {
        const textInput = event.target;
        if (!(textInput instanceof HTMLInputElement) || !textInput.matches('[data-rajaongkir-location]')) {
            return;
        }

        const picker = textInput.closest('[data-location-picker]');
        const hiddenInput = picker?.querySelector('[data-rajaongkir-area-id]');
        if (!picker || !(hiddenInput instanceof HTMLInputElement)) {
            return;
        }

        hiddenInput.value = '';
        textInput.removeAttribute('aria-invalid');
        picker.querySelector('[data-location-validation]')?.setAttribute('hidden', '');
        const search = textInput.value.trim();
        const previousLookup = pendingLookups.get(textInput);
        if (previousLookup) {
            window.clearTimeout(previousLookup);
        }

        clearSuggestions(picker);
        if (search.length < 3) {
            return;
        }

        const lookup = window.setTimeout(async () => {
            try {
                const url = new URL(locationsUrl, window.location.origin);
                url.searchParams.set('search', search);
                const response = await fetch(url, {
                    headers: { Accept: 'application/json' },
                    credentials: 'same-origin',
                });
                const result = await response.json();
                if (!response.ok) {
                    throw new Error(result.message || 'Lokasi belum berhasil dicari.');
                }
                if (textInput.value.trim() === search) {
                    renderSuggestions(picker, result.destinations || [], hiddenInput, textInput);
                }
            } catch (error) {
                console.error('Navora could not search RajaOngkir locations.', error);
                clearSuggestions(picker);
                const message = document.createElement('p');
                message.className = 'navora-location-empty';
                message.textContent = error.message || 'Pencarian lokasi gagal. Coba lagi.';
                message.dataset.rajaongkirSuggestions = '';
                picker.append(message);
            }
        }, 300);
        pendingLookups.set(textInput, lookup);
    });

    document.addEventListener('click', (event) => {
        if (!(event.target instanceof Element)) {
            return;
        }

        document.querySelectorAll('[data-location-picker]').forEach((picker) => {
            if (!picker.contains(event.target)) {
                clearSuggestions(picker);
            }
        });
    });
})();
