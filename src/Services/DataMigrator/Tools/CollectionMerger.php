<?php

declare(strict_types=1);

namespace Cat4year\DataMigrator\Services\DataMigrator\Tools;

use Illuminate\Support\Collection as SupportCollection;
use InvalidArgumentException;

final readonly class CollectionMerger
{
    public function putWithMerge(
        SupportCollection $supportCollection,
        string $key,
        SupportCollection|array $data,
        bool $forceUnique = false
    ): SupportCollection|array {
        if (! $supportCollection->has($key)) {
            $supportCollection->put($key, $data);

            return $data;
        }

        $currentItem = $supportCollection->get($key);
        throw_if(! is_array($currentItem) && ! $currentItem instanceof SupportCollection, new InvalidArgumentException('Attribute collection was collection of collections|arrays'));

        $currentCollection = is_array($currentItem) ? collect($currentItem) : $currentItem;
        $currentCollection = $currentCollection->merge($data);

        if ($forceUnique) {
            $currentCollection = $currentCollection->unique();
        }

        $mergedValues = is_array($currentItem) ? $currentCollection->toArray() : $currentCollection;

        $supportCollection->put($key, $mergedValues);

        return $mergedValues;
    }
}
