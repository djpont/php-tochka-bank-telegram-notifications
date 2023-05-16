<?php

include_once './class.php';
$postData = file_get_contents('php://input');
$bank = new TochkaBank();
$bank->incomingPaymentNotification($postData);
