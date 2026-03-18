<?php
declare(strict_types=1);

namespace Modules\NetworkMap\Lib;

final class Helpers {
    public static function isPublicIp(string $ip): bool {
        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) !== false;
    }

    public static function safeString(mixed $value): string {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_scalar($value)) {
            return trim((string) $value);
        }

        return '';
    }

    public static function normalizeConnectionList(mixed $value): array {
        if ($value === null || $value === '') {
            return [];
        }

        if (is_array($value)) {
            return self::isAssoc($value) ? [$value] : $value;
        }

        return [];
    }

    public static function decodeJsonObject(string $value): ?array {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        try {
            $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        }
        catch (\JsonException) {
            return null;
        }

        return is_array($decoded) ? $decoded : null;
    }

    public static function firstNonEmpty(mixed ...$values): string {
        foreach ($values as $value) {
            $string = self::safeString($value);

            if ($string !== '') {
                return $string;
            }
        }

        return '';
    }

    public static function isAssoc(array $value): bool {
        if ($value === []) {
            return false;
        }

        return array_keys($value) !== range(0, count($value) - 1);
    }

    public static function iso8601(int $timestamp): string {
        return gmdate('c', $timestamp);
    }
}
