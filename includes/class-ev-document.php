<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class EVDA_Document {

    public static function generate_verification_id() {
        return strtoupper( bin2hex( random_bytes( 8 ) ) );
    }

    public static function get_by_verification_id( $vid ) {
        global $wpdb;
        $table = esc_sql( EVDA_Database::validate_table( EVDA_Database::documents_table() ) );
        return $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->prepare(
                "SELECT * FROM `{$table}` WHERE verification_id = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                sanitize_text_field( $vid )
            )
        );
    }

    /**
     * Returns the ONE document of a given type for an employee, or null.
     */
    public static function get_existing( $employee_id, $type ) {
        global $wpdb;
        $table = esc_sql( EVDA_Database::validate_table( EVDA_Database::documents_table() ) );
        return $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->prepare(
                "SELECT * FROM `{$table}` WHERE employee_id = %s AND document_type = %s LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                sanitize_text_field( $employee_id ),
                sanitize_text_field( $type )
            )
        );
    }

    /**
     * Returns both documents (internship + experience) for an employee as associative array.
     * Keys: 'internship', 'experience'  — value is row object or null.
     */
    public static function get_both( $employee_id ) {
        global $wpdb;
        $table = esc_sql( EVDA_Database::validate_table( EVDA_Database::documents_table() ) );
        $rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->prepare(
                "SELECT * FROM `{$table}` WHERE employee_id = %s ORDER BY created_at DESC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                sanitize_text_field( $employee_id )
            )
        );
        $result = array( 'internship' => null, 'experience' => null );
        foreach ( $rows as $row ) {
            if ( isset( $result[ $row->document_type ] ) && $result[ $row->document_type ] === null ) {
                $result[ $row->document_type ] = $row;
            }
        }
        return $result;
    }

    public static function get_all( $limit = 20, $offset = 0 ) {
        global $wpdb;
        $table     = esc_sql( EVDA_Database::validate_table( EVDA_Database::documents_table() ) );
        $emp_table = esc_sql( EVDA_Database::validate_table( EVDA_Database::employees_table() ) );
        return $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->prepare(
                "SELECT d.*, e.full_name, e.position, e.department FROM `{$table}` d LEFT JOIN `{$emp_table}` e ON d.employee_id = e.employee_id ORDER BY d.created_at DESC LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $limit, $offset
            )
        );
    }

    /**
     * Create a document — enforces 1 per type per employee.
     * Returns array: ['success'=>bool, 'vid'=>string|null, 'error'=>string|null]
     */
    public static function create( $employee_id, $type, $extra = array() ) {
        global $wpdb;
        $table = esc_sql( EVDA_Database::validate_table( EVDA_Database::documents_table() ) );

        $type        = sanitize_key( $type );
        $employee_id = sanitize_text_field( $employee_id );

        // Built-in types stay as-is. Other letter slugs are accepted if they exist
        // in the EVDA_LetterTypes registry or if they are a known custom slug.
        $built_in = array( 'internship', 'experience' );

        // --- Enforce one-per-type rule for built-in types and types with allow_multiple=false ---
        $enforce_one = in_array( $type, $built_in, true );
        if ( ! $enforce_one ) {
            $type_def = EVDA_LetterTypes::get_by_slug( $type );
            if ( $type_def && empty( $type_def['allow_multiple'] ) ) {
                $enforce_one = true;
            }
        }

        if ( $enforce_one ) {
            $existing = self::get_existing( $employee_id, $type );
            if ( $existing ) {
                if ( $type === 'internship' ) {
                    $label = 'Internship Completion Letter';
                } elseif ( $type === 'experience' ) {
                    $label = 'Experience Letter';
                } else {
                    $type_def = EVDA_LetterTypes::get_by_slug( $type );
                    $label    = $type_def ? esc_html( $type_def['name'] ) : ucwords( str_replace( '_', ' ', $type ) );
                }
                return array(
                    'success' => false,
                    'vid'     => null,
                    'error'   => "A {$label} has already been issued for this employee. Please delete the existing one first.",
                );
            }
        }

        $vid  = self::generate_verification_id();
        $data = array(
            'verification_id' => $vid,
            'employee_id'     => $employee_id,
            'document_type'   => $type,
            'issue_date'      => current_time( 'Y-m-d' ),
            'is_valid'        => 1,
        );
        if ( ! empty( $extra['leaving_date'] ) ) {
            $data['leaving_date'] = sanitize_text_field( $extra['leaving_date'] );
        }
        // Store extra_data (dynamic fields for other letters) as JSON in notes.
        if ( ! empty( $extra['notes'] ) ) {
            $data['notes'] = sanitize_textarea_field( $extra['notes'] );
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $result = $wpdb->insert( EVDA_Database::documents_table(), $data ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        if ( ! $result ) {
            return array( 'success' => false, 'vid' => null, 'error' => 'Database insert failed: ' . $wpdb->last_error );
        }

        $doc_id = $wpdb->insert_id;

        // Generate the letter file
        $employee = EVDA_Employee::get_by_employee_id( $employee_id );
        if ( $employee ) {
            $pdf_path = EVDA_PDF::generate( $employee, $data, $vid );
            if ( $pdf_path ) {
                $wpdb->update( EVDA_Database::documents_table(), array( 'pdf_path' => $pdf_path ), array( 'id' => $doc_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            }
        }

        return array( 'success' => true, 'vid' => $vid, 'error' => null );
    }

    public static function revoke( $vid ) {
        global $wpdb;
        $table = esc_sql( EVDA_Database::validate_table( EVDA_Database::documents_table() ) );
        return $wpdb->update( EVDA_Database::documents_table(), array( 'is_valid' => 0 ), array( 'verification_id' => sanitize_text_field( $vid ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
    }

    public static function delete( $id ) {
        global $wpdb;
        $table = esc_sql( EVDA_Database::validate_table( EVDA_Database::documents_table() ) );
        // Also delete the file
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
        $doc = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->prepare(
                "SELECT pdf_path FROM `{$table}` WHERE id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                absint( $id )
            )
        );
        if ( $doc && $doc->pdf_path && file_exists( $doc->pdf_path ) ) {
            wp_delete_file( $doc->pdf_path );
        }
        return $wpdb->delete( EVDA_Database::documents_table(), array( 'id' => absint( $id ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
    }

    public static function get_verification_url( $vid ) {
        $page_id = get_option( 'evda_document_verification_page' );
        if ( $page_id ) {
            return add_query_arg( 'id', $vid, get_permalink( $page_id ) );
        }
        return home_url( '/?evda_verify_doc=' . urlencode( $vid ) );
    }
}
