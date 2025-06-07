<?php

declare(strict_types=1);

namespace Cat4year\DataMigrator\Services\DataMigrator\Factory;

use Cat4year\DataMigrator\Entity\ExportModifyColumn;

final class ExportModifyColumnFactory
{
    /**
     * @var array<string, ExportModifyColumn>
     */
    private static array $state = [];

    public static function create(string $hash): Product
    {
        if (!isset(self::$state[$hash])) {
            self::$state[$hash] = new Product($hash);
        }

        return self::$state[$hash];
    }
}
