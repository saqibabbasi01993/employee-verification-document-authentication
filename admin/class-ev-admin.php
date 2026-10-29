<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class EVDA_Admin {

    public function __construct() {
        add_action( 'admin_menu',             array( $this, 'register_menus' ) );
        add_action( 'admin_enqueue_scripts',  array( $this, 'enqueue_assets' ) );
        add_action( 'wp_ajax_evda_save_employee',     array( $this, 'ajax_save_employee' ) );
        add_action( 'wp_ajax_evda_delete_employee',   array( $this, 'ajax_delete_employee' ) );
        add_action( 'wp_ajax_evda_generate_document', array( $this, 'ajax_generate_document' ) );
        add_action( 'wp_ajax_evda_revoke_document',   array( $this, 'ajax_revoke_document' ) );
        add_action( 'wp_ajax_evda_delete_document',   array( $this, 'ajax_delete_document' ) );
        add_action( 'wp_ajax_evda_import_csv',        array( $this, 'ajax_import_csv' ) );
        add_action( 'wp_ajax_evda_send_test_email',   array( $this, 'ajax_send_test_email' ) );
        add_action( 'admin_notices',                array( $this, 'db_admin_notice' ) );
        add_action( 'wp_ajax_evda_repair_tables',     array( $this, 'ajax_repair_tables' ) );
        add_action( 'wp_ajax_evda_run_migration',      array( $this, 'ajax_run_migration' ) );
        add_action( 'wp_ajax_evda_patch_old_letters',    array( $this, 'ajax_patch_old_letters' ) );
        add_action( 'wp_ajax_evda_export_employees',    array( $this, 'ajax_export_employees' ) );
        add_action( 'wp_ajax_evda_generate_other_letter', array( $this, 'ajax_generate_other_letter' ) );
        add_action( 'wp_ajax_evda_save_letter_type',      array( $this, 'ajax_save_letter_type' ) );
        add_action( 'wp_ajax_evda_delete_letter_type',    array( $this, 'ajax_delete_letter_type' ) );
    }

    public function register_menus() {
        add_menu_page(
            'Employee Verification',
            'Employee Verify',
            'manage_options',
            'evda-dashboard',
            array( $this, 'page_dashboard' ),
            'dashicons-id-alt',
            30
        );
        add_submenu_page( 'evda-dashboard', 'Employees',  'Employees',  'manage_options', 'evda-employees',  array( $this, 'page_employees' ) );
        add_submenu_page( 'evda-dashboard', 'Documents',  'Documents',  'manage_options', 'evda-documents',  array( $this, 'page_documents' ) );
        add_submenu_page( 'evda-dashboard', 'Settings',   'Settings',   'manage_options', 'evda-settings',   array( $this, 'page_settings' ) );
    }

    public function enqueue_assets( $hook ) {
        // Load on all EV plugin pages (slug contains 'ev-' or 'employee-verify')
        if ( strpos( $hook, 'evda-' ) === false && strpos( $hook, 'ev-' ) === false && strpos( $hook, 'employee-verify' ) === false ) return;
        // Media uploader (needed for photo upload and logo upload)
        wp_enqueue_media();
        wp_enqueue_style(  'evda-admin', EVDA_PLUGIN_URL . 'assets/css/admin.css', array(), EVDA_VERSION );
        wp_enqueue_script( 'evda-admin', EVDA_PLUGIN_URL . 'assets/js/admin.js', array( 'jquery' ), EVDA_VERSION, true );
        wp_localize_script( 'evda-admin', 'EVDA', array(
            'ajax_url'        => admin_url( 'admin-ajax.php' ),
            'nonce'           => wp_create_nonce( 'evda_admin_nonce' ),
            'migration_nonce' => wp_create_nonce( 'evda_admin_nonce' ),
        ) );
    }

    // =========================================================================
    // Dashboard
    // =========================================================================
    public function page_dashboard() {
        global $wpdb;
        // Direct raw counts from DB to verify data exists
        $raw_emp  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}ev_employees" ); // phpcs:ignore
        $raw_docs = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}ev_documents" );  // phpcs:ignore
        $total_emp  = EVDA_Employee::count();
        $total_docs = count( EVDA_Document::get_all( 9999 ) );
        ?>
        <div class="wrap ev-admin-wrap">
            <h1>Employee Verification System</h1>
            <div class="ev-stats-row">
                <div class="ev-stat-card">
                    <span class="ev-stat-num"><?php echo absint( $total_emp ); ?></span>
                    <span class="ev-stat-label">Total Employees</span>
                </div>
                <div class="ev-stat-card">
                    <span class="ev-stat-num"><?php echo absint( $total_docs ); ?></span>
                    <span class="ev-stat-label">Documents Issued</span>
                </div>
            </div>
            <?php if ( $raw_emp !== $total_emp || $raw_docs !== $total_docs ) : ?>
            <div class="notice notice-warning evda-notice-box">
                <strong>⚠️ Data Migration Notice:</strong>
                Raw DB has <strong><?php echo absint($raw_emp); ?></strong> employees and
                <strong><?php echo absint($raw_docs); ?></strong> documents.
                Plugin reads <strong><?php echo absint($total_emp); ?></strong> employees and
                <strong><?php echo absint($total_docs); ?></strong> documents.
                <?php if ( $raw_emp > 0 && $total_emp === 0 ) : ?>
                <br><strong class="evda-notice-error-text">Tables found but returning 0 — please contact support.</strong>
                <?php endif; ?>
            </div>
            <?php elseif ( $raw_emp > 0 ) : ?>
            <div class="notice notice-success evda-notice-box">
                <strong>✔ Data OK:</strong> Found <?php echo absint($raw_emp); ?> employees and <?php echo absint($raw_docs); ?> documents.
            </div>
            <?php endif; ?>
            <?php if ( $raw_emp > 0 ) : ?>
            <p>
                <button type="button" class="button button-secondary" id="evda-run-migration">
                    🔄 Re-run Data Migration
                </button>
                <span id="evda-migration-result" class="evda-migration-result"></span>
            </p>

            <?php endif; ?>
            <h2>Quick Links</h2>
            <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=evda-employees&action=add' ), 'evda_add_employee' ) ); ?>" class="ev-btn">+ Add Employee</a>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=evda-employees' ) ); ?>" class="ev-btn ev-btn-secondary">View All Employees</a>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=evda-documents' ) ); ?>" class="ev-btn ev-btn-secondary">View All Documents</a>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=evda-settings' ) ); ?>" class="ev-btn ev-btn-secondary">Settings</a>
            <hr>
            <h2>Shortcode Reference</h2>
            <table class="widefat">
                <thead><tr><th>Shortcode</th><th>Usage</th></tr></thead>
                <tbody>
                    <tr><td><code>[evda_employee_verification]</code></td><td>Add to any page to create the employee lookup form</td></tr>
                    <tr><td><code>[evda_document_verification]</code></td><td>Add to any page to create the document verification form</td></tr>
                </tbody>
            </table>
        </div>
        <?php
    }

    // =========================================================================
    // Employees page
    // =========================================================================
    public function page_employees() {
        // Capability check must come before any input is read.
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'employee-verification-document-authentication' ) );
        }

        // These are read-only admin navigation params (no state change on list view).
        // Nonce is verified below for any state-changing action (edit/add).
        $action = isset( $_GET['action'] ) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : 'list'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $id     = isset( $_GET['id'] )     ? absint( $_GET['id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

        // Verify nonce for edit action — link is generated with wp_nonce_url().
        if ( 'edit' === $action && $id ) {
            if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'evda_edit_employee_' . $id ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                wp_die( esc_html__( 'Security check failed.', 'employee-verification-document-authentication' ) );
            }
        }

        // Verify nonce for add action — link is generated with wp_nonce_url().
        if ( 'add' === $action ) {
            if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'evda_add_employee' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                wp_die( esc_html__( 'Security check failed.', 'employee-verification-document-authentication' ) );
            }
        }

        if ( 'add' === $action || 'edit' === $action ) {
            $this->render_employee_form( $id );
        } else {
            $this->render_employee_list();
        }
    }

    private function render_employee_list() {
        // All GET params are read-only display/filter values — no state change occurs.
        $search     = isset( $_GET['s'] )          ? sanitize_text_field( wp_unslash( $_GET['s'] ) )          : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $paged      = isset( $_GET['paged'] )      ? max( 1, absint( $_GET['paged'] ) )                       : 1;  // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $department = isset( $_GET['department'] ) ? sanitize_text_field( wp_unslash( $_GET['department'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $status     = isset( $_GET['status'] )     ? sanitize_text_field( wp_unslash( $_GET['status'] ) )     : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

        $allowed_statuses = array( 'Active', 'Intern', 'Former Employee' );
        if ( ! in_array( $status, $allowed_statuses, true ) ) {
            $status = '';
        }

        $per_page  = 20;
        $offset    = ( $paged - 1 ) * $per_page;
        $filter    = array( 'search' => $search, 'department' => $department, 'status' => $status );
        $employees = EVDA_Employee::get_all( array_merge( $filter, array( 'limit' => $per_page, 'offset' => $offset ) ) );
        $total     = EVDA_Employee::count( $filter );
        $pages     = ceil( $total / $per_page );
        $depts     = EVDA_Employee::get_departments();
        ?>
        <div class="wrap ev-admin-wrap">
            <h1 class="wp-heading-inline"><?php esc_html_e( 'Employees', 'employee-verification-document-authentication' ); ?></h1>
            <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=evda-employees&action=add' ), 'evda_add_employee' ) ); ?>" class="page-title-action"><?php esc_html_e( 'Add New', 'employee-verification-document-authentication' ); ?></a>
            <hr class="wp-header-end">

            <!-- ── Filter & Tools Bar ── -->
            <div class="evda-toolbar">

                <!-- Search + Filters form (GET — read-only, no nonce needed) -->
                <form method="GET" class="evda-filter-form">
                    <input type="hidden" name="page" value="evda-employees">

                    <input type="text"
                           name="s"
                           value="<?php echo esc_attr( $search ); ?>"
                           placeholder="<?php esc_attr_e( 'Search name or ID…', 'employee-verification-document-authentication' ); ?>"
                           class="evda-filter-input">

                    <select name="department" class="evda-filter-select">
                        <option value=""><?php esc_html_e( 'All Departments', 'employee-verification-document-authentication' ); ?></option>
                        <?php foreach ( $depts as $dept ) : ?>
                            <option value="<?php echo esc_attr( $dept ); ?>" <?php selected( $department, $dept ); ?>>
                                <?php echo esc_html( $dept ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <select name="status" class="evda-filter-select">
                        <option value=""><?php esc_html_e( 'All Statuses', 'employee-verification-document-authentication' ); ?></option>
                        <?php foreach ( array( 'Active', 'Intern', 'Former Employee' ) as $st ) : ?>
                            <option value="<?php echo esc_attr( $st ); ?>" <?php selected( $status, $st ); ?>>
                                <?php echo esc_html( $st ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <button type="submit" class="button evda-filter-btn">
                        🔍 <?php esc_html_e( 'Filter', 'employee-verification-document-authentication' ); ?>
                    </button>

                    <?php if ( $search || $department || $status ) : ?>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=evda-employees' ) ); ?>" class="button evda-filter-reset">
                            ✕ <?php esc_html_e( 'Clear', 'employee-verification-document-authentication' ); ?>
                        </a>
                    <?php endif; ?>
                </form>

                <!-- Right-side tools -->
                <div class="evda-toolbar-tools">

                    <!-- Export CSV -->
                    <button id="evda-export-csv-btn" class="button evda-export-btn"
                            data-search="<?php echo esc_attr( $search ); ?>"
                            data-department="<?php echo esc_attr( $department ); ?>"
                            data-status="<?php echo esc_attr( $status ); ?>"
                            data-nonce="<?php echo esc_attr( wp_create_nonce( 'evda_export_employees' ) ); ?>">
                        📥 <?php esc_html_e( 'Export CSV', 'employee-verification-document-authentication' ); ?>
                    </button>

                    <!-- Bulk Import CSV -->
                    <div class="evda-import-inline">
                        <input type="file" id="ev-csv-file" accept=".csv" class="evda-import-file-input">
                        <button class="button evda-import-btn" id="ev-import-csv-btn">
                            📤 <?php esc_html_e( 'Import CSV', 'employee-verification-document-authentication' ); ?>
                        </button>
                        <span id="ev-import-result" class="evda-import-result-msg"></span>
                    </div>
                </div>
            </div>

            <!-- Active filter badges -->
            <?php if ( $search || $department || $status ) : ?>
            <div class="evda-active-filters">
                <span class="evda-filter-label"><?php esc_html_e( 'Filtering by:', 'employee-verification-document-authentication' ); ?></span>
                <?php if ( $search ) : ?>
                    <span class="evda-filter-badge">🔍 <?php echo esc_html( $search ); ?></span>
                <?php endif; ?>
                <?php if ( $department ) : ?>
                    <span class="evda-filter-badge">🏢 <?php echo esc_html( $department ); ?></span>
                <?php endif; ?>
                <?php if ( $status ) : ?>
                    <span class="evda-filter-badge evda-filter-badge--status evda-status-<?php echo esc_attr( strtolower( str_replace( ' ', '-', $status ) ) ); ?>">
                        <?php echo esc_html( $status ); ?>
                    </span>
                <?php endif; ?>
                <span class="evda-filter-count"><?php
                /* translators: %d = number of employees matching the current filter */
                printf( esc_html__( '%d employee(s) found', 'employee-verification-document-authentication' ), absint( $total ) ); ?></span>
            </div>
            <?php endif; ?>

            <!-- Import hint -->
            <p class="evda-import-hint">
                <small><?php esc_html_e( 'CSV Import headers: employee_id, full_name, position, department, joining_date, intern_start, intern_end, status', 'employee-verification-document-authentication' ); ?></small>
            </p>

            <table class="widefat ev-table-list">
                <thead>
                    <tr>
                        <th>Photo</th><th>Employee ID</th><th>Name</th><th>Position / Dept</th><th>Status</th>
                        <th class="evda-th-center">🎓 Internship Letter</th>
                        <th class="evda-th-center">💼 Experience Letter</th>
                        <th class="evda-th-center">📄 Other Letters</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ( empty( $employees ) ) : ?>
                    <tr><td colspan="9" class="evda-td-center">No employees found.</td></tr>
                <?php else : ?>
                    <?php foreach ( $employees as $emp ) :
                        $docs = EVDA_Document::get_both( $emp->employee_id );
                        $intern_doc = $docs['internship'];
                        $exp_doc    = $docs['experience'];
                    ?>
                    <tr>
                        <td><?php if ( $emp->photo_url ) echo wp_kses( '<img src="' . esc_url( $emp->photo_url ) . '" class="ev-thumb">', array( 'img' => array( 'src' => true, 'class' => true, 'alt' => true ) ) ); else echo '<span class="ev-no-photo">👤</span>'; ?></td>
                        <td><code><?php echo esc_html($emp->employee_id); ?></code></td>
                        <td><strong><?php echo esc_html($emp->full_name); ?></strong></td>
                        <td><span class="evda-pos-text"><?php echo esc_html($emp->position); ?></span><br><small class="evda-dept-text"><?php echo esc_html($emp->department); ?></small></td>
                        <td><span class="ev-status ev-status-<?php echo esc_attr(strtolower(str_replace(' ','-',$emp->status))); ?>"><?php echo esc_html($emp->status); ?></span></td>

                        <!-- Internship Letter Column -->
                        <td class="ev-letter-cell evda-th-center">
                            <?php if ( $intern_doc ) : ?>
                                <div class="ev-letter-issued">
                                    <span class="ev-issued-badge">✅ Issued</span>
                                    <div class="ev-letter-meta"><?php echo esc_html(gmdate('d M Y', strtotime($intern_doc->issue_date))); ?></div>
                                    <div class="ev-letter-actions">
                                        <?php if($intern_doc->pdf_path): ?>
                                            <a href="<?php echo esc_url(EVDA_PDF::get_pdf_url($intern_doc->pdf_path)); ?>" target="_blank" class="ev-link-btn">📄 View</a>
                                        <?php endif; ?>
                                        <a href="<?php echo esc_url(EVDA_Document::get_verification_url($intern_doc->verification_id)); ?>" target="_blank" class="ev-link-btn">🔗 Verify</a>
                                        <button class="ev-link-btn ev-link-delete ev-delete-doc"
                                            data-id="<?php echo absint( $intern_doc->id ); ?>"
                                            data-label="Internship Letter for <?php echo esc_attr($emp->full_name); ?>">
                                            🗑 Delete
                                        </button>
                                    </div>
                                </div>
                            <?php else : ?>
                                <button class="button button-small ev-btn-intern ev-gen-doc"
                                    data-empid="<?php echo esc_attr($emp->employee_id); ?>"
                                    data-empname="<?php echo esc_attr($emp->full_name); ?>"
                                    data-intern-start="<?php echo esc_attr($emp->intern_start ?? ''); ?>"
                                    data-intern-end="<?php echo esc_attr($emp->intern_end ?? ''); ?>"
                                    data-type="internship">
                                    🎓 Generate
                                </button>
                            <?php endif; ?>
                        </td>

                        <!-- Experience Letter Column -->
                        <td class="ev-letter-cell evda-th-center">
                            <?php if ( $exp_doc ) : ?>
                                <div class="ev-letter-issued">
                                    <span class="ev-issued-badge">✅ Issued</span>
                                    <div class="ev-letter-meta"><?php echo esc_html(gmdate('d M Y', strtotime($exp_doc->issue_date))); ?></div>
                                    <div class="ev-letter-actions">
                                        <?php if($exp_doc->pdf_path): ?>
                                            <a href="<?php echo esc_url(EVDA_PDF::get_pdf_url($exp_doc->pdf_path)); ?>" target="_blank" class="ev-link-btn">📄 View</a>
                                        <?php endif; ?>
                                        <a href="<?php echo esc_url(EVDA_Document::get_verification_url($exp_doc->verification_id)); ?>" target="_blank" class="ev-link-btn">🔗 Verify</a>
                                        <button class="ev-link-btn ev-link-delete ev-delete-doc"
                                            data-id="<?php echo absint( $exp_doc->id ); ?>"
                                            data-label="Experience Letter for <?php echo esc_attr($emp->full_name); ?>">
                                            🗑 Delete
                                        </button>
                                    </div>
                                </div>
                            <?php else : ?>
                                <button class="button button-small ev-btn-exp ev-gen-doc"
                                    data-empid="<?php echo esc_attr($emp->employee_id); ?>"
                                    data-empname="<?php echo esc_attr($emp->full_name); ?>"
                                    data-type="experience">
                                    💼 Generate
                                </button>
                            <?php endif; ?>
                        </td>

                        <td class="evda-other-letters-cell evda-th-center">
                            <?php $other_types = EVDA_LetterTypes::get_active(); ?>
                            <?php if ( ! empty( $other_types ) ) : ?>
                            <div class="evda-dropdown">
                                <button type="button" class="button button-small evda-dropdown-toggle"
                                        data-emp="<?php echo esc_attr( $emp->employee_id ); ?>"
                                        data-empname="<?php echo esc_attr( $emp->full_name ); ?>">
                                    📄 <?php esc_html_e( 'Other Letters', 'employee-verification-document-authentication' ); ?> ▾
                                </button>
                                <div class="evda-dropdown-menu evda-dropdown-menu--right">
                                    <?php foreach ( $other_types as $ot ) : ?>
                                        <a href="#"
                                           class="evda-dropdown-item evda-other-letter-btn"
                                           data-emp="<?php echo esc_attr( $emp->employee_id ); ?>"
                                           data-empname="<?php echo esc_attr( $emp->full_name ); ?>"
                                           data-type="<?php echo esc_attr( $ot['slug'] ); ?>"
                                           data-typename="<?php echo esc_attr( $ot['name'] ); ?>"
                                           data-fields="<?php echo esc_attr( wp_json_encode( $ot['extra_fields'] ?? array() ) ); ?>">
                                            <?php echo esc_html( $ot['name'] ); ?>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php else : ?>
                            <span style="color:#aaa;font-size:12px;">—</span>
                            <?php endif; ?>
                        </td>

                        <td class="ev-actions evda-td-actions">
                            <a href="<?php echo esc_url( wp_nonce_url( admin_url('admin.php?page=evda-employees&action=edit&id='.$emp->id), 'evda_edit_employee_'.$emp->id ) ); ?>" class="button button-small">✏️ Edit</a>
                            <button class="button button-small button-link-delete ev-delete-emp evda-delete-btn evda-delete-mt" data-id="<?php echo absint( $emp->id ); ?>">🗑 Delete</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>

            <?php if ( $pages > 1 ) : ?>
            <div class="tablenav bottom">
                <div class="tablenav-pages">
                    <?php
                    echo wp_kses_post( paginate_links( array(
                        'base'    => add_query_arg( array(
                            'paged'      => '%#%',
                            's'          => $search,
                            'department' => $department,
                            'status'     => $status,
                        ) ),
                        'format'  => '',
                        'current' => $paged,
                        'total'   => $pages,
                    ) ) );
                    ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Generate Doc Modal -->
        <div id="ev-gen-doc-modal" class="ev-modal evda-hidden">
            <div class="ev-modal-content">
                <h2 id="ev-modal-title">Generate Document</h2>
                <p id="ev-modal-desc" class="evda-modal-desc"></p>
                <input type="hidden" id="ev-modal-empid">
                <input type="hidden" id="ev-modal-type">

                <!-- Internship fields -->
                <div id="ev-modal-intern-wrap" class="evda-hidden">
                    <div class="evda-modal-intern-box">
                        <label class="evda-modal-label">📅 Internship Start Date:</label>
                        <input type="date" id="ev-modal-intern-start" class="evda-modal-date-input-mb">
                        <label class="evda-modal-label">📅 Internship End / Completion Date:</label>
                        <input type="date" id="ev-modal-intern-end" class="evda-modal-date-input">
                        <small class="evda-modal-hint">These will appear on the internship completion letter.</small>
                    </div>
                </div>

                <!-- Experience fields -->
                <div id="ev-modal-leaving-wrap" class="evda-hidden">
                    <div class="evda-modal-leaving-box">
                        <label class="evda-modal-label">📅 Last Working Day (Leaving Date):</label>
                        <input type="date" id="ev-modal-leaving" class="evda-modal-date-input">
                        <small class="evda-modal-hint">This will appear on the experience letter.</small>
                    </div>
                </div>

                <div class="evda-modal-actions">
                    <button class="button button-primary evda-modal-confirm" id="ev-modal-confirm">✅ Generate Letter</button>
                    <button class="button evda-modal-cancel" id="ev-modal-cancel">Cancel</button>
                </div>
                <div id="ev-modal-result" class="evda-modal-result"></div>
            </div>
        </div>

        <!-- ============================================================ -->
        <!-- Other Letters Modal                                           -->
        <!-- ============================================================ -->
        <div id="evda-other-letter-modal" class="ev-modal" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,.55);z-index:99999;">
            <div style="background:#fff;border-radius:8px;max-width:520px;width:94%;margin:60px auto;padding:28px 30px;box-shadow:0 8px 32px rgba(0,0,0,.18);position:relative;">
                <button type="button" id="evda-other-modal-close" style="position:absolute;top:12px;right:14px;background:none;border:none;font-size:22px;cursor:pointer;color:#888;">&times;</button>
                <h3 id="evda-other-modal-title" style="margin:0 0 6px;font-size:17px;color:#1d2327;"></h3>
                <p id="evda-other-modal-emp" style="margin:0 0 18px;color:#555;font-size:13px;"></p>
                <div id="evda-other-modal-fields"></div>
                <div style="display:flex;gap:10px;margin-top:20px;">
                    <button type="button" id="evda-other-modal-confirm" class="button button-primary" style="flex:1;padding:10px;">
                        <?php esc_html_e( 'Generate Letter', 'employee-verification-document-authentication' ); ?>
                    </button>
                    <button type="button" id="evda-other-modal-cancel" class="button" style="padding:10px 20px;">
                        <?php esc_html_e( 'Cancel', 'employee-verification-document-authentication' ); ?>
                    </button>
                </div>
                <div id="evda-other-modal-result" style="margin-top:14px;font-size:13px;"></div>
            </div>
        </div>
        <?php
    }

    private function render_employee_form( $id = 0 ) {
        $emp     = $id ? EVDA_Employee::get_by_id($id) : null;
        $action  = $id ? 'Edit' : 'Add';
        $cf_defs = get_option('evda_custom_field_defs', array());
        $cf_vals = $emp ? EVDA_Employee::get_custom_fields($emp) : array();
        // Index existing cf values by key for quick lookup
        $cf_map  = array();
        foreach ($cf_vals as $v) { if (!empty($v['key'])) $cf_map[$v['key']] = $v; }
        ?>
        <div class="wrap ev-admin-wrap">
            <h1><?php echo esc_html($action); ?> Employee</h1>
            <a href="<?php echo esc_url( admin_url('admin.php?page=evda-employees') ); ?>" class="button">← Back to list</a>
            <br><br>
            <form id="ev-employee-form" class="ev-form-admin">
                <input type="hidden" name="evda_emp_id" value="<?php echo absint( $id ); ?>">

                <!-- ── Core Fields ── -->
                <div class="ev-form-section">
                    <h3 class="ev-section-title">👤 Basic Information</h3>
                    <table class="form-table">
                        <tr>
                            <th><label>Employee ID *</label></th>
                            <td><input type="text" name="employee_id" required class="regular-text"
                                    value="<?php echo $emp ? esc_attr($emp->employee_id) : ''; ?>"
                                    placeholder="e.g. EMP-001"></td>
                        </tr>
                        <tr>
                            <th><label>Full Name *</label></th>
                            <td><input type="text" name="full_name" required class="regular-text"
                                    value="<?php echo $emp ? esc_attr($emp->full_name) : ''; ?>"></td>
                        </tr>
                        <tr>
                            <th><label>Father Name</label></th>
                            <td><input type="text" name="father_name" class="regular-text"
                                    value="<?php echo $emp ? esc_attr($emp->father_name ?? '') : ''; ?>"
                                    placeholder="Father's full name"></td>
                        </tr>
                        <tr>
                            <th><label>CNIC #</label></th>
                            <td>
                                <input type="text" name="cnic" class="regular-text"
                                    value="<?php echo $emp ? esc_attr($emp->cnic ?? '') : ''; ?>"
                                    placeholder="e.g. 12345-6789012-3">
                                <p class="description">National Identity Card number.</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label>Email Address</label></th>
                            <td>
                                <input type="email" name="email" class="regular-text"
                                    value="<?php echo $emp ? esc_attr($emp->email ?? '') : ''; ?>"
                                    placeholder="employee@example.com">
                                <p class="description">Used to auto-send letters when generated.</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label>Profile Photo</label></th>
                            <td>
                                <input type="text" name="photo_url" id="ev-photo-url" class="regular-text"
                                    value="<?php echo $emp ? esc_url($emp->photo_url) : ''; ?>">
                                <button type="button" class="button" id="ev-upload-photo">📷 Upload Photo</button>
                                <?php if ($emp && $emp->photo_url) : ?>
                                    <br><img src="<?php echo esc_url($emp->photo_url); ?>" class="evda-photo-img" alt="<?php echo esc_attr($emp->full_name); ?>">
                                <?php endif; ?>
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- ── Employment Details ── -->
                <div class="ev-form-section">
                    <h3 class="ev-section-title">💼 Employment Details</h3>
                    <table class="form-table">
                        <tr>
                            <th><label>Position *</label></th>
                            <td><input type="text" name="position" required class="regular-text"
                                    value="<?php echo $emp ? esc_attr($emp->position) : ''; ?>"></td>
                        </tr>
                        <tr>
                            <th><label>Department *</label></th>
                            <td><input type="text" name="department" required class="regular-text"
                                    value="<?php echo $emp ? esc_attr($emp->department) : ''; ?>"></td>
                        </tr>
                        <tr>
                            <th><label>Joining Date</label></th>
                            <td><input type="date" name="joining_date" class="regular-text"
                                    value="<?php echo $emp ? esc_attr($emp->joining_date ?? '') : ''; ?>"></td>
                        </tr>
                        <tr>
                            <th><label>Status *</label></th>
                            <td>
                                <select name="status" id="ev-status-select">
                                    <?php foreach (array('Active','Intern','Former Employee') as $st) : ?>
                                        <option value="<?php echo esc_attr( $st ); ?>" <?php selected($emp ? $emp->status : 'Active', $st); ?>><?php echo esc_html( $st ); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <tr class="ev-row-intern<?php echo esc_attr( ( $emp && $emp->status === 'Intern' ) ? '' : ' evda-hidden' ); ?>">
                            <th><label>Internship Start</label></th>
                            <td><input type="date" name="intern_start" class="regular-text"
                                    value="<?php echo $emp ? esc_attr($emp->intern_start ?? '') : ''; ?>"></td>
                        </tr>
                        <tr class="ev-row-intern<?php echo esc_attr( ( $emp && $emp->status === 'Intern' ) ? '' : ' evda-hidden' ); ?>">
                            <th><label>Internship End / Completion Date</label></th>
                            <td><input type="date" name="intern_end" class="regular-text"
                                    value="<?php echo $emp ? esc_attr($emp->intern_end ?? '') : ''; ?>"></td>
                        </tr>
                        <tr class="ev-row-former<?php echo esc_attr( ( $emp && $emp->status === 'Former Employee' ) ? '' : ' evda-hidden' ); ?>">
                            <th><label>Leaving Date</label></th>
                            <td>
                                <input type="date" name="leaving_date" class="regular-text"
                                    value="<?php echo $emp ? esc_attr($emp->leaving_date ?? '') : ''; ?>">
                                <p class="description">Shown publicly on the Employee Verification page.</p>
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- ── Tasks Completed ── -->
                <div class="ev-form-section">
                    <h3 class="ev-section-title">📋 Tasks Completed</h3>
                    <table class="form-table">
                        <tr>
                            <th><label>Tasks / Responsibilities</label></th>
                            <td>
                                <textarea name="tasks_completed" rows="4" class="large-text"
                                    placeholder="e.g. Virtual Assistant, Data Entry, Email Handling, Social Media Management"><?php echo $emp ? esc_textarea($emp->tasks_completed ?? '') : ''; ?></textarea>
                                <p class="description">Comma-separated list. These appear as a bullet list in the letter using the <code>{tasks_section}</code> placeholder.</p>
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- ── Custom Fields ── -->
                <?php if (!empty($cf_defs)) : ?>
                <div class="ev-form-section">
                    <h3 class="ev-section-title">🔧 Custom Fields</h3>
                    <table class="form-table">
                        <?php foreach ($cf_defs as $cf) :
                            $key   = $cf['key'];
                            $saved = $cf_map[$key] ?? array();
                        ?>
                        <tr>
                            <th><label><?php echo esc_html($cf['label']); ?></label></th>
                            <td>
                                <input type="text" name="evda_cf_val[<?php echo esc_attr($key); ?>]"
                                    class="regular-text"
                                    value="<?php echo esc_attr($saved['value'] ?? ''); ?>">
                                <label class="evda-cf-label-wrap">
                                    <input type="checkbox" name="evda_cf_show[<?php echo esc_attr($key); ?>]" value="1"
                                        <?php checked(!empty($saved['show_in_letter'])); ?>>
                                    Show in letter
                                </label>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </table>
                </div>
                <?php else : ?>
                <p class="evda-form-hint">
                    No custom fields defined yet. Go to <a href="<?php echo esc_url( admin_url('admin.php?page=evda-settings') ); ?>">Settings → Custom Fields</a> to add them.
                </p>
                <?php endif; ?>

                <div class="ev-form-section evda-form-section-bare">
                    <button type="submit" class="button button-primary button-large">💾 Save Employee</button>
                    <span id="ev-save-result" class="evda-save-result"></span>
                </div>
            </form>
        </div>

        <?php
    }

    // =========================================================================
    // Documents page
    // =========================================================================
    public function page_documents() {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $paged    = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only pagination param
        $per_page = 20;
        $offset   = ( $paged - 1 ) * $per_page;
        $docs     = EVDA_Document::get_all( $per_page, $offset );
        ?>
        <div class="wrap ev-admin-wrap">
            <h1>Issued Documents</h1>
            <table class="widefat ev-table-list">
                <thead>
                    <tr><th>Verification ID</th><th>Employee</th><th>Type</th><th>Issue Date</th><th>Status</th><th>Actions</th></tr>
                </thead>
                <tbody>
                <?php if ( empty( $docs ) ) : ?>
                    <tr><td colspan="6" class="evda-td-empty">No documents issued yet.</td></tr>
                <?php else : ?>
                    <?php foreach ( $docs as $doc ) : ?>
                    <tr>
                        <td><code><?php echo esc_html( $doc->verification_id ); ?></code></td>
                        <td><?php echo esc_html( $doc->full_name ?? $doc->employee_id ); ?></td>
                        <td><?php echo esc_html( ucfirst( $doc->document_type ) ); ?> Letter</td>
                        <td><?php echo esc_html( gmdate( 'Y-m-d', strtotime( $doc->issue_date ) ) ); ?></td>
                        <td>
                            <?php if ( $doc->is_valid ) : ?>
                                <span class="ev-status ev-status-active">Valid</span>
                            <?php else : ?>
                                <span class="ev-status ev-status-invalid">Revoked</span>
                            <?php endif; ?>
                        </td>
                        <td class="ev-actions">
                            <?php
                            $verify_url = EVDA_Document::get_verification_url( $doc->verification_id );
                            ?>
                            <a href="<?php echo esc_url( $verify_url ); ?>" target="_blank" class="button button-small">View</a>
                            <?php if ( $doc->pdf_path ) : ?>
                                <a href="<?php echo esc_url( EVDA_PDF::get_pdf_url( $doc->pdf_path ) ); ?>" target="_blank" class="button button-small">Download</a>
                            <?php endif; ?>
                            <?php if ( $doc->is_valid ) : ?>
                                <button class="button button-small ev-revoke-doc" data-vid="<?php echo esc_attr( $doc->verification_id ); ?>">Revoke</button>
                            <?php endif; ?>
                            <button class="button button-small button-link-delete ev-delete-doc" data-id="<?php echo absint( $doc->id ); ?>">Delete</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    // =========================================================================
    // Settings page
    // =========================================================================
    public function page_settings() {
        if ( isset($_POST['evda_save_settings']) && check_admin_referer('evda_settings_nonce') ) {
            // General
            update_option('evda_company_logo',               absint( $_POST['evda_company_logo'] ?? 0 ));
            update_option('evda_document_verification_page', absint( $_POST['evda_doc_verify_page'] ?? 0 ));
            update_option('evda_employee_verification_page', absint( $_POST['evda_emp_verify_page'] ?? 0 ));
            update_option('evda_keep_data_on_uninstall',     isset($_POST['evda_keep_data_on_uninstall']) ? 1 : 0);
            // Email
            update_option('evda_email_enabled',              isset($_POST['evda_email_enabled']) ? 1 : 0);
            update_option('evda_email_subject_internship',   sanitize_text_field( wp_unslash( $_POST['evda_email_subject_internship'] ?? '' ) ));
            update_option('evda_email_subject_experience',   sanitize_text_field( wp_unslash( $_POST['evda_email_subject_experience'] ?? '' ) ));
            update_option('evda_email_body_internship',      wp_kses_post( wp_unslash( $_POST['evda_email_body_internship'] ?? '' ) ));
            update_option('evda_email_body_experience',      wp_kses_post( wp_unslash( $_POST['evda_email_body_experience'] ?? '' ) ));
            // Email colors
            update_option('evda_email_color_header',   sanitize_hex_color( wp_unslash( $_POST['evda_email_color_header']   ?? '#003366' ) ));
            update_option('evda_email_color_accent',   sanitize_hex_color( wp_unslash( $_POST['evda_email_color_accent']   ?? '#003366' ) ));
            update_option('evda_email_color_button',   sanitize_hex_color( wp_unslash( $_POST['evda_email_color_button']   ?? '#003366' ) ));
            update_option('evda_email_color_bg',       sanitize_hex_color( wp_unslash( $_POST['evda_email_color_bg']       ?? '#f4f6f9' ) ));
            update_option('evda_email_color_text',     sanitize_hex_color( wp_unslash( $_POST['evda_email_color_text']     ?? '#333333' ) ));
            update_option('evda_email_color_title_bg', sanitize_hex_color( wp_unslash( $_POST['evda_email_color_title_bg'] ?? '#e8f0fe' ) ));
            update_option('evda_email_color_title_tx', sanitize_hex_color( wp_unslash( $_POST['evda_email_color_title_tx'] ?? '#003366' ) ));
            // Letter templates
            update_option('evda_letter_template_internship', wp_kses_post( wp_unslash( $_POST['evda_letter_template_internship'] ?? '' ) ));
            update_option('evda_letter_template_experience', wp_kses_post( wp_unslash( $_POST['evda_letter_template_experience'] ?? '' ) ));
            // Styling
            $style_keys = array('primary_color','text_color','bg_color','border_color','font_family',
                                 'font_size_body','font_size_title','line_height','page_padding',
                                 'show_border','title_underline','show_qr','print_bar_color');
            foreach ($style_keys as $k) {
                update_option( 'evda_letter_style_' . $k, sanitize_text_field( wp_unslash( $_POST[ 'evda_style_' . $k ] ?? '' ) ) );
            }
            // Signature settings
            update_option('evda_signature_enabled', !empty($_POST['evda_signature_enabled']) ? 1 : 0);
            update_option('evda_signature_name',    sanitize_text_field( wp_unslash( $_POST['evda_signature_name']  ?? '' ) ));
            update_option('evda_signature_title',   sanitize_text_field( wp_unslash( $_POST['evda_signature_title'] ?? '' ) ));
            update_option('evda_signature_image',   absint($_POST['evda_signature_image'] ?? 0));
            // Letter footer message
            update_option('evda_letter_footer_message', sanitize_textarea_field( wp_unslash( $_POST['evda_letter_footer_message'] ?? '' ) ));
            update_option('evda_letter_header_info',    sanitize_textarea_field( wp_unslash( $_POST['evda_letter_header_info']    ?? '' ) ));
            // Custom field definitions
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            $cf_labels = array_map( 'sanitize_text_field', array_map( 'wp_unslash', $_POST['evda_cf_label'] ?? array() ) );
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            $cf_keys   = array_map( 'sanitize_key', array_map( 'wp_unslash', $_POST['evda_cf_key'] ?? array() ) );
            $defs = array();
            foreach ($cf_labels as $i => $lbl) {
                if ($lbl && !empty($cf_keys[$i])) $defs[] = array('label'=>$lbl,'key'=>$cf_keys[$i]);
            }
            update_option('evda_custom_field_defs', $defs);
            // Search field settings
            $search_fields = array();
            $allowed = array('employee_id', 'full_name', 'cnic');
            foreach ($allowed as $f) {
                if (!empty($_POST['evda_search_field_'.$f])) $search_fields[] = $f;
            }
            // Must have at least one — default to employee_id
            if (empty($search_fields)) $search_fields = array('employee_id');
            update_option('evda_search_fields', $search_fields);
            echo wp_kses_post( '<div class="notice notice-success is-dismissible"><p>✅ Settings saved.</p></div>' );
        }

        $s           = EVDA_PDF::get_all_styles();
        $logo_id     = get_option('evda_company_logo', 0);
        $doc_page_id = get_option('evda_document_verification_page', 0);
        $emp_page_id = get_option('evda_employee_verification_page', 0);
        $keep_data   = get_option('evda_keep_data_on_uninstall', 1); // default ON — protect data
        $email_on    = get_option('evda_email_enabled', 1);
        $si          = get_option('evda_email_subject_internship', 'Your Internship Completion Letter from {company}');
        $se          = get_option('evda_email_subject_experience',  'Your Experience Letter from {company}');
        $bi          = get_option('evda_email_body_internship', '');
        $be          = get_option('evda_email_body_experience', '');
        $ec_header   = get_option('evda_email_color_header',   '#003366');
        $ec_accent   = get_option('evda_email_color_accent',   '#003366');
        $ec_button   = get_option('evda_email_color_button',   '#003366');
        $ec_bg       = get_option('evda_email_color_bg',       '#f4f6f9');
        $ec_text     = get_option('evda_email_color_text',     '#333333');
        $ec_title_bg = get_option('evda_email_color_title_bg', '#e8f0fe');
        $ec_title_tx = get_option('evda_email_color_title_tx', '#003366');
        $ti          = get_option('evda_letter_template_internship', '');
        $te          = get_option('evda_letter_template_experience', '');
        $cf_defs       = get_option('evda_custom_field_defs', array());
        $search_fields    = get_option('evda_search_fields', array('employee_id'));
        $sig_enabled      = get_option('evda_signature_enabled', 0);
        $sig_name         = get_option('evda_signature_name', '');
        $sig_title        = get_option('evda_signature_title', '');
        $sig_image_id     = get_option('evda_signature_image', 0);
        $footer_msg       = get_option('evda_letter_footer_message', '');
        $header_info      = get_option('evda_letter_header_info', '');

        $letter_ph = '{employee_name}, {employee_id}, {father_name}, {cnic}, {position}, {department}, {company}, {joining_date}, {leaving_date}, {intern_start}, {intern_end}, {issue_date}, {verification_id}, {tasks_section}, {custom_fields}';
        $email_ph  = '{employee_name}, {employee_id}, {position}, {department}, {company}, {issue_date}, {verification_id}, {verify_url}, {letter_type}';

        $fonts = array(
            'Segoe UI, Arial, sans-serif'  => 'Segoe UI (Default)',
            'Arial, sans-serif'            => 'Arial',
            'Georgia, serif'              => 'Georgia',
            'Times New Roman, serif'      => 'Times New Roman',
            'Calibri, sans-serif'         => 'Calibri',
            'Helvetica, Arial, sans-serif'=> 'Helvetica',
            'Tahoma, sans-serif'          => 'Tahoma',
            'Verdana, sans-serif'         => 'Verdana',
        );
        ?>
        <div class="wrap ev-admin-wrap">
        <h1>⚙️ Settings</h1>
        <form method="POST">
        <?php wp_nonce_field('evda_settings_nonce'); ?>

        <div class="ev-tabs">
            <button type="button" class="ev-tab active" data-tab="general">🏢 General</button>
            <button type="button" class="ev-tab" data-tab="letter">📝 Letter Templates</button>
            <button type="button" class="ev-tab" data-tab="styling">🎨 Styling</button>
            <button type="button" class="ev-tab" data-tab="email">📧 Email</button>
            <button type="button" class="ev-tab" data-tab="customfields">🔧 Custom Fields</button>
            <button type="button" class="ev-tab" data-tab="otherletters">📄 Other Letters</button>
        </div>

        <!-- GENERAL -->
        <div class="ev-tab-panel active" id="tab-general">
            <table class="form-table">
                <tr>
                    <th>Company Logo</th>
                    <td>
                        <input type="hidden" name="evda_company_logo" id="ev-settings-logo-id" value="<?php echo absint( $logo_id ); ?>">
                        <button type="button" class="button" id="ev-upload-logo">📷 Upload / Change Logo</button>
                        <?php if ($logo_id) echo '<br>' . wp_get_attachment_image($logo_id,'thumbnail'); ?>
                        <p class="description">Appears in all generated letters.</p>
                    </td>
                </tr>
                <tr>
                    <th>Employee Verification Page</th>
                    <td>
                        <?php wp_dropdown_pages(array('name'=>'evda_emp_verify_page','selected'=>absint($emp_page_id),'show_option_none'=>'— Select —','option_none_value'=>0)); ?>
                        <p class="description">Page containing <code>[evda_employee_verification]</code> shortcode.</p>
                    </td>
                </tr>
                <tr>
                    <th>Document Verification Page</th>
                    <td>
                        <?php wp_dropdown_pages(array('name'=>'evda_doc_verify_page','selected'=>absint($doc_page_id),'show_option_none'=>'— Select —','option_none_value'=>0)); ?>
                        <p class="description">Page containing <code>[evda_document_verification]</code> shortcode.</p>
                    </td>
                </tr>
                <tr>
                    <th class="evda-settings-th-top">Employee Search Fields</th>
                    <td>
                        <p class="evda-search-fields-desc">
                            Choose which fields visitors can use to search on the
                            <strong>[evda_employee_verification]</strong> shortcode page.
                            At least one must be selected.
                        </p>
                        <div class="evda-search-fields-wrap">
                            <label class="ev-search-opt">
                                <input type="checkbox" name="evda_search_field_employee_id" value="1"
                                    <?php checked(in_array('employee_id', $search_fields)); ?>>
                                <span class="ev-search-label">
                                    <strong>Employee ID</strong>
                                    <small>Search by the unique employee code (e.g. EMP-001)</small>
                                </span>
                            </label>
                            <label class="ev-search-opt">
                                <input type="checkbox" name="evda_search_field_full_name" value="1"
                                    <?php checked(in_array('full_name', $search_fields)); ?>>
                                <span class="ev-search-label">
                                    <strong>Full Name</strong>
                                    <small>Search by the employee's full name (partial match supported)</small>
                                </span>
                            </label>
                            <label class="ev-search-opt">
                                <input type="checkbox" name="evda_search_field_cnic" value="1"
                                    <?php checked(in_array('cnic', $search_fields)); ?>>
                                <span class="ev-search-label">
                                    <strong>CNIC #</strong>
                                    <small>Search by National Identity Card number</small>
                                </span>
                            </label>
                        </div>
                        <p class="description">
                            Enabled fields show as separate inputs on the verification page.
                            Only one needs to match to find the employee.
                        </p>
                    </td>
                </tr>
                <tr>
                    <th class="evda-settings-th-top">🛡️ Data Safety</th>
                    <td>
                        <label class="ev-search-opt evda-keep-data-opt">
                            <input type="checkbox" name="evda_keep_data_on_uninstall" value="1"
                                <?php checked($keep_data, 1); ?>>
                            <span class="ev-search-label">
                                <strong>Keep all data when plugin is deleted</strong>
                                <small>If checked, employees, documents and settings are preserved even if this plugin is fully uninstalled. Uncheck only if you want a clean removal.</small>
                            </span>
                        </label>
                    </td>
                </tr>
            </table>
        </div>

        <!-- LETTER TEMPLATES -->
        <div class="ev-tab-panel" id="tab-letter">
            <div class="ev-ph-box"><strong>Available Placeholders:</strong><br><?php echo esc_html($letter_ph); ?></div>
            <p class="description evda-template-desc">
                <strong>{tasks_section}</strong> — auto bullet list from "Tasks Completed" field.<br>
                <strong>{custom_fields}</strong> — custom fields marked "Show in letter".<br>
                Use plain line breaks for paragraphs. HTML tags like &lt;strong&gt; are supported.
            </p>
            <h3 class="ev-section-title">🎓 Internship Completion Letter</h3>
            <textarea name="evda_letter_template_internship" rows="13" class="large-text"
                class="evda-template-textarea"><?php echo esc_textarea($ti ?: EVDA_PDF::get_default_template('internship')); ?></textarea>
            <p class="description">Edit freely. Leave completely blank and save to reset to default.</p>

            <h3 class="ev-section-title evda-section-mt">💼 Experience Letter</h3>
            <textarea name="evda_letter_template_experience" rows="13" class="large-text"
                class="evda-template-textarea"><?php echo esc_textarea($te ?: EVDA_PDF::get_default_template('experience')); ?></textarea>

            <p class="evda-italic-hint">
                ⚠ Changes here affect newly generated letters only. Already-generated letters are saved as HTML files — delete and re-generate to apply new templates.
            </p>
        </div>

        <!-- STYLING -->
        <div class="ev-tab-panel" id="tab-styling">
            <div class="evda-grid-2col-wide">
                <!-- Colors -->
                <div>
                    <h3 class="ev-section-title evda-section-title-0">🎨 Colors</h3>
                    <?php
                    $color_fields = array(
                        'primary_color'   => array('Primary / Accent Color',  'Title, borders, links, strong text'),
                        'text_color'      => array('Body Text Color',         'Main paragraph text'),
                        'bg_color'        => array('Page Background',         'Letter background'),
                        'border_color'    => array('Header Border',           'Line under the header'),
                        'print_bar_color' => array('Print Bar Color',         'Background of the "Print / Save as PDF" bar at the top'),
                    );
                    foreach ($color_fields as $key => [$label, $desc]) :
                    ?>
                    <div class="evda-color-row">
                        <input type="color" name="evda_style_<?php echo esc_attr( $key ); ?>"
                            id="ev-color-<?php echo esc_attr( $key ); ?>"
                            value="<?php echo esc_attr($s[$key]); ?>"
                            class="evda-color-swatch evda-color-picker"
                            data-target="ev-colortext-<?php echo esc_attr( $key ); ?>">
                        <input type="text" id="ev-colortext-<?php echo esc_attr( $key ); ?>"
                            value="<?php echo esc_attr($s[$key]); ?>"
                            class="evda-hex-field evda-hex-text"
                            data-target="ev-color-<?php echo esc_attr( $key ); ?>">
                        <span><strong><?php echo esc_html($label); ?></strong><br><small class="evda-dept-text"><?php echo esc_html($desc); ?></small></span>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Typography -->
                <div>
                    <h3 class="ev-section-title evda-section-title-0">🔤 Typography</h3>
                    <table class="form-table evda-form-table-0">
                        <tr>
                            <th class="evda-th-160">Font Family</th>
                            <td>
                                <select name="evda_style_font_family">
                                    <?php foreach ($fonts as $val => $lbl) : ?>
                                        <option value="<?php echo esc_attr($val); ?>"
                                            <?php selected($s['font_family'],$val); ?>
                                            data-font="<?php echo esc_attr($val); ?>">
                                            <?php echo esc_html($lbl); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th>Body Font Size</th>
                            <td><input type="number" name="evda_style_font_size_body" value="<?php echo esc_attr($s['font_size_body']); ?>" min="10" max="22" class="evda-number-input-sm"> px</td>
                        </tr>
                        <tr>
                            <th>Title Font Size</th>
                            <td><input type="number" name="evda_style_font_size_title" value="<?php echo esc_attr($s['font_size_title']); ?>" min="14" max="36" class="evda-number-input-sm"> px</td>
                        </tr>
                        <tr>
                            <th>Line Height</th>
                            <td><input type="number" name="evda_style_line_height" value="<?php echo esc_attr($s['line_height']); ?>" min="1.0" max="3.0" step="0.1" class="evda-lh-input"></td>
                        </tr>
                    </table>

                    <h3 class="ev-section-title evda-section-title-24">📐 Layout</h3>
                    <table class="form-table evda-form-table-0">
                        <tr>
                            <th>Page Padding</th>
                            <td>
                                <input type="text" name="evda_style_page_padding" value="<?php echo esc_attr($s['page_padding']); ?>" class="evda-padding-input-wide" placeholder="50px 60px">
                                <p class="description">e.g. <code>50px 60px</code> (top/bottom left/right)</p>
                            </td>
                        </tr>
                        <tr>
                            <th>Border &amp; Shadow</th>
                            <td><label><input type="checkbox" name="evda_style_show_border" value="1" <?php checked($s['show_border'],'1'); ?>> Show border &amp; drop shadow around letter</label></td>
                        </tr>
                        <tr>
                            <th>Underline Title</th>
                            <td><label><input type="checkbox" name="evda_style_title_underline" value="1" <?php checked($s['title_underline'],'1'); ?>> Underline the letter title text</label></td>
                        </tr>
                        <tr>
                            <th>QR Code</th>
                            <td><label><input type="checkbox" name="evda_style_show_qr" value="1" <?php checked($s['show_qr'],'1'); ?>> Show QR code on letter</label></td>
                        </tr>
                    </table>
                </div>
            </div>

            <!-- Signature section — full width below the two columns -->
            <div class="evda-section-divider">
                <h3 class="ev-section-title evda-section-title-0">✍️ Signature Block</h3>
                <table class="form-table evda-form-table-0">
                    <tr>
                        <th class="evda-th-200">Show Signature on Letters</th>
                        <td>
                            <label>
                                <input type="checkbox" name="evda_signature_enabled" value="1"
                                    id="ev-sig-toggle"
                                    <?php checked($sig_enabled, 1); ?>>
                                Display a signature block (name, title, image) at the bottom of letters
                            </label>
                        </td>
                    </tr>
                </table>
                <div id="ev-sig-fields" class="evda-sig-fields<?php echo esc_attr( $sig_enabled ? '' : ' evda-hidden' ); ?>">
                    <table class="form-table evda-form-table-0">
                        <tr>
                            <th class="evda-th-200">Signatory Name</th>
                            <td>
                                <input type="text" name="evda_signature_name" class="regular-text"
                                    value="<?php echo esc_attr($sig_name); ?>"
                                    placeholder="e.g. Muhammad Ali">
                                <p class="description">Printed in bold under the signature image.</p>
                            </td>
                        </tr>
                        <tr>
                            <th>Signatory Title / Role</th>
                            <td>
                                <input type="text" name="evda_signature_title" class="regular-text"
                                    value="<?php echo esc_attr($sig_title); ?>"
                                    placeholder="e.g. CEO / HR Manager / Director">
                            </td>
                        </tr>
                        <tr>
                            <th>Signature Image</th>
                            <td>
                                <input type="hidden" name="evda_signature_image"
                                    id="ev-sig-image-id" value="<?php echo absint( $sig_image_id ); ?>">
                                <button type="button" class="button" id="ev-upload-signature">
                                    🖊 Upload Signature Image
                                </button>
                                <button type="button" class="button evda-remove-sig-btn<?php echo esc_attr( $sig_image_id ? '' : ' evda-hidden' ); ?>" id="ev-remove-signature">
                                    ✕ Remove
                                </button>
                                <div id="ev-sig-preview" class="evda-sig-preview">
                                    <?php if ($sig_image_id) : ?>
                                        <?php echo wp_get_attachment_image($sig_image_id, array(200,80),
                                            false, array('style'=>'max-height:80px;max-width:200px;display:block;')); ?>
                                    <?php endif; ?>
                                </div>
                                <p class="description">Optional. Upload a PNG with transparent background for best results. Recommended size: 400×160px.</p>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>

            <!-- Header top-right info -->
            <div class="evda-section-divider">
                <h3 class="ev-section-title evda-section-title-0">📋 Letter Header — Top Right Info</h3>
                <p class="evda-subtext">
                    This text appears in the <strong>top-right corner</strong> of every letter, next to the logo.
                    Use it for your address, phone, website, or any other info you want visible in the header.
                    The issue date is shown automatically below this text.
                </p>
                <textarea name="evda_letter_header_info" rows="3" class="large-text"
                    class="evda-textarea-wide"
                    placeholder="e.g. 123 Main Street, Karachi&#10;Phone: +92-300-1234567&#10;www.skfreelancers.com"><?php echo esc_textarea($header_info); ?></textarea>
                <p class="description evda-desc-mt">
                    Leave blank to show only the date. Each line break creates a new line in the header.
                </p>
            </div>

            <!-- Footer custom message -->
            <div class="evda-section-divider">
                <h3 class="ev-section-title evda-section-title-0">📌 Letter Footer Message</h3>
                <p class="evda-subtext">
                    This text appears at the very bottom of every letter, above the Verification ID strip.
                    Use it for your office address, phone number, website, or any other contact info.
                </p>
                <textarea name="evda_letter_footer_message" rows="3" class="large-text"
                    class="evda-textarea-wide"
                    placeholder="e.g. Office: 123 Main Street, Karachi | Phone: +92-300-1234567 | www.yourcompany.com"><?php echo esc_textarea($footer_msg); ?></textarea>
                <p class="description evda-desc-mt">
                    Leave blank to show no custom message. Supports line breaks — each new line appears on a separate line in the footer.
                </p>
            </div>

            <!-- One-click fix for existing letters -->
            <div class="evda-warning-box">
                <strong>⚙️ Migrate Old Letters to Live Rendering</strong>
                <p class="evda-warning-text">
                    Letters generated before v1.1.4 were saved as static HTML files that don't update when you change settings.
                    Click below to convert all old static letters to <strong>live rendering</strong> — after this, every letter
                    will always reflect your current settings (typography, signature, footer, etc.) automatically.
                </p>
                <button type="button" class="button button-primary" id="ev-patch-letters">⚡ Convert to Live Rendering</button>
                <span id="ev-patch-result" class="evda-patch-result"></span>
            </div>
        </div>

        <!-- EMAIL -->
        <div class="ev-tab-panel" id="tab-email">
            <div class="ev-ph-box"><strong>Email Placeholders:</strong><br><?php echo esc_html($email_ph); ?></div>
            <table class="form-table">
                <tr>
                    <th>Enable Emails</th>
                    <td>
                        <label><input type="checkbox" name="evda_email_enabled" value="1" <?php checked($email_on,1); ?>>
                        Auto-email employees when a letter is issued</label>
                        <p class="description">Uses WordPress <code>wp_mail()</code>. Works on Namecheap hosting.</p>
                    </td>
                </tr>
                <tr>
                    <th>🎓 Internship Subject</th>
                    <td><input type="text" name="evda_email_subject_internship" class="large-text" value="<?php echo esc_attr($si); ?>"></td>
                </tr>
                <tr>
                    <th>🎓 Internship Body</th>
                    <td>
                        <textarea name="evda_email_body_internship" rows="7" class="large-text"><?php echo esc_textarea($bi); ?></textarea>
                        <p class="description">Leave blank to use the built-in default.</p>
                    </td>
                </tr>
                <tr>
                    <th>💼 Experience Subject</th>
                    <td><input type="text" name="evda_email_subject_experience" class="large-text" value="<?php echo esc_attr($se); ?>"></td>
                </tr>
                <tr>
                    <th>💼 Experience Body</th>
                    <td>
                        <textarea name="evda_email_body_experience" rows="7" class="large-text"><?php echo esc_textarea($be); ?></textarea>
                    </td>
                </tr>
            </table>

            <h3 class="ev-section-title evda-section-title-28">🎨 Email Template Colors</h3>
            <p class="evda-email-subtext">
                Customize the colors used in the HTML email sent to employees when a letter is issued.
            </p>
            <table class="form-table">
                <?php
                $email_color_fields = array(
                    'evda_email_color_header'   => array($ec_header,   'Header Background',      'Background color of the email header bar'),
                    'evda_email_color_title_bg' => array($ec_title_bg, 'Title Bar Background',   'Background of the "Letter Issued" heading bar'),
                    'evda_email_color_title_tx' => array($ec_title_tx, 'Title Bar Text & Border','Text color of the heading and the bottom border'),
                    'evda_email_color_button'   => array($ec_button,   'Verify Button',          'Background color of the "Verify This Letter" button'),
                    'evda_email_color_bg'       => array($ec_bg,       'Email Background',       'Outer background behind the email card'),
                    'evda_email_color_text'     => array($ec_text,     'Body Text',              'Main body text color'),
                );
                foreach ($email_color_fields as $name => [$val, $label, $desc]) : ?>
                <tr>
                    <th class="evda-th-200-dyn"><?php echo esc_html($label); ?></th>
                    <td>
                        <div class="evda-color-row-email">
                            <input type="color" name="<?php echo esc_attr($name); ?>"
                                value="<?php echo esc_attr($val); ?>"
                                class="evda-color-swatch-email">
                            <input type="text" name="<?php echo esc_attr($name); ?>_hex"
                                value="<?php echo esc_attr($val); ?>"
                                class="evda-hex-field-email evda-hex-sibling">
                            <span class="evda-color-label-desc"><?php echo esc_html($desc); ?></span>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </table>

            <h3 class="ev-section-title evda-section-title-28">📤 Send Test Email</h3>
            <table class="form-table">
                <tr>
                    <th>Test Recipient</th>
                    <td>
                        <input type="email" id="ev-test-email-addr" class="regular-text" placeholder="test@example.com">
                        <button type="button" class="button button-primary evda-test-email-btn" id="ev-send-test-email">📤 Send Test Email</button>
                        <span id="ev-test-email-result" class="evda-test-email-result"></span>
                        <p class="description">Sends a test HTML email using your current color settings so you can preview exactly how it looks in an inbox.</p>
                    </td>
                </tr>
            </table>
        </div>

        <!-- CUSTOM FIELDS -->
        <div class="ev-tab-panel" id="tab-customfields">
            <p>Define extra fields for every employee's profile. After saving, they appear in the <strong>Add/Edit Employee</strong> form. Toggle "Show in letter" per-employee to include them in generated letters via <code>{custom_fields}</code>.</p>
            <table class="widefat evda-cf-table-sm">
                <thead><tr><th>Field Label</th><th>Field Key <small>(no spaces)</small></th><th></th></tr></thead>
                <tbody id="ev-cf-rows">
                    <?php foreach ($cf_defs as $cf) : ?>
                    <tr class="ev-cf-row">
                        <td><input type="text" name="evda_cf_label[]" value="<?php echo esc_attr($cf['label']); ?>" class="regular-text" placeholder="e.g. Salary"></td>
                        <td><input type="text" name="evda_cf_key[]"   value="<?php echo esc_attr($cf['key']); ?>"   class="regular-text" placeholder="e.g. salary"></td>
                        <td><button type="button" class="button ev-cf-remove">🗑</button></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <button type="button" class="button evda-cf-add-btn" id="ev-cf-add">➕ Add Custom Field</button>
            <p class="description">
                Examples: <em>LinkedIn URL</em> / <em>linkedin</em> &nbsp;|&nbsp; <em>Salary</em> / <em>salary</em> &nbsp;|&nbsp; <em>Reference</em> / <em>reference</em>
            </p>
        </div>

        <p class="evda-submit-wrap">
            <!-- ===================== OTHER LETTERS ===================== -->
            <div class="ev-tab-panel" id="tab-otherletters">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;">
                    <h2 style="margin:0;"><?php esc_html_e( 'Other Letter Types', 'employee-verification-document-authentication' ); ?></h2>
                    <div style="display:flex;gap:10px;align-items:center;">
                        <button type="button" class="button" id="evda-reseed-templates-btn"
                                title="<?php esc_attr_e( 'Reset all letter templates and email content to professional HR defaults. Does not affect your custom letter types or settings.', 'employee-verification-document-authentication' ); ?>">
                            🔄 <?php esc_html_e( 'Reset Templates to Default', 'employee-verification-document-authentication' ); ?>
                        </button>
                        <button type="button" class="button button-primary" id="evda-add-letter-type-btn">
                            ➕ <?php esc_html_e( 'Add New Letter Type', 'employee-verification-document-authentication' ); ?>
                        </button>
                    </div>
                </div>
                <p style="color:#555;margin-bottom:16px;">
                    <?php esc_html_e( 'Manage dynamic letter types. Active types appear under "Other Letters" on the Employees page.', 'employee-verification-document-authentication' ); ?>
                </p>

                <?php
                $other_types    = EVDA_LetterTypes::get_all();
                $builtin_labels = array(
                    'internship' => __( 'Internship Completion Letter (built-in)', 'employee-verification-document-authentication' ),
                    'experience' => __( 'Experience Letter (built-in)', 'employee-verification-document-authentication' ),
                );
                ?>
                <table class="wp-list-table widefat fixed striped evda-letter-types-table">
                    <thead>
                        <tr>
                            <th style="width:28%;"><?php esc_html_e( 'Letter Name', 'employee-verification-document-authentication' ); ?></th>
                            <th style="width:12%;"><?php esc_html_e( 'Slug', 'employee-verification-document-authentication' ); ?></th>
                            <th style="width:10%;"><?php esc_html_e( 'Status', 'employee-verification-document-authentication' ); ?></th>
                            <th style="width:14%;"><?php esc_html_e( 'Multiple?', 'employee-verification-document-authentication' ); ?></th>
                            <th style="width:20%;"><?php esc_html_e( 'Extra Fields', 'employee-verification-document-authentication' ); ?></th>
                            <th style="width:16%;"><?php esc_html_e( 'Actions', 'employee-verification-document-authentication' ); ?></th>
                        </tr>
                    </thead>
                    <tbody id="evda-letter-types-list">
                        <?php foreach ( $builtin_labels as $slug => $label ) : ?>
                        <tr>
                            <td><strong><?php echo esc_html( $label ); ?></strong></td>
                            <td><code><?php echo esc_html( $slug ); ?></code></td>
                            <td><span class="evda-filter-badge"><?php esc_html_e( 'Active', 'employee-verification-document-authentication' ); ?></span></td>
                            <td><?php esc_html_e( 'No', 'employee-verification-document-authentication' ); ?></td>
                            <td>—</td>
                            <td><em style="color:#888;"><?php esc_html_e( 'Built-in', 'employee-verification-document-authentication' ); ?></em></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php foreach ( $other_types as $lt ) : ?>
                        <tr id="evda-lt-row-<?php echo esc_attr( $lt['slug'] ); ?>">
                            <td><strong><?php echo esc_html( $lt['name'] ); ?></strong>
                                <?php if ( ! empty( $lt['description'] ) ) : ?>
                                <br><small style="color:#888;"><?php echo esc_html( $lt['description'] ); ?></small>
                                <?php endif; ?>
                            </td>
                            <td><code><?php echo esc_html( $lt['slug'] ); ?></code></td>
                            <td>
                                <span class="evda-filter-badge <?php echo $lt['status'] === 'active' ? '' : 'evda-filter-badge--inactive'; ?>">
                                    <?php echo $lt['status'] === 'active' ? esc_html__( 'Active', 'employee-verification-document-authentication' ) : esc_html__( 'Inactive', 'employee-verification-document-authentication' ); ?>
                                </span>
                            </td>
                            <td><?php echo ! empty( $lt['allow_multiple'] ) ? esc_html__( 'Yes', 'employee-verification-document-authentication' ) : esc_html__( 'No', 'employee-verification-document-authentication' ); ?></td>
                            <td>
                                <?php if ( ! empty( $lt['extra_fields'] ) ) : ?>
                                    <?php foreach ( $lt['extra_fields'] as $ef ) : ?>
                                        <span style="display:inline-block;background:#f0f0f1;padding:2px 7px;border-radius:4px;font-size:11px;margin:2px;">
                                            <?php echo esc_html( $ef['label'] ); ?>
                                            <?php echo $ef['required'] ? '<sup style="color:red;">*</sup>' : ''; ?>
                                        </span>
                                    <?php endforeach; ?>
                                <?php else : ?>
                                    <em style="color:#aaa;"><?php esc_html_e( 'None', 'employee-verification-document-authentication' ); ?></em>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button type="button" class="button button-small evda-edit-lt-btn"
                                        data-lt="<?php echo esc_attr( wp_json_encode( $lt ) ); ?>">
                                    ✏️ <?php esc_html_e( 'Edit', 'employee-verification-document-authentication' ); ?>
                                </button>
                                <button type="button" class="button button-small button-link-delete evda-delete-lt-btn"
                                        data-slug="<?php echo esc_attr( $lt['slug'] ); ?>"
                                        data-name="<?php echo esc_attr( $lt['name'] ); ?>">
                                    🗑 <?php esc_html_e( 'Delete', 'employee-verification-document-authentication' ); ?>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if ( empty( $other_types ) ) : ?>
                        <tr id="evda-lt-empty-row">
                            <td colspan="6" style="text-align:center;color:#888;padding:20px;">
                                <?php esc_html_e( 'No custom letter types yet. Click "Add New Letter Type" to create one.', 'employee-verification-document-authentication' ); ?>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <input type="submit" name="evda_save_settings" class="button button-primary button-large" value="💾 Save All Settings">
        </p>
        </form>
        </div>


        <!-- ============================================================ -->
        <!-- Letter Type Editor Modal                                       -->
        <!-- ============================================================ -->
        <div id="evda-lt-modal" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,.6);z-index:99999;overflow-y:auto;">
            <div style="background:#fff;border-radius:8px;max-width:740px;width:96%;margin:40px auto;padding:30px 32px;box-shadow:0 8px 32px rgba(0,0,0,.2);position:relative;">
                <button type="button" id="evda-lt-modal-close" style="position:absolute;top:14px;right:16px;background:none;border:none;font-size:22px;cursor:pointer;color:#888;">&times;</button>
                <h2 id="evda-lt-modal-heading" style="margin:0 0 20px;font-size:18px;"><?php esc_html_e( 'Add New Letter Type', 'employee-verification-document-authentication' ); ?></h2>
                <input type="hidden" id="evda-lt-is-edit" value="0">

                <table class="form-table" style="margin:0;">
                    <tr>
                        <th style="width:170px;"><?php esc_html_e( 'Letter Name', 'employee-verification-document-authentication' ); ?> <span style="color:red;">*</span></th>
                        <td><input type="text" id="evda-lt-name" class="large-text" placeholder="<?php esc_attr_e( 'e.g. Salary Certificate', 'employee-verification-document-authentication' ); ?>"></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'Slug', 'employee-verification-document-authentication' ); ?> <span style="color:red;">*</span></th>
                        <td>
                            <input type="text" id="evda-lt-slug" class="regular-text" placeholder="<?php esc_attr_e( 'e.g. salary_certificate', 'employee-verification-document-authentication' ); ?>">
                            <p class="description"><?php esc_html_e( 'Auto-generated from name. Lowercase, numbers, underscores only. Cannot be changed after creation.', 'employee-verification-document-authentication' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'Description', 'employee-verification-document-authentication' ); ?></th>
                        <td><textarea id="evda-lt-description" rows="2" class="large-text"></textarea></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'Status', 'employee-verification-document-authentication' ); ?></th>
                        <td>
                            <select id="evda-lt-status">
                                <option value="active"><?php esc_html_e( 'Active (visible in Other Letters dropdown)', 'employee-verification-document-authentication' ); ?></option>
                                <option value="inactive"><?php esc_html_e( 'Inactive (hidden)', 'employee-verification-document-authentication' ); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'Allow Multiple', 'employee-verification-document-authentication' ); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" id="evda-lt-allow-multiple" value="1" checked>
                                <?php esc_html_e( 'Allow issuing multiple letters of this type to the same employee', 'employee-verification-document-authentication' ); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th style="vertical-align:top;padding-top:14px;"><?php esc_html_e( 'Letter Template', 'employee-verification-document-authentication' ); ?></th>
                        <td>
                            <textarea id="evda-lt-template" rows="12" class="large-text" style="font-family:monospace;font-size:13px;line-height:1.6;"></textarea>
                            <p class="description">
                                <strong><?php esc_html_e( 'Available placeholders:', 'employee-verification-document-authentication' ); ?></strong><br>
                                <code>{employee_name}</code> <code>{employee_id}</code> <code>{father_name}</code> <code>{cnic}</code>
                                <code>{position}</code> <code>{department}</code> <code>{company}</code>
                                <code>{joining_date}</code> <code>{leaving_date}</code> <code>{issue_date}</code>
                                <code>{verification_id}</code> <code>{tasks_section}</code> <code>{custom_fields}</code><br>
                                <em><?php esc_html_e( 'Extra fields you define below also become placeholders, e.g. {salary}, {bank_name}', 'employee-verification-document-authentication' ); ?></em>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'Email Subject', 'employee-verification-document-authentication' ); ?></th>
                        <td><input type="text" id="evda-lt-email-subject" class="large-text" placeholder="<?php esc_attr_e( 'Your {letter_type} from {company}', 'employee-verification-document-authentication' ); ?>"></td>
                    </tr>
                    <tr>
                        <th style="vertical-align:top;padding-top:14px;"><?php esc_html_e( 'Email Body', 'employee-verification-document-authentication' ); ?></th>
                        <td><textarea id="evda-lt-email-body" rows="6" class="large-text" placeholder="<?php esc_attr_e( 'Dear {employee_name}, ...', 'employee-verification-document-authentication' ); ?>"></textarea></td>
                    </tr>
                </table>

                <!-- Extra Fields Section -->
                <div style="margin-top:24px;padding:20px;background:#f8f9fa;border:1px solid #e0e8f5;border-radius:6px;">
                    <h3 style="margin:0 0 8px;font-size:15px;color:#1d2327;"><?php esc_html_e( 'Extra Fields', 'employee-verification-document-authentication' ); ?></h3>
                    <p style="color:#555;font-size:13px;margin:0 0 14px;">
                        <?php esc_html_e( 'Fields shown to the admin when generating this letter. Each field key becomes a {placeholder} in the template above.', 'employee-verification-document-authentication' ); ?>
                    </p>
                    <table class="widefat" style="margin-bottom:10px;background:#fff;">
                        <thead>
                            <tr>
                                <th style="width:22%;"><?php esc_html_e( 'Key (→ {placeholder})', 'employee-verification-document-authentication' ); ?></th>
                                <th style="width:28%;"><?php esc_html_e( 'Label (shown to admin)', 'employee-verification-document-authentication' ); ?></th>
                                <th style="width:20%;"><?php esc_html_e( 'Input Type', 'employee-verification-document-authentication' ); ?></th>
                                <th style="width:15%;text-align:center;"><?php esc_html_e( 'Required', 'employee-verification-document-authentication' ); ?></th>
                                <th style="width:15%;"></th>
                            </tr>
                        </thead>
                        <tbody id="evda-lt-ef-rows">
                            <!-- Rows injected by JS -->
                        </tbody>
                    </table>
                    <button type="button" class="button" id="evda-lt-add-ef">
                        ➕ <?php esc_html_e( 'Add Field', 'employee-verification-document-authentication' ); ?>
                    </button>
                </div>

                <!-- Action Buttons -->
                <div style="display:flex;align-items:center;gap:12px;margin-top:24px;padding-top:18px;border-top:1px solid #eee;">
                    <button type="button" id="evda-lt-save-btn" class="button button-primary" style="padding:8px 28px;font-size:14px;">
                        💾 <?php esc_html_e( 'Save Letter Type', 'employee-verification-document-authentication' ); ?>
                    </button>
                    <button type="button" id="evda-lt-modal-cancel" class="button" style="padding:8px 18px;">
                        <?php esc_html_e( 'Cancel', 'employee-verification-document-authentication' ); ?>
                    </button>
                    <span id="evda-lt-save-result" style="font-size:13px;flex:1;"></span>
                </div>
            </div>
        </div>
        <?php
    }
    public function page_email_logs() {
        $logs = EVDA_Email::get_logs();
        ?>
        <div class="wrap ev-admin-wrap">
            <h1>📧 Email Logs</h1>
            <p class="evda-log-desc">Last 100 email attempts. Logs are stored in the database.</p>
            <?php if ( empty($logs) ) : ?>
                <p>No emails have been sent yet.</p>
            <?php else : ?>
                <table class="widefat">
                    <thead><tr><th>Employee</th><th>Email</th><th>Type</th><th>Verification ID</th><th>Status</th><th>Sent At</th></tr></thead>
                    <tbody>
                    <?php foreach ( $logs as $log ) : ?>
                        <tr>
                            <td><code><?php echo esc_html($log['employee_id']); ?></code></td>
                            <td><?php echo esc_html($log['email']); ?></td>
                            <td><?php echo esc_html( ucfirst( $log['type'] ) ); ?></td>
                            <td><code><?php echo esc_html($log['vid']); ?></code></td>
                            <td>
                                <?php if($log['sent']): ?>
                                    <span class="ev-status ev-status-active">✅ Sent</span>
                                <?php else: ?>
                                    <span class="ev-status ev-status-invalid">❌ Failed</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html($log['sent_at']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
    }

    // =========================================================================
    // AJAX handlers
    // =========================================================================

    /**
     * Safely reads a POST array field: assigns to an intermediate variable,
     * applies wp_unslash() to every element, then runs the sanitize callback.
     * This satisfies the WordPress.Security.ValidatedSanitizedInput sniff which
     * requires $_POST reads to be unslashed before sanitization and not passed
     * directly into array_map() calls.
     *
     * @param string   $field    The $_POST key to read.
     * @param callable $sanitize Sanitize callback, e.g. 'sanitize_text_field'.
     * @return array Sanitized array, or empty array if field is absent/not array.
     */
    /**
     * Reads a POST array field, unslashes every element, then sanitizes each
     * with the provided callback. $_POST is accessed only inside this method
     * so the scanner's taint trace terminates here.
     * Nonce must be verified by the caller before invoking this helper.
     *
     * @param string   $field    $_POST key.
     * @param callable $sanitize Sanitize function, e.g. 'sanitize_text_field'.
     * @return array
     */
    private static function sanitize_post_array( $field, $sanitize ) {
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- caller verifies nonce
        if ( ! isset( $_POST[ $field ] ) || ! is_array( $_POST[ $field ] ) ) {
            return array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing
        }
        $raw = (array) $_POST[ $field ]; // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.MissingUnslash
        // phpcs:enable WordPress.Security.NonceVerification.Missing
        return array_map( $sanitize, array_map( 'wp_unslash', $raw ) );
    }

    /**
     * Reads a POST array field and returns it unslashed but NOT yet sanitized.
     * Caller is responsible for sanitizing each value individually.
     * Nonce must be verified by the caller before invoking this helper.
     *
     * @param string $field $_POST key.
     * @return array Unslashed array of raw values, or empty array.
     */
    private static function read_post_array( $field ) {
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- caller verifies nonce
        if ( ! isset( $_POST[ $field ] ) || ! is_array( $_POST[ $field ] ) ) {
            return array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing
        }
        $raw = (array) $_POST[ $field ]; // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.MissingUnslash
        // phpcs:enable WordPress.Security.NonceVerification.Missing
        return array_map( 'wp_unslash', $raw );
    }

    private function verify_ajax() {
        check_ajax_referer( 'evda_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );
    }

    public function ajax_save_employee() {
        $this->verify_ajax();
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $id = absint( $_POST['evda_emp_id'] ?? 0 );
        // phpcs:disable WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput
        $data = array(
            'employee_id'     => sanitize_text_field( wp_unslash( $_POST['employee_id']  ?? '' ) ),
            'full_name'       => sanitize_text_field( wp_unslash( $_POST['full_name']    ?? '' ) ),
            'father_name'     => sanitize_text_field( wp_unslash( $_POST['father_name']  ?? '' ) ),
            'cnic'            => sanitize_text_field( wp_unslash( $_POST['cnic']         ?? '' ) ),
            'email'           => sanitize_email( wp_unslash( $_POST['email']             ?? '' ) ),
            'position'        => sanitize_text_field( wp_unslash( $_POST['position']     ?? '' ) ),
            'department'      => sanitize_text_field( wp_unslash( $_POST['department']   ?? '' ) ),
            'joining_date'    => sanitize_text_field( wp_unslash( $_POST['joining_date'] ?? '' ) ),
            'leaving_date'    => sanitize_text_field( wp_unslash( $_POST['leaving_date'] ?? '' ) ),
            'intern_start'    => sanitize_text_field( wp_unslash( $_POST['intern_start'] ?? '' ) ),
            'intern_end'      => sanitize_text_field( wp_unslash( $_POST['intern_end']   ?? '' ) ),
            'status'          => sanitize_text_field( wp_unslash( $_POST['status']       ?? 'Active' ) ),
            'photo_url'       => esc_url_raw( wp_unslash( $_POST['photo_url']            ?? '' ) ),
            'tasks_completed' => sanitize_textarea_field( wp_unslash( $_POST['tasks_completed'] ?? '' ) ),
        );
        // Build custom_fields from posted ev_cf_val / ev_cf_show
        $cf_defs = get_option('evda_custom_field_defs', array());
        if (!empty($cf_defs)) {
            $cf_post_vals = array_map( 'sanitize_text_field', array_map( 'wp_unslash', $_POST['evda_cf_val']  ?? array() ) );
            $cf_post_show = array_map( 'sanitize_text_field', array_map( 'wp_unslash', $_POST['evda_cf_show'] ?? array() ) );
            $cf_arr = array();
            foreach ($cf_defs as $def) {
                $key = $def['key'];
                $cf_arr[] = array(
                    'key'            => $key,
                    'label'          => $def['label'],
                    'value'          => sanitize_text_field($cf_post_vals[$key] ?? ''),
                    'show_in_letter' => !empty($cf_post_show[$key]) ? 1 : 0,
                );
            }
            $data['custom_fields'] = $cf_arr;
        }
        // phpcs:enable WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput

        if ( empty($data['employee_id']) || empty($data['full_name']) || empty($data['position']) || empty($data['department']) ) {
            wp_send_json_error('Required fields missing: Employee ID, Full Name, Position, Department.');
            return;
        }

        global $wpdb;
        if ($id) {
            $existing = EVDA_Employee::get_by_id($id);
            if ($existing && $existing->employee_id !== $data['employee_id']) {
                if (EVDA_Employee::get_by_employee_id($data['employee_id'])) {
                    wp_send_json_error('Another employee already has that Employee ID.'); return;
                }
            }
            EVDA_Employee::update($id, $data);
            wp_send_json_success(array('message' => 'Employee updated successfully.', 'id' => $id));
        } else {
            if (EVDA_Employee::get_by_employee_id($data['employee_id'])) {
                wp_send_json_error('An employee with this ID already exists.'); return;
            }
            $new_id = EVDA_Employee::insert($data);
            if ($new_id) {
                wp_send_json_success(array('message' => 'Employee added successfully.', 'id' => $new_id));
            } else {
                wp_send_json_error('Failed to save employee. Please try again.'); // DB error logged internally
            }
        }
    }

    public function ajax_delete_employee() {
        $this->verify_ajax();
        // phpcs:disable WordPress.Security.NonceVerification.Missing
        $id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
        if ( ! $id ) wp_send_json_error( 'Invalid employee ID.' );
        EVDA_Employee::delete( $id );
        wp_send_json_success( array( 'message' => 'Employee deleted.' ) );
    }

    public function ajax_generate_document() {
        $this->verify_ajax();
        // phpcs:disable WordPress.Security.ValidatedSanitizedInput.MissingUnslash
        $employee_id  = sanitize_text_field( wp_unslash( $_POST['employee_id'] ?? '' ) );
        $type         = sanitize_text_field( wp_unslash( $_POST['doc_type'] ?? 'experience' ) );
        $leaving_date = sanitize_text_field( wp_unslash( $_POST['leaving_date'] ?? '' ) );
        $intern_start = sanitize_text_field( wp_unslash( $_POST['intern_start'] ?? '' ) );
        $intern_end   = sanitize_text_field( wp_unslash( $_POST['intern_end'] ?? '' ) );
        // phpcs:enable WordPress.Security.ValidatedSanitizedInput.MissingUnslash

        if ( empty( $employee_id ) ) {
            wp_send_json_error( 'Employee ID is required.' );
            return;
        }

        $employee = EVDA_Employee::get_by_employee_id( $employee_id );
        if ( ! $employee ) {
            wp_send_json_error( 'Employee not found.' );
            return;
        }

        // For internship letters: update intern dates on employee record if provided
        if ( $type === 'internship' ) {
            $update = array();
            if ( $intern_start ) $update['intern_start'] = $intern_start;
            if ( $intern_end )   $update['intern_end']   = $intern_end;
            if ( ! empty( $update ) ) {
                EVDA_Employee::update( $employee->id, $update );
                // Refresh employee object
                $employee = EVDA_Employee::get_by_id( $employee->id );
            }
        }

        $extra  = array( 'leaving_date' => $leaving_date );
        $result = EVDA_Document::create( $employee_id, $type, $extra );

        if ( $result['success'] ) {
            $vid        = $result['vid'];
            $doc        = EVDA_Document::get_by_verification_id( $vid );
            $pdf_url    = ( $doc && $doc->pdf_path )
                          ? EVDA_PDF::get_pdf_url( $doc->pdf_path ) : '';
            $verify_url = EVDA_Document::get_verification_url( $vid );
            $label      = $type === 'internship' ? 'Internship Completion Letter' : 'Experience Letter';

            // Send email notification if employee has email on file
            $email_sent = false;
            $email_msg  = '';
            if ( $employee && ! empty( $employee->email ) ) {
                $doc_data   = array( 'document_type' => $type, 'issue_date' => current_time('Y-m-d'), 'leaving_date' => $leaving_date );
                $email_sent = EVDA_Email::send_letter_notification( $employee, $doc_data, $vid );
                $email_msg  = $email_sent
                    ? ' Email sent to ' . esc_html( $employee->email ) . '.'
                    : ' (Email failed — check SMTP settings.)';
            }

            wp_send_json_success( array(
                'verification_id' => $vid,
                'verify_url'      => $verify_url,
                'pdf_url'         => $pdf_url,
                'email_sent'      => $email_sent,
                'message'         => $label . ' generated successfully.' . $email_msg,
            ) );
        } else {
            wp_send_json_error( $result['error'] );
        }
    }

    public function ajax_revoke_document() {
        $this->verify_ajax();
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash
        $vid = sanitize_text_field( wp_unslash( $_POST['verification_id'] ?? '' ) );
        EVDA_Document::revoke( $vid );
        wp_send_json_success( array( 'message' => 'Document revoked.' ) );
    }

    public function ajax_delete_document() {
        $this->verify_ajax();
        // phpcs:disable WordPress.Security.NonceVerification.Missing
        $id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
        if ( ! $id ) wp_send_json_error( 'Invalid document ID.' );

        global $wpdb;
        $table = esc_sql( EVDA_Database::validate_table( EVDA_Database::documents_table() ) );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
        $doc = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->prepare(
                "SELECT * FROM `{$table}` WHERE id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                absint( $id )
            )
        );
        if ( ! $doc ) wp_send_json_error( 'Document not found.' );

        // Delete physical file only if it's a real path (not our dynamic: prefix)
        $file_deleted = false;
        if ( ! empty($doc->pdf_path) && strpos($doc->pdf_path, 'dynamic:') !== 0 ) {
            if ( file_exists($doc->pdf_path) ) {
                wp_delete_file( $doc->pdf_path );
        $file_deleted = true;
            }
        }

        $wpdb->delete( EVDA_Database::documents_table(), array( 'id' => absint( $id ) ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

        wp_send_json_success( array( 'message' => 'Document deleted.', 'file_deleted' => $file_deleted ) );
    }

    public function ajax_import_csv() {
        $this->verify_ajax();
        // phpcs:disable WordPress.Security.NonceVerification.Missing
        if ( ! isset( $_FILES['csv_file'] ) || ! isset( $_FILES['csv_file']['tmp_name'] ) ) wp_send_json_error( 'No file uploaded.' );
        $file = sanitize_text_field( wp_unslash( $_FILES['csv_file']['tmp_name'] ) );
        if ( ! is_uploaded_file( $file ) ) wp_send_json_error( 'Invalid file.' );
        $result = EVDA_Employee::import_csv( $file );
        wp_send_json_success( $result );
    }

    // =========================================================================
    // DB Admin Notice + Repair
    // =========================================================================
    public function db_admin_notice() {
        $missing = EVDA_Database::get_missing_tables();
        if ( empty( $missing ) ) return;
        ?>
        <div class="notice notice-error" id="ev-db-notice">
            <p>
                <strong>Employee Verification Plugin:</strong>
                Database tables are missing: <code><?php echo esc_html( implode( ', ', $missing ) ); ?></code><br>
                <button type="button" class="button button-primary evda-repair-btn" id="ev-repair-btn">
                    🔧 Create Tables Now
                </button>
                <span id="ev-repair-result" class="evda-repair-result"></span>
            </p>
        </div>

        <?php
    }

    public function ajax_patch_old_letters() {
        check_ajax_referer('evda_admin_nonce','nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');

        $upload_dir = wp_upload_dir();
        $ev_dir     = $upload_dir['basedir'] . '/ev-documents';
        $files      = glob($ev_dir . '/doc-*.html') ?: array();
        $patched    = 0;

        foreach ($files as $file) {
            $html = file_get_contents($file);
            if (!$html) continue;

            // Remove old hardcoded "Authorized Signatory" block
            // Pattern: the old signature div that contains "Authorized Signatory"
            $patterns = array(
                // Old format: <div style="margin-top:48px...">...<div class="sig-name">Authorized Signatory</div>...</div>
                '/<div[^>]*class="signature"[^>]*>.*?<\/div>\s*<\/div>/si',
                // Any block containing "Authorized Signatory" text
                '/<div[^>]*margin-top:4[0-9]px[^>]*>.*?Authorized Signatory.*?<\/div>\s*<\/div>/si',
            );

            $new_html = $html;
            foreach ($patterns as $pattern) {
                $replaced = preg_replace($pattern, '', $new_html);
                if ($replaced !== null && $replaced !== $new_html) {
                    $new_html = $replaced;
                }
            }

            // Also strip any inline "Authorized Signatory" text that survived
            $new_html = preg_replace('/\s*<[^>]*>\s*Authorized Signatory\s*<\/[^>]*>\s*/i', '', $new_html);
            // Strip "XXXX" placeholder if present
            $new_html = preg_replace('/XXXX/', '', $new_html);

            if ($new_html !== $html) {
                file_put_contents($file, $new_html);
                $patched++;
            }
        }

        wp_send_json_success(array(
            'message' => "Processed " . count($files) . " letter(s). Patched $patched file(s). " .
                         "Delete and re-generate letters to apply new styling settings."
        ));
    }

    public function ajax_send_test_email() {
        check_ajax_referer( 'evda_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );

        $to = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
        if ( ! is_email($to) ) { wp_send_json_error( 'Invalid email address.' ); return; }

        $company    = get_bloginfo('name');
        $verify_url = home_url('/');
        $vid        = 'TEST-' . strtoupper( substr( md5( time() ), 0, 8 ) );
        $headers    = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $company . ' <' . get_option('admin_email') . '>',
        );

        $body_text = "Dear Recipient,\n\nThis is a <strong>test email</strong> from the Employee Verification plugin on <strong>" . esc_html($company) . "</strong>.\n\nIf you received this email, your mail configuration is working correctly and your email colors are displaying as expected.\n\nBest regards,\n" . esc_html($company);

        $body = EVDA_Email::build_test_email_html( $body_text, $company, $verify_url, $vid );
        $sent = wp_mail( $to, 'Test Email — ' . $company . ' Employee Verification', $body, $headers );

        if ( $sent ) wp_send_json_success( array('message' => 'Test email sent to ' . $to . '. Check your inbox!') );
        else         wp_send_json_error( 'wp_mail() returned false. Check your hosting email/SMTP settings.' );
    }

    public function ajax_run_migration() {
        check_ajax_referer( 'evda_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );
        EVDA_Database::migrate_options();
        global $wpdb;
        $emp_count  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}ev_employees" ); // phpcs:ignore
        $doc_count  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}ev_documents" );  // phpcs:ignore
        wp_send_json_success( array(
            'message'    => 'Migration complete! Found ' . $emp_count . ' employees and ' . $doc_count . ' documents.',
            'employees'  => $emp_count,
            'documents'  => $doc_count,
        ) );
    }


    // =========================================================================
    // OTHER LETTERS — Generate
    // =========================================================================
    /**
     * Generates an "Other Letter" (any dynamic letter type).
     * Security: nonce + manage_options via verify_ajax().
     */
    public function ajax_generate_other_letter() {
        $this->verify_ajax();

        $employee_id = sanitize_text_field( wp_unslash( $_POST['employee_id'] ?? '' ) );
        $type        = sanitize_key( wp_unslash( $_POST['letter_type']  ?? '' ) );

        if ( ! $employee_id || ! $type ) {
            wp_send_json_error( esc_html__( 'Employee ID and letter type are required.', 'employee-verification-document-authentication' ) );
        }

        // Validate letter type exists in registry.
        $type_def = EVDA_LetterTypes::get_by_slug( $type );
        if ( ! $type_def ) {
            wp_send_json_error( esc_html__( 'Invalid letter type.', 'employee-verification-document-authentication' ) );
        }

        $employee = EVDA_Employee::get_by_employee_id( $employee_id );
        if ( ! $employee ) {
            wp_send_json_error( esc_html__( 'Employee not found.', 'employee-verification-document-authentication' ) );
        }

        // Collect and sanitize extra field values.
        $extra_data = array();
        // Nonce verified via verify_ajax() above.
        // Use helper to read, unslash, and prepare the extra_data array.
        // Each value is then sanitized individually per-field in the foreach loop below.
        $raw_extra = self::read_post_array( 'extra_data' );

        if ( ! empty( $type_def['extra_fields'] ) ) {
            foreach ( $type_def['extra_fields'] as $field ) {
                $key = sanitize_key( $field['key'] );
                $val = isset( $raw_extra[ $key ] ) ? sanitize_textarea_field( wp_unslash( $raw_extra[ $key ] ) ) : '';
                if ( $field['required'] && '' === $val ) {
                    wp_send_json_error(
                        /* translators: %s: field label */
                        sprintf( 'Field "%s" is required.', sanitize_text_field( $field['label'] ) )
                    );
                }
                $extra_data[ $key ] = $val;
            }
        }

        // Create the document record. extra_data stored as JSON in notes column.
        $notes  = wp_json_encode( array( '_extra_data' => $extra_data ) );
        $result = EVDA_Document::create( $employee_id, $type, array( 'notes' => $notes ) );

        if ( ! $result['success'] ) {
            wp_send_json_error( esc_html( $result['error'] ) );
        }

        $vid        = $result['vid'];
        $doc        = EVDA_Document::get_by_verification_id( $vid );
        $pdf_url    = $doc && $doc->pdf_path ? EVDA_PDF::get_pdf_url( $doc->pdf_path ) : '';
        $verify_url = EVDA_Document::get_verification_url( $vid );
        $label      = esc_html( $type_def['name'] );

        // Send email notification.
        $email_sent = false;
        $email_msg  = '';
        if ( $employee && ! empty( $employee->email ) ) {
            $doc_data = array(
                'document_type' => $type,
                'issue_date'    => current_time( 'Y-m-d' ),
                'extra_data'    => $extra_data,
            );
            $email_sent = EVDA_Email::send_letter_notification( $employee, $doc_data, $vid );
            $email_msg  = $email_sent
                ? ' ' . sprintf(
                    /* translators: %s: employee email address */
                    esc_html__( 'Email sent to %s.', 'employee-verification-document-authentication' ),
                    esc_html( $employee->email )
                  )
                : ' ' . esc_html__( '(Email failed — check SMTP settings.)', 'employee-verification-document-authentication' );
        }

        wp_send_json_success( array(
            'verification_id' => $vid,
            'verify_url'      => $verify_url,
            'pdf_url'         => $pdf_url,
            'email_sent'      => $email_sent,
            'message'         => $label . ' ' . esc_html__( 'generated successfully.', 'employee-verification-document-authentication' ) . $email_msg,
        ) );
    }

    // =========================================================================
    // OTHER LETTERS — Save Letter Type (Settings)
    // =========================================================================
    public function ajax_save_letter_type() {
        check_ajax_referer( 'evda_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( esc_html__( 'Unauthorized.', 'employee-verification-document-authentication' ) );
        }

        // phpcs:disable WordPress.Security.NonceVerification.Missing -- verified above
        $data = array(
            'slug'           => sanitize_key( wp_unslash( $_POST['slug']          ?? '' ) ),
            'name'           => sanitize_text_field( wp_unslash( $_POST['name']   ?? '' ) ),
            'description'    => sanitize_textarea_field( wp_unslash( $_POST['description'] ?? '' ) ),
            'status'         => sanitize_text_field( wp_unslash( $_POST['status'] ?? 'active' ) ),
            'allow_multiple' => ! empty( $_POST['allow_multiple'] ),
            'template'       => wp_kses_post( wp_unslash( $_POST['template']      ?? '' ) ),
            'email_subject'  => sanitize_text_field( wp_unslash( $_POST['email_subject'] ?? '' ) ),
            'email_body'     => wp_kses_post( wp_unslash( $_POST['email_body']    ?? '' ) ),
            'is_edit'        => ! empty( $_POST['is_edit'] ),
            'ef_key'      => self::sanitize_post_array( 'ef_key',      'sanitize_key' ),
            'ef_label'    => self::sanitize_post_array( 'ef_label',    'sanitize_text_field' ),
            'ef_type'     => self::sanitize_post_array( 'ef_type',     'sanitize_text_field' ),
            'ef_required' => self::sanitize_post_array( 'ef_required', 'absint' ),
        );
        // phpcs:enable WordPress.Security.NonceVerification.Missing

        $result = EVDA_LetterTypes::save( $data );
        if ( true === $result ) {
            wp_send_json_success( array( 'message' => esc_html__( 'Letter type saved successfully.', 'employee-verification-document-authentication' ) ) );
        } else {
            wp_send_json_error( esc_html( $result ) );
        }
    }

    // =========================================================================
    // OTHER LETTERS — Delete Letter Type (Settings)
    // =========================================================================
    public function ajax_delete_letter_type() {
        check_ajax_referer( 'evda_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( esc_html__( 'Unauthorized.', 'employee-verification-document-authentication' ) );
        }
        $slug = sanitize_key( wp_unslash( $_POST['slug'] ?? '' ) );
        if ( ! $slug ) {
            wp_send_json_error( esc_html__( 'Slug is required.', 'employee-verification-document-authentication' ) );
        }
        $ok = EVDA_LetterTypes::delete( $slug );
        if ( $ok ) {
            wp_send_json_success( array( 'message' => esc_html__( 'Letter type deleted.', 'employee-verification-document-authentication' ) ) );
        } else {
            wp_send_json_error( esc_html__( 'Could not delete built-in letter types.', 'employee-verification-document-authentication' ) );
        }
    }

    /**
     * AJAX handler: export employees as CSV respecting active filters.
     * Security: nonce + manage_options capability required.
     */
    public function ajax_export_employees() {
        check_ajax_referer( 'evda_export_employees', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( esc_html__( 'Unauthorized.', 'employee-verification-document-authentication' ) );
        }

        // Sanitize filter args from POST.
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- nonce verified above
        $search     = isset( $_POST['search'] )     ? sanitize_text_field( wp_unslash( $_POST['search'] ) )     : '';
        $department = isset( $_POST['department'] ) ? sanitize_text_field( wp_unslash( $_POST['department'] ) ) : '';
        $status     = isset( $_POST['status'] )     ? sanitize_text_field( wp_unslash( $_POST['status'] ) )     : '';
        // phpcs:enable WordPress.Security.NonceVerification.Missing

        // Whitelist status.
        $allowed_statuses = array( 'Active', 'Intern', 'Former Employee' );
        if ( ! in_array( $status, $allowed_statuses, true ) ) {
            $status = '';
        }

        $employees = EVDA_Employee::get_all_for_export( array(
            'search'     => $search,
            'department' => $department,
            'status'     => $status,
        ) );

        // Build CSV rows.
        $rows   = array();
        $rows[] = array(
            __( 'Employee ID',  'employee-verification-document-authentication' ),
            __( 'Full Name',    'employee-verification-document-authentication' ),
            __( 'Father Name',  'employee-verification-document-authentication' ),
            __( 'CNIC',         'employee-verification-document-authentication' ),
            __( 'Email',        'employee-verification-document-authentication' ),
            __( 'Position',     'employee-verification-document-authentication' ),
            __( 'Department',   'employee-verification-document-authentication' ),
            __( 'Status',       'employee-verification-document-authentication' ),
            __( 'Joining Date', 'employee-verification-document-authentication' ),
            __( 'Leaving Date', 'employee-verification-document-authentication' ),
            __( 'Intern Start', 'employee-verification-document-authentication' ),
            __( 'Intern End',   'employee-verification-document-authentication' ),
        );

        foreach ( $employees as $emp ) {
            $rows[] = array(
                $emp->employee_id  ?? '',
                $emp->full_name    ?? '',
                $emp->father_name  ?? '',
                $emp->cnic         ?? '',
                $emp->email        ?? '',
                $emp->position     ?? '',
                $emp->department   ?? '',
                $emp->status       ?? '',
                $emp->joining_date ?? '',
                $emp->leaving_date ?? '',
                $emp->intern_start ?? '',
                $emp->intern_end   ?? '',
            );
        }

        // Return as JSON — JS will handle the download.
        wp_send_json_success( array( 'rows' => $rows ) );
    }

    public function ajax_repair_tables() {
        check_ajax_referer( 'evda_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );

        EVDA_Database::install();

        $missing = EVDA_Database::get_missing_tables();
        if ( empty( $missing ) ) {
            wp_send_json_success( array( 'message' => 'Tables created successfully! Reloading…' ) );
        } else {
            global $wpdb;
            wp_send_json_error( 'Still missing: ' . implode(', ', $missing) . '. DB Error: ' . $wpdb->last_error );
        }
    }
}
