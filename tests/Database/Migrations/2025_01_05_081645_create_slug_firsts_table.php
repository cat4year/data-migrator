<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('slug_firsts', static function (Blueprint $table): void {
            $table->id();
            $table->string('slug')->unique();
            $table->boolean('bool_test')->default(false);
            $table->timestamp('timestamp_test')->nullable();
            $table->string('string_test')->nullable();
            $table->integer('int_test')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('slug_firsts');
    }
};
