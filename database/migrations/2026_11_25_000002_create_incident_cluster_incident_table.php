<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incident_cluster_incident', function (Blueprint $table) {
            $table->foreignId('incident_cluster_id')->constrained()->cascadeOnDelete();
            $table->foreignId('incident_id')->constrained()->cascadeOnDelete();
            $table->primary(['incident_cluster_id', 'incident_id']);
            $table->unique('incident_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incident_cluster_incident');
    }
};
