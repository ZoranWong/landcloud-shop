<?php

namespace app\api\controller;

use app\common\controller\Api;
use app\common\model\Cart as CartModel;
use app\common\model\User as UserModel;
use think\facade\Request;

/**
 * 购物车
 * Class Cart
 * @package app\api\controller
 * @author keinx
 */
class Cart extends Api
{
    /**
     * 单个加入购物车
     * 需要同时保证必选关联产品的添加
     * @return array
     */
    public function add()
    {
        $result = [
            'status' => false,
            'data' => [],
            'msg' => ''
        ];

        if (!input("?param.product_id")) {
            $result['msg'] = '请输入货品id';
            return $result;
        }
        if (!input("?param.nums")) {
            $result['msg'] = '请输入货品数量';
            return $result;
        }


        $type = input('param.type', 1);          //1是累加，2是覆盖


        return model('common/Cart')->add($this->userId, input('product_id'), input('nums'), $type);
    }


    /**
     * 移除购物车
     * @param array ids
     * @return array
     */
    public function del()
    {
        $ids = input('param.ids', "");
        $user_id = $this->userId;

        $result = model('common/Cart')->del($user_id, $ids);
        if ($result) {
            $return_data = array(
                'status' => true,
                'msg' => '移除购物车成功',
                'data' => $result
            );
        } else {
            $return_data = array(
                'status' => false,
                'msg' => '移除购物车失败',
                'data' => $result
            );
        }
        return $return_data;
    }

    /**
     * 批量删除购物车（如失效产品，下架产品）
     * 需要删除关联的产品
     */
    public function batchDel()
    {

    }


    /**
     * 获取购物车列表
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getList()
    {
        $model = new CartModel();
        $ids = Request::param('ids', '');
        $display = Request::param('display', '');
        $area_id = Request::param('area_id', false);
        $point = Request::param('point', 0);
        $coupon_code = Request::param('coupon_code', '');
        $receipt_type = Request::param('receipt_type', 1);
        $area = Request::param('area', null);
        $result = $model->info($this->userId, $ids, $display, $area_id, $point, $coupon_code, $receipt_type, $area);
        return $result;
    }


    /**
     * 设置购物车数量接口
     * @return mixed
     */
    public function setNums()
    {
        $input['user_id'] = $this->userId;
        $input['id'] = input('id');
        $input['nums'] = input('nums', 1);
//        if ($input['nums'] <= 0) {
//            $input['nums'] = 1;
//        }
        $result = model('common/Cart')->setNums($input);
        if (!$result['status']) {
            return $result;
        }
        return model('common/Cart')->info($this->userId, input('param.ids', ""));

    }


    /**
     *
     *  获取购物车数量
     * @return array
     */
//    public function getNumber()
//    {
//        $result = [
//            'status' => true,
//            'msg' => '获取成功',
//            'data' => []
//        ];
//
//        $model = new Model();
//        $where[] = ['user_id', 'eq', $this->userId];
//        $vclass = getSetting('virtual_card_class');
//        if ($vclass) {
//            $where[] = ['g.goods_cat_id', 'neq', $vclass];
//        }
//
//        $cartNums = $model->alias('c')
//            ->where($where)
//            ->join('products p', 'p.id = c.product_id')
//            ->join('goods g', 'g.id = p.goods_id')
//            ->sum('nums');
//
//        $result['data'] = $cartNums;
//
//        return $result;
//    }
    public function getNumber()
    {
        $result = [
            'status' => true,
            'msg' => '获取成功',
            'data' => 0
        ];

        $user = UserModel::with('carts')->find($this->userId);
        if (!empty($user->carts)) {
            $num = 0;
            foreach ($user->carts as $cart) {
                $num += $cart['nums'];
            }
            $result['data'] = $num;
        }

        return $result;
    }
}