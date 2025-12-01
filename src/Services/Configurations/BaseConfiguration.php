<?php

declare(strict_types=1);

namespace Cat4year\DataMigrator\Services\Configurations;

use Cat4year\DataMigrator\Services\DataMigrator\Export\ExportConfigurator;
use Cat4year\DataMigrator\Services\DataMigrator\Tools\Attachment\AttachmentSaver;

final readonly class BaseConfiguration implements DataMigratorConfiguration
{
    public function make(array $ids = []): ExportConfigurator
    {
        return app(ExportConfigurator::class)
            ->setMaxRelationDepth(PHP_INT_MAX)
            ->setAttachmentSaver(app(AttachmentSaver::class))
            ->setIds($ids);
    }

    public function update(ExportConfigurator $exportConfigurator): ExportConfigurator
    {
        return $exportConfigurator;
    }
}
