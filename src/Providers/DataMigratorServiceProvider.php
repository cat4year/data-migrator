<?php

declare(strict_types=1);

namespace Cat4year\DataMigrator\Providers;

use Cat4year\DataMigrator\Console\Commands\CreateMigrationCommand;
use Cat4year\DataMigrator\Console\Commands\PintFileCommand;
use Cat4year\DataMigrator\Services\DataMigrator\MigratorCreator;
use Cat4year\DataMigrator\Services\DataMigrator\Tools\Attachment\AttachmentSaver;
use Cat4year\DataMigrator\Services\DataMigrator\Tools\Attachment\OrchidAttachmentSaver;
use Cat4year\DataMigrator\Services\DataMigrator\Tools\DataSource\MigrationDataSourceFormat;
use Cat4year\DataMigrator\Services\DataMigrator\Tools\DataSource\Php\PhpMigrationDataSourceFormat;
use Cat4year\DataMigrator\Services\DataMigrator\Tools\SchemaState;
use Illuminate\Support\ServiceProvider;
use Override;

final class DataMigratorServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $bindings = [
        // MigrationDataSourceFormat::class => XmlMigrationDataSourceFormat::class,
        MigrationDataSourceFormat::class => PhpMigrationDataSourceFormat::class,
        AttachmentSaver::class => OrchidAttachmentSaver::class,
    ];

    #[Override]
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../../config/data-migrator.php',
            'data-migrator'
        );

        require_once __DIR__ . '/../../src/Helpers/app.php';

        $this->registerCreator();
        $this->app->singleton(SchemaState::class, static fn ($app): SchemaState => new SchemaState);
    }

    public function boot(): void
    {
        $this->commands([
            CreateMigrationCommand::class,
            PintFileCommand::class,
        ]);

        $this->publishes([
            __DIR__ . '/../../config/data-migrator.php' => config_path('data-migrator.php'),
            'data-migrator-config'
        ]);
    }

    private function registerCreator(): void
    {
        $this->app->singleton(
            'migration-data.creator',
            static fn ($app): MigratorCreator => new MigratorCreator($app['files'], $app->basePath('stubs'))
        );
    }
}
