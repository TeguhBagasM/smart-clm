import { ref, watch, type Ref } from 'vue';

/** Natural size of an image in pixels, once it has loaded (null before, or when it fails). */
export function useImageSize(url: Ref<string | null>) {
    const size = ref<{ w: number; h: number } | null>(null);

    watch(
        url,
        (next) => {
            size.value = null;

            if (!next) return;

            const image = new Image();
            image.onload = () => {
                size.value = { w: image.naturalWidth, h: image.naturalHeight };
            };
            image.src = next;
        },
        { immediate: true },
    );

    return size;
}
