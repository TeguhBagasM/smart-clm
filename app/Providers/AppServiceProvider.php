<?php

namespace App\Providers;

use App\Enums\Permission;
use App\Models\User;
use App\Support\Dcs\Ai\DcsAi;
use App\Support\Dcs\Ai\NodeDcsAi;
use App\Support\Dcs\CurrentTenant;
use App\Support\Dcs\Notifications\DcsNotifier;
use App\Support\Dcs\Notifications\NullDcsNotifier;
use App\Support\Dcs\Pdf\NodePdfEngine;
use App\Support\Dcs\Pdf\PdfEngine;
use App\Support\Rbac\PermissionResolver;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(PermissionResolver::class);
        $this->app->scoped(CurrentTenant::class);
        $this->app->bind(DcsAi::class, fn (): DcsAi => new NodeDcsAi(
            config('dcs.ai.url'),
            config('dcs.ai.secret'),
            (int) config('dcs.ai.timeout'),
        ));
        $this->app->bind(DcsNotifier::class, NullDcsNotifier::class);
        $this->app->bind(PdfEngine::class, fn (): PdfEngine => new NodePdfEngine(
            config('dcs.pdf.url'),
            config('dcs.pdf.secret'),
            (int) config('dcs.pdf.timeout'),
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureAuthorization();
    }

    /**
     * Register one gate ability per permission so routes and policies can call
     * `can(Permission::X)`. Grants are read from the database by the resolver.
     */
    protected function configureAuthorization(): void
    {
        foreach (Permission::cases() as $permission) {
            Gate::define(
                $permission->value,
                fn (User $user): bool => $this->app->make(PermissionResolver::class)->allows($user, $permission),
            );
        }
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
