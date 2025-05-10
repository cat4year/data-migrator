<?php

declare(strict_types=1);

namespace Cat4year\DataMigrator\Services\DataMigrator\Tools\DataSource\Php;

use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Filesystem\Filesystem;

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

        return $this->populateDataStub($stub, $this->varExportPretty($data, true));
    }

    private function varExportPretty(array $expression, bool $return = false): ?string
    {
        $export = var_export($expression, true);
        $export = preg_replace('/^( *)(.*)/m', '$1$1$2', $export);
        $array = preg_split("/\r\n|\n|\r/", $export);
        $array = preg_replace(["/\s*array\s\($/", "/\)(,)?$/", "/\s=>\s$/"], [null, ']$1', ' => ['], $array);
        $export = implode(PHP_EOL, array_filter(['['] + $array));
        if ($return) {
            return $export;
        }

        echo $export;

        return null;
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
