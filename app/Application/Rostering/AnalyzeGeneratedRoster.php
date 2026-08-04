<?php

namespace App\Application\Rostering;

use App\Domain\Rostering\GeneratedRosterResult;
use App\Domain\Rostering\RosterGenerationInput;

final readonly class AnalyzeGeneratedRoster
{
    public function __construct(private AnalyzeRosterGenerationFeasibility $feasibility, private AnalyzeDraftRosterQuality $quality) {}

    public function handle(RosterGenerationInput $input, GeneratedRosterResult $result): GeneratedRosterResult
    {
        $feasibility = $this->feasibility->handle($input);
        $quality = $this->quality->handle($input, $result, $feasibility);

        return $result->withAnalysis($feasibility, $quality);
    }
}
