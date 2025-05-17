<?php

namespace Cat4year\DataMigrator\Entity;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

final class ExportModifyMorphColumn implements ExportModifyColumn, Arrayable, JsonSerializable
{
    /**
     * @param list<string>|array<string, string> $sourceKeyNames
     * @param list<string>|array<string, string> $sourceOldKeyNames
     */
    public function __construct(
        private readonly string $morphType,
        private readonly string $tableName,
        private readonly string $keyName,
        private array $sourceKeyNames,
        private array $sourceOldKeyNames,
        private readonly bool $nullable,
        private readonly bool $autoincrement,
        private readonly bool $isPrimaryKey = false,
    )
    {
    }

    public function getSourceKeyNames(): array
    {
        return $this->sourceKeyNames;
    }

    public function getSourceOldKeyNames(): array
    {
        return $this->sourceOldKeyNames;
    }


    public function addOldKeyNames(array $oldKeyNames): void
    {
        $this->sourceOldKeyNames += $oldKeyNames;
    }

    public function addKeyNames(array $keyNames): void
    {
        $this->sourceKeyNames += $keyNames;
    }

    public function getMorphType(): string
    {
        return $this->morphType;
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
        throw new \RuntimeException('Проблемка getSourceTableName');
    }

    public function getSourceKeyName(): string
    {
        throw new \RuntimeException('Проблемка getSourceKeyName');
    }

    public function getSourceUniqueKeyName(): ?string
    {
        throw new \RuntimeException('Проблемка getSourceUniqueKeyName');
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
            morphType: $array['morphType'],
            tableName: $array['tableName'],
            keyName: $array['keyName'],
            sourceKeyNames: $array['sourceKeyNames'],
            sourceOldKeyNames: $array['sourceOldKeyNames'],
            nullable: $array['nullable'],
            autoincrement: $array['autoincrement'],
            isPrimaryKey: $array['isPrimaryKey'],
        );
    }

    public function toArray(): array
    {
        return [
            'morphType' => $this->morphType,
            'tableName' => $this->tableName,
            'keyName' => $this->keyName,
            'sourceKeyNames' => $this->sourceKeyNames,
            'sourceOldKeyNames' => $this->sourceOldKeyNames,
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
