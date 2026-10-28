(() => {
    'use strict';

    const roleSelect = document.querySelector('#role');
    const googleLink = document.querySelector('[data-google-auth-link]');

    if (!(roleSelect instanceof HTMLSelectElement) || !(googleLink instanceof HTMLAnchorElement)) {
        return;
    }

    roleSelect.addEventListener('change', () => {
        const url = new URL(googleLink.href);
        url.searchParams.set('role', roleSelect.value);
        googleLink.href = url.toString();
    });
})();
