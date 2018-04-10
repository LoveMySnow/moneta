<?php
/**
 * Created by PhpStorm.
 * User: chenyuan3
 * Date: 2017/11/22
 * Time: 下午3:38
 */

namespace common\moneta;

class Point
{
    private $x;
    private $y;

    function __construct($x, $y)
    {
        $this->x = $x;
        $this->y = $y;
    }

    /**
     * @param Point $pointA
     * @param Point $pointB
     * @return float
     */
    public static function getPointToPointDistance($pointA, $pointB)
    {
        $a = pow($pointA->x - $pointB->x, 2);
        $b = pow($pointA->y - $pointB->y, 2);
        $c = $a + $b;
        return abs(sqrt($c));
    }
}