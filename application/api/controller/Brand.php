<?php

namespace app\api\controller;

use app\common\controller\Api;
use app\common\model\Brand as BrandModel;
use app\common\model\GoodsCat as GoodsCatModel;
use think\model\Collection;

class Brand extends Api
{
    public function getCategories()
    {
        $result = [
            'status' => true,
            'msg' => '获取成功',
            'data' => []
        ];
        $id = input('id');
        if($id) {
            $brand = (new \app\common\model\Brand())->findOrFail($id);
            $result['data'] = $brand->categories;
            return $result;
        }else{
            $result['status'] = true;
            return $result;
        }
    }

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

            /**@var GoodsCatModel $goodsCat*/
            $goodsCat = $goodsCatModel->with(['brands' => function($query) {
                return $query->order('sort', 'asc');
            }])->where(['id' => $categoryId])->find();

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
                $v['logo'] = _sImage($v['logo']);
            }
        }

        $result['data'] = [
            'list' => $list,
            'count' => $count
        ];

        return $result;

    }
}


