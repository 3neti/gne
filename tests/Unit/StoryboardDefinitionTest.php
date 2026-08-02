<?php

use App\Domain\Storyboard\StoryboardDefinition;
use App\Domain\Storyboard\StoryboardFrame;
use Symfony\Component\Yaml\Yaml;

it('loads the ordered repository-authored Property Reservation storyboard', function () {
    $source = Yaml::parseFile(dirname(__DIR__, 2).'/docs/mvp/storyboards/property-reservation-mvp.yaml');
    $frames = array_map(fn (array $frame): StoryboardFrame => new StoryboardFrame(
        (int) $frame['sequence'], $frame['identifier'], $frame['act'], $frame['title'], $frame['persona'],
        $frame['display_route'], $frame['stage'], (string) $frame['expected'], $frame['action'],
        $frame['capture']['type'], $frame['capture']['route'], $frame['capture']['requires_authentication'],
        $frame['capture']['expected_final_route'], $frame['capture']['production_surface'],
    ), $source['frames']);
    $storyboard = new StoryboardDefinition($source['format'], $source['identifier'], $source['title'], $source['subject'], $source['personas'], $frames);

    expect($storyboard->format)->toBe('gne-storyboard/1.0')
        ->and($storyboard->frames)->toHaveCount(25)
        ->and(array_column(array_map(fn ($frame): array => $frame->toArray(), $storyboard->frames), 'sequence'))->toBe(range(1, 25))
        ->and(collect($storyboard->frames)->pluck('identifier')->unique())->toHaveCount(25)
        ->and(collect($storyboard->frames)->every(fn ($frame): bool => str_starts_with($frame->captureRoute, '/')))->toBeTrue()
        ->and(collect($storyboard->frames)->where('productionSurface', true))->toHaveCount(22)
        ->and(collect($storyboard->frames)->where('captureType', 'explanation'))->toHaveCount(3)
        ->and(collect($storyboard->frames)->where('captureType', 'explanation')->every(fn ($frame): bool => ! $frame->productionSurface))->toBeTrue();
});

it('rejects duplicate frame identity and sequence', function () {
    $frame = new StoryboardFrame(
        1, 'duplicate', 'act', 'Title', 'operator', '/dashboard', 'application', 'Visible', 'Observe',
        'application', '/dashboard', true, '/dashboard', true,
    );

    expect(fn () => new StoryboardDefinition('gne-storyboard/1.0', 'invalid', 'Invalid', 'SUBJECT', ['operator'], [$frame, $frame]))
        ->toThrow(InvalidArgumentException::class);
});
