<?php

declare(strict_types=1);

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
        Schema::create('slug_first_slug_second', static function (Blueprint $blueprint): void {
            $blueprint->id();
            $blueprint->unsignedInteger('slug_first_id');
            $blueprint->unsignedInteger('slug_second_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('slug_first_slug_second');
    }
};
