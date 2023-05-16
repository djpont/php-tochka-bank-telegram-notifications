<?php

include_once './class.php';

if(isset($_GET['code'])){
	$code = $_GET['code'];
	$bank = new TochkaBank();
	$redirect = $bank->receiveAuthCode($code);
	header("Location: $redirect");
	exit;
}

