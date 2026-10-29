=== Employee Verification & Document Authentication ===
Contributors:      skfreelancers, saqibabbasi
Tags:              employee verification, document authentication, HR letters, experience letter, internship letter
Requires at least: 5.8
Tested up to:      7.1
Stable tag:        1.4.1
Requires PHP:      7.4
License:           GPLv2 or later
License URI:       https://www.gnu.org/licenses/gpl-2.0.html

Complete HR document management — manage employees, issue QR-verified letters, and let anyone verify authenticity using simple shortcodes.

== Description ==

**Employee Verification & Document Authentication** is a complete HR document management system for WordPress. Issue tamper-proof employee letters with unique verification IDs and QR codes, then let anyone verify their authenticity through a simple public-facing page — no login required.

Built for HR teams, staffing agencies, and businesses of any size. Every letter renders live on demand, so updates to your branding, colours, or templates are instantly reflected on all existing documents — no regeneration needed.

= Employee Management =

* Add, edit, and delete employee profiles with photos
* Track Active, Intern, and Former Employee statuses
* Store joining date, leaving date, internship period, CNIC, father name, and tasks completed
* **Filter employees** by department, status, or search term with a modern filter toolbar
* **Export employees to CSV** — applies active filters; UTF-8 BOM included for Excel compatibility
* **Bulk import employees via CSV** — template provided
* Custom fields system — define global fields and fill values per employee

= Letter Generation =

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

= Dynamic Letter Types (Other Letters) =

Create and manage unlimited letter types from **Employee Verify → Settings → Other Letters** without touching any code:

* **Letter Name & Slug** — displayed in the employee dropdown menu
* **Letter Template** — full HTML editor with placeholder support
* **Extra Fields** — define letter-specific fields (e.g. "Monthly Salary", "Bank Name") that appear in a modal when generating; each field becomes a `{placeholder}` in the template
* **Email Subject & Body** — per-letter-type email content
* **Status** — Active or Inactive (inactive types are hidden from the dropdown)
* **Allow Multiple** — control whether multiple letters of the same type can be issued to one employee
* **Reset Templates to Default** — one-click button to restore all professional HR templates

= Verification System =

* Every letter gets a unique Verification ID (cryptographically secure)
* QR code on each letter links directly to the document verification page
* `[evda_employee_verification]` — public employee lookup shortcode
* `[evda_document_verification]` — public letter verification shortcode (also works via URL: `?id=VERIFICATION_ID`)
* Verified badge, employee details, and download link shown on successful verification

= Letter Customization (Settings) =

* Company logo displayed on all letters
* Full typography control: font family, size, line height, colours
* Editable letter body templates with `{placeholder}` support
* Optional signature block: signatory name, title, and uploaded signature image
* Custom footer message (office address, phone, website)
* Toggle: QR code, border/shadow, title underline
* All styling changes apply instantly to all existing letters — no regeneration needed
* Live letter preview in Settings → Styling

= Email Notifications =

* Auto-email employees when any letter is issued
* Per-letter-type configurable subject and body templates
* Placeholder support: `{employee_name}`, `{company}`, `{letter_type}`, `{verify_url}`, and more
* Email logs (last 100 send attempts)
* Test email button

= Security =

* All AJAX endpoints protected by `check_ajax_referer()` + `current_user_can()`
* All forms use `wp_nonce_field()` + `check_admin_referer()`
* All database queries use `$wpdb->prepare()`
* All inputs sanitized with appropriate functions (`sanitize_text_field`, `absint`, `sanitize_email`, etc.)
* All output escaped with `esc_html()`, `esc_attr()`, `esc_url()`, `wp_kses()`, or `wp_kses_post()`
* Public endpoints secured via HMAC token (`wp_hash`) — no user session required

= Shortcodes =

`[evda_employee_verification]` — Public employee lookup form.
`[evda_document_verification]` — Public document verification form. Also works via URL: `?id=VERIFICATION_ID`

= Placeholder Reference =

The following placeholders are supported in all letter templates and email bodies:

`{employee_name}` `{employee_id}` `{father_name}` `{cnic}` `{position}` `{department}` `{company}` `{joining_date}` `{leaving_date}` `{intern_start}` `{intern_end}` `{issue_date}` `{verification_id}` `{verify_url}` `{letter_type}` `{tasks_section}` `{custom_fields}`

For Other Letters, each extra field you define also becomes a placeholder automatically — e.g. defining a field with key `salary` adds the `{salary}` placeholder.

= Privacy =

This plugin stores employee data entered by the site administrator. No data is sent to external services except for QR code generation (uses a public QR API or falls back to a local GD-based generator). No tracking or analytics are included.

== Installation ==

1. Upload the `employee-verification-document-authentication` folder to `/wp-content/plugins/`, or install via **Plugins → Add New → Upload Plugin**.
2. Activate the plugin. Database tables are created automatically.
3. Create two WordPress pages and add shortcodes:
   * A page with `[evda_employee_verification]`
   * A page with `[evda_document_verification]`
4. Go to **Employee Verify → Settings → General** and assign those pages.
5. Upload your company logo and configure styling as desired.
6. Go to **Employee Verify → Settings → Other Letters** to review or customise the built-in letter types and their templates.
7. Add employees under **Employee Verify → Employees** and start generating letters.

== Frequently Asked Questions ==

= Do I need any extra libraries or Composer? =
No. The plugin works out of the box with no dependencies. Letters are rendered as printer-friendly HTML pages which users can save as PDF using Ctrl+P → Save as PDF.

= Are generated letters affected by settings changes? =
Yes. Letters are rendered live on every view, so any change to typography, colours, signature, footer message, or other settings is immediately reflected on all existing letters without needing to regenerate them.

= How do I add a new letter type? =
Go to **Employee Verify → Settings → Other Letters** and click **"+ Add New Letter Type"**. Enter the letter name, template, and any extra fields you need. The new type will appear immediately in the "Other Letters" dropdown on the Employees page.

= What are extra fields in a letter type? =
Extra fields are letter-specific inputs that the admin fills in when generating a letter. For example, a Salary Certificate might have a "Monthly Salary" extra field. The value entered is automatically injected into the letter template via its `{placeholder}`. You define both the label and the placeholder key when creating the letter type.

= Can I issue multiple letters of the same type to one employee? =
It depends on the letter type. Internship and Experience letters enforce one per employee. For Other Letters, you can toggle "Allow Multiple" when creating or editing the letter type — useful for types like Salary Certificate or Warning Letter.

= Is the verification system secure? =
Verification IDs are 16-character uppercase hex strings generated using PHP's `random_bytes()` (cryptographically secure). All inputs are sanitized and validated. All admin actions are protected by WordPress nonces and capability checks. Database queries use `$wpdb->prepare()`.

= Can I reset the letter templates to the professional defaults? =
Yes. Go to **Employee Verify → Settings → Other Letters** and click **"🔄 Reset Templates to Default"**. This restores all letter templates and email content to the built-in professional HR content without affecting your letter type definitions or settings.

= Can I search and filter employees? =
Yes. The Employees page has a filter toolbar with a search box, department dropdown, and status dropdown. All filters can be combined. Active filters are shown as badges with a count of matching employees.

= Can I export employee data? =
Yes. Use the **Export CSV** button on the Employees page. The export respects any active search and filter settings, so you can export only the employees matching your current view.

= Can I bulk-import employees? =
Yes. Use the **Import CSV** button on the Employees page. The CSV should include headers: `employee_id, full_name, position, department, joining_date, intern_start, intern_end, status`.

= What happens to data when I deactivate the plugin? =
Deactivating the plugin preserves all employee, document, and settings data. Data is only removed if you fully uninstall (delete) the plugin.

== Screenshots ==

1. Employee list with filter toolbar, Other Letters dropdown, and Export CSV button
2. Other Letters generate modal with dynamic extra fields
3. Settings → Other Letters tab — manage dynamic letter types
4. Letter Type Editor — name, template, email settings, and extra fields
5. Generated letter (A4, print-ready) with QR code and verification ID
6. Public document verification page — verified badge and employee details
7. Add/Edit employee form
8. Settings — Styling tab with typography, colours, and live preview

== External Services ==

This plugin optionally connects to two external QR code generation APIs when generating QR codes for employee letters. These APIs are only called **server-side** (never from the visitor's browser).

= 1. goQR.me API =
* **What it does:** Generates a QR code PNG image for a verification URL.
* **When it is called:** When a letter is being served and the PHP GD extension is unavailable.
* **Data sent:** The public verification URL only (e.g. `https://yoursite.com/?evda_verify_doc=XXXX`). No personal data is transmitted.
* **Service provider:** goQR.me — https://goqr.me
* **Terms of use:** https://goqr.me/terms-of-use/
* **Privacy policy:** https://goqr.me/privacy-policy/

= 2. Google Charts API =
* **What it does:** Fallback QR code PNG generation if the goQR.me API is unavailable.
* **When it is called:** Only if the goQR.me API call fails and PHP GD is unavailable.
* **Data sent:** The public verification URL only. No personal data is transmitted.
* **Service provider:** Google LLC — https://developers.google.com/chart
* **Terms of use:** https://developers.google.com/terms
* **Privacy policy:** https://policies.google.com/privacy

If both external APIs are unavailable, the plugin automatically falls back to a locally-generated placeholder using the PHP GD library — no external request is made.

== Changelog ==

= 1.4.1 =
* Fix: Removed extra top margin on the Delete buttons in Settings → Other Letters so they align with the Edit buttons.

= 1.4.0 =
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

= 1.3.4 =
* Fixed: All Plugin Check SQL warnings resolved — rewrote EVDA_Employee::get_all() and count() using explicit hardcoded query-per-filter-combination approach.
* Fixed: WordPress.DB.PreparedSQL warnings (NotPrepared, InterpolatedNotPrepared, ReplacementsWrongNumber, UnfinishedPrepare, UnescapedDBParameter) all cleared.
* Fixed: Translators comment added to employee count string in filter bar.
* Improvement: Employee filter and export fully compliant with WordPress Plugin Check (PCP).

= 1.3.3 =
* New: Employee filter bar — filter by Department and Status with active filter badges.
* New: Export Employees CSV — exports all employees matching current filters with one click.
* Improvement: Filters persist across pagination pages.
* Improvement: Modern toolbar UI with inputs, dropdowns, and action buttons.
* Security: Export AJAX handler uses check_ajax_referer() + current_user_can() + whitelisted status values.

= 1.3.2 =
* Security: Complete nonce and permissions audit — all AJAX handlers verified; nonce never used alone for authorization.
* Security: Full output escaping audit — all echo statements use appropriate escaping functions.
* Security: DB error messages no longer exposed to browser.
* Fixed: QR code not displaying on document verification page.
* Fixed: Added guaranteed base64 fallback so QR code area never renders as broken image.

= 1.3.1 =
* Fixed: wp_enqueue compliance — removed all inline script/style tags.
* Fixed: Nonces and permissions — added current_user_can() + wp_verify_nonce() to page_employees().
* Fixed: Escaping — all echo statements now use proper escaping functions.
* Fixed: Unsafe SQL — all table name variables wrapped with esc_sql(validate_table()); dbDelta() used for schema management.
* Fixed: QR code domain validation corrected.
* Fixed: Removed empty unused folders.

= 1.3.0 =
* wp_enqueue compliance: Removed all inline script and style tags.
* Nonces and permissions: Added current_user_can() + wp_verify_nonce() throughout.
* Escaping: Replaced echo build_letter() with dedicated EVDA_PDF::output_letter() method.
* Unsafe SQL: Replaced raw wpdb->query() with dbDelta() for schema management.
* Prefixing: All ev_/EV_ identifiers renamed to evda_/EVDA_.

= 1.2.7 =
* Added saqibabbasi to Contributors list.
* Replaced inline style block in letter renderer with a PHP variable.
* Added server-side hash token validation to the QR image AJAX endpoint.
* Renamed all classes, functions, defines, AJAX actions, and option names from ev_/EV_ to evda_/EVDA_ prefix.

= 1.2.6 =
* Fixed last Plugin Check warning: added phpcs:ignore on is_uploaded_file() line.

= 1.2.5 =
* Fixed all remaining Plugin Check warnings: wp_unslash() on email color fields, phpcs blocks on multi-line DB queries, input sanitization for array_map patterns.

= 1.2.4 =
* Fixed admin Settings page tabs not working after JS refactor.

= 1.2.3 =
* Added proper evda_ prefix to shortcodes.
* Moved all inline script and style blocks to enqueued files.
* Removed external CDN dependency (qrcodejs); QR codes generated server-side only.
* Added External Services documentation for QR API usage disclosure.

= 1.2.2 =
* Fixed all Plugin Check warnings: wp_unslash(), esc_html/attr/url output escaping, isset() validation for POST/FILES.

= 1.1.7 =
* Centered footer text on letters.
* Updated plugin header to WordPress repository standards.

= 1.1.6 =
* Tasks/responsibilities now display in a 3-column grid layout on letters.

= 1.1.5 =
* Fixed live letter URL containing dynamic: prefix causing Document not found error.

= 1.1.4 =
* Major: Letters now render live on every view — settings changes apply instantly to all existing letters.
* No more static HTML files stored on disk.
* Added one-click migration tool for existing static letters.

= 1.1.3 =
* Fixed document delete not removing the file from disk.
* Fixed patch counter reporting wrong number.

= 1.1.2 =
* Footer now always pinned to the bottom of the A4 page using flexbox.
* Added custom footer message field.

= 1.1.1 =
* Fixed fatal error on plugin deactivation.

= 1.1.0 =
* Fixed WordPress admin footer appearing inside settings panel.
* Letters now fixed at A4 size (794px x 1123px screen, 210mm x 297mm print).

= 1.0.9 =
* Added signature block setting: enable/disable, name, title, and uploaded image.
* Added one-click tool to remove old Authorized Signatory block from existing letters.

= 1.0.8 =
* Added configurable employee search fields (Employee ID, Full Name, CNIC).

= 1.0.7 =
* Added Father Name, CNIC, Tasks Completed, and Leaving Date fields.
* Added custom fields system with per-employee values.
* Editable letter templates with full placeholder support.
* Settings page redesigned with tabbed layout.

== Upgrade Notice ==

= 1.4.1 =
Minor admin UI fix: Delete buttons in Settings → Other Letters now align with the Edit buttons.

= 1.4.0 =
New dynamic "Other Letters" system with 9 professional HR letter types. Visit Settings → Other Letters after upgrading to review templates and click "Reset Templates to Default" to apply professional HR content to all letter types.

= 1.1.4 =
Letters now render live. Use the "Convert to Live Rendering" button in Settings → Styling to migrate any existing static letters generated before this version.

== License ==

This plugin is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 2 of the License, or any later version.
