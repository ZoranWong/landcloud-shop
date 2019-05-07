<?php

namespace app\api\controller;

use app\common\controller\Api;
use app\common\model\Advertisement;
use app\common\model\AdvertPosition;

class Advert extends Api
{
    /**
     *  获取广告位列表
     * User:tianyu
     * @return mixed
     */
    public function positionList()
    {
        $positionModel = new AdvertPosition();
        return $positionModel->getOptionsList(input('page/d',1), input('limit/d',5));
    }


    /**
     *
     *  获取广告列表
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getAdvertList()
    {
        $code = input('code/s', '');
        if (!$code) return error_code(10051);
        $advertModel = new Advertisement();
        return $advertModel->getAdvertList($code, input('page/d',1), input('limit/d',5));
    }

}