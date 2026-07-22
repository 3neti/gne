<?php

use Illuminate\Support\Facades\Artisan;

it('prepares human and JSON invoice requests without invoking an external package', function () {
    $this->artisan('gne:x-document:request --document=DOCUMENT-INVOICE --subject=RESERVATION-000001')
        ->assertSuccessful()
        ->expectsOutputToContain('Contract version: 1.0')
        ->expectsOutputToContain('x-document is not installed or invoked');

    expect(Artisan::call('gne:x-document:request', ['--document' => 'DOCUMENT-INVOICE', '--subject' => 'RESERVATION-000001', '--json' => true]))->toBe(0);
    $payload = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
    expect($payload['contract_version'])->toBe('1.0')
        ->and($payload['requested_driver'])->toBe('json')
        ->and($payload['document']['definition_identifier'])->toBe('DOCUMENT-INVOICE');
});

it('prepares receipt and certificate requests', function (string $document) {
    expect(Artisan::call('gne:x-document:request', ['--document' => $document, '--subject' => 'RESERVATION-000001', '--json' => true]))->toBe(0)
        ->and(json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR)['document']['definition_identifier'])->toBe($document);
})->with(['DOCUMENT-RECEIPT', 'DOCUMENT-RESERVATION-CERTIFICATE']);

it('rejects pending, unknown-document, and unknown-subject requests', function (array $options) {
    expect(Artisan::call('gne:x-document:request', $options))->toBe(1);
})->with([
    'pending' => [['--document' => 'DOCUMENT-BROCHURE', '--subject' => 'RESERVATION-000001']],
    'unknown document' => [['--document' => 'DOCUMENT-UNKNOWN', '--subject' => 'RESERVATION-000001']],
    'unknown subject' => [['--document' => 'DOCUMENT-INVOICE', '--subject' => 'RESERVATION-UNKNOWN']],
]);
