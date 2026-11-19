import { computed, inject, toValue, type MaybeRefOrGetter } from 'vue';
import HeaderAssetController from '@/actions/App/Http/Controllers/Documents/Config/HeaderAssetController';
import { headerContextKey } from '@/components/documents/config/header/headerContext';
import { useImageSize } from '@/components/documents/config/header/logo/useImageSize';
import { fitLogo, type Box, type HeaderLayout } from '@/lib/headerGeometry';

/** Where the logo image is, and where it sits inside its box (as the PDF fits it). */
export function useLogoBox(
    layout: MaybeRefOrGetter<HeaderLayout>,
    logoArea: MaybeRefOrGetter<Box | null>,
) {
    const context = inject(headerContextKey);

    const url = computed(() => {
        const logo = toValue(layout).logo;

        if (!logo.enabled) return null;
        if (logo.source === 'custom') {
            return logo.assetId
                ? HeaderAssetController.show.url(logo.assetId)
                : null;
        }

        return context?.hasCompanyLogo
            ? HeaderAssetController.company.url()
            : null;
    });
    const natural = useImageSize(url);
    const box = computed(() => {
        const area = toValue(logoArea);

        if (!area) return null;

        const size = natural.value ?? { w: area.w, h: area.h };
        const { padding, valign } = toValue(layout).logo;

        return fitLogo(area, padding, size.w, size.h, valign);
    });

    return { url, box };
}
