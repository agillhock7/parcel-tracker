<?php
declare(strict_types=1);

namespace App\Infrastructure;

final class AppVersion
{
    /**
     * @param array{js?:list<string>} $viteAssets
     */
    public static function resolve(string $appRoot, array $viteAssets = []): string
    {
        $envVersion = trim((string)getenv('APP_VERSION'));
        if ($envVersion !== '') {
            return $envVersion;
        }

        $deployVersionFile = rtrim($appRoot, '/') . '/storage/version.txt';
        if (is_file($deployVersionFile)) {
            $fileVersion = trim((string)file_get_contents($deployVersionFile));
            if ($fileVersion !== '') {
                return $fileVersion;
            }
        }

        $firstJs = (string)($viteAssets['js'][0] ?? '');
        if ($firstJs !== '' && preg_match('/-([A-Za-z0-9_-]{6,})\\.js$/', $firstJs, $m) === 1) {
            return 'build-' . substr((string)$m[1], 0, 8);
        }

        $fallbackAsset = rtrim($appRoot, '/') . '/public/assets/app.css';
        if (is_file($fallbackAsset)) {
            $mtime = (int)filemtime($fallbackAsset);
            return 'local-' . gmdate('YmdHi', $mtime > 0 ? $mtime : time());
        }

        return 'dev';
    }
}
