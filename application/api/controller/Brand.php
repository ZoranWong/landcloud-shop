<?php
// +----------------------------------------------------------------------
// | JSHOP [ 小程序商城 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2018 http://jihainet.com All rights reserved.
// +----------------------------------------------------------------------
// | Author: tianyu <tianyu@jihainet.com>
// +----------------------------------------------------------------------
namespace app\api\controller;

use app\common\controller\Api;
use app\common\model\Brand as BrandModel;
use app\common\model\GoodsCat as GoodsCatModel;

class Brand extends Api
{

    /**
     *
     *  获取品牌列表
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function brandList()
    {
        $result = [
            'status' => true,
            'msg' => '获取成功',
            'data' => []
        ];

        $field = 'id,name,logo,sort';

        $order = input('param.order', 'sort asc');
        $page = input('param.page', 1);
        $limit = input('param.limit', PAGE_SIZE);

        $categoryId = input('param.category_id');


        if ($categoryId) {
            $goodsCatModel = new GoodsCatModel();
            $brandModel = new BrandModel();

            $goodsCat = $goodsCatModel->with('brands')->where(['id' => $categoryId])->find();

            $list = $goodsCat->brands;

            $list = $brandModel->tableFormat($list);

            $count = count($list);

        } else {
            $brandModel = new BrandModel;

            $list = $brandModel->field($field)->order($order)->page($page, $limit)->select();

            $count = $brandModel->field($field)->count();
        }


        if (!$list->isEmpty()) {
            foreach ($list as &$v) {
                $v['logo_url'] = _sImage($v['logo']);
            }
        }

        $result['data'] = [
            'list' => $list,
            'count' => $count
        ];

        return $result;

    }
}


