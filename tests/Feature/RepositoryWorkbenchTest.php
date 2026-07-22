<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('protects the repository workbench', function () {
    $this->get(route('repository'))->assertRedirect(route('login'));
});

it('shows repository-derived status to an authenticated user', function () {
    $this->withoutVite();
    $user = User::factory()->create(['email_verified_at' => now()]);
    $this->actingAs($user)->get(route('repository'))->assertSuccessful()->assertInertia(fn (Assert $page) => $page
        ->component('RepositoryWorkbench')
        ->where('repository.canonical_source_path', 'business')
        ->where('repository.generated_projection_path', '.gne')
        ->has('repository.profiles', 1)
        ->has('repository.scenarios', 1)
    );
});
