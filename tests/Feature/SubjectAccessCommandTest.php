<?php

use App\Models\SubjectAccessGrant;
use App\Models\User;

it('grants idempotently lists and revokes subject access', function () {
    $user = User::factory()->create(['email' => 'operator@example.test']);
    $options = ['--user' => $user->email, '--subject' => 'RESERVATION-000001', '--permission' => 'view', '--json' => true];

    $this->artisan('gne:subject-access:grant', $options)->assertSuccessful()->expectsOutputToContain('created');
    $this->artisan('gne:subject-access:grant', $options)->assertSuccessful()->expectsOutputToContain('already_exists');
    expect(SubjectAccessGrant::query()->count())->toBe(1);

    $this->artisan('gne:subject-access:list', ['--user' => $user->email, '--json' => true])
        ->assertSuccessful()->expectsOutputToContain('RESERVATION-000001');
    $this->artisan('gne:subject-access:revoke', $options)->assertSuccessful()->expectsOutputToContain('revoked');
    $this->artisan('gne:subject-access:revoke', $options)->assertSuccessful()->expectsOutputToContain('absent');
});

it('rejects unknown users subjects and permissions', function () {
    $user = User::factory()->create();

    $this->artisan('gne:subject-access:grant', ['--user' => 'missing@example.test', '--subject' => 'RESERVATION-000001'])->assertFailed();
    $this->artisan('gne:subject-access:grant', ['--user' => $user->email, '--subject' => 'UNKNOWN'])->assertFailed();
    $this->artisan('gne:subject-access:grant', ['--user' => $user->email, '--subject' => 'RESERVATION-000001', '--permission' => 'admin'])->assertFailed();
});
