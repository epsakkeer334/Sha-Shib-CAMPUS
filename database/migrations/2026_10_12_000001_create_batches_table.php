<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Batches per institute + course (managed by Super Admin / Institute Admin). Students choose a batch
 * when they register (portal) or are added by the office. The batch code is unique across all institutes.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::create('batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institute_id')->constrained('institutes')->restrictOnDelete();
            $table->foreignId('course_id')->constrained('courses')->restrictOnDelete();
            $table->string('name', 150);
            $table->string('code', 30)->unique();          // unique everywhere (deleted batches keep theirs)
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->unsignedInteger('capacity')->nullable(); // seats; null = no limit
            $table->boolean('status')->default(true);
            $table->text('remarks')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();
            $table->index(['institute_id', 'course_id', 'status']);
        });

        Schema::table('students', function (Blueprint $table) {
            $table->foreignId('batch_id')->nullable()->after('course_id')->constrained('batches')->nullOnDelete();
        });
    }

    public function down()
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropConstrainedForeignId('batch_id');
        });
        Schema::dropIfExists('batches');
    }
};
