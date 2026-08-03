<?php

use App\Domain\Rostering\RosterPeriodStatus;

it('declares the complete lifecycle vocabulary but enables only early transitions', function () {
    expect(array_map(fn (RosterPeriodStatus $status): string => $status->value, RosterPeriodStatus::cases()))->toBe(['draft', 'collecting_requests', 'ready_for_generation', 'generated', 'under_review', 'published', 'archived'])
        ->and(RosterPeriodStatus::Draft->allowedFoundationTransitions())->toBe([RosterPeriodStatus::CollectingRequests])
        ->and(RosterPeriodStatus::CollectingRequests->allowedFoundationTransitions())->toBe([RosterPeriodStatus::ReadyForGeneration])
        ->and(RosterPeriodStatus::Published->allowedFoundationTransitions())->toBe([]);
});
