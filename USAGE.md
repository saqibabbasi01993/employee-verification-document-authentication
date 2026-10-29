# Employee Verification & Document Authentication System
### WordPress Plugin — v1.0.0

---

## ✅ Installation (Plug & Play)

1. **Zip the plugin folder** — right-click `employee-verification/` → Compress to ZIP.
2. In your WordPress Admin go to **Plugins → Add New → Upload Plugin**.
3. Upload the ZIP and click **Activate Plugin**.
4. The database tables are created automatically on activation.

---

## ⚙️ Initial Setup

### Step 1 — Create Verification Pages
Create two WordPress pages:

| Page Title | Shortcode to add in the page body |
|---|---|
| Employee Verification | `[employee_verification]` |
| Document Verification | `[document_verification]` |

### Step 2 — Configure Settings
Go to **Employee Verify → Settings** and:
- Upload your company logo (used in generated PDF letters)
- Select the two verification pages you just created

---

## 🔑 Admin Features (Employee Verify menu in WP Dashboard)

### Dashboard
- Overview stats (total employees, total documents issued)
- Quick links to all sections

### Employees
- Add, edit, delete employees
- Upload profile photo via WordPress Media Library
- Set status: **Active / Intern / Former Employee**
- **Generate Internship Completion Letter** (for Intern employees)
- **Generate Experience Letter** (for any employee)
- **Bulk CSV Import** — download the template below

### Documents
- View all issued documents
- Revoke documents (marks as invalid)
- Delete document records
- Download generated PDF letters
- View live verification link

### Settings
- Company logo upload
- Page assignments for shortcodes

---

## 📄 Shortcodes

### `[employee_verification]`
Public-facing employee lookup form. Visitors enter an Employee ID and see:
- Employee name, ID, position, department, joining date, status, photo

**Usage:** Add this shortcode to any page.

### `[document_verification]`
Public-facing document verification form. Visitors enter a verification ID and see:
- Document type, employee name, issue date, validity status, QR code, download link

Also works with direct URL: `yoursite.com/document-verification/?id=VERIFICATION_ID`

---

## 📦 CSV Bulk Import Format

Create a `.csv` file with these column headers:

```
employee_id,full_name,position,department,joining_date,intern_start,intern_end,status
EMP-001,John Smith,Software Engineer,IT,2023-01-15,,,Active
EMP-002,Jane Doe,Marketing Manager,Marketing,2022-06-01,,,Active
INT-001,Ali Hassan,UI/UX Design Intern,Design,2024-01-01,2024-01-01,2024-07-01,Intern
```

- `joining_date`, `intern_start`, `intern_end` → format `YYYY-MM-DD` (leave blank if not applicable)
- `status` → must be one of: `Active`, `Intern`, `Former Employee`

---

## 🔒 Security Features

- WordPress nonce verification on all forms
- Sanitized and validated inputs on all fields
- Admin-only access for all management features
- Non-guessable 16-character hex verification IDs (cryptographically random)
- SQL injection protection via `$wpdb->prepare()`
- `.htaccess` protection on uploaded PDF directory

---

## 📑 PDF Generation

### Option A (Recommended) — Install TCPDF via Composer
For professional PDF output, run this in the plugin folder:
```bash
composer require tecnickcom/tcpdf
```
Then the plugin will automatically use TCPDF.

### Option B — HTML Fallback (Works without Composer)
If TCPDF is not installed, the plugin generates a printer-friendly HTML page.
- Users can open it and use **Ctrl+P → Save as PDF** (or the Print button on the page)
- Looks identical to a PDF when printed

---

## 🔗 QR Codes

QR codes are generated via the **Google Charts API** (no library needed, requires server internet access).

For offline/air-gapped servers, install the QR library via Composer:
```bash
composer require endroid/qr-code
```

---

## 📁 Plugin File Structure

```
employee-verification/
├── employee-verification.php       ← Main plugin file
├── README.md
├── includes/
│   ├── class-ev-database.php       ← DB schema & install
│   ├── class-ev-employee.php       ← Employee CRUD
│   ├── class-ev-document.php       ← Document management
│   ├── class-ev-pdf.php            ← PDF generation
│   ├── class-ev-qrcode.php         ← QR code generation
│   └── class-ev-shortcodes.php     ← Frontend shortcodes
├── admin/
│   └── class-ev-admin.php          ← Admin dashboard UI
├── assets/
│   ├── css/
│   │   ├── admin.css
│   │   └── public.css
│   └── js/
│       └── admin.js
└── vendor/                         ← (optional, after Composer install)
```

---

## ❓ Troubleshooting

| Issue | Fix |
|---|---|
| Tables not created | Deactivate and reactivate the plugin |
| PDFs not generating | Check the `/wp-content/uploads/ev-documents/` folder is writable (chmod 755) |
| QR code not showing | Ensure your server has outbound internet access (for Google Charts API) |
| Logo not showing in letters | Go to Settings and re-upload the company logo |
