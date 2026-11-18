<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('videos', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 16)->unique();
            $table->string('title');
            $table->string('mime')->default('video/webm');
            $table->string('extension', 8)->default('webm');
            $table->unsignedBigInteger('size')->default(0);
            $table->unsignedInteger('chunks')->default(0);
            $table->float('duration')->nullable();
            $table->unsignedInteger('views')->default(0);
            $table->boolean('has_thumbnail')->default(false);
            $table->string('status')->default('uploading'); // uploading | ready
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('videos');
    }
};
