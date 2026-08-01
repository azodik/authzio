const PENDING_REDIRECT_KEY = 'authzio.pending_auth_redirect';
const PENDING_REDIRECT_AT_KEY = 'authzio.pending_auth_redirect_at';
const MAX_AGE_MS = 24 * 60 * 60 * 1000;

/** Safe in-app path only (blocks open redirects). */
export function isSafeAuthRedirect(path: string): boolean {
    return path.startsWith('/') && !path.startsWith('//') && !path.includes('://');
}

/**
 * Persist where the user should land after login / register / email verify.
 * Uses localStorage so a verification link opened in another tab still finds it.
 */
export function setPendingAuthRedirect(path: string): void {
    if (!isSafeAuthRedirect(path)) {
        return;
    }

    try {
        localStorage.setItem(PENDING_REDIRECT_KEY, path);
        localStorage.setItem(PENDING_REDIRECT_AT_KEY, String(Date.now()));
    } catch {
        // private mode / quota — ignore
    }
}

export function peekPendingAuthRedirect(): string | null {
    try {
        const path = localStorage.getItem(PENDING_REDIRECT_KEY);
        const atRaw = localStorage.getItem(PENDING_REDIRECT_AT_KEY);
        if (path === null || !isSafeAuthRedirect(path)) {
            return null;
        }

        const at = atRaw !== null ? Number(atRaw) : NaN;
        if (!Number.isFinite(at) || Date.now() - at > MAX_AGE_MS) {
            clearPendingAuthRedirect();
            return null;
        }

        return path;
    } catch {
        return null;
    }
}

export function clearPendingAuthRedirect(): void {
    try {
        localStorage.removeItem(PENDING_REDIRECT_KEY);
        localStorage.removeItem(PENDING_REDIRECT_AT_KEY);
    } catch {
        // ignore
    }
}

/** Read and clear the pending redirect, or return fallback. */
export function consumePendingAuthRedirect(fallback = '/'): string {
    const path = peekPendingAuthRedirect();
    clearPendingAuthRedirect();
    return path ?? fallback;
}

/** Capture `?redirect=` from the URL into localStorage. */
export function captureRedirectFromSearchParams(searchParams: URLSearchParams): void {
    const redirect = searchParams.get('redirect');
    if (redirect !== null && redirect !== '') {
        setPendingAuthRedirect(redirect);
    }
}
