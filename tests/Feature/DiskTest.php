<?php

declare(strict_types=1);

namespace Cat4year\DataMigratorTests\Feature;

use Illuminate\Config\Repository;
use Illuminate\Support\Facades\Storage;
use Cat4year\DataMigrator\Services\DataMigrator\Tools\DataSource\MigrationDataSourceFormat;
use Cat4year\DataMigrator\Services\DataMigrator\Tools\DataSource\Php\PhpMigrationDataSourceFormat;
use Cat4year\DataMigrator\Services\DataMigrator\Tools\DataSource\Xml\XmlMigrationDataSourceFormat;
use PHPUnit\Framework\Attributes\TestWith;

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
        $filesystem = Storage::fake('public');
        $fullPath = $filesystem->path($path);
        /** @var MigrationDataSourceFormat $sourceFormat */
        $sourceFormat = app($sourceFormatClass);

        $sourceFormat->save(['test' => true], $fullPath);

        $this->assertTrue($filesystem->exists($path));
    }

    public function test_get_attachment(): void
    {
        $attachment = Storage::disk('testing')->get('avatars/5d11b2895cecbda580a9f667bd26a6389143c982.jpg');

        $this->assertNotNull($attachment);
    }

    #[\Override]
    protected function getEnvironmentSetUp($app): void
    {
        $app->make(Repository::class)->set('filesystems.disks.testing.driver', 'local');
        $app->make(Repository::class)->set('filesystems.disks.testing.root', realpath(__DIR__.'/../Resource/Files'));
    }

    #[\Override]
    protected function tearDown(): void
    {
        // Storage::fake('public');

        parent::tearDown();
    }
}
