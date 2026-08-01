<?php

namespace App\Services\Storage;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class AssetStorage
{
    public function diskName(): string
    {
        $configured = config('authzio.assets.disk');

        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        $default = (string) config('filesystems.default', 'local');

        // Local default disk is private; public assets need the public disk (or Cloud/S3).
        return $default === 'local' ? 'public' : $default;
    }

    public function disk(): Filesystem
    {
        return Storage::disk($this->diskName());
    }

    /**
     * Store an uploaded image and return its publicly reachable URL.
     *
     * @param  non-empty-string  $directory
     */
    public function storePublicImage(UploadedFile $file, string $directory, ?string $previousUrl = null): string
    {
        $this->deleteManagedUrl($previousUrl);

        $extension = strtolower($file->getClientOriginalExtension() ?: ($file->extension() ?: 'bin'));
        $filename = Str::uuid()->toString().'.'.$extension;

        $path = $file->storePubliclyAs(
            trim($directory, '/'),
            $filename,
            ['disk' => $this->diskName()],
        );

        if (! is_string($path) || $path === '') {
            throw new RuntimeException('Failed to store uploaded asset.');
        }

        return $this->disk()->url($path);
    }

    public function deleteManagedUrl(?string $url): void
    {
        if ($url === null || $url === '') {
            return;
        }

        $path = $this->pathFromPublicUrl($url);

        if ($path === null) {
            return;
        }

        $disk = $this->disk();

        if ($disk->exists($path)) {
            $disk->delete($path);
        }
    }

    public function pathFromPublicUrl(string $url): ?string
    {
        $disk = $this->disk();
        $base = rtrim($disk->url(''), '/');

        if ($base !== '' && str_starts_with($url, $base.'/')) {
            return rawurldecode(ltrim(substr($url, strlen($base)), '/'));
        }

        $marker = '/storage/';
        $position = strpos($url, $marker);

        if ($position !== false) {
            return rawurldecode(substr($url, $position + strlen($marker)));
        }

        return null;
    }
}
