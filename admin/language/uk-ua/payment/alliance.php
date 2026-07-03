<?php
// Heading
$_['heading_title']            = 'Alliance Payment';
$_['heading_title_operations'] = 'Отримання даних по замовленню';

// Text
$_['text_extension']              = 'Розширення';
$_['text_success']                = 'Успіх: Налаштування модуля Alliance змінено!';
$_['text_edit']                   = 'Редагування Alliance Payment';
$_['text_general']                = 'Загальні';
$_['text_enabled']                = 'Увімкнено';
$_['text_disabled']               = 'Вимкнено';
$_['text_statuses']               = 'Статуси замовлень';
$_['text_refund_comment']         = 'Повернення коштів через Alliance Payment';
$_['text_refund_success']         = 'Повернення коштів здійснено';
$_['text_refund_complete']        = 'Повернення коштів завершено!';
$_['text_refund_failed']          = 'Помилка повернення коштів!';
$_['text_alliance_settings']      = 'Налаштування модуля оплати Alliance';
$_['text_help_settings']          = 'Обов\'язково пропишіть всі налаштування і перевірте чи все правильно налаштовано. В іншому випадку, оплата може працювати некоректно.';
$_['text_help_jwt']               = 'Пропишіть ключі jwt. Потрібно зі згенерованого файлу ключа вибрати параметри "x", "y", "d" для дешифрування відповідей. Якщо ключі не будуть прописані або будуть невірні, плагін не працюватиме. У разі виникнення питань, зверніться до АТ "БАНК АЛЬЯНС".';

// Helps
$_['help_service_code']           = 'Отримайте serviceCode в АТ “БАНК АЛЬЯНС”';
$_['help_merchant_id']            = 'Отримайте merchantId в АТ “БАНК АЛЬЯНС”';
$_['help_fail_url']               = 'Ця сторінка стандартна за налаштуваннями вашого магазину, якщо є сторінка на яку ви хочете перенаправляти покупця будь ласка вкажіть її. Обов\'язково потрібно прописати повний шлях. Приклад - https://example.com/fail';
$_['help_success_url']            = 'Ця сторінка стандартна за налаштуваннями вашого магазину, якщо є сторінка на яку ви хочете перенаправляти покупця будь ласка вкажіть її. Обов\'язково потрібно прописати повний шлях. Приклад - https://example.com/success';
$_['help_status']                 = 'Виберіть \'Enabled\', щоб активувати модуль';
$_['help_statuses']               = 'Статуси обов\'язково потрібно налаштувати і правильно. Так як вони будуть використувавати в процесі оплати.';
$_['help_pending_status']         = 'Виберіть тут статус, який відповідає даному статусу в вашому магазині. Наприклад статус - "Pending"';
$_['help_error_status']           = 'Виберіть тут статус, який відповідає даному статусу в вашому магазині. Наприклад статус - "Failed"';
$_['help_paid_status']            = 'Виберіть тут статус, який відповідає даному статусу в вашому магазині. Наприклад статус - "Complete"';
$_['help_refunded_status']        = 'Виберіть тут статус, який відповідає даному статусу в вашому магазині. Наприклад статус - "Refounded"';
$_['help_refund_fail_status']     = 'Виберіть тут статус, який відповідає даному статусу в вашому магазині. Наприклад статус - "Failed"';

// Operation Info
$_['text_msg_type']               = 'Тип повідомлення:';
$_['text_refund_status']          = 'Статус повернення:';
$_['text_refund_successful']      = 'повернення коштів здійснено';
$_['text_order_status']           = 'Статус замовлення:';
$_['text_amount']                 = 'Сума:';
$_['text_ecom_order_id']          = 'ID замовлення Ecom:';
$_['text_status_url']             = 'URL статусу:';
$_['text_merchant_id']            = 'ID мерчанта:';
$_['text_hpp_order_id']           = 'ID замовлення HPP:';
$_['text_hpp_pay_type']           = 'Тип оплати HPP:';
$_['text_expired_order_date']     = 'Термін дії замовлення:';
$_['text_created_date']           = 'Дата створення:';

// Entry
$_['entry_api_url']               = 'Alliance API URL';
$_['entry_param_x']               = 'Параметр "x"';
$_['entry_param_y']               = 'Параметр "y"';
$_['entry_param_d']               = 'Параметр "d"';
$_['entry_hpp_type']              = 'Тип HPP сторінки';
$_['entry_fail_url']              = 'Сторінка на яку перенаправиться користувач в разі помилки оплати';
$_['entry_success_url']           = 'Сторінка на яку перенаправиться користувач в разі успішної оплати';
$_['entry_status']                = 'Статус модуля оплати';
$_['entry_pending_status']        = 'Статус "в процесі оплати"';
$_['entry_error_status']          = 'Статус "помилка оплати"';
$_['entry_paid_status']           = 'Статус "оплачено"';
$_['entry_refunded_status']       = 'Статус "оплату повернено"';
$_['entry_refund_fail_status']    = 'Статус "помилка повернення оплати"';
$_['entry_confirmed_status']      = 'Статус підтвердження';
$_['entry_complete_status']       = 'Статус завершення';

// Help
$_['help_service_code']           = 'Отримайте serviceCode в АТ "БАНК АЛЬЯНС"';
$_['help_merchant_id']            = 'Отримайте merchantId в АТ "БАНК АЛЬЯНС"';
$_['help_fail_url']               = 'Ця сторінка стандартна за налаштуваннями вашого магазину. Приклад: https://example.com/fail';
$_['help_success_url']            = 'Ця сторінка стандартна за налаштуваннями вашого магазину. Приклад: https://example.com/success';
$_['help_status']                 = 'Виберіть "Увімкнено", щоб активувати модуль';
$_['help_statuses']               = 'Статуси обов\'язково потрібно налаштувати правильно, так як вони будуть використовуватись в процесі оплати.';
$_['help_pending_status']         = 'Виберіть статус, який відповідає "Pending" у вашому магазині';
$_['help_error_status']           = 'Виберіть статус, який відповідає "Failed" у вашому магазині';
$_['help_paid_status']            = 'Виберіть статус, який відповідає "Complete" у вашому магазині';
$_['help_refunded_status']        = 'Виберіть статус, який відповідає "Refunded" у вашому магазині';
$_['help_refund_fail_status']     = 'Виберіть статус, який відповідає "Failed" у вашому магазині';
$_['help_hpp_type']               = 'Виберіть тип HPP платежу з двох доступних PURCHASE або A2A.';

// Button
$_['button_save']                 = 'Зберегти';
$_['button_refund']               = 'Повернення коштів';
$_['button_partial_refund']       = 'Часткове повернення коштів';
$_['button_check_status']         = 'Перевірити статус замовлення';
$_['text_refund_shipping_cost']   = 'Повернути вартість доставки?';
$_['text_please_select_products'] = 'Оберіть продукти для повернення.';
$_['text_confirm_return_requests'] = 'Ви підтверджуєте повернення?';
$_['text_refund_success_message']  = 'Успіх: Повернення виконано!';
$_['text_refund_history_comment']  = 'Кошти повернено через Alliance Payment';
$_['text_auto_return_comment']     = 'Автоматичний запит на повернення через Alliance Payment';

// Error
$_['error_permission']            = 'Увага: У вас немає дозволу на зміну модуля Alliance!';
$_['error_technical']             = 'Сталась технічна помилка';
$_['error_something_went_wrong']  = 'Щось пішло не так :(';
$_['error_refund_not_allowed']    = 'Не можна зробити повернення! Дивіться журнал помилок alliance.log.';
$_['text_alliance_operation_purchase_not_found'] = 'Не знайдено операції купівлі.';