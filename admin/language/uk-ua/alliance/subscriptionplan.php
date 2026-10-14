<?php
// Heading
$_['heading_title'] = 'Плани підписки';
$_['heading_title_reprice'] = 'Зміна ціни для наявних підписників';

// Column
$_['column_status'] = 'Статус';
$_['column_action'] = 'Дія';

// Text
$_['text_subscriptions_menu'] = 'Підписки';
$_['text_success'] = 'Успіх: план підписки збережено.';
$_['text_success_existing'] = 'Такий план уже існує — використано наявний, дубль не створено.';
$_['text_active_subscriptions_count'] = 'Активних підписок: %d';

// Entry
$_['entry_name'] = 'Назва плану';
$_['entry_description'] = 'Опис';
$_['entry_product'] = 'Товар';
$_['entry_category_indicator'] = 'Категорія (індикатор)';
$_['entry_currency'] = 'Валюта';
$_['entry_rules'] = 'Правила розкладу оплат';
$_['entry_payment_number_from'] = 'Від платежу №';
$_['entry_payment_number_to'] = 'До платежу №';
$_['entry_interval_value'] = 'Кожні';
$_['entry_interval_unit'] = 'Одиниця';
$_['entry_amount'] = 'Сума';
$_['entry_applies_from'] = 'Нова ціна діє з';
$_['entry_reason'] = 'Причина зміни ціни';
$_['entry_notified_confirmed'] = 'Підтверджую, що клієнтів, яких стосується зміна, повідомлено (або буде повідомлено)';

// Help
$_['help_rules'] = 'Триал — це правило із сумою 0. Діапазони номерів платежу не повинні перетинатись.';
$_['help_lead_time'] = 'Нова ціна не може набути чинності раніше ніж через %d днів від сьогодні — для сповіщення клієнтів.';
$_['help_reprice_scope'] = 'Ця дія змінює ціну для ВСІХ %d активних підписників цього плану. Нові клієнти після зміни також отримають нову ціну.';

// Button
$_['button_add'] = 'Додати план підписки';
$_['button_add_rule'] = 'Додати правило';
$_['button_reprice'] = 'Застосувати нову ціну';
$_['button_deactivate_for_new'] = 'Не пропонувати новим клієнтам';
$_['button_save'] = 'Зберегти';
$_['button_back'] = 'Назад';

// Error
$_['error_permission'] = 'Попередження: у вас немає прав для зміни планів підписки!';
$_['error_required_fields'] = 'Заповніть усі обов\'язкові поля.';
$_['error_invalid_rule_row'] = 'Кожен рядок правила має містити коректний діапазон номерів платежу, інтервал і суму.';
$_['error_plan_create_failed'] = 'Не вдалося створити план у банку. Деталі — у alliance.log.';
$_['error_reprice_failed'] = 'Не вдалося застосувати нову ціну.';
$_['error_lead_time'] = 'Нова ціна повинна набути чинності не раніше ніж через %d днів від сьогодні.';
$_['error_notification_not_confirmed'] = 'Потрібно підтвердити, що клієнтів повідомлено або буде повідомлено, перш ніж застосувати зміну.';
