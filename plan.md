# CAMP — Central Academic Management Portal

### Laravel 9 + Livewire 2 Development Plan (Multi-Institute)

---

## 1. Project Overview

CAMP is a multi-tenant academic and compliance management system serving 10+ institutes under the Sha Shib Group. It is **centralized for data integrity** (single source of truth for ER numbers, question banks, marksheet serials) and **decentralized for operational approvals** (institutes run their own day-to-day workflows).

Core functional pillars, drawn from your two documents:

1. **Student Onboarding & ER Number Generation**
2. **Examination Lifecycle** (Application → Approval → Admit Card → Conduct)
3. **Grading & Result Publication**
4. **Document Management System (DMS)** — MTOE/SOP/Training Manual approvals
5. **MoU (Practical Accessor) Tracking**
6. **Compliance & Audit Dashboard**

---

## 2. Tech Stack (current project — keep as is)

| Layer | Choice | Notes |
| --- | --- | --- |
| Framework | Laravel 9.x (PHP ^8.0.2) | Already installed — no upgrade in scope |
| UI | Livewire 2.12 + Alpine.js | Components live in `app/Http/Livewire/` |
| Styling | Tailwind CSS | Already configured (`tailwind.config.js`) |
| Auth | Laravel Breeze (installed) + custom Livewire admin login | `app/Http/Livewire/Admin/Auth/Login.php` |
| Roles/Permissions | `spatie/laravel-permission` (installed) | Roles: Super Admin, Institute Admin, Accounts, TM, BiC, EM, HoT, Faculty, Student |
| Multi-tenancy | Single DB, `institute_id` scoping (not separate DBs) | Simpler ops for 10+ institutes; use Laravel Global Scopes |
| PDF generation | `barryvdh/laravel-dompdf` (to install, Laravel 9 compatible) | Admit cards, marksheets, ID cards |
| File storage | Laravel Filesystem — **private disk** for KYC/medical/signed docs | Downloads only through authorized routes / temporary signed URLs |
| Notifications | Laravel Notifications (Mail + SMS gateway TBD) | ER issuance, exam approval, results, MoU expiry |
| Queues | Laravel Queue (database/Redis driver) | Notification dispatch, PDF generation |
| Audit logging | Custom `audit_trail` table + `App\Traits\RecordsAuditTrail` + `App\Models\Admin\AuditTrail` | Replaces the old `activity_logs`; no external audit package |
| Search/Filters | **Existing** `Admin\Components\Table\DataTable` Livewire component | Reuse for all list screens |
| Testing | PHPUnit + `Livewire::test()` | Approval-chain logic needs solid test coverage |

---

## 3. Architecture Notes

- **Single Laravel app, single database**, every tenant-scoped table carries `institute_id`. Use a Global Scope + trait (`BelongsToInstitute`) so every model auto-filters by the logged-in user's institute (Super Admin bypasses the scope).
- **Base model convention (existing):** all new models extend `App\Models\Admin\BaseModel`, which gives soft deletes and auto-filled `created_by` / `updated_by`. Therefore every new table includes `created_by`, `updated_by`, `deleted_at`, `timestamps` — not repeated in each table below. Restore/force-delete UI uses the existing `SoftDeleteManager` component.
- **State machine pattern** for anything that moves through approval gates (student ER request, exam appearance request, document lifecycle): a `status` enum on the record + its approvals table — this maps directly to your "Trigger → Approval → Execution" logic. Every status change is also written to `audit_trail`.
- **Serial Number Generator**: one central service class (`SerialNumberService`) backed by a `serial_counters` table (locked row per series/institute/year) so no two requests collide, even under concurrency (use `DB::transaction` + `lockForUpdate`).
- **Signature handling**: "Physical Signature" requirements (TM, EM) are modeled as a status flag + optional uploaded scan, not a digital signature workflow — matches your description of printed/physically signed documents that get archived.
- **Configurable rules**: pass percentage (75%) and attendance threshold live in `config/camp.php` (later movable to a settings table), never hard-coded in services.

---

## 4. User Roles & Permissions Matrix

| Role | Scope | Key Powers |
| --- | --- | --- |
| **Super Admin** | Global (all institutes) | Subject/syllabus mapping, question bank, result governance & corrections, serial number oversight, compliance dashboard |
| **Institute Admin** | Single institute | Exam creation, document verification (initial vetting), local reporting |
| **Accounts** | Single institute | Fee/dues verification (onboarding + exam gate) |
| **TM (Training Manager)** | Single institute | Attendance verification, ER form signature, admit card signature, marksheet signature |
| **BiC (Base In-Charge)** | Single institute | Override/bypass dues-block with recorded reason |
| **EM (Examination Manager)** | Single institute | Admit card signature, marksheet signature |
| **HoT (Head of Training)** | Single institute (or group) | DMS approvals, MoU expiry alerts, document return/approve |
| **Faculty** | Single institute | Attendance & lesson-plan uploads |
| **Student** | Self only | Profile, document upload, exam application, downloads |

Implemented with `spatie/laravel-permission` (`model_has_roles`) + `institute_id` on the `users` table (nullable for Super Admin). No `role_id` column on `users`. Role slugs and labels: `config/camp.php` → `roles`.

**Who can create whom** (`config/camp.php` → `assignable_roles`) ✅

| Creator | Can create | Institute |
| --- | --- | --- |
| Super Admin | Super Admin, Institute Admin, Accounts, TM, BiC, EM, HoT, Faculty | selects the institute (none for Super Admin) |
| Institute Admin | Accounts, TM, BiC, EM, HoT, Faculty | always their own institute (enforced server-side) |

Only Super Admin adds/edits institutes. Students are created by Module 2 (onboarding), not from the Users screen.

**Side menu** ✅ — role × menu matrix in `config/menu.php`, rendered by `App\Services\MenuService`. An item shows when its route exists and the user has the listed role/permission, so menu items for Modules 2–8 appear automatically as each module is built. Non-Super-Admin dashboards show the same items as quick links.

**Permissions** ✅ — defined in `config/camp.php` → `permissions` (Module 1: `institutes.manage`, `users.view/create/update/delete`, `roles.manage`, `audit.view`, `notifications.view`, `serials.manage`); editable per role on the Roles & Permissions screen. Super Admin passes every check (`Gate::before`).

---

## 5. Module-Wise Breakdown

Each module below lists: **purpose → Livewire components → database tables & fields**.
Common columns from `BaseModel` (`created_by`, `updated_by`, `deleted_at`, `timestamps`) are implied on every table and not listed.

### Module 1 — Core / Foundation

**Purpose:** institutes, users, roles, notifications, audit trail — shared by every other module.

**Status:** ✅ implemented (tests: `tests/Feature/Module1CoreTest.php`).

**Livewire components:** `Admin\Institutes\InstitutesComponent` ✅, `Admin\Users\UsersComponent` ✅ (all users, or one institute's users via Institutes → Users), `Admin\Roles\RolesComponent` ✅ (role × permission matrix), `Admin\AuditTrail\AuditTrailComponent` ✅, `Admin\Notifications\NotificationLogComponent` ✅

**Services / support:** `SerialNumberService` ✅, `NotificationService` ✅ (email sent + logged; SMS logged as pending until a gateway is chosen), `MenuService` ✅, `BelongsToInstitute` trait ✅ (for Module 2+ models), `EnsureUserIsActive` middleware ✅ (blocks inactive users, users without a role, and users of inactive institutes)

**Tables**

`institutes` ✅ (exists — `2026_03_25_180000_create_institutes_table`)

| Field | Type | Notes |
| --- | --- | --- |
| id | bigint PK |  |
| name | string, unique | must contain ≥ 3 letters |
| established_year | smallint | required in form, 1800 – current year |
| code | string, unique | auto-generated on create, read-only after: format `first 3 letters/year/running no.`, running no. group-wide from 1010 (e.g. `SHA/2005/1010`); used in ER/serial prefixes |
| description, about | text nullable |  |
| address, city, postal_code | string nullable |  |
| country, state | string nullable | `countries` / `states` tables exist |
| contact_person, email, phone, website | string nullable |  |
| logo, banner | string nullable |  |
| status | boolean default true |  |

`users` ✅

| Field | Type | Notes |
| --- | --- | --- |
| id | bigint PK |  |
| institute_id | bigint FK nullable | null = central/Super Admin |
| employee_code | string nullable |  |
| created_by, updated_by | bigint FK nullable |  |
| name, email | string |  |
| phone | string(15) unique nullable |  |
| password | string |  |
| user_image | string nullable |  |
| status | tinyint | 0=Inactive, 1=Active, 2=Pending, 3=Suspended |
| last_login_at | timestamp nullable |  |
| last_login_ip | string nullable |  |
| deleted_at | soft delete |  |

Roles via spatie tables (`2025_11_26_064444_create_permission_tables`).

`serial_counters` ✅ (series formats: `config/camp.php` → `serial_series`)

| Field | Type | Notes |
| --- | --- | --- |
| id | bigint PK |  |
| series_key | string | `ER`, `MARKSHEET`, `CONSOLIDATED_MARKSHEET`, `ADMIT_CARD` |
| institute_id | bigint FK nullable | null = global series |
| year | smallint |  |
| last_value | bigint | incremented under row lock |
| prefix_format | string | e.g. `SSG-{institute_code}-{year}-` |
| pad_length | tinyint | zero-padding of the number |
| unique | (series_key, institute_id, year) |  |

`audit_trail` ✅ (implemented — replaces the old `activity_logs` table; append-only, no soft deletes)

| Field | Type | Notes |
| --- | --- | --- |
| id | bigint PK |  |
| user_id | bigint FK nullable | null = system/scheduled job |
| institute_id | bigint FK nullable |  |
| action | string | e.g. `approve`, `bypass`, `correct_marks` |
| module | string |  |
| reference_type / reference_id | string / bigint | polymorphic target |
| ip_address | string |  |
| meta | json nullable | before/after values (`old`, `new`), `reason`, `description` |
| created_at | timestamp | acts as the "time-stamp" |

`notifications_log` ✅

| Field | Type | Notes |
| --- | --- | --- |
| id | bigint PK |  |
| institute_id | bigint FK nullable |  |
| notifiable_type / notifiable_id | polymorphic | user or student |
| channel | enum(sms,email) |  |
| recipient | string nullable | email / phone used |
| event_type | string | `er_issued`, `exam_approved`, `result_published`, `mou_expiry` |
| payload | json |  |
| status | enum(pending,sent,failed) |  |
| error | text nullable |  |
| sent_at | timestamp nullable |  |

---

### Module 2 — Student Onboarding & ER Number

**Purpose:** implements Phase 1 (KYC upload, dual-gate approval, ER generation, ID card).

**Livewire components:** `StudentRegistrationForm`, `DocumentUploadWizard`, `AdminDocumentVerification`, `AccountsFeeVerification`, `ERRequestGenerator`, `IDCardIssuance`

**Tables**

`students`

| Field | Type | Notes |
| --- | --- | --- |
| id | bigint PK |  |
| institute_id | bigint FK |  |
| user_id | bigint FK nullable unique | student login account (Student role) |
| program_id | bigint FK |  |
| er_number | string nullable unique | **single source of the ER number** — populated after dual approval |
| first_name, last_name | string |  |
| dob | date |  |
| gender | string |  |
| email, phone | string |  |
| address | text |  |
| parent_name, parent_phone, parent_email | string |  |
| joining_date | date | 30-day onboarding window starts here |
| onboarding_deadline | date | computed = joining_date + 30 |
| status | enum(draft,pending_docs,pending_approval,er_issued,active,alumni,rejected) |  |

`student_documents`

| Field | Type | Notes |
| --- | --- | --- |
| id | bigint PK |  |
| student_id | bigint FK |  |
| institute_id | bigint FK |  |
| document_type | enum(kyc_photo,medical_certificate,marksheet_10,marksheet_12,other) |  |
| file_path | string | private disk |
| uploaded_at | timestamp |  |
| verified_by | bigint FK nullable (users) | Admin role |
| verified_at | timestamp nullable |  |
| verification_status | enum(pending,verified,rejected) |  |
| remarks | text nullable |  |

`student_dues` (new — data source for the Accounts gate)

| Field | Type | Notes |
| --- | --- | --- |
| id | bigint PK |  |
| student_id | bigint FK |  |
| institute_id | bigint FK |  |
| fee_head | string | e.g. admission, semester fee |
| amount_due, amount_paid | decimal |  |
| due_date | date |  |
| status | enum(pending,partial,cleared) | "no dues" = all rows cleared |

`enrollment_approvals`

| Field | Type | Notes |
| --- | --- | --- |
| id | bigint PK |  |
| student_id | bigint FK |  |
| institute_id | bigint FK |  |
| gate | enum(admin_doc_verification, accounts_fee_verification) | the "dual gate" |
| approved_by | bigint FK (users) |  |
| status | enum(pending,approved,rejected) |  |
| remarks | text nullable |  |
| approved_at | timestamp nullable |  |
| unique | (student_id, gate) |  |

`er_requests`

| Field | Type | Notes |
| --- | --- | --- |
| id | bigint PK |  |
| student_id | bigint FK unique | ER number read from `students.er_number` |
| institute_id | bigint FK |  |
| request_form_path | string | auto-generated PDF |
| tm_signed_by | bigint FK nullable |  |
| tm_signature_status | enum(pending,physically_signed) |  |
| archived_at | timestamp nullable | physical file archive confirmation |
| status | enum(generated,printed,archived) |  |

`id_cards`

| Field | Type | Notes |
| --- | --- | --- |
| id | bigint PK |  |
| student_id | bigint FK | ER number read from `students.er_number` |
| institute_id | bigint FK |  |
| issue_date | date |  |
| tm_signature_status | enum(pending,physically_signed) | mandatory for validation |
| file_path | string nullable | printable template |
| status | enum(pending,issued,reprinted) |  |

---

### Module 3 — Examination Lifecycle

**Purpose:** implements Phase 2 — Trigger → Approval → Execution.

**Livewire components:** `ExamAppearanceApplication`, `AccountsDuesApproval`, `BiCOverridePanel`, `TMAttendanceApproval`, `AdmitCardGenerator`, `ExamScheduleManager`, `SyllabusMappingManager`

**Tables**

`programs`

| Field | Type | Notes |
| --- | --- | --- |
| id | bigint PK |  |
| institute_id | bigint FK |  |
| name, code | string |  |
| duration_months | int |  |
| total_semesters | int |  |

`subjects`

| Field | Type | Notes |
| --- | --- | --- |
| id | bigint PK |  |
| institute_id | bigint FK nullable | null = central/master subject (used by master question bank) |
| program_id | bigint FK nullable | null for central subjects |
| name, code | string |  |
| semester_no | int |  |
| syllabus_topics | json | list of topics used for % mapping |

`syllabus_mapping`

| Field | Type | Notes |
| --- | --- | --- |
| id | bigint PK |  |
| subject_id | bigint FK |  |
| institute_id | bigint FK |  |
| mapped_by | bigint FK (users, Super Admin only) |  |
| exam_type | enum(mid_sem,semester) |  |
| threshold_percent | int | 40 for mid-sem, 100 for semester |
| covered_percent | int | syllabus covered so far; exam can be scheduled only when covered ≥ threshold |

`exams`

| Field | Type | Notes |
| --- | --- | --- |
| id | bigint PK |  |
| institute_id | bigint FK |  |
| program_id | bigint FK |  |
| exam_type | enum(mid_sem,semester) |  |
| semester_no | int |  |
| scheduled_date | date |  |
| mode | enum(online,offline) |  |
| status | enum(scheduled,ongoing,completed,cancelled) |  |

`exam_appearance_requests`

| Field | Type | Notes |
| --- | --- | --- |
| id | bigint PK |  |
| student_id | bigint FK |  |
| exam_id | bigint FK |  |
| institute_id | bigint FK |  |
| submitted_at | timestamp |  |
| status | enum(submitted,accounts_review,tm_review,approved,rejected) |  |
| unique | (student_id, exam_id) |  |

`exam_appearance_subjects` (pivot)

| Field | Type | Notes |
| --- | --- | --- |
| id | bigint PK |  |
| exam_appearance_request_id | bigint FK |  |
| subject_id | bigint FK |  |
| unique | (exam_appearance_request_id, subject_id) |  |

`exam_approvals`

| Field | Type | Notes |
| --- | --- | --- |
| id | bigint PK |  |
| exam_appearance_request_id | bigint FK |  |
| approver_role | enum(accounts,tm) |  |
| approver_id | bigint FK (users) |  |
| verification_metric | enum(no_dues_status,attendance) | dues from `student_dues`; attendance % vs `config('camp.attendance_threshold')` |
| result | enum(green,red,pending) |  |
| bypassed | boolean default false |  |
| bypass_reason | text nullable |  |
| bypassed_by | bigint FK nullable (users, BiC) |  |
| decided_at | timestamp nullable |  |

`attendance_records`

| Field | Type | Notes |
| --- | --- | --- |
| id | bigint PK |  |
| student_id | bigint FK |  |
| subject_id | bigint FK |  |
| institute_id | bigint FK |  |
| date | date |  |
| status | enum(present,absent) |  |
| uploaded_by | bigint FK (faculty) |  |
| lesson_plan_ref | string nullable |  |
| unique | (student_id, subject_id, date) |  |

`admit_cards`

| Field | Type | Notes |
| --- | --- | --- |
| id | bigint PK |  |
| exam_appearance_request_id | bigint FK unique |  |
| institute_id | bigint FK |  |
| admit_card_no | string unique | serial generator |
| generated_at | timestamp | only when both approvals green |
| em_signature_status | enum(pending,physically_signed) |  |
| tm_signature_status | enum(pending,physically_signed) |  |
| printed_at | timestamp nullable |  |

---

### Module 4 — Question Bank & Exam Paper (Super Admin + Institute)

**Livewire components:** `QuestionBankManager`, `QuestionUploadBulk`, `ExamPaperBuilder`

**Tables**

`question_bank`

| Field | Type | Notes |
| --- | --- | --- |
| id | bigint PK |  |
| subject_id | bigint FK | central subject for master questions |
| institute_id | bigint FK nullable | null = master/central bank |
| question_text | text |  |
| question_type | enum(objective,descriptive,practical) |  |
| topic | string |  |
| para | string nullable |  |
| level | enum(easy,medium,hard) |  |
| options_json | json nullable | for objective type |
| correct_answer | text nullable |  |

`exam_papers`

| Field | Type | Notes |
| --- | --- | --- |
| id | bigint PK |  |
| exam_id | bigint FK |  |
| institute_id | bigint FK |  |
| source | enum(master_bank,custom,mixed) |  |
| status | enum(draft,final) |  |

`exam_paper_questions`

| Field | Type | Notes |
| --- | --- | --- |
| id | bigint PK |  |
| exam_paper_id | bigint FK |  |
| question_id | bigint FK |  |
| marks | decimal |  |
| unique | (exam_paper_id, question_id) |  |

---

### Module 5 — Grading & Result Publication

**Purpose:** Phase 3 — mixed-mode marking, 75% pass criteria, Super Admin override, marksheets.

**Livewire components:** `MarksEntryGrid`, `ResultPublisher`, `MarkCorrectionPanel` (Super Admin only), `MarksheetGenerator`, `ConsolidatedMarksheetGenerator`

**Tables**

`marks`

| Field | Type | Notes |
| --- | --- | --- |
| id | bigint PK |  |
| student_id | bigint FK |  |
| exam_id | bigint FK |  |
| subject_id | bigint FK |  |
| institute_id | bigint FK |  |
| mark_type | enum(objective,descriptive,practical) |  |
| marks_obtained | decimal |  |
| max_marks | decimal |  |
| entered_by | bigint FK |  |
| entered_at | timestamp |  |
| unique | (student_id, exam_id, subject_id, mark_type) |  |

`results`

| Field | Type | Notes |
| --- | --- | --- |
| id | bigint PK |  |
| student_id | bigint FK |  |
| exam_id | bigint FK |  |
| subject_id | bigint FK nullable | null = overall exam result row |
| institute_id | bigint FK |  |
| total_marks, max_marks | decimal |  |
| percentage | decimal |  |
| pass_status | enum(pass,fail) | threshold from `config('camp.pass_percentage')` (75) |
| published_at | timestamp nullable |  |
| published_by | bigint FK nullable |  |
| unique | (student_id, exam_id, subject_id) |  |

`mark_correction_logs` (governance record; each correction is also written to `audit_trail`)

| Field | Type | Notes |
| --- | --- | --- |
| id | bigint PK |  |
| mark_id | bigint FK |  |
| corrected_by | bigint FK (Super Admin only) |  |
| old_value | decimal |  |
| new_value | decimal |  |
| reason | text |  |
| corrected_at | timestamp |  |

`marksheets`

| Field | Type | Notes |
| --- | --- | --- |
| id | bigint PK |  |
| student_id | bigint FK |  |
| exam_id | bigint FK |  |
| institute_id | bigint FK |  |
| serial_number | string unique | via `SerialNumberService` |
| file_path | string |  |
| tm_signature_status | enum(pending,physically_signed) |  |
| em_signature_status | enum(pending,physically_signed) |  |
| generated_at | timestamp |  |

`consolidated_marksheets`

| Field | Type | Notes |
| --- | --- | --- |
| id | bigint PK |  |
| student_id | bigint FK |  |
| program_id | bigint FK |  |
| institute_id | bigint FK |  |
| serial_number | string unique |  |
| file_path | string |  |
| generated_at | timestamp | end-of-program aggregate |

---

### Module 6 — Document Management System (DMS)

**Purpose:** Part 2, Section 1–2 — MTOE/SOP drafting, HoT approval, external submission tracking, 7-day rule, final repository.

**Livewire components:** `DocumentUploadForm`, `HoTReviewQueue`, `RegulatorySubmissionTracker`, `DocumentRepository`, `SevenDayRuleMonitor` (scheduled command + dashboard widget)

**Tables**

`documents`

| Field | Type | Notes |
| --- | --- | --- |
| id | bigint PK |  |
| institute_id | bigint FK nullable | null = central document |
| document_name | string | e.g. "MTOE Part 1" |
| document_type | enum(mtoe,sop,training_manual,other) |  |
| draft_version | string | e.g. v1.0 |
| temp_issue_no, temp_revision_no | string | proposed numbers pre-approval |
| uploaded_by | bigint FK |  |
| file_path | string | draft PDF/Word |
| status | enum(draft,hot_review,returned_for_correction,submitted_external,externally_approved,archived) |  |
| external_authority | string nullable | e.g. DGCA, Director of Airworthiness |
| submitted_external_at | timestamp nullable |  |
| externally_approved_at | timestamp nullable | starts the 7-day clock |
| seven_day_flag | boolean default false | set by scheduled job if no final record/scan 7 days after external approval |

`document_reviews`

| Field | Type | Notes |
| --- | --- | --- |
| id | bigint PK |  |
| document_id | bigint FK |  |
| reviewer_id | bigint FK (HoT) |  |
| action | enum(approve,return_for_correction) |  |
| remarks | text nullable |  |
| reviewed_at | timestamp |  |

`document_final_records` (created when the signed scan is uploaded — clears the 7-day flag)

| Field | Type | Notes |
| --- | --- | --- |
| id | bigint PK |  |
| document_id | bigint FK unique |  |
| issue_number | string |  |
| revision_number | string |  |
| revision_date | date | effective date |
| approving_authority | string | HoT, DGCA, etc. |
| digital_signature_file_path | string | scanned signed PDF (private disk) |
| uploaded_at | timestamp |  |

`document_revision_history`

| Field | Type | Notes |
| --- | --- | --- |
| id | bigint PK |  |
| document_id | bigint FK |  |
| issue_number, revision_number | string |  |
| effective_date | date |  |
| change_summary | text |  |

---

### Module 7 — MoU (Practical Accessor) Tracker

**Purpose:** Part 2, Section 3.

**Livewire components:** `MoURegister`, `MoUExpiryAlertsWidget`

**Tables**

`mou_records`

| Field | Type | Notes |
| --- | --- | --- |
| id | bigint PK |  |
| institute_id | bigint FK | base-specific visibility |
| accessor_name | string | organization/MRO |
| scope_of_training | json | e.g. \["B1.1","B2","Engine","Avionics"\] |
| start_date, expiry_date | date |  |
| file_path | string nullable | scanned MoU |
| status | enum(active,renewal_pending,expired) | refreshed daily by scheduled command from expiry_date |

`mou_alerts`

| Field | Type | Notes |
| --- | --- | --- |
| id | bigint PK |  |
| mou_id | bigint FK |  |
| alert_due_date | date | expiry_date − 60 days |
| sent_to | bigint FK (users) | one row per HoT of that institute |
| sent_at | timestamp nullable |  |
| status | enum(pending,sent) |  |

---

### Module 8 — Compliance Dashboard (Super Admin)

**Purpose:** Part 2, Section 4 — aggregate reporting, no new core data, mostly query/service layer + export.

**Livewire components:** `ComplianceHeatmap`, `PendingApprovalsWidget`, `RevisionDueList`, `AuditReadyExportButton`

**Supporting table**

`compliance_snapshots` (optional, for caching heavy aggregates)

| Field | Type | Notes |
| --- | --- | --- |
| id | bigint PK |  |
| institute_id | bigint FK nullable |  |
| metric_key | string | `active_sops`, `pending_hot_approvals`, `pending_external_approvals`, `due_for_revision` |
| value | int |  |
| generated_at | timestamp | refreshed via scheduled job |

Export feature: a queued job zips the `document_final_records` of all `documents` where `documents.status = externally_approved` (Active & Signed) into a downloadable archive per institute/auditor request.

---

## 6. Cross-Cutting Technical Features (from your requirements)

| Requirement | Implementation |
| --- | --- |
| Audit Trail on every approval | `audit_trail` table + `RecordsAuditTrail` trait (`audit`, `auditCreate/Update/Delete`, `auditApprove/Reject`, `auditBypass`) called from every approval action (Accounts, TM, BiC, HoT, Super Admin corrections) |
| Centralized serial numbers (ER, marksheets, admit cards) | `SerialNumberService` + `serial_counters` table with row-level locking |
| SMS/Email notifications | `notifications_log` table + Laravel Notification classes + queued jobs, triggered on ER issuance, exam approval, result publication, MoU 60-day alert |
| 7-Day Rule flag | Laravel Scheduled Command (daily) scanning `documents` where `externally_approved_at` < now − 7 days and no `document_final_records` row |
| Role-based data visibility (Institute sees only its own MoUs/students/exams) | `BelongsToInstitute` Global Scope on `institute_id`, bypassed only for Super Admin role |
| Soft deletes / retention | Existing `BaseModel` soft deletes + `SoftDeleteManager` (see `SOFT_DELETES_IMPLEMENTATION.md`) |
| Sensitive files | Private disk + authorized download routes |

---

## 7. Suggested Development Phases / Sprint Plan

| Phase | Duration (suggested) | Scope |
| --- | --- | --- |
| **Sprint 0 — Foundation (Module 1)** | ✅ Done | Laravel/Livewire setup, auth, roles & permissions (seeder + matrix screen), institutes CRUD with auto code, user management, role-based side menu, audit trail + viewer, notification log, `users.institute_id`, `BelongsToInstitute`, `SerialNumberService`, `config/camp.php` |
| **Sprint 1 — Student Onboarding** | 2 weeks | Student registration (+ user account), document upload wizard, student dues, dual-gate approval (Admin + Accounts), ER generation, ID card issuance |
| **Sprint 2 — Exam Application & Approval** | 2 weeks | Programs/subjects/syllabus mapping, exam appearance requests, Accounts dues gate + BiC bypass, TM attendance gate, admit card generation |
| **Sprint 3 — Question Bank & Paper Setup** | 1–2 weeks | Question bank CRUD (Super Admin + Institute), exam paper builder |
| **Sprint 4 — Grading & Results** | 2 weeks | Marks entry (all three modes), result computation (configurable pass %), Super Admin correction workflow, marksheet + consolidated marksheet generation with unique serials |
| **Sprint 5 — Document Management System** | 2 weeks | Document upload → HoT review → external submission tracking → final repository, 7-day rule scheduled job |
| **Sprint 6 — MoU Tracker** | 1 week | MoU CRUD, institute-scoped visibility, 60-day expiry alert job |
| **Sprint 7 — Compliance Dashboard & Reporting** | 1–2 weeks | Heatmap widgets, pending approvals list, revision-due list, audit-ready bulk export |
| **Sprint 8 — Notification Engine Hardening + QA** | 1–2 weeks | SMS/Email templates, queue tuning, end-to-end testing across all approval chains, UAT with pilot institute |

*(Total: roughly 13–17 weeks for a single small-to-mid dev team; adjust to your actual team size/velocity.)*

---

## 8. Laravel Project Structure (Livewire 2 layout)

```
app/
  Http/Livewire/
    Admin/
      Auth/                Login ✅
      Components/          SoftDeleteManager ✅, Table/DataTable ✅
      Dashboard/           Dashboard ✅
      Institutes/          InstitutesComponent ✅
      Core/                UserManager, RoleAssignment, AuditLogViewer, NotificationCenter
      Onboarding/          StudentRegistrationForm, DocumentUploadWizard, AdminDocumentVerification, AccountsFeeVerification, ERRequestGenerator, IDCardIssuance
      Exams/               ExamAppearanceApplication, AccountsDuesApproval, BiCOverridePanel, TMAttendanceApproval, AdmitCardGenerator, ExamScheduleManager, SyllabusMappingManager
      QuestionBank/        QuestionBankManager, QuestionUploadBulk, ExamPaperBuilder
      Grading/             MarksEntryGrid, ResultPublisher, MarkCorrectionPanel, MarksheetGenerator, ConsolidatedMarksheetGenerator
      DMS/                 DocumentUploadForm, HoTReviewQueue, RegulatorySubmissionTracker, DocumentRepository
      MoU/                 MoURegister, MoUExpiryAlertsWidget
      Compliance/          ComplianceHeatmap, PendingApprovalsWidget, RevisionDueList, AuditReadyExportButton
  Models/
    User.php ✅
    Admin/                 BaseModel ✅, Institute ✅, AuditTrail ✅, Country ✅, State ✅, (one model per new table, extending BaseModel)
  Traits/                  RecordsAuditTrail ✅, BelongsToInstitute
  Services/                SerialNumberService, NotificationService, PdfGenerationService, ComplianceAggregationService
  Policies/                StudentPolicy, ExamApprovalPolicy, DocumentPolicy, MoUPolicy ...
  Console/Commands/        CheckSevenDayRule, SendMoUExpiryAlerts, RefreshMoUStatus, RefreshComplianceSnapshots
config/
  camp.php                 pass_percentage, attendance_threshold, onboarding_days, mou_alert_days, seven_day_rule_days
database/
  migrations/              one per new table listed in Section 5
  seeders/                 RolesAndPermissionsSeeder, DemoInstituteSeeder
```

---

## 9. Open Questions to Resolve Before Sprint 1

1. Digital signatures: are TM/EM/HoT signatures purely a "physically signed, then scanned" status flag (as modeled above), or do you eventually want e-signature capture in-app?
2. Multi-institute hierarchy: do any institutes share students/programs, or is every student strictly single-institute?
3. SMS gateway preference (for the notification engine) — affects which Laravel notification channel driver to install.
4. Should Institute Admins be able to see other institutes' anonymized compliance stats, or is visibility strictly siloed except for Super Admin?
5. Dues source: will Accounts maintain fee rows in CAMP (`student_dues`), or just set a manual "no dues" flag / import from an external accounts system?
6. Attendance threshold for the TM gate — what % counts as green (e.g. 75% / 80%), and is it per subject or overall?
7. Serial numbering: should ER/marksheet/admit card counters run per institute per year, or one global running series?

---

*This plan maps directly to the two requirement documents you shared (Student Onboarding/ER/Exam/Grading and the Document Management/MoU/Compliance system), is aligned with the existing Laravel 9 / Livewire 2 codebase, and is structured so each module can be built, tested, and deployed independently.*
