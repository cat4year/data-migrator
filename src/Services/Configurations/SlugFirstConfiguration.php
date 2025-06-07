<?php

declare(strict_types=1);

namespace Cat4year\DataMigrator\Services\Configurations;

use Cat4year\DataMigrator\Services\DataMigrator\Export\ExportConfigurator;

final readonly class SlugFirstConfiguration implements DataMigratorConfiguration
{
    public function make(array $ids = []): ExportConfigurator
    {
        return app(ExportConfigurator::class)
            ->setMaxRelationDepth(1)
            ->setIds($ids);
    }

    public function update(ExportConfigurator $exportConfigurator): ExportConfigurator
    {
        return $exportConfigurator;
    }
}
