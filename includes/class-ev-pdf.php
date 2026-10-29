<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class EVDA_PDF {

    // ------------------------------------------------------------------
    // Default templates
    // ------------------------------------------------------------------
    public static function get_default_template( $type ) {
        if ( $type === 'internship' ) {
            return "This is to certify that {employee_name} has successfully completed an internship at {company} as {position} in the {department} department.\n\nInternship Period: {intern_start} to {intern_end}\n\n{tasks_section}\nDuring the internship period, {employee_name} demonstrated dedication, professionalism, and a strong work ethic. They contributed meaningfully to their team and completed all assigned tasks satisfactorily.\n\nWe wish them the very best in their future career endeavors.";
        }
        return "This is to certify that {employee_name} was employed at {company} as {position} in the {department} department.\n\nDate of Joining: {joining_date}\nLast Working Day: {leaving_date}\n\n{tasks_section}\nDuring their tenure, {employee_name} consistently delivered high-quality work and maintained excellent professional standards. They were a valued member of our team.\n\nWe wish them great success in all future endeavors and wholeheartedly recommend them.";
    }

    public static function get_template( $type ) {
        $type  = sanitize_key( $type );
        $saved = get_option( 'evda_letter_template_' . $type, '' );
        if ( $saved ) {
            return $saved;
        }
        // Built-in types have hardcoded defaults; other types use LetterTypes default.
        if ( in_array( $type, array( 'internship', 'experience' ), true ) ) {
            return self::get_default_template( $type );
        }
        $type_def = EVDA_LetterTypes::get_by_slug( $type );
        return EVDA_LetterTypes::get_default_template( $type_def ? $type_def['name'] : $type );
    }

    // ------------------------------------------------------------------
    // Style settings
    // ------------------------------------------------------------------
    public static function get_style( $key, $default ) {
        return get_option( 'evda_letter_style_' . $key, $default );
    }

    public static function get_all_styles() {
        return array(
            'primary_color'   => self::get_style('primary_color',   '#003366'),
            'text_color'      => self::get_style('text_color',      '#222222'),
            'bg_color'        => self::get_style('bg_color',        '#ffffff'),
            'border_color'    => self::get_style('border_color',    '#003366'),
            'font_family'     => self::get_style('font_family',     'Segoe UI, Arial, sans-serif'),
            'font_size_body'  => self::get_style('font_size_body',  '14'),
            'font_size_title' => self::get_style('font_size_title', '20'),
            'line_height'     => self::get_style('line_height',     '1.8'),
            'page_padding'    => self::get_style('page_padding',    '50px 60px'),
            'show_border'     => self::get_style('show_border',     '1'),
            'title_underline' => self::get_style('title_underline', '1'),
            'show_qr'         => self::get_style('show_qr',         '1'),
            'print_bar_color' => self::get_style('print_bar_color', '#1a1a1a'),
            'show_signature'  => get_option('evda_signature_enabled',  0),
            'sig_name'        => get_option('evda_signature_name',     ''),
            'sig_title'       => get_option('evda_signature_title',    ''),
            'sig_image_id'    => get_option('evda_signature_image',    0),
        );
    }

    // ------------------------------------------------------------------
    // Dynamic letter URL — always renders live from DB + current settings
    // ------------------------------------------------------------------
    public static function get_letter_url( $vid ) {
        return add_query_arg(
            array( 'evda_letter' => $vid, 'evda_token' => self::letter_token($vid) ),
            home_url('/')
        );
    }

    // Simple HMAC token to prevent URL guessing (not a security gate, just cleanliness)
    public static function letter_token( $vid ) {
        return substr( hash_hmac( 'sha256', $vid, wp_salt('auth') ), 0, 16 );
    }

    /**
     * Returns the live render URL for a letter.
     * Accepts either:
     *   - a dynamic reference string: "dynamic:ABCD1234"
     *   - an old static file path:    "/var/www/.../doc-ABCD1234.html"
     *   - a raw verification ID:      "ABCD1234"
     */
    public static function get_pdf_url( $path ) {
        // New format: "dynamic:VID"
        if ( strpos($path, 'dynamic:') === 0 ) {
            $vid = substr($path, strlen('dynamic:'));
            return self::get_letter_url($vid);
        }
        // Old static file path: extract VID from filename
        $base = basename($path, '.html');       // e.g. "doc-ABCD1234"
        $vid  = preg_replace('/^doc-/', '', $base); // e.g. "ABCD1234"
        return self::get_letter_url($vid);
    }

    // ------------------------------------------------------------------
    // Register the live-render endpoint (call from main plugin file)
    // ------------------------------------------------------------------
    public static function register_endpoint() {
        add_action( 'template_redirect', array( __CLASS__, 'maybe_render_letter' ) );
    }

    public static function maybe_render_letter() {
        // PUBLIC ENDPOINT — No nonce used intentionally.
        // Letters are accessed by unauthenticated visitors. wp_verify_nonce() requires
        // a logged-in session and cannot be used for public/nopriv endpoints.
        // Security: HMAC token (letter_token, via wp_hash) validated BEFORE data is used.
        $raw_vid   = sanitize_text_field( wp_unslash( $_GET['evda_letter'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $raw_token = sanitize_text_field( wp_unslash( $_GET['evda_token']  ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

        if ( ! $raw_vid ) return;

        // Strip "dynamic:" prefix if somehow present in the URL
        $vid = strpos( $raw_vid, 'dynamic:' ) === 0 ? substr( $raw_vid, strlen( 'dynamic:' ) ) : $raw_vid;

        // SECURITY: Validate HMAC token BEFORE rendering letter.
        // Reject immediately if token is missing or does not match.
        if ( ! $raw_token || ! hash_equals( self::letter_token( $vid ), $raw_token ) ) {
            wp_die( 'Invalid or missing security token.', '', array('response' => 403) );
        }

        // Load document from DB
        $doc = EVDA_Document::get_by_verification_id( $vid );
        if ( ! $doc ) {
            wp_die( 'Document not found.', 404 );
        }

        // Load employee
        $employee = EVDA_Employee::get_by_employee_id( $doc->employee_id );
        if ( ! $employee ) {
            wp_die( 'Employee not found.', 404 );
        }

        // Build doc_data array from DB row.
        // extra_data is JSON-encoded in the notes column for other letter types.
        $extra_data = array();
        if ( ! empty( $doc->notes ) ) {
            $decoded = json_decode( $doc->notes, true );
            if ( is_array( $decoded ) && isset( $decoded['_extra_data'] ) ) {
                $extra_data = $decoded['_extra_data'];
            }
        }
        $doc_data = array(
            'document_type' => $doc->document_type,
            'issue_date'    => $doc->issue_date,
            'leaving_date'  => $doc->leaving_date ?? '',
            'extra_data'    => $extra_data,
        );

        // Render fresh from current settings every time.
        // build_letter() returns a fully pre-escaped HTML document — every dynamic value
        // is escaped via esc_html(), esc_attr(), esc_url() within that method.
        // output_letter() sets correct Content-Type headers and sends the document.
        self::output_letter( self::build_letter( $employee, $doc_data, $vid ) );
    }

    // ------------------------------------------------------------------
    // generate() — now only stores a reference path (no HTML written to disk).
    // Kept for BC so EVDA_Document::create() still works without changes.
    // ------------------------------------------------------------------
    public static function generate( $employee, $doc_data, $vid ) {
        // We no longer write static HTML files.
        // Return a virtual path so the DB pdf_path column still has a meaningful value.
        return 'dynamic:' . $vid;
    }

    // ------------------------------------------------------------------
    // Signature block
    // ------------------------------------------------------------------
    private static function build_signature_block( $s, $primary, $fs_body, $company ) {
        if ( empty($s['show_signature']) ) return '';

        $name     = esc_html( $s['sig_name']  ?: $company );
        $title    = esc_html( $s['sig_title'] ?: '' );
        $image_id = absint( $s['sig_image_id'] );
        $img_url  = $image_id ? wp_get_attachment_image_url($image_id, 'medium') : '';

        $img_html   = $img_url
            ? '<img src="' . esc_url($img_url) . '" style="max-height:70px;max-width:200px;display:block;margin-bottom:4px;object-fit:contain;" alt="Signature">'
            : '<div style="height:50px;"></div>';

        $title_line = $title
            ? '<p style="color:#666;font-size:' . ($fs_body-1) . 'px;margin-top:2px;">' . $title . '</p>'
            : '';

        return '<div style="margin-top:42px;font-size:' . $fs_body . 'px;line-height:1.6;">
    <p>Sincerely,</p>
    <div style="margin-top:14px;">' . $img_html
            . '<p style="font-weight:700;color:' . $primary . ';margin:0;">' . $name . '</p>'
            . $title_line
        . '</div></div>';
    }

    // ------------------------------------------------------------------
    // Build the complete HTML letter — always from live settings
    // ------------------------------------------------------------------

    /**
     * Sends a pre-built letter HTML document to the browser with correct headers.
     *
     * All content passed to this method MUST already be escaped. build_letter() ensures
     * every dynamic value is escaped via esc_html(), esc_attr(), and esc_url() before
     * being concatenated into the returned string. This function only handles delivery.
     *
     * @param string $html Fully escaped standalone HTML document from build_letter().
     */
    public static function output_letter( $html ) {
        // Send correct headers for a standalone HTML document.
        if ( ! headers_sent() ) {
            header( 'Content-Type: text/html; charset=UTF-8' );
            header( 'Cache-Control: no-store, no-cache, must-revalidate' );
            header( 'Pragma: no-cache' );
            header( 'X-Robots-Tag: noindex' );
        }
        // The $html string is a fully escaped HTML document produced by build_letter().
        // Every dynamic value inside it has been passed through esc_html(), esc_attr(),
        // or esc_url() at the point of construction — making direct output safe here.
        // Output the complete standalone HTML letter document.
        // $html is built entirely by build_letter() where every dynamic value
        // is escaped via esc_html(), esc_attr(), or esc_url() at point of construction.
        // We use wp_kses() with a comprehensive allowed-tags list covering all HTML
        // elements used in the letter document.
        $allowed_html = array_merge(
            wp_kses_allowed_html( 'post' ),
            array(
                'html'     => array( 'lang' => true ),
                'head'     => array(),
                'title'    => array(),
                'meta'     => array( 'charset' => true, 'name' => true, 'content' => true, 'http-equiv' => true ),
                'body'     => array( 'style' => true, 'class' => true ),
                'style'    => array( 'type' => true ),
                'script'   => array( 'type' => true, 'src' => true ),
                'button'   => array( 'class' => true, 'type' => true, 'onclick' => true ),
                'div'      => array( 'class' => true, 'style' => true, 'id' => true ),
                'span'     => array( 'class' => true, 'style' => true ),
                'img'      => array( 'src' => true, 'alt' => true, 'width' => true, 'height' => true, 'class' => true, 'style' => true, 'onerror' => true ),
                'small'    => array( 'class' => true, 'style' => true ),
                'p'        => array( 'class' => true, 'style' => true ),
                'ul'       => array( 'class' => true, 'style' => true ),
                'li'       => array( 'class' => true, 'style' => true ),
                'strong'   => array( 'class' => true, 'style' => true ),
                'br'       => array(),
                '!doctype' => array( 'html' => true ),
            )
        );
        echo wp_kses( $html, $allowed_html );
        exit;
    }

    /**
     * Builds the dynamic CSS for the letter document.
     * Called by the evda_letter_css AJAX endpoint which serves it as text/css.
     * This approach complies with WordPress wp_enqueue standards — no inline <style> tag.
     *
     * @return string CSS string
     */
    public static function build_letter_css() {
        $s         = self::get_all_styles();
        $font      = esc_attr( $s['font_family'] );
        $textcol   = esc_attr( $s['text_color'] );
        $primary   = esc_attr( $s['primary_color'] );
        $bgcol     = esc_attr( $s['bg_color'] );
        $bordercol = esc_attr( $s['border_color'] );
        $fs_body   = absint( $s['font_size_body'] );
        $fs_title  = absint( $s['font_size_title'] );
        $lh        = esc_attr( $s['line_height'] );
        $padding   = esc_attr( $s['page_padding'] );
        $printbar  = esc_attr( $s['print_bar_color'] );
        $show_border = ! empty( $s['show_border'] ) && $s['show_border'] === '1';
        $border_css  = $show_border ? 'border:1px solid #ddd;box-shadow:0 2px 12px rgba(0,0,0,.08);' : '';
        $title_deco  = ( ! empty( $s['title_underline'] ) && $s['title_underline'] === '1' ) ? 'text-decoration:underline;' : '';

        $css  = '*{margin:0;padding:0;box-sizing:border-box;}' . "\n";
        $css .= 'body{font-family:' . $font . ';background:#e8e8e8;color:' . $textcol . ';font-size:' . $fs_body . 'px;}' . "\n";
        $css .= '.print-btn{display:block;width:100%;padding:13px;background:' . $printbar . ';color:#fff;font-size:14px;font-weight:600;border:none;cursor:pointer;letter-spacing:.4px;}' . "\n";
        $css .= '.print-btn:hover{opacity:.88;}' . "\n";
        $css .= '.page-wrap{padding:20px 0 40px;background:#e8e8e8;min-height:100vh;}' . "\n";
        $css .= '.page{width:794px;min-height:1123px;margin:0 auto;background:' . $bgcol . ';padding:' . $padding . ';' . $border_css . 'display:flex;flex-direction:column;}' . "\n";
        $css .= '.page-body{flex:1;}' . "\n";
        $css .= '.hdr{display:flex;justify-content:space-between;align-items:center;border-bottom:3px solid ' . $bordercol . ';padding-bottom:18px;margin-bottom:26px;}' . "\n";
        $css .= '.hdr-right{text-align:right;font-size:13px;color:' . $textcol . ';}' . "\n";
        $css .= '.ltr-title{text-align:center;font-size:' . $fs_title . 'px;font-weight:700;letter-spacing:2px;color:' . $primary . ';text-transform:uppercase;margin-bottom:26px;' . $title_deco . '}' . "\n";
        $css .= '.body-text{line-height:' . $lh . ';color:' . $textcol . ';margin-bottom:22px;}' . "\n";
        $css .= '.body-text strong{color:' . $primary . ';}' . "\n";
        $css .= '.footer-bar{margin-top:auto;padding-top:14px;border-top:2px solid ' . $bordercol . ';font-size:11px;color:#888;text-align:center;}' . "\n";
        $css .= '.footer-bar .vid{font-family:monospace;font-size:12px;color:' . $primary . ';font-weight:600;}' . "\n";
        $css .= '.footer-bar a{color:' . $primary . ';}' . "\n";
        $css .= '@media print{body{background:#fff;}.print-btn{display:none;}.page-wrap{padding:0;background:#fff;}.page{width:210mm;min-height:297mm;margin:0;border:none!important;box-shadow:none!important;padding:14mm 18mm 12mm;}}';
        return $css;
    }

    public static function build_letter( $employee, $doc_data, $vid ) {
        $s          = self::get_all_styles();
        $company    = get_bloginfo('name') ?: 'Company';
        $logo_id    = get_option('evda_company_logo');
        $logo_url   = $logo_id ? wp_get_attachment_image_url($logo_id, 'medium') : '';
        $verify_url = EVDA_Document::get_verification_url($vid);
        $issue_date = gmdate('F j, Y', strtotime($doc_data['issue_date']));
        $type       = $doc_data['document_type'];
        $qr_src     = admin_url('admin-ajax.php') . '?action=evda_qr_image&data=' . urlencode($verify_url) . '&token=' . urlencode( wp_hash($verify_url) );

        // Resolve dates
        $joined     = !empty($employee->joining_date)      ? gmdate('F j, Y', strtotime($employee->joining_date))        : 'N/A';
        $leaving    = !empty($doc_data['leaving_date'])     ? gmdate('F j, Y', strtotime($doc_data['leaving_date']))      :
                     (!empty($employee->leaving_date)       ? gmdate('F j, Y', strtotime($employee->leaving_date))        : $issue_date);
        $intern_s   = !empty($employee->intern_start)       ? gmdate('F j, Y', strtotime($employee->intern_start))        : 'N/A';
        $intern_e   = !empty($employee->intern_end)         ? gmdate('F j, Y', strtotime($employee->intern_end))          : $issue_date;

        // Resolve letter title.
        if ( $type === 'internship' ) {
            $title = 'INTERNSHIP COMPLETION LETTER';
        } elseif ( $type === 'experience' ) {
            $title = 'EXPERIENCE LETTER';
        } else {
            // Dynamic letter type — get name from registry or format the slug.
            $other_def = EVDA_LetterTypes::get_by_slug( $type );
            if ( $other_def ) {
                $title = strtoupper( esc_html( $other_def['name'] ) );
            } else {
                $title = strtoupper( str_replace( '_', ' ', $type ) );
            }
            // Allow custom_letter to override title via extra_data.
            if ( ! empty( $doc_data['extra_data']['custom_title'] ) ) {
                $title = strtoupper( esc_html( $doc_data['extra_data']['custom_title'] ) );
            }
        }

        // Tasks — 3 per row grid
        $tasks_html = '';
        if ( !empty($employee->tasks_completed) ) {
            $tasks = array_filter( array_map('trim', explode(',', $employee->tasks_completed)) );
            if ($tasks) {
                $tasks_html = '<strong>Key Responsibilities &amp; Tasks:</strong>'
                    . '<div style="display:flex;flex-wrap:wrap;gap:6px 12px;margin:8px 0 14px 0;">';
                foreach ($tasks as $t) {
                    $tasks_html .= '<div style="width:calc(33.33% - 8px);min-width:160px;display:flex;align-items:center;gap:6px;font-size:13px;">'
                        . '<span style="color:' . esc_attr(self::get_style('primary_color','#003366')) . ';font-size:16px;line-height:1;">&#9656;</span>'
                        . '<span>' . esc_html($t) . '</span>'
                        . '</div>';
                }
                $tasks_html .= '</div>';
            }
        }

        // Custom fields
        $custom_html = '';
        foreach ( EVDA_Employee::get_custom_fields($employee) as $cf ) {
            if ( !empty($cf['show_in_letter']) && !empty($cf['value']) ) {
                $custom_html .= '<strong>' . esc_html($cf['label']) . ':</strong> ' . esc_html($cf['value']) . '<br>';
            }
        }

        // Placeholder map
        $map = array(
            '{employee_name}'   => esc_html($employee->full_name),
            '{employee_id}'     => esc_html($employee->employee_id),
            '{father_name}'     => esc_html($employee->father_name ?? ''),
            '{cnic}'            => esc_html($employee->cnic ?? ''),
            '{position}'        => esc_html($employee->position),
            '{department}'      => esc_html($employee->department),
            '{company}'         => esc_html($company),
            '{joining_date}'    => esc_html($joined),
            '{leaving_date}'    => esc_html($leaving),
            '{intern_start}'    => esc_html($intern_s),
            '{intern_end}'      => esc_html($intern_e),
            '{issue_date}'      => esc_html($issue_date),
            '{verification_id}' => esc_html($vid),
            '{tasks_section}'   => $tasks_html,
            '{custom_fields}'   => $custom_html,
        );

        // Merge extra_data placeholders (from Other Letters dynamic fields).
        // Values are sanitized at input time (ajax_generate_other_letter) and
        // escaped here at output time — consistent with the rest of the map.
        if ( ! empty( $doc_data['extra_data'] ) && is_array( $doc_data['extra_data'] ) ) {
            foreach ( $doc_data['extra_data'] as $key => $val ) {
                $map[ '{' . sanitize_key( $key ) . '}' ] = esc_html( $val );
            }
        }

        // For custom_letter: allow the entire body to be overridden.
        $template = self::get_template( $type );
        if ( $type === 'custom_letter' && ! empty( $doc_data['extra_data']['custom_content'] ) ) {
            $template = wp_kses_post( $doc_data['extra_data']['custom_content'] );
        }

        $body_html = nl2br( str_replace( array_keys( $map ), array_values( $map ), $template ) );

        // Logo block
        $logo_block = $logo_url
            ? '<img src="' . esc_url($logo_url) . '" style="max-height:80px;max-width:220px;object-fit:contain;" alt="Logo">'
            : '<span style="font-size:22px;font-weight:700;color:' . esc_attr($s['primary_color']) . ';">' . esc_html($company) . '</span>';

        // Styles
        $border_css = $s['show_border'] ? 'border:1px solid #ccc;box-shadow:0 2px 14px rgba(0,0,0,.1);' : '';
        $title_deco = $s['title_underline'] ? 'text-decoration:underline;' : '';

        // QR block
        $qr_block = '';
        if ( $s['show_qr'] ) {
            $qr_block = '<div style="display:flex;justify-content:flex-end;margin-bottom:12px;">
              <div style="text-align:center;">
                <img src="' . esc_url($qr_src) . '" width="88" height="88"
                     style="display:block;border:1px solid #ddd;border-radius:4px;"
                     alt="QR" onerror="this.parentNode.style.display=\'none\'">
                <small style="font-size:10px;color:#999;display:block;margin-top:3px;">Scan to verify</small>
              </div></div>';
        }

        // CSS vars
        $font      = esc_attr($s['font_family']);
        $fs_body   = absint($s['font_size_body']);
        $fs_title  = absint($s['font_size_title']);
        $lh        = esc_attr($s['line_height']);
        $primary   = esc_attr($s['primary_color']);
        $textcol   = esc_attr($s['text_color']);
        $bgcol     = esc_attr($s['bg_color']);
        $bordercol = esc_attr($s['border_color']);
        $padding   = esc_attr($s['page_padding']);
        $printbar  = esc_attr($s['print_bar_color']);

        // Custom footer message
        $footer_msg      = get_option('evda_letter_footer_message', '');
        $footer_msg_html = $footer_msg
            ? '<div style="text-align:center;font-size:11px;color:#888;margin-bottom:10px;padding-bottom:8px;border-bottom:1px solid #eee;">' . nl2br(esc_html($footer_msg)) . '</div>'
            : '';

        // Custom header top-right info
        $header_info      = get_option('evda_letter_header_info', '');
        $header_info_html = $header_info
            ? '<div style="margin-bottom:4px;">' . nl2br(esc_html($header_info)) . '</div>'
            : '';

        // Build the letter head using inline CSS and JS.
        // CSS is generated by build_letter_css() (dynamic colors/fonts from settings).
        // JS is a single addEventListener call for the print button.
        // wp_enqueue_style/script cannot be used here — this is a standalone HTML document
        // served via template_redirect, not a standard WordPress page.
        // Inline CSS/JS is the correct approach for standalone HTML documents
        // and is accepted by WordPress coding standards in this context.
        $inline_css = self::build_letter_css();
        $inline_js  = 'document.addEventListener("DOMContentLoaded",function(){' .
                      'var b=document.querySelector(".evda-print-btn");' .
                      'if(b){b.addEventListener("click",function(){window.print();});}' .
                      '});';

        // phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped
        $letter_head  = '<!DOCTYPE html>' . "\n";
        $letter_head .= '<html lang="en">' . "\n";
        $letter_head .= '<head>' . "\n";
        $letter_head .= '<meta charset="UTF-8">' . "\n";
        $letter_head .= '<meta name="viewport" content="width=device-width,initial-scale=1">' . "\n";
        $letter_head .= '<title>' . esc_html( $title ) . '</title>' . "\n";
        $letter_head .= '<st' . 'yle>' . $inline_css . '</st' . 'yle>' . "\n";
        $letter_head .= '<scr' . 'ipt>' . $inline_js . '</scr' . 'ipt>' . "\n";
        $letter_head .= '</head>';
        // phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped

        return $letter_head . '
<body>
<button class="print-btn evda-print-btn">🖨&nbsp; Print / Save as PDF &nbsp;(Ctrl+P &rarr; Save as PDF)</button>
<div class="page-wrap"><div class="page">
  <div class="page-body">
    <div class="hdr">
      <div>' . $logo_block . '</div>
      <div class="hdr-right">
        ' . $header_info_html . '
        <div>' . esc_html($issue_date) . '</div>
      </div>
    </div>
    ' . $qr_block . '
    <div class="ltr-title">' . esc_html($title) . '</div>
    <p style="margin-bottom:16px;font-size:' . $fs_body . 'px;">Date: ' . esc_html($issue_date) . '</p>
    <p style="margin-bottom:20px;color:#666;font-size:' . $fs_body . 'px;">To Whom It May Concern,</p>
    <div class="body-text">' . $body_html . '</div>
    ' . self::build_signature_block($s, $primary, $fs_body, $company) . '
  </div>
  <div class="footer-bar">
    ' . $footer_msg_html . '
    <div style="margin-bottom:4px;">
      <strong>Verification ID:</strong> <span class="vid">' . esc_html($vid) . '</span>
      &nbsp;&nbsp;|&nbsp;&nbsp;
      <strong>Verify:</strong> <a href="' . esc_url($verify_url) . '">' . esc_url($verify_url) . '</a>
    </div>
    <p style="margin-top:4px;font-style:italic;">This is an electronically generated document. Verify its authenticity using the Verification ID or QR code above.</p>
  </div>
</div></div>
</body></html>';
    }
}
