<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('institutes', function (Blueprint $table) {

            // Rename columns
            $table->renameColumn('country', 'country_id');
            $table->renameColumn('state', 'state_id');
        });

        Schema::table('institutes', function (Blueprint $table) {

            // Change type to foreignId (optional but recommended)
            $table->unsignedBigInteger('country_id')->nullable()->change();
            $table->unsignedBigInteger('state_id')->nullable()->change();

            // Add foreign keys (if you have tables)
            $table->foreign('country_id')->references('id')->on('countries')->nullOnDelete();
            $table->foreign('state_id')->references('id')->on('states')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('institutes', function (Blueprint $table) {

            // Drop foreign keys first
            $table->dropForeign(['country_id']);
            $table->dropForeign(['state_id']);

            // Rename back
            $table->renameColumn('country_id', 'country');
            $table->renameColumn('state_id', 'state');

            // Change back to string
            $table->string('country')->nullable()->change();
            $table->string('state')->nullable()->change();
        });
    }
};
