export type JsonValue =
    | string
    | number
    | boolean
    | null
    | JsonValue[]
    | { [key: string]: JsonValue };

type ApiErrorBody = {
    message?: string;
    errors?: Record<string, string[]>;
};

export class ApiError extends Error {
    readonly status: number;

    readonly errors: Record<string, string[]>;

    constructor(status: number, message: string, errors: Record<string, string[]> = {}) {
        super(message);
        this.name = 'ApiError';
        this.status = status;
        this.errors = errors;
    }
}

function csrfToken(): string {
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta?.getAttribute('content') ?? '';
}

function apiErrorFromPayload(status: number, payload: ApiErrorBody): ApiError {
    const fieldErrors = payload.errors ?? {};
    const firstFieldError = Object.values(fieldErrors)
        .flat()
        .find((value): value is string => typeof value === 'string' && value.trim() !== '');
    const message =
        firstFieldError ??
        (typeof payload.message === 'string' && payload.message.trim() !== ''
            ? payload.message
            : 'Request failed.');

    return new ApiError(status, message, fieldErrors);
}

async function ensureCsrfCookie(): Promise<void> {
    await fetch('/sanctum/csrf-cookie', {
        method: 'GET',
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
    });
}

function isMutatingMethod(method: string | undefined): boolean {
    const normalized = (method ?? 'GET').toUpperCase();
    return normalized !== 'GET' && normalized !== 'HEAD' && normalized !== 'OPTIONS';
}

export async function apiRequest<T>(
    path: string,
    options: RequestInit = {},
    retryOnCsrf = true,
): Promise<T> {
    const headers = new Headers(options.headers);
    headers.set('Accept', 'application/json');
    headers.set('X-Requested-With', 'XMLHttpRequest');

    if (!headers.has('Content-Type') && options.body && !(options.body instanceof FormData)) {
        headers.set('Content-Type', 'application/json');
    }

    // Prefer the small meta CSRF token. Avoid echoing the XSRF cookie into a second
    // request header — that contributes to HTTP 431 on production proxies.
    if (isMutatingMethod(typeof options.method === 'string' ? options.method : undefined)) {
        const token = csrfToken();
        if (token !== '') {
            headers.set('X-CSRF-TOKEN', token);
        }
    }

    const response = await fetch(path.startsWith('/') ? path : `/api/v1/${path}`, {
        ...options,
        credentials: 'same-origin',
        headers,
    });

    if (response.status === 419 && retryOnCsrf) {
        await ensureCsrfCookie();
        return apiRequest<T>(path, options, false);
    }

    if (response.status === 204) {
        return undefined as T;
    }

    const payload = (await response.json().catch(() => ({}))) as ApiErrorBody & T;

    if (!response.ok) {
        throw apiErrorFromPayload(response.status, payload);
    }

    return payload as T;
}

export async function apiGet<T>(path: string): Promise<T> {
    return apiRequest<T>(path.startsWith('/api/') ? path : `/api/v1/${path}`);
}

/**
 * Public GETs that omit cookies (avoids HTTP 431 from oversized Cookie headers).
 */
export async function apiGetPublic<T>(path: string): Promise<T> {
    const response = await fetch(path.startsWith('/') ? path : `/api/v1/${path}`, {
        method: 'GET',
        credentials: 'omit',
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
    });

    const payload = (await response.json().catch(() => ({}))) as ApiErrorBody & T;

    if (!response.ok) {
        throw apiErrorFromPayload(response.status, payload);
    }

    return payload as T;
}

export async function apiPost<T>(path: string, body?: JsonValue): Promise<T> {
    await ensureCsrfCookie();
    return apiRequest<T>(path.startsWith('/api/') ? path : `/api/v1/${path}`, {
        method: 'POST',
        body: body === undefined ? undefined : JSON.stringify(body),
    });
}

export async function apiUpload<T>(path: string, formData: FormData): Promise<T> {
    await ensureCsrfCookie();
    return apiRequest<T>(path.startsWith('/api/') ? path : `/api/v1/${path}`, {
        method: 'POST',
        body: formData,
    });
}

export async function apiPut<T>(path: string, body?: JsonValue): Promise<T> {
    await ensureCsrfCookie();
    return apiRequest<T>(path.startsWith('/api/') ? path : `/api/v1/${path}`, {
        method: 'PUT',
        body: body === undefined ? undefined : JSON.stringify(body),
    });
}

export async function apiPatch<T>(path: string, body?: JsonValue): Promise<T> {
    await ensureCsrfCookie();
    return apiRequest<T>(path.startsWith('/api/') ? path : `/api/v1/${path}`, {
        method: 'PATCH',
        body: body === undefined ? undefined : JSON.stringify(body),
    });
}

export async function apiDelete<T>(path: string): Promise<T> {
    await ensureCsrfCookie();
    return apiRequest<T>(path.startsWith('/api/') ? path : `/api/v1/${path}`, {
        method: 'DELETE',
    });
}
