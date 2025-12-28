<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = config('data-migrations.table', 'data_migrations');

        Schema::create($tableName, function (Blueprint $table) {
            $table->id();
            $table->string('migration');
            $table->integer('batch');
            $table->enum('status', ['pending', 'running', 'completed', 'failed', 'rolled_back'])->default('pending');
            $table->unsignedInteger('rows_affected')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->text('error_message')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique('migration');
            $table->index(['batch', 'status']);
        });
    }

    public function down(): void
    {
        $tableName = config('data-migrations.table', 'data_migrations');
        Schema::dropIfExists($tableName);
    }
};
