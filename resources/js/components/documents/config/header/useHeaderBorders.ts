import { computed, type ComputedRef } from 'vue';
import {
    ALL_SIDES,
    NO_SIDES,
    resolveBorders,
    sidesPath,
} from '@/components/documents/config/header/headerBorders';
import type { HeaderGeometry } from '@/lib/headerGeometry';
import type { HeaderLayout, HeaderSides } from '@/types';

/** The drawn lines of the preview: one SVG path per cell, and one for the logo block. */
export function useHeaderBorders(
    layout: () => HeaderLayout,
    geometry: ComputedRef<HeaderGeometry>,
) {
    const resolved = computed(() => {
        const { cells, logo } = geometry.value;
        const boxes = cells.map((box) => ({
            ...box,
            want:
                (box.cell as { borders?: typeof ALL_SIDES }).borders ??
                ALL_SIDES,
        }));

        if (logo) {
            boxes.push({
                ...logo,
                want: layout().logo.border === false ? NO_SIDES : ALL_SIDES,
            } as (typeof boxes)[number]);
        }

        return resolveBorders(boxes);
    });

    return {
        cellPath: (index: number): string =>
            sidesPath(geometry.value.cells[index], resolved.value[index]),
        logoPath: computed(() =>
            geometry.value.logo
                ? sidesPath(
                      geometry.value.logo,
                      resolved.value[geometry.value.cells.length],
                  )
                : '',
        ),
    };
}
