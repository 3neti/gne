<?php

namespace App\Integration\XDocument;

use LBHurtado\XDocument\Browser\Host\BrowserHostResponse;
use LBHurtado\XDocument\Browser\Host\BrowserRepresentation;

interface BrowserDocumentRepresentationResolver
{
    public function handle(
        string $repositoryRoot,
        string $documentIdentifier,
        string $subjectIdentifier,
        BrowserRepresentation $representation = BrowserRepresentation::CompositionStyledHtml,
    ): BrowserHostResponse;
}
