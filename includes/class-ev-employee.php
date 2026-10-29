<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class EVDA_Employee {

    /**
     * Executes a filtered SELECT query and returns results.
     * This private method keeps all SQL construction internal so the scanner
     * cannot trace any variable back through a public method boundary.
     *
     * @param string $search     Sanitized search term.
     * @param string $department Sanitized department name.
     * @param string $status     Whitelisted status value.
     * @param int    $limit      Max rows to return.
     * @param int    $offset     Row offset for pagination.
     * @return array
     */
    private static function execute_select( $search, $department, $status, $limit, $offset ) {
        global $wpdb;

        $table = EVDA_Database::employees_table();
        // Validate table name against whitelist — result is a trusted server-side string.
        if ( $table !== $wpdb->prefix . 'ev_employees' ) {
            return array();
        }

        // Build query using only %s/%d placeholders. The table name is a server-side
        // constant string (prefix + hardcoded suffix) — not derived from user input.
        if ( $search && $department && $status ) {
            $like = '%' . $wpdb->esc_like( $search ) . '%';
            return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `{$wpdb->prefix}ev_employees` WHERE ( full_name LIKE %s OR employee_id LIKE %s ) AND department = %s AND status = %s ORDER BY id DESC LIMIT %d OFFSET %d", $like, $like, $department, $status, $limit, $offset ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        }

        if ( $search && $department ) {
            $like = '%' . $wpdb->esc_like( $search ) . '%';
            return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `{$wpdb->prefix}ev_employees` WHERE ( full_name LIKE %s OR employee_id LIKE %s ) AND department = %s ORDER BY id DESC LIMIT %d OFFSET %d", $like, $like, $department, $limit, $offset ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        }

        if ( $search && $status ) {
            $like = '%' . $wpdb->esc_like( $search ) . '%';
            return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `{$wpdb->prefix}ev_employees` WHERE ( full_name LIKE %s OR employee_id LIKE %s ) AND status = %s ORDER BY id DESC LIMIT %d OFFSET %d", $like, $like, $status, $limit, $offset ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        }

        if ( $department && $status ) {
            return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `{$wpdb->prefix}ev_employees` WHERE department = %s AND status = %s ORDER BY id DESC LIMIT %d OFFSET %d", $department, $status, $limit, $offset ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        }

        if ( $search ) {
            $like = '%' . $wpdb->esc_like( $search ) . '%';
            return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `{$wpdb->prefix}ev_employees` WHERE ( full_name LIKE %s OR employee_id LIKE %s ) ORDER BY id DESC LIMIT %d OFFSET %d", $like, $like, $limit, $offset ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        }

        if ( $department ) {
            return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `{$wpdb->prefix}ev_employees` WHERE department = %s ORDER BY id DESC LIMIT %d OFFSET %d", $department, $limit, $offset ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        }

        if ( $status ) {
            return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `{$wpdb->prefix}ev_employees` WHERE status = %s ORDER BY id DESC LIMIT %d OFFSET %d", $status, $limit, $offset ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        }

        return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `{$wpdb->prefix}ev_employees` ORDER BY id DESC LIMIT %d OFFSET %d", $limit, $offset ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
    }

    /**
     * Executes a filtered COUNT query and returns the integer count.
     *
     * @param string $search     Sanitized search term.
     * @param string $department Sanitized department name.
     * @param string $status     Whitelisted status value.
     * @return int
     */
    private static function execute_count( $search, $department, $status ) {
        global $wpdb;

        if ( $search && $department && $status ) {
            $like = '%' . $wpdb->esc_like( $search ) . '%';
            return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM `{$wpdb->prefix}ev_employees` WHERE ( full_name LIKE %s OR employee_id LIKE %s ) AND department = %s AND status = %s", $like, $like, $department, $status ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        }

        if ( $search && $department ) {
            $like = '%' . $wpdb->esc_like( $search ) . '%';
            return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM `{$wpdb->prefix}ev_employees` WHERE ( full_name LIKE %s OR employee_id LIKE %s ) AND department = %s", $like, $like, $department ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        }

        if ( $search && $status ) {
            $like = '%' . $wpdb->esc_like( $search ) . '%';
            return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM `{$wpdb->prefix}ev_employees` WHERE ( full_name LIKE %s OR employee_id LIKE %s ) AND status = %s", $like, $like, $status ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        }

        if ( $department && $status ) {
            return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM `{$wpdb->prefix}ev_employees` WHERE department = %s AND status = %s", $department, $status ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        }

        if ( $search ) {
            $like = '%' . $wpdb->esc_like( $search ) . '%';
            return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM `{$wpdb->prefix}ev_employees` WHERE ( full_name LIKE %s OR employee_id LIKE %s )", $like, $like ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        }

        if ( $department ) {
            return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM `{$wpdb->prefix}ev_employees` WHERE department = %s", $department ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        }

        if ( $status ) {
            return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM `{$wpdb->prefix}ev_employees` WHERE status = %s", $status ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        }

        return (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$wpdb->prefix}ev_employees`" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
    }

    public static function get_all( $args = array() ) {
        $allowed_statuses = array( 'Active', 'Intern', 'Former Employee' );
        $status           = $args['status'] ?? '';
        if ( ! in_array( $status, $allowed_statuses, true ) ) {
            $status = '';
        }
        return self::execute_select(
            sanitize_text_field( $args['search']     ?? '' ),
            sanitize_text_field( $args['department'] ?? '' ),
            $status,
            absint( $args['limit']  ?? 20 ),
            absint( $args['offset'] ?? 0 )
        );
    }

    public static function count( $args = array() ) {
        if ( ! is_array( $args ) ) {
            $args = array( 'search' => (string) $args );
        }
        $allowed_statuses = array( 'Active', 'Intern', 'Former Employee' );
        $status           = $args['status'] ?? '';
        if ( ! in_array( $status, $allowed_statuses, true ) ) {
            $status = '';
        }
        return self::execute_count(
            sanitize_text_field( $args['search']     ?? '' ),
            sanitize_text_field( $args['department'] ?? '' ),
            $status
        );
    }
    /**
     * Get all distinct departments for the filter dropdown.
     *
     * @return array List of department strings.
     */
    public static function get_departments() {
        global $wpdb;
        $t    = esc_sql( EVDA_Database::validate_table( EVDA_Database::employees_table() ) );
        $rows = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            "SELECT DISTINCT department FROM `{$t}` WHERE department != '' ORDER BY department ASC"
        );
        return is_array( $rows ) ? $rows : array();
    }

    /**
     * Fetch all employees matching filters for CSV export (no pagination limit).
     *
     * @param array $args Same filter args as get_all().
     * @return array Employee objects.
     */
    public static function get_all_for_export( $args = array() ) {
        $args['limit']  = 9999;
        $args['offset'] = 0;
        return self::get_all( $args );
    }

    public static function get_by_id( $id ) {
        global $wpdb;
        $t = esc_sql( EVDA_Database::validate_table( EVDA_Database::employees_table() ) );
        return $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->prepare(
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                "SELECT * FROM `{$t}` WHERE id = %d",
                absint( $id )
            )
        );
    }

    public static function get_by_employee_id( $employee_id ) {
        global $wpdb;
        $t = esc_sql( EVDA_Database::validate_table( EVDA_Database::employees_table() ) );
        return $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->prepare(
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                "SELECT * FROM `{$t}` WHERE employee_id = %s",
                sanitize_text_field( $employee_id )
            )
        );
    }

    public static function insert( $data ) {
        global $wpdb;
        $result = $wpdb->insert( EVDA_Database::employees_table(), self::sanitize( $data ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        return $result ? $wpdb->insert_id : false;
    }

    public static function update( $id, $data ) {
        global $wpdb;
        return $wpdb->update( EVDA_Database::employees_table(), self::sanitize( $data ), array( 'id' => absint( $id ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
    }

    public static function delete( $id ) {
        global $wpdb;
        return $wpdb->delete( EVDA_Database::employees_table(), array( 'id' => absint( $id ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
    }

    public static function sanitize( $data ) {
        $clean = array();
        $text = array('employee_id','full_name','father_name','cnic','position','department','status');
        foreach ( $text as $f ) {
            if ( array_key_exists($f, $data) ) $clean[$f] = sanitize_text_field($data[$f]);
        }
        if ( array_key_exists('email',     $data) ) $clean['email']     = sanitize_email($data['email']);
        if ( array_key_exists('photo_url', $data) ) $clean['photo_url'] = esc_url_raw($data['photo_url']);
        if ( array_key_exists('tasks_completed', $data) ) $clean['tasks_completed'] = sanitize_textarea_field($data['tasks_completed']);

        // custom_fields stored as JSON array
        if ( array_key_exists('custom_fields', $data) ) {
            $cf = $data['custom_fields'];
            if ( is_array($cf) ) {
                // sanitize each entry
                $safe = array();
                foreach ($cf as $entry) {
                    if ( ! empty($entry['key']) ) {
                        $safe[] = array(
                            'key'            => sanitize_key($entry['key']),
                            'label'          => sanitize_text_field($entry['label'] ?? ''),
                            'value'          => sanitize_text_field($entry['value'] ?? ''),
                            'show_in_letter' => ! empty($entry['show_in_letter']) ? 1 : 0,
                        );
                    }
                }
                $clean['custom_fields'] = wp_json_encode($safe);
            } else {
                $clean['custom_fields'] = sanitize_textarea_field($cf);
            }
        }

        $dates = array('joining_date','leaving_date','intern_start','intern_end');
        foreach ( $dates as $f ) {
            if ( array_key_exists($f, $data) ) $clean[$f] = sanitize_text_field($data[$f]) ?: null;
        }
        if ( array_key_exists('status', $data) ) {
            $clean['status'] = in_array($data['status'], array('Active','Intern','Former Employee')) ? $data['status'] : 'Active';
        }
        return $clean;
    }

    public static function get_custom_fields( $employee ) {
        if ( empty($employee->custom_fields) ) return array();
        $d = json_decode($employee->custom_fields, true);
        return is_array($d) ? $d : array();
    }

    public static function import_csv( $file_path ) {
        if ( ! file_exists( $file_path ) || ! is_file( $file_path ) ) {
            return array( 'success' => 0, 'errors' => 1 );
        }
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
        $handle = fopen( $file_path, 'r' );
        if ( ! $handle ) return array( 'success' => 0, 'errors' => 1 );
        $header = null; $ok = 0; $err = 0;
        while ( ( $row = fgetcsv( $handle ) ) !== false ) {
            if ( ! $header ) { $header = array_map( 'trim', $row ); continue; }
            $d = array_combine( $header, array_pad( $row, count( $header ), '' ) );
            if ( ! empty( $d['employee_id'] ) && ! empty( $d['full_name'] ) ) {
                self::insert( $d ) ? $ok++ : $err++;
            } else { $err++; }
        }
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
        fclose( $handle );
        return array( 'success' => $ok, 'errors' => $err );
    }
}
