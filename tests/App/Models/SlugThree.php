<?php

declare(strict_types=1);

namespace Cat4year\DataMigratorTests\App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Cat4year\DataMigratorTests\Database\Factory\SlugThreeFactory;

/**
 * @mixin IdeHelperSlugThree
 */
final class SlugThree extends Model
{
    /** @use HasFactory<SlugThreeFactory> */
    use HasFactory;

    protected $fillable = [
        'slug',
        'name',
        'slug_second_id',
        'slug_three_id',
    ];

    protected static function newFactory(): SlugThreeFactory
    {
        return SlugThreeFactory::new();
    }

    /**
     * @return HasOne<SlugFirst>
     */
    public function slugFirst(): HasOne
    {
        return $this->hasOne(SlugFirst::class, 'slug_three_id', 'id');
    }

    /**
     * @return BelongsTo<SlugSecond>
     */
    public function slugSecond(): BelongsTo
    {
        return $this->belongsTo(SlugSecond::class, 'slug_second_id', 'id');
    }

    public function slugSeconds(): MorphToMany
    {
        return $this->morphToMany(SlugSecond::class, 'slug_secondable');
    }
}
