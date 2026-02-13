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
        Schema::create('share_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('share_id')->constrained()->cascadeOnDelete();
            $table->string('original_name');
            $table->string('relative_path')->nullable();
            $table->string('stored_path');
            $table->unsignedBigInteger('file_size')->default(0);
            $table->string('mime_type')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('share_files');
    }
};
