<?php

namespace Linqiao\Addtran;

class IpRegion
{
    const IPV4_VERSION_NO = 4;
    const IPV6_VERSION_NO = 6;
    const XDB_HEADER_LENGTH = 256;
    const VECTOR_INDEX_ROWS = 256;
    const VECTOR_INDEX_COLS = 256;
    const VECTOR_INDEX_SIZE = 8;

    /**
     * @var string
     */
    private $dbFileV4;

    /**
     * @var string
     */
    private $dbFileV6;

    /**
     * @var string|null
     */
    private $contentBuffV4 = null;

    /**
     * @var string|null
     */
    private $contentBuffV6 = null;

    /**
     * ipRegion info
     */
    private $ip = '';
    private $ip_address = '';
    private $ip_address_list = array();
    private $country = '';
    private $province = '';
    private $city = '';
    private $isp = '';
    private $isoCode = '';

    /**
     * @param string|null $ipv4DbFile
     * @param string|null $ipv6DbFile
     */
    public function __construct($ipv4DbFile = null, $ipv6DbFile = null)
    {
        $baseDir = __DIR__;

        $this->dbFileV4 = $ipv4DbFile ?: $baseDir . '/ip2region_v4.xdb';
        $this->dbFileV6 = $ipv6DbFile ?: $baseDir . '/ip2region_v6.xdb';
    }

    /**
     * @param string $ip
     * @return $this|null
     */
    public function setIp($ip)
    {
        $this->resetRegion();
        $this->ip = $ip;

        $ipBytes = $this->parseIP($ip);
        if ($ipBytes === null) {
            throw new \InvalidArgumentException("Invalid ip address: {$ip}");
        }

        $version = $this->getVersionMeta($ipBytes);
        $contentBuff = $this->loadContentBuffer($version['version_no'], $version['db_file']);
        $region = $this->searchByBytes($version, $contentBuff, $ipBytes);

        if ($region === '') {
            return null;
        }

        $this->fillRegion($region);

        return $this;
    }

    /**
     * @return string
     */
    public function getIpAddress()
    {
        return $this->ip_address;
    }

    /**
     * @return string
     */
    public function getCountry()
    {
        return $this->country;
    }

    /**
     * @return string
     */
    public function getProvince()
    {
        return $this->province;
    }

    /**
     * @return string
     */
    public function getCity()
    {
        return $this->city;
    }

    /**
     * @return string
     */
    public function getIsp()
    {
        return $this->isp;
    }

    /**
     * @return string
     */
    public function getIsoCode()
    {
        return $this->isoCode;
    }

    /**
     * @return array
     */
    public function getIpAddressList()
    {
        return $this->ip_address_list;
    }

    /**
     * @param string $ipString
     * @return string|null
     */
    private function parseIP($ipString)
    {
        $flag = FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6;
        if (!filter_var($ipString, FILTER_VALIDATE_IP, $flag)) {
            return null;
        }

        return inet_pton($ipString);
    }

    /**
     * @param string $ipBytes
     * @return array
     */
    private function getVersionMeta($ipBytes)
    {
        if (strlen($ipBytes) === 4) {
            return array(
                'version_no' => self::IPV4_VERSION_NO,
                'bytes' => 4,
                'segment_index_size' => 14,
                'db_file' => $this->dbFileV4,
            );
        }

        return array(
            'version_no' => self::IPV6_VERSION_NO,
            'bytes' => 16,
            'segment_index_size' => 38,
            'db_file' => $this->dbFileV6,
        );
    }

    /**
     * @param int $versionNo
     * @param string $dbFile
     * @return string
     */
    private function loadContentBuffer($versionNo, $dbFile)
    {
        if ($versionNo === self::IPV4_VERSION_NO) {
            if ($this->contentBuffV4 === null) {
                $this->contentBuffV4 = $this->loadContentFromFile($dbFile);
            }

            return $this->contentBuffV4;
        }

        if ($this->contentBuffV6 === null) {
            $this->contentBuffV6 = $this->loadContentFromFile($dbFile);
        }

        return $this->contentBuffV6;
    }

    /**
     * @param string $dbFile
     * @return string
     */
    private function loadContentFromFile($dbFile)
    {
        $handle = fopen($dbFile, 'rb');
        if ($handle === false) {
            throw new \RuntimeException("Fail to open xdb file {$dbFile}");
        }

        $verifyError = $this->verifyHandle($handle);
        if ($verifyError !== null) {
            fclose($handle);
            throw new \RuntimeException("Invalid xdb file {$dbFile}: {$verifyError}");
        }

        if (fseek($handle, 0, SEEK_END) === -1) {
            fclose($handle);
            throw new \RuntimeException("Fail to seek xdb file {$dbFile}");
        }

        $size = ftell($handle);
        if ($size === false) {
            fclose($handle);
            throw new \RuntimeException("Fail to stat xdb file {$dbFile}");
        }

        if (fseek($handle, 0) === -1) {
            fclose($handle);
            throw new \RuntimeException("Fail to rewind xdb file {$dbFile}");
        }

        $contentBuff = fread($handle, $size);
        fclose($handle);

        if ($contentBuff === false || strlen($contentBuff) != $size) {
            throw new \RuntimeException("Fail to load xdb file {$dbFile}");
        }

        return $contentBuff;
    }

    /**
     * @param resource $handle
     * @return string|null
     */
    private function verifyHandle($handle)
    {
        $header = $this->loadHeader($handle);
        if ($header === null) {
            return 'failed to load the header';
        }

        if ($header['version'] == 2) {
            $runtimePtrBytes = 4;
        } elseif ($header['version'] == 3) {
            $runtimePtrBytes = $header['runtimePtrBytes'];
        } else {
            return "invalid structure version `{$header['version']}`";
        }

        $stat = fstat($handle);
        if ($stat === false) {
            return 'failed to stat the xdb file';
        }

        $maxFilePtr = (1 << ($runtimePtrBytes * 8)) - 1;
        if ($stat['size'] > $maxFilePtr) {
            return "xdb file exceeds the maximum supported bytes: {$maxFilePtr}";
        }

        return null;
    }

    /**
     * @param resource $handle
     * @return array|null
     */
    private function loadHeader($handle)
    {
        if (fseek($handle, 0) === -1) {
            return null;
        }

        $buff = fread($handle, self::XDB_HEADER_LENGTH);
        if ($buff === false || strlen($buff) != self::XDB_HEADER_LENGTH) {
            return null;
        }

        return array(
            'version' => $this->leGetUint16($buff, 0),
            'indexPolicy' => $this->leGetUint16($buff, 2),
            'createdAt' => $this->leGetUint32($buff, 4),
            'startIndexPtr' => $this->leGetUint32($buff, 8),
            'endIndexPtr' => $this->leGetUint32($buff, 12),
            'ipVersion' => $this->leGetUint16($buff, 16),
            'runtimePtrBytes' => $this->leGetUint16($buff, 18),
        );
    }

    /**
     * @param array $version
     * @param string $contentBuff
     * @param string $ipBytes
     * @return string
     */
    private function searchByBytes(array $version, $contentBuff, $ipBytes)
    {
        if (strlen($ipBytes) != $version['bytes']) {
            throw new \InvalidArgumentException('invalid ip address version');
        }

        $il0 = ord($ipBytes[0]) & 0xFF;
        $il1 = ord($ipBytes[1]) & 0xFF;
        $idx = $il0 * self::VECTOR_INDEX_COLS * self::VECTOR_INDEX_SIZE + $il1 * self::VECTOR_INDEX_SIZE;
        $sPtr = $this->leGetUint32($contentBuff, self::XDB_HEADER_LENGTH + $idx);
        $ePtr = $this->leGetUint32($contentBuff, self::XDB_HEADER_LENGTH + $idx + 4);

        if ($sPtr == 0 || $ePtr == 0) {
            return '';
        }

        $bytes = $version['bytes'];
        $dataOffset = $bytes << 1;
        $idxSize = $version['segment_index_size'];
        $dataLen = 0;
        $dataPtr = 0;
        $l = 0;
        $h = ($ePtr - $sPtr) / $idxSize;

        while ($l <= $h) {
            $m = ($l + $h) >> 1;
            $p = $sPtr + $m * $idxSize;
            $buff = substr($contentBuff, $p, $idxSize);

            if ($this->compareIpBytes($version['version_no'], $ipBytes, $buff, 0) < 0) {
                $h = $m - 1;
            } elseif ($this->compareIpBytes($version['version_no'], $ipBytes, $buff, $bytes) > 0) {
                $l = $m + 1;
            } else {
                $dataLen = $this->leGetUint16($buff, $dataOffset);
                $dataPtr = $this->leGetUint32($buff, $dataOffset + 2);
                break;
            }
        }

        if ($dataLen == 0) {
            return '';
        }

        return substr($contentBuff, $dataPtr, $dataLen);
    }

    /**
     * @param int $versionNo
     * @param string $ipBytes
     * @param string $buff
     * @param int $offset
     * @return int
     */
    private function compareIpBytes($versionNo, $ipBytes, $buff, $offset)
    {
        if ($versionNo === self::IPV4_VERSION_NO) {
            $len = strlen($ipBytes);
            $end = $offset + $len;
            for ($i = 0, $j = $end - 1; $i < $len; $i++, $j--) {
                $left = ord($ipBytes[$i]) & 0xFF;
                $right = ord($buff[$j]) & 0xFF;
                if ($left > $right) {
                    return 1;
                }
                if ($left < $right) {
                    return -1;
                }
            }

            return 0;
        }

        $result = strcmp($ipBytes, substr($buff, $offset, strlen($ipBytes)));
        if ($result < 0) {
            return -1;
        }
        if ($result > 0) {
            return 1;
        }

        return 0;
    }

    /**
     * @param string $region
     */
    private function fillRegion($region)
    {
        $parts = explode('|', $region);

        $this->country = isset($parts[0]) ? $this->normalizeField($parts[0]) : '';
        $this->province = isset($parts[1]) ? $this->normalizeField($parts[1]) : '';
        $this->city = isset($parts[2]) ? $this->normalizeField($parts[2]) : '';
        $this->isp = isset($parts[3]) ? $this->normalizeField($parts[3]) : '';
        $this->isoCode = isset($parts[4]) ? $this->normalizeField($parts[4]) : '';

        $addressParts = array_filter(
            array($this->country, $this->province, $this->city),
            function ($value) {
                return $value !== '';
            }
        );

        $this->ip_address = implode(' ', $addressParts);
        $this->ip_address_list = array(
            'ip_address' => $this->ip_address,
            'country' => $this->country,
            'province' => $this->province,
            'city' => $this->city,
            'isp' => $this->isp,
            'iso_code' => $this->isoCode,
        );
    }

    private function resetRegion()
    {
        $this->ip_address = '';
        $this->ip_address_list = array();
        $this->country = '';
        $this->province = '';
        $this->city = '';
        $this->isp = '';
        $this->isoCode = '';
    }

    /**
     * @param string $value
     * @return string
     */
    private function normalizeField($value)
    {
        $value = trim($value);

        if ($value === '0') {
            return '';
        }

        return $value;
    }

    /**
     * @param string $buffer
     * @param int $offset
     * @return int|string
     */
    private function leGetUint32($buffer, $offset)
    {
        $value = (ord($buffer[$offset])) | (ord($buffer[$offset + 1]) << 8)
            | (ord($buffer[$offset + 2]) << 16) | (ord($buffer[$offset + 3]) << 24);

        if ($value < 0 && PHP_INT_SIZE == 4) {
            $value = sprintf('%u', $value);
        }

        return $value;
    }

    /**
     * @param string $buffer
     * @param int $offset
     * @return int
     */
    private function leGetUint16($buffer, $offset)
    {
        return (ord($buffer[$offset])) | (ord($buffer[$offset + 1]) << 8);
    }

    public function __destruct()
    {
        $this->contentBuffV4 = null;
        $this->contentBuffV6 = null;
    }
}
