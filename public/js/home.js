(() => {
    'use strict';

    const body = document.body;
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const revealElements = document.querySelectorAll('.navora-landing [data-reveal]');

    body.classList.add('js-ready');

    if ('IntersectionObserver' in window) {
        const revealObserver = new IntersectionObserver((entries, observer) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-revealed');
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.12 });

        revealElements.forEach((element) => revealObserver.observe(element));
    } else {
        revealElements.forEach((element) => element.classList.add('is-revealed'));
    }

    const nav = document.querySelector('#navoraHomeNav');
    const navLinks = document.querySelectorAll('#navoraHomeNav a[href^="#"]');

    if (nav && window.bootstrap?.Collapse) {
        const collapse = window.bootstrap.Collapse.getOrCreateInstance(nav, { toggle: false });

        navLinks.forEach((link) => {
            link.addEventListener('click', () => {
                if (nav.classList.contains('show')) {
                    collapse.hide();
                }
            });
        });
    }

    const backToTop = document.querySelector('.navora-back-top');

    if (backToTop) {
        const updateButton = () => {
            const isVisible = window.scrollY > 560;
            backToTop.classList.toggle('is-visible', isVisible);
            backToTop.tabIndex = isVisible ? 0 : -1;
        };

        window.addEventListener('scroll', updateButton, { passive: true });
        updateButton();

        backToTop.addEventListener('click', () => {
            window.scrollTo({ top: 0, behavior: reducedMotion ? 'auto' : 'smooth' });
        });
    }
})();
