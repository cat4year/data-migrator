<?php

declare(strict_types=1);

namespace Tests\App\Models;

use Cat4year\DataMigrator\Services\Configurations\SlugFirstConfiguration;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Tests\Database\Factory\SlugFirstFactory;

/**
 * @mixin IdeHelperSlugFirst
 */
final class SlugFirst extends Model
{
    /** @use HasFactory<SlugFirstFactory> */
    use HasFactory;

    public string $dataMigratorConfiguration = SlugFirstConfiguration::class;

    protected $fillable = [
        'slug',
        'bool_test',
        'timestamp_test',
        'string_test',
        'int_test',
        'slug_three_id',
    ];

    protected static function newFactory(): SlugFirstFactory
    {
        return SlugFirstFactory::new();
    }

    /**
     * @return HasOne<SlugSecond>
     */
    public function slugSecond(): HasOne
    {
        return $this->hasOne(SlugSecond::class, 'slug_first_id', 'id');
    }

    /**
     * @return BelongsToMany<SlugSecond>
     */
    public function slugSeconds(): BelongsToMany
    {
        return $this->belongsToMany(SlugSecond::class);
    }

    /**
     * @return BelongsTo<SlugThree>
     */
    public function slugThree(): BelongsTo
    {
        return $this->belongsTo(SlugThree::class, 'slug_three_id', 'id');
    }

    /**
     * @return HasMany<SlugSecond>
     */
    public function slugSecondsHasMany(): HasMany
    {
        return $this->hasMany(SlugSecond::class, 'slug_first_id', 'id');
    }

    public function slugSecondSlugThree(): HasOneThrough
    {
        return $this->hasOneThrough(SlugThree::class, SlugSecond::class);
    }

    public function slugSecondables(): MorphToMany
    {
        return $this->morphToMany(SlugSecond::class, 'slug_secondable');
    }

    public function slugFours(): MorphOne
    {
        return $this->morphOne(SlugFour::class, 'slug_fourable');
    }
}
