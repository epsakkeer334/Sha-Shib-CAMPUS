# CAMP — Workflow Reference & QA Follow-up

**Sha Shib Group · Central Academic Management Portal (Laravel 9 + Livewire 2)**
Version: 8 Oct 2026 · Source of truth for the detailed design: `plan.md` (tables, fields, components)

This document describes the workflow agreed so far: who does what, in which order, and under which rules. It also lists what QA should check. Each part is marked:

| Mark | Meaning |
| --- | --- |
| ✅ **Built** | Implemented and covered by automated tests; QA can verify it now |
| 🟡 **Planned** | Design agreed, not built yet; QA checkpoints are acceptance criteria for later |
| ❓ **Open** | Still needs a decision from management / the client |

---

## Contents

1. [System overview](#1-system-overview)
2. [Roles & responsibilities](#2-roles--responsibilities)
3. [Menus and who uses them](#3-menus-and-who-uses-them)
4. [Permissions](#4-permissions)
5. [Set-up workflow: institutes, courses, batches, academic years, semesters](#5-set-up-workflow)
6. [Student onboarding workflow (admin side + admissions portal)](#6-student-onboarding-workflow)
7. [ER number generation & rules](#7-er-number-generation--rules)
8. [ER request form & ID card](#8-er-request-form--id-card)
9. [Fee structure & fee management (semester-wise)](#9-fee-structure--fee-management)
10. [Notifications](#10-notifications)
11. [Examination workflow (Module 3 + Module 4)](#11-examination-workflow)
12. [Module 5 — Grading & Result Publication](#12-module-5--grading--result-publication)
13. [Student promotion workflow](#13-student-promotion-workflow)
14. [DMS — Document Management System (Module 6)](#14-dms--document-management-system)
15. [MoU tracker & Compliance dashboard (Modules 7–8)](#15-mou-tracker--compliance-dashboard)
16. [Cross-cutting rules (audit, serials, uploads, data scoping)](#16-cross-cutting-rules)
17. [Confirmed requirements (decision log)](#17-confirmed-requirements-decision-log)
18. [Open / pending points](#18-open--pending-points)
19. [QA checkpoints & test scenarios](#19-qa-checkpoints--test-scenarios)
20. [QA environment notes](#20-qa-environment-notes)

---

## 1. System overview

CAMP serves 10+ institutes of the Sha Shib Group from **one application and one database**.

- **Centralised:** ER numbers, receipt numbers, marksheet serials, master data, question bank and result governance are managed group-wide.
- **Decentralised:** each institute runs its own daily approvals (documents, fees, ID cards, exams).
- Every institute-level record carries `institute_id`. Institute users **only ever see their own institute's data**; the Super Admin sees everything.

### Module map and status

| # | Module | Status |
| --- | --- | --- |
| 1 | Core / Foundation: institutes, users, roles & permissions, side menu, audit trail, notification log, serial numbers | ✅ Built |
| 1A | Master Data (Super Admin): academic years, courses, qualifications, boards, religions, categories, countries, states, payment gateways | ✅ Built |
| 2 | Student Onboarding & ER Number: admin onboarding, admissions portal, document gate, fee gate, ER number, ER form, ID card, batches, payment settings | ✅ Built |
| 2A | In-app workflow notifications (bell, notification page, portal notifications) | ✅ Built |
| 2B | Academic years, academic periods (semesters / terms), student period history, semester-wise fee structure | ✅ Built |
| 3 | Examination Lifecycle: subjects, syllabus mapping, exam schedule, exam application, dues gate + BiC bypass, attendance gate, admit card | 🟡 Planned |
| 4 | Question Bank & Exam Paper | 🟡 Planned |
| 5 | Grading & Result Publication + **Promotion** to the next semester | 🟡 Planned |
| 6 | DMS: MTOE / SOP / Training Manual approvals, external submission, 7-day rule, repository | 🟡 Planned |
| 7 | MoU (Practical Accessor) Tracker with 60-day expiry alerts | 🟡 Planned |
| 8 | Compliance Dashboard (Super Admin) | 🟡 Planned |

### End-to-end student life cycle

```
Super Admin set-up            Institute set-up                 Student journey
──────────────────            ────────────────                 ───────────────
Academic years        ──►     Institute courses        ──►     Registration (portal or office)
Courses (+ periods)           Batches                          Documents ─► Gate 1 (Admin)
Masters, gateways             Academic periods                 Admission fee ─► Gate 2 (Accounts)
Institutes                    Fee structure                    ER number ─► Semester 1 starts
                              Payment settings                 ER form signed · ID card issued ─► ACTIVE
                                                               Exams ─► Results ─► Promotion (Sem 2 … N)
                                                               Last semester passed ─► ALUMNI
```

### Student statuses

| Status | Meaning | Set when |
| --- | --- | --- |
| `draft` | Application being filled in | Created (office or portal) |
| `pending_docs` | Submitted, but required documents are missing | Submit with missing documents |
| `pending_approval` | All required documents present; waiting for the gates | Submit complete / last missing document uploaded |
| `rejected` | Returned for corrections (Gate 1 rejected) | Admin rejects the application |
| `er_issued` | ER number issued | Gate 1 **and** Gate 2 approved |
| `active` | ID card signed & issued | ID card marked signed & issued |
| `alumni` | Course completed | 🟡 Last semester passed (Module 5) |

---

## 2. Roles & responsibilities

| Role | Scope | Responsibilities |
| --- | --- | --- |
| **Super Admin** | All institutes | Master data; institutes; users of any institute; roles & permissions; serial numbers; acts in every institute screen; 🟡 subject/syllabus mapping, master question bank, mark corrections, promotion override, compliance dashboard |
| **Institute Admin** | Own institute | Institute courses, batches, academic periods, fee structure, payment settings; students (add/edit); **Gate 1** document verification; ER form & ID card handling (chooses which TM signed); creates the institute's staff users; 🟡 exam creation, **promotion confirmation** |
| **Accounts** | Own institute | Fee structure & student dues, records payments, **verifies payments and approves Gate 2**; 🟡 exam dues gate |
| **Training Manager (TM)** | Own institute | Physically signs the ER request form and the ID card (recorded in CAMP); 🟡 attendance gate, admit card & marksheet signature |
| **Base In-Charge (BiC)** | Own institute | 🟡 Bypasses the exam dues block with a recorded reason |
| **Examination Manager (EM)** | Own institute | 🟡 Admit card & marksheet signature, marks/results, **promotion confirmation** |
| **Head of Training (HoT)** | Own institute | 🟡 DMS approvals (approve / return), MoU expiry alerts |
| **Faculty** | Own institute | 🟡 Attendance & lesson plans, marks entry |
| **Student** | Self | Admissions portal: register, upload documents, pay fees, track status, notifications; 🟡 exam application, downloads |

**Who can create which users** ✅

| Creator | Can create | Institute |
| --- | --- | --- |
| Super Admin | Super Admin, Institute Admin, Accounts, TM, BiC, EM, HoT, Faculty | Selects the institute |
| Institute Admin | Accounts, TM, BiC, EM, HoT, Faculty | Always their own institute (enforced server-side) |
| — | Students | Created only through onboarding (office or portal), never from the Users screen |

---

## 3. Menus and who uses them

The side menu is built from a role × permission matrix (`config/menu.php`). An item appears only when its screen exists **and** the user has the role/permission, so menus for planned modules appear automatically once built.

### 3.1 Built menus ✅ (default access)

| Section | Menu | Super Admin | Institute Admin | Accounts | TM | BiC / EM / HoT / Faculty |
| --- | --- | :-: | :-: | :-: | :-: | :-: |
| Main | Dashboard | ✓ | ✓ | ✓ | ✓ | ✓ |
| Main | **Master Data** ▸ Academic (Academic Years, Courses, Qualifications, Matriculation Boards, Higher Secondary Boards) · Personal (Religions, Categories) · Location (Countries, States) · Finance (Payment Gateways) | ✓ only | – | – | – | – |
| Institute | Institute Management ▸ **Institutes** (incl. institute users) | ✓ only | – | – | – | – |
| Institute | Institute Management ▸ **Institute Courses** | ✓ (all, with institute column) | ✓ (own; no institute/code columns) | – | – | – |
| Institute | Institute Management ▸ **Batches** | ✓ | ✓ | – | – | – |
| Institute | Institute Management ▸ **Academic Periods** | ✓ | ✓ | – | – | – |
| Institute | Institute Management ▸ **Fee Structure** | ✓ | ✓ | ✓ | – | – |
| Institute | Institute Management ▸ **Payment Settings** | ✓ | ✓ | ✓ | – | – |
| Admissions | Students ▸ **All Students** | ✓ | ✓ | ✓ | ✓ | – |
| Admissions | Students ▸ **Add Student** | ✓ | ✓ | – | – | – |
| Admissions | Onboarding ▸ **Document Verification** (Gate 1) | ✓ | ✓ | – | – | – |
| Admissions | Onboarding ▸ **Payment Verification** (Gate 2) | ✓ | – | ✓ | – | – |
| Admissions | Onboarding ▸ **ER & ID Cards** | ✓ | ✓ | – | ✓ | – |
| Administration | Users & Permissions ▸ **Users** | ✓ (all) | ✓ (own institute) | – | – | – |
| Administration | Users & Permissions ▸ **Roles & Permissions** | ✓ only | – | – | – | – |
| Administration | System ▸ **Serial Numbers** | ✓ only | – | – | – | – |
| Administration | Monitoring ▸ **Audit Trail**, **Notification Log** | ✓ | ✓ (own) | – | – | – |
| Header | Notification bell + **My Notifications** page | ✓ | ✓ | ✓ | ✓ | ✓ |

Per-student pages, opened from All Students: **Onboarding** (tabs), **Fees & Payments**, **Gates, ER & ID card**.

Student **admissions portal** (`/admissions`, separate from the admin panel): Home · Apply (Step 1 Details → Step 2 Academic → Step 3 Documents → Step 4 Payment) · Application Status · Notifications.

> Default access can be changed per role on **Roles & Permissions** (Super Admin). `masters.manage` is Super-Admin-only and cannot be granted.

### 3.2 Planned menus 🟡 (role access as designed)

| Section | Menu | Roles |
| --- | --- | --- |
| Academics | Examinations ▸ Courses & Subjects | Super Admin, Institute Admin |
| Academics | Examinations ▸ Syllabus Mapping | Super Admin |
| Academics | Examinations ▸ Exam Schedule | Super Admin, Institute Admin, EM |
| Academics | Examinations ▸ Exam Applications | Institute Admin, Accounts, TM, BiC |
| Academics | Examinations ▸ Attendance | Faculty, TM |
| Academics | Examinations ▸ Admit Cards | Institute Admin, TM, EM |
| Academics | Question Bank ▸ Questions, Exam Papers | Super Admin, Institute Admin, EM |
| Academics | Grading & Results ▸ Marks Entry | Institute Admin, Faculty, EM |
| Academics | Grading & Results ▸ Results | Super Admin, Institute Admin, EM |
| Academics | Grading & Results ▸ Mark Corrections | Super Admin |
| Academics | Grading & Results ▸ Marksheets | Super Admin, Institute Admin, TM, EM |
| Academics | Grading & Results ▸ Promotions | Institute Admin, EM (Super Admin: override) |
| Documents & Compliance | Documents (DMS) ▸ Documents | Super Admin, Institute Admin, HoT |
| Documents & Compliance | Documents (DMS) ▸ HoT Review Queue | HoT |
| Documents & Compliance | Documents (DMS) ▸ Regulatory Submissions | Super Admin, HoT |
| Documents & Compliance | Documents (DMS) ▸ Document Repository | Super Admin + all staff |
| Documents & Compliance | MoU Register | Super Admin, Institute Admin, HoT |
| Documents & Compliance | Compliance Dashboard | Super Admin |

---

## 4. Permissions

Super Admin passes every permission check. Defaults for the other roles are below; they can be edited on Roles & Permissions.

| Permission | Meaning | Default roles |
| --- | --- | --- |
| `institutes.manage` | Manage institutes | Super Admin |
| `masters.manage` | Master data (cannot be granted) | Super Admin only |
| `institute_courses.view` / `.manage` | View / assign institute courses | Institute Admin |
| `batches.manage` | Batches | Institute Admin |
| `periods.manage` | Academic periods (semesters / terms) | Institute Admin |
| `students.view` / `.create` / `.update` / `.delete` | Students (delete = drafts only) | Institute Admin (all); Accounts & TM (view) |
| `onboarding.verify_documents` | Verify documents & approve Gate 1 | Institute Admin |
| `enrollment.manage` | ER form & ID card (print, signed, archive, issue) | Institute Admin, TM |
| `fees.manage` | Fee structure, student dues, payment settings | Institute Admin, Accounts |
| `payments.collect` | Record payments | Institute Admin, Accounts |
| `payments.verify` | Verify payments, receipts, approve Gate 2 | Accounts |
| `users.view` / `.create` / `.update` / `.delete` | Users | Institute Admin (own institute) |
| `roles.manage` | Roles & permissions | Super Admin |
| `audit.view` | Audit trail | Institute Admin |
| `notifications.view` | Notification log | Institute Admin |
| `serials.manage` | Serial number series | Super Admin |
| 🟡 `promotions.confirm_exam` | Confirm promotion list (results side) | EM |
| 🟡 `promotions.confirm_institute` | Confirm promotion list (institute side) | Institute Admin |

---

## 5. Set-up workflow

Set-up must happen **in this order** before students can be admitted:

```
1 Academic Years ─► 2 Courses (period structure) ─► 3 Institutes ─► 4 Institute Courses
   ─► 5 Batches ─► 6 Academic Periods ─► 7 Fee Structure ─► 8 Payment Settings
```

### 5.1 Academic years ✅ (Master Data, Super Admin)

- Name format `YYYY-YY` (e.g. `2026-27`), unique; start and end dates; **years may not overlap**.
- Default year runs **1 June – 31 May** (`academic_year_start_month = 6`). "Add" suggests the year after the latest one.
- A seeder creates the current year plus the next 4 (2026-27 … 2030-31).
- An academic year used by periods or fee lines cannot be deleted; deactivate it instead.

### 5.2 Courses & period structure ✅ (Master Data, Super Admin)

| Field | Rule |
| --- | --- |
| Name, Code | Code unique (e.g. `B1.1`) |
| Duration (months) | Overall length of the course |
| **Period type** | Semester / Term / Trimester / Module / Year (default Semester) |
| **Period length (months)** | e.g. 6 for a semester, 3 for a term; blank = duration ÷ number of periods |
| **Number of periods** | e.g. 8 semesters (4-year course), 1 term (3-month course) |

- Derived: periods per year = 12 ÷ period length; year of study = ceil(period no ÷ periods per year).
- The list shows e.g. "8 × Semester · 6 months".
- A course already used by students is deactivated, never deleted.

### 5.3 Institutes ✅ (Super Admin only)

- The Super Admin enters a **code prefix** (2–6 capital letters). It is **unique**, checked while typing, and read-only after creation.
- The institute code is generated automatically as **`PREFIX/established year/running no.`**. The running number is group-wide and starts at 1010 (e.g. `SHA/2005/1010`). The code is read-only.
- Established year is required (1800 – current year) and shown in its own column.
- Deactivating an institute blocks all of its users from logging in.

### 5.4 Institute courses ✅ (Super Admin all / Institute Admin own)

- Assign which master courses an institute offers; each can be activated or deactivated.
- Only an institute's **active** courses appear in its student registration dropdowns.
- The Institute Admin's list hides the institute and code columns (shown to the Super Admin only).

### 5.5 Batches ✅ (Super Admin / Institute Admin)

| Field | Rule |
| --- | --- |
| Institute + course | Must be a course the institute offers; locked once students are in the batch |
| Name | e.g. "June 2026 Batch" |
| **Batch code** | **Unique across all institutes** (deleted batches keep theirs). Checked while typing. Suggestion: `PREFIX-COURSE-YEAR`, e.g. `SHA-B11-2026` |
| Start / end date | After the end date, the batch is no longer offered at registration |
| **Intake academic year** | Filled from the start date (editable) |
| Capacity | Optional; a full batch is refused for new students |
| Status | Open / closed for admission |

- **Registration:** the batch is **required when the chosen course has open batches**, both on the portal (Step 1) and in the admin Add/Edit Student form. Changing the course clears the batch.
- A batch with students cannot be deleted; close it instead.
- ❓ Further batch details are still to be confirmed by management.

### 5.6 Academic periods (semesters) ✅ (Super Admin / Institute Admin)

A **calendar** is the set of all periods of one course for one intake at one institute, optionally for one batch.

1. **Generate:** choose the institute (Super Admin), course, optional batch, and the start date of period 1. Choosing a batch fills in the batch start date. A preview shows every period before saving.
2. CAMP creates periods 1…N. Period *n* starts (n−1) × period length after the start date. Each period records its label ("Semester 3"), year of study, start/end date and the **academic year it starts in**. The intake year is the academic year of period 1.
3. Generation is refused when:
   - no academic year covers the start date; or
   - the calendar for that course + intake (or batch) already exists.
4. **Edit** a period's dates or status (planned / ongoing / completed). Running periods are highlighted.
5. **Remove** a calendar only while no student is in any of its periods.

**Which calendar applies to a student:** the batch's calendar if one exists; otherwise the institute course calendar of the intake year of the student's joining date.

### 5.7 Fee structure → see [Section 9](#9-fee-structure--fee-management)

### 5.8 Payment settings ✅ (Super Admin / Institute Admin / Accounts)

- Per institute: which payment methods are accepted (from the master Payment Gateways).
- **UPI/GPay:** UPI ID, payee name, QR image. **Cash / bank transfer / cheque:** office instructions and bank details.
- **Online gateways** (Razorpay, PayU …) are shown as separate cards, ready for configuration. ❓ Integration pending.
- On the portal Payment step, the student sees each accepted method as a radio option that expands its details.

---

## 6. Student onboarding workflow

### 6.1 Admin side ✅ (Students ▸ Add Student / Onboarding)

Tabs, **each saved separately as a draft**:

1. **Basic:**
   - name, DOB;
   - gender (one selection only);
   - qualification, email, phone, emergency contact, religion → category (dependent dropdown);
   - institute → course → batch, joining date;
   - **student login credentials** (portal account).
2. **Address & Parent:** address, country → state, city, pincode, parent name / phone / email / occupation.
3. **Academic:** 10th board + mark (% 0–100 or CGPA 0–10); 12th board, stream (PCM / PCB / Commerce / Arts) and mark.
4. **KYC Documents:**
   - Required: passport photo (jpg/png ≤ 2 MB), medical certificate, 10th marksheet, 12th marksheet (pdf/jpg/png ≤ 5 MB). "Other" is optional.
   - **Upload starts on file select.** Replace and delete appear immediately after each upload.
5. **Review & Submit:** shows a checklist of anything missing.

**Onboarding window:** 30 days from the joining date (`onboarding_deadline`). The students list warns 7 days before the deadline and shows "Past deadline" for students who are overdue and still have no ER number.

### 6.2 Admissions portal (student) ✅ (`/admissions`)

1. **Step 1, Your details:**
   - creates the student login and a draft application (institute → course → batch);
   - once a course is chosen, its fees are shown.
2. **Step 2, Academic.**
3. **Step 3, Documents:** upload on select; re-upload rejected documents; submit the application.
4. **Step 4, Payment:**
   - fees list grouped by period;
   - pay by UPI (QR / UPI app link / UPI ID, then enter the UTR and a screenshot) or at the office;
   - receipts.
   - **Pay now** opens only when all required documents are uploaded (or a payment already exists).
5. **Application status:** timeline, "action needed" banners, current semester once assigned.

Navigation and editing rules:
- Students can move freely between steps; unsaved entries are auto-saved and restored, and sign-in resumes at the last step.
- Details, academic data and documents **stay editable until a fee payment is confirmed**.
- The course cannot be changed once a payment has been submitted.
- Edits made after Gate 1 approval **reopen Gate 1**.

### 6.3 Submission

- **Submit** with missing documents → `pending_docs`; with all documents → `pending_approval`. Both gates are created as pending.
- At submission the student is charged the **admission fee only** (see [9.3](#93-charging-rules--when-each-fee-reaches-the-student)).

### 6.4 Gate 1: Document verification ✅ (Institute Admin)

Queue tabs: Pending / Rejected / Gate approved. Verified students **stay visible**; pending ones are highlighted.

- **Verify** or **Reject** each document. Remarks are required on reject, and the student is emailed and notified.
- **Re-review a rejected document:** "Approve after re-review" (student told it is accepted) or "Move back to pending". Both are audited.
- A re-uploaded document keeps the previous rejection reason.
- **Remind** the student about missing documents (email).
- **Approve Gate 1:** only when every required document is verified and the status is `pending_approval`.
- **Reject Gate 1** (reason required): application → `rejected`. Resubmission reopens the gate.
- Any document or detail change after approval **reopens Gate 1**.

### 6.5 Gate 2: Fee verification ✅ (Accounts)

Payments arrive by:
- **UPI/GPay:** UTR + screenshot from the student or office; or
- **offline:** cash / bank / cheque recorded at the desk.

Every payment waits as **Pending verification**.

**Payment Verification** queue (To verify / Approved / Rejected / Fee gate):
- shows the student's phone number under the name and the fee gate status in the table;
- shows an amount-vs-balance check.

| Action | Result |
| --- | --- |
| Approve payment | Receipt `RCPT/2026/00001` issued; due recalculated (pending → part paid → paid); student notified |
| Reject payment (reason) | Payment `failed`; student notified |
| Waive a due (reason) | Due `waived` |
| Delete a due | Only when unpaid |
| **Approve Gate 2** | Only when Gate 1 is approved, fees exist, **nothing is outstanding** and no payment is waiting for verification |

A new due added after Gate 2 approval **but before the ER number** reopens Gate 2.

---

## 7. ER number generation & rules ✅

| Rule | Detail |
| --- | --- |
| Trigger | **Automatic**, the moment **both Gate 1 and Gate 2 are approved** (whichever is approved last) |
| Format | `ER-{year}-{5-digit no.}`, e.g. `ER-2026-00001` |
| Series | **One group-wide running series** (not per institute), via the central serial number service with row locking, so two students can never get the same number |
| Uniqueness | Unique, stored once on the student, never changed or re-issued |
| Status | Student → `er_issued` |
| Created with it | ER request form (status *generated*) + pending ID card |
| Semester | **Period 1 (Semester 1) starts**: a student period record is created in the student's calendar (batch, else intake). Nothing happens if no calendar exists yet |
| Fees | The remaining **one-time fees** of the student's intake year **+ period 1 fees** are charged (due on period 1's start date) |
| Notifications | Student emailed + notified; staff with `enrollment.manage` notified to print the ER form and ID card |
| After ER | New fees no longer reopen Gate 2 |
| Display | The ER number is highlighted on the students list, the ER & ID list and the student pages |

---

## 8. ER request form & ID card ✅

**ER request form:** generated → printed → TM signed → archived

1. **Print** (browser print / save as PDF).
2. **Mark TM signed:** allowed only after printing.
3. **Archive:** allowed only after the TM signature is recorded.

**ID card** (85.6 × 54 mm, with the KYC photo):

1. **Print:** every print is counted at every stage, **including after the card is issued** (duplicates).
2. **Mark signed & issued:** allowed only after at least one print. The student becomes **`active`**.
3. **Reprint** (lost / damaged, reason required): the card goes back to pending signature and must be signed again.

**Who records the TM's physical signature:**

| User | Behaviour |
| --- | --- |
| Training Manager | Recorded as the signer automatically; **no signer dropdown** |
| Super Admin, Institute Admin | Choose **which TM signed** from a dropdown of the institute's TMs |
| Others with `enrollment.manage` | Only while the institute has no TM account (recorded under them) |

The ER & ID Cards list has filters and keeps the Students menu highlighted when a student is opened.

---

## 9. Fee structure & fee management

### 9.1 Fee lines ✅ (Institute Management ▸ Fee Structure; Super Admin, Institute Admin, Accounts)

| Field | Rule |
| --- | --- |
| Institute + course | Must be an offered course |
| Fee (name) | Unique per institute + course + **academic year** + **period** |
| Amount | ₹1 – 99,99,999 |
| **Academic year** | Blank = every year. For a one-time fee it means "students whose **intake** is that year"; for a semester fee it means "semesters **starting** in that year" |
| **Period** | Blank = **one-time fee**; 1…N = fee of that semester / term (labels come from the course) |
| **Admission fee** flag | One-time fees only. This is the fee paid **during registration**. A course with **no** admission fee charges every applicable fee at registration (older behaviour) |
| Due date | **On period start** (default for semester fees) · **after joining** (joining date + N days; 0 = on joining) · **fixed date** (same calendar date for everyone) |
| Order, Active/Inactive | Inactive lines are never charged |

**Screen:**
- **Academic year** filter.
- One collapsible card per course; opening one closes the others.
- Inside a card, fees are **grouped One-time · Semester 1 · Semester 2 …** with a subtotal per group.
- The card header shows fees count, periods, admission fee, next fixed due and the course or year total.
- Courses with **no fees** are flagged. "No admission fee" is flagged.
- Search, course / due / status filters.
- **Copy from previous year:** copies that year's lines into the selected year, skips lines that already exist, and moves fixed dates on by one year.

### 9.2 Student dues ✅ (student ▸ Fees & Payments)

- Dues are grouped **by period** (One-time, Semester 1, …) with the balance per group, on the admin page and the portal.
- Manual due: add by hand (e.g. uniform fee), waive with a reason, delete only if unpaid.
- Due statuses: pending, part paid, paid, waived. "No dues" = every due is paid or waived.

### 9.3 Charging rules: when each fee reaches the student

| Moment | Fees charged |
| --- | --- |
| Registration / submission (no ER yet) | **Admission fee only** (if the course has one) |
| **ER number issued** | One-time fees for the intake year (or all years) + **Semester 1** fees, due on the Semester 1 start date |
| 🟡 **Promotion to Semester N** (Module 5) | Semester N fees for the academic year the semester starts in, due on its start date (immediately if it has already started) |
| New or re-activated fee line | Charged **at once** to every current student it applies to (all statuses except rejected / alumni). A semester fee goes only to students **currently in that semester** |
| Editing a fee line | Applies only to students charged from then on; existing dues are not changed |
| Never | A fee is never charged twice to the same student |

> **Existing data note:** fee lines created before Module 2B are one-time lines for every year. Students who received their ER number before Module 2B have no semester record; they are treated as Semester 1 for fees.

---

## 10. Notifications ✅

- **Admin bell** (refreshes every 30 s): unread badge; the 5 latest notifications; mark all read; **View all**. Clicking an item marks it read and opens the related screen. The count on the bell updates when items are marked read on the notifications page.
- **My Notifications** page: All / Unread / Read, stage filter, search, grouped by day, 20 per page.
- **Portal bell** for students.
- **Email** (and SMS, ❓ gateway pending) go through the notification log.

**Who is notified at each stage:**
- **Staff:** users of the student's institute who hold the stage's permission, plus the Super Admin.
- **Not notified:** the user who performed the action.

| Stage | Events |
| --- | --- |
| Registration | registration started, application submitted, details updated |
| Documents | uploaded, resubmitted, complete, verified, rejected, accepted on re-review, reopened, reminder |
| Gate 1 | approved (→ Accounts), reopened, application rejected |
| Payment | fees added, payment submitted (→ Accounts), approved, rejected, fee waived |
| Gate 2 | approved, reopened |
| ER / ID | ER issued (→ ER/ID staff), ID card issued (student active), reprint |

---

## 11. Examination workflow 🟡 (Module 3 + Module 4)

**Trigger → Approval → Execution**

1. **Subjects** per course and **semester** (`period_no`), with syllabus topics.
2. **Syllabus mapping** (Super Admin): an exam can be scheduled only when the syllabus covered is at least the threshold. **Mid-semester: 40 %. Semester exam: 100 %.**
3. **Exam schedule** (Super Admin, Institute Admin, EM): exam type (mid-sem / semester), semester, date, mode (online / offline).
4. **Exam application** by the student, choosing subjects. One application per student per exam.
5. **Dues gate** (Accounts): green = no dues **up to the exam's semester**, red = dues outstanding.
   - **BiC bypass:** BiC can override a red dues gate with a **mandatory recorded reason** (audited).
6. **Attendance gate** (TM): attendance % vs the threshold (75 % in config). ❓ Per subject or overall is still open.
7. **Admit card:** generated **only when both gates are green (or bypassed)**.
   - Number format: `AC-{institute code}-{year}-00001` (per institute).
   - Physically signed by the **EM and TM** (status recorded), then printed.
8. **Question bank** (Module 4):
   - central master bank (Super Admin) plus institute questions;
   - objective / descriptive / practical; topic, level.
   - **Exam paper builder:** from the master bank, custom questions or mixed; draft → final.

---

## 12. Module 5 — Grading & Result Publication 🟡

1. **Marks entry** (Institute Admin, Faculty, EM): per student × subject × mark type (objective / descriptive / practical), out of the max marks.
2. **Result computation:**
   - percentage per subject and overall;
   - **pass = 75 % or more** (`pass_percentage`, configurable, never hard-coded).
3. **Publish results** (Super Admin, Institute Admin, EM): records who published and when; the student is notified.
4. **Mark correction** (Super Admin only, after publishing):
   - old value → new value with a **mandatory reason**;
   - kept in the correction log **and** the audit trail.
5. **Marksheet** per exam:
   - unique serial `MS-{year}-000001` (group-wide);
   - physically signed by the **TM and EM** (status recorded).
6. **Consolidated marksheet** at the end of the course: serial `CMS-{year}-000001`.
7. Results published for a semester → the semester is marked **completed**, which opens **Promotion**.

---

## 13. Student promotion workflow 🟡 (Module 5, uses Module 2B semesters)

Promotion is the **only** way a student moves to the next semester. It is never automatic by date.

**Eligibility** (shown per student with the reasons):

| Check | Rule |
| --- | --- |
| Results | Results of the semester published (semester *completed*) |
| Marks | **75 % or more in every subject** of the semester. Any subject below 75 % → not eligible (re-exam / repeat per exam rules) |
| Attendance | At or above the attendance threshold |
| Dues | No dues up to that semester (configurable, default on) |
| Status | Not on hold / not rejected |

**Approval: both the Examination Manager and the Institute Admin are required** (order does not matter):

1. A **promotion list** is produced for institute + course + academic year + semester: *Eligible* / *Not eligible* with reasons.
2. The **Examination Manager** confirms the results-based list (bulk or per student) and can mark students **detained** with a reason.
3. The **Institute Admin** confirms the same list (bulk or per student). Either role can send a student back with a remark.
4. When **both** have confirmed a student:
   - the current semester → *completed*;
   - the next semester → *current*, in the calendar of the academic year it starts in;
   - **next-semester fees are charged** (due on its start date);
   - the student is notified;
   - an audit entry is written.
5. **Super Admin override:** promote a not-eligible student with a **mandatory reason**. Both roles' status is still recorded, and the override is audited.
6. After the **last semester**: student → **alumni**; the consolidated marksheet becomes available.

---

## 14. DMS — Document Management System 🟡 (Module 6)

**Documents:** MTOE, SOP, Training Manual, other. Each is central or institute-specific.

```
Draft upload ─► HoT review ─┬─► Returned for correction ─► (re-upload) ─► HoT review
                            └─► Approved ─► Submitted to external authority (DGCA / Director of Airworthiness)
                                              ─► Externally approved ─► Signed scan uploaded ─► Final record / Archived
```

1. **Upload a draft:** document name, type, draft version, proposed issue / revision numbers, file.
2. **HoT review:** approve or return for correction (remarks). Every action is recorded.
3. **External submission:** authority and date submitted; then the external approval date.
4. **7-day rule:** a daily job flags any document where 7 days have passed since external approval and **no signed final record / scan** has been uploaded. Uploading the scan clears the flag.
5. **Final record:** issue no., revision no., effective date, approving authority, signed scan (private storage).
6. **Revision history** is kept for every issue / revision.
7. **Document repository:** final documents available to all staff of the institute (Super Admin: all).

---

## 15. MoU tracker & Compliance dashboard 🟡 (Modules 7–8)

**MoU (Practical Accessor) register** (Super Admin, Institute Admin, HoT):
- accessor organisation, scope of training (B1.1, B2, Engine, Avionics …), start / expiry date, scanned MoU;
- visible only to its own institute.
- A daily job refreshes the status: **active → renewal pending → expired**.
- **Expiry alert 60 days before** expiry to every HoT of the institute.

**Compliance dashboard** (Super Admin):
- heatmap per institute: active SOPs, pending HoT approvals, pending external approvals, documents due for revision;
- pending-approvals list;
- **audit-ready export**: a zip of all active and signed final documents per institute.

---

## 16. Cross-cutting rules

| Area | Rule |
| --- | --- |
| **Data scoping** | Institute users see and act only on their own institute's records, enforced server-side. The Super Admin sees all |
| **Audit trail** | Every create / update / delete, approval, rejection, bypass, print, signature, correction and override is logged with user, institute, IP, before/after values and reason |
| **Serial numbers** | Central service with row locking. ER `ER-{year}-#####` and receipts `RCPT/{year}/#####` (group-wide); admit cards `AC-{institute}-{year}-#####` (per institute); marksheets `MS-{year}-######` and consolidated marksheets `CMS-{year}-######` (group-wide) |
| **Uploads** | Stored on a **private** disk with **random, non-predictable file names** (never the student id or a running number), on both the admin and portal sides. Downloads only through authorised routes. Old predictable files can be renamed with `php artisan camp:secure-uploads` (`--dry-run` to preview) |
| **Deletion** | Soft deletes everywhere. Master rows and batches in use are **deactivated / closed**, not deleted. Only **draft** students can be deleted |
| **Business rules in config** | Pass 75 %, attendance 75 %, onboarding window 30 days, deadline warning 7 days, academic year start month June, MoU alert 60 days, 7-day rule |
| **Login rules** | Inactive users, users without a role and users of inactive institutes cannot sign in. Students are kept out of the admin panel and staff out of the portal |

---

## 17. Confirmed requirements (decision log)

| # | Decision |
| --- | --- |
| D1 | One application / one database for all institutes; data separated by institute |
| D2 | Institute code = **chosen unique prefix / established year / group-wide running number** (from 1010) |
| D3 | Master data (incl. academic years, courses, payment gateways) is managed **only by the Super Admin** |
| D4 | Batches are per institute + course with a **unique batch code**, managed by the Super Admin and Institute Admin, and used in both the portal and admin registration |
| D5 | Registration charges **only the admission fee** (flag on the fee line); the other fees follow automatically **after the ER number** |
| D6 | **Dual gate:** Gate 1 documents (Institute Admin) + Gate 2 fees (Accounts) → ER number automatically |
| D7 | ER number: **one group-wide series** `ER-YYYY-#####` |
| D8 | ID card prints are counted at **every** stage, including after issue |
| D9 | TM signature: a TM records it as themselves (no dropdown); the Super Admin / Institute Admin choose which TM signed |
| D10 | Uploaded files use random, non-predictable names |
| D11 | Academic periods are **configurable per course** (semester / term / trimester / module / year; e.g. 3- or 6-month courses) |
| D12 | Academic year default **June – May**; periods belong to the academic year they **start** in |
| D13 | Fee structure is **semester-wise** by Institute + Course + Academic Year + Semester, managed by the Super Admin and Institute Admin (and Accounts) |
| D14 | Semester fees are **due on the semester start date** |
| D15 | Semester 1 starts when the **ER number is issued** |
| D16 | Promotion requires **75 % in every subject** |
| D17 | Promotion is confirmed by **both the Institute Admin and the Examination Manager**; the Super Admin can override with a reason. *(Supersedes an earlier idea of automatic promotion by semester completion date.)* |
| D18 | Promotion and grading belong to **Module 5** |

---

## 18. Open / pending points

| # | Point | Owner |
| --- | --- | --- |
| O1 | **Semester-wise fee amounts / details** per course and academic year (the client said complete details will follow) | Management |
| O2 | **Batch details:** extra fields or rules still to be confirmed | Management |
| O3 | **Academic year start month editable from the UI** by the Super Admin / Institute Admin (currently a config value, June) | Dev, to confirm |
| O4 | **Online payment gateways:** which one first (Razorpay / PayU / PhonePe); GPay via manual UTR (as built) or via a gateway; one merchant account per institute? | Management |
| O5 | **SMS gateway** provider (SMS currently logged as pending) | Management |
| O6 | **Attendance threshold** for the exam gate: 75 % or 80 %, and per subject or overall? | Management |
| O7 | **Signatures:** physical-sign-then-record (as built) vs in-app e-signature in future | Management |
| O8 | Can students or courses be shared between institutes, or is every student strictly single-institute? | Management |
| O9 | Can Institute Admins see anonymised compliance stats of other institutes? | Management |
| O10 | Re-exam / repeat rules for subjects below 75 % (affects promotion and detention) | Management / EM |
| O11 | Promotion "no dues" condition on by default: confirm | Management |
| O12 | Serial numbering for marksheets / admit cards: per institute or group-wide (current design shown in Section 16) | Management |
| O13 | Existing students who got their ER number before Module 2B: confirm they should be placed in Semester 1 (or another semester) | Institute Admins |

---

## 19. QA checkpoints & test scenarios

Columns: **ID** · **Scenario** · **Expected result**. ✅ = verifiable now; 🟡 = acceptance criteria for the planned module.

### 19.1 Module 1: Core ✅

| ID | Scenario | Expected |
| --- | --- | --- |
| C-01 | Super Admin creates an institute with prefix `ABC`, established 2005 | Code `ABC/2005/<next no.>` generated; prefix and code read-only afterwards |
| C-02 | Enter a prefix already used by another institute | Duplicate error shown while typing; cannot save |
| C-03 | Institute Admin opens the Users screen | Sees only own institute's users; can create only Accounts / TM / BiC / EM / HoT / Faculty |
| C-04 | Deactivate an institute, then log in as one of its users | Login refused |
| C-05 | Log in as each role | Side menu matches [Section 3.1](#31-built-menus--default-access); Master Data sits at the top, Super Admin only |
| C-06 | Change a permission on Roles & Permissions | Menu and access change for that role; audited |
| C-07 | Any approval / edit / delete | Audit trail entry with user, IP, before/after |
| C-08 | Breadcrumbs on list pages | Page title (h2) above the breadcrumb, with arrow separators |

### 19.2 Module 1A: Master Data ✅

| ID | Scenario | Expected |
| --- | --- | --- |
| M-01 | Non-Super-Admin opens a master URL | Access denied; menu hidden |
| M-02 | Add academic year overlapping an existing one | "Dates overlap …" error |
| M-03 | Academic year name not in `YYYY-YY` format | Validation error |
| M-04 | Add a course with blank period length (duration 6, periods 2) | Saved; period length 3; summary "2 × Semester · 3 months" |
| M-05 | Add a category | Must belong to a religion; same name allowed under another religion |
| M-06 | Delete a master row in use | Refused; deactivate instead; inactive rows hidden from dropdowns |

### 19.3 Set-up: Institute courses, Batches, Academic periods ✅

| ID | Scenario | Expected |
| --- | --- | --- |
| S-01 | Institute Admin views Institute Courses | Only own courses; no institute/code columns |
| S-02 | Create a batch with a code that exists (any institute) | "Already used" error while typing |
| S-03 | Batch start date 05 Jul 2027 | Intake year auto-set to 2027-28 |
| S-04 | Batch with capacity reached | New students cannot select it |
| S-05 | Delete a batch with students | Refused; "close it instead" |
| S-06 | Generate periods for an 8-semester course from 01 Jul 2026 | 8 periods; Sem 2 = 01 Jan 2027 – 30 Jun 2027; Sem 3 = Year 2, academic year 2027-28 |
| S-07 | Generate the same course + intake again | Refused: periods already exist |
| S-08 | Generate with a start date not covered by any academic year | Refused: add the academic year first |
| S-09 | Generate for a batch | Start date pre-filled with the batch start; batch students use this calendar |
| S-10 | Edit a period's end date before its start date | Validation error |
| S-11 | Remove a calendar with a student in it | Refused with a warning |
| S-12 | Institute Admin B opens Academic Periods | Does not see institute A's calendars |

### 19.4 Student onboarding ✅

| ID | Scenario | Expected |
| --- | --- | --- |
| O-01 | Add Student, save each tab separately | Each tab saves as a draft independently |
| O-02 | Gender selection | Only one option can be selected |
| O-03 | Add Student with login credentials | Student portal login created and works at `/admissions/login` |
| O-04 | Course with open batches, batch left empty | Batch required error (admin and portal) |
| O-05 | Upload a document | Uploads on select; Replace / Delete appear instantly for that document |
| O-06 | Check the stored file name | Random, non-predictable name (no id / running number) |
| O-07 | Submit with a missing required document | Status Pending Documents; both gates pending |
| O-08 | Portal: try Pay now before all documents are uploaded | Not available |
| O-09 | Portal: change course after submitting a payment | Refused |
| O-10 | Edit details after Gate 1 approval | Gate 1 reopened; admins notified |
| O-11 | Joining date + 30 days passed without ER | Shown as past deadline; counted on the list header |

### 19.5 Gates, ER number, ER form, ID card ✅

| ID | Scenario | Expected |
| --- | --- | --- |
| G-01 | Approve Gate 1 with an unverified document | Blocked: "Every required document must be verified first" |
| G-02 | Reject a document without remarks | Not allowed; with remarks → student emailed + notified |
| G-03 | Re-review a rejected document → Approve after re-review | Document verified; student told "accepted"; audited |
| G-04 | Reject Gate 1 | Student `rejected`; resubmission reopens the gate |
| G-05 | Verified students in the document queue | Still listed (Gate approved tab), not hidden |
| G-06 | Approve Gate 2 with outstanding dues / payment pending | Blocked with the reason |
| G-07 | Approve a UPI payment | Receipt `RCPT/YYYY/#####`; due paid / part paid; student notified |
| G-08 | Payment queue | Phone under the name; fee gate status visible; handled payments stay listed |
| G-09 | Approve Gate 1 and Gate 2 (either order) | ER `ER-YYYY-#####` issued automatically; status ER Issued; ER form + pending ID card created |
| G-10 | Two students approved at the same moment | Different, consecutive ER numbers (no duplicates) |
| G-11 | Add a fee after Gate 2 approval, before ER | Gate 2 reopened |
| G-12 | Mark ER form signed before printing | Blocked; archive blocked until signed |
| G-13 | ID card: print 2×, issue, print again | Count = 3; status issued; student Active |
| G-14 | TM marks the card signed | No dropdown; TM recorded as signer |
| G-15 | Institute Admin / Super Admin marks signed | Must choose which TM signed |
| G-16 | Reprint an issued card (reason) | Back to pending signature; student notified |

### 19.6 Fee structure & semester fees ✅

| ID | Scenario | Expected |
| --- | --- | --- |
| F-01 | Course with admission fee; student registers | Only the admission fee is charged |
| F-02 | ER issued (calendar exists) | Student in Semester 1; one-time fees + Semester 1 fees added; semester fee due = Semester 1 start date |
| F-03 | One-time fee set for academic year 2027-28; student intake 2026-27 | Not charged to that student |
| F-04 | Add a Semester 1 fee while students are in Semester 1 | Charged immediately to them only (not to applicants or other semesters); notified |
| F-05 | Select a period in Add Fee | Due type defaults to "Period start"; admission-fee option hidden |
| F-06 | Same fee name, same course / year / semester | "Already exists" error; allowed for another semester |
| F-07 | Period number above the course's number of periods | Validation error |
| F-08 | Academic year filter | Shows that year's lines + "every year" lines; totals for that year |
| F-09 | Copy from previous year | Lines copied; fixed dates +1 year; running it again copies nothing |
| F-10 | Edit a fee amount | Existing dues unchanged; new charges use the new amount |
| F-11 | Deactivate → reactivate a line | Never charged twice |
| F-12 | Delete a line already charged | Refused; set inactive instead |
| F-13 | Student Fees page / portal Payment | Dues grouped One-time / Semester 1 / … with balances |
| F-14 | Students list | Current semester chip ("Semester 1 · 2026-27"); filter by semester after choosing a course |

### 19.7 Notifications ✅

| ID | Scenario | Expected |
| --- | --- | --- |
| N-01 | Student uploads a document after submission | Institute Admins notified; the uploader is not |
| N-02 | Gate 1 approved | Accounts notified; student notified |
| N-03 | Click a bell item | Marked read; opens the related screen |
| N-04 | Mark read on the notifications page | Bell count updates |
| N-05 | User of institute B | Never receives institute A notifications |

### 19.8 Examination 🟡

| ID | Scenario | Expected |
| --- | --- | --- |
| E-01 | Schedule a mid-sem exam with 30 % syllabus covered | Refused (needs 40 %); semester exam needs 100 % |
| E-02 | Student applies twice for the same exam | Refused |
| E-03 | Dues outstanding up to the exam's semester | Dues gate red; admit card not generated |
| E-04 | BiC bypasses the dues gate without a reason | Refused; with a reason → bypass recorded + audited |
| E-05 | Attendance below threshold | Attendance gate red |
| E-06 | Both gates green | Admit card `AC-{institute}-{year}-#####` generated; EM + TM signature pending |
| E-07 | Institute user | Sees only own exams / applications |

### 19.9 Grading, results, marksheets 🟡

| ID | Scenario | Expected |
| --- | --- | --- |
| R-01 | Subject at 74.9 % | Fail; at 75 % → pass |
| R-02 | Non-Super-Admin tries a mark correction | Not allowed |
| R-03 | Super Admin corrects a mark without a reason | Refused; with reason → logged (old/new) + audited |
| R-04 | Generate marksheets | Unique `MS-YYYY-######`; TM + EM signature status pending |
| R-05 | Publish results | Published by/at recorded; students notified; semester completed |

### 19.10 Promotion 🟡

| ID | Scenario | Expected |
| --- | --- | --- |
| P-01 | Student with one subject below 75 % | Listed as Not eligible with that subject as the reason |
| P-02 | Only the EM confirms | Not promoted yet |
| P-03 | EM and Institute Admin confirm (any order) | Promoted: semester N completed, N+1 current; Semester N+1 fees charged (due on its start date); notified; audited |
| P-04 | EM marks detained (reason) | Stays in the semester; reason recorded |
| P-05 | Super Admin override without a reason | Refused; with a reason → promoted + audited |
| P-06 | Last semester passed | Student → Alumni; consolidated marksheet `CMS-YYYY-######` available |
| P-07 | Results not published | Promotion list not available |

### 19.11 DMS, MoU, Compliance 🟡

| ID | Scenario | Expected |
| --- | --- | --- |
| D-01 | HoT returns a draft for correction | Status returned; remarks visible to the uploader |
| D-02 | Externally approved, no signed scan after 7 days | Flagged by the daily job; flag clears when the scan is uploaded |
| D-03 | Final record upload | Issue / revision / effective date stored; revision history updated |
| D-04 | MoU expiring in 60 days | Alert to every HoT of that institute |
| D-05 | MoU expiry passed | Status expired |
| D-06 | Institute user opens MoUs / documents | Only own institute (+ central documents) |
| D-07 | Audit-ready export | Zip of active & signed documents for the institute |

---

## 20. QA environment notes

- **Never run automated tests or `migrate:fresh` against the working database `shashib_campus`.** Tests run only on `shashib_campus_testing` (forced by `phpunit.xml`; the test base class refuses any database not ending in `_testing`). Run `php artisan config:clear` before tests.
- Schema changes are **additive migrations only**, applied to the test database first, then the working database.
- Automated test suites: `Module1CoreTest`, `Module1AMasterDataTest`, `Module2StudentOnboardingTest`, `Module2WorkflowTest`, `Module2PortalTest`, `Module2BPeriodsTest` (88 tests passing at the time of writing).
- For manual QA with sample data, serve the test database: `DB_DATABASE=shashib_campus_testing php artisan serve --port=8099`.
