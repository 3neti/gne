<?php

namespace App\Domain\Rostering;

enum RosterPeriodStatus: string
{
    case Draft = 'draft';
    case CollectingRequests = 'collecting_requests';
    case ReadyForGeneration = 'ready_for_generation';
    case Generated = 'generated';
    case UnderReview = 'under_review';
    case Published = 'published';
    case Archived = 'archived';

    /** @return list<self> */
    public function allowedFoundationTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::CollectingRequests],
            self::CollectingRequests => [self::ReadyForGeneration],
            self::ReadyForGeneration => [self::Generated],
            self::Generated => [self::UnderReview],
            default => [],
        };
    }
}
