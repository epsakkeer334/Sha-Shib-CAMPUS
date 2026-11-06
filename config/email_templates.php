<?php

/*
|--------------------------------------------------------------------------
| Email templates — catalogue and default wording
|--------------------------------------------------------------------------
| Every email CAMP sends uses one of these keys (App\Services\EmailService). The text here is the
| built-in default; Super Admin can save a group-wide version and Institute Admins their own
| (Administration ▸ System ▸ Email Templates, table `email_templates`). Lookup order:
| institute version → group version → this default.
|
| Body format (kept simple so every email stays on the branded layout):
|   - {placeholder} is replaced by the value (see 'placeholders' + the common ones below)
|   - a blank line starts a new paragraph, a single line break stays a line break
|   - lines starting with "- " become a bulleted list, **text** is bold
| 'button' is an optional call-to-action [text, url] shown under the body.
*/

return [

    'groups' => [
        'admissions' => ['Admissions', 'ti ti-school'],
        'documents' => ['Documents', 'ti ti-file-check'],
        'payments' => ['Fees & payments', 'ti ti-cash'],
        'system' => ['System', 'ti ti-settings'],
    ],

    /** Available in every template. */
    'common_placeholders' => [
        'recipient_name' => 'Full name of the person receiving the email',
        'first_name' => 'First name of the recipient',
        'institute_name' => 'Institute name',
        'institute_email' => 'Institute email',
        'institute_phone' => 'Institute phone',
        'site_name' => 'System name (System Settings ▸ General)',
        'portal_url' => 'Link to the admissions portal',
        'support_email' => 'Support email (System Settings ▸ General)',
    ],

    /** Student placeholders, for templates sent to a student. */
    'student_placeholders' => [
        'student_name' => 'Student full name',
        'er_number' => 'ER number (empty until issued)',
        'course' => 'Course name',
        'deadline' => 'Onboarding deadline (date)',
    ],

    'templates' => [

        'er_issued' => [
            'name' => 'ER number issued',
            'group' => 'admissions',
            'audience' => 'student',
            'description' => 'Sent to the student when both gates are approved and the ER number is issued.',
            'placeholders' => [],
            'subject' => 'Your ER number: {er_number}',
            'body' => "Dear {first_name},\n\nCongratulations! Your documents and fees are verified and your admission to **{course}** at {institute_name} is confirmed.\n\nYour ER number is **{er_number}**. Please quote it in all communication with the institute.\n\nYour ID card will be ready once it is signed by the Training Manager.",
            'button' => ['View my application', '{portal_url}'],
        ],

        'application_rejected' => [
            'name' => 'Application returned for corrections',
            'group' => 'admissions',
            'audience' => 'student',
            'description' => 'Sent when the application is returned at Gate 1.',
            'placeholders' => ['reason' => 'Reason given by the reviewer'],
            'subject' => 'Your admission application needs corrections',
            'body' => "Dear {first_name},\n\nYour application to {institute_name} was returned for corrections.\n\nReason: {reason}\n\nPlease update your application and submit it again before {deadline}.",
            'button' => ['Update my application', '{portal_url}'],
        ],

        'document_accepted' => [
            'name' => 'Document accepted after re-review',
            'group' => 'documents',
            'audience' => 'student',
            'description' => 'Sent when a rejected document is approved after re-review.',
            'placeholders' => ['document' => 'Document name'],
            'subject' => 'Your {document} is accepted',
            'body' => "Dear {first_name},\n\nWe checked your **{document}** again and it is now accepted. You do not need to upload it again.",
            'button' => null,
        ],

        'document_rejected' => [
            'name' => 'Document rejected',
            'group' => 'documents',
            'audience' => 'student',
            'description' => 'Sent when a document is rejected at verification.',
            'placeholders' => ['document' => 'Document name', 'reason' => 'Reason given by the reviewer'],
            'subject' => 'Action needed: re-upload your {document}',
            'body' => "Dear {first_name},\n\nYour **{document}** could not be accepted.\n\nReason: {reason}\n\nPlease upload a new copy before {deadline}.",
            'button' => ['Upload documents', '{portal_url}'],
        ],

        'document_reminder' => [
            'name' => 'Pending documents reminder',
            'group' => 'documents',
            'audience' => 'student',
            'description' => 'Sent when staff send a reminder for missing documents.',
            'placeholders' => ['documents' => 'List of the missing documents'],
            'subject' => 'Reminder: documents pending for your admission',
            'body' => "Dear {first_name},\n\nTo complete your admission, please upload the following documents:\n\n{documents}\n\nComplete onboarding before {deadline}.",
            'button' => ['Upload documents', '{portal_url}'],
        ],

        'payment_received' => [
            'name' => 'Payment received',
            'group' => 'payments',
            'audience' => 'student',
            'description' => 'Sent when a payment is verified and the receipt is issued.',
            'placeholders' => ['amount' => 'Amount paid', 'fee' => 'Fee head', 'receipt' => 'Receipt number'],
            'subject' => 'Payment received — receipt {receipt}',
            'body' => "Dear {first_name},\n\nWe have received **{amount}** towards {fee}.\n\nReceipt number: **{receipt}**\n\nThank you for your payment.",
            'button' => ['View my payments', '{portal_url}'],
        ],

        'payment_rejected' => [
            'name' => 'Payment not confirmed',
            'group' => 'payments',
            'audience' => 'student',
            'description' => 'Sent when a submitted payment is rejected at verification.',
            'placeholders' => ['amount' => 'Amount', 'reference' => 'Transaction reference', 'reason' => 'Reason given by Accounts'],
            'subject' => 'Your payment could not be confirmed',
            'body' => "Dear {first_name},\n\nYour payment of **{amount}** (reference: {reference}) could not be confirmed.\n\nReason: {reason}\n\nPlease check the details and submit the payment again.",
            'button' => ['Go to payments', '{portal_url}'],
        ],

        'test_email' => [
            'name' => 'Test email',
            'group' => 'system',
            'audience' => 'any',
            'description' => 'Sent from System Settings ▸ Email to check that sending works.',
            'placeholders' => ['sent_at' => 'Date and time of sending'],
            'subject' => '{site_name} — test email',
            'body' => "Hello {first_name},\n\nThis is a test email from **{site_name}**. If you can read it, email sending through SMTP2GO works.\n\nSent on {sent_at}.",
            'button' => null,
        ],
    ],
];
