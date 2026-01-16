<!-- Send Modal -->
<div class="modal fade" id="sendModal" tabindex="-1" role="dialog" aria-labelledby="sendModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title" id="sendModalLabel">{$send_modal_title}</h4>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="sendForm">
                <div class="modal-body">
                    <div class="form-group">
                        <label>{$send_modal_client_response_label}</label>
                        <div class="radio">
                            <label>
                                <input type="radio" name="client_response" id="client_ok" value="ok" checked>
                                {$send_modal_client_ok_label}
                            </label>
                        </div>
                        <div class="radio">
                            <label>
                                <input type="radio" name="client_response" id="client_no" value="no">
                                {$send_modal_client_no_label}
                            </label>
                        </div>
                    </div>
                    <div class="form-group" id="custom_message_group" style="display: none;">
                        <label for="custom_message">{$send_modal_message_label}</label>
                        <textarea
                            class="form-control"
                            id="custom_message"
                            name="custom_message"
                            rows="4"
                            placeholder="{$send_modal_placeholder}"
                        ></textarea>
                    </div>
                    <div class="form-group">
                        <label for="notification_email">{$send_modal_notification_email_label}</label>
                        <input type="email" class="form-control" id="notification_email" name="notification_email" value="{$send_modal_default_notification_email}" data-default="{$send_modal_default_notification_email}" placeholder="{$send_modal_notification_email_placeholder}">
                        <small class="form-text text-muted">{$send_modal_notification_email_help}</small>
                    </div>
                    <input type="hidden" name="id_request" id="modal_request_id" value="">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-info" id="previewBtn">
                        <i class="icon-eye"></i> {$send_modal_preview_button}
                    </button>
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">{$send_modal_cancel_button}</button>
                    <button type="submit" class="btn btn-primary" id="sendBtn">
                        <i class="icon-envelope"></i> {$send_modal_send_button}
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Preview Modal -->
<div class="modal fade" id="previewModal" tabindex="-1" role="dialog" aria-labelledby="previewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title" id="previewModalLabel">{$preview_modal_title}</h4>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body" id="previewContent">
                <!-- Preview content will be loaded here -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">{$preview_modal_close_button}</button>
            </div>
        </div>
    </div>
</div>

<!-- Preview Modal -->
<div class="modal fade" id="previewModal" tabindex="-1" role="dialog" aria-labelledby="previewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title" id="previewModalLabel">{$send_modal_preview_title}</h4>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div id="previewContent" style="max-height: 600px; overflow-y: auto;">
                    <div class="text-center">
                        <i class="icon-spinner icon-spin icon-2x"></i>
                        <p>{$send_modal_loading_preview}</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">{$send_modal_close_button}</button>
            </div>
        </div>
    </div>
</div>

<style>
.email-preview {
    font-family: Arial, sans-serif;
}
.email-preview h4 {
    color: #333;
    border-bottom: 1px solid #ddd;
    padding-bottom: 10px;
    margin-bottom: 20px;
}
.email-preview .email-content {
    border: 1px solid #ddd;
    padding: 20px;
    background: #fff;
    margin-bottom: 20px;
    border-radius: 4px;
}
.email-preview pre {
    background: #f8f8f8;
    padding: 15px;
    border: 1px solid #ddd;
    border-radius: 4px;
    white-space: pre-wrap;
    word-wrap: break-word;
    font-family: monospace;
    max-height: 300px;
    overflow-y: auto;
}
</style>