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
$_['text_help_jwt']               = 'Enter jwt keys. You need to select parameters "x", "y", "d" from the generated key file to decrypt the responses. If the keys are not filled or incorrect, the plugin will not work. If you have any questions, contact JSC "BANK ALLIANCE".';

// Operation Info Text
$_['text_msg_type']               = 'Message Type:';
$_['text_refund_status']          = 'Refund Status:';
$_['text_refund_successful']      = 'Refund successful';
$_['text_order_status']           = 'Order Status:';
$_['text_amount']                 = 'Amount:';
$_['text_ecom_order_id']          = 'Ecom Order ID:';
$_['text_status_url']             = 'Status URL:';
$_['text_merchant_id']            = 'Merchant ID:';
$_['text_hpp_order_id']           = 'HPP Order ID:';
$_['text_hpp_pay_type']           = 'HPP Pay Type:';
$_['text_expired_order_date']     = 'Expired Order Date:';
$_['text_created_date']           = 'Created Date:';

// Entry
$_['entry_api_url']               = 'Alliance API URL';
$_['entry_param_x']               = 'Parameter "x"';
$_['entry_param_y']               = 'Parameter "y"';
$_['entry_param_d']               = 'Parameter "d"';
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

// Help
$_['help_service_code']           = 'Get serviceCode from JSC "BANK ALLIANCE"';
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

// Button
$_['button_save']                 = 'Save';
$_['button_refund']               = 'Refund';
$_['button_partial_refund']       = 'Partial refund';
$_['button_check_status']         = 'Check order status';
$_['text_refund_shipping_cost']   = 'Refund shipping cost?';
$_['text_please_select_products'] = 'Please select products for refund';
$_['text_confirm_return_requests'] = 'Confirm refund request?';
$_['text_refund_success_message'] = 'Success: Refund request has been confirmed!';

// Error
$_['error_permission']            = 'Warning: You do not have permission to modify Alliance payment module!';
$_['error_technical']             = 'Technical error occurred';
$_['error_something_went_wrong']  = 'Something went wrong :(';
$_['error_refund_not_allowed']    = 'Cannot process refund! Check the alliance.log error log.';