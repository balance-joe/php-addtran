# php-addtran
查询 手机号归属地/银行卡信息/IP地址

[![Latest Stable Version](https://img.shields.io/packagist/v/shitoudev/phone-location.svg)](https://packagist.org/packages/shitoudev/phone-location)
[![Build Status](https://travis-ci.org/shitoudev/phone-location.svg?style=flat-square&branch=master)](https://travis-ci.org/shitoudev/phone-location)
[![Minimum PHP Version](https://img.shields.io/badge/php-%3E%3D%205.6-8892BF.svg)](https://php.net/)

### Installation
```
composer require "linqiao/php-addtran:^0.1"
```
### Use
```
<?php

// composer 方式安装
// include './vendor/autoload.php';
use Linqiao\Addtran\BankCardInfo;
use Linqiao\Addtran\PhoneRegion;
use Linqiao\Addtran\IpRegion;


// 非 composer 方式安装的，引入文件
require_once 'src/BankCardInfo.php';
require_once 'src/PhoneRegion.php';
require_once 'src/IpRegion.php';
	
$back = new BankCardInfo();
print_r($back->setCartId(6236984981800029137)->getBankCardInfo());
$phone = new PhoneRegion();
print_r($phone->setPhone(17630257215)->getRegion());
$ip = new IpRegion();

print_r($ip->setIp("123.15.54.250")->getIpAddress());

// Output;

bank info:
array(5) {
  ["card_type_name"]=>
  string(9) "信用卡"
  ["bank_name"]=>
  string(24) "中国邮政储蓄银行"
  ["bank_code"]=>
  string(4) "PSBC"
  ["bank_img"]=>
  string(51) "https://apimg.alipay.com/combo.png?d=cashier&t=PSBC"
  ["cart_id"]=>
  int(6236984981800029137)
}

ip address:
array(4) {
  ["tel_address"]=>
  string(20) "河南 新乡 联通"
  ["province"]=>
  string(6) "河南"
  ["city"]=>
  string(6) "新乡"
  ["sp"]=>
  string(6) "联通"
}

phone address:
string(26) "中国 河南省 郑州市"
string(6) "中国"
string(9) "河南省"
string(9) "郑州市"
```


### 手机号信息来源
[https://github.com/lovedboy/phone](https://github.com/lovedboy/phone)

### License
[MIT license.](https://raw.githubusercontent.com/shitoudev/phone-location/master/LICENSE)