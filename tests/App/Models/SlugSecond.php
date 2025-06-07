<?php

declare(strict_types=1);

namespace Cat4year\DataMigratorTests\App\Models;

use Cat4year\DataMigratorTests\Database\Factory\SlugSecondFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * @mixin IdeHelperSlugSecond
 */
final class SlugSecond extends Model
{
    /** @use HasFactory<SlugSecondFactory> */
    use HasFactory;

    protected $fillable = [
        'slug',
        'name',
        'slug_first_id',
    ];

    protected static function newFactory(): SlugSecondFactory
    {
        return SlugSecondFactory::new();
    }

    /**
     * @return BelongsTo<SlugFirst>
     */
    public function slugFirst(): BelongsTo
    {
        return $this->belongsTo(SlugFirst::class, 'slug_first_id', 'id');
    }

    /**
     * @return HasOne<SlugThree>
     */
    public function slugThree(): HasOne
    {
        return $this->hasOne(SlugThree::class, 'slug_second_id', 'id');
    }

    public function slugFourable(): MorphMany
    {
        return $this->morphMany(SlugFour::class, 'slug_fourable');
    }
}
