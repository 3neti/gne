<?php

use App\Quality\PhpStanNoRegressionVerifier;

function phpStanReport(array $messages): array
{
    return ['totals' => ['errors' => 0, 'file_errors' => count($messages)], 'files' => ['/workspace/app/Legacy.php' => ['messages' => $messages]]];
}

it('accepts unchanged reviewed legacy findings', function () {
    $message = ['identifier' => 'legacy.finding', 'message' => 'Known legacy issue.'];

    $result = (new PhpStanNoRegressionVerifier)->verify(phpStanReport([$message]), ['reviewed_commit' => 'abc', 'finding_count' => 1, 'finding_identities' => ['app/Legacy.php|legacy.finding|Known legacy issue.']], []);

    expect($result['accepted'])->toBeTrue()->and($result['new_findings'])->toBe([]);
});

it('rejects a simulated new finding identity and findings in new production files', function () {
    $message = ['identifier' => 'new.finding', 'message' => 'New issue.'];

    $result = (new PhpStanNoRegressionVerifier)->verify(phpStanReport([$message]), ['reviewed_commit' => 'abc', 'finding_count' => 1, 'finding_identities' => ['app/Legacy.php|legacy.finding|Known legacy issue.']], ['app/Legacy.php']);

    expect($result['accepted'])->toBeFalse()
        ->and($result['new_findings'])->toBe(['app/Legacy.php|new.finding|New issue.'])
        ->and($result['new_production_file_findings'])->not->toBeEmpty();
});

it('rejects an increased full-project finding count', function () {
    $messages = [['identifier' => 'one', 'message' => 'One'], ['identifier' => 'two', 'message' => 'Two']];

    $result = (new PhpStanNoRegressionVerifier)->verify(phpStanReport($messages), ['reviewed_commit' => 'abc', 'finding_count' => 1, 'finding_identities' => ['app/Legacy.php|one|One', 'app/Legacy.php|two|Two']], []);

    expect($result['accepted'])->toBeFalse()->and($result['current_findings'])->toBe(2);
});
