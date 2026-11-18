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
