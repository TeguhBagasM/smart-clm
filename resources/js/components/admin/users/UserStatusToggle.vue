<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { ref } from 'vue';
import UserStatusController from '@/actions/App/Http/Controllers/Admin/UserStatusController';
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
import type { ManagedUser } from '@/types';

const props = defineProps<{
    user: ManagedUser;
}>();

const isOpen = ref(false);
</script>

<template>
    <section class="max-w-xl space-y-4 rounded-xl border p-6">
        <Heading
            variant="small"
            :title="user.is_active ? 'Nonaktifkan akun' : 'Aktifkan akun'"
            :description="
                user.is_active
                    ? 'Pengguna akan langsung keluar dan tidak dapat masuk sampai diaktifkan lagi.'
                    : 'Pengguna dapat masuk kembali dengan kata sandinya.'
            "
        />

        <Dialog v-model:open="isOpen">
            <DialogTrigger as-child>
                <Button :variant="user.is_active ? 'destructive' : 'default'">
                    {{ user.is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                </Button>
            </DialogTrigger>
            <DialogContent>
                <Form
                    v-bind="UserStatusController.form(props.user.id)"
                    :options="{ preserveScroll: true }"
                    :transform="() => ({ is_active: !props.user.is_active })"
                    @success="isOpen = false"
                    v-slot="{ errors, processing }"
                    class="space-y-6"
                >
                    <DialogHeader>
                        <DialogTitle>
                            {{ user.is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                            {{ user.name }}?
                        </DialogTitle>
                        <DialogDescription>
                            {{
                                user.is_active
                                    ? 'Akses pengguna ini dicabut pada permintaan berikutnya.'
                                    : 'Pengguna ini akan dapat masuk kembali.'
                            }}
                        </DialogDescription>
                    </DialogHeader>
                    <InputError :message="errors.is_active" />
                    <DialogFooter class="gap-2">
                        <DialogClose as-child>
                            <Button variant="secondary">Batal</Button>
                        </DialogClose>
                        <Button
                            type="submit"
                            :variant="
                                user.is_active ? 'destructive' : 'default'
                            "
                            :disabled="processing"
                        >
                            Ya,
                            {{ user.is_active ? 'nonaktifkan' : 'aktifkan' }}
                        </Button>
                    </DialogFooter>
                </Form>
            </DialogContent>
        </Dialog>
    </section>
</template>
