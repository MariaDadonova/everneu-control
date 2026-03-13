jQuery(document).ready(function($) {
    // When the form selection changes
    $('select[name="lts_supported_forms[]"]').on('change', function() {


        var selectedForms = $(this).val(); // Get selected forms' IDs

        if (selectedForms.length > 0) {

            //update saving message
            showMessage('Loading...');

            // Clear existing notifications list
            $('#lts-notifications-wrapper').empty();

            var divArray = [];

            // Loop through each selected form and fetch notifications
            selectedForms.forEach(function(formId) {
                $.ajax({
                    url: ajaxurl, // WordPress AJAX URL
                    method: 'POST',
                    data: {
                        action: 'lts_get_form_notifications', // Custom action name
                        form_id: formId,
                        _ajax_nonce: lts_ajax_object.nonce // Nonce for security
                    },
                    success: function(response) {
                        if (response.success) {

                            //update saving message
                            showMessage('');
console.dir(response.data);

                            var notifications = response.data.notifications;
                            var fields = response.data.fields;
                            var selected_fields = response.data.selected_fields;

                            // Create a dropdown for each form's notifications
                            if( notifications ) {

                                var notificationSelect = '<strong>' + response.data.form_name + '</strong>';
                                notificationSelect += '<label for="lts_notification_' + formId + '">Select Notification:</label>';
                                notificationSelect += '<select name="lts_form_notifications[' + formId + ']" id="lts_notification_' + formId + '">';

                                notifications.forEach(function (notification) {
                                    notificationSelect += '<option value="' + notification.id + '"';
                                    if( notification.selected ) {
                                        notificationSelect += ' selected ';
                                    }
                                    notificationSelect += '>' + notification.name + '</option>';
                                });
                                notificationSelect += '</select>';


                                // Add dropdowns for each lead field
                                var fieldSelect = function(fieldName, fieldLabel, formId) {
                                    var html = '<div class="field-wrap">';
                                    html += '<label for="lead_' + fieldName + '_' + formId + '">' + fieldLabel + ':</label>';
                                    html += '<select name="lts_form_fields[' + formId + '][' + fieldName + ']" id="lead_' + fieldName + '_' + formId + '">';
                                    html += '<option value="" selected>Not Set</option>'
                                    fields.forEach(function(field) {
                                        html += '<option value="' + field.id + '"';
                                        if( selected_fields[ formId ] && selected_fields[ formId ][ fieldName ] == field.id ) {
                                            html += ' selected ';
                                        }
                                        html += '>' + field.label + '</option>';
                                    });
                                    html += '</select></div><br>';
                                    return html;
                                };

                                // Generate dropdowns for lead fields
                                notificationSelect += '<div class="lead-fields">';
                                notificationSelect += fieldSelect('FullName','Full Name', formId);
                                notificationSelect += fieldSelect('FirstName', 'First Name', formId);
                                notificationSelect += fieldSelect('LastName', 'Last Name', formId);
                                notificationSelect += fieldSelect('Email', 'Email', formId);
                                notificationSelect += fieldSelect('Phone', 'Phone', formId);
                                notificationSelect += fieldSelect('Company', 'Company', formId);
                                notificationSelect += fieldSelect('OriginalMessage', 'Message', formId);
                                notificationSelect += '</div>';

                                // Create the div and add it to the array
                                var div = $('<div>', {
                                    class: 'form-notification-wrap',
                                    'data-form-name': response.data.form_name // Assuming form_name is returned in the AJAX response
                                }).append(notificationSelect);

                                divArray.push(div);

                                // Sort the divArray by data-form-name
                                divArray.sort(function(a, b) {
                                    var nameA = $(a).data('form-name').toLowerCase();
                                    var nameB = $(b).data('form-name').toLowerCase();
                                    if (nameA < nameB) return -1;
                                    if (nameA > nameB) return 1;
                                    return 0;
                                });

                                // Empty and re-append the sorted divs to the wrapper
                                $('#lts-notifications-wrapper').empty().append(divArray);

                            } else {
                                $('#lts-notifications-wrapper').append( "<div></div>" ).text("No Notifications. Please create a Notification.");
                            }



                            // Append the notification dropdown to the wrapper
                            // $('#lts-notifications-wrapper').append(notificationSelect);
                        } else {
                            showMessage('Error retrieving notifications for Form ID ' + formId, false );
                            alert('Error retrieving notifications for Form ID ' + formId);
                        }
                    },
                    error: function(jqXHR, textStatus, errorThrown) {
                        showMessage('Error retrieving notifications for Form ID ' + formId, false );

                        // Handle error response
                        console.error('Error:', textStatus, errorThrown);

                        if (jqXHR.status === 404) {
                            // Handle 404 Not Found
                        } else if (jqXHR.status === 500) {
                            // Handle 500 Internal Server Error
                        } else {
                            // Handle other errors
                        }
                    }
                });
            });
        } else {
            // No forms selected, clear the notifications section
            $('#lts-notifications-wrapper').empty();
        }
    });


    ///
    /// LTS Connection
    ///
    $('.lts-connect').click(function() {
        //update saving message
        showMessage('Connecting...');

        let clientUrl = $('#client-url').val();
        let clientId = $('#client-name').val();
        let clientName = $('#custom-client-name').val();

        $.ajax({
            url: ajaxurl, // WordPress AJAX URL
            method: 'POST',
            data: {
                action: 'lts_connect', // Custom action name
                client_name: clientName,
                client_id: clientId,
                client_url: clientUrl,
                _ajax_nonce: lts_ajax_object.nonce // Nonce for security
            },
            success: function(response) {
                if (response.success) {

                    //update saving message
                    showMessage('Connected! Please wait, refreshing page...', true );
                    console.dir(response.data);

                    // update UI
                    setTimeout(function() {
                        location.reload();
                    }, 5000);

                } else {
                    showMessage('Error: ' + response.data, false );
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                showMessage('Error connecting to LTS ', false );

                // Handle error response
                console.error('Error:', textStatus, errorThrown);

                if (jqXHR.status === 404) {
                    // Handle 404 Not Found
                } else if (jqXHR.status === 500) {
                    // Handle 500 Internal Server Error
                } else {
                    // Handle other errors
                }
            }
        });
    });

    $('.lts-disconnect').click(function() {

        if( confirm('Are you sure you want to DISCONNECT from LTS?' ) ) {

            //update saving message
            showMessage('Disconnecting...');

            $.ajax({
                url: ajaxurl, // WordPress AJAX URL
                method: 'POST',
                data: {
                    action: 'lts_disconnect',
                    _ajax_nonce: lts_ajax_object.nonce // Nonce for security
                },
                success: function (response) {
                    if (response.success) {

                        // update saving message
                        showMessage('Disconnected. Please wait, refreshing page...', true );

                        // update UI
                        setTimeout(function() {
                            location.reload();
                        }, 5000);

                    } else {
                        showMessage('Error: ' + response.data, false );
                    }
                },
                error: function (jqXHR, textStatus, errorThrown) {
                    showMessage('Error retrieving notifications for Form ID ' + formId, false);

                    // Handle error response
                    console.error('Error:', textStatus, errorThrown);

                    if (jqXHR.status === 404) {
                        // Handle 404 Not Found
                    } else if (jqXHR.status === 500) {
                        // Handle 500 Internal Server Error
                    } else {
                        // Handle other errors
                    }
                }
            });

        } // end if confirm disconnect

    });

    function showMessage( msg, success = null ) {

        // if msg empty, hide display
        if( ! msg ) {
            $('#lts-message-wrap').fadeTo(0.1, 0);

        } else {

            // color success, error
            if ( success === null ) {
                $('#lts-message-wrap').removeClass('notice-error');
                $('#lts-message-wrap').removeClass('notice-success');
            } else if ( success ) {
                $('#lts-message-wrap').removeClass('notice-error');
                $('#lts-message-wrap').addClass('notice-success');
            } else {
                $('#lts-message-wrap').removeClass('notice-success');
                $('#lts-message-wrap').addClass('notice-error');
            }

            // display message
            $('#saving-message').text( msg );
            $('#lts-message-wrap').fadeTo(0.1,1);
        }
        
    }
});

function checkCustomClientName(select) {
    var customNameInput = document.getElementById('custom-client-name-wrap');
    var UrlInput = document.getElementById('client-url-wrap');
    if (select.value === 'lts-custom') {
        customNameInput.style.display = 'block';
        UrlInput.style.display = 'block';
    } else {
        customNameInput.style.display = 'none';
        UrlInput.style.display = 'none';
    }
}
