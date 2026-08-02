<?php

namespace App\Domain\Storyboard;

use InvalidArgumentException;

final readonly class StoryboardDefinition
{
    /**
     * @param  list<string>  $personas
     * @param  list<StoryboardFrame>  $frames
     */
    public function __construct(
        public string $format,
        public string $identifier,
        public string $title,
        public string $subject,
        public array $personas,
        public array $frames,
    ) {
        $identifiers = array_map(fn (StoryboardFrame $frame): string => $frame->identifier, $frames);
        $sequences = array_map(fn (StoryboardFrame $frame): int => $frame->sequence, $frames);
        if ($format !== 'gne-storyboard/1.0' || $identifier === '' || $title === '' || $subject === '') {
            throw new InvalidArgumentException('Storyboard identity is incomplete or unsupported.');
        }
        if (count($identifiers) !== count(array_unique($identifiers)) || count($sequences) !== count(array_unique($sequences))) {
            throw new InvalidArgumentException('Storyboard frame identifiers and sequences must be unique.');
        }
        if ($sequences !== range(1, count($frames))) {
            throw new InvalidArgumentException('Storyboard frame sequences must be contiguous and ordered.');
        }
        foreach ($frames as $frame) {
            if (! in_array($frame->persona, $personas, true) || ! str_starts_with($frame->route, '/')) {
                throw new InvalidArgumentException("Storyboard frame {$frame->identifier} has an invalid persona or route.");
            }
        }
    }
}
