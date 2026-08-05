<?php

namespace App\Domain\Rostering;

enum RosterPolicyEvaluationPurpose: string
{
    case CurrentDiagnostics = 'current_diagnostics';
    case GenerationPreview = 'generation_preview';
    case GenerationCommit = 'generation_commit';
    case Validation = 'validation';
    case QualityAnalysis = 'quality_analysis';
    case HistoricalReplay = 'historical_replay';
}
