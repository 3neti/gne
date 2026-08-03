<?php

namespace App\Console\Commands;

use App\Domain\Repository\ValidateRepository;
use App\Integration\XDocument\PackageBaselineMismatch;
use App\Integration\XDocument\XDocumentContractSmokeCheck;
use App\Integration\XDocument\XDocumentPackageBaselineAttestor;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Uri;
use LBHurtado\XDocumentLaravel\Contracts\DocumentHttpResponseFactory;

#[Signature('gne:mvp:smoke {--json : Emit structured smoke diagnostics}')]
#[Description('Verify the Property Reservation MVP runtime and delivery seams')]
final class GneMvpSmokeCommand extends Command
{
    public function handle(
        ValidateRepository $validator,
        XDocumentPackageBaselineAttestor $baselines,
        XDocumentContractSmokeCheck $contractSmoke,
        Container $container,
    ): int {
        $manifest = $validator->handle(base_path());
        if ($manifest->hasErrors()) {
            $this->components->error('MVP smoke stopped because repository validation failed.');

            return self::FAILURE;
        }

        try {
            $attestations = $baselines->assertMatches();
        } catch (PackageBaselineMismatch $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $profileAvailable = collect($manifest->profiles)->contains('identifier', 'PROFILE-PROPERTY-RESERVATION');
        $subjectAvailable = collect($manifest->artifacts)->contains(
            fn (array $artifact): bool => ($artifact['subject']['identifier'] ?? null) === 'RESERVATION-000001',
        );
        $smoke = $contractSmoke->handle(base_path());
        $browserRouteParameters = [
            'subject' => 'RESERVATION-000001',
            'document' => 'DOCUMENT-INVOICE',
        ];
        $browserRoutePath = route('documents.browser', $browserRouteParameters, absolute: false);
        $applicationUrl = (string) config('app.url');
        $browserRouteUrl = (string) Uri::of($applicationUrl)->withPath($browserRoutePath);
        $grantStorageAvailable = Schema::hasTable('gne_subject_access_grants');
        $subjectPolicyRegistered = Gate::has('view-subject');
        $inventoryFilteringActive = Route::has('document_sets.index');
        $result = [
            'passed' => $profileAvailable
                && $subjectAvailable
                && $smoke->passed
                && $container->bound(DocumentHttpResponseFactory::class)
                && Route::has('documents.browser')
                && $grantStorageAvailable
                && $subjectPolicyRegistered,
            'repository_valid' => true,
            'property_reservation_profile_available' => $profileAvailable,
            'completed_subject_available' => $subjectAvailable,
            'package_baselines' => array_map(
                fn ($attestation): array => $attestation->toArray(),
                $attestations,
            ),
            'contract_smoke' => $smoke->toArray(),
            'http_factory_bound' => $container->bound(DocumentHttpResponseFactory::class),
            'authenticated_route_available' => Route::has('documents.browser'),
            'subject_authorization' => [
                'grant_storage_available' => $grantStorageAvailable,
                'policy_registered' => $subjectPolicyRegistered,
                'browser_route_protected' => Route::has('documents.browser'),
                'inventory_filtering_active' => $inventoryFilteringActive,
            ],
            'application_url' => $applicationUrl,
            'browser_route_path' => $browserRoutePath,
            'browser_route_url' => $browserRouteUrl,
        ];

        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        } else {
            $this->components->info('Property Reservation MVP smoke diagnostics');
            $this->line('Repository valid: yes');
            $this->line('Package baselines match: yes');
            $this->line('Contract smoke passed: '.($smoke->passed ? 'yes' : 'no'));
            $this->line('HTTP response factory bound: '.($result['http_factory_bound'] ? 'yes' : 'no'));
            $this->line('Authenticated browser route available: '.($result['authenticated_route_available'] ? 'yes' : 'no'));
            $this->line('Subject authorization grant storage available: '.($grantStorageAvailable ? 'yes' : 'no'));
            $this->line('Subject authorization policy registered: '.($subjectPolicyRegistered ? 'yes' : 'no'));
            $this->line('Subject inventory filtering active: '.($inventoryFilteringActive ? 'yes' : 'no'));
            $this->line('Application URL: '.$result['application_url']);
            $this->line('Browser route path: '.$result['browser_route_path']);
            $this->line('Example document URL: '.$result['browser_route_url']);
            $this->line('Checksum: '.$smoke->checksum);
            $this->line('ETag: '.$smoke->etag);
        }

        return $result['passed'] ? self::SUCCESS : self::FAILURE;
    }
}
