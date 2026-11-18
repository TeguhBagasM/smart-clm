<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { ref } from 'vue';
import RoleController from '@/actions/App/Http/Controllers/Admin/RoleController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
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
import type { RoleRecord } from '@/types';

const props = defineProps<{
    role: RoleRecord;
}>();

const isOpen = ref(false);
</script>

<template>
    <section class="max-w-xl space-y-4 rounded-xl border p-6">
        <Heading
            variant="small"
            title="Hapus peran"
            :description="
                role.users_count > 0
                    ? `Peran ini masih dipakai ${role.users_count} pengguna. Pindahkan mereka ke peran lain terlebih dahulu.`
                    : 'Peran dihapus permanen. Tindakan ini tercatat di log audit.'
            "
        />

        <Dialog v-model:open="isOpen">
            <DialogTrigger as-child>
                <Button variant="destructive" :disabled="role.users_count > 0">
                    Hapus peran
                </Button>
            </DialogTrigger>
            <DialogContent>
                <Form
                    v-bind="RoleController.destroy.form(props.role.id)"
                    v-slot="{ errors, processing }"
                    class="space-y-6"
                >
                    <DialogHeader>
                        <DialogTitle>Hapus {{ role.name }}?</DialogTitle>
                        <DialogDescription>
                            Peran dan pemetaan izinnya akan dihapus permanen.
                        </DialogDescription>
                    </DialogHeader>
                    <InputError :message="errors.role" />
                    <DialogFooter class="gap-2">
                        <DialogClose as-child>
                            <Button variant="secondary">Batal</Button>
                        </DialogClose>
                        <Button
                            type="submit"
                            variant="destructive"
                            :disabled="processing"
                        >
                            Ya, hapus
                        </Button>
                    </DialogFooter>
                </Form>
            </DialogContent>
        </Dialog>
    </section>
</template>
