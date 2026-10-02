<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Which payment methods each institute accepts, with its own UPI ID / QR / merchant account (plan.md Module 1A).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('institute_payment_gateways', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institute_id')->constrained('institutes')->cascadeOnDelete();
            $table->foreignId('payment_gateway_id')->constrained('payment_gateways')->restrictOnDelete();
            $table->string('payee_name')->nullable(); // name shown in the UPI app
            $table->string('upi_id')->nullable(); // e.g. institute@okaxis (GPay / UPI)
            $table->string('qr_code_path')->nullable(); // uploaded UPI QR image (private disk)
            $table->text('instructions')->nullable(); // e.g. bank account details, office hours
            $table->string('merchant_id')->nullable(); // online gateways (later)
            $table->text('credentials')->nullable(); // encrypted; online gateways (later)
            $table->boolean('is_test_mode')->default(true);
            $table->boolean('status')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();

            // Short name: the generated one is longer than MySQL's 64-character limit.
            $table->unique(['institute_id', 'payment_gateway_id'], 'institute_gateway_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('institute_payment_gateways');
    }
};
