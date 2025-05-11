<?php

declare(strict_types=1);

namespace Cat4year\DataMigrator\Services\DataMigrator\Tools\DataSource\Php;

use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Filesystem\Filesystem;
use function Cat4year\DataMigrator\Helpers\var_pretty_export;

final readonly class ArrayToPhp
{
    public function __construct(
        private Filesystem $files,
        private string $stubPath = __DIR__.'/array-migration-data.stub')
    {
    }

    /**
     * @throws FileNotFoundException
     */
    public function prepareStubBeforeSave(array $data): string
    {
        $stub = $this->getDataStub();

        return $this->populateDataStub($stub, var_pretty_export($data, true));
    }

    /**
     * @throws FileNotFoundException
     */
    private function getDataStub(): string
    {
        if (! $this->files->exists($this->stubPath)) {
            throw new FileNotFoundException($this->stubPath.' отсутствует. Он нужен для корректной работы');
        }

        return $this->files->get($this->stubPath);
    }

    private function populateDataStub(string $stub, ?string $data): string
    {
        if ($data !== null) {
            $stub = str_replace(
                ['{{ data }}', '{{data}}'],
                $data,
                $stub
            );
        }

        return $stub;
    }
}
