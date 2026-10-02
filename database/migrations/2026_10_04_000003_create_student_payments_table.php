<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Module 2.3 — every payment (GPay/UPI, offline; online gateways later)
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institute_id')->constrained('institutes')->restrictOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('student_due_id')->nullable()->constrained('student_dues')->nullOnDelete();
            $table->foreignId('payment_gateway_id')->constrained('payment_gateways')->restrictOnDelete();
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('INR');
            $table->string('gateway_order_id')->nullable();
            $table->string('gateway_payment_id')->nullable()->unique();
            $table->string('transaction_reference')->nullable(); // UTR / UPI ref, cheque no., bank ref
            $table->string('payer_upi_id')->nullable();
            $table->string('proof_file_path')->nullable(); // private disk
            $table->enum('status', ['initiated', 'pending_verification', 'success', 'failed', 'refunded'])->default('pending_verification');
            $table->date('paid_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->string('receipt_number')->nullable()->unique();
            $table->json('gateway_response')->nullable();
            $table->decimal('refund_amount', 10, 2)->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->text('remarks')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();
            $table->index(['institute_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_payments');
    }
};
