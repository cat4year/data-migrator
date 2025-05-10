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
        Schema::table('slug_firsts', static function (Blueprint $table): void {
            $table->foreignId('slug_three_id')
                ->nullable()
                ->constrained('slug_threes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('slug_firsts', static function (Blueprint $table): void {
            $table->dropColumn('slug_three_id');
        });
    }
};
