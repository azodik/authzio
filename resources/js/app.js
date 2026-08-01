/**
 * Marketing site interactions — keep light for SEO pages.
 */
document.documentElement.classList.add('js');

function setupMobileNav() {
    const toggle = document.getElementById('mobile-nav-toggle');
    const panel = document.getElementById('mobile-nav');
    const backdrop = document.getElementById('mobile-nav-backdrop');
    const header = toggle?.closest('header');

    if (!(toggle instanceof HTMLButtonElement) || !(panel instanceof HTMLElement)) {
        return;
    }

    const setOpen = (open) => {
        panel.hidden = !open;
        panel.classList.toggle('hidden', !open);
        if (backdrop instanceof HTMLElement) {
            backdrop.hidden = !open;
            backdrop.classList.toggle('hidden', !open);
        }
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        toggle.setAttribute('aria-label', open ? 'Close menu' : 'Open menu');
    };

    setOpen(false);

    toggle.addEventListener('click', (event) => {
        event.stopPropagation();
        setOpen(panel.hidden);
    });

    panel.querySelectorAll('.mobile-nav-link').forEach((link) => {
        link.addEventListener('click', () => setOpen(false));
    });

    if (backdrop instanceof HTMLElement) {
        backdrop.addEventListener('click', () => setOpen(false));
    }

    document.addEventListener('click', (event) => {
        if (panel.hidden) {
            return;
        }

        const target = event.target;
        if (!(target instanceof Node)) {
            return;
        }

        if (header instanceof HTMLElement && header.contains(target)) {
            return;
        }

        setOpen(false);
    });

    window.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            setOpen(false);
        }
    });
}

setupMobileNav();
