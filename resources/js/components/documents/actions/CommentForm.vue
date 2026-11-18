<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import VersionCommentController from '@/actions/App/Http/Controllers/Documents/VersionCommentController';
import AnchorFields from '@/components/documents/actions/AnchorFields.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';

defineProps<{ documentId: string; versionId: string }>();
</script>

<template>
    <Form
        v-bind="
            VersionCommentController.store.form({
                document: documentId,
                version: versionId,
            })
        "
        reset-on-success
        class="flex flex-col gap-3 rounded-lg border p-3"
        v-slot="{ errors, processing }"
    >
        <Label for="new-comment" class="font-medium">Tambah komentar</Label>
        <Textarea
            id="new-comment"
            name="body"
            rows="3"
            placeholder="Tandai bagian yang perlu diperhatikan"
        />
        <InputError :message="errors.body" />
        <AnchorFields />
        <Button type="submit" size="sm" class="self-end" :disabled="processing">
            Kirim komentar
        </Button>
    </Form>
</template>
