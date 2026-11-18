<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/login';
import { request } from '@/routes/password';

defineOptions({
    layout: {
        title: 'Masuk ke akun Anda',
        description:
            'Gunakan email dan kata sandi yang diberikan administrator.',
    },
});

defineProps<{
    status?: string;
    canResetPassword: boolean;
}>();
</script>

<template>
    <Head title="Masuk" />

    <Alert v-if="status">
        <AlertDescription>{{ status }}</AlertDescription>
    </Alert>

    <Form
        v-bind="store.form()"
        :reset-on-success="['password']"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-6"
    >
        <div class="grid gap-2">
            <Label for="email">Email</Label>
            <Input
                id="email"
                type="email"
                name="email"
                required
                v-focus
                :tabindex="1"
                autocomplete="email"
                placeholder="nama@perusahaan.com"
            />
            <InputError :message="errors.email" />
        </div>

        <div class="grid gap-2">
            <div class="flex items-center justify-between">
                <Label for="password">Kata sandi</Label>
                <TextLink
                    v-if="canResetPassword"
                    :href="request()"
                    class="text-sm"
                    :tabindex="5"
                >
                    Lupa kata sandi?
                </TextLink>
            </div>
            <PasswordInput
                id="password"
                name="password"
                required
                :tabindex="2"
                autocomplete="current-password"
                placeholder="Kata sandi"
            />
            <InputError :message="errors.password" />
        </div>

        <Label for="remember" class="flex items-center gap-3 font-normal">
            <Checkbox id="remember" name="remember" :tabindex="3" />
            <span>Ingat saya</span>
        </Label>

        <Button
            type="submit"
            class="w-full"
            size="lg"
            :tabindex="4"
            :disabled="processing"
            data-test="login-button"
        >
            <Spinner v-if="processing" />
            Masuk
        </Button>
    </Form>
</template>
