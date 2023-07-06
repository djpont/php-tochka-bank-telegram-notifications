<?php

include_once './class.php';
$bank = new TochkaBank();

// Авторизуемся
if (isset($_GET['auth'])) {
	$authUrl = $bank->Authorization();
	header("Location: $authUrl");
	exit;
}

// Проверяем авторизацию
if ($bank->checkAuthorization()) {
	echo 'Авторизация прошла успешно.<br>';
	echo 'Проверяю вебхук.<br>';
	if(!$bank->checkWebHooks()){
		echo 'Вебхук требуется обновить.<br>Удаляю вебхук...<br>';
		$bank->deleteWebHook();
		echo 'Создаю новый вебхук...<br>';
		$bank->createWebHook();
		echo 'Новый вебхук создан!<br>';
		exit;
	}
	echo 'Вебхук в порядке.<br>';
	exit;
}

echo 'Требуется авторизация!<br>';
$protocol = stripos($_SERVER['SERVER_PROTOCOL'], 'https') ? 'https://' : 'http://';
$url = $protocol . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
$trimmedUrl = strstr($url, '?auth', true);
echo "<a href='$trimmedUrl'>Авторизоваться</a>";
