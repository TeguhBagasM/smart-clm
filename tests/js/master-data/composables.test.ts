import { beforeEach, describe, expect, it, vi } from 'vitest';
import { answerConfirm, confirmState } from '@/composables/useConfirm';
import { useMasterList } from '@/composables/useMasterList';
import { useReorder } from '@/composables/useReorder';
import type { MasterInfo, OrderRow } from '@/types';

const router = vi.hoisted(() => ({ patch: vi.fn(), put: vi.fn() }));
vi.mock('@inertiajs/vue3', () => ({ router }));

const info = {
    key: 'platforms',
    columns: [],
    help: {},
    usage_covers_documents: false,
    usage_notice: 'Dokumen belum ikut dihitung.',
    texts: {
        impact_hide_title: 'Sembunyikan ":name"?',
        impact_used: 'Dipakai :usage data.',
        impact_unused: 'Belum dipakai.',
        impact_show_title: 'Tampilkan ":name"?',
        impact_show: 'Muncul lagi.',
    },
} as unknown as MasterInfo;

beforeEach(() => {
    router.patch.mockReset();
    router.put.mockReset();
});

describe('useMasterList', () => {
    it('asks first, saying how many records use the row, and hides only after confirming', async () => {
        const { setActive } = useMasterList(() => info);
        const done = setActive(
            { id: 5, name: 'Tiketux', usage_count: 4 },
            true,
            (row) => `/x/${row.id}`,
        );

        expect(confirmState.open).toBe(true);
        expect(confirmState.options).toMatchObject({
            title: 'Sembunyikan "Tiketux"?',
            message: 'Dipakai 4 data.',
            destructive: true,
            confirmLabel: 'Sembunyikan',
        });
        expect(router.patch).not.toHaveBeenCalled();

        answerConfirm(true);
        await done;

        expect(router.patch).toHaveBeenCalledWith(
            '/x/5',
            {},
            expect.objectContaining({ preserveScroll: true }),
        );
    });

    it('does nothing when the question is cancelled', async () => {
        const { setActive, busy } = useMasterList(() => info);
        const done = setActive({ id: 5, name: 'X' }, true, () => '/x');

        answerConfirm(false);
        await done;

        expect(router.patch).not.toHaveBeenCalled();
        expect(busy.value).toBeNull();
    });

    it('sends once even when the button is pressed twice, and frees the button afterwards', async () => {
        const { setActive, busy } = useMasterList(() => info);
        const first = setActive(
            { id: 1, name: 'X', usage_count: 0 },
            true,
            () => '/x',
        );
        answerConfirm(true);
        await first;

        expect(busy.value).toBe(1);
        await setActive({ id: 1, name: 'X', usage_count: 0 }, true, () => '/x');
        expect(router.patch).toHaveBeenCalledTimes(1);

        router.patch.mock.calls[0][2].onFinish();
        expect(busy.value).toBeNull();
    });

    it('does not ask in the hiding words when showing a row again', async () => {
        const { setActive } = useMasterList(() => info);
        const done = setActive(
            { id: 2, name: 'Y', usage_count: 7 },
            false,
            () => '/y',
        );

        expect(confirmState.options).toMatchObject({
            title: 'Tampilkan "Y"?',
            destructive: false,
            confirmLabel: 'Tampilkan',
        });
        answerConfirm(true);
        await done;
    });
});

describe('useReorder', () => {
    const rows = (): OrderRow[] =>
        [1, 2, 3].map((id) => ({
            id,
            name: `R${id}`,
            code: null,
            is_active: true,
        }));
    const ids = (r: { order: { value: OrderRow[] } }) =>
        r.order.value.map((row) => row.id);

    it('tracks whether the order changed', () => {
        const reorder = useReorder(() => 'gagal');
        reorder.start(rows());

        expect(reorder.dirty.value).toBe(false);
        reorder.move(0, 2);
        expect(ids(reorder)).toEqual([2, 3, 1]);
        expect(reorder.dirty.value).toBe(true);
        reorder.move(2, 0);
        expect(reorder.dirty.value).toBe(false);
    });

    it('sends the whole list with the extra fields, and not when nothing changed', () => {
        const reorder = useReorder(() => 'gagal');
        reorder.start(rows());
        reorder.save('/order', { tenant_id: 9 }, () => {});
        expect(router.put).not.toHaveBeenCalled();

        reorder.move(0, 1);
        reorder.save('/order', { tenant_id: 9 }, () => {});

        expect(router.put).toHaveBeenCalledWith(
            '/order',
            { ids: [2, 1, 3], tenant_id: 9 },
            expect.objectContaining({ preserveScroll: true }),
        );
    });

    it.each(['onError', 'onHttpException', 'onNetworkError'])(
        'puts the original order back and shows the message when saving fails (%s)',
        (hook) => {
            const reorder = useReorder(() => 'Urutan gagal disimpan.');
            reorder.start(rows());
            reorder.move(0, 2);
            reorder.save('/order', {}, () => {});

            router.put.mock.calls[0][2][hook]();
            router.put.mock.calls[0][2].onFinish();

            expect(ids(reorder)).toEqual([1, 2, 3]);
            expect(reorder.error.value).toBe('Urutan gagal disimpan.');
            expect(reorder.saving.value).toBe(false);
            expect(reorder.dirty.value).toBe(false);
        },
    );

    it('calls done on success and sends only once while saving', () => {
        const reorder = useReorder(() => 'gagal');
        const done = vi.fn();
        reorder.start(rows());
        reorder.move(1, 0);
        reorder.save('/order', {}, done);
        reorder.save('/order', {}, done);

        expect(router.put).toHaveBeenCalledTimes(1);
        expect(reorder.saving.value).toBe(true);
        router.put.mock.calls[0][2].onSuccess();
        expect(done).toHaveBeenCalledOnce();
    });
});
