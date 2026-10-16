<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('project_tiles', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignUuid('project_id')->constrained('projects')->cascadeOnDelete();
            $table->string('file_path');
            $table->jsonb('avg_color')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_tiles');
    }
};
