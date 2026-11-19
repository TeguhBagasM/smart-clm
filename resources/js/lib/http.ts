function csrfToken(): string {
    const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/);

    return match ? decodeURIComponent(match[1]) : '';
}

/** POSTs form data to a JSON endpoint of the app and returns its JSON. */
export async function postForm<T>(url: string, body: FormData): Promise<T> {
    const response = await fetch(url, {
        method: 'POST',
        body,
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'X-XSRF-TOKEN': csrfToken(),
            'X-Requested-With': 'XMLHttpRequest',
        },
    });
    const data = (await response.json().catch(() => ({}))) as {
        message?: string;
        errors?: Record<string, string[]>;
    };

    if (!response.ok) {
        throw new Error(
            Object.values(data.errors ?? {})[0]?.[0] ??
                data.message ??
                'Permintaan gagal.',
        );
    }

    return data as T;
}

/** Sends JSON to an endpoint of the app with the given method and returns its JSON. */
export async function sendJson<T>(
    method: 'PUT' | 'POST' | 'DELETE',
    url: string,
    body: unknown,
): Promise<T> {
    const response = await fetch(url, {
        method,
        body: JSON.stringify(body),
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-XSRF-TOKEN': csrfToken(),
            'X-Requested-With': 'XMLHttpRequest',
        },
    });
    const data = (await response.json().catch(() => ({}))) as {
        message?: string;
    };

    if (!response.ok) throw new Error(data.message ?? 'Permintaan gagal.');

    return data as T;
}

/** Uploads a file to a JSON endpoint, reporting progress from 0 to 100. */
export function uploadFile<T>(
    url: string,
    file: File,
    onProgress: (percent: number) => void,
): Promise<T> {
    return new Promise((resolve, reject) => {
        const request = new XMLHttpRequest();
        const body = new FormData();
        body.append('file', file);

        request.open('POST', url);
        request.setRequestHeader('Accept', 'application/json');
        request.setRequestHeader('X-XSRF-TOKEN', csrfToken());
        request.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        request.upload.onprogress = (event) => {
            if (event.lengthComputable) {
                onProgress(Math.round((event.loaded / event.total) * 100));
            }
        };
        request.onerror = () =>
            reject(new Error('Koneksi terputus. Coba lagi.'));
        request.onload = () => {
            let data: { message?: string; errors?: Record<string, string[]> } =
                {};

            try {
                data = JSON.parse(request.responseText);
            } catch {
                // Not JSON: the generic message below applies.
            }

            if (request.status >= 200 && request.status < 300) {
                resolve(data as T);

                return;
            }

            reject(
                new Error(
                    Object.values(data.errors ?? {})[0]?.[0] ??
                        data.message ??
                        'Unggahan gagal.',
                ),
            );
        };
        request.send(body);
    });
}
