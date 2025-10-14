<?php

declare(strict_types=1);

namespace Cat4year\DataMigratorTests\Feature;

use Cat4year\DataMigrator\Services\DataMigrator\Export\ExportConfigurator;
use Cat4year\DataMigrator\Services\DataMigrator\Export\Exporter;
use Cat4year\DataMigrator\Services\DataMigrator\Import\ImportData;
use Cat4year\DataMigrator\Services\DataMigrator\Import\Importer;
use Cat4year\DataMigratorTests\App\Models\CompositeKey;
use Cat4year\DataMigratorTests\Database\Seeders\CompositeKeyDuplicateSeeder;
use Cat4year\DataMigratorTests\Database\Seeders\CompositeKeySeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Override;

final class CompositeKeyTest extends BaseTestCase
{
    use DatabaseMigrations;

    public function test_success_export_with_composite_keys(): void
    {
        $this->seed(CompositeKeySeeder::class);
        $filesystem = Storage::fake('public');
        $path = 'test_success_sync_with_composite_keys';
        $exportConfigurator = app(ExportConfigurator::class);
        $exportConfigurator->setWithRelations(false)
            ->setDisk($filesystem)
            ->setFileName($path);

        $exporter = Exporter::create(app(CompositeKey::class), $exportConfigurator);

        $exporter->export();

        $this->assertTrue($filesystem->exists($exportConfigurator->makeSourceBaseName()));
    }

    public function test_success_import_with_composite_keys_on_empty(): void
    {
        $importer = app(Importer::class);
        $fullPath = __DIR__ . '/Fixtures/test_success_sync_with_composite_keys.php';

        $importData = ImportData::createFromFile($fullPath);
        $importer->import($importData);

        $this->assertDatabaseCount('composite_keys', 3);
    }

    public function test_success_double_import_with_composite_keys(): void
    {
        $importer = app(Importer::class);
        $fullPath = __DIR__ . '/Fixtures/test_success_sync_with_composite_keys.php';

        $importData = ImportData::createFromFile($fullPath);
        $importer->import($importData);
        $importer->import($importData);

        $this->assertDatabaseCount('composite_keys', 3);
    }

    public function test_success_duplicate_export_with_composite_keys(): void
    {
        $this->seed(CompositeKeyDuplicateSeeder::class);
        $filesystem = Storage::disk('public');
        $path = 'test_success_sync_duplicate_with_composite_keys';
        $exportConfigurator = app(ExportConfigurator::class);
        $exportConfigurator->setWithRelations(false)
            ->setDisk($filesystem)
            ->setFileName($path);

        $exporter = Exporter::create(app(CompositeKey::class), $exportConfigurator);

        $exporter->export();

        $this->assertTrue($filesystem->exists($exportConfigurator->makeSourceBaseName()));
    }

    public function test_success_import_duplicate_with_composite_keys(): void
    {
        $importer = app(Importer::class);
        $fullPath = __DIR__ . '/Fixtures/test_success_sync_duplicate_with_composite_keys.php';

        $importData = ImportData::createFromFile($fullPath);
        $importer->import($importData);

        $this->assertDatabaseCount('composite_keys', 2);
    }

    #[Override]
    protected function tearDown(): void
    {
        Storage::fake('public');
        Mockery::close();
        parent::tearDown();
    }
}
