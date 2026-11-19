import { computed, type Ref } from 'vue';
import {
    clampHeight,
    clampLogoWidth,
    moveDivider,
    setRowAt,
} from '@/components/documents/config/header/headerDragOps';
import { startHandleDrag } from '@/components/documents/config/header/useHandleDrag';
import type { HeaderLayout } from '@/types';

const WIDTH = 483;

/** What dragging (or using the arrow keys on) each canvas handle does to the layout. */
export function useCanvasHandles(
    layout: Ref<HeaderLayout>,
    root: Ref<HTMLElement | null>,
    rows: Ref<{ w: number }[]>,
) {
    const direction = (
        event: KeyboardEvent,
        plus: string,
        minus: string,
    ): number => (event.key === plus ? 1 : event.key === minus ? -1 : 0);

    function dragDivider(event: PointerEvent, r: number, c: number): void {
        const start = layout.value.rows[r];
        const rowW = rows.value[r].w;

        startHandleDrag(event, root.value!, WIDTH, (dx, _dy, shift) => {
            layout.value = setRowAt(
                layout.value,
                r,
                moveDivider(start, c, (dx / rowW) * 100, shift ? 0 : 5),
            );
        });
    }

    function keyDivider(event: KeyboardEvent, r: number, c: number): void {
        const dir = direction(event, 'ArrowRight', 'ArrowLeft');

        if (!dir) return;

        event.preventDefault();
        layout.value = setRowAt(
            layout.value,
            r,
            moveDivider(
                layout.value.rows[r],
                c,
                dir * (event.shiftKey ? 1 : 5),
                event.shiftKey ? 0 : 5,
            ),
        );
    }

    function dragHeight(event: PointerEvent, r: number): void {
        const start = layout.value.rows[r];

        startHandleDrag(event, root.value!, WIDTH, (_dx, dy) => {
            layout.value = setRowAt(layout.value, r, {
                ...start,
                height: clampHeight(start.height + dy),
            });
        });
    }

    function keyHeight(event: KeyboardEvent, r: number): void {
        const dir = direction(event, 'ArrowDown', 'ArrowUp');

        if (!dir) return;

        event.preventDefault();

        const row = layout.value.rows[r];
        layout.value = setRowAt(layout.value, r, {
            ...row,
            height: clampHeight(row.height + dir * (event.shiftKey ? 1 : 3)),
        });
    }

    const logoDir = computed(() =>
        layout.value.logo.side === 'left' ? 1 : -1,
    );

    function setLogoWidth(width: number): void {
        layout.value = {
            ...layout.value,
            logo: { ...layout.value.logo, width: clampLogoWidth(width) },
        };
    }

    function dragLogo(event: PointerEvent): void {
        const start = layout.value.logo.width;

        startHandleDrag(event, root.value!, WIDTH, (dx) =>
            setLogoWidth(start + (dx / WIDTH) * 100 * logoDir.value),
        );
    }

    function keyLogo(event: KeyboardEvent): void {
        const dir = direction(event, 'ArrowRight', 'ArrowLeft');

        if (!dir) return;

        event.preventDefault();
        setLogoWidth(
            layout.value.logo.width +
                dir * logoDir.value * (event.shiftKey ? 1 : 2),
        );
    }

    return {
        dragDivider,
        keyDivider,
        dragHeight,
        keyHeight,
        dragLogo,
        keyLogo,
    };
}
