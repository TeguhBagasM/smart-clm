<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { Send } from '@lucide/vue';
import { ref } from 'vue';
import TemplateDraftController from '@/actions/App/Http/Controllers/Templates/TemplateDraftController';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';

/**
 * Publishing asks first and says what follows: the new version is what new
 * contracts use; the one before stays for old contracts and cannot change.
 */
const props = defineProps<{
    templateId: number;
    draftRevision: number;
    versionNo: number;
    previousVersionNo: number | null;
    title: string;
    message: string;
    firstMessage: string;
    confirmLabel: string;
    disabled: boolean;
}>();

const open = ref(false);
</script>

<template>
    <Dialog v-model:open="open">
        <DialogTrigger as-child>
            <Button
                type="button"
                class="h-11 w-full md:h-9 md:w-auto"
                :disabled="disabled"
            >
                <Send />{{ confirmLabel }}
            </Button>
        </DialogTrigger>
        <DialogContent>
            <DialogHeader>
                <DialogTitle>{{
                    title.replace(':version', String(versionNo))
                }}</DialogTitle>
                <DialogDescription>
                    {{
                        previousVersionNo === null
                            ? firstMessage
                            : message
                                  .replace(
                                      ':previous',
                                      String(previousVersionNo),
                                  )
                                  .replace(':version', String(versionNo))
                    }}
                </DialogDescription>
            </DialogHeader>
            <Form
                v-bind="TemplateDraftController.publish.form(templateId)"
                v-slot="{ processing }"
                class="contents"
                @success="open = false"
            >
                <input
                    type="hidden"
                    name="expected_revision"
                    :value="draftRevision"
                />
                <DialogFooter class="flex-col-reverse gap-2 sm:flex-row">
                    <DialogClose as-child>
                        <Button
                            type="button"
                            variant="secondary"
                            class="h-11 md:h-9"
                            >Batal</Button
                        >
                    </DialogClose>
                    <Button
                        type="submit"
                        class="h-11 md:h-9"
                        :disabled="processing"
                    >
                        {{ processing ? '…' : confirmLabel }}
                    </Button>
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>
</template>
