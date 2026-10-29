<?php
if (!defined('ABSPATH')) exit;

class EVDA_Settings {

    public static function save() {
        if (!isset($_POST['evda_save_settings']) || !check_admin_referer('evda_settings_nonce')) return;

        // General
        update_option( 'evda_company_logo',               absint( $_POST['evda_company_logo'] ?? 0 ) );
        update_option( 'evda_document_verification_page', absint( $_POST['evda_doc_verify_page'] ?? 0 ) );
        update_option( 'evda_employee_verification_page', absint( $_POST['evda_emp_verify_page'] ?? 0 ) );

        // Email
        update_option('evda_email_enabled',           isset($_POST['evda_email_enabled']) ? 1 : 0);
        update_option( 'evda_email_subject_internship', sanitize_text_field( wp_unslash( $_POST['evda_email_subject_internship'] ?? '' ) ) );
        update_option( 'evda_email_subject_experience',  sanitize_text_field( wp_unslash( $_POST['evda_email_subject_experience'] ?? '' ) ) );
        update_option( 'evda_email_body_internship',     wp_kses_post( wp_unslash( $_POST['evda_email_body_internship'] ?? '' ) ) );
        update_option( 'evda_email_body_experience',     wp_kses_post( wp_unslash( $_POST['evda_email_body_experience'] ?? '' ) ) );

        // Letter templates
        update_option( 'evda_letter_template_internship', wp_kses_post( wp_unslash( $_POST['evda_letter_template_internship'] ?? '' ) ) );
        update_option( 'evda_letter_template_experience',  wp_kses_post( wp_unslash( $_POST['evda_letter_template_experience'] ?? '' ) ) );

        // Styling
        $style_keys = array(
            'primary_color','text_color','bg_color','header_bg','header_text',
            'border_color','font_family','font_size_body','font_size_title',
            'line_height','page_padding','show_border','title_underline','show_qr'
        );
        foreach ($style_keys as $k) {
            $val = sanitize_text_field( wp_unslash( $_POST[ 'evda_style_' . $k ] ?? '' ) );
            update_option( 'evda_letter_style_' . $k, $val );
        }

        // Custom field definitions (global)
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $cf_labels  = array_map( 'sanitize_text_field', array_map( 'wp_unslash', $_POST['evda_cf_label'] ?? array() ) );
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $cf_keys    = array_map( 'sanitize_key', array_map( 'wp_unslash', $_POST['evda_cf_key'] ?? array() ) );
        $defs = array();
        foreach ($cf_labels as $i => $label) {
            $key = $cf_keys[$i] ?? '';
            if ($label && $key) $defs[] = array('label' => $label, 'key' => $key);
        }
        update_option('evda_custom_field_defs', $defs);
    }

    public static function render() {
        self::save();
        $s           = EVDA_PDF::get_all_styles();
        $logo_id     = get_option('evda_company_logo', 0);
        $doc_page_id = get_option('evda_document_verification_page', 0);
        $emp_page_id = get_option('evda_employee_verification_page', 0);
        $email_on    = get_option('evda_email_enabled', 1);
        $s_int       = get_option('evda_email_subject_internship', 'Your Internship Completion Letter from {company}');
        $s_exp       = get_option('evda_email_subject_experience',  'Your Experience Letter from {company}');
        $b_int       = get_option('evda_email_body_internship', '');
        $b_exp       = get_option('evda_email_body_experience', '');
        $t_int       = get_option('evda_letter_template_internship', '');
        $t_exp       = get_option('evda_letter_template_experience', '');
        $cf_defs     = get_option('evda_custom_field_defs', array());
        $placeholders = '{employee_name}, {employee_id}, {father_name}, {cnic}, {position}, {department}, {company}, {joining_date}, {leaving_date}, {intern_start}, {intern_end}, {issue_date}, {verification_id}, {tasks_section}, {custom_fields}';
        $gf_placeholders = '{employee_name}, {employee_id}, {position}, {department}, {company}, {issue_date}, {verification_id}, {verify_url}, {letter_type}';
        $fonts = array(
            'Segoe UI, Arial, sans-serif' => 'Segoe UI (Default)',
            'Arial, sans-serif'           => 'Arial',
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
        <form method="POST" id="ev-settings-form">
        <?php wp_nonce_field('evda_settings_nonce'); ?>

        <!-- TAB NAV -->
        <div class="ev-tabs">
            <button type="button" class="ev-tab active" data-tab="general">🏢 General</button>
            <button type="button" class="ev-tab" data-tab="letter">📝 Letter Templates</button>
            <button type="button" class="ev-tab" data-tab="styling">🎨 Letter Styling</button>
            <button type="button" class="ev-tab" data-tab="email">📧 Email</button>
            <button type="button" class="ev-tab" data-tab="customfields">🔧 Custom Fields</button>
        </div>

        <!-- ===================== GENERAL ===================== -->
        <div class="ev-tab-panel active" id="tab-general">
            <table class="form-table">
                <tr>
                    <th>Company Logo</th>
                    <td>
                        <input type="hidden" name="evda_company_logo" id="ev-settings-logo-id" value="<?php echo absint( $logo_id ); ?>">
                        <button type="button" class="button" id="ev-upload-logo">Upload / Change Logo</button>
                        <?php if ($logo_id) echo wp_kses_post( '<br>' . wp_get_attachment_image( $logo_id, 'thumbnail' ) ); ?>
                        <p class="description">Appears on all generated letters.</p>
                    </td>
                </tr>
                <tr>
                    <th>Employee Verification Page</th>
                    <td>
                        <?php wp_dropdown_pages(array('name'=>'evda_emp_verify_page','selected'=>absint($emp_page_id),'show_option_none'=>'— Select —','option_none_value'=>0)); ?>
                        <p class="description">Page with <code>[evda_employee_verification]</code>.</p>
                    </td>
                </tr>
                <tr>
                    <th>Document Verification Page</th>
                    <td>
                        <?php wp_dropdown_pages(array('name'=>'evda_doc_verify_page','selected'=>absint($doc_page_id),'show_option_none'=>'— Select —','option_none_value'=>0)); ?>
                        <p class="description">Page with <code>[evda_document_verification]</code>.</p>
                    </td>
                </tr>
            </table>
        </div>

        <!-- ===================== LETTER TEMPLATES ===================== -->
        <div class="ev-tab-panel" id="tab-letter">
            <p class="ev-help-text">Customize the body text of each letter. Use these placeholders:<br>
            <code><?php echo esc_html($placeholders); ?></code></p>
            <p class="ev-help-text evda-help-text-mt">
                <strong>{tasks_section}</strong> — auto-generates a bulleted task list from the employee's "Tasks Completed" field.<br>
                <strong>{custom_fields}</strong> — renders any custom fields marked "show in letter".
            </p>

            <h3>🎓 Internship Completion Letter</h3>
            <textarea name="evda_letter_template_internship" rows="12" class="large-text evda-template-textarea"><?php echo esc_textarea($t_int ?: EVDA_PDF::get_default_template('internship')); ?></textarea>
            <p class="description">Leave blank to reset to default on next save.</p>

            <h3 class="evda-section-mt">💼 Experience Letter</h3>
            <textarea name="evda_letter_template_experience" rows="12" class="large-text evda-template-textarea"><?php echo esc_textarea($t_exp ?: EVDA_PDF::get_default_template('experience')); ?></textarea>

            <div class="ev-preview-bar evda-preview-bar">
                <a href="<?php echo esc_url( admin_url('admin-ajax.php?action=evda_preview_letter&type=internship&nonce='.wp_create_nonce('evda_admin_nonce')) ); ?>" target="_blank" class="button">👁 Preview Internship Letter</a>
                <a href="<?php echo esc_url( admin_url('admin-ajax.php?action=evda_preview_letter&type=experience&nonce='.wp_create_nonce('evda_admin_nonce')) ); ?>" target="_blank" class="button evda-preview-btn-ml">👁 Preview Experience Letter</a>
                <p class="description">Save settings first, then preview.</p>
            </div>
        </div>

        <!-- ===================== STYLING ===================== -->
        <div class="ev-tab-panel" id="tab-styling">
            <div class="evda-grid-2col">
                <div>
                    <h3>🎨 Colors</h3>
                    <table class="form-table evda-form-table-0">
                        <?php
                        $color_fields = array(
                            'primary_color'  => array('Primary / Accent Color', 'Used for titles, borders, links'),
                            'text_color'     => array('Body Text Color', 'Main text color'),
                            'bg_color'       => array('Page Background', 'Letter background color'),
                            'border_color'   => array('Header Border Color', 'Line under the header'),
                        );
                        foreach ($color_fields as $key => $info) : ?>
                        <tr>
                            <th class="evda-color-th"><label><?php echo esc_html( $info[0] ); ?></label></th>
                            <td>
                                <input type="color" name="evda_style_<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr($s[$key]); ?>" class="evda-color-input">
                                <input type="text" name="evda_style_<?php echo esc_attr( $key ); ?>_text" value="<?php echo esc_attr($s[$key]); ?>" class="evda-hex-input-field evda-hex-sibling" data-color-name="evda_style_<?php echo esc_attr( $key ); ?>">
                                <p class="description evda-color-desc"><?php echo esc_html( $info[1] ); ?></p>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </table>
                </div>
                <div>
                    <h3>🔤 Typography</h3>
                    <table class="form-table evda-form-table-0">
                        <tr>
                            <th><label>Font Family</label></th>
                            <td>
                                <select name="evda_style_font_family">
                                    <?php foreach ($fonts as $val => $label) : ?>
                                        <option value="<?php echo esc_attr($val); ?>" <?php selected($s['font_family'],$val); ?> data-font="<?php echo esc_attr($val); ?>"><?php echo esc_html($label); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th><label>Body Font Size (px)</label></th>
                            <td><input type="number" name="evda_style_font_size_body" value="<?php echo esc_attr($s['font_size_body']); ?>" min="10" max="22" class="evda-number-input-sm"> px</td>
                        </tr>
                        <tr>
                            <th><label>Title Font Size (px)</label></th>
                            <td><input type="number" name="evda_style_font_size_title" value="<?php echo esc_attr($s['font_size_title']); ?>" min="14" max="36" class="evda-number-input-sm"> px</td>
                        </tr>
                        <tr>
                            <th><label>Line Height</label></th>
                            <td><input type="number" name="evda_style_line_height" value="<?php echo esc_attr($s['line_height']); ?>" min="1" max="3" step="0.1" class="evda-number-input-sm"></td>
                        </tr>
                    </table>

                    <h3 class="evda-section-mt">📐 Layout</h3>
                    <table class="form-table evda-form-table-0">
                        <tr>
                            <th><label>Page Padding</label></th>
                            <td><input type="text" name="evda_style_page_padding" value="<?php echo esc_attr($s['page_padding']); ?>" class="evda-padding-input" placeholder="50px 60px">
                            <p class="description">CSS shorthand e.g. <code>50px 60px</code></p></td>
                        </tr>
                        <tr>
                            <th><label>Show Page Border</label></th>
                            <td><label><input type="checkbox" name="evda_style_show_border" value="1" <?php checked($s['show_border'],'1'); ?>> Show border &amp; shadow around letter</label></td>
                        </tr>
                        <tr>
                            <th><label>Underline Title</label></th>
                            <td><label><input type="checkbox" name="evda_style_title_underline" value="1" <?php checked($s['title_underline'],'1'); ?>> Underline the letter title</label></td>
                        </tr>
                        <tr>
                            <th><label>Show QR Code</label></th>
                            <td><label><input type="checkbox" name="evda_style_show_qr" value="1" <?php checked($s['show_qr'],'1'); ?>> Show QR code on letter</label></td>
                        </tr>
                    </table>
                </div>
            </div>
            <!-- Live preview swatch -->
            <div class="evda-preview-section">
                <strong>Style Preview</strong>
                <div id="ev-style-preview" class="evda-style-preview-box">
                    <div class="evda-preview-header">Company Name</div>
                    <div class="evda-preview-title">EXPERIENCE LETTER</div>
                    <div class="evda-preview-body">This is to certify that <strong class="evda-preview-highlight">John Smith</strong> was employed at <strong class="evda-preview-highlight">Company Name</strong> as <strong class="evda-preview-highlight">Web Developer</strong>...</div>
                </div>
                <p class="description evda-cf-desc">Save settings and re-open letter to see full styling applied.</p>
            </div>
        </div>

        <!-- ===================== EMAIL ===================== -->
        <div class="ev-tab-panel" id="tab-email">
            <p class="ev-help-text">Placeholders: <code><?php echo esc_html($gf_placeholders); ?></code></p>
            <table class="form-table">
                <tr>
                    <th>Enable Email Sending</th>
                    <td>
                        <label><input type="checkbox" name="evda_email_enabled" value="1" <?php checked($email_on,1); ?>>
                        Auto-email employees when a letter is issued</label>
                        <p class="description">Uses WordPress <code>wp_mail()</code>. Works on Namecheap hosting out of the box.</p>
                    </td>
                </tr>
                <tr>
                    <th>🎓 Internship Subject</th>
                    <td><input type="text" name="evda_email_subject_internship" class="large-text" value="<?php echo esc_attr($s_int); ?>"></td>
                </tr>
                <tr>
                    <th>🎓 Internship Body</th>
                    <td><textarea name="evda_email_body_internship" rows="7" class="large-text"><?php echo esc_textarea($b_int); ?></textarea></td>
                </tr>
                <tr>
                    <th>💼 Experience Subject</th>
                    <td><input type="text" name="evda_email_subject_experience" class="large-text" value="<?php echo esc_attr($s_exp); ?>"></td>
                </tr>
                <tr>
                    <th>💼 Experience Body</th>
                    <td><textarea name="evda_email_body_experience" rows="7" class="large-text"><?php echo esc_textarea($b_exp); ?></textarea></td>
                </tr>
                <tr>
                    <th>Send Test Email</th>
                    <td>
                        <input type="email" id="ev-test-email-addr" class="regular-text" placeholder="test@example.com">
                        <button type="button" class="button" id="ev-send-test-email">📤 Send Test</button>
                        <span id="ev-test-email-result" class="evda-test-email-span"></span>
                    </td>
                </tr>
            </table>
        </div>

        <!-- ===================== CUSTOM FIELDS ===================== -->
        <div class="ev-tab-panel" id="tab-customfields">
            <p class="ev-help-text">Define extra fields that appear on every employee's form. You can also mark them to appear in the generated letter using the <code>{custom_fields}</code> placeholder.</p>
            <table class="widefat evda-cf-table" id="ev-cf-table">
                <thead><tr><th>Field Label</th><th>Field Key (no spaces)</th><th></th></tr></thead>
                <tbody id="ev-cf-rows">
                    <?php foreach ($cf_defs as $cf) : ?>
                    <tr class="ev-cf-row">
                        <td><input type="text" name="evda_cf_label[]" value="<?php echo esc_attr($cf['label']); ?>" class="regular-text" placeholder="e.g. Salary"></td>
                        <td><input type="text" name="evda_cf_key[]"   value="<?php echo esc_attr($cf['key']); ?>"   class="regular-text" placeholder="e.g. salary"></td>
                        <td><button type="button" class="button ev-cf-remove">🗑 Remove</button></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <button type="button" class="button evda-cf-add-btn" id="ev-cf-add">➕ Add Custom Field</button>
            <p class="description evda-cf-desc">After saving, these fields appear in the employee edit form. You can toggle "Show in Letter" per employee.</p>
        </div>

        <div class="evda-settings-submit">
            <input type="submit" name="evda_save_settings" class="button button-primary button-large" value="💾 Save All Settings">
        </div>
        </form>
        </div>

        <?php
    }
}
