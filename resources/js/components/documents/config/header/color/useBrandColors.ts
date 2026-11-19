import { inject } from 'vue';
import HeaderAssetController from '@/actions/App/Http/Controllers/Documents/Config/HeaderAssetController';
import { headerContextKey } from '@/components/documents/config/header/headerContext';
import { sendJson } from '@/lib/http';
import type { BrandColor } from '@/types';

const MAX_COLORS = 24;

/** The company's saved colours: shared by every picker, kept on the server. */
export function useBrandColors() {
    const context = inject(headerContextKey);
    const colors = context?.brandColors;

    async function save(next: BrandColor[]): Promise<void> {
        if (!colors) return;

        const response = await sendJson<{ colors: BrandColor[] }>(
            'PUT',
            HeaderAssetController.colors.url(),
            { colors: next },
        );
        colors.value = response.colors;
    }

    return {
        colors,
        full: () => (colors?.value.length ?? 0) >= MAX_COLORS,
        has: (hex: string) =>
            colors?.value.some((c) => c.hex === hex.toUpperCase()) ?? false,
        add: (hex: string) =>
            save([
                ...(colors?.value ?? []),
                { hex: hex.toUpperCase(), name: '' },
            ]),
        remove: (hex: string) =>
            save(
                (colors?.value ?? []).filter(
                    (c) => c.hex !== hex.toUpperCase(),
                ),
            ),
    };
}
