<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useWizard } from '@/composables/useDraftWizard';

const { state, touch } = useWizard();

const languages = [
    { value: 'single', label: 'Bahasa Indonesia saja' },
    { value: 'multi', label: '+ Bahasa Inggris otomatis' },
] as const;
</script>

<template>
    <div class="flex flex-col gap-5">
        <div class="flex flex-col gap-1.5">
            <Label for="purpose">Tujuan</Label>
            <Textarea
                id="purpose"
                v-model="state.purpose"
                rows="4"
                placeholder="Menjelaskan tata cara..."
                @update:model-value="touch('purpose')"
            />
        </div>
        <div class="flex flex-col gap-1.5">
            <Label for="scope">Ruang lingkup</Label>
            <Textarea
                id="scope"
                v-model="state.scope"
                rows="4"
                placeholder="Berlaku untuk..."
                @update:model-value="touch('scope')"
            />
        </div>
        <fieldset class="flex flex-col gap-1.5">
            <legend class="mb-1 text-sm font-medium">Bahasa dokumen</legend>
            <div class="grid gap-2 sm:grid-cols-2">
                <Button
                    v-for="language in languages"
                    :key="language.value"
                    type="button"
                    :variant="
                        state.language_mode === language.value
                            ? 'default'
                            : 'outline'
                    "
                    @click="
                        state.language_mode = language.value;
                        touch('language');
                    "
                >
                    {{ language.label }}
                </Button>
            </div>
            <p class="text-xs text-muted-foreground">
                Menandai dokumen sebagai dwibahasa (Indonesia / English).
                Salinan terjemahan Inggris dibuat terpisah sebagai referensi.
            </p>
        </fieldset>
    </div>
</template>
