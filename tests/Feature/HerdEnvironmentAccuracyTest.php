<?php

use Illuminate\Support\Facades\Artisan;

it('keeps the browser route host independent and generates it by name', function () {
    $parameters = [
        'subject' => 'RESERVATION-000001',
        'document' => 'DOCUMENT-INVOICE',
    ];

    expect(route('documents.browser', $parameters, absolute: false))
        ->toBe('/subjects/RESERVATION-000001/documents/DOCUMENT-INVOICE/browser')
        ->and(file_get_contents(resource_path('js/pages/DocumentSetWorkbench.vue')))
        ->toContain('showBrowserDocument.url')
        ->and(file_get_contents(app_path('Console/Commands/GneMvpSmokeCommand.php')))
        ->toContain("route('documents.browser'");
});

it('derives smoke URLs from the configured application URL for HTTP and HTTPS', function (string $applicationUrl) {
    config(['app.url' => $applicationUrl]);

    expect(Artisan::call('gne:mvp:smoke', ['--json' => true]))->toBe(0);

    $result = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

    expect($result)
        ->toMatchArray([
            'passed' => true,
            'application_url' => $applicationUrl,
            'browser_route_path' => '/subjects/RESERVATION-000001/documents/DOCUMENT-INVOICE/browser',
            'browser_route_url' => $applicationUrl.'/subjects/RESERVATION-000001/documents/DOCUMENT-INVOICE/browser',
        ]);
})->with([
    'unsecured Herd site' => 'http://gne.test',
    'secured Herd site' => 'https://gne.test',
    'non-Herd deployment' => 'https://preview.example.net',
]);

it('keeps production code free of development-server and Herd runtime coupling', function () {
    $productionFiles = collect([app_path(), resource_path('js'), base_path('routes')])
        ->flatMap(function (string $directory): array {
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));

            return collect(iterator_to_array($iterator))
                ->filter(fn (SplFileInfo $file): bool => $file->isFile())
                ->keys()
                ->all();
        });
    $productionSource = $productionFiles
        ->map(fn (string $path): string => file_get_contents($path) ?: '')
        ->implode("\n");
    $developmentServer = 'localhost'.':8000';

    expect($productionSource)
        ->not->toContain($developmentServer)
        ->not->toContain('Laravel\\Herd')
        ->not->toContain('Herd\\');
});

it('documents the Herd default without committing a developer environment', function () {
    expect(file_get_contents(base_path('.env.example')))
        ->toContain('APP_URL=http://gne.test')
        ->and(file_get_contents(base_path('.gitignore')))
        ->toContain("\n.env\n");
});
