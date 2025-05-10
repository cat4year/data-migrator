<?php

declare(strict_types=1);

namespace Cat4year\DataMigrator\Services\DataMigrator\Export;

use Cat4year\DataMigrator\Services\DataMigrator\Tools\DataSource\MigrationDataSourceFormat;
use Cat4year\DataMigrator\Services\DataMigrator\Tools\DataSource\Php\PhpMigrationDataSourceFormat;
use Cat4year\DataMigrator\Services\DataMigrator\Tools\DataSource\Xml\XmlMigrationDataSourceFormat;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use TypeError;

final class ExportConfigurator
{
    private bool $withRelations = true;

    private int $maxRelationDepth = 1;

    private array $supportedRelations = [
        HasOne::class,
        HasMany::class,
        BelongsTo::class,
        BelongsToMany::class,

        HasOneThrough::class,
        HasManyThrough::class,

        MorphTo::class,
        MorphOne::class,
        MorphMany::class,
        MorphToMany::class,
    ];

    private string $fileName = 'export';

    private ?string $directoryPath = null;

    private array $ids = [];

    public function __construct(
        private readonly MigrationDataSourceFormat $sourceFormat,
        private Filesystem $disk
    ) {
    }

    public static function create(): self
    {
        $configurator = app(self::class);

        if (! $configurator instanceof self) {
            throw new TypeError('Неверный тип конфигуратора');
        }

        return $configurator;
    }

    public function withRelations(): bool
    {
        return $this->withRelations;
    }

    public function setWithRelations(bool $withRelations): self
    {
        $this->withRelations = $withRelations;

        return $this;
    }

    public function getSourceFormat(): MigrationDataSourceFormat
    {
        return $this->sourceFormat;
    }

    public function makeSourcePath(string $fileName = '', string $format = ''): string
    {
        if (empty($format)) {
            $format = match ($this->sourceFormat::class) {
                PhpMigrationDataSourceFormat::class => 'php',
                XmlMigrationDataSourceFormat::class => 'xml',
            };
        }

        return ($fileName ?: $this->fileName).'.'.$format;
    }

    public function makeSource(string $fileName = '', string $format = ''): string
    {
        $pathWithFormat = $this->makeSourcePath($fileName, $format);

        if ($this->directoryPath !== null) {
            $fullPath = $this->directoryPath.'/'.$pathWithFormat;

            return str_replace('//', '/', $fullPath);
        }

        return $this->getDisk()->path($pathWithFormat);
    }

    private function getDisk(): Filesystem
    {
        return $this->disk;
    }

    public function setDisk(Filesystem $disk): self
    {
        $this->disk = $disk;

        return $this;
    }

    public function getFileName(): string
    {
        return $this->fileName;
    }

    public function setFileName(string $fileName): self
    {
        $this->fileName = $fileName;

        return $this;
    }

    public function getMaxRelationDepth(): int
    {
        return $this->maxRelationDepth;
    }

    public function setMaxRelationDepth(int $maxRelationDepth): self
    {
        $this->maxRelationDepth = $maxRelationDepth;

        return $this;
    }

    public function getIds(): array
    {
        return $this->ids;
    }

    /**
     * @param list<int|string> $ids
     */
    public function setIds(array $ids): self
    {
        $this->ids = $ids;

        return $this;
    }

    public function getSupportedRelations(): array
    {
        return $this->supportedRelations;
    }

    public function setSupportedRelations(array $supportedRelations): self
    {
        $this->supportedRelations = $supportedRelations;

        return $this;
    }

    public function setDirectoryPath(?string $directoryPath): self
    {
        $this->directoryPath = $directoryPath;

        return $this;
    }
}
