<?php

declare(strict_types=1);

namespace Cat4year\DataMigrator\Services\DataMigrator\Tools;

use Illuminate\Support\Collection as SupportCollection;
use InvalidArgumentException;

final readonly class CollectionMerger
{
    public function putWithMerge(
        SupportCollection $collection,
        string $key,
        SupportCollection|array $data,
        bool $forceUnique = false
    ): SupportCollection|array {
        if (! $collection->has($key)) {
            $collection->put($key, $data);

            return $data;
        }

        $currentItem = $collection->get($key);
        if (! is_array($currentItem) && ! $currentItem instanceof SupportCollection) {
            throw new InvalidArgumentException('Attribute collection was collection of collections|arrays');
        }

        $currentCollection = is_array($currentItem) ? collect($currentItem) : $currentItem;
        $currentCollection = $currentCollection->merge($data);

        if ($forceUnique) {
            $currentCollection = $currentCollection->unique();
        }

        if (is_array($currentItem)) {
            $mergedValues = $currentCollection->toArray();
        } else {
            $mergedValues = $currentCollection;
        }

        $collection->put($key, $mergedValues);

        return $mergedValues;
    }
}
