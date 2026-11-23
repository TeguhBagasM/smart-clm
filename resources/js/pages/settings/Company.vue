<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { ImageUp, Trash2 } from '@lucide/vue';
import { computed, onBeforeUnmount, ref } from 'vue';
import CompanyController from '@/actions/App/Http/Controllers/Settings/CompanyController';
import BrandMark from '@/components/BrandMark.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { confirm } from '@/composables/useConfirm';
import { edit } from '@/routes/company';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Perusahaan', href: edit() }],
    },
});

const props = defineProps<{
    company: { name: string };
    logo_url: string | null;
}>();

const MAX_MB = 2;
const form = useForm<{ file: File | null }>({ file: null });
const picker = ref<HTMLInputElement | null>(null);
const preview = ref<string | null>(null);
const tooBig = computed(() => (form.file?.size ?? 0) > MAX_MB * 1024 * 1024);

function pick(event: Event): void {
    const file = (event.target as HTMLInputElement).files?.[0] ?? null;

    clearPreview();
    form.clearErrors();
    form.file = file;
    preview.value = file ? URL.createObjectURL(file) : null;
}

function clearPreview(): void {
    if (preview.value) URL.revokeObjectURL(preview.value);

    preview.value = null;
}

function cancel(): void {
    clearPreview();
    form.reset();
    if (picker.value) picker.value.value = '';
}

function save(): void {
    form.post(CompanyController.update.url(), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: cancel,
    });
}

async function remove(): Promise<void> {
    const ok = await confirm({
        title: 'Hapus logo perusahaan?',
        message:
            'Aplikasi kembali memakai ikon bawaan. Kop dokumen yang memakai logo perusahaan juga tidak menampilkan logo lagi.',
        confirmLabel: 'Hapus logo',
        destructive: true,
    });

    if (ok)
        router.delete(CompanyController.destroy.url(), {
            preserveScroll: true,
        });
}

onBeforeUnmount(clearPreview);
</script>

<template>
    <Head title="Perusahaan" />

    <h1 class="sr-only">Pengaturan perusahaan</h1>

    <div class="flex flex-col gap-6">
        <Heading
            variant="small"
            :title="`Logo ${props.company.name}`"
            description="Logo ini tampil di sidebar, halaman masuk, dan ikon tab browser. Kalau belum diunggah, aplikasi memakai ikon bawaan."
        />

        <div class="flex flex-wrap items-center gap-4">
            <div
                class="flex size-24 items-center justify-center rounded-lg border bg-card p-2"
            >
                <img
                    v-if="preview"
                    :src="preview"
                    alt="Pratinjau logo yang akan disimpan"
                    class="size-full object-contain"
                />
                <img
                    v-else-if="props.logo_url"
                    :src="props.logo_url"
                    alt="Logo perusahaan saat ini"
                    class="size-full object-contain"
                />
                <BrandMark v-else size="lg" tone="plain" />
            </div>
            <p class="max-w-xs text-sm text-muted-foreground">
                {{
                    preview
                        ? 'Ini pratinjau. Logo baru dipakai setelah Anda menekan Simpan logo.'
                        : props.logo_url
                          ? 'Logo yang dipakai sekarang.'
                          : 'Belum ada logo. Ikon bawaan dipakai.'
                }}
            </p>
        </div>

        <div class="flex flex-col gap-2">
            <input
                ref="picker"
                type="file"
                accept="image/png,image/jpeg,image/webp"
                class="sr-only"
                aria-label="Pilih berkas logo"
                @change="pick"
            />
            <div class="flex flex-wrap gap-2">
                <Button
                    type="button"
                    variant="outline"
                    class="min-h-11"
                    @click="picker?.click()"
                >
                    <ImageUp />
                    {{ props.logo_url ? 'Ganti logo' : 'Pilih logo' }}
                </Button>
                <Button
                    v-if="form.file"
                    type="button"
                    class="min-h-11"
                    :disabled="form.processing || tooBig"
                    @click="save"
                >
                    Simpan logo
                </Button>
                <Button
                    v-if="form.file"
                    type="button"
                    variant="ghost"
                    class="min-h-11"
                    @click="cancel"
                >
                    Batal
                </Button>
                <Button
                    v-if="props.logo_url && !form.file"
                    type="button"
                    variant="ghost"
                    class="min-h-11 text-destructive"
                    @click="remove"
                >
                    <Trash2 /> Hapus logo
                </Button>
            </div>
            <p class="text-xs text-muted-foreground">
                Format PNG, JPG atau WebP, maksimal {{ MAX_MB }} MB. Pakai
                gambar yang jelas dan berlatar bersih, sebaiknya hampir persegi.
            </p>
            <p v-if="tooBig" class="text-sm text-destructive" role="alert">
                Ukuran berkas lebih dari {{ MAX_MB }} MB. Pilih gambar yang
                lebih kecil.
            </p>
            <InputError :message="form.errors.file" />
        </div>
    </div>
</template>
