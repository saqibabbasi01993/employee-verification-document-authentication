<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class EVDA_Email {

    /**
     * Send letter notification to employee.
     * Called automatically after a letter is generated.
     */
    public static function send_letter_notification( $employee, $doc, $vid ) {
        // Check if email sending is enabled
        if ( ! get_option( 'evda_email_enabled', 1 ) ) return false;

        $to = sanitize_email( $employee->email ?? '' );
        if ( ! $to || ! is_email( $to ) ) return false;

        $type        = sanitize_key( $doc['document_type'] );
        $company     = get_bloginfo( 'name' );
        $verify_url  = EVDA_Document::get_verification_url( $vid );
        $issue_date  = gmdate( 'F j, Y', strtotime( $doc['issue_date'] ) );

        // Resolve human-readable label for any letter type.
        if ( $type === 'internship' ) {
            $label = 'Internship Completion Letter';
        } elseif ( $type === 'experience' ) {
            $label = 'Experience Letter';
        } else {
            $other_def = EVDA_LetterTypes::get_by_slug( $type );
            $label     = $other_def ? $other_def['name'] : ucwords( str_replace( '_', ' ', $type ) );
            // Allow custom_letter to use its custom_title.
            if ( $type === 'custom_letter' && ! empty( $doc['extra_data']['custom_title'] ) ) {
                $label = sanitize_text_field( $doc['extra_data']['custom_title'] );
            }
        }

        // Get template from settings or use default
        $subject_tpl = get_option( 'evda_email_subject_' . $type, '' );
        $body_tpl    = get_option( 'evda_email_body_' . $type, '' );

        // Build replacements
        $replacements = array(
            '{employee_name}'   => esc_html( $employee->full_name ),
            '{employee_id}'     => esc_html( $employee->employee_id ),
            '{position}'        => esc_html( $employee->position ),
            '{department}'      => esc_html( $employee->department ),
            '{company}'         => esc_html( $company ),
            '{issue_date}'      => esc_html( $issue_date ),
            '{verification_id}' => esc_html( $vid ),
            '{verify_url}'      => esc_url( $verify_url ),
            '{letter_type}'     => esc_html( $label ),
        );

        // Subject
        if ( ! $subject_tpl ) {
            $subject_tpl = "Your {letter_type} from {company}";
        }
        $subject = str_replace( array_keys($replacements), array_values($replacements), $subject_tpl );

        // Body
        if ( ! $body_tpl ) {
            $body_tpl = self::default_body( $type );
        }
        $body_text = str_replace( array_keys($replacements), array_values($replacements), $body_tpl );

        // Build HTML email
        $html_body = self::wrap_html( $body_text, $label, $company, $verify_url, $vid );

        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . esc_html( $company ) . ' <' . sanitize_email( get_option('admin_email') ) . '>',
        );

        $sent = wp_mail( $to, $subject, $html_body, $headers );

        // Log the attempt
        self::log( array(
            'employee_id' => $employee->employee_id,
            'email'       => $to,
            'type'        => $type,
            'vid'         => $vid,
            'sent'        => $sent ? 1 : 0,
            'sent_at'     => current_time( 'mysql' ),
        ) );

        return $sent;
    }

    private static function default_body( $type ) {
        if ( $type === 'internship' ) {
            return "Dear {employee_name},\n\nCongratulations on completing your internship at {company}!\n\nWe are pleased to issue your Internship Completion Letter. Please find the details below:\n\n<strong>Employee ID:</strong> {employee_id}\n<strong>Position:</strong> {position}\n<strong>Department:</strong> {department}\n<strong>Issue Date:</strong> {issue_date}\n\nYou can verify this letter at any time using the button below.\n\nWe wish you all the best in your future career.\n\nBest regards,\n{company}";
        }
        if ( $type === 'experience' ) {
            return "Dear {employee_name},\n\nPlease find your Experience Letter from {company} issued on {issue_date}.\n\n<strong>Employee ID:</strong> {employee_id}\n<strong>Position:</strong> {position}\n<strong>Department:</strong> {department}\n<strong>Issue Date:</strong> {issue_date}\n\nYou can verify this letter using the button below.\n\nWe wish you great success in your future endeavors.\n\nBest regards,\n{company}";
        }
        // Generic default for other letter types.
        return "Dear {employee_name},\n\nPlease find your {letter_type} from {company} issued on {issue_date}.\n\n<strong>Employee ID:</strong> {employee_id}\n<strong>Position:</strong> {position}\n<strong>Department:</strong> {department}\n\nYou can verify this document using the button below.\n\nBest regards,\n{company}";
    }

    /**
     * Wraps email content in HTML template.
     * Inline styles are required for email client compatibility.
     * Gmail, Outlook, and Apple Mail strip external stylesheets.
     * Inline CSS is the established industry standard for HTML emails.
     */
    private static function wrap_html( $body_text, $label, $company, $verify_url, $vid ) {
        $logo_id  = get_option( 'evda_company_logo' );
        $logo_url = $logo_id ? wp_get_attachment_image_url( $logo_id, 'medium' ) : '';
        $logo_tag = $logo_url
            ? '<img src="' . esc_url($logo_url) . '" style="max-height:70px;max-width:200px;" alt="' . esc_attr($company) . '">'
            : '<strong style="font-size:20px;color:#ffffff;">' . esc_html($company) . '</strong>';

        // Email colors from settings
        $c_header   = get_option('evda_email_color_header',   '#003366');
        $c_accent   = get_option('evda_email_color_accent',   '#003366');
        $c_button   = get_option('evda_email_color_button',   '#003366');
        $c_bg       = get_option('evda_email_color_bg',       '#f4f6f9');
        $c_text     = get_option('evda_email_color_text',     '#333333');
        $c_title_bg = get_option('evda_email_color_title_bg', '#e8f0fe');
        $c_title_tx = get_option('evda_email_color_title_tx', '#003366');

        $body_html = nl2br( $body_text );

        return '<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:' . esc_attr($c_bg) . ';font-family:Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:' . esc_attr($c_bg) . ';padding:30px 0;">
  <tr><td align="center">
    <table width="600" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.1);">
      <tr><td style="background:' . esc_attr($c_header) . ';padding:24px 32px;text-align:center;">
        ' . $logo_tag . '
      </td></tr>
      <tr><td style="background:' . esc_attr($c_title_bg) . ';padding:14px 32px;border-bottom:3px solid ' . esc_attr($c_title_tx) . ';text-align:center;">
        <p style="margin:0;font-size:16px;font-weight:700;color:' . esc_attr($c_title_tx) . ';text-transform:uppercase;letter-spacing:1px;">' . esc_html($label) . ' Issued</p>
      </td></tr>
      <tr><td style="padding:28px 32px;font-size:14px;color:' . esc_attr($c_text) . ';line-height:1.8;">
        ' . $body_html . '
      </td></tr>
      <tr><td style="padding:0 32px 28px;text-align:center;">
        <a href="' . esc_url($verify_url) . '" style="display:inline-block;background:' . esc_attr($c_button) . ';color:#fff;padding:12px 32px;border-radius:6px;text-decoration:none;font-size:15px;font-weight:600;">
          🔗 Verify This Letter
        </a>
        <p style="margin-top:12px;font-size:12px;color:#888;">Verification ID: <strong style="font-family:monospace;color:' . esc_attr($c_accent) . ';">' . esc_html($vid) . '</strong></p>
        <p style="font-size:11px;color:#aaa;">Or copy this link: ' . esc_url($verify_url) . '</p>
      </td></tr>
      <tr><td style="background:#f9f9f9;padding:16px 32px;border-top:1px solid #eee;text-align:center;font-size:11px;color:#999;">
        This email was sent by ' . esc_html($company) . ' | ' . esc_html( get_bloginfo('url') ) . '
      </td></tr>
    </table>
  </td></tr>
</table>
</body>
</html>';
    }

    /**
     * Lighten a hex color by mixing with white.
     * $amount: 0 = original, 1 = white
     */
    private static function hex_lighten( $hex, $amount ) {
        $hex = ltrim($hex, '#');
        if ( strlen($hex) !== 6 ) return '#e8f0fe';
        $r = hexdec(substr($hex,0,2));
        $g = hexdec(substr($hex,2,2));
        $b = hexdec(substr($hex,4,2));
        $r = (int) round($r + (255 - $r) * $amount);
        $g = (int) round($g + (255 - $g) * $amount);
        $b = (int) round($b + (255 - $b) * $amount);
        return sprintf('#%02x%02x%02x', $r, $g, $b);
    }

    public static function build_test_email_html( $body_text, $company, $verify_url, $vid ) {
        return self::wrap_html( $body_text, 'Test Email', $company, $verify_url, $vid );
    }

    private static function log( $data ) {
        $logs = get_option( 'evda_email_logs', array() );
        array_unshift( $logs, $data ); // newest first
        $logs = array_slice( $logs, 0, 100 ); // keep last 100
        update_option( 'evda_email_logs', $logs );
    }

    public static function get_logs() {
        return get_option( 'evda_email_logs', array() );
    }
}
