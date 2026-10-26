<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incidents', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->uuid('client_uuid')->unique();
            $table->foreignId('reporter_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('incident_type_id')->constrained('incident_types')->restrictOnDelete();
            $table->foreignId('forest_area_id')->constrained('forest_areas')->restrictOnDelete();
            $table->foreignId('patrol_id')->nullable()->constrained('patrols')->restrictOnDelete();
            $table->foreignId('risk_level_id')->nullable()->constrained('risk_levels')->restrictOnDelete();
            $table->foreignId('case_status_id')->constrained('case_statuses')->restrictOnDelete();
            $table->text('description');
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->decimal('gps_accuracy', 8, 2)->nullable();
            $table->timestamp('occurred_at')->index();
            $table->text('reject_reason')->nullable();
            $table->text('resolution_note')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incidents');
    }
};
