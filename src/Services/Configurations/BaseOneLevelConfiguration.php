<?php

declare(strict_types=1);

namespace Cat4year\DataMigrator\Services\Configurations;

use Cat4year\DataMigrator\Services\DataMigrator\Export\ExportConfigurator;
use Cat4year\DataMigrator\Services\DataMigrator\Tools\Attachment\AttachmentSaver;

/** if u used it - u need be sure that in a belongsTo relationship the foreign key will already exist */
final readonly class BaseOneLevelConfiguration implements DataMigratorConfiguration
{
    public function make(array $ids = []): ExportConfigurator
    {
        return resolve(ExportConfigurator::class)
            ->setAttachmentSaver(resolve(AttachmentSaver::class))
            ->setIds($ids);
    }

    public function update(ExportConfigurator $exportConfigurator): ExportConfigurator
    {
        return $exportConfigurator;
    }
}
