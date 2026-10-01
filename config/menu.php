<?php

/*
|--------------------------------------------------------------------------
| Side menu — role matrix (plan.md Section 4)
|--------------------------------------------------------------------------
| An item is shown when ALL of these hold:
|   - its route exists (items for modules not built yet stay hidden automatically)
|   - 'permission' (if set) passes $user->can()  (super-admin passes every permission)
|   - 'roles' (if set) contains one of the user's roles
| 'active' are route-name patterns that highlight the item.
| An item with 'children' (and no 'route') is a collapsible group; it is shown
| when at least one child is visible, and opens when a child is active.
*/

$staff = ['institute-admin', 'accounts', 'training-manager', 'bic', 'examination-manager', 'hot', 'faculty'];

return [

    [
        'title' => 'Main',
        'items' => [
            ['label' => 'Dashboard', 'icon' => 'ti ti-layout-dashboard', 'route' => 'admin.dashboard'],
        ],
    ],

    // Module 1 — Core / Foundation
    [
        'title' => 'Institute Management',
        'items' => [
            ['label' => 'Institutes', 'icon' => 'ti ti-building-community', 'route' => 'admin.institutes', 'permission' => 'institutes.manage', 'roles' => ['super-admin'], 'active' => ['admin.institutes*', 'admin.institute-users*']],
        ],
    ],
    [
        'title' => 'User Management',
        'items' => [
            // Collapsible group: shown when at least one child is visible.
            [
                'label' => 'Users & Permissions',
                'icon' => 'ti ti-users-group',
                'children' => [
                    ['label' => 'Users', 'icon' => 'ti ti-users', 'route' => 'admin.users', 'permission' => 'users.view', 'active' => ['admin.users*']],
                    ['label' => 'Roles & Permissions', 'icon' => 'ti ti-shield-lock', 'route' => 'admin.roles', 'permission' => 'roles.manage', 'roles' => ['super-admin']],
                ],
            ],
        ],
    ],
    [
        'title' => 'System',
        'items' => [
            ['label' => 'Serial Numbers', 'icon' => 'ti ti-123', 'route' => 'admin.serials', 'permission' => 'serials.manage', 'roles' => ['super-admin']],
        ],
    ],

    // Module 2 — Student Onboarding & ER Number
    [
        'title' => 'Student Onboarding',
        'items' => [
            ['label' => 'Students', 'icon' => 'ti ti-school', 'route' => 'admin.students', 'roles' => ['super-admin', 'institute-admin', 'accounts', 'training-manager']],
            ['label' => 'Document Verification', 'icon' => 'ti ti-file-check', 'route' => 'admin.onboarding.documents', 'roles' => ['institute-admin']],
            ['label' => 'Fee Verification', 'icon' => 'ti ti-cash', 'route' => 'admin.onboarding.fees', 'roles' => ['accounts']],
            ['label' => 'ER Requests', 'icon' => 'ti ti-id', 'route' => 'admin.er-requests', 'roles' => ['super-admin', 'training-manager']],
            ['label' => 'ID Cards', 'icon' => 'ti ti-id-badge-2', 'route' => 'admin.id-cards', 'roles' => ['institute-admin', 'training-manager']],
        ],
    ],

    // Module 3 — Examination Lifecycle
    [
        'title' => 'Examinations',
        'items' => [
            ['label' => 'Programs & Subjects', 'icon' => 'ti ti-books', 'route' => 'admin.programs', 'roles' => ['super-admin', 'institute-admin']],
            ['label' => 'Syllabus Mapping', 'icon' => 'ti ti-list-check', 'route' => 'admin.syllabus-mapping', 'roles' => ['super-admin']],
            ['label' => 'Exam Schedule', 'icon' => 'ti ti-calendar-event', 'route' => 'admin.exams', 'roles' => ['super-admin', 'institute-admin', 'examination-manager']],
            ['label' => 'Exam Applications', 'icon' => 'ti ti-clipboard-list', 'route' => 'admin.exam-applications', 'roles' => ['institute-admin', 'accounts', 'training-manager', 'bic']],
            ['label' => 'Attendance', 'icon' => 'ti ti-calendar-check', 'route' => 'admin.attendance', 'roles' => ['faculty', 'training-manager']],
            ['label' => 'Admit Cards', 'icon' => 'ti ti-ticket', 'route' => 'admin.admit-cards', 'roles' => ['institute-admin', 'training-manager', 'examination-manager']],
        ],
    ],

    // Module 4 — Question Bank
    [
        'title' => 'Question Bank',
        'items' => [
            ['label' => 'Question Bank', 'icon' => 'ti ti-help-hexagon', 'route' => 'admin.question-bank', 'roles' => ['super-admin', 'institute-admin', 'examination-manager']],
            ['label' => 'Exam Papers', 'icon' => 'ti ti-file-text', 'route' => 'admin.exam-papers', 'roles' => ['super-admin', 'institute-admin', 'examination-manager']],
        ],
    ],

    // Module 5 — Grading & Results
    [
        'title' => 'Grading & Results',
        'items' => [
            ['label' => 'Marks Entry', 'icon' => 'ti ti-pencil', 'route' => 'admin.marks', 'roles' => ['institute-admin', 'faculty', 'examination-manager']],
            ['label' => 'Results', 'icon' => 'ti ti-chart-bar', 'route' => 'admin.results', 'roles' => ['super-admin', 'institute-admin', 'examination-manager']],
            ['label' => 'Mark Corrections', 'icon' => 'ti ti-edit-circle', 'route' => 'admin.mark-corrections', 'roles' => ['super-admin']],
            ['label' => 'Marksheets', 'icon' => 'ti ti-certificate', 'route' => 'admin.marksheets', 'roles' => ['super-admin', 'institute-admin', 'training-manager', 'examination-manager']],
        ],
    ],

    // Module 6 — Document Management System
    [
        'title' => 'Documents (DMS)',
        'items' => [
            ['label' => 'Documents', 'icon' => 'ti ti-folder', 'route' => 'admin.documents', 'roles' => ['super-admin', 'institute-admin', 'hot']],
            ['label' => 'HoT Review Queue', 'icon' => 'ti ti-checklist', 'route' => 'admin.documents.review', 'roles' => ['hot']],
            ['label' => 'Regulatory Submissions', 'icon' => 'ti ti-send', 'route' => 'admin.documents.submissions', 'roles' => ['super-admin', 'hot']],
            ['label' => 'Document Repository', 'icon' => 'ti ti-archive', 'route' => 'admin.documents.repository', 'roles' => array_merge(['super-admin'], $staff)],
        ],
    ],

    // Module 7 — MoU Tracker
    [
        'title' => 'MoU',
        'items' => [
            ['label' => 'MoU Register', 'icon' => 'ti ti-file-certificate', 'route' => 'admin.mous', 'roles' => ['super-admin', 'institute-admin', 'hot']],
        ],
    ],

    // Module 8 — Compliance
    [
        'title' => 'Compliance',
        'items' => [
            ['label' => 'Compliance Dashboard', 'icon' => 'ti ti-shield-check', 'route' => 'admin.compliance', 'roles' => ['super-admin']],
        ],
    ],

    // Module 1 — monitoring
    [
        'title' => 'Monitoring',
        'items' => [
            ['label' => 'Audit Trail', 'icon' => 'ti ti-history', 'route' => 'admin.audit-trail', 'permission' => 'audit.view'],
            ['label' => 'Notification Log', 'icon' => 'ti ti-bell', 'route' => 'admin.notifications', 'permission' => 'notifications.view'],
        ],
    ],

    // Student self-service
    [
        'title' => 'My Portal',
        'items' => [
            ['label' => 'My Profile', 'icon' => 'ti ti-user', 'route' => 'student.profile', 'roles' => ['student']],
            ['label' => 'My Documents', 'icon' => 'ti ti-files', 'route' => 'student.documents', 'roles' => ['student']],
            ['label' => 'Exam Application', 'icon' => 'ti ti-clipboard-text', 'route' => 'student.exam-application', 'roles' => ['student']],
            ['label' => 'Downloads', 'icon' => 'ti ti-download', 'route' => 'student.downloads', 'roles' => ['student']],
        ],
    ],
];
