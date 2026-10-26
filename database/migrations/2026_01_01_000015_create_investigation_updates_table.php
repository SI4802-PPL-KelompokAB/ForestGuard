<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('investigation_updates', function (Blueprint $table) {
            $table->id();
            $table->uuid('client_uuid')->unique();
            $table->foreignId('incident_id')->constrained('incidents')->restrictOnDelete();
            $table->foreignId('ranger_id')->constrained('users')->restrictOnDelete();
            $table->text('note');
            $table->foreignId('handling_status_id')->constrained('case_statuses')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('investigation_updates');
    }
};
