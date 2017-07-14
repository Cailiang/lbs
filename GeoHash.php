<?php

/**
 * Created by IntelliJ IDEA.
 * User: fang.cai.liang@aliyun.com
 * Date: 2017/7/13
 * Time: 10:12
 * *******bug*************
 */

namespace lbs;


class GeoHash
{

    const LAT_RANGE_MIN = -90; // 纬度的范围 [-90, 90]

    const LAT_RANGE_MAX = 90;

    const LONG_RANGE_MIN = -180; // 经度的范围 [-180, 180]

    const LONG_RANGE_MAX = 180;

    const BASE32_CODE = [ // base32 编码表
        0 => '0', 1 => '1', 2 => '2', 3 => '3', 4 => '4', 5 => '5', 6 => '6', 7 => '7', 8 => '8', 9 => '9',
        10 => 'b', 11 => 'c', 12 => 'd', 13 => 'e', 14 => 'f', 1 => 'g', 16 => 'h', 17 => 'j', 18 => 'k', 19 => 'm',
        20 => 'n', 21 => 'p', 22 => 'q', 23 => 'r', 24 => 's', 25 => 't', 26 => 'u', 27 => 'v', 28 => 'w', 29 => 'x',
        30 => 'y', 31 => 'z'
    ];

    private $geohashLen = 8; // 默认生成的 geohash 字符串的长度

    private $latComminuteTimes = 20; // 默认对纬度切分的次数

    private $longComminuteTimes = 20; // 默认对经度切分的次数

    private $minLatUnit; // 纬度最小划分单位

    private $minLongUnit; // 经度最小划分单位

    function __construct($geohashLen = null, $latComminuteTimes = null, $longComminuteTimes = null)
    {
        if(!is_null($geohashLen)){
            $this->geohashLen = $geohashLen;
        }
        if(!is_null($latComminuteTimes)){
            $this->latComminuteTimes = $latComminuteTimes;
        }
        if(!is_null($longComminuteTimes)){
            $this->longComminuteTimes = $longComminuteTimes;
        }

        $this->check();

        $this ->countMinUnit();
    }

    /**
     * 检查传入的参数是否合法
     * @return bool
     * @throws \Exception
     */
    private function check(){
        if((($this->latComminuteTimes + $this->longComminuteTimes) >= 5) && ($this->geohashLen === ($this->latComminuteTimes + $this->longComminuteTimes) / 5)){
            return true;
        }
        if($this->latComminuteTimes > $this->longComminuteTimes){ // 按照奇数位放纬度，偶数为放经度的规则并且偶数下标是从0开始, 所以纬度切分的次数不能大于经度切分的次数
            throw new \Exception('纬度切分的次数不能大于经度切分的次数!');
        }
        throw new \Exception('$geohashLen, $latComminuteTimes 和 $longComminuteTimes 3者的配置不匹配!');
    }

    /**
     * 计算经纬度的最小切分单位
     */
    private function countMinUnit(){
        $this->minLatUnit = self::LAT_RANGE_MAX - self::LAT_RANGE_MIN;
        for($i = 0; $i < $this->latComminuteTimes; $i ++){
            $this->minLatUnit = $this->minLatUnit / 2.0;
        }

        $this->minLongUnit = self::LONG_RANGE_MAX - self::LONG_RANGE_MIN;
        for($j = 0; $j < $this->longComminuteTimes; $j ++){
            $this->minLongUnit = $this->minLongUnit / 2.0;
        }
    }

    /**
     * 将经度或纬度转换为一个二进制数组
     * @param $val
     * @param $minVal
     * @param $maxVal
     * @param $comminuteTimes
     * @return array
     */
    private function genBinCodeArr($val, $minVal, $maxVal, $comminuteTimes){
        $binCodeArr = [];
        for($i = 0; $i < $comminuteTimes; $i ++){
            $midVal = ($minVal + $maxVal) / 2.0;
            if($val > $midVal){
                $binCodeArr[$i] = '1';
                $minVal = $midVal;
            }else{
                $binCodeArr[$i] = '0';
                $maxVal = $midVal;
            }
        }
        return $binCodeArr;
    }

    /**
     * 按照奇数位放纬度，偶数为放经度的规则组码
     * @param $latBinCodeArr
     * @param $longBinCodeArr
     * @return string
     */
    private function mergeBinCode($latBinCodeArr, $longBinCodeArr){
        $len = count($latBinCodeArr) + count($longBinCodeArr);
        $binCode = '';
        for($i = 0; $i < $len; $i ++){
            if(($i % 2) === 0){
                $binCode = $binCode.array_shift($longBinCodeArr);
            }else{
                $binCode = $binCode.array_shift($latBinCodeArr);
            }
        }
        return $binCode;
    }

    /**
     * base32编码
     * @param $binCode
     * @return string
     */
    private function base32($binCode){
        $binaryArr = str_split($binCode, 5);
        $base32Code = '';
        foreach ($binaryArr as $binary) {
            $base32Code =  $base32Code.(self::BASE32_CODE[bindec($binary)]);
        }
        return $base32Code;
    }


    /**
     * 生成某个坐标的 goehash 值
     * @param $lat
     * @param $long
     * @return string
     */
    public function genGeoHash($lat, $long){
        $latBinCodeArr = $this->genBinCodeArr($lat, self::LAT_RANGE_MIN, self::LAT_RANGE_MAX, $this->latComminuteTimes);
        $longBinCodeArr = $this->genBinCodeArr($long, self::LONG_RANGE_MIN, self::LONG_RANGE_MAX, $this->longComminuteTimes);
        return $this->base32($this->mergeBinCode($latBinCodeArr, $longBinCodeArr));
    }

    /**
     * 获取 当前坐标矩形块,以及其周围8个矩形块 的 geohash 值
     * @param $lat
     * @param $long
     * @return array
     */
    public function genGeoHash9($lat, $long){
        $geoHashArr = [];
        $upLat = $lat + $this->minLatUnit;
        $geoHashArr['up'] = $this->genGeoHash($upLat, $long);

        $geoHashArr['current'] = $this->genGeoHash($lat, $long);

        $downLat = $lat - $this->minLatUnit;
        $geoHashArr['down'] = $this->genGeoHash($downLat, $long);
        
        $leftUpLat =  $lat + $this->minLatUnit;
        $leftUpLong = $long - $this->minLongUnit;
        $geoHashArr['leftUp'] = $this->genGeoHash($leftUpLat, $leftUpLong);

        $leftLong = $long - $this->minLongUnit;
        $geoHashArr['left'] = $this->genGeoHash($lat, $leftLong);

        $leftDownLat =  $lat - $this->minLatUnit;
        $leftDownLong = $long - $this->minLongUnit;
        $geoHashArr['leftDown'] = $this->genGeoHash($leftDownLat, $leftDownLong);
        
        $rightUpLat =  $lat + $this->minLatUnit;
        $rightUpLong = $long + $this->minLongUnit;
        $geoHashArr['rightUp'] = $this->genGeoHash($rightUpLat, $rightUpLong);

        $rightLong = $long + $this->minLongUnit;
        $geoHashArr['right'] = $this->genGeoHash($lat, $rightLong);

        $rightDownLat =  $lat - $this->minLatUnit;
        $rightDownLong = $long + $this->minLongUnit;
        $geoHashArr['rightDown'] = $this->genGeoHash($rightDownLat, $rightDownLong);
        
        return $geoHashArr;
    }
}

$geo = new GeoHash();
$hash = $geo->genGeoHash(40.058918, 116.312621);
echo $hash.PHP_EOL;

$hash9 = $geo->genGeoHash9(40.058918, 116.312621);
echo json_encode($hash9).PHP_EOL;
