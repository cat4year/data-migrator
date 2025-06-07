<?php

declare(strict_types=1);

namespace Cat4year\DataMigrator\Services\DataMigrator\Export;

final readonly class ExportModifyState
{
    private array $tableColumnsModifyInfoMap;

    public function getTableColumnsModifyInfoMap(): array
    {
        return $this->tableColumnsModifyInfoMap;
    }
}
