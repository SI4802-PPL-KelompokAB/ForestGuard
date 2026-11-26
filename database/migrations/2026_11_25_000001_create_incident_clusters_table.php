<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incident_clusters', function (Blueprint $table) {
            $table->id();
            $table->string('fingerprint')->unique();
            $table->decimal('centroid_latitude', 10, 7);
            $table->decimal('centroid_longitude', 10, 7);
            $table->unsignedInteger('report_count')->default(0);
            $table->decimal('radius_km', 8, 3);
            $table->string('status')->default('processed')->index();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incident_clusters');
    }
};
