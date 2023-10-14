<div align='center'>

# Tochka Bank Telegram Notifications

![php](https://img.shields.io/badge/php-7.3-blue)
![tochka](https://img.shields.io/badge/tochka-1.37.15-blue)
[![Authors](https://img.shields.io/badge/Authors-djpont-blue)](https://github.com/djpont)

Уведомления о поступлении средств в "Точка Банк" для Telegram.

<img width=33% src='https://github.com/djpont/php-tochka-bank-telegram-notifications/assets/34692754/16bc9c6f-a638-4c0b-8994-a13b28368bca' />

</div>

---

### Как добавить к себе

Ваш сервер должен поддерживать SSL, достаточно самоподписного сертификата.

Разместите файлы в отдельной директории на своём сервере, например:

`https://example.ru/tochka/`

---

### Интеграция с "Точка Банк"

В личном кабинете "Точка Банк" перейдите в раздел **Сервисы** -> **Интеграции** -> **Разработчикам** -> **Перейти к подключению**

https://i.tochka.com/bank/app/integration

- В поле `Redirect url` укажите адрес до файла `tochka.php`, например `https://example.ru/tochka/tochka.php`
- В поле `Название` укажиле любое название вашего приложение (будет видно только вам).

В ответ получите clientId и clientSecret - сохраните их, они пригодятся для настроки.

---

### Настройка

Укажите параметры в файле `config.php`

- `clientId` и `clientSecret` - полученные в предыдущем пункте
- `receiveCodeUrl` - должен совпадать с указанным в интеграции с "Точка Банк"
- `afterAuthUrl` - страничка, куда перенаправляем после авторизации
- `webhookUrl` - адрес до файла `incoming_payment.php`
- `incomingPaymentLog` - если требуется вести лог (будет сохранён в файл `incoming_payment_log.txt`)
- `incomingPaymentAccounts` - массив с номерами ваших счетов
- `telegramBotToken` - Токен вашего телеграм бота (если бота нет, создайте новый через [BotFather](https://t.me/BotFather))
- `telegramBotTokenChannelId` - ID или @username вашего телеграм канала

_Подсказка: если не знаете ID приватного канала, можно временно сделать канал публичным, присвоить ему @название, любым способом на @название отправить через бота сообщение и в ответе получите ID._

---

### Авторизация

Перейдите по адресу директории, скрипт проверит текущую авторизацию и вебхуки.

`https://example.ru/tochka`

В случае необходимости появится ссылка для авторизации.

Тут же можно получить информацию о состоянии вебхуков.

Данные об авторизации хранятся локально в файле `access_tokens.php`.
Если хотите изменить локигу и сохранять токены в БД, добавьте код в методы `saveAccessTokens` и `loadAccessTokens` класса `TochkaBank`.

---

### Контакты

Телеграм автора: [@djpont](https://t.me/djpont)
