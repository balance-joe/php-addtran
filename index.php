<?php
require_once 'src/BankCardInfo.php';
require_once 'src/PhoneRegion.php';

$back = new \addtran\BankCardInfo();
//var_dump($back->setCartId(6236984980000019007)->getBankCardInfo());

$phone = new \addtran\PhoneRegion();
var_dump($phone->setPhone(17630247125)->getRegion());
var_dump($phone->setPhone(17630247125)->getProvince());
var_dump($phone->setPhone(17630247125)->getCity());
var_dump($phone->setPhone(17630247125)->getSp());
