<?php

namespace app\api\controller;

use app\common\controller\Api;
use app\common\model\Brand;
use app\common\model\Goods as GoodsModel;
use app\common\model\GoodsCat;
use app\common\model\GoodsComment;
use app\common\model\Products;
use app\common\model\UserShip;
use app\common\model\VisitProductCount;
use think\db\Query;
use think\facade\Log;
use think\facade\Request;
use think\model\Collection;

/***
 * 商品相关接口
 * Class Goods
 * @package app\api\controller
 * User: wjima
 * Email:1457529125@qq.com
 * Date: 2018-01-23 19:45
 */
class Goods extends Api
{

    //商品允许出现字段
    private $goodsAllowedFields = [
        'id', 'bn', 'name', 'brief', 'price', 'costprice', 'mktprice', 'image_id', 'goods_cat_id', 'goods_type_id', 'brand_id'
        , 'is_nomal_virtual', 'marketable', 'stock', 'freeze_stock', 'weight', 'unit', 'intro', 'spes_desc', 'comments_count', 'view_count', 'buy_count', 'uptime'
        , 'downtime', 'sort', 'is_hot', 'is_recommend', 'ctime', 'utime', 'products', 'params', 'preferential_price', 'promotion_price', 'erp_goods_id'
    ];
    //货品允许字段
    private $productAllowedFields = [
        'id', 'goods_id', 'barcode', 'sn', 'price', 'costprice', 'mktprice', 'marketable', 'stock', 'freeze_stock', 'spes_desc', 'is_defalut'
    ];

    private function allowedField($data, $type = 'goods')
    {
        $return_data = [
            'status' => false,
            'msg' => '有非法查询字段',
            'data' => []
        ];

        if ($data == '' && $data != '*') {
            $return_data['msg'] = '查询字段错误';
            return $return_data;
        }
        if ($data != '*') {
            $tmpData = explode(',', $data);
            foreach ($tmpData as $key => $value) {
                if ($type == 'goods') {
                    if (!in_array($value, $this->goodsAllowedFields)) {
                        $return_data['msg'] .= ':' . $value;
                        return $return_data;
                    }
                } elseif ($type == 'product') {
                    if (!in_array($value, $this->productAllowedFields)) {
                        $return_data['msg'] .= ':' . $value;
                        return $return_data;
                    }
                }
            }
        }
        $return_data['status'] = true;
        $return_data['msg'] = '字段校检通过';
        return $return_data;
    }

    /**
     * 检查排序字段
     * @param $order
     * @return array
     * User: wjima
     * Email:1457529125@qq.com
     * Date: 2018-01-29 16:42
     */
    private function allowedOrder($order)
    {
        $return_data = [
            'status' => false,
            'msg' => '排序错误',
            'data' => []
        ];
        $return_data['status'] = true;
        $return_data['msg'] = '排序校检通过';
        return $return_data;
    }

    /**
     * 获取商品列表
     * @return array
     * User: wjima
     * Email:1457529125@qq.com
     * Date: 2018-01-23 19:46
     */
    public function getList()
    {
        $return_data = [
            'status' => false,
            'msg' => '查询失败',
            'data' => []
        ];
        $field = input('field', '*');
        $page = input('page/d', 1);
        $limit = input('limit/d', PAGE_SIZE);
        $order = input('order', 'g.sort asc');
        $token = input('token', '');//token值 会员登录后传
        $this->userId = getUserIdByToken($token);
        if (input('?param.where')) {
            $postWhere = request()->param('where');
            if (is_string($postWhere)) {
                $postWhere = json_decode($postWhere, true);
            }

            if (isset($postWhere['brands'])) {
                $brands = explode(',', $postWhere['brands']);
                $where[] = ['brand_id', 'in', $brands];
            }
            //判断商品搜索,
            if (isset($postWhere['search_name']) && $postWhere['search_name']) {
                $where[] = ['g.bn|g.name|g.brief|g.keywords', 'LIKE', '%' . $postWhere['search_name'] . '%'];
            }
            if (isset($postWhere['bn']) && $postWhere['bn']) {
                $where[] = ['bn', '=', $postWhere['bn']];
            }
            //商品分类,同时取所有子分类 todo 无限极分类时要注意
            if (isset($postWhere['cat_id'])) {
                //$where[] = ['goods_cat_id', 'eq', $postWhere['cat_id']];
                $goodsCatModel = new GoodsCat();
                $catIds = [];
                $childCats = $goodsCatModel->getCatByParentId($postWhere['cat_id']);
                $catIds = array_column($childCats->toArray(), 'id');
                $catIds[] = $postWhere['cat_id'];
                $where[] = ['goods_cat_id', 'in', $catIds];
            }
            //价格区间
            if (isset($postWhere['price_f']) && $postWhere['price_f']) {
                $where[] = ['price', '>=', $postWhere['price_f']];
            }
            if (isset($postWhere['price_t']) && $postWhere['price_t']) {
                $where[] = ['price', '<', $postWhere['price_t']];
            }
            if (isset($postWhere['recommend'])) {
                $where[] = ['is_recommend', 'eq', '1'];
            }
            if (isset($postWhere['hot'])) {
                $where[] = ['is_hot', 'eq', '1'];
            }
            if (isset($postWhere['keyword'])) {

            }
        }
        $goodsModel = new GoodsModel();
        $where[] = ['marketable', 'eq', $goodsModel::MARKETABLE_UP];


        $return_data = $this->allowedField($field);
        if (!$return_data['status']) {
            return $return_data;
        }
        $return_data = $this->allowedOrder($order);
        if (!$return_data['status']) {
            return $return_data;
        }


        $page_limit = config('labgic.page_limit');
        $limit = $limit ? $limit : $page_limit;

        $returnGoods = $goodsModel->getList('api', $field, $where, $order, $page, $limit);
        $area = "";
        if ($this->userId) {
            $user = (new \app\common\model\User())->where('id', 'eq', $this->userId)->find();
            $area = $user['area_id'];
            if (!$area) {
                $where[] = ['user_id', 'eq', $this->userId];
                $where[] = ['is_def', 'eq', 1];
                /**@var UserShip $userShip*/
                $userShip = (new UserShip())->with('area')->where($where)->order('utime desc')->find();
                if ($userShip) {
                    $areas = $userShip->area->getParentArea();
                    foreach ($areas as $a) {
                        if ($a['info']['parent_id'] == 0) {
                            $area = $a['info']['id'];
                            break;
                        }
                    }
                }
            }

        }
        /**
         * @var \app\common\model\Goods[]|Collection $list
         * */
        $list = $returnGoods['data'];
        foreach ($list as &$goods) {
            /**
             * @var Collection $levels
             * */
            $levels = $goods['price_levels'];


            if ($levels && $levels->count() > 0) {
                if (!$area) {
                    $area = "";
                }
                $goods->levels($levels, $area);
                $levels->each(function ($level, $key) use (&$goods, &$levels) {
                    if ($level['buy_num'] == 1) {
                        $goods['price'] = $level['price'];
                    }
                });
            }
        }

        if ($returnGoods['status']) {
            $return_data ['msg'] = '查询成功';
            $return_data ['data']['list'] = $returnGoods['data'];
            $return_data ['data']['total_page'] = $returnGoods['total'];
        }

        $return_data['data']['page'] = $page;
        $return_data['data']['limit'] = $limit;
        $return_data['data']['where'] = $where;
        $return_data['data']['order'] = $order;

        return $return_data;
    }

    public function getListByKeyword()
    {
        $return_data = [
            'status' => false,
            'msg' => '查询失败',
            'data' => []
        ];

        $keyword = input('keyword', '');
        $field = input('field', '*');
        $page = input('page/d', 1);
        $limit = input('limit/d', PAGE_SIZE);
        $order = input('order', 'g.sort asc');
        $token = input('token', '');//token值 会员登录后传
        $this->userId = getUserIdByToken($token);

        $return_data = $this->allowedField($field);
        if (!$return_data['status']) {
            return $return_data;
        }
        $return_data = $this->allowedOrder($order);
        if (!$return_data['status']) {
            return $return_data;
        }

        $goodsModel = new GoodsModel();

        $where = function (Query $query) use ($keyword) {
            $query->where('bn', 'like', '%' . $keyword . '%')
                ->whereOr('g.name', 'like', '%' . $keyword . '%')
                ->whereOr('erp_goods_id', $keyword)
                ->whereOrRaw('json_contains(keywords->\'$[*]\',\'"' . $keyword . '"\',\'$\')');
        };
        $order = "case when g.keywords LIKE '%{$keyword}%' then 1 else 2 end asc,{$order}";
        Log::debug("-------- order condition: {$order} -------");
        $returnGoods = $goodsModel->getList('api', $field, $where, $order, $page, $limit);
        $area = "";
        if ($this->userId) {
            $user = (new \app\common\model\User())->where('id', 'eq', $this->userId)->find();
            $area = $user['area_id'];
            if (!$area) {
                $where[] = ['user_id', 'eq', $this->userId];
                $where[] = ['is_def', 'eq', 1];
                $userShip = (new UserShip())->with('area')->where($where)->order('utime desc')->find();
                if ($userShip) {
                    $areas = $userShip->area->getParentArea();
                    foreach ($areas as $a) {
                        if ($a['info']['parent_id'] == 0) {
                            $area = $a['info']['id'];
                            break;
                        }
                    }
                }
            }

        }
        /**
         * @var \app\common\model\Goods[]|Collection $list
         * */
        $list = &$returnGoods['data'];
        foreach ($list as &$goods) {
            /**
             * @var Collection $levels
             * */
            $levels = $goods['price_levels'];


            if ($levels && $levels->count() > 0) {
                if (!$area) {
                    $area = "";
                }
                $goods->levels($levels, $area);
                $levels->each(function ($level, $key) use (&$goods, &$levels) {
                    if ($level['buy_num'] == 1) {
                        $goods['price'] = $level['price'];
                    }
                    Log::info('----- goods =>'.json_encode($goods).' -------');
                });
            }
        }
        if ($returnGoods['status']) {
            $return_data ['msg'] = '查询成功';
            $return_data ['data']['list'] = $list;
            $return_data ['data']['total_page'] = $returnGoods['total'];
        }

        $return_data['data']['page'] = $page;
        $return_data['data']['limit'] = $limit;
        $return_data['data']['where'] = $where;
        $return_data['data']['order'] = $order;

        return $return_data;
    }

//    public function levels (Collection $levels, $area)
//    {
//        $list = $levels->where('area', '=', $area)->order('buy_num', 'desc');
//        return $list->count() > 0 ? $list : $levels->where('area', '=', '')->order('buy_num', 'desc');
//    }

    /**
     * 获取商品明细
     * @return array
     * User: wjima
     * Email:1457529125@qq.com
     * Date: 2018-01-23 19:47
     */
    public function getDetial()
    {
        $return_data = [
            'status' => false,
            'msg' => '查询失败',
            'data' => []
        ];
        $goods_id = input('id/d', 0);//商品ID
        $token = input('token', '');//token值 会员登录后传
        $this->userId = getUserIdByToken($token);
        $this->visit($goods_id);
        if (!$goods_id) {
            $return_data['msg'] = '缺少商品ID参数';
            return $return_data;
        }
        $field = input('field', '*');
        $return_data = $this->allowedField($field);
        $goodsModel = new GoodsModel();
        $returnGoods = $goodsModel->getGoodsDetial($goods_id, $field, $token);
        Log::debug('----- user id ------' . $this->userId);
        $area = "";
        if ($this->userId) {
            $user = (new \app\common\model\User())->where('id', 'eq', $this->userId)->find();
            $area = $user['area_id'];
            if (!$area) {
                $where[] = ['user_id', 'eq', $this->userId];
                $where[] = ['is_def', 'eq', 1];
                $userShip = (new UserShip())->with('area')->where($where)->order('utime desc')->find();
                if ($userShip) {
                    $areas = $userShip->area->getParentArea();
                    foreach ($areas as $a) {
                        if ($a['info']['parent_id'] == 0) {
                            $area = $a['info']['id'];
                            break;
                        }
                    }
                }
            }

        }

        /**
         * @var Collection $levels
         * */
        $levels = &$returnGoods['data']['price_levels'];

        /**
         * @var \app\common\model\Goods $goods
         * */
        $goods = &$returnGoods['data'];
        if ($levels && $levels->count() > 0) {
            if (!$area) {
                $area = "";
            }
            $goods->levels($levels, $area);
            $levels->each(function ($level, $key) use (&$goods, &$levels) {
                if ($level['buy_num'] == 1) {
                    $goods['price'] = $level['price'];
                    $items = $levels->toArray();
                    array_splice($items, $key, 1);
                    while ($levels->pop()) ;
                    if (count($items) > 0)
                        $levels = $levels->merge($items);
//                    Log::info('<<<<<<<<-------- '.json_encode($levels).' -------->>>>>>>>');
//                    Log::info('<<<<<<<<-------- '.json_encode($items).' -------->>>>>>>>');
                }
            });
        }

        if ($returnGoods['status']) {
            if (isset($return_data['data']['isdel']) && $return_data['data']['isdel']) {
                $return_data['msg'] = '产品已失效';
                $return_data['status'] = false;
            } elseif ($returnGoods['data']['marketable'] == 2) {
                $return_data['msg'] = '产品已下架';
                $return_data['status'] = false;
            } else {
                $return_data['msg'] = '查询成功';
                $return_data['data'] = $goods->toArray();
                $return_data['data']['price_levels'] = $levels->toArray();
            }
        } else {
            $return_data['msg'] = $returnGoods['msg'];
            $return_data['status'] = false;
        }
        return $return_data;
    }

    /**
     * 根据sku获取相关价格，库存等信息
     * @return array
     * User: wjima
     * Email:1457529125@qq.com
     * Date: 2018-02-02 10:09
     */
    public function getSkuDetial()
    {
        $return_data = [
            'status' => false,
            'msg' => '无此规格信息',
            'data' => []
        ];
        $spec_value = input('spec', '');
        $goods_id = input('id/d', 0);//商品ID
        $token = input('token', '');//token值 会员登录后传

        if (!$goods_id) {
            return $return_data;
        }
        if (!$spec_value) {
            return $return_data;
        }
        $goodsModel = new GoodsModel();
        $returnGoods = $goodsModel->getGoodsDetial($goods_id, 'id,bn,name,image_id,goods_cat_id,goods_type_id,brand_id,spes_desc', $token);
        if ($returnGoods['status']) {
            $goods = $returnGoods['data'];
            if ($goods['products']) {
                $products = $goods['products'];
                foreach ($products as $key => $val) {
                    if ($val['spes_desc'] == $spec_value) {
                        //获取价格
                        $val['price'] = $goodsModel->getPrice($val);
                        $val['stock'] = $goodsModel->getStock($val);
                        $return_data['data'] = $val;
                        $return_data['msg'] = '获取成功';
                        $return_data['status'] = true;
                    }
                }
            }
        }
        return $return_data;
    }

    /**
     * 获取参数接口
     * @return array
     * User: wjima
     * Email:1457529125@qq.com
     * Date: 2018-02-02 11:18
     */
    public function getGoodsParams()
    {
        $return_data = [
            'status' => false,
            'msg' => '无参数相关信息',
            'data' => []
        ];
        $goods_id = input('id/d', 0);//商品ID
        $goodsModel = new GoodsModel();
        $brandModel = new Brand();
        $returnGoods = $goodsModel->getOne($goods_id, 'id,bn,name,brand_id,image_id,params,spes_desc');

        if ($returnGoods['status']) {
            $params = [];
            $data = $returnGoods['data'];
            if (isset($data['brand_id'])) {
                $brand = $brandModel::get($data['brand_id']);
                $params[] = [
                    'name' => '品牌',
                    'value' => $brand['name'],
                ];

            }
            if ($data['params']) {
                $goodsParams = unserialize($data['params']);
                $goodsParams = array_filter($goodsParams);
                if ($goodsParams) {
                    foreach ($goodsParams as $key => $val) {
                        if (is_array($val)) {
                            $val = implode('、', $val);
                            $params[] = [
                                'name' => $key,
                                'value' => $val
                            ];
                        } else {
                            $params[] = [
                                'name' => $key,
                                'value' => $val
                            ];
                        }

                    }
                }
            }
            $return_data['data'] = $params;
            $return_data['status'] = true;
            $return_data['msg'] = '查询成功';
        }
        return $return_data;
    }

    /**
     * 获取该货品相关信息
     * @param int $user_id
     * @return array
     */
    public function getProductInfo()
    {
        $return_data = [
            'status' => false,
            'msg' => '无参数相关信息',
            'data' => []
        ];
        $product_id = input('id/d', 0);//货品ID
        $token = input('token', '');//token值 会员登录后传
        if (!$product_id) {
            $return_data['msg'] = '货品ID缺失';
            return $return_data;
        }

        $productsModel = new Products();
        $user_id = getUserIdByToken($token);//获取user_id
        $product = $productsModel->getProductInfo($product_id, true, $user_id);
        $return_data['msg'] = $product['msg'];
        if (!$product['status']) {
            return $return_data;
        }
        $return_data['data'] = $product['data'];
        $return_data['status'] = true;
        return $return_data;
    }


    /**
     * 获取商品评价
     * @return array|mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getGoodsComment()
    {
        $goods_id = input('goods_id');
        $page = input('page', 1);
        $limit = input('limit', 10);
        if (empty($goods_id)) {
            return error_code(13403);
        }
        $model = new GoodsComment();
        $res = $model->getList($goods_id, $page, $limit, 1);
        return $res;
    }


    /**
     * 获取某个分类的热卖商品
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getGoodsCatHotGoods()
    {
        $cat_id = Request::param('cat_id');
        $limit = Request::param('limit', 6);
        $model = new GoodsModel();
        $res = $model->getGoodsCatHotGoods($cat_id, $limit);
        return $res;
    }

    public function visit($id)
    {
        if (!$id)
            $id = input('goods_id');
        if (!$this->userId) {
            $token = input('token', '');//token值 会员登录后传
            $this->userId = getUserIdByToken($token);
        }
        $userId = $this->userId;
        $result = (new VisitProductCount())->addRecord($id, $userId);
        $return_data = [
            'status' => false,
            'msg' => '无参数相关信息',
            'data' => []
        ];
        if ($result) {
            $return_data['status'] = true;
            $return_data['msg'] = '记录成功';
        }
        Log::debug('--------- '.json_encode($return_data).' --------');
        return $return_data;
    }

    public function ipArea()
    {
        return (new VisitProductCount())->ipArea(Request::ip());
    }
}