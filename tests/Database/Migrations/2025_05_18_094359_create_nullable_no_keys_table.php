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
        Schema::create('nullable_no_keys', static function (Blueprint $blueprint): void {
            $blueprint->id();
            $blueprint->string('first_name');
            $blueprint->string('last_name');
            $blueprint->date('birthday')->nullable();
            $blueprint->unique(['first_name', 'last_name', 'birthday']);
            $blueprint->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('nullable_no_keys');
    }
};
