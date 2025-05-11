<?php

declare(strict_types=1);

namespace Cat4year\DataMigrator\Providers;

use Cat4year\DataMigrator\Console\Commands\CreateMigrationCommand;
use Cat4year\DataMigrator\Console\Commands\PintFileCommand;
use Cat4year\DataMigrator\Services\DataMigrator\MigratorCreator;
use Cat4year\DataMigrator\Services\DataMigrator\Tools\DataSource\MigrationDataSourceFormat;
use Cat4year\DataMigrator\Services\DataMigrator\Tools\DataSource\Php\PhpMigrationDataSourceFormat;
use Illuminate\Support\ServiceProvider;

final class DataMigratorServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $bindings = [
        // MigrationDataSourceFormat::class => XmlMigrationDataSourceFormat::class,
        MigrationDataSourceFormat::class => PhpMigrationDataSourceFormat::class,
    ];

    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../../config/data-migrator.php', 'data-migrator'
        );

        require_once(__DIR__.'/../../src/Helpers/app.php');

        $this->registerCreator();
    }

    public function boot(): void
    {
        $this->commands([
            CreateMigrationCommand::class,
            PintFileCommand::class,
        ]);

        $this->publishes([
            __DIR__.'/../../config/data-migrator.php' => config_path('data-migrator.php'),
        ]);
    }

    protected function registerCreator(): void
    {
        $this->app->singleton(
            'migration-data.creator',
            static fn ($app) => new MigratorCreator($app['files'], $app->basePath('stubs'))
        );
    }
}
