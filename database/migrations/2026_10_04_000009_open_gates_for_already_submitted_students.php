<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Students submitted before the dual gate existed (Module 2.1) get their two pending gates,
 * so they appear in the Document verification / Accounts queues.
 */
return new class extends Migration
{
    public function up(): void
    {
        $students = DB::table('students')
            ->whereNull('deleted_at')
            ->whereIn('status', ['pending_docs', 'pending_approval'])
            ->get(['id', 'institute_id']);

        foreach ($students as $student) {
            foreach (['admin_doc_verification', 'accounts_fee_verification'] as $gate) {
                $exists = DB::table('enrollment_approvals')->where('student_id', $student->id)->where('gate', $gate)->exists();

                if (!$exists) {
                    DB::table('enrollment_approvals')->insert([
                        'institute_id' => $student->institute_id,
                        'student_id' => $student->id,
                        'gate' => $gate,
                        'status' => 'pending',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        // Gates are part of the workflow data; nothing to undo.
    }
};
