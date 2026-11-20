/**
 * Sends a write of the contract library as JSON and reads the answer. Inertia
 * cannot send an empty list (the server then says it is missing), so the draft
 * is sent this way. Failures keep the person's text: the caller keeps its state.
 */
export type LibraryResult<T> =
    | { ok: true; data: T }
    | { ok: false; errors: Record<string, string>; message: string };

function csrfToken(): string {
    if (typeof document === 'undefined') {
        return '';
    }

    const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/);

    return match ? decodeURIComponent(match[1]) : '';
}

export async function libraryRequest<T>(
    method: 'PUT' | 'POST',
    url: string,
    body: object,
    fallback: string,
): Promise<LibraryResult<T>> {
    return send<T>(method, url, JSON.stringify(body), fallback);
}

/** Reads a JSON endpoint of the library (for the preview). */
export async function libraryRead<T>(
    url: string,
    fallback: string,
): Promise<LibraryResult<T>> {
    return send<T>('GET', url, undefined, fallback);
}

async function send<T>(
    method: 'PUT' | 'POST' | 'GET',
    url: string,
    body: string | undefined,
    fallback: string,
): Promise<LibraryResult<T>> {
    try {
        const init: RequestInit = {
            method,
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                ...(body === undefined
                    ? {}
                    : { 'Content-Type': 'application/json' }),
                'X-XSRF-TOKEN': csrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
        };

        if (body !== undefined) {
            init.body = body;
        }

        const response = await fetch(url, init);
        const data = (await response.json().catch(() => ({}))) as {
            message?: string;
            errors?: Record<string, string[]>;
        };

        if (response.ok) {
            return { ok: true, data: data as T };
        }

        const errors = Object.fromEntries(
            Object.entries(data.errors ?? {}).map(([key, lines]) => [
                key,
                lines[0] ?? '',
            ]),
        );

        return {
            ok: false,
            errors,
            message:
                response.status === 429
                    ? 'Terlalu banyak permintaan. Tunggu sebentar, lalu coba lagi.'
                    : (data.message ?? fallback),
        };
    } catch {
        return { ok: false, errors: {}, message: fallback };
    }
}
