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
|
| Sections: Main (Dashboard, Master Data) · Institute · Admissions · Academics ·
| Documents & Compliance · Administration · My Portal. New modules go into the matching section as a group.
*/

$staff = ['institute-admin', 'accounts', 'training-manager', 'bic', 'examination-manager', 'hot', 'faculty'];

return [

    [
        'title' => 'Main',
        'items' => [
            ['label' => 'Dashboard', 'icon' => 'ti ti-layout-dashboard', 'route' => 'admin.dashboard'],
            [
                // Module 1A — multi-level: Master Data ▸ group ▸ list (Super Admin only)
                'label' => 'Master Data',
                'icon' => 'ti ti-database',
                'children' => [
                    [
                        'label' => 'Academic',
                        'icon' => 'ti ti-school',
                        'children' => [
                            ['label' => 'Academic Years', 'icon' => 'ti ti-calendar-stats', 'route' => 'admin.masters.academic-years', 'permission' => 'masters.manage', 'roles' => ['super-admin']],
                            ['label' => 'Courses', 'icon' => 'ti ti-books', 'route' => 'admin.masters.courses', 'permission' => 'masters.manage', 'roles' => ['super-admin']],
                            ['label' => 'Qualifications', 'icon' => 'ti ti-certificate-2', 'route' => 'admin.masters.qualifications', 'permission' => 'masters.manage', 'roles' => ['super-admin']],
                            ['label' => 'Matriculation Boards', 'icon' => 'ti ti-building-bank', 'route' => 'admin.masters.matriculation-boards', 'permission' => 'masters.manage', 'roles' => ['super-admin']],
                            ['label' => 'Higher Secondary Boards', 'icon' => 'ti ti-building-bank', 'route' => 'admin.masters.higher-secondary-boards', 'permission' => 'masters.manage', 'roles' => ['super-admin']],
                        ],
                    ],
                    [
                        'label' => 'Personal',
                        'icon' => 'ti ti-user-heart',
                        'children' => [
                            ['label' => 'Religions', 'icon' => 'ti ti-users', 'route' => 'admin.masters.religions', 'permission' => 'masters.manage', 'roles' => ['super-admin']],
                            ['label' => 'Categories', 'icon' => 'ti ti-category', 'route' => 'admin.masters.categories', 'permission' => 'masters.manage', 'roles' => ['super-admin']],
                        ],
                    ],
                    [
                        'label' => 'Location',
                        'icon' => 'ti ti-map-2',
                        'children' => [
                            ['label' => 'Countries', 'icon' => 'ti ti-world', 'route' => 'admin.masters.countries', 'permission' => 'masters.manage', 'roles' => ['super-admin']],
                            ['label' => 'States', 'icon' => 'ti ti-map-pin', 'route' => 'admin.masters.states', 'permission' => 'masters.manage', 'roles' => ['super-admin']],
                        ],
                    ],
                    [
                        'label' => 'Finance',
                        'icon' => 'ti ti-cash',
                        'children' => [
                            ['label' => 'Payment Gateways', 'icon' => 'ti ti-credit-card', 'route' => 'admin.masters.payment-gateways', 'permission' => 'masters.manage', 'roles' => ['super-admin']],
                        ],
                    ],
                ],
            ],
        ],
    ],

    // ── Institute: set-up per institute (courses, fees, payment methods) ──
    [
        'title' => 'Institute',
        'items' => [
            [
                'label' => 'Institute Management',
                'icon' => 'ti ti-building',
                'children' => [
                    ['label' => 'Institutes', 'icon' => 'ti ti-building-community', 'route' => 'admin.institutes', 'permission' => 'institutes.manage', 'roles' => ['super-admin'], 'active' => ['admin.institutes', 'admin.institute-users*']],
                    ['label' => 'Institute Courses', 'icon' => 'ti ti-books', 'route' => 'admin.institute-courses', 'permission' => 'institute_courses.view', 'active' => ['admin.institute-courses*']],
                    ['label' => 'Batches', 'icon' => 'ti ti-users-group', 'route' => 'admin.batches', 'permission' => 'batches.manage'],
                    ['label' => 'Academic Periods', 'icon' => 'ti ti-calendar-time', 'route' => 'admin.academic-periods', 'permission' => 'periods.manage'],
                    ['label' => 'Fee Structure', 'icon' => 'ti ti-receipt-2', 'route' => 'admin.onboarding.fee-structure', 'permission' => 'fees.manage'],
                    ['label' => 'Payment Settings', 'icon' => 'ti ti-qrcode', 'route' => 'admin.institute-payment-settings', 'permission' => 'fees.manage'],
                ],
            ],
        ],
    ],

    // ── Admissions (Module 2): students and the onboarding queues ──
    [
        'title' => 'Admissions',
        'items' => [
            [
                'label' => 'Students',
                'icon' => 'ti ti-school',
                'children' => [
                    ['label' => 'All Students', 'icon' => 'ti ti-list', 'route' => 'admin.students', 'permission' => 'students.view',
                        'active' => ['admin.students', 'admin.students.edit', 'admin.students.fees', 'admin.students.enrollment']],
                    ['label' => 'Add Student', 'icon' => 'ti ti-user-plus', 'route' => 'admin.students.create', 'permission' => 'students.create'],
                ],
            ],
            [
                // Gate 1 (Admin) → Gate 2 (Accounts) → ER & ID card (TM / Admin)
                'label' => 'Onboarding',
                'icon' => 'ti ti-progress-check',
                'children' => [
                    ['label' => 'Document Verification', 'icon' => 'ti ti-file-check', 'route' => 'admin.onboarding.documents', 'permission' => 'onboarding.verify_documents'],
                    ['label' => 'Payment Verification', 'icon' => 'ti ti-cash', 'route' => 'admin.onboarding.payments', 'permission' => 'payments.verify'],
                    ['label' => 'ER & ID Cards', 'icon' => 'ti ti-id-badge-2', 'route' => 'admin.onboarding.enrollment', 'permission' => 'enrollment.manage',
                        'active' => ['admin.onboarding.enrollment', 'admin.onboarding.enrollment.student']],
                ],
            ],
        ],
    ],

    // ── Academics (Modules 3–5): items appear automatically once their routes exist ──
    [
        'title' => 'Academics',
        'items' => [
            [
                'label' => 'Examinations',
                'icon' => 'ti ti-calendar-event',
                'children' => [
                    ['label' => 'Programs & Subjects', 'icon' => 'ti ti-books', 'route' => 'admin.programs', 'roles' => ['super-admin', 'institute-admin']],
                    ['label' => 'Syllabus Mapping', 'icon' => 'ti ti-list-check', 'route' => 'admin.syllabus-mapping', 'roles' => ['super-admin']],
                    ['label' => 'Exam Schedule', 'icon' => 'ti ti-calendar-event', 'route' => 'admin.exams', 'roles' => ['super-admin', 'institute-admin', 'examination-manager']],
                    ['label' => 'Exam Applications', 'icon' => 'ti ti-clipboard-list', 'route' => 'admin.exam-applications', 'roles' => ['institute-admin', 'accounts', 'training-manager', 'bic']],
                    ['label' => 'Attendance', 'icon' => 'ti ti-calendar-check', 'route' => 'admin.attendance', 'roles' => ['faculty', 'training-manager']],
                    ['label' => 'Admit Cards', 'icon' => 'ti ti-ticket', 'route' => 'admin.admit-cards', 'roles' => ['institute-admin', 'training-manager', 'examination-manager']],
                ],
            ],
            [
                'label' => 'Question Bank',
                'icon' => 'ti ti-help-hexagon',
                'children' => [
                    ['label' => 'Questions', 'icon' => 'ti ti-help-hexagon', 'route' => 'admin.question-bank', 'roles' => ['super-admin', 'institute-admin', 'examination-manager']],
                    ['label' => 'Exam Papers', 'icon' => 'ti ti-file-text', 'route' => 'admin.exam-papers', 'roles' => ['super-admin', 'institute-admin', 'examination-manager']],
                ],
            ],
            [
                'label' => 'Grading & Results',
                'icon' => 'ti ti-chart-bar',
                'children' => [
                    ['label' => 'Marks Entry', 'icon' => 'ti ti-pencil', 'route' => 'admin.marks', 'roles' => ['institute-admin', 'faculty', 'examination-manager']],
                    ['label' => 'Results', 'icon' => 'ti ti-chart-bar', 'route' => 'admin.results', 'roles' => ['super-admin', 'institute-admin', 'examination-manager']],
                    ['label' => 'Mark Corrections', 'icon' => 'ti ti-edit-circle', 'route' => 'admin.mark-corrections', 'roles' => ['super-admin']],
                    ['label' => 'Marksheets', 'icon' => 'ti ti-certificate', 'route' => 'admin.marksheets', 'roles' => ['super-admin', 'institute-admin', 'training-manager', 'examination-manager']],
                ],
            ],
        ],
    ],

    // ── Documents & Compliance (Modules 6–8) ──
    [
        'title' => 'Documents & Compliance',
        'items' => [
            [
                'label' => 'Documents (DMS)',
                'icon' => 'ti ti-folder',
                'children' => [
                    ['label' => 'Documents', 'icon' => 'ti ti-folder', 'route' => 'admin.documents', 'roles' => ['super-admin', 'institute-admin', 'hot']],
                    ['label' => 'HoT Review Queue', 'icon' => 'ti ti-checklist', 'route' => 'admin.documents.review', 'roles' => ['hot']],
                    ['label' => 'Regulatory Submissions', 'icon' => 'ti ti-send', 'route' => 'admin.documents.submissions', 'roles' => ['super-admin', 'hot']],
                    ['label' => 'Document Repository', 'icon' => 'ti ti-archive', 'route' => 'admin.documents.repository', 'roles' => array_merge(['super-admin'], $staff)],
                ],
            ],
            ['label' => 'MoU Register', 'icon' => 'ti ti-file-certificate', 'route' => 'admin.mous', 'roles' => ['super-admin', 'institute-admin', 'hot']],
            ['label' => 'Compliance Dashboard', 'icon' => 'ti ti-shield-check', 'route' => 'admin.compliance', 'roles' => ['super-admin']],
        ],
    ],

    // ── Administration: users, master data, system settings, monitoring ──
    [
        'title' => 'Administration',
        'items' => [
            [
                'label' => 'Users & Permissions',
                'icon' => 'ti ti-users-group',
                'children' => [
                    ['label' => 'Users', 'icon' => 'ti ti-users', 'route' => 'admin.users', 'permission' => 'users.view', 'active' => ['admin.users*']],
                    ['label' => 'Roles & Permissions', 'icon' => 'ti ti-shield-lock', 'route' => 'admin.roles', 'permission' => 'roles.manage', 'roles' => ['super-admin']],
                ],
            ],
            [
                'label' => 'System',
                'icon' => 'ti ti-settings',
                'children' => [
                    ['label' => 'Serial Numbers', 'icon' => 'ti ti-123', 'route' => 'admin.serials', 'permission' => 'serials.manage', 'roles' => ['super-admin']],
                ],
            ],
            [
                'label' => 'Monitoring',
                'icon' => 'ti ti-activity',
                'children' => [
                    ['label' => 'Audit Trail', 'icon' => 'ti ti-history', 'route' => 'admin.audit-trail', 'permission' => 'audit.view'],
                    ['label' => 'Notification Log', 'icon' => 'ti ti-bell', 'route' => 'admin.notifications', 'permission' => 'notifications.view'],
                ],
            ],
        ],
    ],

    // ── Student self-service ──
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
