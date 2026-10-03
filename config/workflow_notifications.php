<?php

/*
|--------------------------------------------------------------------------
| In-app workflow notifications (bell + notifications page)
|--------------------------------------------------------------------------
| Every event the onboarding workflow raises, who gets it and how it reads.
|
|   staff    → permissions whose holders in the student's institute are notified
|              (Super Admins too when 'include_super_admin' is true). Empty = no staff.
|   student  → true: the student's portal login is notified.
|   stage    → key of 'stages' (shown as a coloured chip and used by the filter).
|   title / message use :placeholders filled by App\Services\AppNotifier
|              (:student, :document, :amount, :reason, :er, :fee, :receipt, :actor, :count, :status).
|
| The person who performed the action is never notified about their own action.
| Emails keep going through NotificationService / notifications_log as before.
*/

return [

    'include_super_admin' => true,

    'stages' => [
        'registration' => ['Registration', 'ti ti-user-plus', 'indigo'],
        'details' => ['Student details', 'ti ti-id', 'indigo'],
        'documents' => ['Documents', 'ti ti-file-text', 'amber'],
        'gate1' => ['Gate 1 · Documents', 'ti ti-shield-check', 'green'],
        'payment' => ['Payment', 'ti ti-cash', 'sky'],
        'gate2' => ['Gate 2 · Fees', 'ti ti-shield-check', 'green'],
        'enrollment' => ['ER number', 'ti ti-id-badge-2', 'green'],
        'id_card' => ['ID card', 'ti ti-id', 'green'],
    ],

    'events' => [

        // ── Registration & details ─────────────────────────────────────────────
        'registration_started' => [
            'stage' => 'registration', 'tone' => 'indigo', 'icon' => 'ti ti-user-plus',
            'staff' => ['onboarding.verify_documents'], 'student' => false,
            'title' => 'New registration started',
            'message' => ':student started an admission application on the portal.',
        ],
        'application_submitted' => [
            'stage' => 'registration', 'tone' => 'indigo', 'icon' => 'ti ti-send',
            'staff' => ['onboarding.verify_documents'], 'student' => true,
            'title' => 'Application submitted',
            'message' => 'The admission application of :student was submitted (:status).',
            'student_message' => 'Your admission application was submitted. We will review your documents shortly.',
        ],
        'details_updated' => [
            'stage' => 'details', 'tone' => 'indigo', 'icon' => 'ti ti-edit',
            'staff' => ['onboarding.verify_documents'], 'student' => false,
            'title' => 'Student details updated',
            'message' => ':student updated their :section after submitting the application.',
        ],

        // ── Documents (Gate 1) ─────────────────────────────────────────────────
        'document_uploaded' => [
            'stage' => 'documents', 'tone' => 'amber', 'icon' => 'ti ti-file-upload',
            'staff' => ['onboarding.verify_documents'], 'student' => true,
            'title' => 'Document uploaded',
            'message' => ':student uploaded :document for verification.',
            'student_message' => ':document was uploaded to your application by the admissions office.',
        ],
        'document_resubmitted' => [
            'stage' => 'documents', 'tone' => 'amber', 'icon' => 'ti ti-file-import',
            'staff' => ['onboarding.verify_documents'], 'student' => false,
            'title' => 'Rejected document re-submitted',
            'message' => ':student re-uploaded :document (previously rejected: :reason).',
        ],
        'documents_complete' => [
            'stage' => 'documents', 'tone' => 'amber', 'icon' => 'ti ti-files',
            'staff' => ['onboarding.verify_documents'], 'student' => false,
            'title' => 'All documents received',
            'message' => 'Every required document of :student is uploaded and ready for review.',
        ],
        'document_verified' => [
            'stage' => 'documents', 'tone' => 'green', 'icon' => 'ti ti-file-check',
            'staff' => [], 'student' => true,
            'title' => 'Document verified',
            'message' => 'Your :document is verified.',
        ],
        'document_rejected' => [
            'stage' => 'documents', 'tone' => 'red', 'icon' => 'ti ti-file-x',
            'staff' => [], 'student' => true,
            'title' => 'Document rejected — action needed',
            'message' => 'Your :document was rejected: :reason. Please upload a new copy.',
        ],
        'document_accepted' => [
            'stage' => 'documents', 'tone' => 'green', 'icon' => 'ti ti-rotate-clockwise',
            'staff' => [], 'student' => true,
            'title' => 'Document accepted after re-review',
            'message' => 'We checked your :document again and it is accepted. No need to upload it again.',
        ],
        'document_reopened' => [
            'stage' => 'documents', 'tone' => 'amber', 'icon' => 'ti ti-arrow-back-up',
            'staff' => [], 'student' => true,
            'title' => 'Document back under review',
            'message' => 'The rejection of your :document was withdrawn. It is being reviewed again.',
        ],
        'document_reminder' => [
            'stage' => 'documents', 'tone' => 'amber', 'icon' => 'ti ti-bell-ringing',
            'staff' => [], 'student' => true,
            'title' => 'Documents pending',
            'message' => 'Please upload: :documents.',
        ],
        'gate1_approved' => [
            'stage' => 'gate1', 'tone' => 'green', 'icon' => 'ti ti-shield-check',
            'staff' => ['payments.verify'], 'student' => true,
            'title' => 'Documents approved (Gate 1)',
            'message' => 'Documents of :student are approved — ready for fee clearance.',
            'student_message' => 'Your documents are approved. Next: fee clearance by Accounts.',
        ],
        'gate1_reopened' => [
            'stage' => 'gate1', 'tone' => 'amber', 'icon' => 'ti ti-shield-x',
            'staff' => ['onboarding.verify_documents'], 'student' => false,
            'title' => 'Gate 1 reopened',
            'message' => 'Gate 1 of :student was reopened: :reason. Please review again.',
        ],
        'application_rejected' => [
            'stage' => 'gate1', 'tone' => 'red', 'icon' => 'ti ti-alert-triangle',
            'staff' => [], 'student' => true,
            'title' => 'Application returned for corrections',
            'message' => 'Your application needs corrections: :reason. Please update it and submit again.',
        ],

        // ── Fees & payments (Gate 2) ───────────────────────────────────────────
        'fees_added' => [
            'stage' => 'payment', 'tone' => 'sky', 'icon' => 'ti ti-receipt-2',
            'staff' => [], 'student' => true,
            'title' => 'Fees added',
            'message' => ':count added to your account (total :amount). You can pay from the payment step.',
        ],
        'payment_submitted' => [
            'stage' => 'payment', 'tone' => 'sky', 'icon' => 'ti ti-cash',
            'staff' => ['payments.verify'], 'student' => true,
            'title' => 'Payment waiting for verification',
            'message' => ':student paid :amount towards :fee via :method.',
            'student_message' => 'Your payment of :amount towards :fee was recorded and is waiting for verification.',
        ],
        'payment_approved' => [
            'stage' => 'payment', 'tone' => 'green', 'icon' => 'ti ti-receipt',
            'staff' => [], 'student' => true,
            'title' => 'Payment confirmed',
            'message' => 'Your payment of :amount is confirmed. Receipt :receipt.',
        ],
        'payment_rejected' => [
            'stage' => 'payment', 'tone' => 'red', 'icon' => 'ti ti-cash-off',
            'staff' => [], 'student' => true,
            'title' => 'Payment could not be confirmed',
            'message' => 'Your payment of :amount could not be confirmed: :reason.',
        ],
        'fee_waived' => [
            'stage' => 'payment', 'tone' => 'green', 'icon' => 'ti ti-discount',
            'staff' => [], 'student' => true,
            'title' => 'Fee waived',
            'message' => ':fee was waived: :reason.',
        ],
        'gate2_approved' => [
            'stage' => 'gate2', 'tone' => 'green', 'icon' => 'ti ti-shield-check',
            'staff' => [], 'student' => true,
            'title' => 'Fees cleared (Gate 2)',
            'message' => 'Your fees are cleared.',
        ],
        'gate2_reopened' => [
            'stage' => 'gate2', 'tone' => 'amber', 'icon' => 'ti ti-shield-x',
            'staff' => ['payments.verify'], 'student' => false,
            'title' => 'Gate 2 reopened',
            'message' => 'Gate 2 of :student was reopened: :reason.',
        ],

        // ── ER number & ID card ────────────────────────────────────────────────
        'er_issued' => [
            'stage' => 'enrollment', 'tone' => 'green', 'icon' => 'ti ti-id-badge-2',
            'staff' => ['enrollment.manage'], 'student' => true,
            'title' => 'ER number issued',
            'message' => ':student got ER number :er — print the ER form and ID card.',
            'student_message' => 'Your ER number is :er. Your ID card will be ready after the Training Manager signs it.',
        ],
        'id_card_issued' => [
            'stage' => 'id_card', 'tone' => 'green', 'icon' => 'ti ti-id',
            'staff' => ['students.view'], 'student' => true,
            'title' => 'ID card issued',
            'message' => 'The ID card of :student (:er) is signed and issued — the student is now Active.',
            'student_message' => 'Your ID card is signed and issued. Welcome aboard — your admission is complete!',
        ],
        'id_card_reprint' => [
            'stage' => 'id_card', 'tone' => 'amber', 'icon' => 'ti ti-refresh',
            'staff' => [], 'student' => true,
            'title' => 'ID card reprint started',
            'message' => 'A new ID card is being prepared: :reason.',
        ],
    ],
];
