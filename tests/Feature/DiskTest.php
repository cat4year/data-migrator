<?php

declare(strict_types=1);

namespace Tests\Feature;

use Cat4year\DataMigrator\Services\DataMigrator\Tools\DataSource\MigrationDataSourceFormat;
use Cat4year\DataMigrator\Services\DataMigrator\Tools\DataSource\Php\PhpMigrationDataSourceFormat;
use Cat4year\DataMigrator\Services\DataMigrator\Tools\DataSource\Xml\XmlMigrationDataSourceFormat;
use PHPUnit\Framework\Attributes\TestWith;
use Storage;

final class DiskTest extends BaseTestCase
{
    /**
     * @param class-string<MigrationDataSourceFormat> $sourceFormatClass
     */
    #[TestWith(['test.xml', XmlMigrationDataSourceFormat::class])]
    #[TestWith(['test.php', PhpMigrationDataSourceFormat::class])]
    #[TestWith(['test/test.php', PhpMigrationDataSourceFormat::class])]
    public function test_save_file(string $path, string $sourceFormatClass): void
    {
        $storage = Storage::fake('public');
        $fullPath = $storage->path($path);
        /** @var MigrationDataSourceFormat $sourceFormat */
        $sourceFormat = app($sourceFormatClass);

        $sourceFormat->save(['test' => true], $fullPath);

        $this->assertTrue($storage->exists($path));
    }

    protected function tearDown(): void
    {
        // Storage::fake('public');

        parent::tearDown();
    }
}
