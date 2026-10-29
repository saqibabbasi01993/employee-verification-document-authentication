<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class EVDA_LetterTypes {

    const OPTION_KEY = 'evda_other_letter_types';

    public static function get_all() {
        $types = get_option( self::OPTION_KEY, array() );
        return is_array( $types ) ? $types : array();
    }

    public static function get_active() {
        return array_values( array_filter( self::get_all(), function( $t ) {
            return isset( $t['status'] ) && $t['status'] === 'active';
        } ) );
    }

    public static function get_by_slug( $slug ) {
        $slug = sanitize_key( $slug );
        foreach ( self::get_all() as $type ) {
            if ( isset( $type['slug'] ) && $type['slug'] === $slug ) {
                return $type;
            }
        }
        return null;
    }

    public static function save( $data ) {
        return true;
    }

    public static function delete( $slug ) {
        return true;
    }

    public static function get_default_template( $name ) {
        return "This is a default template for {$name}.\n\nEmployee: {employee_name}\nPosition: {position}\nDepartment: {department}\n\nIssued by: {company}\nDate: {issue_date}";
    }

    public static function get_placeholders( $type_def = null ) {
        return array(
            '{employee_name}'   => 'Full name of the employee',
            '{employee_id}'     => 'Employee ID',
            '{position}'        => 'Job position',
            '{department}'      => 'Department',
            '{company}'         => 'Company name',
            '{issue_date}'      => 'Issue date',
            '{verification_id}' => 'Verification ID',
        );
    }

    public static function seed_defaults() {
        // Stub — real seeding happens via force_reseed_templates()
        $types = array(
            array( 'slug' => 'bank_letter',       'name' => 'Bank Account Opening Letter',  'status' => 'active', 'allow_multiple' => true,  'extra_fields' => array( array( 'key' => 'bank_name', 'label' => 'Bank Name', 'type' => 'text', 'required' => true ), array( 'key' => 'branch', 'label' => 'Branch / City', 'type' => 'text', 'required' => true ), array( 'key' => 'account_type', 'label' => 'Account Type', 'type' => 'text', 'required' => false ) ), 'description' => 'Issued for bank account opening.' ),
            array( 'slug' => 'salary_certificate', 'name' => 'Salary Certificate',           'status' => 'active', 'allow_multiple' => true,  'extra_fields' => array( array( 'key' => 'salary', 'label' => 'Monthly Gross Salary', 'type' => 'text', 'required' => true ), array( 'key' => 'salary_words', 'label' => 'Salary in Words', 'type' => 'text', 'required' => true ), array( 'key' => 'allowances', 'label' => 'Allowances', 'type' => 'text', 'required' => false ), array( 'key' => 'purpose', 'label' => 'Purpose', 'type' => 'text', 'required' => false ) ), 'description' => 'Official salary certificate.' ),
            array( 'slug' => 'joining_letter',     'name' => 'Joining Letter',               'status' => 'active', 'allow_multiple' => false, 'extra_fields' => array( array( 'key' => 'joining_date', 'label' => 'Date of Joining', 'type' => 'date', 'required' => true ), array( 'key' => 'probation_period', 'label' => 'Probation Period', 'type' => 'text', 'required' => true ), array( 'key' => 'offered_salary', 'label' => 'Offered Monthly Salary', 'type' => 'text', 'required' => true ), array( 'key' => 'reporting_to', 'label' => 'Reporting To', 'type' => 'text', 'required' => false ), array( 'key' => 'work_hours', 'label' => 'Working Hours', 'type' => 'text', 'required' => false ) ), 'description' => 'Issued to new employees upon joining.' ),
            array( 'slug' => 'promotion_letter',   'name' => 'Promotion Letter',             'status' => 'active', 'allow_multiple' => true,  'extra_fields' => array( array( 'key' => 'new_position', 'label' => 'New Position', 'type' => 'text', 'required' => true ), array( 'key' => 'promotion_date', 'label' => 'Promotion Date', 'type' => 'date', 'required' => true ), array( 'key' => 'new_salary', 'label' => 'Revised Salary', 'type' => 'text', 'required' => false ), array( 'key' => 'new_department', 'label' => 'New Department', 'type' => 'text', 'required' => false ) ), 'description' => 'Issued when an employee is promoted.' ),
            array( 'slug' => 'warning_letter',     'name' => 'Warning Letter',               'status' => 'active', 'allow_multiple' => true,  'extra_fields' => array( array( 'key' => 'warning_reason', 'label' => 'Reason for Warning', 'type' => 'textarea', 'required' => true ), array( 'key' => 'incident_date', 'label' => 'Date of Incident', 'type' => 'date', 'required' => true ), array( 'key' => 'improvement_period', 'label' => 'Improvement Period', 'type' => 'text', 'required' => false ) ), 'description' => 'Formal warning issued to an employee.' ),
            array( 'slug' => 'transfer_letter',    'name' => 'Transfer Letter',              'status' => 'active', 'allow_multiple' => true,  'extra_fields' => array( array( 'key' => 'transfer_to', 'label' => 'Transfer To', 'type' => 'text', 'required' => true ), array( 'key' => 'transfer_date', 'label' => 'Effective Date', 'type' => 'date', 'required' => true ), array( 'key' => 'reporting_to', 'label' => 'New Reporting Manager', 'type' => 'text', 'required' => false ), array( 'key' => 'transfer_reason', 'label' => 'Reason for Transfer', 'type' => 'text', 'required' => false ) ), 'description' => 'Issued when an employee is transferred.' ),
            array( 'slug' => 'relieving_letter',   'name' => 'Relieving Letter',             'status' => 'active', 'allow_multiple' => false, 'extra_fields' => array( array( 'key' => 'last_working_day', 'label' => 'Last Working Day', 'type' => 'date', 'required' => true ), array( 'key' => 'notice_period', 'label' => 'Notice Period Served', 'type' => 'text', 'required' => false ), array( 'key' => 'clearance_status', 'label' => 'Clearance Status', 'type' => 'text', 'required' => false ) ), 'description' => 'Issued upon completion of notice period.' ),
            array( 'slug' => 'noc',                'name' => 'No Objection Certificate',     'status' => 'active', 'allow_multiple' => true,  'extra_fields' => array( array( 'key' => 'noc_purpose', 'label' => 'Purpose of NOC', 'type' => 'text', 'required' => true ), array( 'key' => 'destination', 'label' => 'Country / University / Body', 'type' => 'text', 'required' => false ), array( 'key' => 'duration', 'label' => 'Duration / Period', 'type' => 'text', 'required' => false ) ), 'description' => 'NOC for visa, studies, or other purposes.' ),
            array( 'slug' => 'custom_letter',      'name' => 'Custom Employee Letter',       'status' => 'active', 'allow_multiple' => true,  'extra_fields' => array( array( 'key' => 'custom_title', 'label' => 'Letter Title', 'type' => 'text', 'required' => true ), array( 'key' => 'custom_content', 'label' => 'Letter Content', 'type' => 'textarea', 'required' => true ) ), 'description' => 'A fully customizable letter.' ),
        );
        update_option( self::OPTION_KEY, $types );
        foreach ( $types as $def ) {
            $tpl = self::get_default_template( $def['name'] );
            update_option( 'evda_letter_template_' . $def['slug'], $tpl );
            update_option( 'evda_email_subject_'   . $def['slug'], 'Your ' . $def['name'] . ' from {company}' );
            update_option( 'evda_email_body_'      . $def['slug'], 'Dear {employee_name}, please find your ' . $def['name'] . ' attached. Regards, {company}' );
        }
    }

    public static function force_reseed_templates() {
        // Professional HR templates — stored via admin_init, not during activation.
        $templates = array(
            'bank_letter' => array(
                'template' => "To Whom It May Concern,

This is to certify that {employee_name} (Employee ID: {employee_id}) is a permanent employee of {company} and has been working with us as {position} in the {department} department since {joining_date}.

We request you to kindly open a salary account for the above-mentioned employee at your bank.

Bank Name: {bank_name}
Branch: {branch}
Account Type: {account_type}

We confirm that the monthly salary will be credited to the above account regularly. {company} takes full responsibility for the authenticity of this letter.

Yours sincerely,
{company}",
                'email_subject' => 'Bank Account Opening Letter — {company}',
                'email_body'    => "Dear {employee_name},

Please find attached your Bank Account Opening Letter issued by {company} on {issue_date}.

Kindly present this letter along with your valid CNIC at the bank branch.

You can verify this document using the link below.

Best regards,
HR Department
{company}",
            ),
            'salary_certificate' => array(
                'template' => "To Whom It May Concern,

SALARY CERTIFICATE

This is to certify that {employee_name} (Employee ID: {employee_id}), CNIC No. {cnic}, is employed with {company} as {position} in the {department} department since {joining_date}.

Monthly Gross Salary: {salary}
In Words: {salary_words}
Allowances / Benefits: {allowances}

This certificate is issued on {issue_date} for the purpose of {purpose}. It is valid for 30 days from the date of issuance.

Yours sincerely,
{company}",
                'email_subject' => 'Your Salary Certificate — {company}',
                'email_body'    => "Dear {employee_name},

Please find your Salary Certificate issued by {company} dated {issue_date}.

This certificate confirms your employment and monthly compensation. It is valid for 30 days. For a revised certificate, please contact HR.

You can verify this document using the link below.

Best regards,
HR Department
{company}",
            ),
            'joining_letter' => array(
                'template' => "Dear {employee_name},

SUBJECT: Letter of Appointment

We are pleased to offer you the position of {position} in the {department} department at {company}, effective {joining_date}.

Position: {position}
Department: {department}
Date of Joining: {joining_date}
Reporting To: {reporting_to}
Monthly Salary: {offered_salary}
Probation Period: {probation_period}
Working Hours: {work_hours}

During probation, your performance will be evaluated. Upon successful completion, your employment will be confirmed.

Please confirm acceptance by signing and returning a copy of this letter.

We look forward to welcoming you to our team.

Sincerely,
{company}",
                'email_subject' => 'Welcome to {company} — Your Joining Letter',
                'email_body'    => "Dear {employee_name},

On behalf of the entire team at {company}, we are thrilled to welcome you aboard!

Please find your official Joining Letter attached. Kindly review it carefully and confirm your acceptance by signing and returning a copy to HR.

We look forward to a long and successful journey together!

Warm regards,
HR Department
{company}",
            ),
            'promotion_letter' => array(
                'template' => "Dear {employee_name},

SUBJECT: Promotion Letter

Based on your exceptional performance and contributions to {company}, management has decided to promote you to {new_position}, effective {promotion_date}.

Current Position: {position}
New Position: {new_position}
New Department: {new_department}
Revised Salary: {new_salary}
Effective Date: {promotion_date}

This promotion reflects the confidence {company} has in your abilities. We expect you to take on the additional responsibilities with the same dedication you have always shown.

Congratulations on this well-deserved achievement.

Sincerely,
{company}",
                'email_subject' => 'Congratulations on Your Promotion — {company}',
                'email_body'    => "Dear {employee_name},

Congratulations! We are delighted to inform you of your promotion to {new_position} at {company}, effective {promotion_date}.

This achievement reflects your hard work and outstanding contributions. Please find your official Promotion Letter attached.

Keep up the excellent work!

Warm congratulations,
HR Department
{company}",
            ),
            'warning_letter' => array(
                'template' => "Dear {employee_name},

SUBJECT: Written Warning Notice

Date: {issue_date}
Employee ID: {employee_id}
Department: {department}

This letter serves as a formal written warning regarding your conduct on {incident_date}.

REASON FOR WARNING:
{warning_reason}

This behavior violates {company} policies. You are required to show significant improvement within {improvement_period}. Failure to do so may result in further disciplinary action including termination.

Please sign and return a copy as acknowledgment of receipt.

Yours sincerely,
{company}

Employee Acknowledgment: _____________________ Date: ___________",
                'email_subject' => 'Formal Warning Notice — {company}',
                'email_body'    => "Dear {employee_name},

Please find attached a formal warning letter issued by {company} dated {issue_date}.

We urge you to review this letter carefully. If you wish to discuss this matter, please contact HR to schedule a meeting.

Please acknowledge receipt at your earliest convenience.

Regards,
HR Department
{company}",
            ),
            'transfer_letter' => array(
                'template' => "Dear {employee_name},

SUBJECT: Transfer Order

The management of {company} has decided to transfer you from the {department} department to {transfer_to}, effective {transfer_date}.

Employee ID: {employee_id}
Current Position: {position}
Transfer To: {transfer_to}
Effective Date: {transfer_date}
New Reporting Manager: {reporting_to}
Reason: {transfer_reason}

Your designation, salary, and all other terms remain unchanged.

Please complete handover of all pending tasks before {transfer_date}.

Yours sincerely,
{company}",
                'email_subject' => 'Transfer Order — {company}',
                'email_body'    => "Dear {employee_name},

Please find your Transfer Order from {company} issued on {issue_date}.

You are being transferred to {transfer_to} effective {transfer_date}. Kindly ensure a smooth handover before the effective date.

Best regards,
HR Department
{company}",
            ),
            'relieving_letter' => array(
                'template' => "Dear {employee_name},

SUBJECT: Relieving Letter

This acknowledges the resignation of {employee_name} (Employee ID: {employee_id}), serving as {position} in the {department} department at {company} since {joining_date}.

{employee_name} has completed the required notice period of {notice_period} and is hereby relieved from duties effective {last_working_day}.

Clearance Status: {clearance_status}

We appreciate the contributions made during the tenure and wish all the best for the future.

Yours sincerely,
{company}",
                'email_subject' => 'Relieving Letter — {company}',
                'email_body'    => "Dear {employee_name},

Please find your Relieving Letter from {company}, confirming your last working day as {last_working_day}.

We truly value the time and contributions you made during your tenure. Wishing you great success ahead!

Warm regards,
HR Department
{company}",
            ),
            'noc' => array(
                'template' => "TO WHOM IT MAY CONCERN

NO OBJECTION CERTIFICATE

Date: {issue_date}

This certifies that {employee_name} (Employee ID: {employee_id}), CNIC No. {cnic}, is a permanent employee of {company} as {position} in {department} since {joining_date}.

PURPOSE: {noc_purpose}
ADDRESSED TO: {destination}
DURATION: {duration}

{company} has NO OBJECTION to {employee_name} pursuing the above purpose. The employee is in good standing and employment will continue uninterrupted.

This certificate is valid for 60 days from the date of issuance.

Yours faithfully,
{company}",
                'email_subject' => 'No Objection Certificate — {company}',
                'email_body'    => "Dear {employee_name},

Please find your No Objection Certificate (NOC) issued by {company} on {issue_date}.

This NOC has been prepared for {noc_purpose}. Kindly present it with your valid CNIC. It is valid for 60 days.

Best regards,
HR Department
{company}",
            ),
            'custom_letter' => array(
                'template' => "{custom_title}

Dear {employee_name},

{custom_content}

Employee Details:
Employee ID: {employee_id}
Position: {position}
Department: {department}
Date of Joining: {joining_date}

This letter is issued on {issue_date} for official use.

Yours sincerely,
{company}",
                'email_subject' => '{custom_title} — {company}',
                'email_body'    => "Dear {employee_name},

Please find attached your official letter from {company} issued on {issue_date}.

Title: {custom_title}

For questions or amendments, please contact HR.

Best regards,
HR Department
{company}",
            ),
        );

        foreach ( $templates as $slug => $data ) {
            if ( ! empty( $data['template'] ) ) {
                update_option( 'evda_letter_template_' . $slug, $data['template'] );
            }
            if ( ! empty( $data['email_subject'] ) ) {
                update_option( 'evda_email_subject_' . $slug, $data['email_subject'] );
            }
            if ( ! empty( $data['email_body'] ) ) {
                update_option( 'evda_email_body_' . $slug, $data['email_body'] );
            }
        }
    }
}
