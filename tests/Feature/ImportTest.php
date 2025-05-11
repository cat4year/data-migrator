<?php

declare(strict_types=1);

namespace Tests\Feature;

use Cat4year\DataMigrator\Services\DataMigrator\Import\ImportData;
use Cat4year\DataMigrator\Services\DataMigrator\Import\Importer;
use Illuminate\Foundation\Testing\DatabaseMigrations;

final class ImportTest extends BaseTestCase
{
    use DatabaseMigrations;

    /**
     * Simple assert by count of rows model tables
     */
    public function test_replace_column_id_to_real_primary_id_for_has_relation(): void
    {
        $importer = app(Importer::class);
        $fullPath = __DIR__.'/Fixtures/export.php';

        $importData = ImportData::createFromFile($fullPath);
        $importer->import($importData);

        $this->assertDatabaseCount('slug_firsts', 4);
        $this->assertDatabaseCount('slug_threes', 4);
        $this->assertDatabaseCount('slug_seconds', 5);
        $this->assertDatabaseCount('slug_fours', 2);
    }
}
