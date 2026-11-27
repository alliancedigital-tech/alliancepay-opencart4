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
$_['text_help_jwt']               = 'Пропишіть RSA приватний ключ (PEM, PKCS#8, "-----BEGIN PRIVATE KEY-----"), виданий АТ "БАНК АЛЬЯНС", та відповідний Key ID (kid). Вони використовуються для підпису кожного запиту до банку. Якщо ключ або kid не прописані чи невірні, плагін не працюватиме. У разі виникнення питань, зверніться до АТ "БАНК АЛЬЯНС".';

// Helps
$_['help_jws_private_key']        = 'EC P-256 приватний ключ (PEM, PKCS#8, "-----BEGIN PRIVATE KEY-----"), запити підписуються алгоритмом ES256 (RSA-ключі працюють як раніше з RS256), для підпису запитів до АТ "БАНК АЛЬЯНС". Видається разом з Key ID.';
$_['help_jws_key_id']             = 'Key ID (kid), який АТ "БАНК АЛЬЯНС" видає разом з приватним ключем.';
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
$_['text_amount_uah']             = 'Сума (UAH):';
$_['text_ecom_order_id']          = 'ID замовлення Ecom:';
$_['text_status_url']             = 'URL статусу:';
$_['text_merchant_id']            = 'ID мерчанта:';
$_['text_hpp_order_id']           = 'ID замовлення HPP:';
$_['text_hpp_pay_type']           = 'Тип оплати HPP:';
$_['text_expired_order_date']     = 'Термін дії замовлення:';
$_['text_created_date']           = 'Дата створення:';

// Entry
$_['entry_api_url']               = 'Alliance API URL';
$_['entry_jws_private_key']       = 'JWS Приватний ключ';
$_['entry_jws_key_id']            = 'JWS Key ID (kid)';
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
$_['entry_preauth_exp_date']      = 'Термін дії PREAUTH авторизації';
$_['entry_preauth_status']        = 'Статус "PREAUTH авторизовано"';
$_['entry_completion_status']     = 'Статус для підтвердження списання';

// Help
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
$_['help_hpp_type']               = 'Виберіть тип HPP платежу з доступних варіантів: PURCHASE, A2A або PREAUTH.';
$_['help_preauth_exp_date']       = 'Оберіть термін заморозки коштів для PREAUTH платежів (мін. 2 год, макс. 28 днів). Автоматично додається буфер 30 секунд.';
$_['help_preauth_status']         = 'Статус, який встановлюється після успішної PREAUTH авторизації (кошти заморожені, але не списані)';
$_['help_completion_status']      = 'Коли адміністратор встановлює цей статус — кошти списуються. Сума повинна відрізнятись не більше ніж на ±20% від авторизованої суми.';

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
$_['error_completion_amount']     = 'Увага: Сума замовлення відрізняється від авторизованої суми більше ніж на 20%%. Списання коштів неможливе.';
$_['error_completion_failed']     = 'Запит на списання коштів не вдався. Перевірте журнал помилок alliance.log.';
$_['error_not_preauth_order']     = 'Це замовлення не є PREAUTH замовленням.';
$_['button_confirm_payment']      = 'Підтвердити оплату';
$_['text_completion_success']     = 'Оплату успішно підтверджено.';
$_['text_alliance_operation_purchase_not_found'] = 'Не знайдено операції купівлі.';
$_['a2a_uah_warning']             = 'Увага: тип оплати A2A підтримує лише валюту UAH. Основна валюта вашого магазину: %s.';

$_['text_fiscalization']                     = 'Фіскалізація';
$_['entry_fiscalization_enabled']            = 'Увімкнути фіскалізацію';
$_['help_fiscalization_enabled']             = 'Якщо вимкнено, запити фіскалізації не відправляються, а кнопки в замовленні приховані.';
$_['entry_fiscalization_auto']               = 'Автоматична фіскалізація';
$_['help_fiscalization_auto']                = 'Якщо увімкнено, запит на фіскалізацію відправляється автоматично після успішної оплати або повернення. Якщо вимкнено, фіскалізація виконується лише кнопкою на сторінці замовлення (і для платежів, і для повернень).';
$_['entry_fiscalization_tax_code']           = 'Код податкової групи (taxCode)';
$_['help_fiscalization_tax_code']            = 'Значення за замовчуванням, якщо в товарі не задано власне. Ціле число, можна залишити порожнім.';
$_['entry_fiscalization_classification_code'] = 'УКТЗЕД (classificationCode)';
$_['help_fiscalization_classification_code'] = 'Значення за замовчуванням, лише цифри (зазвичай 10). Обов\'язкове, якщо код податкової групи 2, 7 або 8.';
$_['entry_fiscalization_excise_labels']      = 'Акцизні марки (exciseLabels)';
$_['help_fiscalization_excise_labels']       = 'Значення за замовчуванням. Через кому або по одній в рядку. Використовується лише для кодів податкової групи 2, 7, 8.';
$_['entry_fiscalization_measurement_code']   = 'Код одиниці виміру (measurementCode)';
$_['help_fiscalization_measurement_code']    = 'Обов\'язкове. За замовчуванням 0001 (штука).';
$_['error_fiscalization_tax_code']           = 'Код податкової групи має бути цілим числом.';
$_['error_fiscalization_classification_code'] = 'УКТЗЕД має містити лише цифри.';
$_['error_fiscalization_classification_required'] = 'УКТЗЕД обов\'язковий для коду податкової групи 2, 7 або 8.';
$_['error_fiscalization_measurement_code']   = 'Код одиниці виміру обов\'язковий.';
$_['text_fiscalization_product_help']        = 'Порожні поля беруть значення за замовчуванням з налаштувань модуля Alliance.';
$_['text_fiscalization_block']               = 'Фіскалізація';
$_['text_fiscalization_none']                = 'Фіскалізацію ще не виконано.';
$_['column_fiscalization_type']              = 'Тип';
$_['column_fiscalization_status']            = 'Статус';
$_['column_fiscalization_number']            = 'Фіскальний номер';
$_['column_fiscalization_receipt']           = 'Чек';
$_['column_fiscalization_date']              = 'Дата';
$_['column_fiscalization_error']             = 'Помилка';
$_['button_fiscalize']                       = 'Фіскалізувати';
$_['button_fiscalization_refresh']           = 'Оновити статус';
$_['text_fiscalization_sent']                = 'Запит на фіскалізацію відправлено. Статус: %s';
$_['error_fiscalization_disabled']           = 'Фіскалізацію вимкнено в налаштуваннях модуля.';
$_['error_fiscalization_already']            = 'Фіскалізація для цієї операції вже виконується або завершена.';
$_['error_fiscalization_no_operation']       = 'Для цього замовлення не знайдено успішної платіжної операції.';
$_['error_fiscalization_request']            = 'Запит фіскалізації не вдався: %s';
$_['error_fiscalization_validation']         = 'Дані для фіскалізації некоректні: %s';
$_['error_fiscalization_missing_required_field'] = 'Відсутнє обов\'язкове поле: %s';
$_['error_fiscalization_invalid_format']     = 'Некоректний формат поля: %s';
$_['error_fiscalization_payment_not_found']  = 'Платіжну операцію не знайдено в Alliance Ecom.';
$_['error_fiscalization_original_receipt_not_found'] = 'Не знайдено оригінальний чек для повернення. Спочатку фіскалізуйте покупку.';
$_['comment_fiscalization_success']          = 'Чек фіскалізовано, ФН: %s';
$_['comment_fiscalization_fail']             = 'Помилка фіскалізації: %s %s';
