<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('case_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->unsignedInteger('sequence')->index();
            $table->boolean('is_terminal')->default(false)->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('case_statuses');
    }
};
