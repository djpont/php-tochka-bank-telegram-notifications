<?php

/**
 * Класс API для работы с "Точка Банк"
 * Реализованные методы:
 *  - авторизация (получение)
 *  - проверка авторизации
 *  - проверка текущего баланса
 *  - продписка и отписка на входящие платежи
 */
class TochkaBank {
	private $ch; // CURL для запросов
	private $apiVersion = 'v1.0'; // Версия Api банка
	private $clientId;
	private $clientSecret;
	private $receiveCodeUrl; // Адрес, куда банк отправит код в процессе авторизации
	private $afterAuthUrl; // Адрес, куда юзер будет перенаправлен после авторизации

	private $webhookUrl; // Адрес, который банк будет дёргать при получении платежа
	// Список подписок
	private $webhooksList = [
		'incomingPayment', // Обычные платежи
		'incomingSbpPayment', // СБП платежи
	];
	private $scope = 'balances'; // Требыемые разрешения от клиента

	private $configFile = 'config.php'; // Файл с конфигом
	private $accessTokensFile = 'access_tokens.php'; // Файл, где будут храниться токены

	private $accessToken;
	private $incomingPaymentLog = false; // Вести лог входящих платежей
	private $incomingPaymentLogFile = 'incoming_payment_log.txt'; // Вести лог входящих платежей
	private $incomingPaymentAccounts;
	private $incomingPaymentAmountLimit = 0;

	private $telegramBotToken;
	private $telegramBotTokenChannelId;

	public function __construct() {
		$this->loadConfig();
		$this->ch = curl_init();
	}

	/**
	 * Метод для загрузки конфига.
	 * Требуется наличие config.php
	 * @return void
	 */
	private function loadConfig(): void {
		include $this->configFile;
		if (
			!isset($clientId) ||
			!isset($clientSecret) ||
			!isset($receiveCodeUrl) ||
			!isset($afterAuthUrl) ||
			!isset($webhookUrl) ||
			!isset($incomingPaymentAccounts)
		) {
			throw new Error('Неправильный конфиг!');
		}
		$this->clientId = $clientId;
		$this->clientSecret = $clientSecret;
		$this->receiveCodeUrl = $receiveCodeUrl;
		$this->afterAuthUrl = $afterAuthUrl;
		$this->webhookUrl = $webhookUrl;
		$this->incomingPaymentLog = $incomingPaymentLog ?? false;
		$this->incomingPaymentAccounts = $incomingPaymentAccounts;
		$this->incomingPaymentAmountLimit = $incomingPaymentAmountLimit ?? 0;
		$this->telegramBotToken = $telegramBotToken ?? false;
		$this->telegramBotTokenChannelId = $telegramBotTokenChannelId ?? false;
	}

	/**
	 * Метод авторизации приложения в "Точка Банк".
	 * @return string Адрес для редиректа на сайт банка
	 */
	public function Authorization(): string {
		$authToken = $this->getAuthToken();
		return $this->getRedirectForAuthorization($authToken);
	}

	/**
	 * Метод поверки авторизации
	 * @return bool
	 */
	public function checkAuthorization(): bool {
		[
			'accessToken' => $accessToken
		] = $this->loadAccessTokens();
		if (!$accessToken) {
			return false;
		}
		$url = 'https://enter.tochka.com/connect/introspect';
		$data = array(
			'access_token' => $accessToken
		);
		$options = array(
			CURLOPT_URL => $url,
			CURLOPT_POST => true,
			CURLOPT_POSTFIELDS => http_build_query($data),
			CURLOPT_HTTPHEADER => array('Content-Type: application/x-www-form-urlencoded'),
			CURLOPT_RETURNTRANSFER => true
		);
		curl_setopt_array($this->ch, $options);
		$response = curl_exec($this->ch);
		$payload = $this->decodeJWT($response);
		if (!isset($payload['aud'])) {
			return false;
		}
		$aud = $payload['aud'];
		return $aud == $this->clientId;
	}

	/**
	 * Метод дешифровки JWT строки в массив
	 * @param $jwt string JWT строка
	 * @return array
	 */
	private function decodeJWT(string $jwt): array {
		$parts = explode('.', $jwt);
		return json_decode(base64_decode($parts[1]), true);
	}

	/**
	 * Метод получения токена для авторизации
	 * @return string Токен для авторизации
	 */
	private function getAuthToken(): string {
		$url = 'https://enter.tochka.com/connect/token';
		$data = array(
			'client_id' => $this->clientId,
			'client_secret' => $this->clientSecret,
			'grant_type' => 'client_credentials',
			'scope' => $this->scope,
			'state' => 'qwe'
		);
		$options = array(
			CURLOPT_URL => $url,
			CURLOPT_POST => true,
			CURLOPT_POSTFIELDS => http_build_query($data),
			CURLOPT_HTTPHEADER => array('Content-Type: application/x-www-form-urlencoded'),
			CURLOPT_RETURNTRANSFER => true
		);
		curl_setopt_array($this->ch, $options);
		$response = curl_exec($this->ch);
		$data = json_decode($response, true);
		if (isset($data['error']) && !isset($data['access_token'])) {
			throw new Error('Ошибка получения Auth Token');
		}
		return $data['access_token'];
	}

	/**
	 * Метод генерации адрса, где клиент сможет авторизоваться
	 * @param string $authToken Токен для авторизации
	 * @return string Адрес для редиректа на сайт банка
	 */
	private function getRedirectForAuthorization(string $authToken): string {
		$url = 'https://enter.tochka.com/uapi/v1.0/consents';
		$expirationDateTime = date('Y-m-d\TH:i:sP', strtotime('+200 hour'));
		$data = array(
			'Data' => array(
				'permissions' => array(
					'ReadBalances',
				),
				'expirationDateTime' => $expirationDateTime
			)
		);
		$headers = array(
			'Authorization: Bearer ' . $authToken,
			'Content-Type: application/json'
		);
		$options = array(
			CURLOPT_URL => $url,
			CURLOPT_POST => true,
			CURLOPT_POSTFIELDS => json_encode($data),
			CURLOPT_HTTPHEADER => $headers,
			CURLOPT_RETURNTRANSFER => true
		);
		curl_setopt_array($this->ch, $options);
		$response = curl_exec($this->ch);
		$data = json_decode($response, true);
		if (
			!isset($data['Data']) ||
			!isset($data['Data']['status']) ||
			$data['Data']['status'] !== 'AwaitingAuthorisation'
		) {
			throw new Error('Ошибка получения Awaiting Authorisation');
		}
		$consentId = $data['Data']['consentId'];
		// $consumerId = $data['Data']['consumerId'];
		$client_id = $this->clientId;
		$response_type = 'code';
		$state = 'Authorization';
		$scope = rawurlencode($this->scope);
		$redirectUrl = $this->receiveCodeUrl;
		return "https://enter.tochka.com/connect/authorize?client_id=$client_id&response_type=$response_type&state=$state&redirect_uri=$redirectUrl&scope=$scope&consent_id=$consentId";
	}

	/**
	 * Метод получения кода авторизации и загрузки access token
	 * @param string $code Кода авторизации
	 * @return string Адрес для редиректа на страницу после авторизации
	 */
	public function receiveAuthCode(string $code): string {
		$url = 'https://enter.tochka.com/connect/token';
		$data = array(
			'client_id' => $this->clientId,
			'client_secret' => $this->clientSecret,
			'grant_type' => 'authorization_code',
			'scope' => $this->scope,
			'code' => $code,
			'redirect_uri' => $this->receiveCodeUrl,
		);
		$options = array(
			CURLOPT_URL => $url,
			CURLOPT_POST => true,
			CURLOPT_POSTFIELDS => http_build_query($data),
			CURLOPT_HTTPHEADER => array('Content-Type: application/x-www-form-urlencoded'),
			CURLOPT_RETURNTRANSFER => true
		);

		$ch2 = curl_init();
		curl_setopt_array($ch2, $options);
		$response = curl_exec($ch2);
		$data = json_decode($response, true);
		$this->saveAccessTokens($data);
		return $this->afterAuthUrl;
	}

	/**
	 * Метод сохранения токенов в файал
	 */
	private function saveAccessTokens(array $data): void {
		// echo '<pre>'.print_r($data, true).'</pre>';
		if (
			!isset($data['refresh_token']) ||
			!isset($data['access_token'])
		) {
			throw new Error('Ошибка сохранения Access Tokens');
		}
		$expiresTime = $data['expires_in'] ?? 86400;
		$expiresTime = time() + (int)$expiresTime;
		$content = "<?php\r\n";
		$content .= "\$refreshToken='" . $data['refresh_token'] . "';\r\n";
		$content .= "\$accessToken='" . $data['access_token'] . "';\r\n";
		$content .= "\$expiresTime='" . $expiresTime . "';\r\n";
		file_put_contents($this->accessTokensFile, $content);
	}

	/**
	 * Метод загрузки токенов из файал
	 * @return array Массив токенов и срока годности
	 */
	private function loadAccessTokens(): array {
		include $this->accessTokensFile;
		if (
			!isset($refreshToken) ||
			!isset($accessToken) ||
			!isset($expiresTime)
		) {
			return [];
		}
		$this->accessToken = $accessToken;
		return [
			'refreshToken' => $refreshToken,
			'accessToken' => $accessToken,
			'expiresTime' => $expiresTime,
			'expires' => date('Y-m-d H-i-s', $expiresTime)
		];
	}

	/**
	 * Метод загрузки балансов на счетах клиента
	 * @return array Массив сумм балансов
	 *   - OpeningAvailable
	 *   - ClosingAvailable
	 *   - Expected
	 */
	public function getBalance(): array {
		if (!$this->checkAuthorization()) {
			return [];
		}
		$url = 'https://enter.tochka.com/uapi/open-banking/' . $this->apiVersion . '/balances';
		$authorizationToken = 'Bearer ' . $this->accessToken;
		$options = array(
			CURLOPT_URL => $url,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_HTTPHEADER => [
				'Authorization: ' . $authorizationToken,
			],
		);
		curl_setopt_array($this->ch, $options);
		$response = curl_exec($this->ch);
		$data = json_decode($response, true);
		if (!isset($data['Data']['Balance'])) {
			throw new Error('Ошибка получения Balance!');
		}
		$data = $data['Data']['Balance'];
		$balance = [
			'OpeningAvailable' => 0,
			'ClosingAvailable' => 0,
			'Expected' => 0,
		];
		foreach ($data as $one) {
			if (isset($one['Amount']['amount']) && isset($one['type'])) {
				$amount = $one['Amount']['amount'];
				$balance[$one['type']] += $amount;
			}
		}
		return $balance;
	}

	/**
	 * Подписка на новые платежи
	 */
	public function createWebHook(): bool {
		if (self::checkWebHooks()) {
			return false;
		}
		$url = 'https://enter.tochka.com/uapi/webhook/' . $this->apiVersion . '/' . $this->clientId;
		$authorizationToken = 'Bearer ' . $this->accessToken;
		$data = [
			'webhooksList' => $this->webhooksList,
			'url' => $this->webhookUrl,
		];
		$jsonData = json_encode($data);
		$options = array(
			CURLOPT_URL => $url,
			CURLOPT_CUSTOMREQUEST => 'PUT',
			CURLOPT_POSTFIELDS => $jsonData,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_HTTPHEADER => [
				'Authorization: ' . $authorizationToken,
				'Content-Type: application/json'
			],
		);
		curl_setopt_array($this->ch, $options);
		$response = curl_exec($this->ch);
		$data = json_decode($response, true);
		if (isset($data['Data']) && isset($data['Data']['webhooksList'])) {
			$ResponsesList = $data['Data']['webhooksList'];
			$incoming = count($this->webhooksList);
			foreach ($this->webhooksList as $webhook) {
				if (in_array($webhook, $ResponsesList)) {
					$incoming--;
				}
			}
			return $incoming == 0;
		}
		return false;
	}

	/**
	 * Отписка
	 */
	public function deleteWebHook(): bool {
		$url = 'https://enter.tochka.com/uapi/webhook/' . $this->apiVersion . '/' . $this->clientId;
		$authorizationToken = 'Bearer ' . $this->accessToken;
		$options = array(
			CURLOPT_URL => $url,
			CURLOPT_CUSTOMREQUEST => 'DELETE',
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_HTTPHEADER => [
				'Authorization: ' . $authorizationToken,
			],
		);
		curl_setopt_array($this->ch, $options);
		$response = curl_exec($this->ch);
		$data = json_decode($response, true);
		if (isset($data['Data']) && isset($data['Data']['result'])) {
			return $data['Data']['result'] == '1';
		}
		return false;
	}

	/**
	 * Проверка подписок
	 */
	public function checkWebHooks(): bool {
		$url = 'https://enter.tochka.com/uapi/webhook/' . $this->apiVersion . '/' . $this->clientId;
		$authorizationToken = 'Bearer ' . $this->accessToken;
		$options = array(
			CURLOPT_URL => $url,
			CURLOPT_CUSTOMREQUEST => 'GET',
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_HTTPHEADER => [
				'Authorization: ' . $authorizationToken,
			],
		);
		curl_setopt_array($this->ch, $options);
		$response = curl_exec($this->ch);
		$data = json_decode($response, true);
		if (isset($data['Data']) && isset($data['Data']['webhooksList']) && isset($data['Data']['url'])) {
			$ResponsesList = $data['Data']['webhooksList'];
			$incoming = count($this->webhooksList);
			foreach ($this->webhooksList as $webhook) {
				if (in_array($webhook, $ResponsesList)) {
					$incoming--;
				}
			}
			return $incoming == 0 && $data['Data']['url'] == $this->webhookUrl;
		}
		return false;
	}

	/**
	 * Метод обработки уведомления о платеже от "Точка банк"
	 */
	public function incomingPaymentNotification(string $jwt): void {
		$data = $this->decodeJWT($jwt);
		if (!isset($data['SidePayer']) || !isset($data['SideRecipient'])) {
			throw new Error('Ошибка обработки уведомления о платеже');
		}
		$payerAccount = $data['SidePayer']['account'];
		$info = $data['SideRecipient'];
		$recipientAccount = $info['account'];
		$amount = $info['amount'];
		$notify =
			$payerAccount !== $recipientAccount &&
			in_array($recipientAccount, $this->incomingPaymentAccounts) &&
			(
				!$this->incomingPaymentAmountLimit ||
				(int)$amount <= $this->incomingPaymentAmountLimit
			);
		if ($notify) {
			$currency = $info['currency'] == 'RUB' ? '₽' : ' ' . $info['currency'];
			$comment = $data['purpose'] ?? '';
			$comment = $this->parseIncomingPaymentComment($comment);
			$message = '💰 <b>' . $this->formatAmount($amount) . "</b>$currency на Точку\r\n$comment";
			$this->telegramBotSendMessage($message);
			$this->incomingPaymentLoging($jwt, $message);
		}
	}

	/**
	 * Пишем полученные данные в лог
	 */
	private function incomingPaymentLoging(string $jwt, string $message):void {
		if($this->incomingPaymentLog){
			$file = $this->incomingPaymentLogFile;
			$date = date('Y-m-d H-i-s');
			$text = "\r\n".$date."\r\n".$jwt."\r\n";
			if($message){
				$text .= "$message\r\n";
			}
			file_put_contents($file, $text, FILE_APPEND);
		}
	}

	private function parseIncomingPaymentComment($originalComment): string {
		$comment = $originalComment;
		$str1 = 'Перевод по номеру телефона';
		$str2 = 'Отправитель';
		$str3 = 'через СБП.';
		$str4 = 'Сообщение получателю:';
		$check1 = strpos($comment, $str1);
		$check2 = strpos($comment, $str2);
		if (
			$check1 !== false &&
			$check1 == 0 &&
			$check2 > 0

		) {
			$comment = substr($comment, strlen($str1));
			$pos = strpos($comment, $str2);
			$phone = substr($comment, 0, $pos);
			$comment = substr($comment, $pos + strlen($str2));
			$fio=$comment;
			$additionalMessage='';
			$method='';
			$pos = strpos($comment, $str4);
			if($pos){
				$additionalMessage=substr($comment, strlen($str4)+$pos);
				$comment=substr($comment, 0, $pos);
			}
			$pos = strpos($comment, $str3);
			if($pos){
				$fio=substr($comment, 0, $pos);
				$method=substr($comment, $pos,  -1);
			}
			$fio=ucwords(mb_convert_case(trim($fio), MB_CASE_TITLE));
			$method=$this->mb_ucfirst(trim($method) );
			$phone=trim($phone);
			$additionalMessage=trim($additionalMessage);
			return "$fio\r\n$phone\r\n<i>$additionalMessage</i>";
		}
		return $originalComment;
	}

	/**
	 * Первую букву в слове в верхний регистр
	 */
	private function mb_ucfirst($string): string {
		$encoding='UTF-8';
		$firstChar = mb_strtoupper(mb_substr($string, 0, 1, $encoding), $encoding);
		$rest = mb_substr($string, 1, null, $encoding);
		return $firstChar . $rest;
	}

	/**
	 * Метод отправки сообщения через телеграм Бота
	 * @param string $message Текст сообщения, допустима HTML разметка
	 */
	private function telegramBotSendMessage(string $message): void {
		if (!$this->telegramBotToken || !$this->telegramBotTokenChannelId) {
			return;
		}
		$url = 'https://api.telegram.org/bot' . $this->telegramBotToken . '/sendMessage';
		$data = array(
			'chat_id' => $this->telegramBotTokenChannelId,
			'text' => $message,
			'parse_mode' => 'HTML',
			'disable_notification' => false,
		);
		$options = array(
			CURLOPT_URL => $url,
			CURLOPT_POST => true,
			CURLOPT_POSTFIELDS => $data,
			CURLOPT_RETURNTRANSFER => true
		);
		curl_setopt_array($this->ch, $options);
		curl_exec($this->ch);
	}

	/**
	 * Красивые суммы
	 */
	private function formatAmount($amount) {
		$amount = preg_replace('/\D\.,/', '', $amount);
		$amount = number_format($amount, 2, '.', ' ');
		if (strpos($amount, '.00') !== false) {
			$amount = str_replace('.00', '', $amount);
		}
		return $amount;
	}
}
