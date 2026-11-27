<?php
// Heading
$_['heading_title']               = 'Alliance Payment';
$_['heading_title_operations']    = 'Order data retrieval';

// Text
$_['text_extension']              = 'Extensions';
$_['text_success']                = 'Success: You have modified Alliance payment module!';
$_['text_edit']                   = 'Edit Alliance Payment';
$_['text_general']                = 'General';
$_['text_enabled']                = 'Enabled';
$_['text_disabled']               = 'Disabled';
$_['text_statuses']               = 'Order Statuses';
$_['text_refund_comment']         = 'Refund through Alliance Payment';
$_['text_refund_success']         = 'Refund successful';
$_['text_refund_complete']        = 'Refund complete!';
$_['text_refund_failed']          = 'Refund failed!';
$_['text_alliance_settings']      = 'Alliance payment module settings';
$_['text_help_settings']          = 'Be sure to fill in all settings and check if everything is correct. Otherwise, the payment may not work correctly.';
$_['text_help_jwt']               = 'Enter the RSA private key (PEM, PKCS#8, "-----BEGIN PRIVATE KEY-----") issued by JSC "BANK ALLIANCE" and the associated Key ID (kid). They are used to sign every request sent to the bank. If they are missing or incorrect, the plugin will not work. If you have any questions, contact JSC "BANK ALLIANCE".';

// Operation Info Text
$_['text_msg_type']               = 'Message Type:';
$_['text_refund_status']          = 'Refund Status:';
$_['text_refund_successful']      = 'Refund successful';
$_['text_order_status']           = 'Order Status:';
$_['text_amount']                 = 'Amount:';
$_['text_amount_uah']             = 'Amount (UAH):';
$_['text_ecom_order_id']          = 'Ecom Order ID:';
$_['text_status_url']             = 'Status URL:';
$_['text_merchant_id']            = 'Merchant ID:';
$_['text_hpp_order_id']           = 'HPP Order ID:';
$_['text_hpp_pay_type']           = 'HPP Pay Type:';
$_['text_expired_order_date']     = 'Expired Order Date:';
$_['text_created_date']           = 'Created Date:';

// Entry
$_['entry_api_url']               = 'Alliance API URL';
$_['entry_jws_private_key']       = 'JWS Private Key';
$_['entry_jws_key_id']            = 'JWS Key ID (kid)';
$_['entry_hpp_type']              = 'Hpp Type';
$_['entry_fail_url']              = 'Page to redirect the user in case of payment error';
$_['entry_success_url']           = 'Page to redirect the user in case of successful payment';
$_['entry_status']                = 'Payment module status';
$_['entry_pending_status']        = '"Payment in progress" status';
$_['entry_error_status']          = '"Payment error" status';
$_['entry_paid_status']           = '"Paid" status';
$_['entry_refunded_status']       = '"Payment refunded" status';
$_['entry_refund_fail_status']    = '"Refund error" status';
$_['entry_confirmed_status']      = 'Confirmed Status';
$_['entry_complete_status']       = 'Complete Status';
$_['entry_preauth_exp_date']      = 'PREAUTH expiration period';
$_['entry_preauth_status']        = '"PREAUTH authorized" status';
$_['entry_completion_status']     = '"Completion trigger" status';

// Help
$_['help_jws_private_key']        = 'EC P-256 private key (PEM, PKCS#8, "-----BEGIN PRIVATE KEY-----"), requests are signed with ES256 (RSA keys still work with RS256) and used to sign requests to JSC "BANK ALLIANCE". Provided together with the Key ID.';
$_['help_jws_key_id']             = 'Key ID (kid) issued by JSC "BANK ALLIANCE" together with the private key.';
$_['help_merchant_id']            = 'Get merchantId from JSC "BANK ALLIANCE"';
$_['help_fail_url']               = 'This page is standard according to your store settings. Example: https://example.com/fail';
$_['help_success_url']            = 'This page is standard according to your store settings. Example: https://example.com/success';
$_['help_status']                 = 'Select \'Enabled\' to activate the module';
$_['help_statuses']               = 'Statuses must be configured correctly, as they will be used during the payment process.';
$_['help_pending_status']         = 'Select the status that corresponds to "Pending"';
$_['help_error_status']           = 'Select the status that corresponds to "Failed"';
$_['help_paid_status']            = 'Select the status that corresponds to "Complete"';
$_['help_refunded_status']        = 'Select the status that corresponds to "Refunded"';
$_['help_refund_fail_status']     = 'Select the status that corresponds to "Failed"';
$_['help_hpp_type']               = 'Choose the HPP payment type from the available options: PURCHASE, A2A or PREAUTH.';
$_['help_preauth_exp_date']       = 'Select the authorization hold period for PREAUTH payments (min 2h, max 28d). A 30-second buffer is added automatically.';
$_['help_preauth_status']         = 'Status set on the order after a successful PREAUTH authorization (funds are held, not yet captured)';
$_['help_completion_status']      = 'When admin sets this status the held funds will be captured. Amount must be within ±20% of the original authorized amount.';

// Button
$_['button_save']                 = 'Save';
$_['button_refund']               = 'Refund';
$_['button_partial_refund']       = 'Partial refund';
$_['button_check_status']         = 'Check order status';
$_['text_refund_shipping_cost']   = 'Refund shipping cost?';
$_['text_please_select_products'] = 'Please select products for refund';
$_['text_confirm_return_requests'] = 'Confirm refund request?';
$_['text_refund_success_message']  = 'Success: Refund request has been confirmed!';
$_['text_refund_history_comment']  = 'Refunded via Alliance Payment';
$_['text_auto_return_comment']     = 'Auto-created return request via Alliance Payment';

// Error
$_['error_permission']            = 'Warning: You do not have permission to modify Alliance payment module!';
$_['error_technical']             = 'Technical error occurred';
$_['error_something_went_wrong']  = 'Something went wrong :(';
$_['error_refund_not_allowed']    = 'Cannot process refund! Check the alliance.log error log.';
$_['error_completion_amount']     = 'Warning: Order amount differs from the original authorized amount by more than 20%%. Completion is not allowed.';
$_['error_completion_failed']     = 'Completion request failed. Check alliance.log for details.';
$_['error_not_preauth_order']     = 'This order is not a PREAUTH order.';
$_['button_confirm_payment']      = 'Confirm Payment';
$_['text_completion_success']     = 'Payment successfully captured.';
$_['text_alliance_operation_purchase_not_found'] = 'Purchase operation not found.';
$_['a2a_uah_warning']             = 'Warning: A2A payment type supports UAH currency only. Your store\'s base currency is %s.';

$_['text_fiscalization']                     = 'Fiscalization';
$_['entry_fiscalization_enabled']            = 'Enable fiscalization';
$_['help_fiscalization_enabled']             = 'If disabled, no fiscalization requests are sent and the order buttons are hidden.';
$_['entry_fiscalization_auto']               = 'Automatic fiscalization';
$_['help_fiscalization_auto']                = 'If enabled, a fiscalization request is sent automatically after a successful payment or refund. If disabled, fiscalization is available only with the button on the order page (for both payments and refunds).';
$_['entry_fiscalization_tax_code']           = 'Tax group code (taxCode)';
$_['help_fiscalization_tax_code']            = 'Default value, used if the product has no own value. Integer, may be empty.';
$_['entry_fiscalization_classification_code'] = 'UKTZED code (classificationCode)';
$_['help_fiscalization_classification_code'] = 'Default value, digits only (usually 10). Required if the tax group code is 2, 7 or 8.';
$_['entry_fiscalization_excise_labels']      = 'Excise labels (exciseLabels)';
$_['help_fiscalization_excise_labels']       = 'Default value. Comma-separated or one per line. Used only for tax group codes 2, 7, 8.';
$_['entry_fiscalization_measurement_code']   = 'Unit of measurement code (measurementCode)';
$_['help_fiscalization_measurement_code']    = 'Required. Default is 0001 (piece).';
$_['error_fiscalization_tax_code']           = 'Tax group code must be an integer.';
$_['error_fiscalization_classification_code'] = 'UKTZED code must contain digits only.';
$_['error_fiscalization_classification_required'] = 'UKTZED code is required for tax group code 2, 7 or 8.';
$_['error_fiscalization_measurement_code']   = 'Unit of measurement code is required.';
$_['text_fiscalization_product_help']        = 'Empty fields fall back to the defaults from the Alliance module settings.';
$_['text_fiscalization_block']               = 'Fiscalization';
$_['text_fiscalization_none']                = 'Fiscalization has not been performed yet.';
$_['column_fiscalization_type']              = 'Type';
$_['column_fiscalization_status']            = 'Status';
$_['column_fiscalization_number']            = 'Fiscal number';
$_['column_fiscalization_receipt']           = 'Receipt';
$_['column_fiscalization_date']              = 'Date';
$_['column_fiscalization_error']             = 'Error';
$_['button_fiscalize']                       = 'Fiscalize';
$_['button_fiscalization_refresh']           = 'Refresh status';
$_['text_fiscalization_sent']                = 'Fiscalization request sent. Status: %s';
$_['error_fiscalization_disabled']           = 'Fiscalization is disabled in the module settings.';
$_['error_fiscalization_already']            = 'Fiscalization for this operation is already in progress or completed.';
$_['error_fiscalization_no_operation']       = 'No successful payment operation found for this order.';
$_['error_fiscalization_request']            = 'Fiscalization request failed: %s';
$_['error_fiscalization_validation']         = 'Fiscalization data is invalid: %s';
$_['error_fiscalization_missing_required_field'] = 'Required field is missing: %s';
$_['error_fiscalization_invalid_format']     = 'Invalid field format: %s';
$_['error_fiscalization_payment_not_found']  = 'Payment operation was not found in Alliance Ecom.';
$_['error_fiscalization_original_receipt_not_found'] = 'Original receipt for the refund was not found. Fiscalize the purchase first.';
$_['comment_fiscalization_success']          = 'Receipt fiscalized, fiscal number: %s';
$_['comment_fiscalization_fail']             = 'Fiscalization error: %s %s';
