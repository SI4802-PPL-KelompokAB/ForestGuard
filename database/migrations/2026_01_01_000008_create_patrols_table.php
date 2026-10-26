<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patrols', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->uuid('client_uuid')->unique();
            $table->foreignId('ranger_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('forest_area_id')->constrained('forest_areas')->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('scheduled_at');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->decimal('total_distance_km', 10, 3)->nullable();
            $table->string('status')->index();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patrols');
    }
};
