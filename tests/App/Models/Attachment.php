<?php

declare(strict_types=1);

namespace Cat4year\DataMigratorTests\App\Models;

use Exception;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Override;
use Symfony\Component\Mime\MimeTypes;

/**
 * @mixin IdeHelperAttachment
 */
final class Attachment extends Model
{
    protected $fillable = [
        'name',
        'original_name',
        'mime',
        'extension',
        'size',
        'path',
        'user_id',
        'description',
        'alt',
        'sort',
        'hash',
        'disk',
        'group',
    ];

    /** @var array */
    protected $appends = [
        'url',
        'relativeUrl',
    ];

    /**
     * Return the address by which you can access the file.
     */
    public function url(?string $default = null): ?string
    {
        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk($this->getAttribute('disk'));
        $path = $this->physicalPath();

        return $path !== null && $disk->exists($path)
            ? $disk->url($path)
            : $default;
    }

    public function getUrlAttribute(): ?string
    {
        return $this->url();
    }

    protected function relativeUrl(): Attribute
    {
        return Attribute::make(get: function () {
            $url = $this->url();
            if (filter_var($url, FILTER_VALIDATE_URL) === false) {
                return null;
            }

            return parse_url((string) $url, PHP_URL_PATH) ?: null;
        });
    }

    protected function title(): Attribute
    {
        return Attribute::make(get: function () {
            if ($this->original_name !== 'blob') {
                return $this->original_name;
            }

            return $this->name . '.' . $this->extension;
        });
    }

    public function physicalPath(): ?string
    {
        if ($this->path === null || $this->name === null) {
            return null;
        }

        return $this->path . $this->name . '.' . $this->extension;
    }

    /**
     * @return bool|null
     *
     * @throws Exception
     */
    #[Override]
    public function delete()
    {
        if ($this->exists) {
            if (self::query()->where('hash', $this->hash)->where('disk', $this->disk)->limit(2)->count() <= 1) {
                // Physical removal a file.
                Storage::disk($this->disk)->delete($this->physicalPath());
            }

            $this->relationships()->delete();
        }

        return parent::delete();
    }

    /**
     * Get MIME type for file.
     */
    public function getMimeType(): string
    {
        $mimeTypes = new MimeTypes;

        $type = $mimeTypes->getMimeType($this->getAttribute('extension'));

        return $type ?? 'unknown';
    }

    public function isMime(string $type): bool
    {
        return Str::of($this->mime)->is($type);
    }

    public function isPhysicalExists(): bool
    {
        return Storage::disk($this->disk)->exists($this->physicalPath());
    }

    public function download(array $headers = [])
    {
        return Storage::disk($this->disk)->download($this->physicalPath(), $this->original_name, $headers);
    }

    protected function casts(): array
    {
        return [
            'sort' => 'integer',
        ];
    }
}
