<?php
/**
 * Created by PhpStorm.
 * User: wang
 * Date: 2019-06-20
 * Time: 23:20
 */

namespace app\common\model;


class VisitProductCount extends Common
{
    public function setIpAttr(string  $ip)
    {
        return ip2long($ip);
    }

    public function getIpAttr($value)
    {
        return long2ip($value);
    }
}