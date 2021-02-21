<?php

use Linqiao\Addtran\BankCardInfo;
use Linqiao\Addtran\IpRegion;
use Linqiao\Addtran\PhoneRegion;

require_once 'src/BankCardInfo.php';
require_once 'src/PhoneRegion.php';
require_once 'src/IpRegion.php';

$back = new BankCardInfo();
var_dump($back->setCartId(6236984981800029137)->getBankCardInfo());

$phone = new PhoneRegion();
var_dump($phone->setPhone(17630257215)->getRegion());

$ip = new IpRegion();
var_dump($ip->setIp("123.15.54.250")->getIpAddress());
var_dump($ip->setIp("123.15.54.250")->getCountry());
var_dump($ip->setIp("123.15.54.250")->getProvince());
var_dump($ip->setIp("123.15.54.250")->getCity());
