<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('reports all document sets through human and JSON commands', function () {
    $this->artisan('gne:documents')->assertSuccessful()->expectsOutputToContain('RESERVATION-000001')->expectsOutputToContain('PENDING');
    expect(Artisan::call('gne:documents', ['--json' => true]))->toBe(0);
    $output = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

    expect($output['document_sets'])->toHaveCount(2)
        ->and($output['document_sets'][0]['counts']['resolved'])->toBe(4)
        ->and($output['document_sets'][1]['counts']['pending'])->toBe(3);
});

it('targets a subject and rejects an unknown subject', function () {
    expect(Artisan::call('gne:documents', ['--subject' => 'RESERVATION-000002', '--json' => true]))->toBe(0);
    $output = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

    expect($output['document_sets'])->toHaveCount(1)
        ->and($output['document_sets'][0]['compilation_subject']['identifier'])->toBe('RESERVATION-000002')
        ->and(Artisan::call('gne:documents', ['--subject' => 'UNKNOWN']))->toBe(1);
});

it('shows subject lifecycle and document readiness in the authenticated workbench', function () {
    $this->withoutVite();
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user)->get(route('document_sets.index'))->assertSuccessful()->assertInertia(fn (Assert $page) => $page->component('DocumentSetWorkbench')->has('documentSets', 2));
    $this->actingAs($user)->get(route('document_sets.show', 'RESERVATION-000002'))->assertSuccessful()->assertInertia(fn (Assert $page) => $page
        ->component('DocumentSetWorkbench')
        ->has('documentSets', 1)
        ->where('documentSets.0.lifecycle.current_stage', 'invoice_accepted')
        ->where('documentSets.0.entries.3.readiness', 'pending')
        ->where('documentSets.0.entries.3.missing_evidence.0.artifact_type', 'PaymentApproval'));
    $this->actingAs($user)->get(route('document_sets.show', 'UNKNOWN'))->assertNotFound();
});

it('summarizes resolved and pending document-set entries during compilation', function () {
    expect(Artisan::call('gne:compile', ['--json' => true]))->toBe(0);
    $plan = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

    expect($plan['resolved_documents'])->toBe(6)
        ->and($plan['pending_documents'])->toBe(4)
        ->and($plan['unavailable_documents'])->toBe(0);
});
