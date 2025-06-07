<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('slug_firsts', static function (Blueprint $blueprint): void {
            $blueprint->id();
            $blueprint->string('slug')->unique();
            $blueprint->boolean('bool_test')->default(false);
            $blueprint->timestamp('timestamp_test')->nullable();
            $blueprint->string('string_test')->nullable();
            $blueprint->integer('int_test')->nullable();
            $blueprint->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('slug_firsts');
    }
};
