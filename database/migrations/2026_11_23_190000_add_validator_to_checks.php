<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `validator` records which stage gave the verdict (local, w3c, rssboard,
     * laminas, jsonschema), so fallback rates can be charted. `null` means no
     * verdict, or a check from before this column existed.
     */
    public function up(): void
    {
        Schema::table('checks', function (Blueprint $table) {
            $table->string('validator', 20)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('checks', function (Blueprint $table) {
            $table->dropColumn('validator');
        });
    }
};
