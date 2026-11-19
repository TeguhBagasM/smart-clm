import type { InjectionKey, Ref } from 'vue';
import type { BrandColor, HeaderFieldOption, HeaderPalette } from '@/types';

/** What every piece of the letterhead editor needs from the server. */
export type HeaderContext = {
    fields: HeaderFieldOption[];
    palette: HeaderPalette;
    brandColors: Ref<BrandColor[]>;
    hasCompanyLogo: boolean;
};

export const headerContextKey: InjectionKey<HeaderContext> =
    Symbol('headerContext');
