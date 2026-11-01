(() => {
    const section = document.querySelector('[data-vendor-subscription]');
    if (!(section instanceof HTMLElement)) {
        return;
    }

    const dismissButton = section.querySelector('[data-vendor-subscription-dismiss]');
    dismissButton?.addEventListener('click', () => {
        section.dataset.vendorSubscriptionDismissed = 'true';
        section.hidden = true;
    });

    const button = section.querySelector('[data-vendor-subscription-checkout]');
    if (!(button instanceof HTMLButtonElement)) {
        return;
    }

    const refreshPayment = async (subscriptionId) => {
        const template = section.dataset.refreshTemplate || '';
        const url = template.replace('__SUBSCRIPTION__', encodeURIComponent(subscriptionId));
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
            },
            credentials: 'same-origin',
        });
        const result = await response.json();
        if (!response.ok) {
            throw new Error(result.message || 'Status pembayaran belum dapat diperiksa.');
        }

        if (result.is_active) {
            section.querySelector('p')?.replaceChildren(
                document.createTextNode(`Paket aktif sampai ${new Date(result.ends_at).toLocaleDateString('id-ID')}. Profil tetap harus disetujui admin untuk tampil sebagai vendor terverifikasi.`),
            );
            button.remove();
        } else {
            window.alert(result.payment_status === 'pending'
                ? 'Pembayaran masih menunggu konfirmasi Midtrans.'
                : 'Pembayaran belum berhasil. Silakan coba kembali.');
        }
    };

    button.addEventListener('click', async () => {
        if (!window.snap || typeof window.snap.pay !== 'function') {
            window.alert('Popup Midtrans belum tersedia. Muat ulang halaman atau hubungi admin.');
            return;
        }

        button.disabled = true;
        try {
            const response = await fetch(section.dataset.checkoutUrl || '', {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                },
                credentials: 'same-origin',
            });
            const result = await response.json();
            if (!response.ok) {
                throw new Error(result.message || 'Checkout Midtrans belum berhasil dibuat.');
            }
            if (!result.token || !result.subscription_id) {
                throw new Error('Midtrans tidak mengembalikan informasi paket vendor yang valid.');
            }

            let refreshStarted = false;
            const verify = async () => {
                if (refreshStarted) {
                    return;
                }
                refreshStarted = true;
                try {
                    await refreshPayment(result.subscription_id);
                } catch (error) {
                    console.error('Navora could not verify the vendor subscription.', error);
                    window.alert(error.message || 'Status pembayaran belum dapat diperiksa.');
                } finally {
                    button.disabled = false;
                }
            };

            window.snap.pay(result.token, {
                onSuccess: verify,
                onPending: verify,
                onError: verify,
                onClose: verify,
            });
        } catch (error) {
            console.error('Navora could not start the vendor subscription checkout.', error);
            window.alert(error.message || 'Checkout Midtrans gagal dibuat. Coba lagi.');
            button.disabled = false;
        }
    });
})();
