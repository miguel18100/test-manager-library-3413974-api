<?php

namespace App\Adapters\Infrastructure;

use App\Application\Ports\CoverStorage;

final class DiskCoverStorage implements CoverStorage
{
    private string $dir;

    public function __construct(?string $dir = null)
    {
        $this->dir = $dir ?: (env('COVERS_DIR') ?: storage_path('app/covers'));
        if (! is_dir($this->dir)) {
            @mkdir($this->dir, 0775, true);
        }
    }

    public function put(string $key, string $bytes): void
    {
        file_put_contents($this->path($key), $bytes);
    }

    public function get(string $key): ?string
    {
        $path = $this->path($key);
        if (! file_exists($path)) {
            return null;
        }

        return file_get_contents($path);
    }

    public function delete(string $key): void
    {
        $path = $this->path($key);
        if (file_exists($path)) {
            @unlink($path);
        }
    }

    private function path(string $key): string
    {
        return $this->dir . DIRECTORY_SEPARATOR . basename($key);
    }
}

