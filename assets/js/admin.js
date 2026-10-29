/* global EV, wp, ajaxurl */
(function ($) {
    'use strict';

    // =========================================================================
    // Tab navigation (Settings + Employee form pages)
    // =========================================================================
    $(document).on('click', '.ev-tab', function () {
        $('.ev-tab').removeClass('active');
        $('.ev-tab-panel').removeClass('active');
        $(this).addClass('active');
        $('#tab-' + $(this).data('tab')).addClass('active');
    });

    // =========================================================================
    // Media uploader - Photo URL field
    // =========================================================================
    $(document).on('click', '#ev-upload-photo', function (e) {
        e.preventDefault();
        var frame = wp.media({ title: 'Select Employee Photo', button: { text: 'Use this photo' }, multiple: false });
        frame.on('select', function () {
            var att = frame.state().get('selection').first().toJSON();
            $('#ev-photo-url').val(att.url);
        });
        frame.open();
    });

    $(document).on('click', '#ev-upload-logo', function (e) {
        e.preventDefault();
        var frame = wp.media({ title: 'Select Company Logo', button: { text: 'Use this logo' }, multiple: false });
        frame.on('select', function () {
            var att = frame.state().get('selection').first().toJSON();
            $('#ev-settings-logo-id').val(att.id);
            $('#ev-logo-preview').remove();
            $('#ev-upload-logo').after('<img id="ev-logo-preview" src="' + att.url + '" style="max-height:60px;display:block;margin-top:8px;">');
        });
        frame.open();
    });

    // =========================================================================
    // Status select - toggle intern / former-employee date rows
    // =========================================================================
    function evToggleStatusFields() {
        var v = $('#ev-status-select').val();
        $('.ev-row-intern').toggle(v === 'Intern');
        $('.ev-row-former').toggle(v === 'Former Employee');
    }
    $(document).on('change', '#ev-status-select', evToggleStatusFields);
    evToggleStatusFields();

    // =========================================================================
    // Save employee form via AJAX
    // =========================================================================
    $(document).on('submit', '#ev-employee-form', function (e) {
        e.preventDefault();
        var $form   = $(this);
        var $result = $('#ev-save-result');
        var $btn    = $form.find('button[type="submit"]');

        $btn.prop('disabled', true).text('Saving...');
        $result.css('color', '#666').text('');

        var data = {
            action:          'evda_save_employee',
            nonce:           EVDA.nonce,
            evda_emp_id:     $form.find('[name="evda_emp_id"]').val(),
            employee_id:     $form.find('[name="employee_id"]').val(),
            full_name:       $form.find('[name="full_name"]').val(),
            father_name:     $form.find('[name="father_name"]').val(),
            cnic:            $form.find('[name="cnic"]').val(),
            email:           $form.find('[name="email"]').val(),
            position:        $form.find('[name="position"]').val(),
            department:      $form.find('[name="department"]').val(),
            joining_date:    $form.find('[name="joining_date"]').val(),
            leaving_date:    $form.find('[name="leaving_date"]').val(),
            intern_start:    $form.find('[name="intern_start"]').val(),
            intern_end:      $form.find('[name="intern_end"]').val(),
            status:          $form.find('[name="status"]').val(),
            photo_url:       $form.find('[name="photo_url"]').val(),
            tasks_completed: $form.find('[name="tasks_completed"]').val(),
        };

        $form.find('[name^="evda_cf_val"]').each(function () {
            data[$(this).attr('name')] = $(this).val();
        });
        $form.find('[name^="evda_cf_show"]').each(function () {
            data[$(this).attr('name')] = $(this).is(':checked') ? '1' : '';
        });

        $.post(EVDA.ajax_url, data, function (res) {
            $btn.prop('disabled', false).text('Save Employee');
            if (res.success) {
                $result.css('color', 'green').text('Saved! ' + res.data.message);
                setTimeout(function () {
                    window.location.href = window.location.href.split('&action')[0];
                }, 1200);
            } else {
                $result.css('color', 'red').text('Error: ' + res.data);
            }
        }).fail(function (xhr) {
            $btn.prop('disabled', false).text('Save Employee');
            $result.css('color', 'red').text('Server error (HTTP ' + xhr.status + '). Check WP debug log.');
        });
    });

    // =========================================================================
    // Delete employee
    // =========================================================================
    $(document).on('click', '.ev-delete-emp', function () {
        if (!confirm('Delete this employee? This cannot be undone.')) return;
        var id  = $(this).data('id');
        var row = $(this).closest('tr');
        $.post(EVDA.ajax_url, { action: 'evda_delete_employee', id: id, nonce: EVDA.nonce }, function (res) {
            if (res.success) row.fadeOut();
        });
    });

    // =========================================================================
    // Generate document modal
    // =========================================================================
    $(document).on('click', '.ev-gen-doc', function () {
        var empid = $(this).data('empid');
        var type  = $(this).data('type');

        $('#ev-modal-empid').val(empid);
        $('#ev-modal-type').val(type);
        $('#ev-modal-result').html('');

        var today = new Date().toISOString().split('T')[0];
        $('#ev-modal-leaving').val(today);
        $('#ev-modal-intern-end').val(today);

        if (type === 'internship') {
            $('#ev-modal-title').html('Generate Internship Completion Letter');
            $('#ev-modal-desc').html('This will generate an <strong>Internship Completion Letter</strong> for <strong>' + empid + '</strong>.<br>Please confirm or adjust the internship dates below.');
            $('#ev-modal-intern-wrap').show();
            $('#ev-modal-leaving-wrap').hide();
        } else {
            $('#ev-modal-title').html('Generate Experience Letter');
            $('#ev-modal-desc').html('This will generate an <strong>Experience Letter</strong> for <strong>' + empid + '</strong>.<br>Please enter the employee\'s last working day.');
            $('#ev-modal-intern-wrap').hide();
            $('#ev-modal-leaving-wrap').show();
        }

        $('#ev-gen-doc-modal').show();
    });

    $(document).on('click', '#ev-modal-cancel', function () {
        $('#ev-gen-doc-modal').hide();
    });

    $(document).on('click', '#ev-gen-doc-modal', function (e) {
        if ($(e.target).is('#ev-gen-doc-modal')) $(this).hide();
    });

    $(document).on('click', '#ev-modal-confirm', function () {
        var empid       = $('#ev-modal-empid').val();
        var type        = $('#ev-modal-type').val();
        var leaving     = $('#ev-modal-leaving').val();
        var internStart = $('#ev-modal-intern-start').val();
        var internEnd   = $('#ev-modal-intern-end').val();
        var $btn        = $(this);

        $btn.prop('disabled', true).text('Generating...');
        $('#ev-modal-result').html('<em style="color:#666;">Please wait, generating letter...</em>');

        $.post(EVDA.ajax_url, {
            action:       'evda_generate_document',
            employee_id:  empid,
            doc_type:     type,
            leaving_date: leaving,
            intern_start: internStart,
            intern_end:   internEnd,
            nonce:        EVDA.nonce
        }, function (res) {
            $btn.prop('disabled', false).text('Generate Letter');
            if (res.success) {
                var d = res.data;
                $('#ev-modal-result').html(
                    '<div style="background:#f0fff4;border:1px solid #28a745;border-radius:6px;padding:16px;margin-top:4px;">' +
                    '<div style="color:#155724;font-weight:700;font-size:15px;margin-bottom:10px;">Letter Generated Successfully!</div>' +
                    '<div style="margin-bottom:8px;"><strong>Verification ID:</strong><br>' +
                    '<code style="font-size:14px;background:#e9ecef;padding:3px 8px;border-radius:3px;">' + d.verification_id + '</code></div>' +
                    '<div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:10px;">' +
                    '<a href="' + d.pdf_url + '" target="_blank" class="button button-primary">Open Letter</a>' +
                    '<a href="' + d.verify_url + '" target="_blank" class="button">Verify Link</a>' +
                    '</div></div>'
                );
            } else {
                $('#ev-modal-result').html('<div style="color:red;padding:10px;background:#fff5f5;border:1px solid #f5c6cb;border-radius:6px;">Error: ' + res.data + '</div>');
            }
        }).fail(function (xhr) {
            $btn.prop('disabled', false).text('Generate Letter');
            $('#ev-modal-result').html('<div style="color:red;">Server error (HTTP ' + xhr.status + ')</div>');
        });
    });

    // =========================================================================
    // Revoke document
    // =========================================================================
    $(document).on('click', '.ev-revoke-doc', function () {
        if (!confirm('Revoke this document? It will show as invalid when verified.')) return;
        var vid = $(this).data('vid');
        var btn = $(this);
        $.post(EVDA.ajax_url, { action: 'evda_revoke_document', verification_id: vid, nonce: EVDA.nonce }, function (res) {
            if (res.success) {
                btn.closest('tr').find('.ev-status').removeClass('ev-status-active').addClass('ev-status-invalid').text('Revoked');
                btn.remove();
            }
        });
    });

    // =========================================================================
    // Delete document
    // =========================================================================
    $(document).on('click', '.ev-delete-doc', function () {
        var label = $(this).data('label') || 'this document';
        if (!confirm('Delete ' + label + '?\n\nThis will permanently remove the letter file and record.')) return;
        var id   = $(this).data('id');
        var $btn = $(this);
        $btn.prop('disabled', true).text('Deleting...');
        $.post(EVDA.ajax_url, { action: 'evda_delete_document', id: id, nonce: EVDA.nonce }, function (res) {
            if (res.success) {
                location.reload();
            } else {
                $btn.prop('disabled', false).text('Delete');
                alert('Delete failed: ' + res.data);
            }
        });
    });

    // =========================================================================
    // CSV Import (employees page)
    // =========================================================================
    $(document).on('click', '#ev-import-csv-btn', function () {
        var file = $('#ev-csv-file')[0].files[0];
        if (!file) { alert('Please select a CSV file.'); return; }
        var fd = new FormData();
        fd.append('action', 'evda_import_csv');
        fd.append('nonce', EVDA.nonce);
        fd.append('csv_file', file);
        $('#ev-import-result').text('Importing...');
        $.ajax({ url: EVDA.ajax_url, type: 'POST', data: fd, processData: false, contentType: false,
            success: function (res) {
                if (res.success) {
                    $('#ev-import-result').css('color', 'green').text('Imported: ' + res.data.success + ' | Errors: ' + res.data.errors);
                    setTimeout(function () { location.reload(); }, 1500);
                } else {
                    $('#ev-import-result').css('color', 'red').text('Error: ' + res.data);
                }
            }
        });
    });

    // =========================================================================
    // Test email button (settings page)
    // =========================================================================
    $(document).on('click', '#ev-send-test-email', function (e) {
        e.preventDefault();
        e.stopPropagation();
        var to   = $('#ev-test-email-addr').val().trim();
        var $btn = $(this);
        var $r   = $('#ev-test-email-result');
        if (!to) { $r.css('color', 'red').text('Please enter an email address.'); return; }
        $btn.prop('disabled', true).text('Sending...');
        $r.css('color', '#666').text('');
        $.ajax({
            url:  EVDA.ajax_url,
            type: 'POST',
            data: { action: 'evda_send_test_email', email: to, nonce: EVDA.nonce },
            success: function (res) {
                if (res.success) {
                    $r.css('color', 'green').text(res.data.message);
                } else {
                    $r.css('color', 'red').text(res.data || 'Unknown error');
                }
            },
            error: function (xhr, status, err) {
                $r.css('color', 'red').text('Request failed: ' + status + ' - ' + err);
            },
            complete: function () {
                $btn.prop('disabled', false).text('Send Test Email');
            }
        });
    });

    // =========================================================================
    // Manual import - single record (import page)
    // =========================================================================
    $(document).on('click', '#ev-imp-submit', function () {
        var $btn = $(this);
        var $r   = $('#ev-imp-result');
        $btn.prop('disabled', true).text('Importing...');
        $r.css('color', '#666').text('');
        $.post(EVDA.ajax_url, {
            action:          'evda_import_manual',
            nonce:           EVDA.nonce,
            employee_id:     $('#ev-imp-empid').val(),
            doc_type:        $('#ev-imp-type').val(),
            verification_id: $('#ev-imp-vid').val(),
            issue_date:      $('#ev-imp-date').val(),
            leaving_date:    $('#ev-imp-leaving').val(),
            notes:           $('#ev-imp-notes').val(),
        }, function (res) {
            $btn.prop('disabled', false).text('Import Record');
            if (res.success) {
                var d = res.data;
                $r.css('color', 'green').html(
                    d.message + '<br>' +
                    '<a href="' + d.verify_url + '" target="_blank">Open Verification Link</a>'
                );
                setTimeout(function () { location.reload(); }, 2000);
            } else {
                $r.css('color', 'red').text('Error: ' + res.data);
            }
        });
    });

    // =========================================================================
    // Manual import - bulk CSV (import page)
    // =========================================================================
    $(document).on('click', '#ev-imp-csv-btn', function () {
        var file = $('#ev-imp-csv')[0] && $('#ev-imp-csv')[0].files[0];
        if (!file) { alert('Please select a CSV file.'); return; }
        var fd = new FormData();
        fd.append('action', 'evda_import_manual_csv');
        fd.append('nonce', EVDA.nonce);
        fd.append('csv_file', file);
        var $r = $('#ev-imp-csv-result');
        $r.css('color', '#666').text('Importing...');
        $.ajax({ url: EVDA.ajax_url, type: 'POST', data: fd, processData: false, contentType: false,
            success: function (res) {
                if (res.success) {
                    var msg = 'Imported: ' + res.data.success + ' | Errors: ' + res.data.errors;
                    if (res.data.details && res.data.details.length) {
                        msg += '<br><small style="color:#a00;">' + res.data.details.join('<br>') + '</small>';
                    }
                    $r.css('color', 'green').html(msg);
                    setTimeout(function () { location.reload(); }, 2000);
                } else {
                    $r.css('color', 'red').text('Error: ' + res.data);
                }
            }
        });
    });

    // =========================================================================
    // Custom field rows (Settings page)
    // =========================================================================
    $(document).on('click', '#ev-cf-add', function () {
        $('#ev-cf-rows').append(
            '<tr class="ev-cf-row">' +
            '<td><input type="text" name="evda_cf_label[]" class="regular-text" placeholder="Field Label"></td>' +
            '<td><input type="text" name="evda_cf_key[]" class="regular-text" placeholder="field_key"></td>' +
            '<td><button type="button" class="button ev-cf-remove">Remove</button></td>' +
            '</tr>'
        );
    });
    $(document).on('click', '.ev-cf-remove', function () {
        $(this).closest('tr').remove();
    });

    // =========================================================================
    // Color picker sync (letter styling & email colors)
    // Uses data-target and data-color-name attributes - no inline oninput handlers
    // =========================================================================
    // Color swatch -> hex text (data-target based)
    $(document).on('input', '.evda-color-picker', function () {
        var targetId = $(this).data('target');
        if ( targetId ) { document.getElementById( targetId ).value = this.value; }
    });
    // Hex text -> color swatch (data-target based)
    $(document).on('input', '.evda-hex-text', function () {
        var targetId = $(this).data('target');
        if ( targetId && /^#[0-9a-f]{6}$/i.test( this.value ) ) {
            document.getElementById( targetId ).value = this.value;
        }
    });
    // Hex text -> color swatch (sibling based - email colors)
    $(document).on('input change', '.evda-hex-sibling', function () {
        var $swatch = $(this).prev('input[type="color"]');
        if ( $swatch.length && /^#[0-9a-f]{6}$/i.test( this.value ) ) {
            $swatch.val( this.value );
        }
        // Also handle data-color-name for settings page
        var colorName = $(this).data('color-name');
        if ( colorName ) {
            var $input = $('input[name="' + colorName + '"]');
            if ( $input.length ) { $input.val( this.value ); }
        }
    });

    // =========================================================================
    // Signature toggle + upload (Settings page)
    // =========================================================================
    $(document).on('change', '#ev-sig-toggle', function () {
        $('#ev-sig-fields').toggle(this.checked);
    });
    $(document).on('click', '#ev-upload-signature', function () {
        var frame = wp.media({
            title: 'Select Signature Image',
            button: { text: 'Use This Image' },
            multiple: false
        });
        frame.on('select', function () {
            var att = frame.state().get('selection').first().toJSON();
            $('#ev-sig-image-id').val(att.id);
            $('#ev-remove-signature').show();
            var img = (att.sizes && att.sizes.thumbnail) ? att.sizes.thumbnail.url : att.url;
            $('#ev-sig-preview').html('<img src="' + img + '" style="max-height:80px;max-width:200px;display:block;margin-top:4px;">');
        });
        frame.open();
    });

    // =========================================================================
    // DB repair button (admin notice)
    // =========================================================================
    $(document).on('click', '#ev-repair-btn', function () {
        var $btn = $(this);
        $btn.prop('disabled', true).text('Creating tables...');
        $.post(ajaxurl, { action: 'evda_repair_tables', nonce: EVDA.nonce }, function (res) {
            if (res.success) {
                $('#ev-repair-result').css('color', 'green').text(res.data.message);
                setTimeout(function () { location.reload(); }, 1200);
            } else {
                $btn.prop('disabled', false).text('Create Tables Now');
                $('#ev-repair-result').css('color', 'red').text(res.data);
            }
        });
    });


    // =========================================================================
    // Data migration button (dashboard page)
    // =========================================================================
    $(document).on('click', '#evda-run-migration', function () {
        var $btn    = $(this);
        var $result = $('#evda-migration-result');
        $btn.prop('disabled', true).text('Running...');
        $result.css('color', '#666').text('');
        var fd = new FormData();
        fd.append('action', 'evda_run_migration');
        fd.append('nonce', EVDA.migration_nonce);
        fetch(EVDA.ajax_url, { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                $btn.prop('disabled', false).text('Re-run Data Migration');
                if (res.success) {
                    $result.css('color', 'green').text(res.data.message);
                    setTimeout(function () { location.reload(); }, 1500);
                } else {
                    $result.css('color', 'red').text(res.data);
                }
            });
    });

    // =========================================================================
    // Export Employees CSV
    // =========================================================================
    $( document ).on( 'click', '#evda-export-csv-btn', function () {
        var $btn   = $( this );
        var search = $btn.data( 'search' )     || '';
        var dept   = $btn.data( 'department' ) || '';
        var status = $btn.data( 'status' )     || '';
        var nonce  = $btn.data( 'nonce' )      || '';

        $btn.prop( 'disabled', true ).text( '⏳ Exporting…' );

        $.post(
            EVDA.ajax_url,
            {
                action:     'evda_export_employees',
                nonce:      nonce,
                search:     search,
                department: dept,
                status:     status,
            },
            function ( res ) {
                $btn.prop( 'disabled', false ).html( '📥 Export CSV' );
                if ( ! res.success || ! res.data.rows || ! res.data.rows.length ) {
                    alert( 'No employees found to export.' );
                    return;
                }

                // Build CSV string.
                var csv = res.data.rows.map( function ( row ) {
                    return row.map( function ( cell ) {
                        var c = String( cell ).replace( /"/g, '""' );
                        return /[,"\n]/.test( c ) ? '"' + c + '"' : c;
                    } ).join( ',' );
                } ).join( '\n' );

                // Trigger browser download.
                var blob     = new Blob( [ '\uFEFF' + csv ], { type: 'text/csv;charset=utf-8;' } );
                var url      = URL.createObjectURL( blob );
                var today    = new Date().toISOString().slice( 0, 10 );
                var filename = 'employees-' + today + '.csv';
                var a        = document.createElement( 'a' );
                a.href       = url;
                a.download   = filename;
                document.body.appendChild( a );
                a.click();
                document.body.removeChild( a );
                URL.revokeObjectURL( url );
            }
        ).fail( function () {
            $btn.prop( 'disabled', false ).html( '📥 Export CSV' );
            alert( 'Export failed. Please try again.' );
        } );
    } );


    // =========================================================================
    // OTHER LETTERS — Dropdown toggle
    // =========================================================================
    $( document ).on( 'click', '.evda-dropdown-toggle', function ( e ) {
        e.preventDefault();
        e.stopPropagation();
        var $menu = $( this ).siblings( '.evda-dropdown-menu' );
        // Close all other open dropdowns first.
        $( '.evda-dropdown-menu' ).not( $menu ).removeClass( 'evda-open' );
        $menu.toggleClass( 'evda-open' );
    } );

    // Close dropdown when clicking outside.
    $( document ).on( 'click', function ( e ) {
        if ( ! $( e.target ).closest( '.evda-dropdown' ).length ) {
            $( '.evda-dropdown-menu' ).removeClass( 'evda-open' );
        }
    } );

    // =========================================================================
    // OTHER LETTERS — Generate Modal
    // =========================================================================
    var $olModal     = $( '#evda-other-letter-modal' );
    var olEmpId      = '';
    var olLetterType = '';

    function openOtherLetterModal( empId, empName, type, typeName, fields ) {
        olEmpId      = empId;
        olLetterType = type;

        $( '#evda-other-modal-title' ).text( typeName );
        $( '#evda-other-modal-emp' ).text(
            /* translators: employee name */
            '👤 ' + empName
        );
        $( '#evda-other-modal-result' ).text( '' ).removeClass( 'evda-msg-ok evda-msg-err' );
        $( '#evda-other-modal-confirm' ).prop( 'disabled', false ).text( 'Generate Letter' );

        // Build dynamic field inputs.
        var $fields = $( '#evda-other-modal-fields' );
        $fields.empty();

        if ( fields && fields.length ) {
            $.each( fields, function ( i, f ) {
                var req      = f.required ? '<span class="evda-ol-required">*</span>' : '';
                var inputHtml = '';
                if ( f.type === 'textarea' ) {
                    inputHtml = '<textarea name="extra_' + f.key + '" id="evda-ol-' + f.key + '" placeholder="' + f.label + '"></textarea>';
                } else {
                    inputHtml = '<input type="' + ( f.type || 'text' ) + '" name="extra_' + f.key + '" id="evda-ol-' + f.key + '" placeholder="' + f.label + '">';
                }
                $fields.append(
                    '<div class="evda-ol-field-row">' +
                    '<label for="evda-ol-' + f.key + '">' + f.label + req + '</label>' +
                    inputHtml +
                    '</div>'
                );
            } );
        } else {
            $fields.append( '<p style="color:#888;font-size:13px;margin-bottom:10px;">No additional information required for this letter type.</p>' );
        }

        $olModal.fadeIn( 150 );
    }

    $( document ).on( 'click', '.evda-other-letter-btn', function ( e ) {
        e.preventDefault();
        $( '.evda-dropdown-menu' ).removeClass( 'evda-open' );
        var $btn   = $( this );
        // Use .attr() not .data() — jQuery .data() auto-parses JSON which breaks JSON.parse().
        var fields = [];
        var rawFields = $btn.attr( 'data-fields' ) || '[]';
        try { fields = JSON.parse( rawFields ); } catch(ex) { fields = []; }
        openOtherLetterModal(
            $btn.data( 'emp' ),
            $btn.data( 'empname' ),
            $btn.data( 'type' ),
            $btn.data( 'typename' ),
            fields
        );
    } );

    $( document ).on( 'click', '#evda-other-modal-close, #evda-other-modal-cancel', function () {
        $olModal.fadeOut( 150 );
    } );

    $( document ).on( 'click', '#evda-other-modal-confirm', function () {
        var $btn = $( this );

        // Collect extra field values.
        var extraData = {};
        $( '#evda-other-modal-fields [id^="evda-ol-"]' ).each( function () {
            var key = this.id.replace( 'evda-ol-', '' );
            extraData[ key ] = $( this ).val();
        } );

        $btn.prop( 'disabled', true ).text( 'Generating…' );
        $( '#evda-other-modal-result' ).text( '' ).removeClass( 'evda-msg-ok evda-msg-err' );

        // Build POST data with extra_data array.
        var postData = {
            action:      'evda_generate_other_letter',
            nonce:       EVDA.nonce,
            employee_id: olEmpId,
            letter_type: olLetterType,
        };
        $.each( extraData, function ( k, v ) {
            postData[ 'extra_data[' + k + ']' ] = v;
        } );

        $.post( EVDA.ajax_url, postData, function ( res ) {
            $btn.prop( 'disabled', false ).text( 'Generate Letter' );
            if ( res.success ) {
                var $res = $( '#evda-other-modal-result' );
                $res.addClass( 'evda-msg-ok' ).html(
                    '✅ ' + res.data.message +
                    ( res.data.pdf_url
                        ? ' <a href="' + res.data.pdf_url + '" target="_blank" style="margin-left:8px;">📄 View Letter</a>'
                        : '' )
                );
                // Auto-close after 4s.
                setTimeout( function () { $olModal.fadeOut( 150 ); }, 4000 );
            } else {
                $( '#evda-other-modal-result' ).addClass( 'evda-msg-err' ).text( '❌ ' + res.data );
            }
        } ).fail( function () {
            $btn.prop( 'disabled', false ).text( 'Generate Letter' );
            $( '#evda-other-modal-result' ).addClass( 'evda-msg-err' ).text( '❌ Request failed. Please try again.' );
        } );
    } );

    // =========================================================================
    // LETTER TYPE EDITOR MODAL (Settings → Other Letters)
    // =========================================================================
    function openLtModal( ltData ) {
        var isEdit = !! ( ltData && ltData.slug );
        $( '#evda-lt-is-edit' ).val( isEdit ? '1' : '0' );
        $( '#evda-lt-modal-heading' ).text( isEdit ? '✏️ Edit Letter Type' : '➕ Add New Letter Type' );
        $( '#evda-lt-save-result' ).text( '' ).removeClass( 'evda-msg-ok evda-msg-err' );
        $( '#evda-lt-save-btn' ).prop( 'disabled', false );

        if ( isEdit && ltData ) {
            $( '#evda-lt-name' ).val( ltData.name || '' );
            $( '#evda-lt-slug' ).val( ltData.slug || '' ).prop( 'readonly', true );
            $( '#evda-lt-description' ).val( ltData.description || '' );
            $( '#evda-lt-status' ).val( ltData.status || 'active' );
            $( '#evda-lt-allow-multiple' ).prop( 'checked', !! ltData.allow_multiple );
            $( '#evda-lt-email-subject' ).val( ltData.email_subject || '' );
            $( '#evda-lt-email-body' ).val( ltData.email_body || '' );

            // Fetch template via AJAX.
            $.post( EVDA.ajax_url, {
                action:    'evda_get_lt_data',
                nonce:     EVDA.nonce,
                slug:      ltData.slug,
            }, function ( res ) {
                if ( res.success ) {
                    $( '#evda-lt-template' ).val( res.data.template || '' );
                    $( '#evda-lt-email-subject' ).val( res.data.email_subject || ltData.email_subject || '' );
                    $( '#evda-lt-email-body' ).val( res.data.email_body || ltData.email_body || '' );
                }
            } );

            // Populate extra fields.
            renderEfRows( ltData.extra_fields || [] );
        } else {
            $( '#evda-lt-name, #evda-lt-description, #evda-lt-template, #evda-lt-email-subject, #evda-lt-email-body' ).val( '' );
            $( '#evda-lt-slug' ).val( '' ).prop( 'readonly', false );
            $( '#evda-lt-status' ).val( 'active' );
            $( '#evda-lt-allow-multiple' ).prop( 'checked', true );
            renderEfRows( [] );
        }

        $( '#evda-lt-modal' ).fadeIn( 150 );
    }

    function renderEfRows( fields ) {
        var $tbody = $( '#evda-lt-ef-rows' );
        $tbody.empty();
        if ( fields && fields.length ) {
            $.each( fields, function ( i, f ) {
                $tbody.append( buildEfRow( f ) );
            } );
        }
    }

    function buildEfRow( f ) {
        f = f || {};
        return $( '<tr>' ).append(
            $( '<td>' ).append( $( '<input type="text" class="regular-text" name="ef_key[]">' ).val( f.key || '' ).attr( 'placeholder', 'salary' ) ),
            $( '<td>' ).append( $( '<input type="text" class="regular-text" name="ef_label[]">' ).val( f.label || '' ).attr( 'placeholder', 'Monthly Salary' ) ),
            $( '<td>' ).append(
                $( '<select name="ef_type[]">' ).append(
                    [ 'text', 'textarea', 'number', 'date', 'email' ].map( function ( t ) {
                        return $( '<option>' ).val( t ).text( t ).prop( 'selected', ( f.type || 'text' ) === t );
                    } )
                )
            ),
            $( '<td style="text-align:center;">' ).append(
                $( '<input type="checkbox" name="ef_required[]" value="1">' ).prop( 'checked', !! f.required )
            ),
            $( '<td>' ).append(
                $( '<button type="button" class="button button-small evda-ef-remove-btn">🗑</button>' ).on( 'click', function () {
                    $( this ).closest( 'tr' ).remove();
                } )
            )
        );
    }

    // Auto-generate slug from name.
    $( document ).on( 'input', '#evda-lt-name', function () {
        if ( $( '#evda-lt-is-edit' ).val() === '0' ) {
            var slug = $( this ).val().toLowerCase()
                .replace( /[^a-z0-9]+/g, '_' )
                .replace( /^_+|_+$/g, '' );
            $( '#evda-lt-slug' ).val( slug );
        }
    } );

    $( document ).on( 'click', '#evda-lt-add-ef', function () {
        $( '#evda-lt-ef-rows' ).append( buildEfRow( {} ) );
    } );

    $( document ).on( 'click', '#evda-add-letter-type-btn', function () {
        openLtModal( null ); // null = Add New mode
    } );

    $( document ).on( 'click', '.evda-edit-lt-btn', function () {
        // Use .attr() not .data() — jQuery .data() auto-parses JSON which breaks JSON.parse().
        var ltData = {};
        var rawLt = $( this ).attr( 'data-lt' ) || '{}';
        try { ltData = JSON.parse( rawLt ); } catch(ex) { ltData = {}; }
        openLtModal( ltData );
    } );

    $( document ).on( 'click', '#evda-lt-modal-close, #evda-lt-modal-cancel', function () {
        $( '#evda-lt-modal' ).fadeOut( 150 );
    } );

    // Save letter type.
    $( document ).on( 'click', '#evda-lt-save-btn', function () {
        var $btn = $( this );
        $btn.prop( 'disabled', true ).text( 'Saving…' );
        $( '#evda-lt-save-result' ).text( '' ).removeClass( 'evda-msg-ok evda-msg-err' );

        // Collect extra field arrays.
        var efKeys     = [];
        var efLabels   = [];
        var efTypes    = [];
        var efRequired = [];
        $( '#evda-lt-ef-rows tr' ).each( function () {
            var $tr = $( this );
            efKeys.push( $tr.find( '[name="ef_key[]"]' ).val() );
            efLabels.push( $tr.find( '[name="ef_label[]"]' ).val() );
            efTypes.push( $tr.find( '[name="ef_type[]"]' ).val() );
            efRequired.push( $tr.find( '[name="ef_required[]"]' ).is( ':checked' ) ? '1' : '0' );
        } );

        var postData = {
            action:         'evda_save_letter_type',
            nonce:          EVDA.nonce,
            slug:           $( '#evda-lt-slug' ).val(),
            name:           $( '#evda-lt-name' ).val(),
            description:    $( '#evda-lt-description' ).val(),
            status:         $( '#evda-lt-status' ).val(),
            allow_multiple: $( '#evda-lt-allow-multiple' ).is( ':checked' ) ? '1' : '0',
            template:       $( '#evda-lt-template' ).val(),
            email_subject:  $( '#evda-lt-email-subject' ).val(),
            email_body:     $( '#evda-lt-email-body' ).val(),
            is_edit:        $( '#evda-lt-is-edit' ).val(),
        };
        $.each( efKeys,     function ( i, v ) { postData[ 'ef_key['     + i + ']' ] = v; } );
        $.each( efLabels,   function ( i, v ) { postData[ 'ef_label['   + i + ']' ] = v; } );
        $.each( efTypes,    function ( i, v ) { postData[ 'ef_type['    + i + ']' ] = v; } );
        $.each( efRequired, function ( i, v ) { postData[ 'ef_required[' + i + ']' ] = v; } );

        $.post( EVDA.ajax_url, postData, function ( res ) {
            $btn.prop( 'disabled', false ).text( '💾 Save Letter Type' );
            if ( res.success ) {
                $( '#evda-lt-save-result' ).addClass( 'evda-msg-ok' ).text( '✅ ' + res.data.message );
                // Reload to reflect new/updated type in the table.
                setTimeout( function () { location.reload(); }, 1200 );
            } else {
                $( '#evda-lt-save-result' ).addClass( 'evda-msg-err' ).text( '❌ ' + res.data );
            }
        } ).fail( function () {
            $btn.prop( 'disabled', false ).text( '💾 Save Letter Type' );
            $( '#evda-lt-save-result' ).addClass( 'evda-msg-err' ).text( '❌ Request failed.' );
        } );
    } );

    // Delete letter type.
    $( document ).on( 'click', '.evda-delete-lt-btn', function () {
        var slug = $( this ).data( 'slug' );
        var name = $( this ).data( 'name' );
        if ( ! window.confirm( 'Delete "' + name + '"? This cannot be undone.' ) ) return;

        $.post( EVDA.ajax_url, {
            action: 'evda_delete_letter_type',
            nonce:  EVDA.nonce,
            slug:   slug,
        }, function ( res ) {
            if ( res.success ) {
                $( '#evda-lt-row-' + slug ).fadeOut( 300, function () { $( this ).remove(); } );
            } else {
                alert( res.data );
            }
        } );
    } );


    // =========================================================================
    // RESET LETTER TEMPLATES TO PROFESSIONAL DEFAULTS
    // =========================================================================
    $( document ).on( 'click', '#evda-reseed-templates-btn', function () {
        if ( ! window.confirm(
            'This will reset ALL letter templates and email content to the professional HR defaults.\n\n' +
            'Your custom letter types, extra fields, and settings will NOT be affected.\n\n' +
            'Are you sure you want to continue?'
        ) ) {
            return;
        }
        var $btn = $( this );
        $btn.prop( 'disabled', true ).text( '⏳ Resetting…' );

        $.post( EVDA.ajax_url, {
            action: 'evda_reseed_lt_templates',
            nonce:  EVDA.nonce,
        }, function ( res ) {
            $btn.prop( 'disabled', false ).html( '🔄 Reset Templates to Default' );
            if ( res.success ) {
                alert( '✅ ' + res.data.message );
            } else {
                alert( '❌ ' + res.data );
            }
        } ).fail( function () {
            $btn.prop( 'disabled', false ).html( '🔄 Reset Templates to Default' );
            alert( '❌ Request failed. Please try again.' );
        } );
    } );


})(jQuery);
