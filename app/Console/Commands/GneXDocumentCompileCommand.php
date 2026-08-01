<?php

namespace App\Console\Commands;

use App\Domain\Compilation\DocumentResolutionException;
use App\Integration\XDocument\ResolveXDocumentBrowserRepresentation;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use LBHurtado\XDocument\Browser\Host\BrowserRepresentation;

#[Signature('gne:x-document:compile {--repository= : Repository root} {--document= : Document definition identifier} {--subject= : Compilation subject identifier} {--representation=browser-composition-html-styled : Allowed browser representation} {--json : Emit structured output metadata}')]
#[Description('Resolve repository evidence through the real x-document browser runtime')]
class GneXDocumentCompileCommand extends Command
{
    public function handle(ResolveXDocumentBrowserRepresentation $resolver): int
    {
        $repositoryRoot = is_string($this->option('repository')) ? $this->option('repository') : base_path();
        $documentIdentifier = $this->option('document');
        $subjectIdentifier = $this->option('subject');
        $representationIdentifier = $this->option('representation');
        if (! is_string($documentIdentifier) || ! is_string($subjectIdentifier) || ! is_string($representationIdentifier)) {
            $this->components->error('--document and --subject are required.');

            return self::FAILURE;
        }

        $representation = BrowserRepresentation::tryFrom($representationIdentifier);
        if (! in_array($representation, [
            BrowserRepresentation::Composition,
            BrowserRepresentation::CompositionHtml,
            BrowserRepresentation::CompositionStyledHtml,
        ], true)) {
            $this->components->error("Browser representation {$representationIdentifier} is not allowed by the GNE MVP runtime.");

            return self::FAILURE;
        }

        try {
            $response = $resolver->handle(
                $repositoryRoot,
                $documentIdentifier,
                $subjectIdentifier,
                $representation,
            );
        } catch (DocumentResolutionException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $result = [
            'document' => $documentIdentifier,
            'subject' => $subjectIdentifier,
            'representation' => $response->descriptor->representation->value,
            'format' => $response->descriptor->format,
            'media_type' => $response->output->mediaType,
            'checksum' => $response->output->checksum,
            'byte_length' => $response->output->byteLength,
            'filename' => $response->output->filename,
            'etag' => $response->etag,
        ];

        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

            return self::SUCCESS;
        }

        $this->components->info('Compiled repository evidence through x-document.');
        foreach ($result as $label => $value) {
            $this->line(Str::headline($label).': '.$value);
        }

        return self::SUCCESS;
    }
}
