## Requested project-root document

- User confirmed the literal project folder is `d:\Laravel Service\-Sha-Shib-CAMPUS` (leading hyphen included) and the requested filename is `PROJECT_PLAN.md` at the project root.
- Folder currently contains only `.git/`; no existing application files or local project instructions were visible.
- Planned document content: the complete Sha Shib CAMPUS development roadmap below, formatted as a standalone Markdown project plan, including project identity/location, phases and exit gates, architecture, relevant implementation areas, verification checklist, and open decisions/boundaries.
- Current mode is planning-only; do not create or edit the workspace file in this turn. The target document is ready for implementation handoff.


## Plan: Sha Shib CAMPUS Development

Greenfield development plan for **Sha Shib Centralised Academic Management & Performance Unified System (Sha Shib CAMPUS)** as a standalone Laravel + Livewire application in a new folder named **Sha Shib CAMPUS** under the Laravel Service workspace. Use the supplied CAMP Part 1/Part 2 specification as the business source of truth; keep technical recommendations and unresolved management decisions explicitly separate. No implementation or scaffolding is included in this planning stage.

**Steps**

### Phase 0 — Scope, decisions, and acceptance baseline
1. Treat the user-provided CAMP Part 1/Part 2-derived plan as the source boundary. Convert each business rule, workflow, role, report, and retention obligation into traceable requirements and acceptance tests; do not invent business rules.
2. Approve initial platform choices before project setup: exact Laravel/PHP versions, MySQL/MariaDB version, authentication provider, SMS/email vendors, storage, hosting, backup/DR, institution list/codes, external approval authorities, online-exam process, record retention, and physical-signature verification.
3. Resolve data and workflow ambiguities before the affected modules: whether student/ER/marksheet identifiers are global or institution-scoped; role multiplicity and cross-institution access; ER and exam approval ordering and rejection/resubmission behavior; attendance eligibility calculation for the 75% rule (source says 75% but does not define denominator/exceptions); marks correction and result republishing rules; DMS 7-Day Rule calendar/business-day and escalation semantics; MoU alert recipients/repeat policy; and which CAMP rules are configurable.
4. Define module owners, UAT users, migration/import needs, and a signed-off acceptance gate for each phase.

### Phase 1 — Foundation and security baseline
5. Create an independent Laravel project in the selected Sha Shib CAMPUS folder, configure environments, coding standards, automated tests, CI/deployment conventions, and database migrations.
6. Implement institutions, users, roles, permissions, user-role and role-permission mappings, institution scope, authentication, authorization policies, and initial seed data. Use a modular monolith, Blade + Livewire, and Tailwind as recommendations pending approval.
7. Add a central audit service/table before operational workflows. Establish private file storage, upload validation, authorization, queue infrastructure, scheduler setup, secrets management, and backup/restore procedure.
8. **Exit gate:** login and permission tests pass; cross-institution access is denied by default; sensitive state changes create actor/time audit records; deployment can restore a tested backup.

### Phase 2 — Academic masters and student lifecycle
9. Implement academic years, programs, semesters, subjects, syllabus/version records, and syllabus-subject mappings, including institution scoping and configurable thresholds.
10. Implement student profiles, parent/guardian records, private KYC/document uploads, checklist/verification state, and student access boundaries.
11. **Exit gate:** master-data and student UAT; validation, duplicate identifier policy, document access, and institution isolation tests pass.

### Phase 3 — ER, ID card, and academic evidence
12. Implement ER request, Admin and Accounts approval gates, ER number allocation only after both approvals, TM signature checkpoint, request/archive references, and ID card lifecycle. Keep audit and notification actions within service-layer transactions.
13. Implement attendance and lesson-plan evidence needed for TM verification, using the approved student/subject/semester model.
14. **Exit gate:** every approval permutation is tested; ER cannot be issued with either gate pending; identifier uniqueness is enforced at the database level; ID-card and evidence UAT passes.

### Phase 4 — Examination and question bank
15. Implement exam setup, subject mappings, student applications, Accounts/TM approval gates, BiC override with mandatory reason/supporting evidence, and admit-card generation after the approved gates/signatures.
16. Build question topics/paras, tagged questions, imports, and exam-question assignment. This work can proceed in parallel with exam workflow after Phase 2 data contracts are agreed.
17. **Exit gate:** pending/denied gate blocks admit cards; unauthorized approvals and BiC overrides are rejected; question tagging and exam assignment UAT passes.

### Phase 5 — Assessment and results
18. Implement objective/descriptive/practical marks entry, validation, submission, result calculation/publication, correction governance, marksheets, consolidated marksheets, and centrally generated unique serials. Implement the 75% rule only after its calculation and exception policy are approved.
19. **Exit gate:** calculation fixtures and boundary cases pass; non-Super Admin post-submission correction is denied; serial duplication is blocked by a unique constraint; publication, signature, and reissue flows are accepted.

### Phase 6 — DMS and MoU (parallel modules)
20. DMS: document metadata, versions, private file references, HoT review/return/approval history, external submission/approval tracking, final signed archive, compliance record, and scheduled 7-Day Rule checks/alerts.
21. MoU: accessors, institute scope, training scope, validity, supporting document, expiry status, and scheduled 60-day alert records.
22. These two modules can be developed in parallel once foundation authorization, audit, storage, queue, and scheduler contracts are stable.
23. **Exit gate:** version/history and approval audit is preserved; DMS due-date behavior is tested at the agreed boundary; MoU 60-day alerts go only to approved recipients and are not duplicated contrary to policy.

### Phase 7 — Notifications, compliance, and reporting
24. Add event-driven in-app/email/SMS delivery through queued jobs and templates; record delivery outcomes. Implement compliance dashboard, pending approvals, revisions, MoU expiry, audit readiness, and approved student/exam/result/DMS/MoU/audit reports with required exports.
25. **Exit gate:** retries/failures are observable; role-scoped dashboard/report results are verified; exports respect permissions and institution scope.

### Phase 8 — Integrated UAT, hardening, and go-live
26. Run end-to-end role-based journeys across admission → ER → examination → marks/result → marksheet, and DMS/MoU compliance journeys. Complete negative authorization, concurrency/unique-number, file security, scheduler/queue, and audit tests.
27. Conduct performance/load tests against agreed volumes; verify monitoring, backup/restore, DR, deployment rollback, migration strategy, retention, training, and operational runbooks.
28. Fix UAT defects, obtain management and technical sign-off, then deploy through a staged release with post-launch monitoring.

**Architecture and reusable patterns**
- Recommended modular monolith with Livewire components as UI only; put business rules in domain services and enforce authorization both at routes and action/policy boundaries.
- Reuse shared institution scoping, enums/status transitions, audit logging, notification events, private file handling, and service-level transaction boundaries.
- Suggested domains: Foundation/Access, Academics, Students/ER, Attendance, Examinations, Question Bank, Results, DMS, MoU, Notifications, Compliance/Reports.
- Apply database foreign keys and indexes; unique constraints for approved identifiers and marksheet serials; use soft deletes only where retention policy allows. Keep approval actor, action/status, timestamp, and reason in durable history.

**Relevant implementation areas**
- New standalone project root: Sha Shib CAMPUS under the Laravel Service workspace (exact folder selected by user).
- Planned Laravel areas: migrations/seeders/factories; Eloquent models/enums; policies; domain services; jobs/events/notifications; Livewire Admin components and Blade views; routes; feature/unit tests; deployment and operations configuration.
- Do not extend the currently open KPI-Dashboard project unless separately requested.

**Verification**
1. Maintain traceability from each source requirement to migration/model, policy/service/UI, and automated test.
2. Run unit and feature tests by module and integrated test suite; include positive, negative, boundary, unauthorized, cross-institution, and concurrent identifier-allocation cases.
3. Explicitly verify: ER needs Admin + Accounts; exam admit card needs approved gates; BiC override requires reason; only Super Admin may make approved post-submission correction; 75% eligibility boundary per approved definition; duplicate marksheet serial rejection; DMS signed-scan 7-day flag; MoU alert within 60 days; Institution A cannot read Institution B records; approval audits record actor/time.
4. Manually UAT all role-specific workflows and inspect private file access, generated PDFs, exports, notifications, scheduler behavior, backup restoration, and rollback.

**Decisions and boundaries**
- Product name: Sha Shib Centralised Academic Management & Performance Unified System; short name: Sha Shib CAMPUS.
- Greenfield, standalone Laravel + Livewire application in a separate folder under Laravel Service; not a modification to existing applications.
- Business rules come from the supplied CAMP Part 1/Part 2 content. Laravel modular monolith, Tailwind, MySQL/MariaDB, auth provider, vendors, and hosting are recommendations/open approvals—not source-defined requirements.
- Included: phased development plan, data/domain coverage, security, testing, deployment, and sign-off gates. Excluded: creating files/project, selecting vendors or final platform versions without approval, inventing missing business rules, and importing/migrating legacy data until assessed.
