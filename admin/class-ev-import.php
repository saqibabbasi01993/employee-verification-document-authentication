<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Handles importing existing/legacy letters by manual entry.
 * Admins can register a verification ID (or URL) for letters
 * that were already issued outside the plugin so they become verifiable.
 */
class EVDA_Import {

    public static function register_hooks() {
        add_action( 'wp_ajax_evda_import_manual', array( __CLASS__, 'ajax_import_manual' ) );
        add_action( 'wp_ajax_evda_import_manual_csv', array( __CLASS__, 'ajax_import_manual_csv' ) );
    }

    // =========================================================================
    // Admin page
    // =========================================================================
    public static function render_page() {
        global $wpdb;
        $table     = esc_sql( EVDA_Database::validate_table( EVDA_Database::documents_table() ) );
        $emp_table = esc_sql( EVDA_Database::validate_table( EVDA_Database::employees_table() ) );

        // Fetch manually imported docs. Table names are whitelisted via validate_table() + esc_sql().
        // No user-supplied values in this query — no prepare() args needed.
        $manual_docs = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            "SELECT d.*, e.full_name FROM `{$table}` d LEFT JOIN `{$emp_table}` e ON d.employee_id = e.employee_id WHERE d.is_manual = 1 ORDER BY d.created_at DESC LIMIT 50"
        );
        ?>
        <div class="wrap ev-admin-wrap">
            <h1>Import Existing Letters</h1>
            <p class="evda-import-desc">
                Use this page to register verification IDs for letters that were already issued
                <em>before</em> this plugin was installed — or letters issued manually (e.g. Word/PDF letters with their own reference numbers).
                Once imported, the verification ID or URL becomes verifiable on your Document Verification page.
            </p>

            <div class="evda-import-grid">

                <!-- Single import form -->
                <div class="ev-card">
                    <h2 class="evda-import-h2">➕ Add Single Letter Record</h2>
                    <table class="form-table evda-import-table">
                        <tr>
                            <th><label>Employee</label></th>
                            <td>
                                <input type="text" id="ev-imp-empid" class="regular-text" placeholder="Employee ID (e.g. EMP-001)">
                                <p class="description">Employee must exist in the system.</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label>Letter Type</label></th>
                            <td>
                                <select id="ev-imp-type">
                                    <option value="experience">Experience Letter</option>
                                    <option value="internship">Internship Completion Letter</option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th><label>Verification ID / Number</label></th>
                            <td>
                                <input type="text" id="ev-imp-vid" class="regular-text" placeholder="e.g. EXP-2024-001 or leave blank to auto-generate">
                                <p class="description">Enter your existing reference number, or leave blank to generate a new one.</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label>Issue Date</label></th>
                            <td><input type="date" id="ev-imp-date" class="regular-text" value="<?php echo esc_attr( gmdate('Y-m-d') ); ?>"></td>
                        </tr>
                        <tr>
                            <th><label>Leaving Date</label></th>
                            <td>
                                <input type="date" id="ev-imp-leaving" class="regular-text">
                                <p class="description">For experience letters only.</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label>Notes</label></th>
                            <td>
                                <input type="text" id="ev-imp-notes" class="regular-text" placeholder="Optional internal note">
                            </td>
                        </tr>
                    </table>
                    <div class="evda-import-actions">
                        <button class="button button-primary" id="ev-imp-submit">➕ Import Record</button>
                        <span id="ev-imp-result" class="evda-import-result"></span>
                    </div>
                </div>

                <!-- Bulk CSV import -->
                <div class="ev-card">
                    <h2 class="evda-import-h2">📋 Bulk Import via CSV</h2>
                    <p>Upload a CSV file to import multiple existing letter records at once.</p>
                    <p><strong>CSV columns:</strong></p>
                    <code class="evda-import-code">
                        employee_id, document_type, verification_id, issue_date, leaving_date, notes
                    </code>
                    <p class="evda-import-note">
                        • <code>document_type</code>: <code>experience</code> or <code>internship</code><br>
                        • <code>verification_id</code>: your existing reference (leave blank to auto-generate)<br>
                        • <code>issue_date</code>: format YYYY-MM-DD<br>
                        • <code>leaving_date</code>: for experience letters (YYYY-MM-DD or blank)
                    </p>
                    <input type="file" id="ev-imp-csv" accept=".csv" class="evda-import-file">
                    <button class="button button-primary" id="ev-imp-csv-btn">📤 Upload & Import</button>
                    <span id="ev-imp-csv-result" class="evda-import-csv-result"></span>

                    <hr class="evda-import-hr">
                    <p><a href="<?php echo esc_url( self::csv_template_url() ); ?>" class="button">⬇ Download CSV Template</a></p>
                </div>
            </div>

            <!-- Imported records table -->
            <h2 class="evda-import-records-h2">📄 Manually Imported Records</h2>
            <?php if ( empty( $manual_docs ) ) : ?>
                <p class="evda-import-none">No manually imported records yet.</p>
            <?php else : ?>
                <table class="widefat ev-table-list">
                    <thead>
                        <tr>
                            <th>Verification ID</th><th>Employee</th><th>Type</th>
                            <th>Issue Date</th><th>Notes</th><th>Status</th><th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ( $manual_docs as $doc ) :
                        $verify_url = EVDA_Document::get_verification_url( $doc->verification_id );
                    ?>
                        <tr>
                            <td><code><?php echo esc_html($doc->verification_id); ?></code></td>
                            <td><?php echo esc_html($doc->full_name ?? $doc->employee_id); ?></td>
                            <td><?php echo esc_html( ucfirst( $doc->document_type ) ); ?> Letter</td>
                            <td><?php echo esc_html(gmdate('d M Y', strtotime($doc->issue_date))); ?></td>
                            <td class="evda-import-notes-td"><?php echo esc_html($doc->notes ?? ''); ?></td>
                            <td><span class="ev-status <?php echo esc_attr( $doc->is_valid ? 'ev-status-active' : 'ev-status-invalid' ); ?>"><?php echo esc_html( $doc->is_valid ? 'Valid' : 'Revoked' ); ?></span></td>
                            <td class="ev-actions">
                                <a href="<?php echo esc_url($verify_url); ?>" target="_blank" class="button button-small">🔗 Verify</a>
                                <?php if ( $doc->is_valid ) : ?>
                                    <button class="button button-small ev-revoke-doc" data-vid="<?php echo esc_attr($doc->verification_id); ?>">Revoke</button>
                                <?php endif; ?>
                                <button class="button button-small button-link-delete ev-delete-doc" data-id="<?php echo absint( $doc->id ); ?>" data-label="this imported record">🗑</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
    }

    // =========================================================================
    // AJAX: single record import
    // =========================================================================
    public static function ajax_import_manual() {
        check_ajax_referer( 'evda_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );

        $employee_id = sanitize_text_field( wp_unslash( $_POST['employee_id'] ?? '' ) );
        $type        = sanitize_text_field( wp_unslash( $_POST['doc_type'] ?? 'experience' ) );
        $vid         = sanitize_text_field( wp_unslash( $_POST['verification_id'] ?? '' ) );
        $issue_date  = sanitize_text_field( wp_unslash( $_POST['issue_date'] ?? current_time('Y-m-d') ) );
        $leaving     = sanitize_text_field( wp_unslash( $_POST['leaving_date'] ?? '' ) );
        $notes       = sanitize_text_field( wp_unslash( $_POST['notes'] ?? '' ) );

        if ( ! $employee_id ) { wp_send_json_error( 'Employee ID is required.' ); return; }

        $employee = EVDA_Employee::get_by_employee_id( $employee_id );
        if ( ! $employee ) { wp_send_json_error( "No employee found with ID: {$employee_id}" ); return; }

        // Check if this type is already issued (plugin-generated or manually imported)
        $existing = EVDA_Document::get_existing( $employee_id, $type );
        if ( $existing ) {
            $label = $type === 'internship' ? 'Internship Completion Letter' : 'Experience Letter';
            wp_send_json_error( "A {$label} already exists for {$employee->full_name}. Delete it first." );
            return;
        }

        // Auto-generate verification ID if blank
        if ( ! $vid ) {
            $vid = strtoupper( bin2hex( random_bytes(8) ) );
        } else {
            // Ensure uniqueness
            global $wpdb;
            $table = EVDA_Database::documents_table();
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
            $clash = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE verification_id = %s", $vid ) );
            if ( $clash ) { wp_send_json_error( "Verification ID '{$vid}' is already in use. Please use a different one." ); return; }
        }

        global $wpdb;
        $table = esc_sql( EVDA_Database::validate_table( EVDA_Database::documents_table() ) );
        $result = $wpdb->insert( EVDA_Database::documents_table(), array( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            'verification_id' => $vid,
            'employee_id'     => $employee_id,
            'document_type'   => $type,
            'issue_date'      => $issue_date,
            'leaving_date'    => $leaving ?: null,
            'pdf_path'        => null, // no generated file for manual imports
            'is_valid'        => 1,
            'is_manual'       => 1,
            'notes'           => $notes,
        ) );

        if ( ! $result ) {
            wp_send_json_error( 'DB insert failed: ' . $wpdb->last_error );
            return;
        }

        $verify_url = EVDA_Document::get_verification_url( $vid );
        wp_send_json_success( array(
            'verification_id' => $vid,
            'verify_url'      => $verify_url,
            'employee_name'   => $employee->full_name,
            'message'         => "Record imported for {$employee->full_name}. Verification ID: {$vid}",
        ) );
    }

    // =========================================================================
    // AJAX: bulk CSV import
    // =========================================================================
    public static function ajax_import_manual_csv() {
        check_ajax_referer( 'evda_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );
        if ( ! isset( $_FILES['csv_file'] ) ) { wp_send_json_error( 'No file.' ); return; }

        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.MissingUnslash
        if ( ! isset( $_FILES['csv_file']['tmp_name'] ) || ! is_uploaded_file( $_FILES['csv_file']['tmp_name'] ) ) {
            wp_send_json_error( 'Invalid file upload.' ); return;
        }
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.MissingUnslash
        $file   = sanitize_text_field( wp_unslash( $_FILES['csv_file']['tmp_name'] ) );
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
        $handle = fopen( $file, 'r' );
        if ( ! $handle ) { wp_send_json_error( 'Cannot read file.' ); return; }

        $header  = null;
        $success = 0; $errors = 0; $error_msgs = array();
        global $wpdb;
        $table = EVDA_Database::documents_table();

        while ( ( $row = fgetcsv($handle) ) !== false ) {
            if ( ! $header ) { $header = array_map('trim', $row); continue; }
            if ( count($row) < 2 ) continue;
            $data = array_combine( $header, array_pad($row, count($header), '') );

            $emp_id = trim($data['employee_id'] ?? '');
            $type   = trim($data['document_type'] ?? 'experience');
            if ( ! $emp_id ) { $errors++; continue; }

            $emp = EVDA_Employee::get_by_employee_id( $emp_id );
            if ( ! $emp ) { $errors++; $error_msgs[] = "Employee not found: {$emp_id}"; continue; }

            $existing = EVDA_Document::get_existing( $emp_id, $type );
            if ( $existing ) { $errors++; $error_msgs[] = "{$type} letter already exists for {$emp_id}"; continue; }

            $vid = trim($data['verification_id'] ?? '');
            if ( ! $vid ) $vid = strtoupper( bin2hex( random_bytes(8) ) );

            // Check uniqueness
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
            $clash = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE verification_id = %s", $vid ) );
            if ( $clash ) { $errors++; $error_msgs[] = "Duplicate verification_id: {$vid}"; continue; }

            $r = $wpdb->insert( $table, array( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
                'verification_id' => $vid,
                'employee_id'     => $emp_id,
                'document_type'   => in_array($type, array('internship','experience')) ? $type : 'experience',
                'issue_date'      => trim($data['issue_date'] ?? current_time('Y-m-d')),
                'leaving_date'    => trim($data['leaving_date'] ?? '') ?: null,
                'pdf_path'        => null,
                'is_valid'        => 1,
                'is_manual'       => 1,
                'notes'           => trim($data['notes'] ?? ''),
            ) );
            $r ? $success++ : $errors++;
        }
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
        fclose($handle);
        wp_send_json_success( array( 'success' => $success, 'errors' => $errors, 'details' => $error_msgs ) );
    }

    // =========================================================================
    // CSV template download
    // =========================================================================
    public static function csv_template_url() {
        return add_query_arg( array( 'action' => 'evda_dl_import_template', 'nonce' => wp_create_nonce('evda_admin_nonce') ), admin_url('admin-ajax.php') );
    }
}
