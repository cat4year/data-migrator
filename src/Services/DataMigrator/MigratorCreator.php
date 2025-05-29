<?php

declare(strict_types=1);

namespace Cat4year\DataMigrator\Services\DataMigrator;

use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Database\Migrations\MigrationCreator;
use Illuminate\Support\Facades\Artisan;

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

        Artisan::call('app:pint', ['--file' => $path]);

        $this->firePostCreateHooks($data, $path);

        return $path;
    }

    /**
     * @throws FileNotFoundException
     */
    protected function getDataStub(): string
    {
        $stub = $this->files->exists($customPath = $this->customStubPath.'/data-migration.model.stub')
            ? $customPath
            : $this->stubPath().'/data-migration.model.stub';

        return $this->files->get($stub);
    }

    #[\Override]
    public function stubPath(): string
    {
        return __DIR__.'/../../../stubs';
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
