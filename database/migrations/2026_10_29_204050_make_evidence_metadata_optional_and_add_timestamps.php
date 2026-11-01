<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('evidences', function (Blueprint $table): void {
            $table->decimal('latitude', 10, 7)->nullable()->change();
            $table->decimal('longitude', 10, 7)->nullable()->change();
            $table->timestamp('captured_at')->nullable()->change();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });

        DB::table('evidences')->whereNull('created_at')->update([
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $hasMissingMetadata = DB::table('evidences')
            ->whereNull('latitude')
            ->orWhereNull('longitude')
            ->orWhereNull('captured_at')
            ->exists();

        Schema::table('evidences', function (Blueprint $table): void {
            $table->dropColumn(['created_at', 'updated_at']);
        });

        if (! $hasMissingMetadata) {
            Schema::table('evidences', function (Blueprint $table): void {
                $table->decimal('latitude', 10, 7)->nullable(false)->change();
                $table->decimal('longitude', 10, 7)->nullable(false)->change();
                $table->timestamp('captured_at')->nullable(false)->change();
            });
        }
    }
};
