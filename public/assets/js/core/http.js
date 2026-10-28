/**
 * Tiny AJAX client for the JSON API (built on fetch).
 *
 * - sends/receives JSON
 * - attaches the CSRF token from <meta name="csrf-token"> to every write
 * - turns error envelopes `{ ok: false, error: {...} }` into ApiError exceptions
 */

export class ApiError extends Error {
    constructor(status, payload) {
        const error = payload?.error ?? {};
        super(error.message || 'Something went wrong. Please try again.');
        this.name = 'ApiError';
        this.status = status;
        this.code = error.code || 'unknown_error';
        this.fields = error.fields || {};
        this.retryAfter = error.retry_after ?? null;
    }
}

const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';

async function request(method, url, body) {
    const headers = { Accept: 'application/json' };
    const init = { method, headers, credentials: 'same-origin' };

    if (method !== 'GET') {
        headers['Content-Type'] = 'application/json';
        headers['X-CSRF-Token'] = csrfToken();
        init.body = JSON.stringify(body ?? {});
    }

    let response;

    try {
        response = await fetch(url, init);
    } catch {
        throw new ApiError(0, {
            error: { code: 'network_error', message: 'We could not reach the server. Check your connection and try again.' },
        });
    }

    const payload = await response.json().catch(() => null);

    if (!response.ok || payload?.ok !== true) {
        throw new ApiError(response.status, payload);
    }

    return payload;
}

export const api = {
    get: (url) => request('GET', url),
    post: (url, body) => request('POST', url, body),
};
