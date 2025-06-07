<?php

declare(strict_types=1);

namespace Cat4year\DataMigrator\Entity;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

final readonly class ExportModifySimpleColumn implements Arrayable, ExportModifyColumn, JsonSerializable
{
    public function __construct(
        private string $tableName,
        private string $keyName,
        private SyncId $uniqueKeyName,
        private bool $nullable,
        private bool $autoincrement,
        private bool $isPrimaryKey = true,
    ) {
    }

    public function getKeyName(): string
    {
        return $this->keyName;
    }

    public function getTableName(): string
    {
        return $this->tableName;
    }

    public function getSourceTableName(): string
    {
        return $this->tableName;
    }

    public function getSourceKeyName(): string
    {
        return $this->keyName;
    }

    public function getSourceUniqueKeyName(): SyncId
    {
        return $this->uniqueKeyName;
    }

    public function isNullable(): bool
    {
        return $this->nullable;
    }

    public function isAutoincrement(): bool
    {
        return $this->autoincrement;
    }

    public function isPrimaryKey(): bool
    {
        return $this->isPrimaryKey;
    }

    public static function fromArray(array $array): self
    {
        $uniqueKeyName = new SyncId($array['uniqueKeyName']);

        return new self(
            tableName: $array['tableName'],
            keyName: $array['keyName'],
            uniqueKeyName: $uniqueKeyName,
            nullable: $array['nullable'],
            autoincrement: $array['autoincrement'],
            isPrimaryKey: $array['isPrimaryKey'],
        );
    }

    public function toArray(): array
    {
        return [
            'tableName' => $this->tableName,
            'keyName' => $this->keyName,
            'uniqueKeyName' => $this->uniqueKeyName,
            'nullable' => $this->nullable,
            'autoincrement' => $this->autoincrement,
            'isPrimaryKey' => $this->isPrimaryKey,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
