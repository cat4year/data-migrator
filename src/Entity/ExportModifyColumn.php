<?php

declare(strict_types=1);

namespace Cat4year\DataMigrator\Entity;

interface ExportModifyColumn
{

    public function getKeyName(): string;

    public function getTableName(): string;

    public function getSourceTableName(): string;

    public function getSourceKeyName(): string;

    public function getSourceUniqueKeyName(): SyncId;

    public function isNullable(): bool;

    public function isAutoincrement(): bool;

    public function isPrimaryKey(): bool;
}
