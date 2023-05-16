<?php

$clientId = 'Ваш client id';
$clientSecret = 'Ваш client secret';

// Адрес, куда Точка отправит код после атворизации
$receiveCodeUrl = 'https://example.ru/tochka/tochka.php';

// Адрес, куда юзер будет перенаправлен после завершения авторизации
$afterAuthUrl = 'https://example.ru/tochka';

// Адрес, на который Точка Банк будет уведомление о входящих платежах
$webhookUrl = 'https://example.ru/tochka/incoming_payment.php';

// Нужно ли вести лог входящих платежей
$incomingPaymentLog = true;

// Список номеров счетов, о поступлении на которые будет отправлять уведомление в телеграм
$incomingPaymentAccounts = [
	'Номер счёта, можно несколько в массиве',
];

// Лимит суммы, выше которой не отправляем уведомление
// Если равно нулю, то лимит не применяется
$incomingPaymentAmountLimit = 0;

// Токен телеграм бота
$telegramBotToken = 'Токен вашего телеграм бота';

// Телеграм канал
$telegramBotTokenChannelId = 'ID или @username вашего канала';

