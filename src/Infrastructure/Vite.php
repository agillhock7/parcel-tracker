<?php
declare(strict_types=1);

namespace App\Infrastructure;

final class Vite
{
    /**
     * @return array{js:list<string>,css:list<string>}
     */
    public static function assets(string $appRoot, string $entry): array
    {
        $manifestPath = $appRoot . '/public/build/.vite/manifest.json';
        if (!is_file($manifestPath)) {
            return ['js' => [], 'css' => []];
        }

        $raw = file_get_contents($manifestPath);
        if (!is_string($raw) || $raw === '') {
            return ['js' => [], 'css' => []];
        }

        $manifest = json_decode($raw, true);
        if (!is_array($manifest)) {
            return ['js' => [], 'css' => []];
        }

        $item = $manifest[$entry] ?? null;
        if (!is_array($item)) {
            return ['js' => [], 'css' => []];
        }

        $js = [];
        $css = [];

        if (is_string($item['file'] ?? null)) {
            $js[] = '/build/' . ltrim((string)$item['file'], '/');
        }
        foreach (($item['css'] ?? []) as $f) {
            if (is_string($f)) {
                $css[] = '/build/' . ltrim($f, '/');
            }
        }

        return ['js' => $js, 'css' => $css];
    }
}

