<?php

namespace Cat4year\DataMigrator\Services\DataMigrator\Export;

final readonly class ExportModifyState
{
    private array $tableColumnsModifyInfoMap;

    public function getTableColumnsModifyInfoMap(): array
    {
        return $this->tableColumnsModifyInfoMap;
    }

    public function addModifyInfoToTableColumnsMap(array $modifyInfo): void
    {
        foreach($modifyInfo as $tableName => $tableModifyColumns){
            foreach($tableModifyColumns as $columnKey => $modifyColumn){

            }
        }
    }
}
