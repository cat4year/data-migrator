<?php

declare(strict_types=1);

namespace Cat4year\DataMigratorTests\Feature\Export;

use Cat4year\DataMigrator\Services\DataMigrator\Export\ExportConfigurator;
use Cat4year\DataMigrator\Services\DataMigrator\Export\Exporter;
use Cat4year\DataMigratorTests\App\Models\SlugFirst;
use Cat4year\DataMigratorTests\Database\Seeders\DatabaseSeeder;
use Cat4year\DataMigratorTests\Feature\BaseTestCase;
use Exception;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Override;

final class ExportTest extends BaseTestCase
{
    use DatabaseMigrations;

    #[Override]
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
        $filesystem = Storage::fake('public');
        $path = 'test_without_relations';
        $exportConfigurator = app(ExportConfigurator::class);
        $exportConfigurator->setWithRelations(false)
            ->setDisk($filesystem)
            ->setFileName($path)
            ->setMaxRelationDepth(PHP_INT_MAX);

        $exporter = Exporter::create(app(SlugFirst::class), $exportConfigurator);

        $exporter->export();

        $this->assertTrue($filesystem->exists($exportConfigurator->makeSourceBaseName()));
    }

    /**
     * @throws BindingResolutionException
     */
    public function test_export_with_relations(): void
    {
        $filesystem = Storage::fake('public');
        $path = 'test_with_relations';
        $exportConfigurator = app(ExportConfigurator::class);
        $exportConfigurator->setDisk($filesystem)->setFileName($path)->setMaxRelationDepth(PHP_INT_MAX);
        $exporter = Exporter::create(app(SlugFirst::class), $exportConfigurator);

        $exporter->export();

        $this->assertTrue($filesystem->exists($exportConfigurator->makeSourceBaseName()));
    }

    #[Override]
    protected function tearDown(): void
    {
        Storage::fake('public');
        Mockery::close();
        parent::tearDown();
    }
}
