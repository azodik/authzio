<?php

namespace App\Support;

use Illuminate\Support\Facades\File;

final class BuildInfo
{
    /**
     * @return array{name: string, version: string, build: string, commit: string, display: string}
     */
    public static function toArray(): array
    {
        $name = (string) config('app.name', 'Authzio');
        $version = self::version();
        $build = self::build();
        $commit = self::commit();

        return [
            'name' => $name,
            'version' => $version,
            'build' => $build,
            'commit' => $commit,
            'display' => self::display($name, $version, $build),
        ];
    }

    public static function version(): string
    {
        $fromConfig = self::nonEmptyString(config('authzio.release.version'));
        if ($fromConfig !== null) {
            return $fromConfig;
        }

        $fromFile = self::fromBuildInfoFile('version');
        if ($fromFile !== null) {
            return $fromFile;
        }

        $versionPath = base_path('VERSION');
        if (File::isFile($versionPath)) {
            $version = trim((string) File::get($versionPath));
            if ($version !== '') {
                return $version;
            }
        }

        return '0.0.0';
    }

    public static function build(): string
    {
        $fromConfig = self::nonEmptyString(config('authzio.release.build'));
        if ($fromConfig !== null) {
            return $fromConfig;
        }

        $fromFile = self::fromBuildInfoFile('build');
        if ($fromFile !== null) {
            return $fromFile;
        }

        return 'dev';
    }

    public static function commit(): string
    {
        $fromConfig = self::nonEmptyString(config('authzio.release.commit'));
        if ($fromConfig !== null) {
            return $fromConfig;
        }

        $fromFile = self::fromBuildInfoFile('commit');
        if ($fromFile !== null) {
            return $fromFile;
        }

        return 'unknown';
    }

    public static function display(string $name, string $version, string $build): string
    {
        if ($build === '' || strcasecmp($build, 'dev') === 0) {
            return sprintf('%s %s', $name, $version);
        }

        return sprintf('%s %s (Build %s)', $name, $version, $build);
    }

    private static function nonEmptyString(mixed $value): ?string
    {
        if (! is_string($value) && ! is_int($value)) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed !== '' ? $trimmed : null;
    }

    private static function fromBuildInfoFile(string $key): ?string
    {
        $path = base_path('build-info.json');
        if (! File::isFile($path)) {
            return null;
        }

        try {
            /** @var array<string, mixed> $data */
            $data = json_decode(File::get($path), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        return self::nonEmptyString($data[$key] ?? null);
    }
}
