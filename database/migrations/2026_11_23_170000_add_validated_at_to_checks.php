<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `validated_at` records when a validator last produced a verdict for the
     * content, so a verdict reused across unchanged checks can expire.
     */
    public function up(): void
    {
        Schema::table('checks', function (Blueprint $table) {
            $table->timestamp('validated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('checks', function (Blueprint $table) {
            $table->dropColumn('validated_at');
        });
    }
};
