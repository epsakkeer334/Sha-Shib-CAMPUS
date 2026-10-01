<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('serial_counters', function (Blueprint $table) {
            $table->id();
            $table->string('series_key'); // ER, MARKSHEET, CONSOLIDATED_MARKSHEET, ADMIT_CARD
            $table->foreignId('institute_id')->nullable()->constrained('institutes')->nullOnDelete(); // null = global series
            $table->unsignedSmallInteger('year');
            $table->unsignedBigInteger('last_value')->default(0); // incremented under row lock
            $table->string('prefix_format'); // e.g. SSG-{institute_code}-{year}-
            $table->unsignedTinyInteger('pad_length')->default(5);
            $table->timestamps();

            $table->unique(['series_key', 'institute_id', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('serial_counters');
    }
};
