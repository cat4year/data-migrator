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
        Schema::create('composite_keys', static function (Blueprint $blueprint): void {
            $blueprint->id();
            $blueprint->string('key1');
            $blueprint->string('key2');
            $blueprint->string('key3')->nullable();
            //$blueprint->unique(['key1', 'key2', 'key3']);//todo
            $blueprint->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('composite_keys');
    }
};
