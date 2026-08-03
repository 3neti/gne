<?php

use App\Domain\Authorization\SubjectAccessDecision;
use App\Domain\Authorization\SubjectPermission;

it('keeps the permission vocabulary deliberately narrow', function () {
    expect(SubjectPermission::cases())->toBe([SubjectPermission::View])
        ->and(SubjectPermission::View->value)->toBe('view');
});

it('represents an explainable subject access decision', function () {
    $decision = new SubjectAccessDecision(false, 'grant_expired', 'RESERVATION-000001', SubjectPermission::View, 'database');

    expect($decision->allowed)->toBeFalse()
        ->and($decision->reason)->toBe('grant_expired')
        ->and($decision->subjectIdentifier)->toBe('RESERVATION-000001')
        ->and($decision->permission)->toBe(SubjectPermission::View)
        ->and($decision->grantSource)->toBe('database');
});
