<?php

declare(strict_types=1);

namespace Cat4year\DataMigratorTests\Feature;

use Cat4year\DataMigrator\Services\DataMigrator\Export\ExportConfigurator;
use Cat4year\DataMigrator\Services\DataMigrator\Export\Exporter;
use Exception;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Cat4year\DataMigratorTests\App\Models\SlugFirst;
use Cat4year\DataMigratorTests\Database\Seeders\DatabaseSeeder;

final class ExportTest extends BaseTestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_export_throws_error_when_exporter_fails(): void
    {
        $mockExporter = Mockery::mock(Exporter::class);
        $mockExporter->expects('export')
            ->andThrows(new Exception('Export failed'));

        $this->expectException(Exception::class);

        $mockExporter->export();
    }

    /**
     * @throws BindingResolutionException
     */
    public function test_export_without_relations(): void
    {
        $disk = Storage::fake('public');
        $path = 'test_without_relations';
        $configurator = app(ExportConfigurator::class);
        $configurator->setWithRelations(false)
            ->setDisk($disk)
            ->setFileName($path)
            ->setMaxRelationDepth(PHP_INT_MAX);

        $exporter = Exporter::create(app(SlugFirst::class), $configurator);

        $exporter->export();

        $this->assertTrue($disk->exists($configurator->makeSourceBaseName()));
    }

    /**
     * @throws BindingResolutionException
     */
    public function test_export_with_relations(): void
    {
        $disk = Storage::fake('public');
        $path = 'test_with_relations';
        $configurator = app(ExportConfigurator::class);
        $configurator->setDisk($disk)->setFileName($path)->setMaxRelationDepth(PHP_INT_MAX);
        $exporter = Exporter::create(app(SlugFirst::class), $configurator);

        $exporter->export();

        $this->assertTrue($disk->exists($configurator->makeSourceBaseName()));
    }

    protected function tearDown(): void
    {
        Storage::fake('public');
        Mockery::close();
        parent::tearDown();
    }
}
