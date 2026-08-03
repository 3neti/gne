<?php

namespace App\Providers;

use App\Application\Authorization\DatabaseSubjectAuthorization;
use App\Application\Rostering\BalancedGreedyRosterGenerator;
use App\Application\Rostering\RecordRosterAudit;
use App\Contracts\Rostering\RosterAuditRecorder;
use App\Contracts\Rostering\RosterGenerator;
use App\Contracts\SubjectAuthorization;
use App\Integration\XDocument\BrowserDocumentRepresentationResolver;
use App\Integration\XDocument\ResolveXDocumentBrowserRepresentation;
use App\Models\User;
use App\Policies\CompilationSubjectPolicy;
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
        $this->app->bind(BrowserDocumentRepresentationResolver::class, ResolveXDocumentBrowserRepresentation::class);
        $this->app->bind(SubjectAuthorization::class, DatabaseSubjectAuthorization::class);
        $this->app->bind(RosterAuditRecorder::class, RecordRosterAudit::class);
        $this->app->bind(RosterGenerator::class, BalancedGreedyRosterGenerator::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        Gate::define('view-subject', [CompilationSubjectPolicy::class, 'view']);
        Gate::define('view-repository-workbench', fn (User $user): bool => (bool) $user->is_operator);
        Gate::define('view-rostering', fn (User $user): bool => (bool) $user->is_roster_administrator);
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
