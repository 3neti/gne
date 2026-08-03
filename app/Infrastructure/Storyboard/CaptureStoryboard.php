<?php

namespace App\Infrastructure\Storyboard;

use App\Application\Authorization\GrantSubjectAccess;
use App\Application\Authorization\RevokeSubjectAccess;
use App\Domain\Authorization\SubjectPermission;
use App\Domain\Compilation\CompilationSubject;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

final class CaptureStoryboard
{
    public function __construct(
        private readonly GrantSubjectAccess $grants,
        private readonly RevokeSubjectAccess $revocations,
    ) {}

    /** @param array<string, mixed> $manifest */
    public function handle(Command $command, string $baseUrl, string $root, array &$manifest): string
    {
        $script = base_path('scripts/gne-storyboard-capture.mjs');
        if (! is_file($script) || ! is_dir(base_path('node_modules/playwright'))) {
            return 'unavailable_playwright';
        }
        $password = Str::password(24);
        $email = 'storyboard-'.Str::lower(Str::random(12)).'@example.test';
        $user = User::query()->create(['name' => 'Fictional Storyboard Operator', 'email' => $email, 'password' => $password]);
        $user->forceFill(['is_operator' => true])->save();
        $userId = $user->getKey();
        $subject = new CompilationSubject($manifest['subject_identifier'], 'PropertyReservation');
        $grant = $this->grants->handle($user, $subject, SubjectPermission::View);
        $manifest['authorization'] = [
            'mode' => 'subject_grant',
            'subject_identifier' => $subject->identifier,
            'permission' => SubjectPermission::View->value,
            'grant_created' => $grant->wasRecentlyCreated,
            'protected_frames_authorized' => 0,
            'unexpected_denials' => 0,
            'grant_removed' => false,
        ];
        DB::disconnect();
        $captureStatus = 'capture_failed';
        try {
            $result = Process::timeout(180)->env([
                'GNE_STORYBOARD_MANIFEST' => $root.'/manifest.json', 'GNE_STORYBOARD_BASE_URL' => $baseUrl,
                'GNE_STORYBOARD_EMAIL' => $email, 'GNE_STORYBOARD_PASSWORD' => $password,
            ])->run(['node', $script]);
            if (! $result->successful()) {
                file_put_contents($root.'/reports/browser-capture-error.log', $result->errorOutput().$result->output());
                $command->error(trim($result->errorOutput() ?: $result->output()));

                return 'capture_failed';
            }
            $captureReport = json_decode($result->output(), true, flags: JSON_THROW_ON_ERROR);
            if (! is_array($captureReport) || ! is_array($captureReport['frames'] ?? null) || ! is_array($captureReport['authentication'] ?? null)) {
                throw new \RuntimeException('Browser capture returned an invalid verification report.');
            }
            $results = collect($captureReport['frames'])->keyBy('identifier');
            foreach ($manifest['frames'] as &$frame) {
                $path = $root.'/'.$frame['capture_filename'];
                $verification = $results->get($frame['identifier']);
                if (is_file($path) && is_array($verification) && ($verification['status'] ?? null) === 'captured_and_verified') {
                    $frame['capture_status'] = 'captured_and_verified';
                    $frame['capture_checksum'] = hash_file('sha256', $path);
                    $frame['capture_byte_length'] = filesize($path);
                    $frame['application_status'] = 'captured_and_verified';
                    $frame['application_final_route'] = $verification['final_route'];
                    $frame['application_http_status'] = $verification['http_status'];
                    $frame['application_expected_marker_verified'] = $verification['expected_marker_verified'];
                }
            }
            unset($frame);

            foreach ($manifest['frames'] as $frame) {
                if ($frame['capture_status'] !== 'captured_and_verified') {
                    return 'capture_incomplete';
                }
            }
            $manifest['authentication'] = $captureReport['authentication'];
            $manifest['authorization']['protected_frames_authorized'] = $captureReport['authentication']['protected_frame_count'];
            $manifest['authorization']['unexpected_denials'] = $captureReport['authentication']['unexpected_login_redirects'];
            $captureStatus = 'captured_and_verified';
        } finally {
            $manifest['authorization']['grant_removed'] = $this->revocations->handle($user, $subject->identifier, SubjectPermission::View);
            User::query()->whereKey($userId)->delete();
            $removed = ! User::query()->whereKey($userId)->exists();
            $manifest['authentication']['ephemeral_user_removed'] = $removed;
        }

        return $captureStatus;
    }
}
