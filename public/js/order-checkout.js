(() => {
    const form = document.querySelector('[data-checkout-form]');
    if (!(form instanceof HTMLFormElement)) {
        return;
    }

    const feedback = document.querySelector('[data-checkout-feedback]');
    const ratePanel = document.querySelector('[data-checkout-rates]');
    const rateList = document.querySelector('[data-checkout-rate-list]');
    const payButton = document.querySelector('[data-checkout-pay]');
    const calculateButton = document.querySelector('[data-checkout-calculate]');
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const midtransReady = document.body.dataset.midtransReady === 'true';
    const amounts = {
        subtotal: Number(document.querySelector('[data-checkout-subtotal]')?.dataset.value || 0),
        fee: Number(document.querySelector('[data-checkout-platform-fee]')?.dataset.value || 0),
        shipping: Number(document.querySelector('[data-checkout-shipping]')?.dataset.value || 0),
    };
    let deliveryDirty = false;
    let hasSelectedRate = document.querySelector('[data-checkout-service]')?.textContent !== 'Belum dipilih';
    let requestInProgress = false;

    const formatRupiah = (amount) => `Rp${Number(amount).toLocaleString('id-ID')}`;

    const showFeedback = (message, isError = false) => {
        if (!(feedback instanceof HTMLElement)) {
            return;
        }

        feedback.textContent = message;
        feedback.classList.toggle('is-error', isError);
        feedback.hidden = false;
    };

    const request = async (url, options = {}) => {
        const response = await fetch(url, {
            ...options,
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                ...(options.headers || {}),
            },
            credentials: 'same-origin',
        });
        const result = await response.json();

        if (!response.ok) {
            const validationMessage = Object.values(result.errors || {}).flat().join(' ');
            throw new Error(validationMessage || result.message || 'Permintaan belum berhasil. Coba lagi.');
        }

        return result;
    };

    const setButtonBusy = (button, isBusy, busyText, idleText) => {
        if (!(button instanceof HTMLButtonElement)) {
            return;
        }

        button.disabled = isBusy;
        button.textContent = isBusy ? busyText : idleText;
    };

    const updateSummary = () => {
        const total = amounts.subtotal + amounts.fee + amounts.shipping;
        const shippingElement = document.querySelector('[data-checkout-shipping]');
        const totalElement = document.querySelector('[data-checkout-total]');

        shippingElement.textContent = formatRupiah(amounts.shipping);
        shippingElement.dataset.value = String(amounts.shipping);
        totalElement.textContent = formatRupiah(total);
        totalElement.dataset.value = String(total);
        payButton.disabled = deliveryDirty || !hasSelectedRate || !midtransReady;
    };

    const markDeliveryChanged = () => {
        deliveryDirty = true;
        payButton.disabled = true;
    };

    // Alamat profil otomatis atau manual
    const applyProfileAddress = () => {
        const contactNameInput = form.elements.namedItem('delivery_contact_name');
        const phoneInput = form.elements.namedItem('delivery_contact_phone');
        const addressInput = form.elements.namedItem('delivery_address');
        const areaNameInput = form.elements.namedItem('delivery_area_name');
        const areaIdInput = form.elements.namedItem('delivery_area_id');

        if (document.body.dataset.profileContactName && contactNameInput) {
            contactNameInput.value = document.body.dataset.profileContactName;
        }
        if (document.body.dataset.profileContactPhone && phoneInput) {
            phoneInput.value = document.body.dataset.profileContactPhone;
        }
        if (document.body.dataset.profileAddress && addressInput) {
            addressInput.value = document.body.dataset.profileAddress;
        }
        if (document.body.dataset.profileAreaId && areaIdInput) {
            areaIdInput.value = document.body.dataset.profileAreaId;
        }
        if (document.body.dataset.profileAreaName && areaNameInput) {
            areaNameInput.value = document.body.dataset.profileAreaName;
        }
        markDeliveryChanged();
        triggerAutoCalculate();
    };

    document.querySelectorAll('input[name="address_source"]').forEach((radio) => {
        radio.addEventListener('change', (e) => {
            if (e.target.value === 'profile') {
                applyProfileAddress();
            } else {
                markDeliveryChanged();
            }
        });
    });

    let autoCalculateTimer = null;
    const triggerAutoCalculate = () => {
        window.clearTimeout(autoCalculateTimer);
        autoCalculateTimer = window.setTimeout(() => {
            const courier = form.elements.namedItem('courier')?.value;
            const areaId = form.querySelector('[data-rajaongkir-area-id]')?.value;
            const address = form.elements.namedItem('delivery_address')?.value?.trim();
            const contactName = form.elements.namedItem('delivery_contact_name')?.value?.trim();
            const phone = form.elements.namedItem('delivery_contact_phone')?.value?.trim();

            if (courier && areaId && address && contactName && phone && !requestInProgress) {
                form.requestSubmit();
            }
        }, 300);
    };

    // Auto-calculate saat ganti kurir jika alamat sudah terisi
    form.elements.namedItem('courier')?.addEventListener('change', () => {
        triggerAutoCalculate();
    });

    form.addEventListener('input', markDeliveryChanged);
    form.addEventListener('change', markDeliveryChanged);
    document.addEventListener('click', (event) => {
        if (event.target instanceof Element && event.target.closest('[data-rajaongkir-suggestions] button')) {
            markDeliveryChanged();
        }
    });

    const selectRate = async (service, button) => {
        const courier = form.elements.namedItem('courier').value;
        button.disabled = true;

        try {
            const result = await request(document.body.dataset.shippingSelectUrl, {
                method: 'POST',
                body: JSON.stringify({ courier, service }),
            });
            amounts.shipping = Number(result.shipping_amount);
            hasSelectedRate = true;
            document.querySelector('[data-checkout-service]').textContent = `${courier.toUpperCase()} · ${result.service}`;
            updateSummary();
            showFeedback('Layanan pengiriman dipilih. Rincian total sudah diperbarui.');
            ratePanel.hidden = true;
        } catch (error) {
            showFeedback(error.message, true);
            button.disabled = false;
        }
    };

    const renderRates = (rates) => {
        rateList.replaceChildren();

        rates.forEach((rate) => {
            const card = document.createElement('article');
            card.className = 'navora-checkout-rate';
            const details = document.createElement('div');
            const title = document.createElement('strong');
            const description = document.createElement('span');
            const price = document.createElement('strong');
            const selectButton = document.createElement('button');

            title.textContent = rate.service;
            description.textContent = `${rate.description}${rate.etd ? ` · ${rate.etd}` : ''}`;
            price.textContent = formatRupiah(rate.cost);
            selectButton.className = 'btn navora-app-outline';
            selectButton.type = 'button';
            selectButton.textContent = 'Pilih';
            selectButton.addEventListener('click', () => selectRate(rate.service, selectButton));
            details.append(title, description);
            card.append(details, price, selectButton);
            rateList.append(card);
        });

        ratePanel.hidden = rates.length === 0;
    };

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (requestInProgress) {
            return;
        }

        const values = Object.fromEntries(new FormData(form).entries());
        const areaId = form.querySelector('[data-rajaongkir-area-id]');
        if (!areaId.value) {
            form.querySelector('[data-location-validation]').hidden = false;
            showFeedback('Pilih area tujuan dari daftar hasil pencarian lokasi.', true);
            return;
        }

        requestInProgress = true;
        setButtonBusy(calculateButton, true, 'Menyimpan alamat...', 'Simpan alamat & hitung ongkir');
        let deliveryDetailsSaved = false;

        try {
            await request(document.body.dataset.deliveryDetailsUrl, {
                method: 'PUT',
                body: JSON.stringify({
                    delivery_contact_name: values.delivery_contact_name.trim(),
                    delivery_contact_phone: values.delivery_contact_phone.trim(),
                    delivery_address: values.delivery_address.trim(),
                    delivery_area_id: values.delivery_area_id,
                    delivery_area_name: values.delivery_area_name.trim(),
                }),
            });
            deliveryDetailsSaved = true;
            amounts.shipping = 0;
            hasSelectedRate = false;
            document.querySelector('[data-checkout-service]').textContent = 'Belum dipilih';
            document.querySelector('[data-current-delivery-address]').textContent = values.delivery_address.trim();
            document.querySelector('[data-current-delivery-area]').textContent = values.delivery_area_name.trim();
            updateSummary();

            const courier = values.courier;
            if (!courier) {
                throw new Error('Pilih jasa ekspedisi sebelum menghitung ongkir.');
            }

            showFeedback('Menghitung pilihan layanan pengiriman...');
            const ratesUrl = new URL(document.body.dataset.shippingRatesUrl, window.location.origin);
            ratesUrl.searchParams.set('courier', courier);
            const result = await request(ratesUrl.toString());

            if (!Array.isArray(result.rates) || result.rates.length === 0) {
                throw new Error('Ekspedisi ini belum menyediakan layanan. Pilih ekspedisi lain.');
            }

            deliveryDirty = false;
            renderRates(result.rates);
            showFeedback(result.simulated
                ? 'Tarif simulasi tersedia untuk ukuran paket apa pun.'
                : 'Pilih salah satu layanan pengiriman yang tersedia.');
        } catch (error) {
            deliveryDirty = !deliveryDetailsSaved;
            updateSummary();
            showFeedback(error.message, true);
        } finally {
            requestInProgress = false;
            setButtonBusy(calculateButton, false, 'Menyimpan alamat...', 'Simpan alamat & hitung ongkir');
        }
    });

    payButton?.addEventListener('click', async () => {
        if (requestInProgress || deliveryDirty || !midtransReady || !(window.snap && typeof window.snap.pay === 'function')) {
            showFeedback('Checkout Midtrans belum siap. Pastikan kunci Midtrans sudah dikonfigurasi, lalu muat ulang halaman.', true);
            return;
        }

        requestInProgress = true;
        payButton.disabled = true;
        payButton.textContent = 'Menyiapkan pembayaran...';

        try {
            const result = await request(document.body.dataset.paymentUrl, { method: 'POST' });
            if (!result.token) {
                throw new Error('Midtrans tidak mengembalikan token pembayaran.');
            }

            window.snap.pay(result.token, {
                onSuccess: () => refreshPayment('Pembayaran berhasil; status sedang diverifikasi Midtrans.'),
                onPending: () => refreshPayment('Pembayaran sedang diproses Midtrans.'),
                onError: () => refreshPayment('Pembayaran belum berhasil. Status sedang diperiksa.'),
                onClose: () => refreshPayment('Popup ditutup. Status pembayaran sudah diperiksa.'),
            });
        } catch (error) {
            requestInProgress = false;
            payButton.disabled = false;
            payButton.textContent = 'Bayar via Midtrans';
            showFeedback(error.message, true);
        }
    });

    const refreshPayment = async (message) => {
        try {
            const result = await request(document.body.dataset.paymentRefreshUrl, { method: 'POST' });
            if (result.payment_status === 'paid') {
                showFeedback('Pembayaran berhasil diverifikasi. Terima kasih telah bertransaksi.');
                window.setTimeout(() => {
                    window.location.assign(document.body.dataset.workspaceUrl);
                }, 1800);
                return;
            }

            showFeedback(message);
        } catch (error) {
            showFeedback(error.message || 'Status pembayaran belum dapat diperiksa.', true);
        } finally {
            requestInProgress = false;
            payButton.disabled = false;
            payButton.textContent = 'Bayar via Midtrans';
        }
    };
})();
