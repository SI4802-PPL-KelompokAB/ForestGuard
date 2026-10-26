<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evidences', function (Blueprint $table) {
            $table->id();
            $table->uuid('client_uuid')->unique();
            $table->foreignId('incident_id')->nullable()->constrained('incidents')->restrictOnDelete();
            $table->foreignId('observation_id')->nullable()->constrained('observations')->restrictOnDelete();
            $table->foreignId('update_id')->nullable()->constrained('investigation_updates')->restrictOnDelete();
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->string('file_path');
            $table->string('mime_type');
            $table->unsignedBigInteger('file_size');
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->timestamp('captured_at');
        });

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement('
                ALTER TABLE evidences
                ADD CONSTRAINT evidences_exactly_one_parent
                CHECK (
                    (CASE WHEN incident_id IS NOT NULL THEN 1 ELSE 0 END)
                    + (CASE WHEN observation_id IS NOT NULL THEN 1 ELSE 0 END)
                    + (CASE WHEN update_id IS NOT NULL THEN 1 ELSE 0 END)
                    = 1
                )
            ');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('evidences');
    }
};
