(() => {
    'use strict';

    const authenticatedRole = document.body.dataset.authRole;
    const authenticatedName = document.body.dataset.authName;
    const authenticatedUserId = document.body.dataset.authUserId;
    const storageKey = authenticatedUserId
        ? `navora-prototype-v1:user-${authenticatedUserId}`
        : 'navora-prototype-v1';
    const modal = document.querySelector('[data-modal]');
    const modalForm = document.querySelector('[data-modal-form]');
    const modalFields = document.querySelector('[data-modal-fields]');
    const productDialog = document.querySelector('[data-product-dialog]');
    const toast = document.querySelector('[data-toast]');
    const urlParams = new URLSearchParams(window.location.search);
    const knownRoles = ['umkm', 'vendor', 'partner'];
    const knownPages = ['overview', 'vendors', 'listings', 'advertisements', 'notifications', 'groups', 'orders', 'rfq', 'assistant', 'programs', 'account'];
    const orderStatuses = [
        { status: 'menunggu_konfirmasi', label: 'Menunggu konfirmasi' },
        { status: 'diproses', label: 'Diproses' },
        { status: 'dalam_produksi', label: 'Dalam produksi' },
        { status: 'dikirim', label: 'Dikirim' },
        { status: 'diterima', label: 'Diterima' },
    ];
    let orderVendors = [];
    let orderLoadPromise = null;
    let orderListData = [];
    let orderNextPageUrl = null;
    let orderSubmissionInProgress = false;
    let vendorListings = [];
    let vendorListingsVerified = false;
    let vendorListingsLoadPromise = null;
    let listingSubmissionInProgress = false;
    let vendorCatalogTimer = 0;
    let vendorOrigin = null;
    let vendorCatalogRequestController = null;
    let listingMediaValidationInProgress = false;
    let simulationRefreshTimer = 0;
    let activeModalHandler = null;
    let toastTimer = 0;
    let lastFocusedElement = null;
    let activeProductContext = null;

    const emptyState = () => ({
        role: 'umkm',
        groups: {},
        createdGroups: [],
        rfqs: [],
        quotes: [],
        listings: [],
        interests: [],
        profile: {},
        verificationRequested: false,
    });

    const reportStorageError = (error) => {
        console.error('Navora demo could not access local storage.', error);
        showToast('Penyimpanan lokal tidak tersedia. Perubahan hanya berlaku selama halaman ini terbuka.');
    };

    const loadState = () => {
        try {
            const rawState = window.localStorage.getItem(storageKey);

            if (!rawState) {
                return emptyState();
            }

            const parsedState = JSON.parse(rawState);

            if (!parsedState || typeof parsedState !== 'object' || Array.isArray(parsedState)) {
                throw new TypeError('The stored Navora state must be an object.');
            }

            const state = { ...emptyState(), ...parsedState };
            state.groups = state.groups && typeof state.groups === 'object' ? state.groups : {};
            state.createdGroups = Array.isArray(state.createdGroups) ? state.createdGroups : [];
            state.rfqs = Array.isArray(state.rfqs) ? state.rfqs : [];
            state.quotes = Array.isArray(state.quotes) ? state.quotes : [];
            state.listings = Array.isArray(state.listings) ? state.listings : [];
            state.interests = Array.isArray(state.interests) ? state.interests : [];
            state.profile = state.profile && typeof state.profile === 'object' ? state.profile : {};

            return state;
        } catch (error) {
            reportStorageError(error);
            return emptyState();
        }
    };

    let state = loadState();

    if (knownRoles.includes(authenticatedRole || '')) {
        state.role = authenticatedRole;
    }

    if (authenticatedName) {
        state.profile.name = authenticatedName;
    }

    function showToast(message) {
        if (!toast) {
            return;
        }

        toast.textContent = message;
        toast.classList.add('is-visible');
        window.clearTimeout(toastTimer);
        toastTimer = window.setTimeout(() => {
            toast.classList.remove('is-visible');
        }, 3300);
    }

    const saveState = () => {
        try {
            window.localStorage.setItem(storageKey, JSON.stringify(state));
        } catch (error) {
            reportStorageError(error);
        }
    };

    const textElement = (tagName, className, text) => {
        const element = document.createElement(tagName);

        if (className) {
            element.className = className;
        }

        element.textContent = text;

        return element;
    };

    const setText = (selector, value) => {
        const element = document.querySelector(selector);

        if (element) {
            element.textContent = value;
        }
    };

    const roleLabel = (role) => ({
        umkm: 'Brand UMKM',
        vendor: 'Vendor kemasan',
        partner: 'Mitra ekosistem',
    })[role] ?? 'Brand UMKM';

    const updateRoleContent = () => {
        const role = knownRoles.includes(state.role) ? state.role : 'umkm';
        state.role = role;
        const roleSelect = document.querySelector('[data-role-select]');

        if (roleSelect) {
            roleSelect.value = role;
        }

        document.querySelectorAll('[data-roles]').forEach((item) => {
            const availableRoles = item.dataset.roles.split(',');
            item.hidden = !availableRoles.includes(role);
        });

        document.querySelectorAll('[data-role-content]').forEach((item) => {
            item.hidden = item.dataset.roleContent !== role;
        });

        document.querySelectorAll('[data-role-select]').forEach((item) => {
            item.setAttribute('aria-label', `Peran demo: ${roleLabel(role)}`);
        });

        document.querySelectorAll('[data-role-caption]').forEach((item) => {
            item.textContent = `Ruang ${role === 'umkm' ? 'UMKM' : role === 'vendor' ? 'vendor' : 'mitra'}`;
        });

        document.querySelectorAll('[data-welcome-title]').forEach((item) => {
            item.textContent = role === 'vendor'
                ? 'Selamat datang, mitra vendor!'
                : role === 'partner'
                    ? 'Selamat datang, mitra ekosistem!'
                    : `Selamat datang${state.profile.name ? `, ${state.profile.name}` : ', pemilik brand'}!`;
        });

        document.querySelectorAll('[data-welcome-copy]').forEach((item) => {
            item.textContent = role === 'vendor'
                ? 'Kelola contoh katalog dan lihat permintaan brand yang mungkin sesuai dengan layananmu.'
                : role === 'partner'
                    ? 'Jelajahi ide program dan peluang kolaborasi untuk mendukung perkembangan UMKM.'
                    : 'Temukan vendor kemasan yang sesuai dan lihat peluang pemesanan bersama.';
        });

        document.querySelectorAll('[data-primary-action]').forEach((item) => {
            const page = role === 'vendor' ? 'listings' : role === 'partner' ? 'programs' : 'vendors';
            const icon = role === 'vendor' ? 'bi-box-seam' : role === 'partner' ? 'bi-buildings' : 'bi-search';
            const label = role === 'vendor' ? 'Kelola katalog' : role === 'partner' ? 'Lihat program' : 'Jelajahi vendor';

            item.dataset.openPage = page;
            item.innerHTML = `<i class="bi ${icon}" aria-hidden="true"></i> ${label}`;
        });

        document.querySelectorAll('[data-rfq-heading]').forEach((item) => {
            item.textContent = role === 'vendor' ? 'Tinjau permintaan dari brand' : 'Bandingkan penawaran vendor';
        });

        document.querySelectorAll('[data-rfq-description]').forEach((item) => {
            item.textContent = role === 'vendor'
                ? 'Lihat contoh kebutuhan brand dan siapkan tanggapan yang sesuai kapasitasmu.'
                : 'Kirim kebutuhanmu dan tinjau respons vendor dalam satu ruang.';
        });

        const buyerList = document.querySelector('[data-rfq-list]');
        const vendorInbox = document.querySelector('[data-vendor-inbox]');

        if (buyerList) {
            buyerList.hidden = role === 'vendor';
        }

        if (vendorInbox) {
            vendorInbox.hidden = role !== 'vendor';
        }

        const createRequestButton = document.querySelector('[data-action="create-rfq"]');

        if (createRequestButton) {
            createRequestButton.hidden = role === 'vendor';
            createRequestButton.innerHTML = role === 'vendor'
                ? '<i class="bi bi-plus-lg" aria-hidden="true"></i> Buat permintaan'
                : '<i class="bi bi-plus-lg" aria-hidden="true"></i> Buat permintaan';
        }

        const orderCreateActions = document.querySelector('[data-order-create-actions]');
        if (orderCreateActions) {
            orderCreateActions.hidden = role !== 'umkm';
        }

        const accountName = state.profile.name || (role === 'vendor' ? 'PouchKita Studio' : role === 'partner' ? 'Mitra UMKM Nusantara' : 'Usaha Kopi Nusantara');
        const accountLocation = state.profile.location || (role === 'vendor' ? 'Bandung, Jawa Barat' : role === 'partner' ? 'Indonesia' : 'Bandung, Jawa Barat');
        setText('[data-account-name]', accountName);
        setText('[data-account-role-label]', `${roleLabel(role)} · ${accountLocation.split(',')[0]}`);
        setText('[data-account-type]', roleLabel(role));
        setText('[data-account-location]', accountLocation);
        setText('[data-account-avatar]', accountName.trim().charAt(0).toUpperCase() || 'N');
        setText('[data-stat-listings]', String(vendorListings.length + state.listings.length));
        setText('[data-stat-incoming]', String(2 + state.quotes.length));
        setText('[data-verification-summary]', state.verificationRequested ? 'Demo dikirim' : 'Draft');

        document.querySelectorAll('[data-page-panel]').forEach((panel) => {
            const page = panel.dataset.pagePanel;
            const rolePageAllowed = page === 'overview' || page === 'account'
                || (role === 'umkm' && ['vendors', 'groups', 'orders', 'rfq', 'assistant'].includes(page))
                || (role === 'vendor' && ['listings', 'advertisements', 'notifications', 'groups', 'orders', 'rfq'].includes(page))
                || (role === 'partner' && page === 'programs');
            const tab = document.querySelector(`[data-page="${page}"]`);

            if (tab) {
                tab.hidden = !rolePageAllowed;
            }

            if (!rolePageAllowed && panel.classList.contains('is-active')) {
                showPage('overview');
            }
        });

        saveState();
    };

    const showPage = (page) => {
        if (!knownPages.includes(page)) {
            return;
        }

        const matchingTab = document.querySelector(`[data-page="${page}"]`);

        if (!matchingTab || matchingTab.hidden) {
            return;
        }

        document.querySelectorAll('[data-page-panel]').forEach((panel) => {
            const isActive = panel.dataset.pagePanel === page;
            panel.classList.toggle('is-active', isActive);
            panel.hidden = !isActive;
        });

        document.querySelectorAll('[data-page]').forEach((tab) => {
            const isActive = tab.dataset.page === page;
            tab.classList.toggle('is-active', isActive);
            tab.setAttribute('aria-selected', String(isActive));
        });

        setText('[data-breadcrumb-current]', matchingTab.textContent.trim());
        window.scrollTo({ top: 0, behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' });

        if (page === 'orders') {
            loadOrders();
        }

        if (page === 'vendors') {
            updateVendorFilters();
        }

        if (page === 'listings') {
            loadVendorListings();
        }
    };

    const createModalField = (field) => {
        const wrapper = textElement('div', 'navora-modal-field', '');
        const label = textElement('label', 'navora-form-label', field.label);
        const input = document.createElement(field.type === 'textarea' ? 'textarea' : field.type === 'select' ? 'select' : 'input');
        input.name = field.name;
        input.required = Boolean(field.required);
        input.id = `modal-${field.name}`;
        label.htmlFor = input.id;

        if (field.type === 'select') {
            field.options.forEach((option) => {
                const optionElement = document.createElement('option');
                optionElement.value = option.value;
                optionElement.textContent = option.label;
                input.append(optionElement);
            });
        } else if (field.type !== 'textarea') {
            input.type = field.type || 'text';
        }

        if (field.accept && input instanceof HTMLInputElement) {
            input.accept = field.accept;
        }

        if (field.multiple && input instanceof HTMLInputElement) {
            input.multiple = true;
        }

        if (field.placeholder) {
            input.placeholder = field.placeholder;
        }

        if (field.value !== undefined) {
            input.value = field.value;
        }

        if (field.min !== undefined) {
            input.min = String(field.min);
        }

        if (field.max !== undefined) {
            input.max = String(field.max);
        }

        if (field.step !== undefined) {
            input.step = String(field.step);
        }

        if (field.rows !== undefined && input instanceof HTMLTextAreaElement) {
            input.rows = field.rows;
        }

        input.classList.add('form-control');
        wrapper.append(label, input);
        if (field.help) {
            wrapper.append(textElement('small', 'navora-modal-field-help', field.help));
        }

        return wrapper;
    };

    const openModal = ({ title, description, fields = [], submit = 'Simpan di demo', icon = 'bi-stars', onSubmit = () => true }) => {
        if (!modal || !modalForm || !modalFields) {
            showToast('Dialog demo belum tersedia. Muat ulang halaman untuk mencoba kembali.');
            return;
        }

        lastFocusedElement = document.activeElement;
        setText('[data-modal-title]', title);
        setText('[data-modal-description]', description);
        setText('[data-modal-submit]', submit);

        const iconElement = document.querySelector('[data-modal-icon]');

        if (iconElement) {
            iconElement.className = `bi ${icon}`;
        }

        modalFields.replaceChildren(...fields.map(createModalField));
        activeModalHandler = onSubmit;
        modal.hidden = false;
        document.body.style.overflow = 'hidden';
        window.requestAnimationFrame(() => {
            const firstInput = modalFields.querySelector('input, select, textarea');
            (firstInput || modal.querySelector('[data-close-modal]'))?.focus();
        });
    };

    const closeModal = () => {
        if (!modal) {
            return;
        }

        modal.hidden = true;
        document.body.style.overflow = '';
        activeModalHandler = null;

        if (lastFocusedElement instanceof HTMLElement) {
            lastFocusedElement.focus();
        }
    };

    const readFormValues = (formData) => Object.fromEntries(formData.entries());

    const groupCardById = (id) => document.querySelector(`[data-group-card][data-id="${CSS.escape(id)}"]`);

    const updateGroupCard = (card) => {
        if (!card) {
            return;
        }

        const baseTotal = Number(card.dataset.total);
        const baseJoined = Number(card.dataset.joined);
        const contribution = Number(state.groups[card.dataset.id]?.quantity || 0);
        const currentJoined = baseJoined + contribution;
        const percentage = Math.min(100, Math.round((currentJoined / baseTotal) * 100));
        const joinedText = card.querySelector('[data-group-joined]');
        const percentageText = card.querySelector('[data-group-percent]');
        const progress = card.querySelector('[data-group-progress]');
        const joinButton = card.querySelector('[data-action="join-group"]');

        if (joinedText) {
            joinedText.textContent = currentJoined.toLocaleString('id-ID');
        }

        if (percentageText) {
            percentageText.textContent = `${percentage}%`;
        }

        if (progress) {
            progress.style.width = `${percentage}%`;
        }

        if (joinButton) {
            const isJoined = contribution > 0;
            joinButton.classList.toggle('is-joined', isJoined);
            joinButton.setAttribute('aria-pressed', String(isJoined));
            joinButton.innerHTML = isJoined
                ? 'Partisipasi demo tersimpan <i class="bi bi-check2" aria-hidden="true"></i>'
                : 'Ikut patungan <i class="bi bi-arrow-right" aria-hidden="true"></i>';
        }
    };

    const updateGroupCards = () => {
        document.querySelectorAll('[data-group-card]').forEach(updateGroupCard);
        const numberOfGroups = document.querySelectorAll('[data-group-card]').length;
        setText('[data-stat-groups]', String(numberOfGroups));
    };

    const renderCatalogVendor = (vendor) => {
        const card = document.createElement('article');
        card.className = 'navora-vendor-card';
        card.dataset.vendorCard = '';
        card.dataset.id = String(vendor.id);
        card.dataset.vendorUserId = String(vendor.id);
        card.dataset.name = vendor.name || 'Vendor';
        card.dataset.product = vendor.category || '';
        card.dataset.tags = `${vendor.description || ''} ${vendor.materials || ''}`;
        card.dataset.moq = String(vendor.minimum_order_quantity || '');
        card.dataset.material = String(vendor.materials || '').toLocaleLowerCase('id');
        card.dataset.location = String(vendor.location || '').toLocaleLowerCase('id');
        card.dataset.price = vendor.price_per_unit ?? '';
        card.dataset.sample = String(vendor.has_sample);
        card.dataset.certificates = String(vendor.certificate_count || 0);
        card.dataset.verified = 'true';
        card.dataset.description = vendor.description || '';

        const art = textElement('div', 'navora-vendor-art art-pouch', '');
        const categoryText = `${vendor.category || ''} ${vendor.description || ''}`.toLocaleLowerCase('id');
        const fallbackImage = categoryText.includes('botol')
            ? '/images/products/bottle.jpg'
            : categoryText.includes('box') || categoryText.includes('karton')
                ? '/images/products/cardboard.jpg'
                : '/images/products/coffee.jpg';
        const vendorImage = vendor.cover_image_url || (Array.isArray(vendor.images) ? vendor.images[0] : null);
        const productImage = textElement('img', '', '');
        productImage.src = vendorImage || fallbackImage;
        productImage.alt = vendor.cover_image_url
            ? `Foto cover ${vendor.name || 'vendor'}`
            : vendorImage
                ? `Foto sampel produk ${vendor.name || 'vendor'}`
                : `Foto referensi ${vendor.category || 'produk vendor'}`;
        productImage.loading = 'lazy';
        productImage.addEventListener('error', () => {
            if (vendorImage && productImage.src !== fallbackImage) {
                productImage.src = fallbackImage;
                return;
            }

            productImage.remove();
        }, { once: true });
        art.append(productImage, textElement('small', '', vendor.cover_image_url
            ? 'COVER VENDOR'
            : vendorImage
                ? 'FOTO SAMPEL VENDOR'
                : 'FOTO REFERENSI'));
        const verified = textElement('span', 'navora-verified-tag', '');
        verified.append(textElement('i', 'bi bi-patch-check-fill', ''), document.createTextNode(' Terverifikasi'));
        art.append(verified);

        const content = textElement('div', 'navora-vendor-content', '');
        const heading = textElement('div', 'navora-vendor-name-row', '');
        const nameAndLocation = textElement('div', '', '');
        nameAndLocation.append(
            textElement('h2', '', vendor.name || 'Vendor'),
            textElement('span', 'navora-vendor-location', vendor.location || 'Lokasi belum diisi'),
        );
        const details = [
            `MOQ mulai ${Number(vendor.minimum_order_quantity || 0).toLocaleString('id-ID')} pcs`,
            vendor.price_per_unit === null
                ? 'Harga belum dicantumkan'
                : `${new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(Number(vendor.price_per_unit))} / pcs (indikatif)`,
            vendor.distance_km === null ? 'Jarak belum tersedia' : `± ${vendor.distance_km} km (garis lurus)`,
        ];
        if (vendor.profile_image_url) {
            const profileImage = textElement('img', 'navora-vendor-profile-avatar', '');
            profileImage.src = vendor.profile_image_url;
            profileImage.alt = `Foto profil ${vendor.name || 'vendor'}`;
            profileImage.loading = 'lazy';
            heading.append(profileImage);
        } else {
            heading.append(textElement(
                'span',
                'navora-vendor-profile-avatar navora-vendor-profile-fallback',
                (vendor.name || 'V').charAt(0).toLocaleUpperCase('id'),
            ));
        }
        heading.append(nameAndLocation);
        const tags = textElement('div', 'navora-vendor-tags', '');
        [
            vendor.category,
            vendor.materials,
            vendor.has_sample ? 'Sampel tersedia' : 'Sampel belum tersedia',
            vendor.certificate_count ? `${vendor.certificate_count} sertifikat terdaftar` : 'Tidak ada sertifikat terdaftar',
        ].filter(Boolean).forEach((tag) => tags.append(textElement('span', '', tag)));
        content.append(heading, textElement('p', '', vendor.description || 'Vendor belum menambahkan deskripsi.'));
        if (Array.isArray(vendor.listings) && vendor.listings.length) {
            const activeProducts = textElement('div', 'navora-vendor-active-listings', '');
            activeProducts.append(textElement('strong', '', 'Katalog produk'));
            const productList = textElement('div', 'navora-catalog-products', '');

            vendor.listings.forEach((listing) => {
                const productButton = textElement('button', 'navora-catalog-product', '');
                productButton.type = 'button';
                productButton.dataset.action = 'product-detail';
                productButton.dataset.vendorUserId = String(vendor.id);
                productButton.dataset.vendor = vendor.name || 'Vendor';
                productButton.dataset.vendorPrice = vendor.price_per_unit ?? '';
                productButton.dataset.productListing = JSON.stringify(listing);

                const preview = textElement('span', 'navora-catalog-product-preview', '');
                const firstMedia = Array.isArray(listing.media) ? listing.media[0] : null;
                if (firstMedia?.type === 'video') {
                    const video = document.createElement('video');
                    video.src = firstMedia.url;
                    video.muted = true;
                    video.playsInline = true;
                    video.preload = 'metadata';
                    video.setAttribute('aria-label', `Video ${listing.name}`);
                    preview.append(video);
                } else if (firstMedia?.url) {
                    const image = document.createElement('img');
                    image.src = firstMedia.url;
                    image.alt = `Foto ${listing.name}`;
                    image.loading = 'lazy';
                    preview.append(image);
                } else {
                    const icon = textElement('i', 'bi bi-box-seam', '');
                    icon.setAttribute('aria-hidden', 'true');
                    preview.append(icon);
                }

                const productCopy = textElement('span', 'navora-catalog-product-copy', '');
                const priceSummary = listing.price_per_unit
                    ? `${formatRupiah(listing.price_per_unit)} / pcs · ${listing.pricing_type === 'final' ? 'Harga final' : 'Bisa nego'}`
                    : 'Bisa nego · Harga belum dicantumkan';
                productCopy.append(
                    textElement('strong', '', listing.name),
                    textElement('small', '', `MOQ ${Number(listing.minimum_order_quantity).toLocaleString('id-ID')} pcs · ${priceSummary}`),
                );
                productButton.append(preview, productCopy, textElement('i', 'bi bi-chevron-right', ''));
                productList.append(productButton);
            });

            activeProducts.append(productList);
            content.append(activeProducts);
        }
        content.append(tags);

        const specs = textElement('div', 'navora-vendor-specs', '');
        details.forEach((detail) => specs.append(textElement('span', '', detail)));
        content.append(specs);

        const actions = textElement('div', 'navora-vendor-actions', '');
        const detailButton = textElement('button', 'btn navora-app-outline', 'Lihat detail');
        detailButton.type = 'button';
        detailButton.dataset.action = 'vendor-detail';
        detailButton.dataset.vendor = vendor.name || 'Vendor';
        detailButton.dataset.vendorDescription = vendor.description || '';
        detailButton.dataset.vendorSpecifications = details.join(' · ');
        const quoteButton = textElement('button', 'btn navora-app-primary', 'Minta penawaran');
        quoteButton.type = 'button';
        quoteButton.dataset.action = 'rfq';
        quoteButton.dataset.vendor = vendor.name || 'Vendor';
        quoteButton.dataset.vendorUserId = String(vendor.id);
        actions.append(detailButton, quoteButton);
        content.append(actions);
        card.append(art, content);

        return card;
    };

    const loadVendorCatalog = async () => {
        const grid = document.querySelector('[data-vendor-grid]');
        const loading = document.querySelector('[data-vendor-loading]');
        const empty = document.querySelector('[data-vendor-empty]');
        const error = document.querySelector('[data-vendor-error]');

        if (!grid) {
            return;
        }

        vendorCatalogRequestController?.abort();
        const requestController = new AbortController();
        vendorCatalogRequestController = requestController;
        if (empty) {
            empty.hidden = true;
        }
        const params = new URLSearchParams();
        const filterValues = {
            search: document.querySelector('[data-vendor-filter="search"]')?.value.trim(),
            category: document.querySelector('[data-vendor-filter="category"]')?.value,
            max_moq: document.querySelector('[data-vendor-filter="moq"]')?.value,
            material: document.querySelector('[data-vendor-filter="material"]')?.value,
            location: document.querySelector('[data-vendor-filter="location"]')?.value,
            max_price: document.querySelector('[data-vendor-filter="price"]')?.value,
            distance_km: document.querySelector('[data-vendor-filter="distance"]')?.value,
            has_sample: document.querySelector('[data-vendor-filter="sample"]')?.checked ? '1' : '',
            has_certificate: document.querySelector('[data-vendor-filter="certificate"]')?.checked ? '1' : '',
            sort: document.querySelector('[data-vendor-sort]')?.value,
        };
        if (filterValues.distance_km && !vendorOrigin) {
            grid.replaceChildren();
            setText('[data-vendor-result-count]', '0');
            if (error) {
                error.replaceChildren(
                    textElement('span', '', 'Untuk memakai radius jarak, izinkan lokasi terlebih dahulu.'),
                    document.createTextNode(' '),
                );
                const locationButton = textElement('button', 'navora-text-action', 'Gunakan lokasi saya');
                locationButton.type = 'button';
                locationButton.dataset.action = 'use-current-location';
                error.append(locationButton);
                error.hidden = false;
            }
            if (loading) {
                loading.hidden = true;
            }
            return;
        }
        Object.entries(filterValues).forEach(([key, value]) => {
            if (value) {
                params.set(key, value);
            }
        });
        if (vendorOrigin) {
            params.set('origin_lat', String(vendorOrigin.latitude));
            params.set('origin_lng', String(vendorOrigin.longitude));
        }

        if (loading) {
            loading.hidden = false;
        }
        if (error) {
            error.hidden = true;
            error.replaceChildren();
        }
        if (empty) {
            empty.hidden = true;
        }

        try {
            const response = await requestOrderData(`${document.body.dataset.vendorCatalogUrl}?${params.toString()}`, {
                signal: requestController.signal,
            });
            const vendors = Array.isArray(response.vendors) ? response.vendors : [];
            grid.replaceChildren(...vendors.map(renderCatalogVendor));
            setText('[data-vendor-result-count]', String(vendors.length));
            if (empty) {
                empty.hidden = vendors.length > 0;
            }
        } catch (requestError) {
            if (requestError.name === 'AbortError') {
                return;
            }
            console.error('Navora could not load the vendor catalog.', requestError);
            grid.replaceChildren();
            setText('[data-vendor-result-count]', '0');
            if (error) {
                error.replaceChildren(
                    textElement('span', '', requestError.message || 'Katalog vendor tidak dapat dimuat.'),
                    document.createTextNode(' '),
                );
                const retry = textElement('button', 'navora-text-action', 'Coba lagi');
                retry.type = 'button';
                retry.dataset.action = 'retry-vendor-catalog';
                error.append(retry);
                error.hidden = false;
            }
        } finally {
            if (vendorCatalogRequestController === requestController && loading) {
                loading.hidden = true;
            }
        }
    };

    const updateVendorFilters = () => {
        if (authenticatedRole === 'umkm') {
            window.clearTimeout(vendorCatalogTimer);
            vendorCatalogTimer = window.setTimeout(loadVendorCatalog, 200);
            return;
        }

        const search = document.querySelector('[data-vendor-filter="search"]');
        const category = document.querySelector('[data-vendor-filter="category"]');
        const moq = document.querySelector('[data-vendor-filter="moq"]');
        const material = document.querySelector('[data-vendor-filter="material"]');
        const location = document.querySelector('[data-vendor-filter="location"]');
        const price = document.querySelector('[data-vendor-filter="price"]');
        const sample = document.querySelector('[data-vendor-filter="sample"]');
        const certificate = document.querySelector('[data-vendor-filter="certificate"]');
        const distance = document.querySelector('[data-vendor-filter="distance"]');
        const query = search?.value.trim().toLocaleLowerCase('id') || '';
        const note = document.querySelector('[data-catalog-note]');
        if (note && distance?.value) {
            note.textContent = 'Filter jarak hanya tersedia untuk akun UMKM yang mengizinkan penggunaan lokasi.';
        } else if (note) {
            note.textContent = 'Harga dan jarak hanya ditampilkan jika profil vendor menyediakan data harga dan koordinat.';
        }
        let visibleCount = 0;

        document.querySelectorAll('[data-vendor-card]').forEach((card) => {
            const searchable = `${card.dataset.name} ${card.dataset.product} ${card.dataset.tags} ${card.dataset.location}`.toLocaleLowerCase('id');
            const validQuery = !query || searchable.includes(query);
            const validCategory = !category?.value || searchable.includes(category.value);
            const validMoq = !moq?.value || Number(card.dataset.moq) <= Number(moq.value);
            const validMaterial = !material?.value || card.dataset.material.split(' ').includes(material.value);
            const validLocation = !location?.value || card.dataset.location === location.value;
            const validPrice = !price?.value || Number(card.dataset.price) <= Number(price.value);
            const validSample = !sample?.checked || card.dataset.sample === 'true';
            const validCertificate = !certificate?.checked || Number(card.dataset.certificates || 0) > 0;
            const validDistance = !distance?.value;
            const visible = validQuery && validCategory && validMoq && validMaterial && validLocation && validPrice && validSample && validCertificate && validDistance;

            card.hidden = !visible;

            if (visible) {
                visibleCount += 1;
            }
        });

        setText('[data-vendor-result-count]', String(visibleCount));
        const emptyStateElement = document.querySelector('[data-vendor-empty]');

        if (emptyStateElement) {
            emptyStateElement.hidden = visibleCount > 0;
        }
    };

    const sortVendorCards = () => {
        if (authenticatedRole === 'umkm') {
            updateVendorFilters();
            return;
        }

        const sortSelect = document.querySelector('[data-vendor-sort]');
        const grid = document.querySelector('[data-vendor-grid]');

        if (!sortSelect || !grid) {
            return;
        }

        const cards = Array.from(grid.querySelectorAll('[data-vendor-card]'));
        const sortKey = sortSelect.value;

        cards.sort((left, right) => {
            if (sortKey === 'price') {
                return Number(left.dataset.price) - Number(right.dataset.price);
            }

            if (sortKey === 'moq') {
                return Number(left.dataset.moq) - Number(right.dataset.moq);
            }

            return left.dataset.id.localeCompare(right.dataset.id);
        });

        cards.forEach((card) => grid.append(card));
    };

    const renderMiniVendors = () => {
        const container = document.querySelector('[data-mini-vendor-list]');

        if (!container) {
            return;
        }

        const vendors = [
            { name: 'PouchKita Studio', detail: 'Pouch custom · MOQ 500 pcs · Bandung', rating: '4,9', icon: 'bi-bag', color: '' },
            { name: 'Kemas Prima', detail: 'Botol PET · MOQ 1.000 pcs · Jakarta', rating: '4,8', icon: 'bi-cup-straw', color: 'mini-green' },
            { name: 'Cetak Bumi', detail: 'Box kraft · MOQ 300 pcs · Yogyakarta', rating: '4,7', icon: 'bi-box2', color: '' },
        ];

        container.replaceChildren(...vendors.map((vendor) => {
            const item = document.createElement('button');
            item.type = 'button';
            item.className = 'navora-mini-vendor';
            item.dataset.openPage = 'vendors';
            const icon = textElement('span', `navora-mini-vendor-icon ${vendor.color}`, '');
            const iconElement = textElement('i', `bi ${vendor.icon}`, '');
            iconElement.setAttribute('aria-hidden', 'true');
            icon.append(iconElement);
            const copy = textElement('span', 'navora-mini-vendor-copy', '');
            copy.append(textElement('strong', '', vendor.name), textElement('small', '', vendor.detail));
            const rating = textElement('span', 'navora-mini-vendor-rating', '');
            const star = textElement('i', 'bi bi-star-fill', '');
            star.setAttribute('aria-hidden', 'true');
            rating.append(star, document.createTextNode(` ${vendor.rating}`));
            item.append(icon, copy, rating);
            return item;
        }));
    };

    const renderVendorListings = () => {
        const container = document.querySelector('[data-vendor-listings]');

        if (!container) {
            return;
        }

        container.replaceChildren();

        const listings = [
            ...vendorListings.map((listing) => ({ ...listing, localDraft: false })),
            ...state.listings.map((listing) => ({
                ...listing,
                client_reference: listing.id,
                minimum_order_quantity: listing.moq,
                localDraft: true,
                status: 'draft',
            })),
        ];

        listings.forEach((listing) => {
            const card = document.createElement('article');
            card.className = 'navora-listing-card';
            card.dataset.serverListing = String(!listing.localDraft);
            const productDetails = `${listing.name} ${listing.description} ${listing.material}`.toLocaleLowerCase('id');
            const imageSource = productDetails.includes('botol')
                ? '/images/products/bottle.jpg'
                : productDetails.includes('box') || productDetails.includes('karton') || listing.category === 'printing'
                    ? '/images/products/cardboard.jpg'
                    : '/images/products/coffee.jpg';
            const art = textElement('span', `navora-listing-art ${listing.category === 'printing' ? 'art-box' : listing.category === 'maklon' ? 'art-maklon' : 'art-pouch'}`, '');
            const firstMedia = Array.isArray(listing.media) ? listing.media[0] : null;
            if (firstMedia?.type === 'video') {
                const productVideo = document.createElement('video');
                productVideo.src = firstMedia.url;
                productVideo.controls = true;
                productVideo.playsInline = true;
                productVideo.preload = 'metadata';
                productVideo.setAttribute('aria-label', `Video produk ${listing.name}`);
                art.append(productVideo);
            } else {
                const productImage = textElement('img', '', '');
                productImage.src = firstMedia?.url || imageSource;
                productImage.alt = firstMedia ? `Foto produk ${listing.name}` : `Foto referensi produk ${listing.name}`;
                productImage.loading = 'lazy';
                art.append(productImage);
            }
            const statusLabel = listing.status === 'active'
                ? 'Aktif'
                : listing.status === 'pending'
                    ? 'Menunggu ACC admin'
                    : 'Draft';
            const content = textElement('div', '', '');
            const priceSummary = listing.price_per_unit
                ? `${formatRupiah(listing.price_per_unit)} / pcs · ${listing.pricing_type === 'final' ? 'Harga final' : 'Bisa nego'}`
                : 'Bisa nego · harga belum dicantumkan';
            content.append(
                textElement('h2', '', listing.name),
                textElement('p', '', `MOQ ${Number(listing.minimum_order_quantity || 0).toLocaleString('id-ID')} pcs · ${priceSummary} · ${listing.material} · ${listing.description}`),
                textElement('small', '', listing.localDraft
                    ? 'Draft demo tersimpan di browser ini, belum masuk ke admin'
                    : listing.status === 'pending'
                        ? 'Sudah diajukan dan menunggu pemeriksaan admin'
                        : 'Tersimpan di akun vendor'),
            );
            const isActive = listing.status === 'active';
            card.append(
                art,
                content,
                textElement('span', `navora-status-pill ${isActive ? 'status-open' : 'status-waiting'}`, statusLabel),
            );

            if (isActive && authenticatedRole === 'vendor' && vendorListingsVerified) {
                const editButton = textElement(
                    'button',
                    'btn navora-app-outline navora-listing-review-submit',
                    'Edit katalog',
                );
                editButton.type = 'button';
                editButton.dataset.action = 'edit-vendor-listing';
                editButton.dataset.listingReference = String(listing.id);
                card.append(editButton);
            }

            if (!isActive && authenticatedRole === 'vendor'
                && (listing.localDraft || listing.status === 'draft' || listing.status === 'rejected')
                && vendorListingsVerified) {
                const submitButton = textElement(
                    'button',
                    'btn navora-app-outline navora-listing-review-submit',
                    'Kirim ke admin',
                );
                submitButton.type = 'button';
                submitButton.dataset.action = 'submit-vendor-listing';
                submitButton.dataset.listingReference = String(listing.localDraft ? listing.client_reference : listing.id);
                submitButton.dataset.localDraft = String(listing.localDraft);
                card.append(submitButton);
            }

            container.append(card);
        });

        const empty = document.querySelector('[data-vendor-listings-empty]');
        if (empty) {
            empty.hidden = listings.length > 0;
        }

        setText('[data-stat-listings]', String(listings.length));
    };

    const renderStoredListings = () => {
        renderVendorListings();
    };

    const loadVendorListings = async () => {
        if (authenticatedRole !== 'vendor' || !authenticatedUserId) {
            return;
        }

        if (vendorListingsLoadPromise) {
            return vendorListingsLoadPromise;
        }

        const loading = document.querySelector('[data-vendor-listings-loading]');
        const error = document.querySelector('[data-vendor-listings-error]');
        if (loading) {
            loading.hidden = false;
        }
        if (error) {
            error.hidden = true;
            error.replaceChildren();
        }

        vendorListingsLoadPromise = (async () => {
            try {
                const payload = await requestOrderData(document.body.dataset.vendorListingsUrl);
                vendorListings = Array.isArray(payload.listings) ? payload.listings : [];
                vendorListingsVerified = Boolean(payload.verified);
                renderVendorListings();

                const notice = document.querySelector('[data-vendor-listing-notice] div');
                if (notice) {
                    notice.replaceChildren(
                        textElement('strong', '', vendorListingsVerified
                            ? 'Profil terverifikasi · katalog baru perlu ACC admin'
                            : 'Tunggu verifikasi profil sebelum mengajukan katalog'),
                        textElement('p', '', vendorListingsVerified
                            ? 'Katalog baru perlu ditinjau admin. Perubahan katalog yang sudah aktif langsung ditampilkan tanpa ACC ulang.'
                            : 'Lengkapi dokumen usaha dan tunggu persetujuan admin atas profil vendor.'),
                    );
                }
            } catch (requestError) {
                console.error('Navora could not load vendor listings.', requestError);
                if (error) {
                    error.replaceChildren(textElement(
                        'span',
                        '',
                        requestError.message || 'Katalog tidak dapat dimuat. Coba muat ulang.',
                    ));
                    const retry = textElement('button', 'navora-text-action', 'Coba lagi');
                    retry.type = 'button';
                    retry.dataset.action = 'retry-vendor-listings';
                    error.append(retry);
                    error.hidden = false;
                }
            } finally {
                if (loading) {
                    loading.hidden = true;
                }
                vendorListingsLoadPromise = null;
            }
        })();

        return vendorListingsLoadPromise;
    };

    const renderStoredRfqs = () => {
        const container = document.querySelector('[data-rfq-list]');

        if (container) {
            container.querySelectorAll('[data-stored-rfq]').forEach((card) => card.remove());
            state.rfqs.forEach((rfq) => {
                const card = document.createElement('article');
                card.className = 'navora-rfq-card';
                card.dataset.storedRfq = 'true';
                card.dataset.id = rfq.id;
                const header = document.createElement('div');
                header.className = 'navora-rfq-card-head';
                const icon = textElement('span', 'navora-rfq-icon', '');
                const iconElement = textElement('i', 'bi bi-send', '');
                iconElement.setAttribute('aria-hidden', 'true');
                icon.append(iconElement);
                const title = document.createElement('div');
                title.append(
                    textElement('h2', '', rfq.title),
                    textElement('span', '', `${rfq.vendor || 'Terbuka untuk vendor'} · Dibuat di demo`),
                );
                header.append(icon, title, textElement('span', 'navora-status-pill status-waiting', rfq.quote ? '1 respons demo' : 'Menunggu respons'));
                const details = document.createElement('div');
                details.className = 'navora-rfq-details';
                [
                    ['Jenis', rfq.category],
                    ['Jumlah target', `${Number(rfq.quantity).toLocaleString('id-ID')} pcs`],
                    ['Material', rfq.material],
                    ['Lokasi', rfq.location],
                ].forEach(([label, value]) => {
                    const item = document.createElement('span');
                    item.append(textElement('small', '', value ? label : ''), textElement('strong', '', value || 'Belum diisi'));
                    details.append(item);
                });
                card.append(header, details);

                if (rfq.quote) {
                    const quote = document.createElement('div');
                    quote.className = 'navora-quote-row';
                    const vendor = document.createElement('div');
                    vendor.className = 'navora-quote-vendor';
                    vendor.append(
                        textElement('span', 'navora-vendor-avatar avatar-green', 'VD'),
                        textElement('span', '', ''),
                    );
                    const quoteText = vendor.querySelector('span:last-child');
                    quoteText.append(
                        textElement('strong', '', rfq.quote.vendor),
                        textElement('small', '', `${rfq.quote.price} · MOQ ${rfq.quote.moq} pcs · indikatif`),
                    );
                    quote.append(vendor);
                    card.append(quote);
                }

                container.prepend(card);
            });
        }

        renderVendorInbox();

        setText('[data-rfq-count]', String(2 + state.rfqs.length));
        setText('[data-rfq-quotes]', String(3 + state.quotes.length));
        setText('[data-stat-rfqs]', String(2 + state.rfqs.length));
    };

    const renderVendorInbox = () => {
        const container = document.querySelector('[data-vendor-inbox]');

        if (!container) {
            return;
        }

        container.querySelectorAll('[data-stored-incoming]').forEach((item) => item.remove());
        state.rfqs.forEach((rfq) => {
            const item = document.createElement('article');
            item.className = 'navora-incoming-rfq';
            item.dataset.storedIncoming = 'true';
            const icon = textElement('span', 'navora-rfq-icon', '');
            const iconElement = textElement('i', 'bi bi-box-seam', '');
            iconElement.setAttribute('aria-hidden', 'true');
            icon.append(iconElement);
            const copy = textElement('div', 'navora-incoming-copy', '');
            copy.append(
                textElement('h3', '', rfq.title),
                textElement('p', '', `${Number(rfq.quantity).toLocaleString('id-ID')} pcs · ${rfq.material} · ${rfq.location}`),
                textElement('span', '', 'Permintaan demo lokal · belum dikirim'),
            );
            const button = textElement('button', 'btn navora-app-primary', rfq.quote ? 'Respons tersimpan' : 'Buat penawaran');
            button.type = 'button';
            button.dataset.action = 'respond-rfq';
            button.dataset.rfq = rfq.id;
            button.disabled = Boolean(rfq.quote);
            item.append(icon, copy, button);
            container.append(item);
        });
    };

    const renderStoredGroups = () => {
        const grid = document.querySelector('[data-group-grid]');

        if (!grid) {
            return;
        }

        grid.querySelectorAll('[data-stored-group]').forEach((card) => card.remove());
        state.createdGroups.forEach((group) => {
            const card = document.createElement('article');
            card.className = 'navora-group-card';
            card.dataset.groupCard = 'true';
            card.dataset.storedGroup = 'true';
            card.dataset.id = group.id;
            card.dataset.name = group.name;
            card.dataset.total = String(group.total);
            card.dataset.joined = '0';
            const top = document.createElement('div');
            top.className = 'navora-group-card-top';
            top.append(textElement('span', 'navora-group-category', group.category), textElement('span', 'navora-group-status', 'Patungan baru'));
            card.append(top, textElement('h2', '', group.name), textElement('p', '', `Dibuat di demo oleh ${state.profile.name || 'UMKM'}. Rincian akhir perlu dikonfirmasi dengan vendor.`));
            const vendorRow = document.createElement('div');
            vendorRow.className = 'navora-group-vendor';
            vendorRow.append(
                textElement('span', 'navora-vendor-avatar avatar-blue', state.profile.name?.charAt(0).toUpperCase() || 'U'),
                textElement('span', '', ''),
            );
            const nameArea = vendorRow.querySelector('span:last-child');
            nameArea.append(textElement('strong', '', 'Penyelenggara demo'), textElement('small', '', 'Informasi vendor belum dipilih'));
            card.append(vendorRow);
            const progressLabel = document.createElement('div');
            progressLabel.className = 'navora-group-progress-label';
            const progressAmount = textElement('strong', '', '');
            progressAmount.append(document.createTextNode('0 / '), textElement('span', '', Number(group.total).toLocaleString('id-ID')), document.createTextNode(' pcs'));
            progressLabel.append(textElement('span', '', 'Progres patungan'), progressAmount);
            const progress = document.createElement('div');
            progress.className = 'progress navora-progress';
            const bar = document.createElement('div');
            bar.className = 'progress-bar';
            bar.dataset.groupProgress = 'true';
            bar.style.width = '0%';
            progress.append(bar);
            card.append(progressLabel, progress);
            const footer = document.createElement('div');
            footer.className = 'navora-group-foot';
            footer.append(textElement('span', '', '0% terkumpul'), textElement('span', '', 'Baru dibuat'));
            const join = document.createElement('button');
            join.className = 'btn navora-app-primary navora-group-join';
            join.type = 'button';
            join.dataset.action = 'join-group';
            join.dataset.group = group.id;
            join.innerHTML = 'Ikut patungan <i class="bi bi-arrow-right" aria-hidden="true"></i>';
            card.append(footer, join);
            grid.prepend(card);
        });
    };

    const modalTextField = (name, label, placeholder, required = true, type = 'text') => ({
        name, label, placeholder, required, type,
    });

    const openProductDetail = (button) => {
        if (!(productDialog instanceof HTMLDialogElement)) {
            showToast('Detail produk tidak tersedia. Muat ulang halaman untuk mencoba kembali.');
            return;
        }

        let listing;
        try {
            listing = JSON.parse(button.dataset.productListing || '');
        } catch (error) {
            console.error('Navora could not read product details.', error);
            showToast('Detail produk tidak dapat dibuka. Muat ulang katalog dan coba lagi.');
            return;
        }

        const vendor = button.dataset.vendor || 'Vendor';
        const vendorUserId = button.dataset.vendorUserId || '';
        activeProductContext = { vendor, vendorUserId, listing };

        const gallery = productDialog.querySelector('[data-product-gallery]');
        gallery?.replaceChildren();
        const media = Array.isArray(listing.media) ? listing.media : [];

        media.forEach((item, index) => {
            const frame = textElement('figure', 'navora-product-media-item', '');
            if (item.type === 'video') {
                const video = document.createElement('video');
                video.src = item.url;
                video.controls = true;
                video.playsInline = true;
                video.preload = 'metadata';
                video.setAttribute('aria-label', `Video ${listing.name} ${index + 1}`);
                frame.append(video);
            } else {
                const image = document.createElement('img');
                image.src = item.url;
                image.alt = `Foto ${listing.name} ${index + 1}`;
                image.loading = 'lazy';
                frame.append(image);
            }
            gallery?.append(frame);
        });

        if (media.length === 0) {
            const empty = textElement('div', 'navora-product-media-empty', '');
            empty.append(
                textElement('i', 'bi bi-box-seam', ''),
                textElement('span', '', 'Vendor belum menambahkan foto atau video produk.'),
            );
            gallery?.append(empty);
        }

        setText('[data-product-vendor]', vendor);
        setText('[data-product-title]', listing.name || 'Produk vendor');
        setText('[data-product-description]', listing.description || 'Vendor belum menambahkan deskripsi.');

        const specifications = productDialog.querySelector('[data-product-specifications]');
        specifications?.replaceChildren();
        [
            ['Jenis layanan', listing.category],
            ['Material', listing.material],
            ['Minimum pemesanan', `${Number(listing.minimum_order_quantity || 0).toLocaleString('id-ID')} pcs`],
        ].forEach(([label, value]) => {
            const row = document.createElement('div');
            row.append(textElement('dt', '', label), textElement('dd', '', value || 'Belum diisi'));
            specifications?.append(row);
        });

        const priceContainer = productDialog.querySelector('[data-product-price]');
        priceContainer?.replaceChildren();
        const pricePerUnit = Number(listing.price_per_unit);
        if (Number.isFinite(pricePerUnit) && pricePerUnit > 0) {
            priceContainer?.append(textElement(
                'strong',
                '',
                `${formatRupiah(pricePerUnit)} / pcs`,
            ));
            priceContainer?.append(textElement(
                'span',
                '',
                listing.pricing_type === 'final'
                    ? 'Harga final per pcs dari vendor.'
                    : 'Harga awal per pcs; masih bisa dinegosiasikan dengan vendor.',
            ));
        } else {
            priceContainer?.append(textElement('strong', '', 'Harga bisa dinegosiasikan'));
            priceContainer?.append(textElement('span', '', 'Kirim permintaan penawaran untuk menerima harga dan total biaya.'));
        }

        productDialog.showModal();
    };

    const submitCatalogQuote = async (vendorUserId, values) => {
        if (orderSubmissionInProgress) {
            return;
        }
        orderSubmissionInProgress = true;
        const submitButton = modalForm?.querySelector('[data-modal-submit]');
        if (submitButton) {
            submitButton.disabled = true;
        }

        try {
            await requestOrderData(document.body.dataset.ordersQuoteUrl, {
                method: 'POST',
                body: JSON.stringify({
                    vendor_user_id: Number(vendorUserId),
                    product_name: values.product_name.trim(),
                    quantity: Number(values.quantity),
                    packaging_details: values.packaging_details.trim(),
                    delivery_address: values.delivery_address.trim(),
                    notes: values.notes?.trim() || null,
                }),
            });
            closeModal();
            showPage('orders');
            await loadOrders();
            showToast('Permintaan harga sudah dikirim ke vendor. Pantau balasan dan harga final di menu Pesanan.');
        } catch (requestError) {
            console.error('Navora could not create a catalog order.', requestError);
            showToast(requestError.message || 'Pesanan tidak dapat dibuat. Periksa data dan coba lagi.');
        } finally {
            orderSubmissionInProgress = false;
            if (submitButton) {
                submitButton.disabled = false;
            }
        }
    };

    const createRfq = (vendor = '', vendorUserId = '', product = null) => {
        const isCatalogVendor = Boolean(vendorUserId && authenticatedRole === 'umkm');
        const fields = isCatalogVendor
            ? [
                { ...modalTextField('product_name', 'Nama produk atau kemasan', 'Contoh: pouch kopi 200 g'), value: product?.name || '' },
                { name: 'quantity', label: 'Jumlah pesanan (pcs)', placeholder: 'Contoh: 1.000', type: 'number', min: product ? product.minimum_order_quantity : 1, step: 1, required: true, value: product ? String(product.minimum_order_quantity) : '' },
                { name: 'packaging_details', label: 'Spesifikasi dan kebutuhan khusus', placeholder: 'Ukuran, material, warna, atau kebutuhan sampel', type: 'textarea', rows: 3, required: true, value: product ? `${product.material}. ${product.description}` : '' },
                { name: 'delivery_address', label: 'Alamat pengiriman', placeholder: 'Alamat lengkap penerima', type: 'textarea', rows: 2, required: true },
                { name: 'notes', label: 'Catatan (opsional)', placeholder: 'Informasi tambahan', type: 'textarea', rows: 2, required: false },
            ]
            : [
                modalTextField('title', 'Produk atau kemasan', 'Contoh: standing pouch kopi 200 g'),
                { name: 'quantity', label: 'Jumlah target (pcs)', placeholder: 'Contoh: 1.000', type: 'number', min: 1, required: true },
                modalTextField('material', 'Material atau kebutuhan', 'Contoh: kraft + laminasi, butuh sampel'),
                modalTextField('location', 'Kota tujuan', 'Contoh: Bandung'),
                { name: 'vendor', label: 'Vendor tujuan (opsional)', placeholder: 'Pilih vendor yang dituju', type: 'select', required: false, options: [{ value: vendor, label: vendor || 'Terbuka untuk vendor' }, { value: '', label: 'Terbuka untuk vendor lain' }, { value: 'PouchKita Studio', label: 'PouchKita Studio' }, { value: 'Kemas Prima', label: 'Kemas Prima' }, { value: 'Cetak Bumi', label: 'Cetak Bumi' }, { value: 'Rasa Racik Maklon', label: 'Rasa Racik Maklon' }] },
                { name: 'note', label: 'Catatan (opsional)', placeholder: 'Spesifikasi atau pertanyaan untuk vendor', type: 'textarea', required: false, rows: 3 },
            ];

        openModal({
            title: 'Buat permintaan penawaran',
            description: isCatalogVendor
                ? `Permintaan produk${product ? ` "${product.name}"` : ''} dikirim kepada ${vendor}. Vendor akan membalas dengan harga final di menu Pesanan.`
                : 'Catat kebutuhan produk. Dalam demo ini, permintaan hanya tersimpan di browser dan tidak dikirim kepada vendor.',
            icon: 'bi-chat-square-text',
            submit: isCatalogVendor ? 'Kirim permintaan penawaran' : 'Simpan permintaan demo',
            fields,
            onSubmit: (values) => {
                if (isCatalogVendor) {
                    const quantity = Number(values.quantity);
                    if (!Number.isSafeInteger(quantity) || quantity < 1) {
                        showToast('Masukkan jumlah pesanan sebagai bilangan bulat positif.');
                        return false;
                    }
                    submitCatalogQuote(vendorUserId, values);
                    return false;
                }

                const rfq = {
                    id: `rfq-${Date.now()}`,
                    title: values.title.trim(),
                    quantity: Number(values.quantity),
                    material: values.material.trim() || 'Belum ditentukan',
                    location: values.location.trim() || 'Belum ditentukan',
                    category: values.title.toLowerCase().includes('label') ? 'Label & percetakan' : 'Kemasan',
                    vendor: values.vendor || '',
                    note: values.note || '',
                    createdAt: new Date().toISOString(),
                };
                state.rfqs.unshift(rfq);
                saveState();
                renderStoredRfqs();
                    showToast('Permintaan tersimpan di browser demo. Belum dikirim kepada vendor.');
                return true;
            },
        });

    };

    const createGroup = () => {
        openModal({
            title: 'Buat patungan MOQ',
            description: 'Buat papan minat lokal untuk mengumpulkan kebutuhan. Ini tidak membuat pesanan atau komitmen pembayaran.',
            icon: 'bi-people',
            submit: 'Buat patungan demo',
            fields: [
                modalTextField('name', 'Nama kebutuhan', 'Contoh: pouch sambal 150 g'),
                { name: 'category', label: 'Jenis kemasan', placeholder: 'Pilih jenis', type: 'select', required: true, options: [{ value: 'Pouch', label: 'Pouch' }, { value: 'Botol', label: 'Botol' }, { value: 'Box', label: 'Box / karton' }, { value: 'Lainnya', label: 'Lainnya' }] },
                { name: 'total', label: 'Target jumlah minimum (pcs)', placeholder: 'Contoh: 1.000', type: 'number', min: 2, required: true },
            ],
            onSubmit: (values) => {
                const total = Number(values.total);

                if (!Number.isSafeInteger(total) || total < 2) {
                    showToast('Masukkan target jumlah yang valid (minimal 2 pcs).');
                    return false;
                }

                const id = `group-${Date.now()}`;
                state.createdGroups.unshift({
                    id,
                    name: values.name.trim(),
                    total,
                    category: values.category,
                });
                saveState();
                renderStoredGroups();
                updateGroupCards();
                showToast('Papan patungan demo dibuat lokal. Vendor dan pesanan belum dikonfirmasi.');
                return true;
            },
        });
    };

    const refreshCsrfToken = async () => {
        const response = await fetch(window.location.href, {
            method: 'GET',
            credentials: 'same-origin',
            cache: 'no-store',
            headers: { Accept: 'text/html' },
        });

        if (!response.ok) {
            throw new Error('Sesi tidak dapat diperbarui. Muat ulang halaman dan coba lagi.');
        }

        const page = new DOMParser().parseFromString(await response.text(), 'text/html');
        const refreshedUserId = page.body?.dataset.authUserId;

        if (authenticatedUserId && refreshedUserId !== authenticatedUserId) {
            throw new Error('Sesi berakhir. Masuk kembali untuk melanjutkan.');
        }

        const token = page.querySelector('meta[name="csrf-token"]')?.content;
        const csrfMeta = document.querySelector('meta[name="csrf-token"]');

        if (!token || !csrfMeta) {
            throw new Error('Token keamanan tidak dapat diperbarui. Muat ulang halaman dan coba lagi.');
        }

        csrfMeta.content = token;
    };

    const requestOrderData = async (url, options = {}, canRetryCsrf = true) => {
        const headers = {
            Accept: 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
            ...options.headers,
        };

        if (options.body && !(options.body instanceof FormData)) {
            headers['Content-Type'] = 'application/json';
        }

        const response = await fetch(url, {
            credentials: 'same-origin',
            ...options,
            headers,
        });

        const method = (options.method || 'GET').toUpperCase();

        if (response.status === 419 && method !== 'GET' && canRetryCsrf) {
            await refreshCsrfToken();
            return requestOrderData(url, options, false);
        }

        let payload;

        try {
            payload = await response.json();
        } catch (error) {
            throw new Error('Respons server tidak dapat dibaca. Coba muat ulang halaman.');
        }

        if (!response.ok) {
            const validationMessage = Object.values(payload.errors || {}).flat()[0];
            const message = response.status === 401
                ? 'Sesi berakhir. Masuk kembali untuk mengakses pesanan.'
                : validationMessage || payload.message || 'Permintaan pesanan gagal. Coba lagi.';
            throw new Error(message);
        }

        return payload;
    };

    const orderUrl = (template, orderId) => template.replace('__ORDER__', encodeURIComponent(String(orderId)));

    const statusClassFor = (status) => ({
        menunggu_konfirmasi: 'status-waiting',
        diproses: 'status-processing',
        dalam_produksi: 'status-production',
        dikirim: 'status-shipped',
        diterima: 'status-received',
        dibatalkan: 'status-cancelled',
    })[status] || 'status-unknown';

    const displayOrderDate = (value) => {
        if (!value) {
            return 'Waktu tidak tersedia';
        }

        const date = new Date(value);

        return Number.isNaN(date.getTime())
            ? 'Waktu tidak tersedia'
            : new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium', timeStyle: 'short' }).format(date);
    };

    const formatRupiah = (amount) => `Rp${Number(amount).toLocaleString('id-ID')}`;

    const renderOrderCard = (order) => {
        const card = document.createElement('article');
        card.className = 'navora-order-card';
        card.dataset.orderId = String(order.id);

        const header = document.createElement('div');
        header.className = 'navora-order-card-head';
        const heading = document.createElement('div');
        heading.append(
            textElement('span', 'navora-panel-kicker', order.type_label || 'Jenis pesanan tidak dikenal'),
            textElement('h2', '', order.product_name || 'Produk tanpa nama'),
            textElement('small', '', `Dibuat ${displayOrderDate(order.created_at)} · ${order.creator_name || 'Pengguna'}`),
        );
        const status = textElement(
            'span',
            `navora-status-pill ${statusClassFor(order.status)}`,
            order.status_label || 'Status tidak dikenal',
        );
        status.setAttribute('aria-label', `Status pesanan: ${order.status_label || 'Status tidak dikenal'}`);
        const statusContainer = document.createElement('div');
        statusContainer.className = 'navora-order-status-wrap';
        statusContainer.append(status);
        if (order.is_simulated) {
            statusContainer.append(textElement('span', 'navora-simulation-badge', 'SIMULASI OTOMATIS'));
        }
        header.append(heading, statusContainer);
        card.append(header);

        if (order.is_simulated) {
            card.append(textElement(
                'p',
                'navora-simulation-notice',
                'Status ini bergerak otomatis sebagai simulasi; bukan konfirmasi produksi, pengiriman, atau penerimaan nyata.',
            ));
        }

        const details = document.createElement('div');
        details.className = 'navora-order-details';
        [
            ['Jumlah', `${Number(order.quantity).toLocaleString('id-ID')} pcs`],
            ['Vendor', order.vendor_name || 'Belum dipilih'],
            ...(order.unit_price ? [['Harga final', `${formatRupiah(order.unit_price)} / pcs`]] : []),
            ...(order.total_amount ? [['Total pesanan', formatRupiah(order.total_amount)]] : []),
            ...(order.current_payment_status && order.current_payment_status !== 'not_required'
                ? [['Pembayaran', ({
                    awaiting_quote: 'Menunggu harga final dari vendor',
                    pending: 'Menunggu pembayaran',
                    paid: 'Lunas',
                    failed: 'Pembayaran gagal; silakan coba lagi',
                })[order.current_payment_status] || 'Status pembayaran tidak dikenal']]
                : []),
            ['Spesifikasi', order.packaging_details || 'Belum tersedia'],
            ...(order.delivery_address ? [['Alamat pengiriman', order.delivery_address]] : []),
            ...(order.notes ? [['Catatan', order.notes]] : []),
        ].forEach(([label, value]) => {
            const item = document.createElement('div');
            item.append(textElement('small', '', label), textElement('strong', '', value));
            details.append(item);
        });
        card.append(details);

        if (order.type === 'patungan') {
            const committed = Number(order.committed_quantity) || 0;
            const target = Number(order.quantity) || 0;
            const percentage = target > 0 ? Math.min(100, Math.round((committed / target) * 100)) : 0;
            const group = document.createElement('section');
            group.className = 'navora-order-group';
            const progressHeading = document.createElement('div');
            progressHeading.className = 'navora-order-progress-heading';
            progressHeading.append(
                textElement('strong', '', 'Progres patungan'),
                textElement('span', '', `${committed.toLocaleString('id-ID')} / ${target.toLocaleString('id-ID')} pcs · ${percentage}%`),
            );
            const progress = document.createElement('div');
            progress.className = 'navora-order-progress';
            progress.setAttribute('role', 'progressbar');
            progress.setAttribute('aria-label', 'Jumlah peserta yang sudah bergabung');
            progress.setAttribute('aria-valuemin', '0');
            progress.setAttribute('aria-valuemax', String(target));
            progress.setAttribute('aria-valuenow', String(committed));
            const progressBar = document.createElement('span');
            progressBar.style.width = `${percentage}%`;
            progress.append(progressBar);
            group.append(progressHeading, progress);

            if (order.participants?.length) {
                const participantList = document.createElement('ul');
                participantList.className = 'navora-order-participants';
                order.participants.forEach((participant) => {
                    participantList.append(textElement(
                        'li',
                        '',
                        `${participant.name || 'Pengguna'} · ${Number(participant.quantity).toLocaleString('id-ID')} pcs${participant.payment_amount ? ` · ${formatRupiah(participant.payment_amount)} · ${participant.payment_status === 'paid' ? 'Lunas' : 'Menunggu pembayaran'}` : ''}`,
                    ));
                });
                group.append(participantList);
            }

            card.append(group);
        }

        const tracking = document.createElement('section');
        tracking.className = 'navora-order-tracking';
        tracking.append(textElement('h3', '', 'Tahapan pesanan'));
        const knownStatusIndex = orderStatuses.findIndex((item) => item.status === order.status);

        if (order.status === 'dibatalkan') {
            tracking.append(textElement('p', 'navora-order-cancelled', 'Pesanan dibatalkan sebelum diterima.'));
        } else if (knownStatusIndex < 0) {
            tracking.append(textElement('p', 'navora-order-unknown', 'Status tidak dikenal. Hubungi pengelola untuk memastikan pembaruan pesanan.'));
        } else {
            const steps = document.createElement('ol');
            steps.className = 'navora-order-steps';
            orderStatuses.forEach((item, index) => {
                const step = document.createElement('li');
                step.className = index < knownStatusIndex
                    ? 'is-complete'
                    : index === knownStatusIndex
                        ? 'is-current'
                        : '';
                step.append(textElement('span', '', item.label));
                steps.append(step);
            });
            tracking.append(steps);
        }

        const history = document.createElement('ol');
        history.className = 'navora-order-history';
        (order.history || []).forEach((entry) => {
            const item = document.createElement('li');
            item.append(
                textElement('strong', '', entry.status_label || 'Status tidak dikenal'),
                textElement('span', '', `${displayOrderDate(entry.created_at)} · ${entry.actor_name || 'Sistem'}`),
            );
            if (entry.note) {
                item.append(textElement('p', '', entry.note));
            }
            history.append(item);
        });
        if (!history.childElementCount) {
            history.append(textElement('li', '', 'Belum ada riwayat pembaruan.'));
        }
        tracking.append(textElement('h3', '', 'Riwayat pembaruan'), history);
        card.append(tracking);

        const actions = document.createElement('div');
        actions.className = 'navora-order-actions';
        if (order.can_set_price) {
            const priceButton = textElement('button', 'btn navora-app-outline', 'Tetapkan harga final');
            priceButton.type = 'button';
            priceButton.dataset.action = 'set-order-price';
            priceButton.dataset.order = String(order.id);
            actions.append(priceButton);
        }
        if (order.can_pay) {
            const paymentButton = textElement(
                'button',
                'btn navora-app-primary',
                `Bayar ${formatRupiah(order.payment_amount)} via Midtrans`,
            );
            paymentButton.type = 'button';
            paymentButton.dataset.action = 'checkout-order';
            paymentButton.dataset.order = String(order.id);
            actions.append(paymentButton);
        }
        if (order.can_join) {
            const joinButton = textElement('button', 'btn navora-app-primary', 'Ikut patungan');
            joinButton.type = 'button';
            joinButton.dataset.action = 'join-order';
            joinButton.dataset.order = String(order.id);
            joinButton.dataset.remaining = String(order.remaining_quantity);
            actions.append(joinButton);
        }
        (order.allowed_transitions || []).forEach((transition) => {
            const button = textElement(
                'button',
                transition.status === 'dibatalkan' ? 'btn navora-order-cancel' : 'btn navora-app-outline',
                transition.status === 'dibatalkan'
                    ? 'Batalkan pesanan'
                    : transition.status === 'diproses'
                        ? 'Konfirmasi pesanan'
                        : `Tandai ${transition.label.toLowerCase()}`,
            );
            button.type = 'button';
            button.dataset.action = 'update-order-status';
            button.dataset.order = String(order.id);
            button.dataset.status = transition.status;
            actions.append(button);
        });
        if (actions.childElementCount) {
            card.append(actions);
        }

        return card;
    };

    const renderOrders = (orders, append = false, openGroupCount = null) => {
        const list = document.querySelector('[data-orders-list]');
        const empty = document.querySelector('[data-orders-empty]');
        const more = document.querySelector('[data-orders-more]');

        if (!list || !empty) {
            return;
        }

        orderListData = append ? [...orderListData, ...orders] : orders;
        list.replaceChildren(...orderListData.map(renderOrderCard));
        empty.hidden = orderListData.length > 0;
        if (more) {
            more.hidden = !orderNextPageUrl;
            more.disabled = false;
            more.textContent = 'Muat pesanan lainnya';
        }
        if (openGroupCount !== null) {
            setText('[data-stat-groups]', String(Number(openGroupCount) || 0));
        }

        const hasActiveSimulation = orderListData.some((order) => order.is_simulated
            && !['diterima', 'dibatalkan'].includes(order.status));
        if (hasActiveSimulation && !simulationRefreshTimer) {
            simulationRefreshTimer = window.setInterval(() => {
                if (!document.hidden) {
                    loadOrders();
                }
            }, 5000);
        } else if (!hasActiveSimulation && simulationRefreshTimer) {
            window.clearInterval(simulationRefreshTimer);
            simulationRefreshTimer = 0;
        }
    };

    const loadOrders = async (loadMore = false) => {
        const loading = document.querySelector('[data-orders-loading]');
        const guest = document.querySelector('[data-orders-guest]');
        const error = document.querySelector('[data-orders-error]');
        const more = document.querySelector('[data-orders-more]');

        if (!authenticatedUserId) {
            if (loading) {
                loading.hidden = true;
            }
            if (guest) {
                guest.hidden = false;
            }
            return;
        }

        if (orderLoadPromise) {
            return orderLoadPromise;
        }

        if (loadMore && !orderNextPageUrl) {
            return;
        }

        if (loading) {
            loading.hidden = loadMore;
        }
        if (guest) {
            guest.hidden = true;
        }
        if (error) {
            error.hidden = true;
        }
        if (!loadMore) {
            orderNextPageUrl = null;
            if (more) {
                more.hidden = true;
            }
        } else if (more) {
            more.disabled = true;
            more.textContent = 'Memuat pesanan…';
        }

        orderLoadPromise = (async () => {
            try {
                const payload = await requestOrderData(loadMore ? orderNextPageUrl : document.body.dataset.ordersUrl);
                orderVendors = Array.isArray(payload.vendors) ? payload.vendors : [];
                orderNextPageUrl = payload.next_page_url || null;
                renderOrders(
                    Array.isArray(payload.orders) ? payload.orders : [],
                    loadMore,
                    Number(payload.open_group_count) || 0,
                );
            } catch (requestError) {
                console.error('Navora could not load orders.', requestError);
                if (error) {
                    error.textContent = requestError.message || 'Pesanan tidak dapat dimuat. Coba lagi.';
                    error.hidden = false;
                }
                if (more) {
                    more.disabled = false;
                    more.textContent = 'Coba muat pesanan lainnya';
                }
                showToast(requestError.message || 'Pesanan tidak dapat dimuat. Coba lagi.');
            } finally {
                if (loading) {
                    loading.hidden = true;
                }
                orderLoadPromise = null;
            }
        })();

        return orderLoadPromise;
    };

    const createOrder = async (type) => {
        if (!authenticatedUserId) {
            showToast('Masuk ke akun UMKM untuk membuat pesanan.');
            return;
        }

        await loadOrders();
        if (!orderVendors.length) {
            showToast('Belum ada vendor terverifikasi yang dapat menetapkan harga pesanan.');
            return;
        }

        const vendorOptions = [
            { value: '', label: 'Pilih vendor untuk menetapkan harga final' },
            ...orderVendors.map((vendor) => ({ value: String(vendor.id), label: vendor.name })),
        ];
        const isGroup = type === 'patungan';
        const fields = [
            modalTextField('product_name', 'Nama produk atau kemasan', 'Contoh: pouch kopi 200 g'),
            { name: 'quantity', label: isGroup ? 'Target jumlah (pcs)' : 'Jumlah pesanan (pcs)', placeholder: 'Contoh: 1.000', type: 'number', min: 1, step: 1, required: true },
            ...(isGroup
                ? [{ name: 'initial_quantity', label: 'Jumlah awal yang kamu ambil (pcs)', placeholder: 'Contoh: 100', type: 'number', min: 1, step: 1, required: true }]
                : []),
            { name: 'packaging_details', label: 'Spesifikasi kemasan', placeholder: 'Ukuran, material, bentuk, atau kebutuhan khusus', type: 'textarea', rows: 3, required: true },
            { name: 'vendor_user_id', label: 'Vendor terverifikasi', type: 'select', required: true, options: vendorOptions },
            ...(!isGroup
                ? [{ name: 'delivery_address', label: 'Alamat pengiriman', placeholder: 'Alamat lengkap penerima', type: 'textarea', rows: 2, required: true }]
                : []),
            { name: 'notes', label: 'Catatan (opsional)', placeholder: 'Informasi tambahan untuk pesanan', type: 'textarea', rows: 2, required: false },
        ];

        openModal({
            title: isGroup ? 'Buat pesanan patungan' : 'Buat pesanan langsung',
            description: isGroup
                ? 'Pilih vendor yang akan menetapkan harga final. Setelah target terpenuhi, setiap peserta membayar porsinya lewat Midtrans.'
                : 'Pilih vendor untuk menetapkan harga final. Setelah harga disetujui, pembayaran dilakukan lewat Midtrans.',
            icon: isGroup ? 'bi-people' : 'bi-box-seam',
            submit: isGroup ? 'Buat pesanan patungan' : 'Simpan pesanan',
            fields,
            onSubmit: (values) => {
                const quantity = Number(values.quantity);
                const initialQuantity = Number(values.initial_quantity);

                if (!Number.isSafeInteger(quantity) || quantity < 1
                    || (isGroup && (!Number.isSafeInteger(initialQuantity) || initialQuantity < 1 || initialQuantity > quantity))) {
                    showToast(isGroup
                        ? 'Target dan jumlah awal harus bilangan bulat; jumlah awal tidak boleh melebihi target.'
                        : 'Masukkan jumlah pesanan sebagai bilangan bulat positif.');
                    return false;
                }

                submitOrder(type, values);
                return false;
            },
        });
    };

    const submitOrder = async (type, values) => {
        if (orderSubmissionInProgress) {
            return;
        }
        orderSubmissionInProgress = true;

        const submitButton = modalForm?.querySelector('[data-modal-submit]');
        if (submitButton) {
            submitButton.disabled = true;
        }

        try {
            const payload = await requestOrderData(document.body.dataset.ordersStoreUrl, {
                method: 'POST',
                body: JSON.stringify({
                    type,
                    product_name: values.product_name.trim(),
                    quantity: Number(values.quantity),
                    initial_quantity: values.initial_quantity ? Number(values.initial_quantity) : undefined,
                    packaging_details: values.packaging_details.trim(),
                    vendor_user_id: values.vendor_user_id || null,
                    delivery_address: values.delivery_address?.trim() || null,
                    notes: values.notes?.trim() || null,
                }),
            });
            closeModal();
            showToast('Pesanan berhasil disimpan dan dapat dilacak di akunmu.');
            if (payload.order) {
                await loadOrders();
            }
        } catch (requestError) {
            console.error('Navora could not create the order.', requestError);
            showToast(requestError.message || 'Pesanan tidak dapat disimpan. Coba lagi.');
        } finally {
            orderSubmissionInProgress = false;
            if (submitButton) {
                submitButton.disabled = false;
            }
        }
    };

    const joinOrder = (orderId, remaining) => {
        if (!authenticatedUserId) {
            showToast('Masuk ke akun UMKM untuk bergabung dalam pesanan.');
            return;
        }

        openModal({
            title: 'Gabung pesanan patungan',
            description: `Masukkan jumlah yang ingin diambil. Sisa kebutuhan saat ini ${remaining.toLocaleString('id-ID')} pcs.`,
            icon: 'bi-people',
            submit: 'Gabung patungan',
            fields: [
                { name: 'quantity', label: 'Jumlah yang diambil (pcs)', placeholder: 'Contoh: 100', type: 'number', min: 1, max: remaining, step: 1, required: true },
            ],
            onSubmit: (values) => {
                const quantity = Number(values.quantity);
                if (!Number.isSafeInteger(quantity) || quantity < 1 || quantity > remaining) {
                    showToast(`Jumlah harus bilangan bulat antara 1 dan ${remaining.toLocaleString('id-ID')} pcs.`);
                    return false;
                }

                submitOrderJoin(orderId, quantity);
                return false;
            },
        });
    };

    const submitOrderJoin = async (orderId, quantity) => {
        if (orderSubmissionInProgress) {
            return;
        }
        orderSubmissionInProgress = true;

        const submitButton = modalForm?.querySelector('[data-modal-submit]');
        if (submitButton) {
            submitButton.disabled = true;
        }

        try {
            await requestOrderData(orderUrl(document.body.dataset.ordersJoinTemplate, orderId), {
                method: 'POST',
                body: JSON.stringify({ quantity }),
            });
            closeModal();
            showToast('Jumlahmu berhasil ditambahkan ke pesanan patungan.');
            await loadOrders();
        } catch (requestError) {
            console.error('Navora could not join the group order.', requestError);
            showToast(requestError.message || 'Kamu tidak dapat bergabung. Muat ulang pesanan dan coba lagi.');
        } finally {
            orderSubmissionInProgress = false;
            if (submitButton) {
                submitButton.disabled = false;
            }
        }
    };

    const updateOrderStatus = async (orderId, status) => {
        if (status === 'dibatalkan' && !window.confirm('Batalkan pesanan ini? Tindakan ini tidak dapat dibatalkan.')) {
            return;
        }

        try {
            await requestOrderData(orderUrl(document.body.dataset.ordersStatusTemplate, orderId), {
                method: 'PATCH',
                body: JSON.stringify({ status }),
            });
            showToast('Status pesanan berhasil diperbarui.');
            await loadOrders();
        } catch (requestError) {
            console.error('Navora could not update the order status.', requestError);
            showToast(requestError.message || 'Status tidak dapat diperbarui. Muat ulang dan coba lagi.');
        }
    };

    const setOrderPrice = (orderId) => {
        openModal({
            title: 'Tetapkan harga final',
            description: 'Masukkan harga final per pcs dalam rupiah. Total tagihan adalah harga dikali jumlah; masukkan biaya lain yang disepakati ke harga ini.',
            icon: 'bi-tag',
            submit: 'Kirim harga ke pembeli',
            fields: [
                { name: 'unit_price', label: 'Harga final per pcs (Rp)', placeholder: 'Contoh: 1250', type: 'number', min: 1, max: 1000000000, step: 1, required: true },
            ],
            onSubmit: (values) => {
                const unitPrice = Number(values.unit_price);
                if (!Number.isSafeInteger(unitPrice) || unitPrice < 1 || unitPrice > 1000000000) {
                    showToast('Masukkan harga final dalam rupiah sebagai bilangan bulat yang valid.');
                    return false;
                }

                submitOrderPrice(orderId, unitPrice);
                return false;
            },
        });
    };

    const submitOrderPrice = async (orderId, unitPrice) => {
        if (orderSubmissionInProgress) {
            return;
        }
        orderSubmissionInProgress = true;
        const submitButton = modalForm?.querySelector('[data-modal-submit]');
        if (submitButton) {
            submitButton.disabled = true;
        }

        try {
            await requestOrderData(orderUrl(document.body.dataset.ordersPriceTemplate, orderId), {
                method: 'POST',
                body: JSON.stringify({ unit_price: unitPrice }),
            });
            closeModal();
            showToast('Harga final tersimpan. Pembeli sekarang dapat membayar lewat Midtrans.');
            await loadOrders();
        } catch (requestError) {
            console.error('Navora could not set the final order price.', requestError);
            showToast(requestError.message || 'Harga final tidak dapat disimpan. Coba lagi.');
        } finally {
            orderSubmissionInProgress = false;
            if (submitButton) {
                submitButton.disabled = false;
            }
        }
    };

    const checkoutOrder = async (orderId, button) => {
        if (orderSubmissionInProgress) {
            return;
        }
        orderSubmissionInProgress = true;
        button.disabled = true;

        try {
            const payload = await requestOrderData(orderUrl(document.body.dataset.ordersCheckoutTemplate, orderId), {
                method: 'POST',
            });
            if (!payload.redirect_url) {
                throw new Error('Midtrans tidak mengembalikan tautan pembayaran.');
            }
            window.location.assign(payload.redirect_url);
        } catch (requestError) {
            console.error('Navora could not start the Midtrans order checkout.', requestError);
            showToast(requestError.message || 'Checkout Midtrans gagal dibuat. Coba lagi.');
            button.disabled = false;
        } finally {
            orderSubmissionInProgress = false;
        }
    };

    const joinGroup = (groupId) => {
        const card = groupCardById(groupId);

        if (!card) {
            showToast('Patungan ini tidak ditemukan. Muat ulang halaman lalu coba kembali.');
            return;
        }

        const existingQuantity = Number(state.groups[groupId]?.quantity || 0);

        if (existingQuantity > 0) {
            openModal({
                title: 'Batalkan partisipasi demo?',
                description: `Status lokalmu saat ini ${existingQuantity.toLocaleString('id-ID')} pcs. Membatalkan hanya mengubah tampilan di browser ini.`,
                icon: 'bi-arrow-counterclockwise',
                submit: 'Batalkan partisipasi',
                onSubmit: () => {
                    delete state.groups[groupId];
                    saveState();
                    updateGroupCard(card);
                    showToast('Partisipasi demo dibatalkan. Tidak ada pembayaran yang dibuat.');
                    return true;
                },
            });
            return;
        }

        const remaining = Math.max(0, Number(card.dataset.total) - Number(card.dataset.joined));

        openModal({
            title: 'Ikut patungan (demo)',
            description: `Masukkan jumlah minat hingga ${remaining.toLocaleString('id-ID')} pcs. Ini bukan pesanan final dan tidak ada pembayaran.`,
            icon: 'bi-people',
            submit: 'Simpan minat demo',
            fields: [
                { name: 'quantity', label: 'Jumlah minat (pcs)', placeholder: 'Contoh: 100', type: 'number', min: 1, max: remaining, required: true },
            ],
            onSubmit: (values) => {
                const quantity = Number(values.quantity);

                if (!Number.isSafeInteger(quantity) || quantity < 1 || quantity > remaining) {
                    showToast(`Jumlah minat harus antara 1 dan ${remaining.toLocaleString('id-ID')} pcs.`);
                    return false;
                }

                state.groups[groupId] = { quantity };
                saveState();
                updateGroupCard(card);
                showToast('Minat patungan disimpan lokal. Tidak ada pesanan atau pembayaran.');
                return true;
            },
        });
    };

    const inspectProductVideoDuration = (file) => new Promise((resolve) => {
        const video = document.createElement('video');
        const objectUrl = URL.createObjectURL(file);
        const cleanup = () => {
            URL.revokeObjectURL(objectUrl);
            video.removeAttribute('src');
            video.load();
        };

        video.preload = 'metadata';
        video.onloadedmetadata = () => {
            const duration = Number.isFinite(video.duration) ? video.duration : null;
            cleanup();
            resolve(duration);
        };
        video.onerror = () => {
            cleanup();
            resolve(null);
        };
        video.src = objectUrl;
    });

    const createListing = (listingToEdit = null) => {
        const fields = [
            { ...modalTextField('name', 'Nama layanan atau produk', 'Contoh: pouch kopi cetak'), value: listingToEdit?.name || '' },
            { name: 'category', label: 'Jenis layanan', type: 'select', required: true, value: listingToEdit?.category || 'packaging', options: [{ value: 'packaging', label: 'Kemasan' }, { value: 'printing', label: 'Percetakan' }, { value: 'maklon', label: 'Maklon' }] },
            { ...modalTextField('material', 'Material', 'Contoh: kraft laminasi'), value: listingToEdit?.material || '' },
            { name: 'moq', label: 'MOQ indikatif (pcs)', placeholder: 'Contoh: 500', type: 'number', min: 1, required: true, value: String(listingToEdit?.minimum_order_quantity || '') },
            { name: 'description', label: 'Deskripsi produk', placeholder: 'Jelaskan spesifikasi, pilihan, atau kapasitas layanan', type: 'textarea', required: true, rows: 3, value: listingToEdit?.description || '' },
        ];

        if (authenticatedRole === 'vendor') {
            fields.push(
                { name: 'pricing_type', label: 'Jenis penawaran harga', type: 'select', required: true, value: listingToEdit?.pricing_type || 'final', options: [{ value: 'final', label: 'Harga final (tetap)' }, { value: 'negotiable', label: 'Bisa nego' }] },
                { name: 'price_per_unit', label: 'Harga per pcs (Rp)', placeholder: 'Contoh: 1.500', type: 'number', min: 1, max: 1000000000, step: 1, required: false, value: listingToEdit?.price_per_unit ? String(listingToEdit.price_per_unit) : '', help: 'Untuk harga final wajib diisi. Pada pilihan bisa nego, harga boleh dikosongkan atau diisi sebagai harga awal.' },
            );
            fields.push({
                name: 'media',
                label: 'Foto, GIF, atau video produk',
                type: 'file',
                accept: 'image/jpeg,image/png,image/gif,image/webp,video/mp4,video/webm',
                multiple: true,
                required: false,
                help: listingToEdit
                    ? `Maksimal 4 lampiran total. 10 MB per file; video MP4/WebM maksimal 5 detik.`
                    : 'Maksimal 4 lampiran, 10 MB per file. Video MP4/WebM maksimal 5 detik.',
            });
        }

        openModal({
            title: listingToEdit ? 'Edit katalog aktif' : 'Tambahkan item katalog',
            description: listingToEdit
                ? 'Perubahan pada katalog yang sudah aktif langsung tampil di pencarian UMKM tanpa perlu ACC ulang.'
                : authenticatedRole === 'vendor'
                    ? 'Tambahkan foto atau video agar UMKM bisa melihat produkmu. Katalog akan aktif setelah disetujui admin.'
                : 'Informasi akan disimpan lokal sebagai draft demo dan tidak dipublikasikan ke marketplace.',
            icon: 'bi-box-seam',
            submit: listingToEdit ? 'Simpan perubahan' : authenticatedRole === 'vendor' ? 'Kirim untuk ditinjau' : 'Simpan draft demo',
            fields,
            onSubmit: (values) => {
                if (authenticatedRole === 'vendor') {
                    const listing = {
                        client_reference: listingToEdit?.client_reference || window.crypto?.randomUUID?.() || `listing-${Date.now()}-${Math.random().toString(36).slice(2)}`,
                        name: values.name.trim(),
                        category: values.category,
                        material: values.material.trim() || 'Belum ditentukan',
                        minimum_order_quantity: Number(values.moq),
                        pricing_type: values.pricing_type,
                        price_per_unit: values.price_per_unit ? Number(values.price_per_unit) : null,
                        description: values.description.trim(),
                    };
                    if (listing.pricing_type === 'final'
                        && (!Number.isSafeInteger(listing.price_per_unit)
                            || listing.price_per_unit < 1
                            || listing.price_per_unit > 1000000000)) {
                        showToast('Masukkan harga final per pcs antara Rp1 dan Rp1.000.000.000.');
                        return false;
                    }

                    if (listing.pricing_type === 'negotiable'
                        && listing.price_per_unit !== null
                        && (!Number.isSafeInteger(listing.price_per_unit)
                            || listing.price_per_unit < 1
                            || listing.price_per_unit > 1000000000)) {
                        showToast('Harga awal nego harus antara Rp1 dan Rp1.000.000.000.');
                        return false;
                    }
                    const mediaInput = modalForm?.querySelector('[name="media"]');
                    const mediaFiles = mediaInput instanceof HTMLInputElement && mediaInput.files
                        ? Array.from(mediaInput.files)
                        : [];

                    const existingMediaCount = listingToEdit?.media?.length || 0;
                    const mediaIdsToRemove = Array.from(
                        modalForm?.querySelectorAll('input[name="remove_media_ids[]"]:checked') || [],
                    ).map((input) => Number(input.value));
                    const remainingMediaCount = existingMediaCount - mediaIdsToRemove.length;
                    if (mediaFiles.length + remainingMediaCount > 4) {
                        showToast(`Katalog hanya dapat memiliki maksimal 4 lampiran. Slot tersisa: ${Math.max(0, 4 - remainingMediaCount)}.`);
                        return false;
                    }

                    if (listingMediaValidationInProgress) {
                        return false;
                    }

                    listingMediaValidationInProgress = true;
                    void (async () => {
                        try {
                            for (const file of mediaFiles) {
                                if (!file.type.startsWith('video/')) {
                                    continue;
                                }

                                const duration = await inspectProductVideoDuration(file);
                                if (duration === null || duration <= 0 || duration > 5) {
                                    showToast('Setiap video produk harus memiliki durasi lebih dari 0 dan maksimal 5 detik.');
                                    return;
                                }
                            }

                            await submitVendorListing(listing, false, mediaFiles, mediaIdsToRemove);
                        } finally {
                            listingMediaValidationInProgress = false;
                        }
                    })();
                    return false;
                }

                state.listings.unshift({
                    id: `listing-${Date.now()}`,
                    name: values.name.trim(),
                    category: values.category,
                    material: values.material.trim() || 'Belum ditentukan',
                    moq: Number(values.moq),
                    description: values.description.trim(),
                });
                saveState();
                renderStoredListings();
                showToast('Draft katalog tersimpan di browser. Belum dipublikasikan.');
                return true;
            },
        });

        if (listingToEdit && modalFields) {
            const mediaSection = textElement('section', 'navora-listing-media-editor', '');
            const mediaHeading = textElement('strong', '', `Lampiran saat ini (${listingToEdit.media?.length || 0}/4)`);
            const mediaHelp = textElement('small', '', 'Pilih Hapus pada setiap gambar, GIF, atau video yang ingin dihapus.');
            const mediaGrid = textElement('div', 'navora-listing-media-editor-grid', '');
            mediaSection.append(mediaHeading, mediaHelp);
            const updateMediaCapacity = () => {
                const remainingCount = (listingToEdit.media?.length || 0)
                    - modalFields.querySelectorAll('input[name="remove_media_ids[]"]:checked').length;
                mediaHeading.textContent = `Lampiran tersimpan setelah perubahan (${remainingCount}/4)`;

                const uploadHelp = modalFields.querySelector('[name="media"]')
                    ?.closest('.navora-modal-field')
                    ?.querySelector('.navora-modal-field-help');
                if (uploadHelp) {
                    uploadHelp.textContent = `Slot lampiran tersedia: ${4 - remainingCount}. Maksimal 10 MB per file; video maksimal 5 detik.`;
                }
            };

            (listingToEdit.media || []).forEach((media) => {
                const mediaCard = textElement('div', 'navora-listing-media-editor-item', '');
                const removeInput = document.createElement('input');
                removeInput.type = 'checkbox';
                removeInput.name = 'remove_media_ids[]';
                removeInput.value = String(media.id);
                removeInput.hidden = true;

                if (media.type === 'video') {
                    const preview = document.createElement('video');
                    preview.src = media.url;
                    preview.muted = true;
                    preview.playsInline = true;
                    preview.preload = 'metadata';
                    preview.setAttribute('aria-label', `Pratinjau video ${listingToEdit.name}`);
                    mediaCard.append(preview);
                } else {
                    const preview = document.createElement('img');
                    preview.src = media.url;
                    preview.alt = `Lampiran produk ${listingToEdit.name}`;
                    preview.loading = 'lazy';
                    mediaCard.append(preview);
                }

                const removeButton = textElement('button', 'navora-listing-media-remove', 'Hapus');
                removeButton.type = 'button';
                removeButton.setAttribute('aria-pressed', 'false');
                removeButton.addEventListener('click', () => {
                    removeInput.checked = !removeInput.checked;
                    mediaCard.classList.toggle('is-marked-for-removal', removeInput.checked);
                    removeButton.textContent = removeInput.checked ? 'Batal hapus' : 'Hapus';
                    removeButton.setAttribute('aria-pressed', String(removeInput.checked));
                    updateMediaCapacity();
                });

                mediaCard.append(removeInput, removeButton);
                mediaGrid.append(mediaCard);
            });

            if ((listingToEdit.media || []).length === 0) {
                mediaGrid.append(textElement('span', 'navora-listing-media-empty', 'Belum ada lampiran. Kamu dapat menambah gambar, GIF, atau video.'));
            }

            mediaSection.append(mediaGrid);
            modalFields?.append(mediaSection);
            updateMediaCapacity();
        }

        const pricingTypeInput = modalFields?.querySelector('[name="pricing_type"]');
        const priceInput = modalFields?.querySelector('[name="price_per_unit"]');
        if (pricingTypeInput instanceof HTMLSelectElement && priceInput instanceof HTMLInputElement) {
            const updatePriceRequirement = () => {
                priceInput.required = pricingTypeInput.value === 'final';
            };
            pricingTypeInput.addEventListener('change', updatePriceRequirement);
            updatePriceRequirement();
        }
    };

    const submitVendorListing = async (listing, localDraft, mediaFiles = [], removeMediaIds = []) => {
        if (listingSubmissionInProgress) {
            return;
        }

        listingSubmissionInProgress = true;
        const submitButton = modalForm?.querySelector('[data-modal-submit]');
        if (submitButton) {
            submitButton.disabled = true;
        }

        try {
            const formData = new FormData();
            Object.entries(listing).forEach(([key, value]) => formData.append(key, value === null ? '' : String(value)));
            mediaFiles.forEach((file) => formData.append('media[]', file, file.name));
            removeMediaIds.forEach((id) => formData.append('remove_media_ids[]', String(id)));

            const response = await requestOrderData(document.body.dataset.vendorListingsStoreUrl, {
                method: 'POST',
                body: formData,
            });

            if (localDraft) {
                state.listings = state.listings.filter((item) => item.id !== listing.client_reference);
                saveState();
            } else {
                closeModal();
            }

            vendorListings = [
                response.listing,
                ...vendorListings.filter((item) => item.id !== response.listing.id),
            ];
            renderVendorListings();
            showToast(response.listing.status === 'pending'
                ? 'Katalog sudah dikirim ke admin dan akan aktif setelah disetujui.'
                : 'Katalog berhasil disimpan.');
        } catch (requestError) {
            console.error('Navora could not save the vendor listing.', requestError);
            showToast(requestError.message || 'Katalog tidak dapat disimpan. Coba lagi.');
        } finally {
            listingSubmissionInProgress = false;
            if (submitButton) {
                submitButton.disabled = false;
            }
        }
    };

    const submitVendorListingFromCard = (button) => {
        const isLocalDraft = button.dataset.localDraft === 'true';
        const reference = button.dataset.listingReference;

        if (!reference || listingSubmissionInProgress) {
            return;
        }

        const listing = isLocalDraft
            ? state.listings.find((item) => item.id === reference)
            : vendorListings.find((item) => String(item.id) === reference);

        if (!listing) {
            showToast('Draft katalog tidak ditemukan. Muat ulang halaman dan coba lagi.');
            return;
        }

        submitVendorListing({
            client_reference: isLocalDraft ? listing.id : listing.client_reference,
            name: listing.name,
            category: listing.category,
            material: listing.material || 'Belum ditentukan',
            minimum_order_quantity: Number(listing.minimum_order_quantity || listing.moq),
            description: listing.description,
        }, isLocalDraft);
    };

    const createVendorQuote = (rfqId) => {
        const rfq = state.rfqs.find((item) => item.id === rfqId);
        const defaultName = rfq?.title || 'Standing pouch kopi 200 g';

        openModal({
            title: 'Buat tanggapan penawaran',
            description: `Isi tanggapan contoh untuk "${defaultName}". Ini tidak dikirim kepada calon pembeli.`,
            icon: 'bi-chat-square-text',
            submit: 'Simpan respons demo',
            fields: [
                { name: 'vendor', label: 'Nama vendor', placeholder: 'Nama usaha', required: true, value: state.profile.name || 'PouchKita Studio' },
                { name: 'price', label: 'Harga indikatif per pcs', placeholder: 'Contoh: Rp1.250', required: true },
                { name: 'moq', label: 'MOQ vendor (pcs)', placeholder: 'Contoh: 500', type: 'number', min: 1, required: true },
                { name: 'leadTime', label: 'Estimasi produksi', placeholder: 'Contoh: 14–21 hari', required: true },
                { name: 'note', label: 'Catatan vendor (opsional)', placeholder: 'Syarat, sampel, atau opsi material', type: 'textarea', required: false, rows: 3 },
            ],
            onSubmit: (values) => {
                const quote = {
                    id: `quote-${Date.now()}`,
                    rfqId,
                    vendor: values.vendor.trim(),
                    price: values.price.trim(),
                    moq: Number(values.moq),
                    leadTime: values.leadTime.trim(),
                    note: values.note || '',
                };
                state.quotes.unshift(quote);

                if (rfq) {
                    rfq.quote = quote;
                }

                saveState();
                renderStoredRfqs();
                showToast('Respons contoh disimpan lokal. Pembeli tidak menerima notifikasi.');
                return true;
            },
        });
    };

    const showAssistantResult = (values) => {
        const product = values.product.trim();
        const lowerProduct = product.toLocaleLowerCase('id');
        const liquid = /minuman|sambal|saus|sabun cair|minyak|sirup|jus/.test(lowerProduct);
        const food = /kopi|teh|snack|makanan|camilan|rempah|bumbu/.test(lowerProduct);
        const packaging = liquid
            ? ['Botol PET atau kaca dapat dieksplorasi; konfirmasi kompatibilitas produk dengan vendor.']
            : food
                ? ['Pouch dengan zipper dapat dipertimbangkan; tanyakan opsi lapisan dan sampel.']
                : ['Bandingkan pouch, box, atau botol berdasarkan bentuk produk dan cara distribusinya.'];
        const material = liquid
            ? ['Tanyakan material yang sesuai kontak produk dan uji kompatibilitas sebelum produksi.']
            : food
                ? ['Tanyakan spesifikasi food-contact, lapisan pelindung, dan bukti yang relevan kepada vendor.']
                : ['Minta informasi komposisi material, ketahanan, dan opsi sampel dari vendor.'];
        const volumeNote = values.volume === 'small'
            ? 'Mulai dengan meminta sampel dan menanyakan opsi batch kecil atau patungan.'
            : values.volume === 'medium'
                ? 'Bandingkan MOQ, harga bertingkat, waktu produksi, dan ongkos pengiriman.'
                : 'Tanyakan kapasitas produksi, konsistensi kualitas, serta jadwal pengiriman bertahap.';
        const vendors = liquid
            ? ['Kemas Prima', 'Rasa Racik Maklon']
            : food
                ? ['PouchKita Studio', 'Cetak Bumi']
                : ['PouchKita Studio', 'Kemas Prima', 'Cetak Bumi'];
        const result = document.querySelector('[data-assistant-result]');

        if (!result) {
            return;
        }

        const content = document.createElement('div');
        content.className = 'navora-result-content';
        content.append(
            textElement('span', 'navora-panel-kicker', 'IDE AWAL UNTUK DISKUSI'),
            textElement('h2', '', `Pilihan awal untuk ${product}`),
            textElement('p', '', `${values.size ? `Kebutuhan ${values.size}. ` : ''}${volumeNote}`),
        );

        const packageBlock = document.createElement('div');
        packageBlock.className = 'navora-result-block';
        packageBlock.append(textElement('strong', '', 'Format yang bisa ditanyakan'));
        const packageList = document.createElement('ul');
        packaging.forEach((recommendation) => packageList.append(textElement('li', '', recommendation)));
        packageBlock.append(packageList);

        const materialBlock = document.createElement('div');
        materialBlock.className = 'navora-result-block';
        materialBlock.append(textElement('strong', '', 'Hal yang perlu dikonfirmasi'));
        const materialList = document.createElement('ul');
        [...material, ...(values.preference ? [`Sampaikan preferensi: ${values.preference.trim()}.`] : [])]
            .forEach((recommendation) => materialList.append(textElement('li', '', recommendation)));
        materialBlock.append(materialList);

        const vendorList = document.createElement('div');
        vendorList.className = 'navora-result-vendors';
        vendors.forEach((name) => vendorList.append(textElement('span', '', name)));
        content.append(packageBlock, materialBlock, textElement('strong', 'navora-panel-kicker', 'VENDOR DEMO UNTUK DITELUSURI'), vendorList);
        content.append(textElement('p', 'navora-result-warning', 'Simulasi berbasis kata kunci, bukan AI atau saran ahli. Material, keamanan pangan, klaim sertifikasi, biaya, dan kecocokan wajib dikonfirmasi dengan pihak yang kompeten.'));
        result.replaceChildren(content);
    };

    const createProfileEdit = () => {
        const role = state.role;
        const defaultName = state.profile.name || (role === 'vendor' ? 'PouchKita Studio' : role === 'partner' ? 'Mitra UMKM Nusantara' : 'Usaha Kopi Nusantara');

        openModal({
            title: 'Perbarui profil demo',
            description: 'Nama dan lokasi ini hanya disimpan di browser pada perangkat ini.',
            icon: 'bi-person',
            submit: 'Simpan profil lokal',
            fields: [
                { name: 'name', label: role === 'umkm' ? 'Nama usaha' : role === 'vendor' ? 'Nama vendor' : 'Nama mitra', placeholder: 'Nama tampilan', required: true, value: defaultName },
                { name: 'location', label: 'Lokasi', placeholder: 'Kota, provinsi', required: true, value: state.profile.location || (role === 'partner' ? 'Indonesia' : 'Bandung, Jawa Barat') },
            ],
            onSubmit: (values) => {
                state.profile = { name: values.name.trim(), location: values.location.trim() };
                saveState();
                updateRoleContent();
                if (authenticatedRole === 'umkm') {
                    loadVendorCatalog();
                } else {
                    updateVendorFilters();
                }
                showToast('Profil demo diperbarui di browser ini.');
                return true;
            },
        });
    };

    const openInfo = (title, description, icon = 'bi-info-circle') => {
        openModal({
            title,
            description,
            icon,
            submit: 'Mengerti',
        });
    };

    const handleAction = (button) => {
        const action = button.dataset.action;

        if (action === 'load-more-orders') {
            loadOrders(true);
        } else if (action === 'create-order') {
            createOrder(button.dataset.orderType || 'langsung');
        } else if (action === 'join-order') {
            joinOrder(button.dataset.order || '', Number(button.dataset.remaining));
        } else if (action === 'update-order-status') {
            updateOrderStatus(button.dataset.order || '', button.dataset.status || '');
        } else if (action === 'set-order-price') {
            setOrderPrice(button.dataset.order || '');
        } else if (action === 'checkout-order') {
            checkoutOrder(button.dataset.order || '', button);
        } else if (action === 'rfq') {
            createRfq(button.dataset.vendor || '', button.dataset.vendorUserId || '');
        } else if (action === 'product-detail') {
            openProductDetail(button);
        } else if (action === 'product-request-quote') {
            if (activeProductContext) {
                const { vendor, vendorUserId, listing } = activeProductContext;
                if (productDialog instanceof HTMLDialogElement) {
                    productDialog.close();
                }
                activeProductContext = null;
                createRfq(vendor, vendorUserId, listing);
            }
        } else if (action === 'create-rfq') {
            createRfq();
        } else if (action === 'join-group') {
            joinGroup(button.dataset.group || '');
        } else if (action === 'create-group') {
            createGroup();
        } else if (action === 'add-listing') {
            createListing();
        } else if (action === 'edit-vendor-listing') {
            const listing = vendorListings.find((item) => String(item.id) === button.dataset.listingReference);
            if (!listing || listing.status !== 'active') {
                showToast('Katalog aktif tidak ditemukan. Muat ulang halaman dan coba lagi.');
            } else {
                createListing(listing);
            }
        } else if (action === 'submit-vendor-listing') {
            submitVendorListingFromCard(button);
        } else if (action === 'retry-vendor-listings') {
            loadVendorListings();
        } else if (action === 'respond-rfq') {
            createVendorQuote(button.dataset.rfq || 'rfq-demo-1');
        } else if (action === 'quote-detail') {
            openInfo(
                `Respons dari ${button.dataset.vendor || 'vendor demo'}`,
                'Harga dan kapasitas yang ditampilkan bersifat indikatif dan belum dikonfirmasi. Hubungi vendor secara langsung pada produk nyata untuk menyepakati spesifikasi, pajak, pengiriman, dan syarat pembayaran.',
                'bi-chat-square-text',
            );
        } else if (action === 'vendor-detail') {
            openInfo(
                button.dataset.vendor || 'Vendor',
                button.dataset.vendorDescription
                    ? `${button.dataset.vendorDescription} ${button.dataset.vendorSpecifications || ''}`
                    : 'Halaman ini hanya menampilkan ringkasan katalog contoh. Detail usaha, kapasitas, sertifikat, sampel, jadwal, harga final, dan identitas vendor belum diverifikasi.',
                'bi-box-seam',
            );
        } else if (action === 'retry-vendor-catalog') {
            loadVendorCatalog();
        } else if (action === 'use-current-location') {
            if (authenticatedRole !== 'umkm') {
                showToast('Masuk ke akun UMKM untuk mencari vendor nyata berdasarkan jarak.');
            } else if (!navigator.geolocation) {
                showToast('Peramban ini tidak mendukung akses lokasi.');
            } else {
                button.disabled = true;
                navigator.geolocation.getCurrentPosition(
                    (position) => {
                        vendorOrigin = {
                            latitude: position.coords.latitude,
                            longitude: position.coords.longitude,
                        };
                        const distance = document.querySelector('[data-vendor-filter="distance"]');
                        if (distance instanceof HTMLSelectElement && !distance.value) {
                            distance.value = '50';
                        }
                        button.disabled = false;
                        updateVendorFilters();
                    },
                    (error) => {
                        button.disabled = false;
                        console.error('Navora could not access the current location.', error);
                        showToast(error.code === error.PERMISSION_DENIED
                            ? 'Izin lokasi ditolak. Aktifkan izin lokasi untuk memakai filter jarak.'
                            : 'Lokasi tidak dapat diperoleh. Periksa izin dan coba lagi.');
                    },
                    { enableHighAccuracy: false, timeout: 10000, maximumAge: 300000 },
                );
            }
        } else if (action === 'verify-profile') {
            openModal({
                title: 'Status profil vendor demo',
                description: state.verificationRequested
                    ? 'Permintaan simulasi sudah tercatat di browser. Ini tidak berarti dokumen atau identitas bisnis telah diperiksa.'
                    : 'Prototipe belum memiliki proses unggah atau pemeriksaan dokumen. Kamu bisa menandai bahwa profil demo siap ditinjau.',
                icon: 'bi-patch-check',
                submit: state.verificationRequested ? 'Tutup' : 'Tandai siap ditinjau',
                onSubmit: () => {
                    state.verificationRequested = true;
                    saveState();
                    setText('[data-verification-summary]', 'Demo dikirim');
                    showToast('Status demo diperbarui. Tidak ada dokumen yang ditinjau.');
                    return true;
                },
            });
        } else if (action === 'program-interest') {
            const name = button.dataset.program || 'program demo';
            const alreadyInterested = state.interests.includes(name);
            if (!alreadyInterested) {
                state.interests.push(name);
                saveState();
            }
            showToast(alreadyInterested ? 'Minat pada program ini sudah tersimpan lokal.' : 'Minat program dicatat lokal. Belum ada pendaftaran nyata.');
            button.textContent = alreadyInterested ? 'Minat tersimpan' : 'Minat dicatat di demo';
            button.disabled = true;
        } else if (action === 'edit-profile') {
            createProfileEdit();
        }
    };

    document.addEventListener('click', (event) => {
        const target = event.target;

        if (!(target instanceof Element)) {
            return;
        }

        const notificationPopup = document.querySelector('[data-ad-notifications-popup]');
        if (target.closest('[data-close-product-dialog]') && productDialog instanceof HTMLDialogElement) {
            productDialog.close();
            return;
        }

        if (target.closest('[data-notifications-toggle]') && notificationPopup instanceof HTMLDialogElement) {
            notificationPopup.showModal();
            return;
        }

        if (target.closest('[data-close-ad-notifications]') && notificationPopup instanceof HTMLDialogElement) {
            notificationPopup.close();
            return;
        }

        if (target.closest('[data-notification-item], [data-open-notifications]')) {
            if (notificationPopup instanceof HTMLDialogElement && notificationPopup.open) {
                notificationPopup.close();
            }
            showPage('notifications');
            return;
        }

        const tab = target.closest('[data-page]');

        if (tab instanceof HTMLButtonElement) {
            showPage(tab.dataset.page || 'overview');
            return;
        }

        const pageLink = target.closest('[data-open-page]');

        if (pageLink instanceof HTMLElement) {
            showPage(pageLink.dataset.openPage || 'overview');
            return;
        }

        const actionButton = target.closest('[data-action]');

        if (actionButton instanceof HTMLButtonElement) {
            handleAction(actionButton);
            return;
        }

        if (target.closest('[data-close-modal]') && !orderSubmissionInProgress) {
            closeModal();
        }
    });

    document.addEventListener('submit', (event) => {
        const form = event.target;

        if (form instanceof HTMLFormElement
            && form.dataset.confirmSubmit
            && !window.confirm(form.dataset.confirmSubmit)) {
            event.preventDefault();
        }
    });

    modalForm?.addEventListener('submit', (event) => {
        event.preventDefault();

        if (!activeModalHandler || orderSubmissionInProgress || !modalForm.reportValidity()) {
            return;
        }

        const shouldClose = activeModalHandler(readFormValues(new FormData(modalForm)));

        if (shouldClose !== false) {
            closeModal();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && modal && !modal.hidden && !orderSubmissionInProgress) {
            closeModal();
        }

        if (event.key === '/' && !event.ctrlKey && !event.metaKey && !event.altKey) {
            const target = event.target;

            if (target instanceof HTMLElement && ['INPUT', 'TEXTAREA', 'SELECT'].includes(target.tagName)) {
                return;
            }

            event.preventDefault();
            document.querySelector('[data-global-search]')?.focus();
        }
    });

    document.querySelector('[data-role-select]')?.addEventListener('change', (event) => {
        if (knownRoles.includes(authenticatedRole || '')) {
            return;
        }

        const select = event.currentTarget;
        state.role = select.value;
        saveState();
        updateRoleContent();
        showPage('overview');
    });

    document.querySelectorAll('[data-vendor-filter]').forEach((filter) => {
        filter.addEventListener('input', updateVendorFilters);
        filter.addEventListener('change', updateVendorFilters);
    });

    document.querySelector('[data-vendor-sort]')?.addEventListener('change', sortVendorCards);

    document.querySelector('[data-reset-filters]')?.addEventListener('click', () => {
        document.querySelectorAll('[data-vendor-filter]').forEach((filter) => {
            if (filter instanceof HTMLInputElement && filter.type === 'checkbox') {
                filter.checked = false;
            } else {
                filter.value = '';
            }
        });
        updateVendorFilters();
        showToast('Filter vendor direset.');
    });

    document.querySelector('[data-global-search]')?.addEventListener('input', (event) => {
        const searchInput = event.currentTarget;
        const vendorSearch = document.querySelector('[data-vendor-filter="search"]');

        if (vendorSearch instanceof HTMLInputElement) {
            vendorSearch.value = searchInput.value;
            updateVendorFilters();
        }
    });

    document.querySelector('[data-global-search]')?.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' && state.role === 'umkm') {
            event.preventDefault();
            showPage('vendors');
        }
    });

    document.querySelector('[data-assistant-form]')?.addEventListener('submit', (event) => {
        event.preventDefault();
        const form = event.currentTarget;

        if (!(form instanceof HTMLFormElement) || !form.reportValidity()) {
            return;
        }

        showAssistantResult(Object.fromEntries(new FormData(form).entries()));
    });

    document.querySelectorAll('[data-group-card]').forEach(updateGroupCard);
    renderMiniVendors();
    renderStoredListings();
    renderStoredGroups();
    updateGroupCards();
    renderStoredRfqs();

    const requestedRole = urlParams.get('role');

    if (!authenticatedRole && knownRoles.includes(requestedRole || '')) {
        state.role = requestedRole;
    }
    updateRoleContent();
    if (authenticatedRole === 'vendor') {
        loadVendorListings();
    }

    const requestedPage = urlParams.get('tab') || urlParams.get('page');
    const requestedSearch = urlParams.get('q');

    if (requestedSearch) {
        const vendorSearch = document.querySelector('[data-vendor-filter="search"]');
        const globalSearch = document.querySelector('[data-global-search]');

        if (vendorSearch instanceof HTMLInputElement) {
            vendorSearch.value = requestedSearch;
        }

        if (globalSearch instanceof HTMLInputElement) {
            globalSearch.value = requestedSearch;
        }

        updateVendorFilters();
    }

    if (requestedPage && knownPages.includes(requestedPage)) {
        showPage(requestedPage);
    } else if (requestedSearch && state.role === 'umkm') {
        showPage('vendors');
    }

    const latestAdvertisementNotice = document.body.dataset.latestAdNotice;
    const notificationPopup = document.querySelector('[data-ad-notifications-popup]');
    if (latestAdvertisementNotice && notificationPopup instanceof HTMLDialogElement) {
        const seenKey = `navora-last-ad-notification:${authenticatedUserId}`;
        try {
            if (window.sessionStorage.getItem(seenKey) !== latestAdvertisementNotice
                && requestedPage !== 'notifications') {
                notificationPopup.showModal();
                window.sessionStorage.setItem(seenKey, latestAdvertisementNotice);
            }
        } catch (error) {
            console.error('Navora could not remember the last advertisement notification.', error);
        }
    }
})();
