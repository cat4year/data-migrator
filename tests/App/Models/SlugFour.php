<?php

declare(strict_types=1);

namespace Tests\App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Tests\Database\Factory\SlugFourFactory;

/**
 * @mixin IdeHelperSlugFour
 */
final class SlugFour extends Model
{
    /** @use HasFactory<SlugFourFactory> */
    use HasFactory;

    protected $fillable = [
        'slug',
        'name',
        'slug_fourable_id',
        'slug_fourable_type',
    ];

    protected static function newFactory(): SlugFourFactory
    {
        return SlugFourFactory::new();
    }

    public function slugFourable(): MorphTo
    {
        return $this->morphTo();
    }
}
