<script setup lang="ts">
import {
    AlertDialog,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Button, buttonVariants } from '@/components/ui/button';
import { answerConfirm, confirmState } from '@/composables/useConfirm';

/** The confirmation dialog of the whole app. Mount once in a layout; open it with `confirm()`. */
</script>

<template>
    <AlertDialog
        :open="confirmState.open"
        @update:open="(open) => !open && answerConfirm(false)"
    >
        <AlertDialogContent @backdrop="answerConfirm(false)">
            <div class="flex flex-col gap-2">
                <AlertDialogTitle>{{
                    confirmState.options.title
                }}</AlertDialogTitle>
                <AlertDialogDescription v-if="confirmState.options.message">
                    {{ confirmState.options.message }}
                </AlertDialogDescription>
            </div>
            <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                <AlertDialogCancel
                    :class="buttonVariants({ variant: 'outline' })"
                    @click="answerConfirm(false)"
                >
                    {{ confirmState.options.cancelLabel ?? 'Batal' }}
                </AlertDialogCancel>
                <Button
                    type="button"
                    :variant="
                        confirmState.options.destructive
                            ? 'destructive'
                            : 'default'
                    "
                    @click="answerConfirm(true)"
                >
                    {{ confirmState.options.confirmLabel ?? 'Ya, lanjutkan' }}
                </Button>
            </div>
        </AlertDialogContent>
    </AlertDialog>
</template>
