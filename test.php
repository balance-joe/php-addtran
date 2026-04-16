<?php

use Linqiao\Addtran\BankCardInfo;
use Linqiao\Addtran\IpRegion;
use Linqiao\Addtran\PhoneRegion;

require_once 'src/BankCardInfo.php';
require_once 'src/PhoneRegion.php';
require_once 'src/IpRegion.php';

// $back = new BankCardInfo();
// var_dump($back->setCartId(6236984981800029137)->getBankCardInfo());

// $phone = new PhoneRegion();
// var_dump($phone->setPhone(17630257215)->getRegion());

$ip = new IpRegion();
$ip_address = "39.162.21.14";
var_dump($ip->setIp($ip_address)->getIpAddress());
var_dump($ip->setIp($ip_address)->getCountry());
var_dump($ip->setIp($ip_address)->getProvince());
var_dump($ip->setIp($ip_address)->getCity());
