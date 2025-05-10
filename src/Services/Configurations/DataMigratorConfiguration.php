<?php

declare(strict_types=1);

namespace Cat4year\DataMigrator\Services\Configurations;

use Cat4year\DataMigrator\Services\DataMigrator\Export\ExportConfigurator;

interface DataMigratorConfiguration
{
    public function make(): ExportConfigurator;

    public function update(ExportConfigurator $configurator): ExportConfigurator;
}
