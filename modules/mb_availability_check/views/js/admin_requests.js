/**
 * Admin requests page - copy to clipboard functionality
 */
$(document).ready(function() {
    // Prevent row click navigation from firing when interacting with inline controls
    $(document).on('click mousedown', '.mb-status-select, .mb-copy-url, .mb-copy-action, .mb-send-action', function(e) {
        e.stopPropagation();
    });
    // Copy URL button click
    $(document).on('click', '.mb-copy-action', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        var $row = $(this).closest('tr');
        var $input = $row.find('.mb-copy-url');
        
        if ($input.length) {
            $input.select();
            
            try {
                var successful = document.execCommand('copy');
                if (successful) {
                    // Show success feedback
                    var $btn = $(this);
                    var originalText = $btn.text();
                    $btn.html('<i class="icon-ok"></i> Copied!');
                    
                    setTimeout(function() {
                        $btn.html('<i class="icon-copy"></i> ' + originalText.replace('Copied!', '').trim());
                    }, 2000);
                } else {
                    alert('Copy failed. Please copy manually.');
                }
            } catch (err) {
                // Fallback for older browsers
                alert('Please copy the URL manually: Ctrl+C');
            }
            
            window.getSelection().removeAllRanges();
        }
    });
    
    // Also allow copying by clicking the input field directly
    $(document).on('click', '.mb-copy-url', function() {
        $(this).select();
    });
    
    // Helper: get query param by name
    function getQueryParam(name) {
        var match = RegExp('[?&]' + name + '=([^&]*)').exec(window.location.search);
        return match && decodeURIComponent(match[1].replace(/\+/g, ' '));
    }

    // Helper: escape HTML
    function htmlspecialchars(str) {
        var map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return str.replace(/[&<>"']/g, function(m) { return map[m]; });
    }

    // Simple toast
    function showToast(message, success) {
        var $t = $('#mb-admin-toast');
        if (!$t.length) {
            $t = $('<div id="mb-admin-toast" style="position:fixed;right:20px;top:20px;z-index:99999;padding:10px 14px;border-radius:4px;color:#fff;"></div>');
            $('body').append($t);
        }
        $t.stop(true).css('background', success ? '#28a745' : '#dc3545').text(message).fadeIn(200).delay(1800).fadeOut(400);
    }

    // Calculate admin URL from wrapper if provided, else fallback to current location
    var $mbWrapper = $('.mb-availability-requests-wrapper');
    var adminUrl = $mbWrapper.length ? $mbWrapper.data('admin-url') : window.location.href;
    // Ensure adminUrl contains token param; if not, append it from page context
    (function() {
        var tokenParam = 'token=';
        if (adminUrl.indexOf(tokenParam) === -1) {
            var token = (function() {
                var param = getQueryParam('token');
                if (param) return param;
                var $t = $('input[name="token"]');
                if ($t.length) return $t.val();
                var wrapperToken = $mbWrapper.length ? $mbWrapper.data('admin-url-token') : null;
                return wrapperToken || null;
            })();
            if (token) {
                adminUrl += (adminUrl.indexOf('?') === -1 ? '?' : '&') + tokenParam + encodeURIComponent(token);
            }
        }
    })();

    // AJAX status change
    $(document).on('change', '.mb-status-select', function(e) {
        // Stop the change event from bubbling to row click handlers
        e.stopPropagation();
        var $sel = $(this);
        var id = $sel.data('id');
        var status = $sel.val();
        var token = getQueryParam('token') || $('input[name="token"]').val();

        var data = {
            id_request: id,
            status: status,
            ajax: 1,
            action: 'setStatus'
        };
        if (token) data.token = token;

        // Use full location (including token) to avoid posting to front controller base path
        var adminUrlForStatus = adminUrl;
        if (adminUrlForStatus.indexOf('action=') === -1) {
            adminUrlForStatus += (adminUrlForStatus.indexOf('?') === -1 ? '?' : '&') + 'action=setStatus';
        }
        console.log('Status AJAX posting to', adminUrlForStatus, 'data', data);
        $.ajax({
            url: adminUrlForStatus,
            method: 'POST',
            data: data,
            dataType: 'json'
        })
        .done(function(json) {
            if (json && json.success) {
                showToast('Status updated', true);
            } else {
                showToast('Could not update status', false);
            }
        })
        .fail(function(xhr, status, error) {
            // Log response for debugging
            if (window.console && window.console.log) {
                console.log('Status update AJAX failed:', status, error, xhr.responseText);
            }
            var msg = 'Request failed (' + status + ')';
            if (xhr && xhr.responseText) {
                var text = xhr.responseText.replace(/<[^>]*>?/gm, '').substr(0, 200);
                msg += ': ' + text;
            }
            showToast(msg, false);
        });
    });

    // Send modal functionality
    $(document).on('click', '.mb-send-action', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        var id = $(this).data('id');
        if (!id) return;
        
        // Set the request ID in the modal
        $('#modal_request_id').val(id);
        
        // Reset to Client OK
        $('#client_ok').prop('checked', true);
        $('#client_no').prop('checked', false);
        
        // Hide custom message
        $('#custom_message_group').hide();
        $('#custom_message').val('');
        
        // Reset notification email to default
        $('#notification_email').val($('#notification_email').data('default') || '');
        
        // Show the modal
        $('#sendModal').modal('show');
    });
    
    // Handle client response radio buttons
    $(document).on('change', 'input[name="client_response"]', function() {
        if ($(this).val() === 'no') {
            $('#custom_message_group').show();
        } else {
            $('#custom_message_group').hide();
            $('#custom_message').val('');
        }
    });
    
    // Preview functionality
    $(document).on('click', '#previewBtn', function(e) {
        e.preventDefault();
        
        var idRequest = $('#modal_request_id').val();
        var clientResponse = $('input[name="client_response"]:checked').val();
        var customMessage = (clientResponse === 'no') ? $('#custom_message').val() : '';
        var notificationEmail = $('#notification_email').val();
        
        if (!idRequest) return;
        
        // Show loading in preview modal
        $('#previewContent').html('<div class="text-center"><i class="icon-spinner icon-spin icon-2x"></i><p>Loading preview...</p></div>');
        $('#previewModal').modal('show');
        
        // Get preview data
        var token = getQueryParam('token') || $('input[name="token"]').val();
        
        var data = {
            ajax: 1,
            action: 'previewEmail',
            id_request: idRequest,
            client_response: clientResponse,
            custom_message: customMessage,
            notification_email: notificationEmail
        };
        if (token) data.token = token;
        
        console.log('Preview AJAX posting to', adminUrl, 'data', data);
        $.post(adminUrl, data)
            .done(function(resp) {
                try {
                    var json = (typeof resp === 'string') ? JSON.parse(resp) : resp;
                    if (json && json.success) {
                        var html = '<div class="email-preview">';
                        html += '<h4>Subject: ' + htmlspecialchars(json.subject) + '</h4>';
                        html += '<div class="email-content">';
                        html += json.html_content;
                        html += '</div>';
                        html += '<hr>';
                        html += '<h5>Text Version:</h5>';
                        html += '<pre>' + htmlspecialchars(json.text_content) + '</pre>';
                        html += '</div>';
                        $('#previewContent').html(html);
                    } else {
                        $('#previewContent').html('<div class="alert alert-danger">Error: ' + (json.error || 'Unknown error') + '</div>');
                    }
                } catch (e) {
                    // Show raw response for debugging
                    $('#previewContent').html('<div class="alert alert-warning">Error parsing response. Raw response:<br><pre style="white-space: pre-wrap; word-break: break-all;">' + htmlspecialchars(resp) + '</pre></div>');
                }
            })
            .fail(function(xhr, status, error) {
                var detail = '';
                if (xhr && xhr.responseText) {
                    detail = '<br><pre style="white-space: pre-wrap; word-break: break-all;">' + htmlspecialchars(xhr.responseText.substr(0, 400)) + '</pre>';
                }
                $('#previewContent').html('<div class="alert alert-danger">AJAX Error: ' + status + ' - ' + error + detail + '</div>');
            });
    });
    
    // Handle send form submission via AJAX
    $(document).on('submit', '#sendForm', function(e) {
        e.preventDefault();
        
        var idRequest = $('#modal_request_id').val();
        var clientResponse = $('input[name="client_response"]:checked').val();
        var customMessage = (clientResponse === 'no') ? $('#custom_message').val() : '';
        var notificationEmail = $('#notification_email').val();
        
        if (!idRequest) {
            showToast('No request ID found', false);
            return;
        }
        
        // Disable the send button to prevent double submission
        $('#sendBtn').prop('disabled', true).html('<i class="icon-spinner icon-spin"></i> Sending...');
        
        var token = getQueryParam('token') || $('input[name="token"]').val();
        
        var data = {
            ajax: 1,
            action: 'resendMbAvailabilityRequests',
            id_request: idRequest,
            client_response: clientResponse,
            custom_message: customMessage,
            notification_email: notificationEmail
        };
        if (token) data.token = token;
        
        console.log('Resend/copy/send AJAX posting to', adminUrl, 'data', data);
        $.post(adminUrl, data)
            .done(function(resp) {
                try {
                    var json = (typeof resp === 'string') ? JSON.parse(resp) : resp;
                    if (json && json.success) {
                        showToast('Email sent successfully', true);
                        $('#sendModal').modal('hide');
                    } else {
                        showToast('Failed to send email: ' + (json.error || 'Unknown error'), false);
                    }
                } catch (e) {
                    // Show raw response for debugging
                    showToast('Error parsing response', false);
                    console.error('Raw response:', resp);
                }
            })
            .fail(function(xhr, status, error) {
                var msg = 'AJAX Error: ' + status + ' - ' + error;
                if (xhr && xhr.responseText) {
                    var short = xhr.responseText.replace(/<[^>]*>?/gm, '').substr(0, 200);
                    msg += ': ' + short;
                }
                showToast(msg, false);
            })
            .always(function() {
                // Re-enable the send button
                $('#sendBtn').prop('disabled', false).html('<i class="icon-envelope"></i> Send');
            });
    });
});

// Use a capture-phase DOM listener to prevent row-level navigation for rows inside our module wrapper.
// This listener runs before PrestaShop's delegated row click handlers and prevents navigation when clicking
// non-action elements (so inline selects and inputs can be used without navigating away).
document.addEventListener('click', function(e) {
    try {
        var tr = e.target.closest('.mb-availability-requests-wrapper table.table tbody tr');
        if (!tr) return; // Not our table row

        // If the click is on a link or form control, allow it
        if (e.target.closest('a, button, input, select, textarea')) return;

        // Prevent click from navigating
        e.stopImmediatePropagation();
        e.preventDefault();
    } catch (err) {
        // Ignore errors in older browsers
    }
}, true);
