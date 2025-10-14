<?php

declare(strict_types=1);

namespace Cat4year\DataMigrator\Entity;

use Illuminate\Contracts\Support\Arrayable;
use InvalidArgumentException;
use JsonSerializable;
use LogicException;

final readonly class SyncId implements Arrayable, JsonSerializable
{
    private string $hash;

    public function __construct(
        private array $columns,
    ) {
        if ($columns === []) {
            throw new InvalidArgumentException('SyncId cannot be empty.');
        }

        $this->hash = self::makeHash($columns);
    }

    public function keyStringByValues(array $values): string
    {
        $result = [];

        foreach ($this->columns as $column) {
            if (! array_key_exists($column, $values)) {
                throw new LogicException('Column not found in values');
            }

            $result[] = $values[$column];
        }

        return implode('|', $result);
    }

    /**
     * @param list<string>|string $keys
     */
    public static function makeHash(array|string $keys): string
    {
        if (is_string($keys)) {
            return $keys;
        }

        if (count($keys) === 1) {
            return current($keys);
        }

        return implode('|', $keys);
        // sort($keys);
        // return md5(json_encode($keys, JSON_THROW_ON_ERROR));
    }

    public function hash(): string
    {
        return $this->hash;
    }

    public function columns(): array
    {
        return $this->columns;
    }

    public function toArray(): array
    {
        return $this->columns;
    }

    public function jsonSerialize(): array
    {
        return $this->columns;
    }
}
