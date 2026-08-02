<?php

namespace App\Infrastructure\Storyboard;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

final class CaptureStoryboard
{
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
        $userId = $user->getKey();
        DB::disconnect();
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
            foreach ($manifest['frames'] as &$frame) {
                $path = $root.'/'.$frame['capture_filename'];
                if (is_file($path)) {
                    $frame['capture_status'] = 'captured';
                    $frame['capture_checksum'] = hash_file('sha256', $path);
                }
            }
            unset($frame);

            foreach ($manifest['frames'] as $frame) {
                if ($frame['capture_status'] !== 'captured') {
                    return 'capture_incomplete';
                }
            }

            return 'captured';
        } finally {
            User::query()->whereKey($userId)->delete();
        }
    }
}
