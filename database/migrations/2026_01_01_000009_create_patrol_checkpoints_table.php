<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patrol_checkpoints', function (Blueprint $table) {
            $table->id();
            $table->uuid('client_uuid')->unique();
            $table->foreignId('patrol_id')->constrained('patrols')->restrictOnDelete();
            $table->foreignId('checkpoint_id')->constrained('checkpoints')->restrictOnDelete();
            $table->unsignedInteger('sequence');
            $table->timestamp('visited_at')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

            $table->unique(['patrol_id', 'checkpoint_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patrol_checkpoints');
    }
};
