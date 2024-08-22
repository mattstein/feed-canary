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
        Schema::create('feeds', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('url', 512);
            $table->string('type');
            $table->string('email');
            $table->boolean('confirmed')->default(false);
            $table->timestamp('last_checked')->nullable();
            $table->timestamps();
        });

        Schema::create('checks', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('feed_id')
                ->references('id')
                ->on('feeds')
                ->onDelete('cascade');
            $table->string('status')->nullable();
            $table->text('headers')->nullable();
            $table->boolean('is_valid')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('feeds');
        Schema::dropIfExists('checks');
    }
};
