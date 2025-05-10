<?php

declare(strict_types=1);

namespace Cat4year\DataMigrator\Services\DataMigrator\Export;

use Illuminate\Support\Collection as SupportCollection;

final readonly class ExporterState
{
    public function __construct(
        public SupportCollection $result,
        public SupportCollection $relationsInfo,
        public SupportCollection $entityIds,
    ) {
    }
}
