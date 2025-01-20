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
        Schema::create('connection_failures', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('feed_id')
                ->references('id')
                ->on('feeds')
                ->onDelete('cascade');
            $table->text('url');
            $table->text('message');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('connection_failures');
    }
};
