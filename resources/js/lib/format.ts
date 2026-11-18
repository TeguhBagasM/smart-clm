const day = new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium' });
const dateTime = new Intl.DateTimeFormat('id-ID', {
    dateStyle: 'medium',
    timeStyle: 'short',
});

export function formatDate(value: string | null): string {
    return value ? day.format(new Date(value)) : '—';
}

export function formatDateTime(value: string | null): string {
    return value ? dateTime.format(new Date(value)) : '—';
}

/** "Terbitan 2 Revisi 1", as the documents themselves are numbered. */
export function versionLabel(version: {
    major: number;
    minor: number;
}): string {
    return `Terbitan ${version.major} Revisi ${version.minor}`;
}
