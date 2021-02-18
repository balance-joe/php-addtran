<?php


namespace addtran;

/**
 * 手机号码归属地
 * 数据文件来源：https://github.com/lovedboy/phone
 */
class PhoneRegion
{

    const DATA_FILE = __DIR__.'/phone.dat';
    protected static $spList = [1=>'移动', 2=>'联通', 3=>'电信', 4=>'电信虚拟运营商', 5=>'联通虚拟运营商', 6=>'移动虚拟运营商'];
    private $_fileHandle = null;
    private $_fileSize = 0;

    protected $tel = '';
    public $tel_address = '';
    public $tel_address_list = [];

    public $province = '';
    public $city = '';
    public $post_code = '';
    public $tel_prefix = '';
    public $sp = '';

    public function __construct()
    {
        $this->_fileHandle = fopen(self::DATA_FILE, 'r');
        $this->_fileSize = filesize(self::DATA_FILE);
    }

    /**
     * 查找单个手机号码归属地信息
     * @param  int $phone
     * @return string
     * @author shitoudev <shitoudev@gmail.com>
     */
    public function setPhone($phone)
     {

         $item = '未知省|未知市|000000|0000|未知运营商';

         if (strlen($phone) != 11) {
             return $item;
         }
         $telPrefix = substr($phone, 0, 7);

         fseek($this->_fileHandle, 4);
         $offset = fread($this->_fileHandle, 4);
         $indexBegin = implode('', unpack('L', $offset));
         $total = ($this->_fileSize - $indexBegin) / 9;

         $position = $leftPos = 0;
         $rightPos = $total;
         while ($leftPos < $rightPos - 1) {
             $position = $leftPos + (($rightPos - $leftPos) >> 1);
             fseek($this->_fileHandle, ($position * 9) + $indexBegin);
             $idx = implode('', unpack('L', fread($this->_fileHandle, 4)));
             // echo 'position = '.$position.' idx = '.$idx;
             if ($idx < $telPrefix) {
                 $leftPos = $position;
             } elseif ($idx > $telPrefix) {
                 $rightPos = $position;
             } else {
                 // 找到数据
                 fseek($this->_fileHandle, ($position * 9 + 4) + $indexBegin);
                 $itemIdx = unpack('Lidx_pos/ctype', fread($this->_fileHandle, 5));
                 $itemPos = $itemIdx['idx_pos'];
                 $type = $itemIdx['type'];
                 fseek($this->_fileHandle, $itemPos);
                 $itemStr = '';
                 while (($tmp = fread($this->_fileHandle, 1)) != chr(0)) {
                     $itemStr .= $tmp;
                 }

                 $item = $itemStr . '|' . self::$spList[$type];
                 break;
             }
         }
         list($this->province, $this->city, $this->post_code, $this->tel_prefix, $this->sp) = explode('|', $item);
         $this->tel_address_list = [
             'tel_address' => $this->tel_address = $this->province . ' ' . $this->city . ' ' . $this->sp,
             'province' => $this->province,
             'city' => $this->city,
             'sp' => $this->sp
         ];
         return $this;
     }
    /**
     * 获取全部地址
     * @return array
     */
    public function getRegion()
    {
        return $this->tel_address_list;
    }

    /**
     * 获取省份
     * @return string
     */
    public function getProvince()
    {
        return $this->province;
    }

    /**
     * 获取市
     * @return string
     */
    public function getCity()
    {
        return $this->city;
    }

    /**
     * @return string
     */
    public function getSp()
    {
        return $this->sp;
    }

    /**
     * 结构方法
     * */
    public function __destruct()
    {
        fclose($this->_fileHandle);
    }



}