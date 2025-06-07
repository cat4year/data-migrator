<?php

declare(strict_types=1);

use Cat4year\DataMigratorTests\App\Models\SlugSecond;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('slug_secondables', static function (Blueprint $blueprint): void {
            $blueprint->id();
            $blueprint->foreignIdFor(SlugSecond::class)
                ->constrained()
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            $blueprint->morphs('slug_secondable');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('slug_secondables');
    }
};
