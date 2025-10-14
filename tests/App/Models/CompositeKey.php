<?php

declare(strict_types=1);

namespace Cat4year\DataMigratorTests\App\Models;

use Cat4year\DataMigrator\Services\Configurations\SlugFirstConfiguration;
use Cat4year\DataMigratorTests\Database\Factory\CompositeKeyFactory;
use Cat4year\DataMigratorTests\Database\Factory\SlugFirstFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

final class CompositeKey extends Model
{
    /** @use HasFactory<CompositeKeyFactory> */
    use HasFactory;

    protected $fillable = [
        'key1',
        'key2',
        'key3',
    ];

    protected static function newFactory(): CompositeKeyFactory
    {
        return CompositeKeyFactory::new();
    }
}
