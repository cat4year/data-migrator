<?php

declare(strict_types=1);

namespace Cat4year\DataMigrator\Services\DataMigrator\Export\Relations;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;

final readonly class RelationFactory
{
    public function createByRelation(Relation $relation, ?Collection $collection = null): ?RelationExporter
    {
        return match ($relation::class) {
             //HasOne::class, HasMany::class => HasOneOrManyExporter::create($relation),
             //HasOneThrough::class, HasManyThrough::class => HasOneOrManyThroughExporter::create($relation),
             //BelongsToMany::class => BelongsToManyExporter::create($relation),
            BelongsTo::class => BelongsToExporter::create($relation),
            MorphToMany::class => MorphToManyExporter::create($relation),
            MorphOne::class, MorphMany::class => MorphOneOrManyExporter::create($relation),
            MorphTo::class => MorphToExporter::create($relation, $collection?->get($relation->getParent()->getTable())),
            default => null
        };
    }
}
