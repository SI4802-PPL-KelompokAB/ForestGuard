<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checkpoints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('forest_area_id')->constrained('forest_areas')->restrictOnDelete();
            $table->string('code')->unique();
            $table->string('name');
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checkpoints');
    }
};
