<?php

namespace App\Application\Storyboard;

use App\Domain\Storyboard\StoryboardDefinition;
use Illuminate\Filesystem\Filesystem;

final readonly class BuildStoryboard
{
    public function __construct(
        private PropertyReservationStoryboardStateProvider $states,
        private Filesystem $files,
    ) {}

    /** @return array<string, mixed> */
    public function handle(string $repositoryRoot, StoryboardDefinition $definition, string $baseUrl): array
    {
        $output = $repositoryRoot.'/.gne/storyboards/'.$definition->identifier;
        $this->files->ensureDirectoryExists($output.'/frames');
        $this->files->ensureDirectoryExists($output.'/pdf');
        $this->files->ensureDirectoryExists($output.'/movie');
        $this->files->ensureDirectoryExists($output.'/reports');
        $this->files->deleteDirectory($output.'/states');
        $stateRoot = storage_path('framework/gne-storyboards/'.$definition->identifier.'/states');
        $this->files->deleteDirectory($stateRoot);
        $this->files->ensureDirectoryExists($stateRoot);
        $this->files->deleteDirectory($output.'/html');
        $snapshots = [];
        $frames = [];
        foreach ($definition->frames as $frame) {
            $snapshot = $snapshots[$frame->stage] ??= $this->states->prepareSnapshot(
                $repositoryRoot,
                $frame->stage,
                $stateRoot.'/'.$frame->stage,
            );
            $filename = sprintf('%03d-%s.png', $frame->sequence, $frame->identifier);
            $frames[] = [
                ...$frame->toArray(),
                'method' => 'GET',
                'subject_identifier' => $definition->subject,
                'capture_filename' => 'frames/'.$filename,
                'application_route' => $frame->captureRoute,
                'application_status' => 'planned',
                'application_final_route' => null,
                'application_http_status' => null,
                'application_expected_marker_verified' => false,
                'authenticated_user' => $frame->requiresAuthentication ? 'Fictional Storyboard Operator' : null,
                'session_identity' => $frame->requiresAuthentication ? 'ephemeral-demo-operator-session' : null,
                'capture_status' => 'planned',
                'capture_checksum' => null,
                'capture_byte_length' => null,
                'snapshot' => $snapshot,
                'duration_seconds' => 4,
                'transition' => 'cut',
            ];
        }
        $manifest = [
            'format' => $definition->format,
            'identifier' => $definition->identifier,
            'title' => $definition->title,
            'base_url' => rtrim($baseUrl, '/'),
            'subject_identifier' => $definition->subject,
            'personas' => $definition->personas,
            'authentication' => [
                'mode' => 'interactive_login',
                'login_route' => '/login',
                'login_submitted' => false,
                'authenticated_redirect' => null,
                'session_preserved' => false,
                'protected_frame_count' => 0,
                'unexpected_login_redirects' => 0,
                'ephemeral_user_removed' => null,
            ],
            'frames' => $frames,
            'outputs' => [
                'html' => ['format' => 'gne-storyboard-html/1.0', 'status' => 'planned'],
                'pdf' => ['path' => 'pdf/'.$definition->identifier.'.pdf', 'status' => 'planned'],
                'movie' => ['path' => 'movie/'.$definition->identifier.'.mp4', 'status' => 'build_ready'],
            ],
            'privacy' => ['fictional_data_only' => true, 'credentials_recorded' => false],
        ];
        $this->writeJson($output.'/manifest.json', $manifest);
        $this->writeJson($output.'/movie/manifest.json', [
            'format' => 'gne-storyboard-movie/1.0',
            'storyboard' => $definition->identifier,
            'frames' => array_map(fn (array $frame): array => [
                'file' => $frame['capture_filename'],
                'duration_seconds' => $frame['duration_seconds'],
                'transition' => $frame['transition'],
            ], $frames),
            'encoder' => 'ffmpeg',
            'status' => 'build_ready',
        ]);
        $concat = ['ffconcat version 1.0'];
        foreach ($definition->frames as $frame) {
            $filename = sprintf('%03d-%s.png', $frame->sequence, $frame->identifier);
            $concat[] = "file '../frames/{$filename}'";
            $concat[] = 'duration 4';
        }
        $lastFrame = $definition->frames[count($definition->frames) - 1];
        $concat[] = "file '../frames/".sprintf('%03d-%s.png', $lastFrame->sequence, $lastFrame->identifier)."'";
        $this->files->put($output.'/movie/frames.ffconcat', implode("\n", $concat)."\n");
        $this->files->put($output.'/movie/build.sh', "#!/usr/bin/env sh\nset -eu\nffmpeg -y -safe 0 -f concat -i frames.ffconcat -vf 'fps=30,format=yuv420p' -c:v libx264 {$definition->identifier}.mp4\n");
        $this->files->put($output.'/narration.md', $this->narration($definition, $frames));

        return $manifest;
    }

    /** @param array<string, mixed> $manifest */
    public function finalizeMovie(string $repositoryRoot, array $manifest): void
    {
        $output = $repositoryRoot.'/.gne/storyboards/'.$manifest['identifier'];
        $frames = array_map(fn (array $frame): array => [
            'file' => $frame['capture_filename'],
            'sha256' => $frame['capture_checksum'],
            'byte_length' => $frame['capture_byte_length'],
            'duration_seconds' => $frame['duration_seconds'],
            'transition' => $frame['transition'],
        ], $manifest['frames']);
        $this->writeJson($output.'/movie/manifest.json', [
            'format' => 'gne-storyboard-movie/1.0',
            'storyboard' => $manifest['identifier'],
            'source_manifest_fingerprint' => $manifest['finalized_frame_fingerprint'],
            'frames' => $frames,
            'encoder' => 'ffmpeg',
            'status' => 'build_ready',
        ]);
    }

    /** @param array<string, mixed> $manifest */
    public function persist(string $repositoryRoot, array $manifest): void
    {
        $this->writeJson($repositoryRoot.'/.gne/storyboards/'.$manifest['identifier'].'/manifest.json', $manifest);
    }

    /** @param array<string, mixed> $value */
    private function writeJson(string $path, array $value): void
    {
        $this->files->put($path, json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n");
    }

    /** @param list<array<string, mixed>> $frames */
    private function narration(StoryboardDefinition $definition, array $frames): string
    {
        $lines = ["# {$definition->title} Narration", '', 'This reconstructed demonstration uses fictional data and immutable isolated repository evidence.', ''];
        foreach ($frames as $frame) {
            $lines[] = "## {$frame['sequence']}. {$frame['title']}";
            $lines[] = '';
            $lines[] = $frame['action'].' '.$frame['expected'].'.';
            $lines[] = '';
            $lines[] = "Duration: {$frame['duration_seconds']} seconds. Transition: {$frame['transition']}.";
            $lines[] = '';
        }
        $lines[] = 'GNE resolves repository truth, x-document expresses resolved meaning, and x-document-laravel delivers the authenticated HTTP representation. No storyboard frame authors business truth.';

        return implode("\n", $lines)."\n";
    }
}
