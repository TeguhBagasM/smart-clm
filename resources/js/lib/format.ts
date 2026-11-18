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

/** Whole days since a moment, never negative. */
export function daysSince(value: string | null): number {
    return value
        ? Math.max(
              0,
              Math.floor((Date.now() - new Date(value).getTime()) / 86_400_000),
          )
        : 0;
}

/** "Hari ini", "1 hari", "5 hari": how long something has waited. */
export function waitingLabel(value: string | null): string {
    const days = daysSince(value);

    return days === 0 ? 'Hari ini' : `${days} hari`;
}
