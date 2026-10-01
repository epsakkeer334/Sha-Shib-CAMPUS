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

    // Default permissions per role (super-admin gets everything through Gate::before).
    'default_role_permissions' => [
        'institute-admin' => ['users.view', 'users.create', 'users.update', 'users.delete', 'audit.view', 'notifications.view'],
        'accounts' => [],
        'training-manager' => [],
        'bic' => [],
        'examination-manager' => [],
        'hot' => [],
        'faculty' => [],
        'student' => [],
    ],

    // Serial number series used by SerialNumberService.
    // Placeholders: {institute_code}, {year}. 'per_institute' = separate counter per institute.
    'serial_series' => [
        'ER' => ['format' => 'SSG-{institute_code}-{year}-', 'pad' => 5, 'per_institute' => true],
        'ADMIT_CARD' => ['format' => 'AC-{institute_code}-{year}-', 'pad' => 5, 'per_institute' => true],
        'MARKSHEET' => ['format' => 'MS-{year}-', 'pad' => 6, 'per_institute' => false],
        'CONSOLIDATED_MARKSHEET' => ['format' => 'CMS-{year}-', 'pad' => 6, 'per_institute' => false],
    ],

    // Business rules (see plan.md).
    'pass_percentage' => 75,
    'attendance_threshold' => 75,
    'onboarding_days' => 30,
    'mou_alert_days' => 60,
    'seven_day_rule_days' => 7,
];
