(() => {
    'use strict';

    const body = document.body;
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const workflowStages = {
        po: {
            title: 'Purchase order',
            description: 'Permintaan, persetujuan, dan status pesanan terdokumentasi.',
            icon: 'bi-file-earmark-text',
            step: 'Tahap 01',
            progress: '33.333%',
        },
        delivery: {
            title: 'Pengiriman & BAST',
            description: 'Catatan pengiriman dan penerimaan barang saling terhubung.',
            icon: 'bi-truck',
            step: 'Tahap 02',
            progress: '66.666%',
        },
        invoice: {
            title: 'Invoice & pembayaran',
            description: 'Invoice dan status pembayaran mengikuti transaksi aktual.',
            icon: 'bi-journal-check',
            step: 'Tahap 03',
            progress: '100%',
        },
    };

    body.classList.add('js-ready');

    const stageButtons = document.querySelectorAll('[data-workflow-stage]');
    const stageTitle = document.querySelector('[data-stage-title]');
    const stageCopy = document.querySelector('[data-stage-copy]');
    const stageIcon = document.querySelector('.stage-detail-icon i');
    const stageBadge = document.querySelector('.stage-detail-badge');
    const progressBar = document.querySelector('.dash-progress > span');

    stageButtons.forEach((button) => {
        button.addEventListener('click', () => {
            const stage = workflowStages[button.dataset.workflowStage];

            if (!stage || !stageTitle || !stageCopy || !stageIcon || !stageBadge || !progressBar) {
                return;
            }

            stageButtons.forEach((stageButton) => {
                const isActive = stageButton === button;

                stageButton.classList.toggle('is-active', isActive);
                stageButton.setAttribute('aria-pressed', String(isActive));
            });

            stageTitle.textContent = stage.title;
            stageCopy.textContent = stage.description;
            stageIcon.className = `bi ${stage.icon}`;
            stageBadge.lastChild.textContent = ` ${stage.step}`;
            progressBar.style.width = stage.progress;
        });
    });

    const revealElements = document.querySelectorAll('[data-reveal]');

    if ('IntersectionObserver' in window) {
        const revealObserver = new IntersectionObserver((entries, observer) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-revealed');
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.12 });

        revealElements.forEach((element) => {
            revealObserver.observe(element);
        });
    } else {
        revealElements.forEach((element) => {
            element.classList.add('is-revealed');
        });
    }

    const navigationLinks = document.querySelectorAll('.site-nav .nav-link[href^="#"]');
    const observedSections = Array.from(navigationLinks)
        .map((link) => document.querySelector(link.getAttribute('href')))
        .filter((section) => section !== null);

    if ('IntersectionObserver' in window && observedSections.length > 0) {
        const sectionObserver = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    navigationLinks.forEach((link) => {
                        const isCurrentSection = link.getAttribute('href') === `#${entry.target.id}`;

                        if (isCurrentSection) {
                            link.setAttribute('aria-current', 'location');
                        } else {
                            link.removeAttribute('aria-current');
                        }

                        link.classList.toggle('active', isCurrentSection);
                    });
                }
            });
        }, {
            rootMargin: '-22% 0px -64% 0px',
        });

        observedSections.forEach((section) => {
            sectionObserver.observe(section);
        });
    }

    const backToTopButton = document.querySelector('.back-top-button');

    if (backToTopButton) {
        let isTicking = false;

        const updateBackToTopVisibility = () => {
            const shouldShowButton = window.scrollY > 560;

            backToTopButton.classList.toggle('is-visible', shouldShowButton);
            backToTopButton.tabIndex = shouldShowButton ? 0 : -1;
            isTicking = false;
        };

        window.addEventListener('scroll', () => {
            if (!isTicking) {
                window.requestAnimationFrame(updateBackToTopVisibility);
                isTicking = true;
            }
        }, { passive: true });

        updateBackToTopVisibility();

        backToTopButton.addEventListener('click', () => {
            window.scrollTo({
                top: 0,
                behavior: reducedMotion ? 'auto' : 'smooth',
            });
        });
    }

    const navigationCollapse = document.querySelector('#menuUtama');

    if (navigationCollapse && window.bootstrap?.Collapse) {
        const collapseInstance = window.bootstrap.Collapse.getOrCreateInstance(navigationCollapse, {
            toggle: false,
        });

        navigationLinks.forEach((link) => {
            link.addEventListener('click', () => {
                if (navigationCollapse.classList.contains('show')) {
                    collapseInstance.hide();
                }
            });
        });
    }
})();
