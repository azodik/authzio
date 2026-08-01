const AUTH_STORAGE_PREFIXES = ['authzio.', 'authzio_'] as const;
const AUTH_STORAGE_KEYS = [
    'authzio.active_organization_id',
    'authzio.active_application_id',
    'authzio_preferred_locale',
    'authzio-theme',
] as const;

/** Cookies the console can clear from JS (HttpOnly session cookies need the server). */
const CLEARABLE_COOKIE_NAMES = ['XSRF-TOKEN'] as const;

function removeMatchingStorage(storage: Storage): void {
    const keys: string[] = [];
    for (let index = 0; index < storage.length; index += 1) {
        const key = storage.key(index);
        if (key !== null) {
            keys.push(key);
        }
    }

    for (const key of keys) {
        if (
            AUTH_STORAGE_KEYS.includes(key as (typeof AUTH_STORAGE_KEYS)[number]) ||
            AUTH_STORAGE_PREFIXES.some((prefix) => key.startsWith(prefix))
        ) {
            storage.removeItem(key);
        }
    }
}

function expireCookie(name: string): void {
    const secure =
        typeof window !== 'undefined' && window.location.protocol === 'https:' ? '; Secure' : '';
    const base = `${encodeURIComponent(name)}=; Max-Age=0; path=/; SameSite=Lax${secure}`;

    document.cookie = base;
    // Also try host-only and common domain variants left by misconfigured SESSION_DOMAIN.
    const host = window.location.hostname;
    if (host && host !== 'localhost' && !/^\d+\.\d+\.\d+\.\d+$/.test(host)) {
        document.cookie = `${base}; domain=${host}`;
        document.cookie = `${base}; domain=.${host}`;
        const parts = host.split('.');
        if (parts.length > 2) {
            const parent = parts.slice(-2).join('.');
            document.cookie = `${base}; domain=.${parent}`;
        }
    }
}

function staleCookieSessionNames(): string[] {
    const names: string[] = [];
    const raw = document.cookie;
    if (raw === '') {
        return names;
    }

    for (const part of raw.split(';')) {
        const name = decodeURIComponent(part.trim().split('=')[0] ?? '');
        // Cookie session driver stores payloads under the 40-char session id name.
        if (/^[A-Za-z0-9]{40}$/.test(name)) {
            names.push(name);
        }
    }

    return names;
}

/**
 * Clear console auth-related local/session storage and readable cookies.
 * Call on explicit logout and when the session is clearly dead (401 / 419 / 431).
 */
export function clearClientAuthState(): void {
    try {
        removeMatchingStorage(window.localStorage);
    } catch {
        /* ignore */
    }

    try {
        removeMatchingStorage(window.sessionStorage);
    } catch {
        /* ignore */
    }

    for (const name of CLEARABLE_COOKIE_NAMES) {
        expireCookie(name);
    }

    for (const name of staleCookieSessionNames()) {
        expireCookie(name);
    }
}
