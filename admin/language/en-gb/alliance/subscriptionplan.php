<?php
// Heading
$_['heading_title'] = 'Subscription Plans';
$_['heading_title_reprice'] = 'Change Price for Existing Subscribers';

// Column
$_['column_status'] = 'Status';
$_['column_action'] = 'Action';

// Text
$_['text_subscriptions_menu'] = 'Subscriptions';
$_['text_success'] = 'Success: subscription plan saved.';
$_['text_success_existing'] = 'An identical plan already exists — reused instead of creating a duplicate.';
$_['text_active_subscriptions_count'] = 'Active subscriptions: %d';

// Entry
$_['entry_name'] = 'Plan name';
$_['entry_description'] = 'Description';
$_['entry_product'] = 'Product';
$_['entry_category_indicator'] = 'Category indicator';
$_['entry_currency'] = 'Currency';
$_['entry_rules'] = 'Billing schedule rules';
$_['entry_payment_number_from'] = 'From payment #';
$_['entry_payment_number_to'] = 'To payment #';
$_['entry_interval_value'] = 'Every';
$_['entry_interval_unit'] = 'Unit';
$_['entry_amount'] = 'Amount';
$_['entry_applies_from'] = 'New price applies from';
$_['entry_reason'] = 'Reason for the price change';
$_['entry_notified_confirmed'] = 'I confirm affected customers have been (or will be) notified of this price change';

// Help
$_['help_rules'] = 'A trial step is a rule with amount 0. Payment numbers must not overlap between rules.';
$_['help_lead_time'] = 'New price cannot apply sooner than %d days from today, to give customers time to be notified.';
$_['help_reprice_scope'] = 'This changes the price for ALL %d active subscribers of this plan. New customers signing up after this change also get the new price.';

// Button
$_['button_add'] = 'Add subscription plan';
$_['button_add_rule'] = 'Add rule';
$_['button_reprice'] = 'Apply new price';
$_['button_deactivate_for_new'] = 'Stop offering to new customers';
$_['button_save'] = 'Save';
$_['button_back'] = 'Back';

// Error
$_['error_permission'] = 'Warning: you do not have permission to modify subscription plans!';
$_['error_required_fields'] = 'Please fill in all required fields.';
$_['error_invalid_rule_row'] = 'Every billing rule row needs a valid payment-number range, interval and amount.';
$_['error_plan_create_failed'] = 'Could not create the plan at the bank. Check alliance.log for details.';
$_['error_reprice_failed'] = 'Could not apply the new price.';
$_['error_lead_time'] = 'The new price must apply at least %d days from today.';
$_['error_notification_not_confirmed'] = 'You must confirm that affected customers have been, or will be, notified before applying this change.';
