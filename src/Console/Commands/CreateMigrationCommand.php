<?php

declare(strict_types=1);

namespace Cat4year\DataMigrator\Console\Commands;

use Cat4year\DataMigrator\Services\Configurations\BaseConfiguration;
use Cat4year\DataMigrator\Services\Configurations\DataMigratorConfiguration;
use Cat4year\DataMigrator\Services\DataMigrator\Migrator;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\File;
use SplFileInfo;

final class CreateMigrationCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'data-migrator:create
     {--name= : The name register migration}
     {--path= : Path for save migration file}
     {--config= : Migration class with some configuration}
     {--model= : Main model for migration}
     {--ids= : Model ids for migration (, - separator)}
     ';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create migration file for data';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $input = $this->input;
        $name = $input->getOption('name') ?? $this->ask('Name');
        $path = $input->getOption('path') ?? $this->getBasePath();
        $modelByTable = $this->getModelByTable();
        $modelTable = $this->input->getOption('model') ?? $this->choice(
            'Model',
            $modelByTable,
        );

        $modelClass = $modelByTable[$modelTable];

        if ($name === null || $modelClass === null) {
            $this->error('Name or model is empty');

            return;
        }

        $ids = $input->getOption('ids') ?? $this->ask('Ids');
        $configurationClass = $input->getOption('config');

        $migrationPath = app(Migrator::class)->createByConfiguration(
            $this->defineConfiguration($modelClass, $configurationClass),
            $name,
            $path,
            $modelClass,
            explode(',', mb_trim($ids))
        );

        $this->info(sprintf('Created data-migration "%s"', $migrationPath));
    }

    /**
     * @return array<non-empty-string, class-string<Model>>
     */
    private function getModelByTable(): array
    {
        $result = [];

        $models = $this->getModels();
        foreach ($models as $model) {
            $table = app($model)->getTable();
            $result[$table] = $model;
        }

        return $result;
    }

    /**
     * @param class-string<DataMigratorConfiguration>|null $configurationClass
     * @param class-string<Model> $modelClass
     * @return class-string<DataMigratorConfiguration>
     */
    private function defineConfiguration(string $modelClass, ?string $configurationClass = null): string
    {
        if ($configurationClass !== null) {
            return $configurationClass;
        }

        $model = app($modelClass);
        assert($model instanceof Model);
        if (
            property_exists($model, 'dataMigratorConfiguration')
            && is_subclass_of($model->dataMigratorConfiguration, DataMigratorConfiguration::class)
        ) {
            return $model->dataMigratorConfiguration;
        }

        /** @var array<class-string<Model>, class-string<DataMigratorConfiguration>> $modelConfigMap */
        $modelConfigMap = config('data-migrator.model_config_map');

        if (
            is_array($modelConfigMap)
            && isset($modelConfigMap[$modelClass])
            && is_subclass_of($modelConfigMap[$modelClass], DataMigratorConfiguration::class)
        ) {
            return $modelConfigMap[$modelClass];
        }

        return BaseConfiguration::class;
    }

    /**
     * @return array<class-string<Model>>
     */
    private function getModels(): array
    {
        $namespace = app()->getNamespace().'Models\\';
        $files = File::allFiles(app_path('Models'));

        return collect($files)
            ->map(static function (SplFileInfo $file) use ($namespace) {
                $filePath = str_replace('.'.$file->getExtension(), '', $file->getRelativePathname());

                return $namespace.str_replace('/', '\\', $filePath);
            })
            ->filter(static fn ($class): bool => class_exists($class) && is_subclass_of($class, Model::class))->values()->toArray();
    }

    private function getBasePath(): string
    {
        return config('data-migrator.migrations_path', database_path('migrations'));
    }
}
