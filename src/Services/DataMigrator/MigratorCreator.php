<?php

declare(strict_types=1);

namespace Cat4year\DataMigrator\Services\DataMigrator;

use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Database\Migrations\MigrationCreator;

final class MigratorCreator extends MigrationCreator
{
    /**
     * @throws FileNotFoundException
     */
    public function createData(string $name, string $path, ?string $data = null): string
    {
        $this->ensureMigrationDoesntAlreadyExist($name, $path);

        $stub = $this->getDataStub();

        $path = $this->getPath($name, $path);

        $this->files->ensureDirectoryExists(dirname($path));

        $this->files->put(
            $path,
            $this->populateDataStub($stub, $data)
        );

        $this->firePostCreateHooks($data, $path);

        return $path;
    }

    /**
     * @throws FileNotFoundException
     */
    protected function getDataStub(): string
    {
        if (! $this->files->exists($customPath = $this->customStubPath.'/migration-data.stub')) {
            throw new FileNotFoundException('migration-data.stub отсутствует. Он нужен для корректной работы');
        }
        $stub = $customPath;

        return $this->files->get($stub);
    }

    protected function populateDataStub(string $stub, ?string $data): string
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
