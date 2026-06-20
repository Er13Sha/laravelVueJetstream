<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('properties', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->string('title_normalized');
            $table->string('image')->nullable();
            $table->string('preview')->nullable();
            $table->unsignedTinyInteger('rooms');
            $table->unsignedSmallInteger('floor');
            $table->decimal('area', 8, 2);
            $table->text('description');
            $table->timestamps();
            $table->index('title_normalized');
            $table->index('area');
            $table->index('floor');
            $table->index(['rooms', 'area']);
        });
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');
            DB::statement(
                'CREATE INDEX properties_title_normalized_trgm_idx '
                .'ON properties USING gin (title_normalized gin_trgm_ops)'
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('properties');
    }
};
