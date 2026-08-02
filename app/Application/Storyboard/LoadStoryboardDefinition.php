<?php

namespace App\Application\Storyboard;

use App\Domain\Storyboard\StoryboardDefinition;
use App\Domain\Storyboard\StoryboardFrame;
use Illuminate\Filesystem\Filesystem;
use InvalidArgumentException;
use Symfony\Component\Yaml\Yaml;

final readonly class LoadStoryboardDefinition
{
    public function __construct(private Filesystem $files) {}

    public function handle(string $repositoryRoot, string $identifier): StoryboardDefinition
    {
        if (! preg_match('/^[a-z0-9-]+$/', $identifier)) {
            throw new InvalidArgumentException('Storyboard identifiers may contain lowercase letters, numbers, and hyphens only.');
        }
        $path = $repositoryRoot.'/docs/mvp/storyboards/'.$identifier.'.yaml';
        if (! $this->files->exists($path)) {
            throw new InvalidArgumentException("Storyboard {$identifier} was not found.");
        }
        $source = Yaml::parseFile($path);
        if (! is_array($source) || ! is_array($source['frames'] ?? null) || ! is_array($source['personas'] ?? null)) {
            throw new InvalidArgumentException('Storyboard source is malformed.');
        }
        $frames = array_map(fn (array $frame): StoryboardFrame => new StoryboardFrame(
            (int) $frame['sequence'], (string) $frame['identifier'], (string) $frame['act'],
            (string) $frame['title'], (string) $frame['persona'], (string) $frame['display_route'],
            (string) $frame['stage'], (string) $frame['expected'], (string) $frame['action'],
            (string) $frame['capture']['type'], (string) $frame['capture']['route'],
            (bool) $frame['capture']['requires_authentication'], (string) $frame['capture']['expected_final_route'],
            (bool) $frame['capture']['production_surface'],
        ), array_values($source['frames']));

        return new StoryboardDefinition(
            (string) $source['format'], (string) $source['identifier'], (string) $source['title'],
            (string) $source['subject'], array_values($source['personas']), $frames,
        );
    }
}
