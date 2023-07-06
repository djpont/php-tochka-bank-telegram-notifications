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
	if(!$bank->checkWebHooks() || isset($_GET['recreatewebhook'])){
		echo 'Вебхук требуется обновить.<br>Удаляю вебхук...<br>';
		$bank->deleteWebHook();
		echo 'Создаю новый вебхук...<br>';
		if($bank->createWebHook()){
			echo 'Новый вебхук создан!<br>';
		}else{
			echo 'Ошибка создания вебхука!<br>';
		}
		exit;
	}
	echo 'Вебхук в порядке.<br>';
	echo "<a href='?recreatewebhook'>Пересоздать вебхук</a>";
	exit;
}

echo 'Требуется авторизация!<br>';
$protocol = stripos($_SERVER['SERVER_PROTOCOL'], 'https') ? 'https://' : 'http://';
$url = $protocol . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
echo "<a href='?auth'>Авторизоваться</a>";
