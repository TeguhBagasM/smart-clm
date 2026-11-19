import { ref } from 'vue';

const KEY = 'clm.header.recent-colors';
const MAX = 8;

function load(): string[] {
    try {
        const stored = JSON.parse(localStorage.getItem(KEY) ?? '[]');

        return Array.isArray(stored) ? stored.slice(0, MAX) : [];
    } catch {
        return [];
    }
}

const recent = ref<string[]>(load());

/** The colours this person used last: a convenience kept in the browser only. */
export function useRecentColors() {
    function remember(hex: string): void {
        recent.value = [hex, ...recent.value.filter((c) => c !== hex)].slice(
            0,
            MAX,
        );

        try {
            localStorage.setItem(KEY, JSON.stringify(recent.value));
        } catch {
            // Private window or blocked storage: the list lives for this page only.
        }
    }

    return { recent, remember };
}
