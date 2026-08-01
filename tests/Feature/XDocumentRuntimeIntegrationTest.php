<?php

use App\Domain\Compilation\DocumentEvidenceNotFound;
use App\Integration\XDocument\ResolveXDocumentBrowserRepresentation;
use Illuminate\Support\Facades\Artisan;
use LBHurtado\XDocument\Browser\Host\BrowserRepresentation;
use LBHurtado\XDocument\Contract\ValidateDocumentCompilationRequest;

it('loads real packages and preserves repository meaning in styled composition', function () {
    $resolver = app(ResolveXDocumentBrowserRepresentation::class);
    $response = $resolver->handle(base_path(), 'DOCUMENT-INVOICE', 'RESERVATION-000001');

    expect(class_exists(ValidateDocumentCompilationRequest::class))->toBeTrue()
        ->and($response->descriptor->representation)->toBe(BrowserRepresentation::CompositionStyledHtml)
        ->and($response->descriptor->format)->toBe('browser-composition-html-styled/1.0')
        ->and($response->output->mediaType)->toBe('text/html; charset=utf-8')
        ->and($response->output->inlineContent)->toContain(
            'RESERVATION-000001',
            'Ana Example',
            'LOT-037',
            '50000',
            'Invoice amount',
        )
        ->and($response->output->inlineContent)->not->toContain(
            '<script',
            '<form',
            'RESERVATION-000002',
            'Ben Example',
            '75000',
        );
});

it('produces identical output and identity for unchanged repository evidence', function () {
    $resolver = app(ResolveXDocumentBrowserRepresentation::class);

    $first = $resolver->handle(base_path(), 'DOCUMENT-INVOICE', 'RESERVATION-000001');
    $second = $resolver->handle(base_path(), 'DOCUMENT-INVOICE', 'RESERVATION-000001');

    expect($second->output->inlineContent)->toBe($first->output->inlineContent)
        ->and($second->output->checksum)->toBe($first->output->checksum)
        ->and($second->output->byteLength)->toBe($first->output->byteLength)
        ->and($second->etag)->toBe($first->etag);
});

it('does not allow pending documents to enter x-document runtime compilation', function () {
    app(ResolveXDocumentBrowserRepresentation::class)->handle(
        base_path(),
        'DOCUMENT-BROCHURE',
        'RESERVATION-000001',
    );
})->throws(DocumentEvidenceNotFound::class);

it('compiles a real styled browser composition through x-document', function () {
    expect(Artisan::call('gne:x-document:compile', [
        '--document' => 'DOCUMENT-INVOICE',
        '--subject' => 'RESERVATION-000001',
        '--json' => true,
    ]))->toBe(0);

    $result = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

    expect($result)
        ->toMatchArray([
            'document' => 'DOCUMENT-INVOICE',
            'subject' => 'RESERVATION-000001',
            'representation' => 'browser-composition-html-styled',
            'format' => 'browser-composition-html-styled/1.0',
            'media_type' => 'text/html; charset=utf-8',
        ])
        ->and($result['checksum'])->toMatch('/^sha256:[a-f0-9]{64}$/')
        ->and($result['etag'])->toBe('"'.$result['checksum'].'"')
        ->and($result['byte_length'])->toBeGreaterThan(0)
        ->and($result['filename'])->toEndWith('.composed.styled.html');
});

it('fails closed for pending evidence and unsupported representations', function (array $options) {
    expect(Artisan::call('gne:x-document:compile', $options))->toBe(1);
})->with([
    'pending brochure' => [[
        '--document' => 'DOCUMENT-BROCHURE',
        '--subject' => 'RESERVATION-000001',
    ]],
    'unsupported direct html' => [[
        '--document' => 'DOCUMENT-INVOICE',
        '--subject' => 'RESERVATION-000001',
        '--representation' => 'browser-html',
    ]],
]);
