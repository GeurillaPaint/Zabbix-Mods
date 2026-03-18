<?php
declare(strict_types=1);

namespace Modules\NetworkMap\Lib;

use RuntimeException;

final class Cache {
    private string $base_dir;

    public function __construct(?string $base_dir = null) {
        $this->base_dir = $base_dir !== null && trim($base_dir) !== ''
            ? rtrim($base_dir, '/')
            : (string) Config::get('cache_dir');
    }

    public function read(string $key, ?int $ttl_seconds = null): ?array {
        $path = $this->pathFor($key);

        if (!is_file($path)) {
            return null;
        }

        if ($ttl_seconds !== null && $ttl_seconds >= 0) {
            $age = time() - (int) @filemtime($path);

            if ($age > $ttl_seconds) {
                return null;
            }
        }

        return $this->decodeFile($path);
    }

    public function readAny(string $key): ?array {
        $path = $this->pathFor($key);

        if (!is_file($path)) {
            return null;
        }

        return $this->decodeFile($path);
    }

    public function write(string $key, array $payload): void {
        $this->ensureBaseDir();

        $path = $this->pathFor($key);
        $tmp_path = $path . '.tmp.' . bin2hex(random_bytes(6));

        $json = json_encode(
            $payload,
            JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );

        if ($json === false) {
            throw new RuntimeException('Failed to encode cache payload to JSON.');
        }

        if (@file_put_contents($tmp_path, $json, LOCK_EX) === false) {
            throw new RuntimeException(sprintf('Failed to write cache file: %s', $tmp_path));
        }

        if (!@rename($tmp_path, $path)) {
            @unlink($tmp_path);
            throw new RuntimeException(sprintf('Failed to move cache file into place: %s', $path));
        }
    }

    public function age(string $key): ?int {
        $path = $this->pathFor($key);

        if (!is_file($path)) {
            return null;
        }

        return max(0, time() - (int) @filemtime($path));
    }

    private function pathFor(string $key): string {
        $safe = preg_replace('/[^A-Za-z0-9._-]/', '_', $key) ?: 'cache';

        return $this->base_dir . '/' . $safe . '.json';
    }

    private function ensureBaseDir(): void {
        if (is_dir($this->base_dir)) {
            return;
        }

        if (!@mkdir($concurrentDirectory = $this->base_dir, 0770, true) && !is_dir($concurrentDirectory)) {
            throw new RuntimeException(sprintf('Failed to create cache directory: %s', $this->base_dir));
        }
    }

    private function decodeFile(string $path): ?array {
        $raw = @file_get_contents($path);

        if ($raw === false || trim($raw) === '') {
            return null;
        }

        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        }
        catch (\JsonException) {
            return null;
        }

        return is_array($decoded) ? $decoded : null;
    }
}
