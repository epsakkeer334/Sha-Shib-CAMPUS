<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Re-uploads keep the reason the previous file was rejected ("Re-uploaded · previously rejected: …").
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_documents', function (Blueprint $table) {
            $table->text('previous_rejection')->nullable()->after('remarks');
        });
    }

    public function down(): void
    {
        Schema::table('student_documents', function (Blueprint $table) {
            $table->dropColumn('previous_rejection');
        });
    }
};
