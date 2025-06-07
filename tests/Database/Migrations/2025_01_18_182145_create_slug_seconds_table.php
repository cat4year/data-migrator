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
        Schema::create('slug_seconds', static function (Blueprint $blueprint): void {
            $blueprint->id();
            $blueprint->string('slug')->unique();
            $blueprint->string('name')->nullable();
            $blueprint->foreignId('slug_first_id')->constrained('slug_firsts');
            $blueprint->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('slug_seconds');
    }
};
