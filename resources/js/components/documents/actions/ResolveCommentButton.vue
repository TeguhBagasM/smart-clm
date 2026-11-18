<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { Check } from '@lucide/vue';
import VersionCommentController from '@/actions/App/Http/Controllers/Documents/VersionCommentController';
import { Button } from '@/components/ui/button';

defineProps<{ documentId: string; versionId: string; commentId: string }>();
</script>

<template>
    <Form
        v-bind="
            VersionCommentController.resolve.form({
                document: documentId,
                version: versionId,
                comment: commentId,
            })
        "
        v-slot="{ processing }"
    >
        <Button
            type="submit"
            variant="outline"
            size="sm"
            :disabled="processing"
        >
            <Check /> Tandai selesai
        </Button>
    </Form>
</template>
