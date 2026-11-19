<script setup lang="ts">
import { inject } from 'vue';
import HeaderAssetController from '@/actions/App/Http/Controllers/Documents/Config/HeaderAssetController';
import { headerContextKey } from '@/components/documents/config/header/headerContext';
import LogoUpload from '@/components/documents/config/header/logo/LogoUpload.vue';
import NumberStepper from '@/components/documents/config/header/NumberStepper.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { HeaderLogo } from '@/types';

const model = defineModel<HeaderLogo>({ required: true });
const props = defineProps<{ rowCount: number }>();

const context = inject(headerContextKey);

function patch(values: Partial<HeaderLogo>): void {
    model.value = { ...model.value, ...values };
}

const valigns = [
    { value: 'top', label: 'Atas' },
    { value: 'middle', label: 'Tengah' },
    { value: 'bottom', label: 'Bawah' },
];
const grid =
    'grid grid-cols-[repeat(auto-fit,minmax(min(100%,13rem),1fr))] gap-4';
const heading =
    'text-xs font-semibold tracking-wide text-muted-foreground uppercase';
</script>

<template>
    <div class="@container flex flex-col gap-5">
        <section class="flex flex-col gap-3">
            <h4 :class="heading">Logo</h4>
            <Label class="flex items-center gap-2 font-normal">
                <Checkbox
                    :model-value="model.enabled"
                    @update:model-value="patch({ enabled: $event === true })"
                />
                Tampilkan logo di kop
            </Label>
            <fieldset v-if="model.enabled" class="flex min-w-0 flex-col gap-2">
                <legend class="sr-only">Sumber logo</legend>
                <Label class="flex items-center gap-2 font-normal">
                    <input
                        type="radio"
                        name="logo-source"
                        value="company"
                        :checked="model.source === 'company'"
                        @change="patch({ source: 'company' })"
                    />
                    Logo perusahaan
                </Label>
                <div
                    v-if="model.source === 'company'"
                    class="ml-6 flex flex-col gap-2"
                >
                    <img
                        v-if="context?.hasCompanyLogo"
                        :src="HeaderAssetController.company.url()"
                        alt="Logo perusahaan"
                        class="h-12 max-w-32 object-contain"
                    />
                    <template v-else>
                        <p class="text-xs text-muted-foreground">
                            Logo perusahaan belum diatur, jadi kop tercetak
                            tanpa gambar. Layar untuk mengaturnya belum ada di
                            aplikasi; sementara itu unggah logo khusus untuk
                            jenis dokumen ini.
                        </p>
                        <Button
                            type="button"
                            size="sm"
                            class="self-start"
                            @click="patch({ source: 'custom' })"
                            >Unggah logo khusus</Button
                        >
                    </template>
                </div>
                <Label class="flex items-center gap-2 font-normal">
                    <input
                        type="radio"
                        name="logo-source"
                        value="custom"
                        :checked="model.source === 'custom'"
                        @change="patch({ source: 'custom' })"
                    />
                    Logo khusus jenis dokumen ini
                </Label>
                <LogoUpload
                    v-if="model.source === 'custom'"
                    :model-value="model.assetId"
                    class="ml-6"
                    @update:model-value="patch({ assetId: $event })"
                />
            </fieldset>
        </section>
        <template v-if="model.enabled">
            <section class="flex flex-col gap-3">
                <h4 :class="heading">Ukuran</h4>
                <div :class="grid">
                    <NumberStepper
                        :model-value="model.width"
                        label="Lebar logo"
                        unit="%"
                        hint="Persen dari lebar kop."
                        :min="10"
                        :max="40"
                        slider
                        @update:model-value="patch({ width: $event })"
                    />
                    <NumberStepper
                        :model-value="model.rows"
                        label="Baris di samping"
                        hint="Berapa baris pertama yang sejajar dengan logo."
                        :min="1"
                        :max="props.rowCount"
                        @update:model-value="patch({ rows: $event })"
                    />
                </div>
            </section>
            <section class="flex flex-col gap-3">
                <h4 :class="heading">Posisi</h4>
                <div :class="grid">
                    <div class="flex min-w-0 flex-col gap-1.5">
                        <span class="text-sm font-medium">Horizontal</span>
                        <Select
                            :model-value="model.side"
                            @update:model-value="
                                patch({ side: $event as HeaderLogo['side'] })
                            "
                        >
                            <SelectTrigger
                                class="w-full min-w-0"
                                aria-label="Sisi logo"
                                ><SelectValue
                            /></SelectTrigger>
                            <SelectContent>
                                <SelectItem value="left">Kiri</SelectItem>
                                <SelectItem value="right">Kanan</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="flex min-w-0 flex-col gap-1.5">
                        <span class="text-sm font-medium">Vertikal</span>
                        <Select
                            :model-value="model.valign"
                            @update:model-value="
                                patch({
                                    valign: $event as HeaderLogo['valign'],
                                })
                            "
                        >
                            <SelectTrigger
                                class="w-full min-w-0"
                                aria-label="Rata vertikal"
                                ><SelectValue
                            /></SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="v in valigns"
                                    :key="v.value"
                                    :value="v.value"
                                    >{{ v.label }}</SelectItem
                                >
                            </SelectContent>
                        </Select>
                    </div>
                    <NumberStepper
                        :model-value="model.padding"
                        label="Jarak dalam"
                        unit="pt"
                        hint="Ruang antara logo dan garis kotaknya."
                        :min="0"
                        :max="20"
                        slider
                        @update:model-value="patch({ padding: $event })"
                    />
                </div>
            </section>
        </template>
    </div>
</template>
