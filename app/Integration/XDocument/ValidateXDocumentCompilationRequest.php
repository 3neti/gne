<?php

namespace App\Integration\XDocument;

use Opis\JsonSchema\Errors\ErrorFormatter;

final readonly class ValidateXDocumentCompilationRequest
{
    public function __construct(private XDocumentContractSchemas $schemas = new XDocumentContractSchemas) {}

    public function handle(XDocumentCompilationRequest $request): void
    {
        $payload = json_decode(json_encode($request->toArray(), JSON_THROW_ON_ERROR), false, flags: JSON_THROW_ON_ERROR);
        $result = $this->schemas->validator()->validate($payload, XDocumentContractSchemas::Request);
        if (! $result->isValid()) {
            $errors = (new ErrorFormatter)->format($result->error());
            throw new InvalidXDocumentCompilationRequest('Generated x-document request violates contract 1.0: '.json_encode($errors, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        }
    }
}
