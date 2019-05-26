<?php
/**
 * Created by PhpStorm.
 * User: wangzaron
 * Date: 2019/5/26
 * Time: 11:15 PM
 */

namespace app\service;


use Finecho\Logistics\Logistics;

class LogisticsService
{
    protected static $_instance = null;
    /**
     * @var Logistics $logistics
     * */
    protected $logistics =  null;
    protected function __construct()
    {
        $this->logistics = new Logistics(config('logistics.'));
    }

    public static function getInstance()
    {
        return self::$_instance = self::$_instance ? self::$_instance : new LogisticsService();
    }

    public function companies ()
    {
        return $this->logistics->companies();
    }

    public function order($no, $type = null)
    {
        return $this->logistics->order($no, $type);
    }
}