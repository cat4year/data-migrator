<?php

declare(strict_types=1);

namespace Cat4year\DataMigrator\Console\Commands;

use Cat4year\DataMigrator\Services\Configurations\DataMigratorConfiguration;
use Cat4year\DataMigrator\Services\DataMigrator\Export\ExportConfigurator;
use Cat4year\DataMigrator\Services\DataMigrator\Migrator;
use Cat4year\DataMigrator\Services\DataMigrator\Tools\Attachment\BlankAttachmentSaver;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\File;
use ReflectionClass;
use RuntimeException;
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
     {--depth= : Rewrite max depth level for collect relations}
     {--N|no-attachments : Without attachments}
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

        $ids = $input->getOption('ids') ?? $this->ask('Ids') ?? '';
        $configurationClass = $input->getOption('config');
        if ($configurationClass === null) {
            $configurationClass = $this->defineConfiguration($modelClass);
        }

        $configurator = $this->makeConfigurator($configurationClass);
        if ($this->option('depth') !== null && is_numeric($this->option('depth'))) {
            $configurator->setMaxRelationDepth((int) $this->option('depth'));
        }

        if ($this->option('no-attachments')) {
            $configurator->setAttachmentSaver(resolve(BlankAttachmentSaver::class));
        }

        $configurator->setDirectoryPath($path)
            ->setIds(!empty($ids) ? explode(',', mb_trim($ids)) : [])
            ->setFileName($name);

        $migrationPath = app(Migrator::class)->createByConfiguration(
            $configurator,
            $modelClass,
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
    private function defineConfiguration(string $modelClass): string
    {
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

        return DataMigratorConfiguration::class;
    }

    /**
     * @return array<class-string<Model>>
     */
    private function getModels(): array
    {
        $namespace = app()->getNamespace() . 'Models\\';
        $files = File::allFiles(app_path('Models'));

        $models = collect($files)
            ->map(static function (SplFileInfo $file) use ($namespace) {
                $filePath = str_replace('.' . $file->getExtension(), '', $file->getRelativePathname());

                return $namespace . str_replace('/', '\\', $filePath);
            })
            ->filter(static fn ($class): bool => class_exists($class) && is_subclass_of($class, Model::class))->values()->toArray();

        // 2. Рекурсивный поиск всех php-файлов в packages
        $packageFiles = collect(File::allFiles(base_path('packages')))
            ->filter(fn (SplFileInfo $file) => $file->getExtension() === 'php');

        $packageModels = $packageFiles
            ->map(fn (SplFileInfo $file) => $this->getClassFromFile($file->getPathname()))
            ->filter(function (?string $class) {
                if (! $class || ! class_exists($class)) {
                    return false;
                }

                if (! is_subclass_of($class, Model::class)) {
                    return false;
                }

                $reflection = new ReflectionClass($class);

                return ! $reflection->isAbstract();
            })
            ->values()
            ->toArray();

        return array_merge($models, $packageModels);
    }

    private function getClassFromFile(string $file): ?string
    {
        $contents = file_get_contents($file);

        // Получаем namespace
        if (preg_match('/namespace\s+(.+?);/', $contents, $matches)) {
            $namespace = $matches[1];
        } else {
            return null;
        }

        // Получаем все классы в файле
        if (preg_match_all('/class\s+(\w+)/', $contents, $matches)) {
            foreach ($matches[1] as $class) {
                $fullClass = $namespace . '\\' . $class;
                if (class_exists($fullClass) && is_subclass_of($fullClass, Model::class)) {
                    return $fullClass;
                }
            }
        }

        return null;
    }

    private function getBasePath(): string
    {
        return config('data-migrator.migrations_path') ?? database_path('migrations');
    }

    private function makeConfigurator(string $configClass): ExportConfigurator
    {
        throw_if(! (resolve($configClass) instanceof DataMigratorConfiguration), new RuntimeException('Migrator configuration class is incorrect'));

        $dataMigratorConfiguration = app($configClass);
        assert($dataMigratorConfiguration instanceof DataMigratorConfiguration);
        return $dataMigratorConfiguration->make();
    }
}
