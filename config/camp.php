<?php

/*
|--------------------------------------------------------------------------
| CAMP — business rules and role definitions
|--------------------------------------------------------------------------
| Values used by services; never hard-code these in components.
*/

return [

    // Role slug => label. Order is the display order in forms.
    'roles' => [
        'super-admin' => 'Super Admin',
        'institute-admin' => 'Institute Admin',
        'accounts' => 'Accounts',
        'training-manager' => 'Training Manager (TM)',
        'bic' => 'Base In-Charge (BiC)',
        'examination-manager' => 'Examination Manager (EM)',
        'hot' => 'Head of Training (HoT)',
        'faculty' => 'Faculty',
        'student' => 'Student',
    ],

    // Roles that belong to one institute (everything except super-admin).
    'institute_roles' => [
        'institute-admin', 'accounts', 'training-manager', 'bic', 'examination-manager', 'hot', 'faculty', 'student',
    ],

    // Who may create users with which roles (students are created by the onboarding module).
    'assignable_roles' => [
        'super-admin' => [
            'super-admin', 'institute-admin', 'accounts', 'training-manager', 'bic', 'examination-manager', 'hot', 'faculty',
        ],
        'institute-admin' => [
            'accounts', 'training-manager', 'bic', 'examination-manager', 'hot', 'faculty',
        ],
    ],

    // Permission groups shown in the Roles & Permissions matrix.
    'permissions' => [
        'Institutes' => [
            'institutes.manage' => 'Manage institutes',
        ],
        'Master Data' => [
            'masters.manage' => 'Manage master data (Super Admin only)',
        ],
        'Institute Courses' => [
            'institute_courses.view' => 'View courses offered by the institute',
            'institute_courses.manage' => 'Assign / activate / remove institute courses',
            'batches.manage' => 'Manage batches of institute courses (code, dates, capacity)',
        ],
        'Students' => [
            'students.view' => 'View students',
            'students.create' => 'Add students (admin onboarding)',
            'students.update' => 'Edit students',
            'students.delete' => 'Delete draft students',
        ],
        'Onboarding' => [
            'onboarding.verify_documents' => 'Verify KYC documents & approve Gate 1 (Admin)',
            'enrollment.manage' => 'ER request form & ID card (print, TM signed, archive, issue)',
        ],
        'Fees & Payments' => [
            'fees.manage' => 'Fee structure & student dues (add, generate, waive)',
            'payments.collect' => 'Record student payments',
            'payments.verify' => 'Verify payments, issue receipts & approve Gate 2 (Accounts)',
        ],
        'Users' => [
            'users.view' => 'View users',
            'users.create' => 'Create users',
            'users.update' => 'Edit users',
            'users.delete' => 'Delete users',
        ],
        'Roles' => [
            'roles.manage' => 'Manage roles & permissions',
        ],
        'Audit Trail' => [
            'audit.view' => 'View audit trail',
        ],
        'Notifications' => [
            'notifications.view' => 'View notification log',
        ],
        'Serial Numbers' => [
            'serials.manage' => 'Manage serial number series',
        ],
    ],

    // Never granted to other roles; hidden from the Roles & Permissions matrix.
    'super_admin_only_permissions' => ['masters.manage'],

    // Default permissions per role (super-admin gets everything through Gate::before).
    'default_role_permissions' => [
        'institute-admin' => ['users.view', 'users.create', 'users.update', 'users.delete', 'audit.view', 'notifications.view', 'institute_courses.view', 'institute_courses.manage', 'batches.manage', 'students.view', 'students.create', 'students.update', 'students.delete', 'onboarding.verify_documents', 'enrollment.manage', 'fees.manage', 'payments.collect'],
        'accounts' => ['students.view', 'fees.manage', 'payments.collect', 'payments.verify'],
        'training-manager' => ['students.view', 'enrollment.manage'],
        'bic' => [],
        'examination-manager' => [],
        'hot' => [],
        'faculty' => [],
        'student' => [],
    ],

    // Serial number series used by SerialNumberService.
    // Placeholders: {institute_code}, {year}. 'per_institute' = separate counter per institute.
    'serial_series' => [
        // One group-wide running series (as in the Module 2 design): ER-2026-00142, RCPT/2026/00318.
        'ER' => ['format' => 'ER-{year}-', 'pad' => 5, 'per_institute' => false],
        'RECEIPT' => ['format' => 'RCPT/{year}/', 'pad' => 5, 'per_institute' => false],
        'ADMIT_CARD' => ['format' => 'AC-{institute_code}-{year}-', 'pad' => 5, 'per_institute' => true],
        'MARKSHEET' => ['format' => 'MS-{year}-', 'pad' => 6, 'per_institute' => false],
        'CONSOLIDATED_MARKSHEET' => ['format' => 'CMS-{year}-', 'pad' => 6, 'per_institute' => false],
    ],

    // Master Data fixed lists (not database tables) — see plan.md Module 1A.
    'payment_gateway_types' => [
        'online' => 'Online gateway (card / netbanking)',
        'upi' => 'UPI (GPay / QR)',
        'offline' => 'Offline (cash / bank / cheque)',
    ],
    'higher_secondary_subjects' => ['PCM' => 'PCM', 'PCB' => 'PCB', 'COMMERCE' => 'Commerce', 'ARTS' => 'Arts'],
    'mark_types' => ['percentage' => 'Percentage (%)', 'cgpa' => 'CGPA'],

    // Module 2 — Student onboarding
    'genders' => ['male' => 'Male', 'female' => 'Female', 'other' => 'Other'],

    // status => [label, badge colour]
    'student_statuses' => [
        'draft' => ['Draft', 'secondary'],
        'pending_docs' => ['Pending Documents', 'warning'],
        'pending_approval' => ['Pending Approval', 'info'],
        'er_issued' => ['ER Issued', 'primary'],
        'active' => ['Active', 'success'],
        'alumni' => ['Alumni', 'dark'],
        'rejected' => ['Rejected', 'danger'],
    ],

    // KYC documents: type => [label, required, mimes, max KB]
    'student_document_types' => [
        'kyc_photo' => ['Passport Size Photo', true, 'jpg,jpeg,png', 2048],
        'medical_certificate' => ['Medical Certificate', true, 'pdf,jpg,jpeg,png', 5120],
        'marksheet_10' => ['10th Marksheet', true, 'pdf,jpg,jpeg,png', 5120],
        'marksheet_12' => ['12th Marksheet', true, 'pdf,jpg,jpeg,png', 5120],
        'other' => ['Other Document', false, 'pdf,jpg,jpeg,png', 5120],
    ],

    // Dual gate — gate => [label, short label]
    'enrollment_gates' => [
        'admin_doc_verification' => ['Admin document verification', 'Gate 1'],
        'accounts_fee_verification' => ['Accounts fee verification', 'Gate 2'],
    ],

    // Fees & payments — status => [label, badge colour]
    'due_statuses' => [
        'pending' => ['Pending', 'warning'],
        'partial' => ['Part paid', 'info'],
        'cleared' => ['Paid', 'success'],
        'waived' => ['Waived', 'secondary'],
    ],
    'payment_statuses' => [
        'initiated' => ['Initiated', 'secondary'],
        'pending_verification' => ['Pending verification', 'info'],
        'success' => ['Approved', 'success'],
        'failed' => ['Rejected', 'danger'],
        'refunded' => ['Refunded', 'dark'],
    ],
    // Payment proof upload (GPay screenshot, bank slip)
    'payment_proof_mimes' => 'jpg,jpeg,png,pdf',
    'payment_proof_max_kb' => 5120,

    // Business rules (see plan.md).
    'pass_percentage' => 75,
    'attendance_threshold' => 75,
    'onboarding_days' => 30,
    'deadline_warning_days' => 7, // students list: deadline shown in dark yellow this many days before it
    'mou_alert_days' => 60,
    'seven_day_rule_days' => 7,
];
