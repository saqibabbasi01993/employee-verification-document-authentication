# Employee Verification & Document Authentication

[![WordPress Plugin Version](https://img.shields.io/wordpress/plugin/v/employee-verification-document-authentication?label=version)](https://wordpress.org/plugins/employee-verification-document-authentication/)
[![Rating](https://img.shields.io/wordpress/plugin/rating/employee-verification-document-authentication)](https://wordpress.org/plugins/employee-verification-document-authentication/#reviews)
[![Downloads](https://img.shields.io/wordpress/plugin/dt/employee-verification-document-authentication)](https://wordpress.org/plugins/employee-verification-document-authentication/advanced/)
[![Requires WP](https://img.shields.io/wordpress/plugin/wp-version/employee-verification-document-authentication)](https://wordpress.org/plugins/employee-verification-document-authentication/)
[![Requires PHP](https://img.shields.io/wordpress/plugin/required-php/employee-verification-document-authentication)](https://wordpress.org/plugins/employee-verification-document-authentication/)
[![License: GPL v2+](https://img.shields.io/badge/License-GPLv2%2B-blue.svg)](LICENSE)

> Complete HR document management — manage employees, issue QR-verified letters, and let anyone verify authenticity using simple shortcodes.

**Download from WordPress.org:** https://wordpress.org/plugins/employee-verification-document-authentication/

## Description & Features

**Employee Verification & Document Authentication** is a complete HR document management system for WordPress. Issue tamper-proof employee letters with unique verification IDs and QR codes, then let anyone verify their authenticity through a simple public-facing page — no login required.

Built for HR teams, staffing agencies, and businesses of any size. Every letter renders live on demand, so updates to your branding, colours, or templates are instantly reflected on all existing documents — no regeneration needed.

### Employee Management
* Add, edit, and delete employee profiles with photos
* Track Active, Intern, and Former Employee statuses
* Store joining date, leaving date, internship period, CNIC, father name, and tasks completed
* **Filter employees** by department, status, or search term with a modern filter toolbar
* **Export employees to CSV** — applies active filters; UTF-8 BOM included for Excel compatibility
* **Bulk import employees via CSV** — template provided
* Custom fields system — define global fields and fill values per employee

### Letter Generation
**Built-in letter types (always available):**

* Internship Completion Letter
* Experience Letter

**Dynamic "Other Letters" system (new in v1.4.0):**

* Bank Account Opening Letter
* Salary Certificate
* Joining Letter / Letter of Appointment
* Promotion Letter
* Warning Letter
* Transfer Letter
* Relieving Letter
* No Objection Certificate (NOC)
* Custom Employee Letter (fully ad-hoc — enter title and content on the fly)
* Add your own unlimited custom letter types from Settings — no code required

All letters include:

* Unique 16-character cryptographically random Verification ID
* QR code linking to the public verification page
* Full company branding (logo, colours, typography, signature, footer)
* Live rendering — settings changes apply instantly to all existing letters
* Print / Save as PDF from the browser (A4 size, printer-ready)

### Dynamic Letter Types (Other Letters)
Create and manage unlimited letter types from **Employee Verify → Settings → Other Letters** without touching any code:

* **Letter Name & Slug** — displayed in the employee dropdown menu
* **Letter Template** — full HTML editor with placeholder support
* **Extra Fields** — define letter-specific fields (e.g. "Monthly Salary", "Bank Name") that appear in a modal when generating; each field becomes a `{placeholder}` in the template
* **Email Subject & Body** — per-letter-type email content
* **Status** — Active or Inactive (inactive types are hidden from the dropdown)
* **Allow Multiple** — control whether multiple letters of the same type can be issued to one employee
* **Reset Templates to Default** — one-click button to restore all professional HR templates

### Verification System
* Every letter gets a unique Verification ID (cryptographically secure)
* QR code on each letter links directly to the document verification page
* `[evda_employee_verification]` — public employee lookup shortcode
* `[evda_document_verification]` — public letter verification shortcode (also works via URL: `?id=VERIFICATION_ID`)
* Verified badge, employee details, and download link shown on successful verification

### Letter Customization (Settings)
* Company logo displayed on all letters
* Full typography control: font family, size, line height, colours
* Editable letter body templates with `{placeholder}` support
* Optional signature block: signatory name, title, and uploaded signature image
* Custom footer message (office address, phone, website)
* Toggle: QR code, border/shadow, title underline
* All styling changes apply instantly to all existing letters — no regeneration needed
* Live letter preview in Settings → Styling

### Email Notifications
* Auto-email employees when any letter is issued
* Per-letter-type configurable subject and body templates
* Placeholder support: `{employee_name}`, `{company}`, `{letter_type}`, `{verify_url}`, and more
* Email logs (last 100 send attempts)
* Test email button

### Security
* All AJAX endpoints protected by `check_ajax_referer()` + `current_user_can()`
* All forms use `wp_nonce_field()` + `check_admin_referer()`
* All database queries use `$wpdb->prepare()`
* All inputs sanitized with appropriate functions (`sanitize_text_field`, `absint`, `sanitize_email`, etc.)
* All output escaped with `esc_html()`, `esc_attr()`, `esc_url()`, `wp_kses()`, or `wp_kses_post()`
* Public endpoints secured via HMAC token (`wp_hash`) — no user session required

### Shortcodes
`[evda_employee_verification]` — Public employee lookup form.
`[evda_document_verification]` — Public document verification form. Also works via URL: `?id=VERIFICATION_ID`

### Placeholder Reference
The following placeholders are supported in all letter templates and email bodies:

`{employee_name}` `{employee_id}` `{father_name}` `{cnic}` `{position}` `{department}` `{company}` `{joining_date}` `{leaving_date}` `{intern_start}` `{intern_end}` `{issue_date}` `{verification_id}` `{verify_url}` `{letter_type}` `{tasks_section}` `{custom_fields}`

For Other Letters, each extra field you define also becomes a placeholder automatically — e.g. defining a field with key `salary` adds the `{salary}` placeholder.

### Privacy
This plugin stores employee data entered by the site administrator. No data is sent to external services except for QR code generation (uses a public QR API or falls back to a local GD-based generator). No tracking or analytics are included.

## Installation

1. Upload the `employee-verification-document-authentication` folder to `/wp-content/plugins/`, or install via **Plugins → Add New → Upload Plugin**.
2. Activate the plugin. Database tables are created automatically.
3. Create two WordPress pages and add shortcodes:
   * A page with `[evda_employee_verification]`
   * A page with `[evda_document_verification]`
4. Go to **Employee Verify → Settings → General** and assign those pages.
5. Upload your company logo and configure styling as desired.
6. Go to **Employee Verify → Settings → Other Letters** to review or customise the built-in letter types and their templates.
7. Add employees under **Employee Verify → Employees** and start generating letters.

## FAQ (highlights)

### Do I need any extra libraries or Composer?
No. The plugin works out of the box with no dependencies. Letters are rendered as printer-friendly HTML pages which users can save as PDF using Ctrl+P → Save as PDF.

### Are generated letters affected by settings changes?
Yes. Letters are rendered live on every view, so any change to typography, colours, signature, footer message, or other settings is immediately reflected on all existing letters without needing to regenerate them.

### How do I add a new letter type?
Go to **Employee Verify → Settings → Other Letters** and click **"+ Add New Letter Type"**. Enter the letter name, template, and any extra fields you need. The new type will appear immediately in the "Other Letters" dropdown on the Employees page.

### What are extra fields in a letter type?
Extra fields are letter-specific inputs that the admin fills in when generating a letter. For example, a Salary Certificate might have a "Monthly Salary" extra field. The value entered is automatically injected into the letter template via its `{placeholder}`. You define both the label and the placeholder key when creating the letter type.

### Can I issue multiple letters of the same type to one employee?
It depends on the letter type. Internship and Experience letters enforce one per employee. For Other Letters, you can toggle "Allow Multiple" when creating or editing the letter type — useful for types like Salary Certificate or Warning Letter.



See the full FAQ on [WordPress.org](https://wordpress.org/plugins/employee-verification-document-authentication/#faq).

## Detailed usage

See [USAGE.md](USAGE.md).

## Changelog (recent)

### 1.4.1
* Fix: Removed extra top margin on the Delete buttons in Settings → Other Letters so they align with the Edit buttons.

### 1.4.0
* New: Dynamic "Other Letters" system — create unlimited custom letter types from Settings → Other Letters without writing any code.
* New: 9 professional HR letter types included out of the box: Bank Account Opening Letter, Salary Certificate, Joining Letter, Promotion Letter, Warning Letter, Transfer Letter, Relieving Letter, No Objection Certificate, and Custom Employee Letter.
* New: Each letter type supports custom extra fields — define fields like "Monthly Salary" or "Bank Name" that appear in a modal when generating; values become {placeholders} in the template automatically.
* New: "Other Letters ▾" dropdown on the Employees page — dynamically shows all active letter types; adding a new type in Settings makes it appear immediately with no code changes.
* New: Letter Type Editor modal in Settings → Other Letters — configure name, slug, status, allow-multiple, letter template, email subject/body, and extra fields.
* New: "Reset Templates to Default" button — one click restores all 9 professional HR letter templates and email content.
* New: Custom Employee Letter type — enter a custom title and full letter content when generating; all standard placeholders work.
* New: All Other Letters are saved to the Documents system with full verification IDs, QR codes, and public verification support.
* New: Auto-email support for all Other Letter types using per-type email subject and body.
* Improvement: Employee filter toolbar — filter by Department and Status with active filter badges showing result count.
* Improvement: Export Employees CSV — respects active search/filter settings; UTF-8 BOM for Excel compatibility.
* Improvement: document_type column increased to VARCHAR(100); notes column changed to TEXT for larger JSON payloads.
* Security: All new AJAX endpoints (generate, save, delete letter type; export employees) protected by check_ajax_referer() + current_user_can('manage_options').
* Security: All new POST inputs unslashed and sanitized via dedicated helper methods.
* Backward compatible: All existing Internship Letter and Experience Letter functionality is unchanged.

### 1.3.4
* Fixed: All Plugin Check SQL warnings resolved — rewrote EVDA_Employee::get_all() and count() using explicit hardcoded query-per-filter-combination approach.
* Fixed: WordPress.DB.PreparedSQL warnings (NotPrepared, InterpolatedNotPrepared, ReplacementsWrongNumber, UnfinishedPrepare, UnescapedDBParameter) all cleared.
* Fixed: Translators comment added to employee count string in filter bar.
* Improvement: Employee filter and export fully compliant with WordPress Plugin Check (PCP).

### 1.3.3
* New: Employee filter bar — filter by Department and Status with active filter badges.
* New: Export Employees CSV — exports all employees matching current filters with one click.
* Improvement: Filters persist across pagination pages.
* Improvement: Modern toolbar UI with inputs, dropdowns, and action buttons.
* Security: Export AJAX handler uses check_ajax_referer() + current_user_can() + whitelisted status values.



Full changelog: [readme.txt](readme.txt).

## Author

Developed by **[SK Freelancers](https://skfreelancers.com)** — [WordPress.org profile](https://profiles.wordpress.org/saqibabbasi/).

## License

GPLv2 or later. See [LICENSE](LICENSE).
