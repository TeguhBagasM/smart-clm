<?php

use App\Enums\SystemRole;
use App\Models\AuditLog;
use App\Models\Dcs\TenantSetting;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Storage::fake('dcs');
    config(['dcs.disk' => 'dcs']);
    $this->tenant = Tenant::factory()->create();
    $this->admin = User::factory()->for($this->tenant)->role(SystemRole::Admin)->create();
});

function companyLogo(): UploadedFile
{
    return UploadedFile::fake()->image('logo.png', 300, 300);
}

describe('who may open it', function () {
    it('sends a guest to the sign-in page', function () {
        $this->get(route('company.edit'))->assertRedirect(route('login'));
        $this->post(route('company.logo.update'), ['file' => companyLogo()])->assertRedirect(route('login'));
    });

    it('refuses a user without the settings permission, and a super admin who belongs to no company', function () {
        $viewer = User::factory()->for($this->tenant)->role(SystemRole::Viewer)->create();
        $super = User::factory()->role(SystemRole::SuperAdmin)->create();

        $this->actingAs($viewer)->get(route('company.edit'))->assertForbidden();
        $this->actingAs($viewer)->post(route('company.logo.update'), ['file' => companyLogo()])->assertForbidden();
        $this->actingAs($super)->get(route('company.edit'))->assertForbidden();
        expect(TenantSetting::query()->count())->toBe(0);
    });

    it('shows the page to the company admin, with no logo until one is uploaded', function () {
        $this->actingAs($this->admin)->get(route('company.edit'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('settings/Company')->where('company.name', $this->tenant->name)->where('logo_url', null));
    });
});

describe('the logo', function () {
    it('is saved, written to the audit log, and shared with every page of the company', function () {
        $this->actingAs($this->admin)->post(route('company.logo.update'), ['file' => companyLogo()])->assertRedirect();

        $settings = TenantSetting::query()->where('tenant_id', $this->tenant->id)->sole();

        expect($settings->company_logo_key)->not->toBeNull()->and($settings->company_logo_mime)->toBe('image/png');
        Storage::disk('dcs')->assertExists($settings->company_logo_key);
        expect(AuditLog::query()->where('event', 'company.logo.updated')->where('tenant_id', $this->tenant->id)->exists())->toBeTrue();

        $this->actingAs($this->admin)->get(route('company.edit'))->assertInertia(fn (Assert $page) => $page->where('branding.logo_url', fn (string $url) => str_contains($url, '/branding/logo?v=')));
    });

    it('refuses a file that is not a picture, one that is too big, and a missing file, saying how to fix it', function () {
        $this->actingAs($this->admin)->post(route('company.logo.update'), ['file' => UploadedFile::fake()->create('logo.pdf', 10, 'application/pdf')])->assertSessionHasErrors(['file' => 'Logo harus berformat PNG, JPG atau WebP.']);
        $this->actingAs($this->admin)->post(route('company.logo.update'), ['file' => UploadedFile::fake()->image('besar.png', 300, 300)->size(3000)])->assertSessionHasErrors(['file' => 'Ukuran logo maksimal 2 MB.']);
        $this->actingAs($this->admin)->post(route('company.logo.update'), [])->assertSessionHasErrors(['file' => 'Pilih berkas logo.']);
        expect(TenantSetting::query()->count())->toBe(0);
    });

    it('is taken away on request, leaving the default icon', function () {
        $this->actingAs($this->admin)->post(route('company.logo.update'), ['file' => companyLogo()]);
        $this->actingAs($this->admin)->delete(route('company.logo.destroy'))->assertRedirect();

        expect(TenantSetting::query()->sole()->company_logo_key)->toBeNull()
            ->and(AuditLog::query()->where('event', 'company.logo.removed')->exists())->toBeTrue();

        $this->actingAs($this->admin)->get(route('company.edit'))->assertInertia(fn (Assert $page) => $page->where('branding.logo_url', null));
    });

    it('cannot be removed by a user without the permission', function () {
        $this->actingAs($this->admin)->post(route('company.logo.update'), ['file' => companyLogo()]);
        $viewer = User::factory()->for($this->tenant)->role(SystemRole::Viewer)->create();

        $this->actingAs($viewer)->delete(route('company.logo.destroy'))->assertForbidden();
        expect(TenantSetting::query()->sole()->company_logo_key)->not->toBeNull();
    });
});

describe('who sees which logo', function () {
    it('shows a visitor the logo of the only company, and a signed-in user only their own', function () {
        $this->actingAs($this->admin)->post(route('company.logo.update'), ['file' => companyLogo()]);
        auth()->logout();

        $this->get(route('branding.logo'))->assertOk()->assertHeader('Content-Type', 'image/png')->assertHeader('X-Content-Type-Options', 'nosniff');

        $other = Tenant::factory()->create();
        $stranger = User::factory()->for($other)->role(SystemRole::Admin)->create();

        $this->actingAs($stranger)->get(route('branding.logo'))->assertNotFound();
        $this->actingAs($this->admin)->get(route('branding.logo'))->assertOk();
    });

    it('shows the default icon to a visitor when there are several companies', function () {
        $this->actingAs($this->admin)->post(route('company.logo.update'), ['file' => companyLogo()]);
        auth()->logout();
        Tenant::factory()->create();

        $this->get(route('branding.logo'))->assertNotFound();
        $this->get(route('login'))->assertInertia(fn (Assert $page) => $page->where('branding.logo_url', null));
    });
});
