import { describe, expect, it } from 'vitest';
import { confirm, confirmState, answerConfirm } from '@/composables/useConfirm';

describe('confirm()', () => {
    it('opens with the options and resolves true only when confirmed', async () => {
        const answer = confirm({
            title: 'Hapus?',
            message: 'Tidak bisa dibatalkan.',
            destructive: true,
        });

        expect(confirmState.open).toBe(true);
        expect(confirmState.options).toMatchObject({
            title: 'Hapus?',
            destructive: true,
        });

        answerConfirm(true);

        await expect(answer).resolves.toBe(true);
        expect(confirmState.open).toBe(false);
    });

    it('resolves false when cancelled, and when another question replaces it', async () => {
        const first = confirm({ title: 'Satu' });
        answerConfirm(false);
        await expect(first).resolves.toBe(false);

        const older = confirm({ title: 'Lama' });
        const newer = confirm({ title: 'Baru' });

        await expect(older).resolves.toBe(false);
        expect(confirmState.options.title).toBe('Baru');
        answerConfirm(true);
        await expect(newer).resolves.toBe(true);
    });
});
