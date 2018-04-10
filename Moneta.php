<?php
/**
 * Moneta 英文全称 Mnemosyne 记忆女神
 * 这个项目命名为记忆女神，原因是这个项目记录了中国城市30年的气温变化
 * 当有新的气温是否合法是，记忆女神就可以从之前的记忆中找出规律，来判断温度是否合法
 *
 * Date: 2017/11/21
 * Time: 下午4:49
 * ↖ ↑ ↗
 * ← ○ →
 * ↙ ↓ ↘
 */

namespace common\moneta;

use common\TqtLog;
use daemon\spider\lib\City;
use dao\DirtyPool;
use common\Warning;

class Moneta
{
    private $forecastMonth;
    private $memory;
    private $city;
    private $forecastDate;
    private $dataSource;

    private static $SAFE_DISTANCE = 4.5;
    private static $FIRST_LEVEL_WEIGHT = 1;
    private static $SECOND_LEVEL_WEIGHT = 0.125;
    private static $THIRD_LEVEL_WEIGHT = 0.00625;
    const DAY = 86400;

    /**
     * Moneta constructor.
     *
     * @param $date
     * @param $city City
     * @param $dataSource
     */
    private function __construct($date, $city, $dataSource)
    {
        $this->forecastDate = $date;
        $this->city = $city;
//        $this->mapPoint = MapPoint::getNearestPoint($city->latLng);

        $this->forecastMonth = date("n", strtotime($date));
        $this->dataSource = $dataSource;

        $this->memory = $this->getMonetaData();
    }

    /**
     * 唤醒系统
     *
     * @param $date
     * @param $city
     * @param $dataSource
     * @return Moneta
     * @throws
     */
    public static function wakeUp($date, $city, $dataSource)
    {
        if (empty($date)) {
            throw new \Exception("date not legal", 1);
        }
        return new self($date, $city, $dataSource);
    }

    /**
     * 判读温度是否合法
     *
     * @param $minTemperature
     * @param $maxTemperature
     * @return bool
     */
    public function judge($minTemperature, $maxTemperature)
    {
        if ($minTemperature == $maxTemperature) {
            Warning::collectWarning("MonetaNotice", "【FIND】" . $this->dataSource . " " . $this->city->cityName . " " . $this->city->tqtCode . " " . $this->forecastDate . " temperature is error $minTemperature , $maxTemperature");
            return false;
        }

        if ($minTemperature < -50 || $maxTemperature > 50) {
            Warning::collectWarning("MonetaNotice", "【FIND】" . $this->dataSource . " " . $this->city->cityName . " " . $this->city->tqtCode . " " . $this->forecastDate . " temperature is error $minTemperature , $maxTemperature");
            return false;
        }

        if (strlen($minTemperature) == 0 || strlen($maxTemperature) == 0) {
            Warning::collectWarning("MonetaNotice", "【FIND】" . $this->dataSource . " " . $this->city->cityName . " " . $this->city->tqtCode . " " . $this->forecastDate . " temperature is error $minTemperature , $maxTemperature");
            return false;
        }

        if ($this->needCheck() == false) {
            return true;
        }

        $judgeRet = $this->doJudgeForecast($minTemperature, $maxTemperature);

        if ($judgeRet == false) {
            $this->addToDirtyPool($minTemperature, $maxTemperature);
            Warning::collectWarning("MonetaNotice", '【ERROR】 15 days ' . $this->dataSource . " " . $this->city->cityName . " " . $this->city->tqtCode . " " . $this->forecastDate . "not legal，value :" . $minTemperature . " " . $maxTemperature);
            return false;
        }


        return $judgeRet;
    }

    private function doJudgeForecast($minTemperature, $maxTemperature)
    {
        /**
         * 临界情况，如果数据出错，直接返回成功
         */
        if (empty($this->memory)) {
            //Warning::collectWarning("MonetaNotice", "【ERROR】" . $this->city->tqtCode . "memory is null");
            return true;
        }

        $pointA = new Point($minTemperature, $maxTemperature);
        $sumWeight = 0;
        foreach ($this->memory as $value) {
            $pointB = new Point($value["l"], $value["h"]);

            $distance = Point::getPointToPointDistance($pointA, $pointB);

            if ($distance == 0) {
                return true;
            }
            if ($distance <= self::$SAFE_DISTANCE) {
                $sumWeight += self::getWeight($distance);
            }
        }
        if ($sumWeight < $this->getSafeWeight()) {
            $a = array(
                "city"   => $this->city,
                "date"   => $this->forecastDate,
                "min"    => $minTemperature,
                "max"    => $maxTemperature,
                "weight" => $sumWeight,
            );
            TqtLog::warning("moneta", " find ", $a);
        }
        return $sumWeight >= $this->getSafeWeight();
    }


    private function getMonetaData()
    {
        if (false == $this->needCheck()) {
            return array();
        }
        return MonetaMemory::getMemory($this->city->tqtCode, $this->forecastDate);
    }

    /**
     * @param $min
     * @param $max
     * @return bool
     */
    private function addToDirtyPool($min, $max)
    {
        $data = array(
            'city_code'       => $this->city->tqtCode,
            'city_name'       => $this->city->cityName,
            'forecast_day'    => $this->forecastDate,
            'forecast_month'  => $this->forecastMonth,
            'min_temperature' => $min,
            'max_temperature' => $max,
            "source"          => $this->dataSource,
            'create_time'     => date("Y-m-d H:i:s"),
        );

        $daoDirtyPool = new DirtyPool();
        $daoDirtyPool->insert($data);

        return true;
    }

    private function needCheck()
    {
        return $this->city->isForeign() == false;
    }

    /**
     * 根据预报日志选择权重
     *
     * @return float
     */
    private function getSafeWeight()
    {
        $daysTime = strtotime($this->forecastDate) - strtotime(date("Y-m-d"));
        if ($daysTime == 0) {
            return self::$FIRST_LEVEL_WEIGHT;
        }

        $day = $daysTime / self::DAY;
        if ($day <= 3) {
            return self::$FIRST_LEVEL_WEIGHT;
        } elseif ($day > 3 && $day <= 5) {
            return self::$SECOND_LEVEL_WEIGHT;
        } else {
            return self::$THIRD_LEVEL_WEIGHT;
        }
    }

    /**
     * 获取权重
     *
     * @param $distance
     * @return float|int
     */
    private static function getWeight($distance)
    {
        return 1 / pow($distance, 2);
    }
}