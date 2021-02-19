<?php
require_once 'src/BankCardInfo.php';
require_once 'src/PhoneRegion.php';
require_once 'src/IpRegion.php';

$back = new \addtran\BankCardInfo();
//var_dump($back->setCartId(6236984980000019007)->getBankCardInfo());

$phone = new \addtran\PhoneRegion();
//var_dump($phone->setPhone(17630247125)->getRegion());

$ip = new \addtran\IpRegion();

var_dump($ip->setIp("123.15.54.250")->getIpAddress());
var_dump($ip->setIp("123.15.54.250")->getCountry());
var_dump($ip->setIp("123.15.54.250")->getProvince());
var_dump($ip->setIp("123.15.54.250")->getCity());
