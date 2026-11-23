import { ref, watch } from 'vue';

const KEY = 'external-review-name';

/**
 * The name an outside party gives, kept in the browser so they type it once. Storage can be
 * blocked, so every access is guarded and the page works without it.
 */
export function useReviewerName() {
    const name = ref('');

    try {
        name.value = window.localStorage.getItem(KEY) ?? '';
    } catch {
        // Storage unavailable: the name is simply asked again next time.
    }

    watch(name, (value) => {
        try {
            window.localStorage.setItem(KEY, value);
        } catch {
            // Ignored on purpose.
        }
    });

    return name;
}
