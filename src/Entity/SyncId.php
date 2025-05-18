<?php

namespace Cat4year\DataMigrator\Entity;

use Illuminate\Contracts\Support\Arrayable;
use InvalidArgumentException;
use JsonSerializable;

final readonly class SyncId implements Arrayable, JsonSerializable
{
    /** @var array<string, mixed> $columns */
    public function __construct(private array $columns)
    {
        if (empty($columns)) {
            throw new InvalidArgumentException('SyncId cannot be empty.');
        }
    }

    public function toArray(): array
    {
        return $this->columns;
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
