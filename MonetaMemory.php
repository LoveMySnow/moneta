<?php
/**
 * Created by PhpStorm.
 * User: chenyuan3
 * Date: 2018/3/19
 * Time: 下午6:42
 */

namespace common\moneta;


class MonetaMemory
{
    private static $MEMORY_PATH = "/data1/moneta/moneta_brain/";
    private static $MEMORY = array();
    const DAY_NUM = 15;
    const DAY_TIME = 86400;

    public static function getMemory($cityCode, $date)
    {
        if (empty($cityCode) || false == file_exists(self::$MEMORY_PATH . $cityCode)) {
            return array();
        }
        $memory = self::chooseMemory(self::getMemoryDate($date), self::getAllMemory($cityCode));

        if (empty($memory)) {
            return array();
        }

        $temperatureList = array();
        foreach (explode("|", $memory) as $data) {
            if (empty($data)) {
                continue;
            }
            list($min, $max) = explode(",", $data);
            $temperatureList[] = array(
                "l" => $min,
                "h" => $max,
            );
        }

        return $temperatureList;
    }

    /**
     * @param $dateList
     * @param $memory
     * @return bool|string
     */
    private static function chooseMemory($dateList, $memory)
    {
        if (empty($memory)) {
            return false;
        }
        $ret = "";
        foreach ($dateList as $date) {
            if (false == array_key_exists($date, $memory)) {
                continue;
            }
            $ret .= $memory[ $date ] . "|";
        }

        return $ret;
    }

    /**
     * 获取date 前后的日期
     *
     * @param $date
     * @return  array
     */
    private static function getMemoryDate($date)
    {
        $dateList = array();
        $dateTime = strtotime($date);

        for ($i = self::DAY_NUM; $i > 0; $i--) {
            $dateList[] = date("md", $dateTime - ($i * self::DAY_TIME));
        }
        for ($i = 0; $i <= self::DAY_NUM; $i++) {
            $dateList[] = date("md", $dateTime + ($i * self::DAY_TIME));
        }

        return $dateList;
    }

    /**
     * @param $cityCode
     * @return mixed
     */
    private static function getAllMemory($cityCode)
    {
        /**
         * 如果存在就直接返回
         */
        if (array_key_exists($cityCode, self::$MEMORY)) {
            return self::$MEMORY[ $cityCode ];
        }
        /**
         * 主动释放 无用memory
         */
        self::$MEMORY = array();

        $memory = array();
        foreach (explode("\n", file_get_contents(self::$MEMORY_PATH . $cityCode)) as $value) {
            if (empty($value)) {
                continue;
            }
            list($day, $data) = explode("_", $value);

            $memory[ $day ] = $data;
        }
        self::$MEMORY[ $cityCode ] = $memory;

        return self::$MEMORY[ $cityCode ];
    }
}