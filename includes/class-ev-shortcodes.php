<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class EVDA_Shortcodes {

    public function __construct() {
        add_shortcode('evda_employee_verification', array($this, 'sc_employee'));
        add_shortcode('evda_document_verification', array($this, 'sc_document'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue'));
    }

    public function enqueue() {
        wp_enqueue_style('evda-public', EVDA_PLUGIN_URL . 'assets/css/public.css', array(), EVDA_VERSION);
    }

    // =========================================================================
    // [employee_verification]
    // =========================================================================
    public function sc_employee( $atts ) {
        ob_start();

        // Which fields are enabled for search (set in Settings → General)
        $enabled_fields = get_option('evda_search_fields', array('employee_id'));
        // Ensure it's always an array with at least one entry
        if ( empty($enabled_fields) ) $enabled_fields = array('employee_id');

        $field_labels = array(
            'employee_id' => 'Employee ID',
            'full_name'   => 'Full Name',
            'cnic'        => 'CNIC #',
        );
        $field_placeholders = array(
            'employee_id' => 'e.g. EMP-001',
            'full_name'   => 'e.g. John Smith',
            'cnic'        => 'e.g. 12345-6789012-3',
        );

        $employee  = null;
        $error     = '';
        $submitted = false;
        $values    = array(); // posted values per field

        if ( isset($_POST['evda_search_submit']) && check_admin_referer('evda_verify_employee','evda_nonce') ) {
            $submitted = true;

            // Collect posted values for enabled fields
            foreach ($enabled_fields as $field) {
                $values[$field] = sanitize_text_field( wp_unslash( $_POST[ 'evda_search_' . $field ] ?? '' ) );
            }

            // Try each enabled field that has a value — first match wins
            foreach ($enabled_fields as $field) {
                $val = $values[$field] ?? '';
                if ( $val === '' ) continue;

                $found = self::search_by_field($field, $val);
                if ($found) {
                    $employee = $found;
                    break;
                }
            }

            // If nothing matched, build a helpful error message
            if (!$employee) {
                $filled = array_filter($values, fn($v) => $v !== '');
                if (empty($filled)) {
                    $error = 'Please enter at least one search value.';
                } else {
                    $labels_used = array();
                    foreach (array_keys($filled) as $f) {
                        $labels_used[] = $field_labels[$f] ?? $f;
                    }
                    $error = 'No employee found matching the provided ' . implode(' / ', $labels_used) . '.';
                }
            }
        }

        // Build dynamic placeholder text for the search hint
        $hint_parts = array_map(fn($f) => $field_labels[$f], $enabled_fields);
        $hint = 'Search by ' . implode(', or ', $hint_parts) . '.';
        ?>
        <div class="ev-wrap ev-employee-verify">
            <h2>Employee Verification</h2>
            <p><?php echo esc_html($hint); ?></p>

            <form method="POST" class="ev-form">
                <?php wp_nonce_field('evda_verify_employee','evda_nonce'); ?>
                <input type="hidden" name="evda_search_submit" value="1">

                <div class="ev-search-fields">
                    <?php foreach ($enabled_fields as $field) : ?>
                        <div class="ev-search-field-row">
                            <label class="ev-search-field-label" for="ev-search-<?php echo esc_attr($field); ?>">
                                <?php echo esc_html($field_labels[$field]); ?>
                            </label>
                            <input
                                type="text"
                                id="ev-search-<?php echo esc_attr($field); ?>"
                                name="evda_search_<?php echo esc_attr($field); ?>"
                                class="ev-input"
                                placeholder="<?php echo esc_attr($field_placeholders[$field]); ?>"
                                value="<?php echo esc_attr($values[$field] ?? ''); ?>"
                                autocomplete="off">
                        </div>
                    <?php endforeach; ?>

                    <?php if (count($enabled_fields) > 1) : ?>
                        <p class="ev-search-hint">Fill in one or more fields — any match will find the employee.</p>
                    <?php endif; ?>

                    <button type="submit" class="ev-btn ev-btn-search">🔍 Verify Employee</button>
                </div>
            </form>

            <?php if ($submitted && $error) : ?>
                <div class="ev-result ev-error">
                    <span class="ev-icon">✗</span> <?php echo esc_html($error); ?>
                </div>
            <?php endif; ?>

            <?php if ($employee) :
                $status_slug = strtolower(str_replace(' ', '-', $employee->status));
            ?>
                <div class="ev-result ev-success">
                    <div class="ev-badge ev-badge-<?php echo esc_attr($status_slug); ?>">
                        <?php echo esc_html($employee->status); ?>
                    </div>

                    <?php if ($employee->photo_url) : ?>
                        <div class="ev-photo-wrap">
                            <img src="<?php echo esc_url($employee->photo_url); ?>" alt="Photo" class="ev-photo">
                        </div>
                    <?php endif; ?>

                    <table class="ev-table">
                        <tr><th>Employee Name</th><td><?php echo esc_html($employee->full_name); ?></td></tr>
                        <tr><th>Employee ID</th>  <td><?php echo esc_html($employee->employee_id); ?></td></tr>
                        <tr><th>Position</th>     <td><?php echo esc_html($employee->position); ?></td></tr>
                        <tr><th>Department</th>   <td><?php echo esc_html($employee->department); ?></td></tr>
                        <tr>
                            <th>Joining Date</th>
                            <td><?php echo !empty($employee->joining_date) ? esc_html(gmdate('F j, Y', strtotime($employee->joining_date))) : '—'; ?></td>
                        </tr>
                        <tr><th>Status</th><td><?php echo esc_html($employee->status); ?></td></tr>

                        <?php if ($employee->status === 'Former Employee') :
                            $leaving = '';
                            if (!empty($employee->leaving_date)) {
                                $leaving = gmdate('F j, Y', strtotime($employee->leaving_date));
                            } else {
                                $exp = EVDA_Document::get_existing($employee->employee_id, 'experience');
                                if ($exp && !empty($exp->leaving_date)) $leaving = gmdate('F j, Y', strtotime($exp->leaving_date));
                            }
                        ?>
                            <tr>
                                <th>Leaving Date</th>
                                <td><?php echo $leaving ? esc_html($leaving) : '—'; ?></td>
                            </tr>
                        <?php endif; ?>

                        <?php if ($employee->status === 'Intern') : ?>
                            <tr>
                                <th>Internship Completion Date</th>
                                <td><?php echo !empty($employee->intern_end) ? esc_html(gmdate('F j, Y', strtotime($employee->intern_end))) : '—'; ?></td>
                            </tr>
                        <?php endif; ?>
                    </table>

                    <div class="ev-verified-stamp">✔ Verified</div>
                </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    // -------------------------------------------------------------------------
    // Search employee by a specific field
    // -------------------------------------------------------------------------
    private static function search_by_field( $field, $value ) {
        global $wpdb;
        $table = esc_sql( EVDA_Database::validate_table( EVDA_Database::employees_table() ) );

        if ($field === 'employee_id') {
            // Exact match on employee_id
            return $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
                $wpdb->prepare(
                    "SELECT * FROM `{$table}` WHERE employee_id = %s LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                    sanitize_text_field( $value )
                )
            );
        }

        if ($field === 'full_name') {
            // Case-insensitive partial match on full_name
            return $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
                $wpdb->prepare(
                    "SELECT * FROM `{$table}` WHERE full_name LIKE %s LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                    '%' . $wpdb->esc_like( $value ) . '%'
                )
            );
        }

        if ($field === 'cnic') {
            // Exact match on CNIC (strip spaces/dashes for flexible matching)
            $clean = preg_replace('/[\s\-]/', '', $value);
            return $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
                $wpdb->prepare(
                    "SELECT * FROM `{$table}` WHERE REPLACE(REPLACE(cnic, '-', ''), ' ', '') = %s LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                    $clean
                )
            );
        }

        return null;
    }

    // =========================================================================
    // [document_verification]
    // =========================================================================
    public function sc_document( $atts ) {
        ob_start();
        $vid   = '';
        $doc   = null;
        $emp   = null;
        $error = '';

        if ( isset( $_GET['id'] ) ) $vid = sanitize_text_field( wp_unslash( $_GET['id'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only display param, nonce verified below for POST submission
        if ( isset( $_POST['evda_doc_id'] ) && check_admin_referer( 'evda_verify_document', 'evda_doc_nonce' ) )
            $vid = sanitize_text_field( wp_unslash( $_POST['evda_doc_id'] ) );

        if ($vid) {
            $doc = EVDA_Document::get_by_verification_id($vid);
            if ($doc) $emp = EVDA_Employee::get_by_employee_id($doc->employee_id);
            else       $error = 'Document not found. Please check the verification ID.';
        }
        ?>
        <div class="ev-wrap ev-document-verify">
            <h2>Document Verification</h2>
            <p>Enter a verification ID or open the QR scan link to authenticate a document.</p>
            <form method="POST" class="ev-form">
                <?php wp_nonce_field('evda_verify_document','evda_doc_nonce'); ?>
                <div class="ev-field-row">
                    <input type="text" name="evda_doc_id" class="ev-input"
                           placeholder="Enter Verification ID"
                           value="<?php echo esc_attr($vid); ?>" required>
                    <button type="submit" class="ev-btn">Verify Document</button>
                </div>
            </form>

            <?php if ($error) : ?>
                <div class="ev-result ev-error"><span class="ev-icon">✗</span> <?php echo esc_html($error); ?></div>
            <?php endif; ?>

            <?php if ($doc) : ?>
                <div class="ev-result <?php echo esc_attr( $doc->is_valid ? 'ev-success' : 'ev-error' ); ?>">
                    <div class="ev-badge <?php echo esc_attr( $doc->is_valid ? 'ev-badge-active' : 'ev-badge-invalid' ); ?>">
                        <?php echo esc_html( $doc->is_valid ? '✔ VERIFIED' : '✗ REVOKED' ); ?>
                    </div>
                    <table class="ev-table">
                        <tr><th>Document Type</th><td><?php echo esc_html( ucfirst( $doc->document_type ) ); ?> Letter</td></tr>
                        <?php if ($emp) : ?>
                            <tr><th>Employee Name</th><td><?php echo esc_html($emp->full_name); ?></td></tr>
                            <tr><th>Position</th>     <td><?php echo esc_html($emp->position); ?></td></tr>
                            <tr><th>Department</th>   <td><?php echo esc_html($emp->department); ?></td></tr>
                        <?php endif; ?>
                        <tr><th>Issue Date</th>   <td><?php echo esc_html(gmdate('F j, Y', strtotime($doc->issue_date))); ?></td></tr>
                        <?php if ($doc->leaving_date) : ?>
                            <tr><th>Leaving Date</th><td><?php echo esc_html(gmdate('F j, Y', strtotime($doc->leaving_date))); ?></td></tr>
                        <?php endif; ?>
                        <tr><th>Verification ID</th><td><?php echo esc_html($doc->verification_id); ?></td></tr>
                        <tr><th>Status</th><td>
                            <?php echo wp_kses(
                                $doc->is_valid
                                    ? '<span class="evda-status-valid">Valid</span>'
                                    : '<span class="evda-status-revoked">Revoked</span>',
                                array( 'span' => array( 'class' => array() ) )
                            ); ?>
                        </td></tr>
                    </table>

                    <?php if ($doc->pdf_path) : ?>
                        <a href="<?php echo esc_url(EVDA_PDF::get_pdf_url($doc->pdf_path)); ?>"
                           class="ev-btn ev-btn-download" target="_blank">⬇ Download Document</a>
                    <?php endif; ?>

                    <div class="ev-qr-wrap">
                        <?php
                        $vurl = EVDA_Document::get_verification_url($doc->verification_id);
                        $b64  = EVDA_QRCode::generate_base64($vurl);
                        if ($b64) {
                            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $b64 is base64-encoded PNG from trusted source, wrapped in esc_attr()
                            echo '<img src="data:image/png;base64,' . esc_attr( $b64 ) . '" width="140" height="140" alt="' . esc_attr__( 'QR Code', 'employee-verification-document-authentication' ) . '">';
                        } else {
                            echo wp_kses( '<a href="' . esc_url( $vurl ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $vurl ) . '</a>', array( 'a' => array( 'href' => true, 'target' => true, 'rel' => true ) ) );
                        }
                        ?>
                        <small>QR Code for sharing</small>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
}
