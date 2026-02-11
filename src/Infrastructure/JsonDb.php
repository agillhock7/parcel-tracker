<?php
declare(strict_types=1);

namespace App\Infrastructure;

final class JsonDb
{
    public function __construct(private string $path)
    {
    }

    /** @return array<string, mixed> */
    public function read(): array
    {
        $dir = dirname($this->path);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $fh = fopen($this->path, 'c+');
        if ($fh === false) {
            return ['shipments' => [], 'events' => []];
        }

        try {
            flock($fh, LOCK_SH);
            $raw = stream_get_contents($fh);
            if ($raw === false || trim($raw) === '') {
                return ['shipments' => [], 'events' => []];
            }
            $data = json_decode($raw, true);
            if (!is_array($data)) {
                return ['shipments' => [], 'events' => []];
            }
            $data['shipments'] ??= [];
            $data['events'] ??= [];
            return $data;
        } finally {
            flock($fh, LOCK_UN);
            fclose($fh);
        }
    }

    /** @param array<string, mixed> $data */
    public function write(array $data): void
    {
        $dir = dirname($this->path);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $tmp = $this->path . '.tmp';
        $json = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        if ($json === false) {
            $json = '{}';
        }

        $fh = fopen($tmp, 'wb');
        if ($fh === false) {
            return;
        }

        fwrite($fh, $json . "\n");
        fclose($fh);
        rename($tmp, $this->path);
    }
}

