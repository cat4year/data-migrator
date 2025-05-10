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
        Schema::create('slug_threes', static function (Blueprint $table): void {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name')->nullable();
            $table->foreignId('slug_second_id')->nullable()->constrained('slug_seconds');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('slug_threes');
    }
};
