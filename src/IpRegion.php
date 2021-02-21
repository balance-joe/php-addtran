<?php


namespace Linqiao\Addtran;

defined('INDEX_BLOCK_LENGTH') or define('INDEX_BLOCK_LENGTH', 12);
defined('TOTAL_HEADER_LENGTH') or define('TOTAL_HEADER_LENGTH', 8192);

class IpRegion
{
    /**
     * db file handler
     */
    private $dbFileHandler = null;

    /**
     * header block info
     */
    private $HeaderSip = null;
    private $HeaderPtr = null;
    private $headerLen = 0;

    /**
     * super block index info
     */
    private $firstIndexPtr = 0;
    private $lastIndexPtr = 0;
    private $totalBlocks = 0;

    /**
     * ipRegion info
     * */
    private $ip = '';
    private $ip_address = '';
    private $ip_address_list = [];
    private $country = '';
    private $province = '';
    private $region = '';
    private $city = '';

    /**
     * for memory mode only
     *  the original db binary string
     */
    private $dbBinStr = null;
    private $dbFile = null;

    /**
     * construct method
     *
     * @param string ip2regionFile
     */
    public function __construct()
    {
        $this->dbFile =  __DIR__ . '/ip2region.db';
    }

    /**
     * get the data block associated with the specified ip with b-tree search algorithm
     * @Note: not thread safe
     *
     * @param string ip
     * @return  Mixed Array for NULL for any error
     */
    public function setIp($ip)
    {
        $this->ip = $ip;

        if (is_string($ip)) $ip = self::safeIp2long($ip);
        //check and load the header
        if ($this->HeaderSip == null) {
            //check and open the original db file
            if ($this->dbFileHandler == null) {
                $this->dbFileHandler = fopen($this->dbFile, 'r');
                if ($this->dbFileHandler == false) {
                    throw new Exception("Fail to open the db file {$this->dbFile}");
                }
            }
            fseek($this->dbFileHandler, 8);
            $buffer = fread($this->dbFileHandler, TOTAL_HEADER_LENGTH);

            //fill the header
            $idx = 0;
            $this->HeaderSip = array();
            $this->HeaderPtr = array();
            for ($i = 0; $i < TOTAL_HEADER_LENGTH; $i += 8) {
                $startIp = self::getLong($buffer, $i);
                $dataPtr = self::getLong($buffer, $i + 4);
                if ($dataPtr == 0) break;
                $this->HeaderSip[] = $startIp;
                $this->HeaderPtr[] = $dataPtr;
                $idx++;
            }
            $this->headerLen = $idx;
        }

        //1. define the index block with the binary search
        $l = 0;
        $h = $this->headerLen;
        $sptr = 0;
        $eptr = 0;
        while ($l <= $h) {
            $m = (($l + $h) >> 1);

            //perfetc matched, just return it
            if ($ip == $this->HeaderSip[$m]) {
                if ($m > 0) {
                    $sptr = $this->HeaderPtr[$m - 1];
                    $eptr = $this->HeaderPtr[$m];
                } else {
                    $sptr = $this->HeaderPtr[$m];
                    $eptr = $this->HeaderPtr[$m + 1];
                }

                break;
            }

            //less then the middle value
            if ($ip < $this->HeaderSip[$m]) {
                if ($m == 0) {
                    $sptr = $this->HeaderPtr[$m];
                    $eptr = $this->HeaderPtr[$m + 1];
                    break;
                } elseif ($ip > $this->HeaderSip[$m - 1]) {
                    $sptr = $this->HeaderPtr[$m - 1];
                    $eptr = $this->HeaderPtr[$m];
                    break;
                }
                $h = $m - 1;
            } else {
                if ($m == $this->headerLen - 1) {
                    $sptr = $this->HeaderPtr[$m - 1];
                    $eptr = $this->HeaderPtr[$m];
                    break;
                } elseif ($ip <= $this->HeaderSip[$m + 1]) {
                    $sptr = $this->HeaderPtr[$m];
                    $eptr = $this->HeaderPtr[$m + 1];
                    break;
                }
                $l = $m + 1;
            }
        }

        //match nothing just stop it
        if ($sptr == 0) return null;

        //2. search the index blocks to define the data
        $blockLen = $eptr - $sptr;
        fseek($this->dbFileHandler, $sptr);
        $index = fread($this->dbFileHandler, $blockLen + INDEX_BLOCK_LENGTH);

        $dataPtr = 0;
        $l = 0;
        $h = $blockLen / INDEX_BLOCK_LENGTH;
        while ($l <= $h) {
            $m = (($l + $h) >> 1);
            $p = (int)($m * INDEX_BLOCK_LENGTH);
            $sip = self::getLong($index, $p);
            if ($ip < $sip) {
                $h = $m - 1;
            } else {
                $eip = self::getLong($index, $p + 4);
                if ($ip > $eip) {
                    $l = $m + 1;
                } else {
                    $dataPtr = self::getLong($index, $p + 8);
                    break;
                }
            }
        }

        //not matched
        if ($dataPtr == 0) return null;

        //3. get the data
        $dataLen = (($dataPtr >> 24) & 0xFF);
        $dataPtr = ($dataPtr & 0x00FFFFFF);

        fseek($this->dbFileHandler, $dataPtr);
        $data = fread($this->dbFileHandler, $dataLen);

        list($this->country, $this->region, $this->province, $this->city) = explode('|', substr($data, 4));
        $this->ip_address_list = [
            'ip_address' => $this->ip_address = $this->country . ' ' . $this->province . ' ' . $this->city,
            'country' => $this->country,
            'province' => $this->province,
            'city' => $this->city
        ];

        return $this;

    }

    /**
     * safe self::safeIp2long function
     *
     * @param string ip
     *
     * @return false|int|string
     */
    public static function safeIp2long($ip)
    {
        $ip = ip2long($ip);
        // convert signed int to unsigned int if on 32 bit operating system
        if ($ip < 0 && PHP_INT_SIZE == 4) {
            $ip = sprintf("%u", $ip);
        }
        return $ip;
    }

    /**
     * read a long from a byte buffer
     *
     * @param string b
     * @param integer offset
     * @return int|string
     */
    public static function getLong($b, $offset)
    {
        $val = (
            (ord($b[$offset++])) |
            (ord($b[$offset++]) << 8) |
            (ord($b[$offset++]) << 16) |
            (ord($b[$offset]) << 24)
        );
        // convert signed int to unsigned int if on 32 bit operating system
        if ($val < 0 && PHP_INT_SIZE == 4) {
            $val = sprintf("%u", $val);
        }
        return $val;
    }

    /**
     * 获取ip地址
     * @return string
     */
    public function getIpAddress()
    {
        return $this->ip_address;
    }

    /**
     * 国籍
     * @return string
     */
    public function getCountry()
    {
        return $this->country;
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
     * destruct method, resource destroy
     */
    public function __destruct()
    {
        if ($this->dbFileHandler != null) {
            fclose($this->dbFileHandler);
        }
        $this->dbBinStr = null;
        $this->HeaderSip = null;
        $this->HeaderPtr = null;
    }
}