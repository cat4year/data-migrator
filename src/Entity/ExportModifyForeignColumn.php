<?php

namespace Cat4year\DataMigrator\Entity;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

final readonly class ExportModifyForeignColumn implements ExportModifyColumn, Arrayable, JsonSerializable
{
    public function __construct(
        private string $tableName,
        private string $keyName,
        private string $foreignTableName,
        private ?string $foreignUniqueKeyName,
        private string $foreignOldKeyName,
        private bool $nullable,
        private bool $autoincrement = false,
        private bool $isPrimaryKey = false,
    )
    {
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
        return $this->foreignTableName;
    }

    public function getSourceKeyName(): string
    {
        return $this->foreignOldKeyName;
    }

    public function getSourceUniqueKeyName(): ?string
    {
        return $this->foreignUniqueKeyName;
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
        return new self(
            tableName: $array['tableName'],
            keyName: $array['keyName'],
            foreignTableName: $array['foreignTableName'],
            foreignUniqueKeyName: $array['foreignUniqueKeyName'],
            foreignOldKeyName: $array['foreignOldKeyName'],
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
            'foreignTableName' => $this->foreignTableName,
            'foreignUniqueKeyName' => $this->foreignUniqueKeyName,
            'foreignOldKeyName' => $this->foreignOldKeyName,
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
