<?php
/**
 * Plugin Name:       Employee Verification & Document Authentication
 * Plugin URI:        https://skfreelancers.com/
 * Description:       Manage employees, issue verifiable internship and experience letters with QR codes, and let visitors verify staff and documents on your website using simple shortcodes.
 * Version:           1.4.1
 * Requires at least: 5.8
 * Tested up to:      7.1
 * Requires PHP:      7.4
 * Author:            SKFreelancers
 * Author URI:        https://skfreelancers.com
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       employee-verification-document-authentication
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'EVDA_VERSION',     '1.4.1' );
define( 'EVDA_PLUGIN_DIR',  plugin_dir_path( __FILE__ ) );
define( 'EVDA_PLUGIN_URL',  plugin_dir_url( __FILE__ ) );
define( 'EVDA_PLUGIN_FILE', __FILE__ );

require_once EVDA_PLUGIN_DIR . 'includes/class-ev-database.php';
require_once EVDA_PLUGIN_DIR . 'includes/class-ev-employee.php';
require_once EVDA_PLUGIN_DIR . 'includes/class-ev-document.php';
require_once EVDA_PLUGIN_DIR . 'includes/class-ev-pdf.php';
require_once EVDA_PLUGIN_DIR . 'includes/class-ev-qrcode.php';
require_once EVDA_PLUGIN_DIR . 'includes/class-ev-shortcodes.php';
require_once EVDA_PLUGIN_DIR . 'includes/class-ev-email.php';
require_once EVDA_PLUGIN_DIR . 'includes/class-ev-letter-types.php';
require_once EVDA_PLUGIN_DIR . 'admin/class-ev-admin.php';
require_once EVDA_PLUGIN_DIR . 'admin/class-ev-import.php';

// Activation: only run DB install.
register_activation_hook( __FILE__, array( 'EVDA_Database', 'install' ) );

register_deactivation_hook( __FILE__, function() {} );

// ============================================================================
// Bootstrap on plugins_loaded
// ============================================================================
add_action( 'plugins_loaded', 'evda_init' );
function evda_init() {
    EVDA_Database::migrate_options();

    $stored_ver = get_option( 'evda_db_version', get_option( 'ev_db_version', '0' ) );
    $missing    = EVDA_Database::get_missing_tables();

    if ( ! empty( $missing ) || version_compare( $stored_ver, EVDA_VERSION, '<' ) ) {
        EVDA_Database::install();
    }

    EVDA_Import::register_hooks();
    new EVDA_Admin();
    new EVDA_Shortcodes();
    EVDA_PDF::register_endpoint();
}

// Seed Other Letter types lazily on admin_init — never fires during activation.
add_action( 'admin_init', 'evda_seed_letter_types' );
function evda_seed_letter_types() {
    if ( false === get_option( EVDA_LetterTypes::OPTION_KEY, false ) ) {
        EVDA_LetterTypes::seed_defaults();
    }
}

// ============================================================================
// AJAX endpoints
// ============================================================================

add_action( 'wp_ajax_evda_patch_old_letters', 'evda_patch_old_letters' );
function evda_patch_old_letters() {
    check_ajax_referer( 'evda_admin_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );

    global $wpdb;
    $table = esc_sql( EVDA_Database::validate_table( EVDA_Database::documents_table() ) );

    $docs = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        "SELECT id, pdf_path, verification_id FROM `{$table}` WHERE pdf_path != '' AND pdf_path IS NOT NULL" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    );
    $cleaned = 0;

    foreach ( $docs as $doc ) {
        if ( strpos( $doc->pdf_path, 'dynamic:' ) === 0 ) continue;
        if ( file_exists( $doc->pdf_path ) ) {
            wp_delete_file( $doc->pdf_path );
        }
        $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $table,
            array( 'pdf_path' => 'dynamic:' . $doc->verification_id ),
            array( 'id' => $doc->id ),
            array( '%s' ),
            array( '%d' )
        );
        $cleaned++;
    }

    $msg = $cleaned > 0
        ? $cleaned . ' old static letter file(s) converted to live rendering.'
        : 'All letters are already using live rendering. No changes needed.';

    wp_send_json_success( array( 'patched' => $cleaned, 'message' => $msg ) );
}

add_action( 'wp_ajax_evda_preview_letter', 'evda_preview_letter' );
function evda_preview_letter() {
    check_ajax_referer( 'evda_admin_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );

    $type = sanitize_text_field( wp_unslash( $_GET['type'] ?? 'experience' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

    $emp = (object) array(
        'full_name'       => 'John Smith',
        'employee_id'     => 'EMP-001',
        'father_name'     => 'Robert Smith',
        'cnic'            => '12345-6789012-3',
        'email'           => 'john@example.com',
        'position'        => 'Web Developer',
        'department'      => 'IT & Programming',
        'joining_date'    => '2023-01-15',
        'leaving_date'    => '2024-12-31',
        'intern_start'    => '2023-06-01',
        'intern_end'      => '2023-08-31',
        'status'          => $type === 'internship' ? 'Intern' : 'Former Employee',
        'photo_url'       => '',
        'tasks_completed' => 'Virtual Assistant, Data Entry, Email Handling',
        'custom_fields'   => '',
    );
    $doc_data = array(
        'document_type' => $type,
        'issue_date'    => current_time( 'Y-m-d' ),
        'leaving_date'  => '2024-12-31',
    );
    $vid = 'PREVIEW-' . strtoupper( substr( md5( time() ), 0, 8 ) );
    EVDA_PDF::output_letter( EVDA_PDF::build_letter( $emp, $doc_data, $vid ) );
}

add_action( 'wp_ajax_evda_dl_import_template', 'evda_download_import_template' );
function evda_download_import_template() {
    check_ajax_referer( 'evda_admin_nonce', 'nonce' );
    header( 'Content-Type: text/csv' );
    header( 'Content-Disposition: attachment; filename="ev-import-template.csv"' );
    echo "employee_id,document_type,verification_id,issue_date,leaving_date,notes\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    echo "EMP-001,experience,EXP-2024-001,2024-01-15,2024-12-31,Sample\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    exit;
}

add_action( 'wp_ajax_evda_reseed_lt_templates', 'evda_reseed_lt_templates' );
function evda_reseed_lt_templates() {
    check_ajax_referer( 'evda_admin_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'Unauthorized' );
    }
    EVDA_LetterTypes::force_reseed_templates();
    wp_send_json_success( array( 'message' => 'All letter templates reset to professional defaults.' ) );
}

add_action( 'wp_ajax_evda_get_lt_data', 'evda_get_lt_data' );
function evda_get_lt_data() {
    check_ajax_referer( 'evda_admin_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'Unauthorized' );
    }
    $slug = sanitize_key( wp_unslash( $_POST['slug'] ?? '' ) );
    wp_send_json_success( array(
        'template'      => get_option( 'evda_letter_template_' . $slug, '' ),
        'email_subject' => get_option( 'evda_email_subject_'   . $slug, '' ),
        'email_body'    => get_option( 'evda_email_body_'      . $slug, '' ),
    ) );
}

add_action( 'wp_ajax_evda_qr_image',        'evda_serve_qr_image' );
add_action( 'wp_ajax_nopriv_evda_qr_image', 'evda_serve_qr_image' );
function evda_serve_qr_image() {
    $raw_data  = isset( $_GET['data'] )  ? sanitize_text_field( wp_unslash( $_GET['data'] ) )  : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    $raw_token = isset( $_GET['token'] ) ? sanitize_text_field( wp_unslash( $_GET['token'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

    if ( ! $raw_data || ! $raw_token ) {
        wp_die( 'Missing required parameters.', '', array( 'response' => 400 ) );
    }
    if ( ! hash_equals( wp_hash( $raw_data ), $raw_token ) ) {
        wp_die( 'Invalid security token.', '', array( 'response' => 403 ) );
    }

    $data      = esc_url_raw( $raw_data );
    $site_host = wp_parse_url( home_url(), PHP_URL_HOST );
    $data_host = wp_parse_url( $data, PHP_URL_HOST );

    if ( empty( $data_host ) || strtolower( $data_host ) !== strtolower( $site_host ) ) {
        wp_die( 'Invalid request target.', '', array( 'response' => 403 ) );
    }

    $apis = array(
        'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' . urlencode( $data ),
        'https://chart.googleapis.com/chart?chs=200x200&cht=qr&chl=' . urlencode( $data ),
    );
    foreach ( $apis as $api_url ) {
        $resp = wp_remote_get( $api_url, array( 'timeout' => 5 ) );
        if ( ! is_wp_error( $resp ) && wp_remote_retrieve_response_code( $resp ) === 200 ) {
            header( 'Content-Type: image/png' );
            header( 'Cache-Control: public, max-age=86400' );
            echo wp_remote_retrieve_body( $resp ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            exit;
        }
    }

    if ( extension_loaded( 'gd' ) ) {
        $size  = 200;
        $img   = imagecreatetruecolor( $size, $size );
        $white = imagecolorallocate( $img, 255, 255, 255 );
        $black = imagecolorallocate( $img, 0, 0, 0 );
        $blue  = imagecolorallocate( $img, 0, 51, 102 );
        imagefill( $img, 0, 0, $white );
        imagerectangle( $img, 0, 0, $size - 1, $size - 1, $black );
        foreach ( array( array( 10, 10 ), array( 150, 10 ), array( 10, 150 ) ) as $pos ) {
            imagefilledrectangle( $img, $pos[0], $pos[1], $pos[0] + 40, $pos[1] + 40, $black );
            imagefilledrectangle( $img, $pos[0] + 4, $pos[1] + 4, $pos[0] + 36, $pos[1] + 36, $white );
            imagefilledrectangle( $img, $pos[0] + 10, $pos[1] + 10, $pos[0] + 30, $pos[1] + 30, $black );
        }
        $hash = md5( $data );
        for ( $i = 0; $i < 32; $i++ ) {
            $v = hexdec( $hash[ $i ] );
            $x = 70 + ( $i % 8 ) * 10;
            $y = 70 + (int) ( $i / 8 ) * 10;
            if ( $v > 8 ) imagefilledrectangle( $img, $x, $y, $x + 8, $y + 8, $black );
        }
        imagestring( $img, 1, 55, 185, 'Verify: ' . substr( $data, -20 ), $blue );
        header( 'Content-Type: image/png' );
        header( 'Cache-Control: public, max-age=86400' );
        imagepng( $img );
        imagedestroy( $img );
        exit;
    }

    header( 'Content-Type: image/png' );
    echo base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    exit;
}
