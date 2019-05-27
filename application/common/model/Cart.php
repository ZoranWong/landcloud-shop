<?php
// +----------------------------------------------------------------------
// | labgic [ 小程序商城 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2018 http://jihainet.com All rights reserved.
// +----------------------------------------------------------------------
// | Author: mark <jima@jihainet.com>
// +----------------------------------------------------------------------

namespace app\common\model;

use app\common\model\Goods as GoodsModel;
use think\Db;
use think\db\Query;
use think\Exception;
use think\facade\Log;
use think\model\Collection;

/**
 * 购物车
 * Class Cart
 * @package app\common\model
 * @author keinx
 */
class Cart extends Common
{
    /**
     * 关联货品
     * @return \think\model\relation\HasOne
     */
    public function products()
    {
        return $this->hasOne('Products', 'id', 'product_id');
    }

    /**
     * 单个加入购物车
     * @param $user_id
     * @param $product_id
     * @param $nums
     * @param $type
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function add($user_id, $product_id, $nums, $type)
    {
        $result = [
            'status' => false,
            'data' => '',
            'msg' => ''
        ];

        $goodsModel = new GoodsModel();
        $goods = $goodsModel->with('relateGoods')->find($product_id);
        if (is_null($goods) || !($goods instanceof GoodsModel)) {
            $result['msg'] = "没有找到ID为{$product_id}的商品";
            return $result;
        }

        $where[] = array('product_id', 'eq', $product_id);
        $where[] = array('user_id', 'eq', $user_id);

        $this->startTrans();

        try {
            $cartInfo = $this->where($where)->find();

            if ($cartInfo) {
                if ($type == 1) {
                    $cartInfo->nums = $nums + $cartInfo['nums'];
                } else {
                    $cartInfo->nums = $nums;
                }
                $cartInfo->save();
                $result['data'] = $cartInfo;
            } else {
                $data['product_id'] = $product_id;
                $data['nums'] = $nums;
                $data['user_id'] = $user_id;
                $cartInfo = $this->create($data);
                $result['data'] = $cartInfo;
            }

            if (!empty($goods->relateGoods)) {
                $userCarts = $this->where('user_id', $user_id)->column('id', 'product_id');
                $userCartGoodsIds = array_keys($userCarts);
                $relateGoodsIds = $goods->relateGoods(true)->column('lc_goods.id');
                $needAddGoodsIds = array_diff($relateGoodsIds, $userCartGoodsIds);
                $needUpdateGoodsIds = array_intersect($relateGoodsIds, $userCartGoodsIds);
                $data = [];
                foreach ($needAddGoodsIds as $item) {
                    $data[] = ['user_id' => $user_id, 'product_id' => $item, 'nums' => $cartInfo['nums']];
                }
                $this->saveAll($data, false);
                $needUpdateCarts = [];
                foreach ($needUpdateGoodsIds as $needUpdateGoodsId) {
                    $needUpdateCarts[] = $userCarts[$needUpdateGoodsId];
                }
                $this->whereIn('id', $needUpdateCarts)->setInc('nums', $nums);
            }

            $this->commit();

            $result['msg'] = '加入成功';
            $result['status'] = true;
        } catch (Exception $exception) {
            $this->rollback();
            $result['msg'] = $exception->getMessage();
        }

        return $result;
    }

    /**
     * 移除购物车
     * @param $ids
     * @param bool $user_id
     * @return int
     */
    public function del($user_id, $ids = "")
    {
        $where[] = array('user_id', 'eq', $user_id);
        if ($ids != "") {
            $where[] = array('id', 'in', $ids);
        }

        $res = $this->where($where)
            ->delete();
        return $res;
    }

    /**
     * 获取购物车列表
     * @param $userId
     * @param string $id
     * @param string $display
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getList($userId, $id = '', $display = '', $keyword = null)
    {
        $result = array(
            'status' => false,
            'data' => [],
            'msg' => ''
        );
        $where[] = ['user_id', 'eq', $userId];
        if ($id != '' && $display == '') {
            $where[] = ['id', 'in', $id];
        }

//        $query = $this->where($where)->order('id', 'desc');
        $query = $this->where($where)->where(function (Query $query) use ($keyword) {
            if (!is_null($keyword)) {
                $query->where('name', 'like', '%' . $keyword . '%')
                    ->whereOr('erp_goods_id', $keyword)
                    ->whereOr('bn', $keyword);
            }
        })->order('id', 'desc');

        $token = input('token', '');//token值 会员登录后传

        $list = $query->column('*', 'product_id');

        $cartGoodsIds = array_keys($list);
        $needExcludeGoods = array();

        $goodsModel = new Goods();
        foreach ($list as $k => $v) {
            $goodsInfo = $goodsModel->getGoodsDetial($v['product_id'], '*', $token);
            if (!$goodsInfo['status']) {
                unset($list[$k]);
                $this::destroy($v['id']);
                continue;
            }
            $list[$k]['detail'] = $goodsInfo['data'];

            if (isset($list[$k]['detail']['relate_goods']) && count($list[$k]['detail']['relate_goods'])) {
                $relateGoods = $list[$k]['detail']['relate_goods'];
                foreach ($relateGoods as $relateGood) {
                    if ($relateGood['pivot']['required'] && in_array($relateGood['id'], $cartGoodsIds)) {
                        if ($list[$relateGood['id']]['nums'] > 1) {
                            $list[$relateGood['id']]['nums'] -= 1;
                            $list[$k]['set'][] = $relateGood;
                        } else {
                            $list[$k]['set'][] = $relateGood;
                            $needExcludeGoods[] = $relateGood['id'];
                        }
                    }
                }
            }

            //如果传过来了购物车数据，就算指定的购物车的数据，否则，就算全部购物车的数据
            if ($id != '') {
                $array_ids = explode(',', $id);
                if (in_array($v['id'], $array_ids)) {
                    $list[$k]['is_select'] = true;
                } else {
                    $list[$k]['is_select'] = false;
                }
            } else {
                $list[$k]['is_select'] = true;
            }
            //判断商品是否已收藏
            $list[$k]['isCollection'] = model('common/GoodsCollection')->check($v['user_id'], $v['product_id']);
        }
//        foreach ($needExcludeGoods as $needExcludeGoodId) {
//            unset($list[$needExcludeGoodId]);
//        }
        $list = array_values($list);

        $data['list'] = $list;
        $result['data'] = $data;
        $result['status'] = true;
        return $result;
    }

    protected function getGoodsAmount($goods, $num, $userId, $area = null)
    {
        $amount = 0;

        if(!$area) {
            $where[] = ['user_id', 'eq', $userId];
            $where[] = ['is_def', 'eq', 1];
            $userShip = (new UserShip())->with('area')->where($where)->order('utime desc')->find();
            if($userShip) {
                $areas = $userShip->area->getParentArea();
                foreach ($areas as $a) {
                    if($a['info']['parent_id'] == 0) {
                        $area = $a['info']['id'];
                        break;
                    }
                }
            }
        }

        if ($goods['price_levels']) {
            /** @var Collection $levels * */
            $levels = &$goods['price_levels'];
//            Log::debug('----------------------- levels ---------------- area = '.$area . '--------' . json_encode($levels));
            if (!$area) {
                $area = "";
            }
            $goods->levels($levels, $area);
            $price = $goods['promotion_price'] > 0 ? ($goods['preferential_price'] > 0 ? ($goods['preferential_price'] < $goods['promotion_price'] ?
                $goods['preferential_price'] : $goods['promotion_price']) : $goods['promotion_price']) : $goods['price'];
            $priceStruct = [];
            foreach ($levels as $level) {
                if ($num >= $level['buy_num']) {
                    $n = (int)($num / $level['buy_num']);
                    $fee = $level['price'] * $n;
                    $amount += $fee;
                    $num = $num % $level['buy_num'];
                    $priceStruct[] = $level;
                    $level['count'] = $n;
                    $level['pack'] = true;
                    $level['amount'] = number_format($fee, 2);
                    $level['price'] = number_format($level['price'], 2);
                }
            }
            if($num > 0) {
                $level0 = ['pack' => false, 'count' => $num,
                    'amount' => number_format($price * $num, 2),
                    'price' => number_format($price, 2),
                    'buy_num' => 1];
                $priceStruct[] = $level0;
                $amount += $price * $num;
            }

        }
        return [$amount, $priceStruct];
    }

    /**
     * @param $userId
     * @param string $id
     * @param string $display
     * @param bool $area_id
     * @param int $point
     * @param string $coupon_code
     * @param int $receipt_type
     * @param null $area
     * @param null $keyword
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function info($userId, $id = '', $display = '', $area_id = false, $point = 0, $coupon_code = "", $receipt_type = 1, $area = null, $keyword = null)
    {
        $result = [
            'status' => false,
            'data' => [
                'user_id' => $userId,
                'list' => [],
                'goods_amount' => 0,         //商品总金额
                'amount' => 0,              //总金额
                'order_pmt' => 0,           //订单促销金额            单纯的订单促销的金额
                'goods_pmt' => 0,           //商品促销金额            所有的商品促销的总计
                'coupon_pmt' => 0,          //优惠券优惠金额
                'promotion_list' => [],      //促销列表
                'cost_freight' => 0,        //运费
                'weight' => 0,               //商品总重
                'coupons' => [],
                'point' => $point,              //在刚开始一定要校验积分是否可以使用，
                'point_money' => 0              //在结尾一定要算积分可以抵扣多少金额
            ],
            'msg' => ""
        ];
        $cartList = $this->getList($userId, $id, $display, $keyword);
        if (!$cartList['status']) {
            $result['msg'] = $cartList['msg'];
            return $result;
        } else {
            $result['data']['list'] = $cartList['data']['list'];
        }
        $carts = [];
        //算订单总金额
        foreach ($result['data']['list'] as $k => $v) {
            //库存不足不计算金额不可以选择
//            if ($v['nums'] > $v['products']['stock']) {
//                $result['data']['list'][$k]['is_select'] = false;
//                $v['is_select'] = false;
//            }
            $carts[] = $v;
            //单条商品总价
            list($amount, $priceStruct) = $this->getGoodsAmount($v['detail'], $v['nums'], $userId, $area);
            $result['data']['list'][$k]['amount'] = $amount;
            $result['data']['list'][$k]['prices'] = $priceStruct;
        }

//        echo json_encode($result['data']['list']);exit;

        //运费判断
        if ($receipt_type == 1) {
            if ($area_id) {
                $shipModel = new Ship();
                $result['data']['cost_freight'] = $shipModel->getShipCost($area_id, $result['data']['weight'], $result['data']['goods_amount']);
                $result['data']['amount'] += $result['data']['cost_freight'];
            }
        }

        //接下来算订单促销金额
        $promotionModel = new Promotion();
        $result['data'] = $promotionModel->toPromotion($result['data']);
        $couponCodes = [];
        if ($coupon_code === "") {
            $list = &$result['data']['list'];
            foreach ($list as $key => &$item) {
                $list[$key]['use_coupon'] = null;
                if (isset($item['promotion_list']) && $item['promotion_list'] && $item['is_select']) {
                    foreach ($item['promotion_list'] as $promotion) {
                        if(!isset($item['use_coupon'])) {
                            foreach ($promotion['coupons'] as $coupon) {
                                if($coupon['is_used'] === Coupon::USED_NO) {
                                    $item['use_coupon'] = ['code' => $coupon['coupon_code'], 'name' => $promotion['name']];
                                    $couponCodes[] = $coupon['coupon_code'];
                                    break;
                                }
                            }

                            break;
                        }
                    }
                }
            }
//            $coupon_code = implode(',', $couponCodes);
        }

        Log::info('------------ result promotion ----------- ' . json_encode($couponCodes));
        //加入有优惠券，判断优惠券是否可用
        if (count($couponCodes) > 0) {
            $couponModel = new Coupon();
            $couponInfo = $couponModel->codeToInfo($couponCodes, $coupon_code);
            if (!$couponInfo['status']) {
                return $couponInfo;
            }
            Log::debug('----------- use coupon ------------');
            $re = $promotionModel->toCoupon($result['data'], $couponInfo['data']);
            if (!$re['status']) {
                return $re;       //优惠券不符合使用规则，后期会把不符合的原因写出来
            }
        } else {

        }
        //$result['data']['amount'] = 0;
//        Log::debug(json_encode($result['data']['list']));
        foreach ($result['data']['list'] as $k => &$v) {
            if ($v['is_select']) {
                //算订单总商品价格
                //$result['data']['goods_amount'] += $result['data']['list'][$k]['products']['amount'];
                //算订单总价格
                //$result['data']['amount'] += (float)$v['amount'];
                $v['amount'] = number_format($v['amount'], 2);
                //计算总重量
                //$result['data']['weight'] += $v['weight'] * $v['nums'];
            }
        }

        if ($point != 0) {
            //判断用户是否有这么多积分
            $userModel = new User();
            $oPoint = $userModel->getUserPoint($userId);
            if ($oPoint['data'] < $point) {
                $result['msg'] = '积分不足，无法使用积分';
                return $result;
            }

            //判断积分值多少钱
            $settingModel = new Setting();
            $orders_point_proportion = $settingModel->getValue('orders_point_proportion'); //订单积分使用比例
            $max_point_deducted_money = $result['data']['amount'] * ($orders_point_proportion / 100); //最大积分抵扣的钱
            $point_discounted_proportion = $settingModel->getValue('point_discounted_proportion'); //积分兑换比例
            $point_deducted_money = (int)$point / (int)$point_discounted_proportion; //积分可以抵扣的钱
            if ($max_point_deducted_money < $point_deducted_money) {
                $result['msg'] = '积分抵扣超过订单总金额的' . $orders_point_proportion . '%，积分无法正常使用！';
                return $result;
            }

            $result['data']['point'] = $point;
            $result['data']['point_money'] = number_format($point_deducted_money, 2);
            $result['data']['amount'] = $result['data']['amount'] - $point_deducted_money;
        }
        $result['data']['amount'] = number_format($result['data']['amount'], 2);
        $result['status'] = true;
        return $result;
    }

    /**
     * 设置购物车数量
     * @param $input
     * @return array
     */
    public function setNums($input)
    {
        $result = [
            'status' => false,
            'msg' => '',
            'data' => ''
        ];

        $where[] = ['id', 'eq', $input['id']];
        $where[] = ['user_id', 'eq', $input['user_id']];
        $res = [];
        if ($input['nums'] > 0) {
            $res = $this->where($where)
                ->update(['nums' => $input['nums']]);
        } else {
            $this->where($where)->delete();
        }


        $result['data'] = $res;
        if ($res !== false) {
            $result['status'] = true;
            $result['msg'] = '设置成功';
        } else {
            $result['msg'] = '设置失败';
        }
        return $result;
    }
}