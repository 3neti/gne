<?php

namespace App\Integration\XDocument;

use Opis\JsonSchema\Resolvers\SchemaResolver;
use Opis\JsonSchema\Validator;

final readonly class XDocumentContractSchemas
{
    public const Request = 'https://3neti.dev/contracts/x-document/1.0/compilation-request.schema.json';

    public const ResolvedDocument = 'https://3neti.dev/contracts/x-document/1.0/resolved-document.schema.json';

    public const Result = 'https://3neti.dev/contracts/x-document/1.0/compilation-result.schema.json';

    public function validator(): Validator
    {
        $resolver = new SchemaResolver;
        $contractPath = dirname(__DIR__, 3).'/resources/gne/contracts/x-document/1.0';
        $resolver->registerFile(self::Request, $contractPath.'/compilation-request.schema.json');
        $resolver->registerFile(self::ResolvedDocument, $contractPath.'/resolved-document.schema.json');
        $resolver->registerFile(self::Result, $contractPath.'/compilation-result.schema.json');

        return new Validator(max_errors: 20, stop_at_first_error: false)->setResolver($resolver);
    }
}
