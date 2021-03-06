<?php
// +----------------------------------------------------------------------
// | JSHOP [ 小程序商城 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2018 http://jihainet.com All rights reserved.
// +----------------------------------------------------------------------
// | Author: mark <jima@jihainet.com>
// +----------------------------------------------------------------------

namespace app\common\model;

use app\service\excel\Excelable;
use app\service\LabGicApiService;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use think\Db;
use think\facade\Log;
use think\model\Collection;


/**
 * 商品类型
 * Class GoodsType
 * @package app\common\model
 * User: wjima
 * Email:1457529125@qq.com
 * Date: 2018-01-09 20:09
 */
class Goods extends Common implements Excelable
{
    protected $autoWriteTimestamp = true;
    protected $createTime = 'ctime';
    protected $updateTime = 'utime';

    const MARKETABLE_UP = 1; //上架
    const MARKETABLE_DOWN = 2;//下架
    const VIRTUAL_YES = 2;//虚拟商品
    const VIRTUAL_NO = 1;//普通商品
    const HOT_YES = 1; //热卖
    const HOT_NO = 2; //非热卖

    protected $type = [
        'keywords' => 'array'
    ];

    const TYPE_PRICE_SALE = 'sale';
    const TYPE_PRICE_MARKET = 'market';
    const TYPE_PRICE_PREFERENTIAL = 'preferential';
    const TYPE_PRICE_PROMOTION = 'promotion';
    const TYPE_PRICES = [
        self::TYPE_PRICE_SALE => 1,
        self::TYPE_PRICE_MARKET => 2,
        self::TYPE_PRICE_PREFERENTIAL => 3,
        self::TYPE_PRICE_PROMOTION => 4
    ];

    public function tableData($post, $isPage = true)
    {
        if (isset($post['limit'])) {
            $limit = $post['limit'];
        } else {
            $limit = config('paginate.list_rows');
        }
        $tableWhere = $this->tableWhere($post);
//        $query = $this::with('defaultImage,brand,goodsCat,goodsType')
//            ->field($tableWhere['field'])->whereNull('isdel')->where(function ($query) use ($tableWhere) {
//                $query->where($tableWhere['where'])->whereOr($tableWhere['whereOr']);
//            })->order($tableWhere['order']);

        $query = $this::with('defaultImage,brand,goodsCat,goodsType')
            ->field($tableWhere['field'])->where(function ($query) use ($tableWhere) {
                $query->where($tableWhere['where'])->whereOr($tableWhere['whereOr']);
            })->order($tableWhere['order'])->whereNull('isdel');

        if ($isPage) {
            $list = $query->paginate($limit);
            $data = $this->tableFormat($list->getCollection());         //返回的数据格式化，并渲染成table所需要的最终的显示数据类型
            $re['count'] = $list->total();
        } else {
            $list = $query->select();
            $data = $this->tableFormat($list);         //返回的数据格式化，并渲染成table所需要的最终的显示数据类型
            $re['count'] = count($list);
        }
        $re['code'] = 0;
        $re['msg'] = '';
        $re['data'] = $data;
        return $re;
    }

    /**
     * 默认排序
     * @param $post
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * User: wjima
     * Email:1457529125@qq.com
     * Date: 2018-01-11 16:32
     */
    protected function tableWhere($post)
    {
        $where = $whereOr = [];
        if (isset($post['marketable']) && $post['marketable'] != "") {
            $where[] = ['marketable', 'eq', $post['marketable']];
        }
        if (isset($post['name']) && $post['name'] != "") {
            $where[] = ['name', 'like', '%' . $post['name'] . '%'];
        }
        if (isset($post['id']) && $post['id'] != "") {
            $where[] = ['id', 'in', $post['id']];
        }
        if (isset($post['warn']) && $post['warn'] == "true") {
            $SettingModel = new Setting();
            $goods_stocks_warn = $SettingModel->getValue('goods_stocks_warn');
            $goods_stocks_warn = $goods_stocks_warn ? $goods_stocks_warn : '10';
            $productModel = new Products();
            //$baseFilter[] = ['(stock - freeze_stock)', 'lt', $goods_stocks_warn];
            $goodsIds = $productModel->field('goods_id')->where("(stock - freeze_stock) < " . $goods_stocks_warn)->group('goods_id')->select();
            if (!$goodsIds->isEmpty()) {
                $goodsIds = array_column($goodsIds->toArray(), 'goods_id');
                $where[] = ['id', 'in', $goodsIds];
            } else {
                $where[] = ['id', 'in', 0];
            }
        }
        if (isset($post['goods_type_id']) && $post['goods_type_id'] != "") {
            $where[] = ['goods_type_id', 'eq', $post['goods_type_id']];
        }
        if (isset($post['brand_id']) && $post['brand_id'] != "") {
            $where[] = ['brand_id', 'eq', $post['brand_id']];
        }
        if (isset($post['bn']) && $post['bn'] != "") {
            $where[] = ['bn', 'like', '%' . $post['bn'] . '%'];
        }
        if (isset($post['erp_goods_id']) && $post['erp_goods_id'] !== '') {
            $where[] = ['erp_goods_id', 'like', "%{$post['erp_goods_id']}%"];
        }

        if (isset($post['last_cat_id']) && $post['last_cat_id'] != "") {
            $where[] = ['goods_cat_id', 'eq', $post['last_cat_id']];
        }
        if (isset($post['goods_cat_id']) && $post['goods_cat_id'] != "" && !$post['last_cat_id']) {//取出来所有子分类进行查询
            if ($post['goods_cat_id']) {
                $goodsCatModel = new GoodsCat();
                $catIds = [];
                $childCats = $goodsCatModel->getCatByParentId($post['goods_cat_id']);
                $catIds = array_column($childCats->toArray(), 'id');
                $catIds[] = $post['goods_cat_id'];
                $where[] = ['goods_cat_id', 'in', $catIds];
            }
        }
        if (isset($post['is_recommend']) && $post['is_recommend'] != "") {
            $where[] = ['is_recommend', 'eq', $post['is_recommend']];
        }
        if (isset($post['is_hot']) && $post['is_hot'] != "") {
            $where[] = ['is_hot', 'eq', $post['is_hot']];
        }
        $result['where'] = $where;
        $result['whereOr'] = $whereOr;
        $result['field'] = "*";
        $result['order'] = ['id' => 'desc'];
        return $result;
    }

    /**
     * 保存商品
     * User:wjima
     * Email:1457529125@qq.com
     * @param array $data
     * @return mixed
     */
    public function doAdd($data = [])
    {
        $goodsid = $this->allowField(true)->insertGetId($data);

        return $goodsid ? $goodsid : 0;
    }

    public function tableFormat($list)
    {

        foreach ($list as $key => $val) {
            $list[$key]['image'] = _sImage($val['image_id']);
            if ($val['label_ids']) {
                $list[$key]['label_ids'] = getLabel($val['label_ids']);
            }
        }
        return $list;
    }

    /**
     * 更新商品信息
     * @param       $goods_id
     * @param array $data
     * @return false|int
     * User: wjima
     * Email:1457529125@qq.com
     * Date: 2018-01-23 19:37
     */
    public function updateGoods($goods_id, $data = [])
    {
        return $this->save($data, ['id' => $goods_id]);
    }

    /**
     * 查询商品列表信息
     * @param string $fields 查询字段
     * @param array $where 查询条件
     * @param string $order 查询排序
     * @param int $page 当前页码
     * @param int $limit 每页数量
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * User: wjima
     * Email:1457529125@qq.com
     * Date: 2018-01-29 16:33
     */
    public function getList($from = 'manage', $fields = 'g.*', $where = [], $order = 'id desc', $page = 1, $limit = 10)
    {
        $result = [
            'status' => true,
            'data' => [],
            'msg' => ''
        ];

        if ($fields != '*') {
            $tmpData = explode(',', $fields);
            if (in_array('products', $tmpData)) {
                $key = array_search('products', $tmpData);
                unset($tmpData[$key]);
            }
            $fields = implode(',', $tmpData);
        }else{
            $fields = "g.id as id,erp_goods_id,bn,g.name as name,price,mktprice,image_id,goods_cat_id,brand_id,stock,spes_desc,g.sort as sort,is_hot,is_recommend";
        }
        $query = $this
            ->alias('g')
            ->field($fields)
            ->where($where)
            ->whereNull('g.isdel');
        if ($from === 'api') {
            $query = $query->where('marketable', '=', 1);
        }
        $query = $query->join('lc_brand b', 'b.id=g.brand_id')->order('b.sort', 'asc');
        Log::debug('--------order condition --------- '.$order.'  ----------');
        $list = $query->orderRaw($order)
            ->page($page, $limit)
            ->select();
        $ids = [];
        foreach ($list as &$item) {
            $ids[] = $item['erp_goods_id'];
        }
        $pStocks = LabGicApiService::productsStock($ids);

        foreach ($list as &$item) {
            $item['stock'] = $pStocks[$item['erp_goods_id']];
        }
        $total = $query->count();

        if (!$list->isEmpty()) {
            $gcModel = new GoodsComment();
            foreach ($list as $key => $value) {
                $goods = $this->getGoodsDetial($value['id'], '*');
                if ($goods['status']) {
                    $list[$key] = $goods['data'];
                }
                $list[$key]['comments_count'] = $gcModel->getCommentCount($value['id']);
            }

            $result['data'] = $this->tableFormat($list);
        }
        $result['total'] = ceil($total / $limit);


        return $result;
    }

    public function levels (Collection &$levels, $area)
    {
        $list = $levels->filter(function ($level) use($area){
            Log::debug("---- level area {$level['area']} ------ {$area} --------");
            return $level['area'] == $area;
        })->order('buy_num', 'desc');

        $list = $list->count() > 0 ? $list : $levels->filter(function ($level) {
            return $level['area'] == '';
        })->order('buy_num', 'desc');

        Log::debug("-------- list count {$list->count()} -------");
        while ($levels->pop());
        while ($list->count()) {
            $levels->push($list->shift());
        }
        //$levels->merge($list->toArray());
        Log::debug('------ '.$levels->toJson().'  ------');
    }

    /**
     * 获取商品详情
     * @param $gid
     * @param string $fields
     * @param string $token
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getGoodsDetial($gid, $fields = '*', $token = '')
    {

        $result = [
            'status' => true,
            'data' => [],
            'msg' => ''
        ];
        $preModel = '';
        if ($fields == '*') {
            $preModel = 'brand,goodsCat,relateGoods,priceLevels.areaInfo';
        } else {

            if (stripos($fields, 'brand_id') !== false) {
                $preModel .= 'brand,';
            }

            if (stripos($fields, 'goods_cat_id') !== false) {
                $preModel .= 'goodsCat,';
            }
            $preModel = substr($preModel, 0, -1);
        }
        $list = $this::with($preModel)->field($fields)->where(['id' => $gid])->find();
        if ($list) {
            $image_url = _sImage($list['image_id']);
            $list['image_url'] = $image_url;
            if (isset($list['label_ids'])) {
                $list['label_ids'] = getLabel($list['label_ids']);
            } else {
                $list['label_ids'] = [];
            }
            $user_id = getUserIdByToken($token);//获取user_id
//            if ($list['spes_desc']) {
//                $list['spes_desc'] = unserialize($list['spes_desc']);
//            }
            //取出图片集
            $imagesModel = new GoodsImages();
            $images = $imagesModel->where(['goods_id' => $list['id']])->order('sort asc')->select();
            $album = [];
            if (isset($list['image_url'])) {
                $album[] = $list['image_url'];
            }
            if (!$images->isEmpty()) {
                foreach ($images as $v) {
                    $album[] = _sImage($v['image_id']);
                }
            }
            $list['album'] = $album;

            //取出销量
            $orderItem = new OrderItems();
            $count = $orderItem->where(['goods_id' => $gid])->sum('nums');
            $list['buy_count'] = $count;

            //获取当前登录是否收藏

            $list['isfav'] = $this->getFav($list['id'], $user_id);


            foreach ($list->relate_goods as &$relate_good) {
                $image_url = _sImage($relate_good['image_id']);
                $relate_good['image_url'] = $image_url;
            }
            $result['data'] = $list;

            //图片处理
            if (isset($list['intro'])) {
                $list['intro'] = clearHtml($list['intro'], ['width', 'height']);
                $list['intro'] = str_replace("<img", "<img style='max-width: 100%'", $list['intro']);
            }
        }
        return $result;
    }


    /**
     * 获取默认规格
     * @param $specDefault //默认规格
     * @param $specKey //当前规格名称
     * @param $specValue //当前规格值
     * @return string
     * User: wjima
     * Email:1457529125@qq.com
     * Date: 2018-01-31 11:32
     */
    private function getDefaultSpec($specDefault, $specKey, $specValue)
    {
        $isDefault = '2';
        foreach ((array)$specDefault as $key => $val) {
            if ($val['sku_name'] == $specKey && $val['sku_value'] == $specValue) {
                $isDefault = '1';
            }
        }
        return $isDefault;
    }

    /**
     * 获取商品下面所有货品
     * @param $goods_id
     * @param bool $isPromotion
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function products($goods_id, $isPromotion = true)
    {
        $productModel = new Products();
        $pids = $productModel->field('id')->where(['goods_id' => $goods_id])->select();
        $products = [];

        if (!$pids->isEmpty()) {
            foreach ($pids as $key => $val) {
                $productInfo = $productModel->getProductInfo($val['id'], $isPromotion);
                if ($productInfo['status']) {
                    $products[$key] = $productInfo['data'];
                } else {
                    $products[$key] = [];
                }
            }
        }
        return $products;
    }

    /**
     * 获取goods表图片对应图片地址
     * @return \think\model\relation\HasOne
     * User: wjima
     * Email:1457529125@qq.com
     * Date: 2018-01-29 16:26
     */
    public function defaultImage()
    {
        return $this->hasOne('Images', 'id', 'image_id')->field('id,url')->bind(['image_url' => 'url']);
    }

    /**
     * 获取品牌信息
     * @return \think\model\relation\HasOne
     * User: wjima
     * Email:1457529125@qq.com
     * Date: 2018-01-31 11:43
     */
    public function brand()
    {
        return $this->hasOne('Brand', 'id', 'brand_id')->field('id,name,logo')->bind(['brand_name' => 'name']);
    }

    /**
     * 获取分类名称
     * @return \think\model\relation\HasOne
     * User: wjima
     * Email:1457529125@qq.com
     * Date: 2018-01-31 11:46
     */
    public function goodsCat()
    {
        return $this->hasOne('GoodsCat', 'id', 'goods_cat_id')->field('id,name')->bind(['cat_name' => 'name']);
    }

    /**
     * 获取类型名称
     * @return \think\model\relation\HasOne
     * User: wjima
     * Email:1457529125@qq.com
     * Date: 2018-02-03 8:55
     */
    public function goodsType()
    {
        return $this->hasOne('GoodsType', 'id', 'goods_type_id')->field('id,name')->bind(['type_name' => 'name']);
    }

    /**
     * 获取销售价
     * @param $product
     * @return mixed
     * User: wjima
     * Email:1457529125@qq.com
     * Date: 2018-02-02 10:26
     */
    public function getPrice($product, $user_id = '')
    {
        $price = $product['price'];

        //获取会员优惠
        $user_grade = get_user_info($user_id, 'grade');
        $priceData['grade_info']['id'] = $user_grade;
        $goodsGradeModel = new GoodsGrade();
        $goodsGrade = $goodsGradeModel->getGradePrice($product['goods_id']);
        $grade_price = [];
        $userGradeModel = new UserGrade();
        if ($goodsGrade['status']) {
            foreach ($goodsGrade['data'] as $key => $val) {
                $grade_price[$key] = $val;
                $userGrade = $userGradeModel->where(['id' => $val['grade_id']])->field('name')->find();
                $grade_price[$key]['grade_name'] = isset($userGrade['name']) ? $userGrade['name'] : '';
                if ($user_grade && $user_grade == $val['grade_id']) {
                    $price = ($product['price'] - $val['grade_price']) > 0 ? $product['price'] - $val['grade_price'] : 0;
                    $priceData['grade_info']['name'] = $grade_price[$key]['grade_name'];
                }
                $grade_price[$key]['grade_price'] = ($product['price'] - $val['grade_price']) > 0 ? $product['price'] - $val['grade_price'] : 0;
            }
        }

        $priceData['grade_price'] = $grade_price;
        $priceData['price'] = $price;
        return $priceData;
    }

    /**
     * 获取可用库存。库存机制：商品下单 总库存不变，冻结库存加1， 商品发货：冻结库存减1，总库存减1，   商品退款：总库存不变，冻结库存减1, 商品退款：总库存加1，冻结库存不变, 可销售库存：总库存-冻结库存
     * @param $product
     * @return mixed
     * User: wjima
     * Email:1457529125@qq.com
     * Date: 2018-02-02 10:30
     */
    public function getStock($product)
    {
        return $product['stock'] - $product['freeze_stock'];
    }

    /**
     * 库存改变机制。库存机制：商品下单 总库存不变，冻结库存加1， 商品发货：冻结库存减1，总库存减1，   商品退款&取消订单：总库存不变，冻结库存减1, 商品退货：总库存加1，冻结库存不变, 可销售库存：总库存-冻结库存
     * @param $product_id
     * @param string $type
     * @param int $num
     * @return array
     * @throws \think\Exception
     * User: wjima
     * Email:1457529125@qq.com
     * Date: 2018-02-02 10:34
     */
    public function changeStock($product_id, $type = 'order', $num = 0)
    {
        $result = [
            'status' => false,
            'data' => [],
            'msg' => '库存更新失败'
        ];
        if ($product_id === '') {
            $result['msg'] = '货品ID不能为空';
            return $result;
        }
        $productModel = new Products();
        $where = [];
        $where[] = ['id', 'eq', $product_id];
        $where[] = [0, 'exp', Db::raw('(stock-freeze_stock)-' . $num . ' >0')];
        switch ($type) {
            case 'order': //下单
                $res = $productModel->where($where)->setInc('freeze_stock', $num);
                break;
            case 'send': //发货
                $res = $productModel->where($where)->setDec('stock', $num);
                if ($res !== false) {
                    $res = $productModel->where($where)->setDec('freeze_stock', $num);
                } else {
                    $result['msg'] = '库存更新失败';
                    return $result;
                }
                break;
            case 'refund': //退款
                $res = $productModel->where($where)->setDec('freeze_stock', $num);
                break;
            case 'return': //退货
                $res = $productModel->where($where)->setInc('stock', $num);
                break;
            case 'cancel': //取消订单
                $res = $productModel->where($where)->setDec('freeze_stock', $num);
                break;
            default:
                $res = $productModel->where($where)->setInc('freeze_stock', $num);
                break;
        }
        if ($res !== false) {
            $result['msg'] = '库存更新成功';
            $result['status'] = true;
            return $result;
        }
        return $result;
    }

    /**
     * 无数据转换
     * @param $goods_id
     * @param string $fields
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getOne($goods_id, $fields = '*')
    {
        $result = [
            'status' => false,
            'data' => [],
            'msg' => '商品不存在'
        ];
        $data = $this->where(['id' => $goods_id])->field($fields)->find();
        if ($data) {
            $goodsImagesModel = new GoodsImages();
            $images = $goodsImagesModel->getAllImages($data->id);
            $tmp_image = [];
            if ($images['status']) {
                foreach ((array)$images['data'] as $key => $val) {
                    $images['data'][$key]['image_path'] = _sImage($val['image_id']);
                }
                $tmp_image[] = [
                    'goods_id' => $data['id'],
                    'image_id' => $data['image_id'],
                    'image_path' => _sImage($data['image_id']),
                ];
                $images['data'] = array_merge((array)$images['data'], (array)$tmp_image);
                $images['data'] = array_reverse($images['data']);
            } else {
                //单图
                $tmp_image[] = [
                    'goods_id' => $data['id'],
                    'image_id' => $data['image_id'],
                    'image_path' => _sImage($data['image_id']),
                ];
                $images['data'] = $tmp_image;
            }
            $data['products'] = $this->products($goods_id, false);

            $data['images'] = $images['data'];
            $result['data'] = $data;
            $result['msg'] = '查询成功';
            $result['status'] = true;
        }
        return $result;
    }

    /**
     * 判断是否收藏过
     * @param int $goods_id
     * @param string $user_id
     * @return string
     * User: wjima
     * Email:1457529125@qq.com
     * Date: 2018-02-03 8:36
     */
    public function getFav($goods_id = 0, $user_id = '')
    {
        $favRes = 'false';
        if ($user_id) {
            $goodsCollectionModel = new GoodsCollection();
            $isfav = $goodsCollectionModel->check($user_id, $goods_id);
            if ($isfav) {
                $favRes = 'true';
            }
        }
        return $favRes;
    }

    /**
     * 删除商品
     * @param int $goods_id
     * @return array
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function delGoods($goods_id = 0)
    {
        $result = [
            'status' => false,
            'data' => [],
            'msg' => '商品不存在'
        ];
        $goods = $this::get($goods_id);
        if (!$goods) {
            return $result;
        }

        $this->startTrans();

//        $res = $this->where(['id' => $goods_id])->delete();
        $res = $this->where(['id' => $goods_id])->setField(['isdel' => time(), 'marketable' => self::MARKETABLE_DOWN]);
        if (!$res) {
            $this->rollback();
            $result['msg'] = '商品删除失败';
            return $result;
        }
        $productsModel = new Products();
        $delProduct = $productsModel->where(['goods_id' => $goods_id])->delete(true);
        $this->commit();
        hook('deletegoodsafter', $goods);//删除商品后增加钩子

        $result['status'] = true;
        $result['msg'] = '删除成功';
        return $result;
    }

    /**
     * 批量上下架
     * @param $ids
     * @param string $type
     * @return static
     */
    public function batchMarketable($ids, $type = 'up')
    {

        if ($type == 'up') {
            $marketable = self::MARKETABLE_UP;
        } elseif ($type == 'down') {
            $marketable = self::MARKETABLE_DOWN;
        }
        $data = [
            'marketable' => $marketable,
            $type . 'time' => time(),
        ];
        return $this::where('id', 'in', $ids)->update($data);
    }

    /**
     * 获取csv数据
     * @param $post
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getCsvData($post)
    {
        $result = [
            'status' => false,
            'data' => [],
            'msg' => '无可导出商品'
        ];
        $header = $this->csvHeader();
        $goodsData = $this->tableData($post, false);
        if ($goodsData['count'] > 0) {
            $tempBody = $goodsData['data'];
            $body = [];
            $i = 0;
            foreach ($tempBody as $key => $val) {
                //$product = $val->products;
                $product = $this->products($val['id'], false);
                if ($val['spes_desc']) { //规格数据处理
                    $tempSpec = unserialize($val['spes_desc']);
                    $spes_desc = '';
                    foreach ($tempSpec as $tsKey => $tsVal) {
                        $spes_desc = $spes_desc . '|' . $tsKey . ':';
                        if (is_array($tsVal)) {
                            foreach ($tsVal as $sk => $sv) {
                                $spes_desc = $spes_desc . $sv . ',';
                            }
                            $spes_desc = substr($spes_desc, 0, -1);
                        } else {
                            $spes_desc = $spes_desc . $sv;
                        }
                    }
                    $spes_desc = substr($spes_desc, 1);
                    $val['spes_desc'] = $spes_desc;

                }
                if (count($product) > 1) {//多规格
                    foreach ($product as $productKey => $productVal) {
                        $i++;
                        if ($productKey != 0) {
                            unset($val);
                        }
                        $val['sn'] = $productVal['sn'];
                        $val['price'] = $productVal['price'];
                        $val['costprice'] = $productVal['costprice'];
                        $val['mktprice'] = $productVal['mktprice'];
                        $val['stock'] = $productVal['stock'];
                        $val['product_spes_desc'] = $productVal['spes_desc'];
                        $val['is_defalut'] = $productVal['is_defalut'];
                        $val['is_spec'] = '1';//多规格
                        foreach ($header as $hk => $hv) {
                            if ($val[$hv['id']] && isset($hv['modify'])) {
                                if (function_exists($hv['modify'])) {
                                    $body[$i][$hk] = $hv['modify']($val[$hv['id']]);
                                }
                            } elseif ($val[$hv['id']]) {
                                $body[$i][$hk] = $val[$hv['id']];
                            } else {
                                $body[$i][$hk] = '';
                            }
                        }

                    }
                } else {//单规格
                    $val['is_spec'] = '2';
                    $i++;
                    $val['sn'] = $product[0]['sn'];
                    $val['price'] = $product[0]['price'];
                    $val['costprice'] = $product[0]['costprice'];
                    $val['mktprice'] = $product[0]['mktprice'];
                    $val['stock'] = $product[0]['stock'];
                    $val['product_spes_desc'] = $product[0]['spes_desc'];
                    $val['is_defalut'] = $product[0]['is_defalut'];
                    foreach ($header as $hk => $hv) {
                        if ($val[$hv['id']] && isset($hv['modify'])) {
                            if (function_exists($hv['modify'])) {
                                $body[$i][$hk] = $hv['modify']($val[$hv['id']]);
                            }
                        } elseif ($val[$hv['id']]) {
                            $body[$i][$hk] = $val[$hv['id']];
                        } else {
                            $body[$i][$hk] = '';
                        }
                    }
                }
            }
            $result['status'] = true;
            $result['msg'] = '导出成功';
            $result['data'] = $body;
            return $result;
        } else {
            //失败，导出失败
            return $result;
        }
    }

    /**
     * 设置csv header
     * @return array
     */
    public function csvHeader()
    {
        return [
            [
                'id' => 'name',
                'desc' => '商品名称',
            ],
            [
                'id' => 'bn',
                'desc' => '商品编号',
                'modify' => 'convertString'
            ],
            [
                'id' => 'brief',
                'desc' => '商品简介',
            ],
            [
                'id' => 'image_id',
                'desc' => '商品主图',
            ],
            [
                'id' => 'cat_name',
                'desc' => '商品分类',
            ],
            [
                'id' => 'type_name',
                'desc' => '商品类型',
            ],
            [
                'id' => 'brand_name',
                'desc' => '品牌名称',
            ],
            [
                'id' => 'is_nomal_virtual',
                'desc' => '是否实物',
                'modify' => 'getBool'
            ],
            [
                'id' => 'marketable',
                'desc' => '是否上架',
                'modify' => 'getMarketable',
            ],
            [
                'id' => 'weight',
                'desc' => '商品重量',
            ],
            [
                'id' => 'unit',
                'desc' => '商品单位',
            ],
            [
                'id' => 'intro',
                'desc' => '商品详情',
            ],
            [
                'id' => 'spes_desc',
                'desc' => '商品规格',
            ],
            [
                'id' => 'params',
                'desc' => '商品参数',
                //'modify'=>'getParams', //todo 格式化商品参数

            ],
            [
                'id' => 'sort',
                'desc' => '商品排序',
            ],
            [
                'id' => 'is_recommend',
                'desc' => '是否推荐',
                'modify' => 'getBool'
            ],
            [
                'id' => 'is_hot',
                'desc' => '是否热门',
                'modify' => 'getBool'

            ],
            [
                'id' => 'is_spec',
                'desc' => '是否多规格',
                'modify' => 'getBool'
            ],
            [
                'id' => 'label_ids',
                'desc' => '商品标签',
                'modify' => 'getExportLabel'
            ],
            [
                'id' => 'ctime',
                'desc' => '创建时间',
                'modify' => 'getTime'
            ],
            [
                'id' => 'utime',
                'desc' => '更新时间',
                'modify' => 'getTime'
            ],
            [
                'id' => 'product_spes_desc',
                'desc' => '货品规格',
            ],
            [
                'id' => 'sn',
                'desc' => '货品编码',
                'modify' => 'convertString'
            ],
            [
                'id' => 'price',
                'desc' => '货品价格',
            ],
            [
                'id' => 'costprice',
                'desc' => '成本价',
            ],
            [
                'id' => 'mktprice',
                'desc' => '市场价',
            ],
            [
                'id' => 'stock',
                'desc' => '货品库存',
            ],
            [
                'id' => 'is_defalut',
                'desc' => '是否默认货品',
                'modify' => 'getBool'
            ]
        ];
    }

    public static function excelHeader()
    {
        return [
            ['id' => 'erp_goods_id', 'desc' => '产品ID（ERP U8编号）'],
            ['id' => 'bn', 'desc' => '商品编号|货号', 'modify' => 'convertString'],
            ['id' => 'name', 'desc' => '产品名称'],
            ['id' => 'en_name', 'desc' => '产品英文名称'],
            ['id' => 'spes_desc', 'desc' => '产品规格'],
            ['id' => 'cat_name', 'desc' => '产品分类'],
            ['id' => 'brief', 'desc' => '产品简介'],
            ['id' => 'image_url_prefix', 'desc' => '商品图册'],
            ['id' => 'brand_name', 'desc' => '品牌名称'],
            ['id' => 'weight', 'desc' => '产品重量'],
            ['id' => 'length', 'desc' => '产品长度'],
            ['id' => 'width', 'desc' => '产品宽度'],
            ['id' => 'height', 'desc' => '产品高度'],
            ['id' => 'unit', 'desc' => '产品单位'],
            ['id' => 'mktprice', 'desc' => '市场价格'],
            ['id' => 'price', 'desc' => '销售价格'],
            ['id' => 'preferential_price', 'desc' => '优惠价格'],
            ['id' => 'promotion_price', 'desc' => '促销价格'],
            ['id' => 'keywords', 'desc' => '搜索关键字'],
            ['id' => 'intro', 'desc' => '详情'],
            ['id' => 'remark', 'desc' => '备注']
        ];
    }


    public static function exportHeader()
    {
        return [
            ['field' => 'erp_goods_id', 'desc' => 'U8 商品编号', 'type' => DataType::TYPE_STRING],
            ['field' => 'bn', 'desc' => '商品编号', 'type' => DataType::TYPE_STRING],
            ['field' => 'name', 'desc' => '商品名称', 'type' => null],
            ['field' => 'en_name', 'desc' => '英文名称', 'type' => null],
            ['field' => 'spes_desc', 'desc' => '产品规格', 'type' => DataType::TYPE_STRING],
            ['field' => 'image', 'desc' => '商品主图', 'type' => null],
            ['field' => 'album', 'desc' => '商品图册', 'type' => null],
            ['field' => 'brief', 'desc' => '商品简介', 'type' => null],
            ['field' => 'category', 'desc' => '分类', 'type' => null],
            ['field' => 'type', 'desc' => '类型', 'type' => null],
            ['field' => 'brand', 'desc' => '品牌', 'type' => null],
            ['field' => 'weight', 'desc' => '重量', 'type' => null],
            ['field' => 'length', 'desc' => '长度', 'type' => null],
            ['field' => 'width', 'desc' => '宽度', 'type' => null],
            ['field' => 'height', 'desc' => '高度', 'type' => null],
            ['field' => 'unit', 'desc' => '产品单位', 'type' => null],
            ['field' => 'mktprice', 'desc' => '市场价格', 'type' => null],
            ['field' => 'price', 'desc' => '销售价格', 'type' => null],
            ['field' => 'preferential_price', 'desc' => '优惠价格', 'type' => null],
            ['field' => 'promotion_price', 'desc' => '促销价格', 'type' => null],
            ['field' => 'keywords', 'desc' => '关键字', 'type' => DataType::TYPE_STRING],
            ['field' => 'intro', 'desc' => '商品详情', 'type' => null],
            ['field' => 'remark', 'desc' => '备注', 'type' => null]
        ];
    }

    /**
     * 商品列表页统计商品相关 todo 警戒库存设置
     * @param array $baseFilter
     * @return array
     */
    public function staticGoods($baseFilter = [])
    {

        $total = $this->where($baseFilter)->count('id');
        $baseFilter[] = ['marketable', 'eq', self::MARKETABLE_UP];


        $totalMarketableUp = $this->where($baseFilter)->count('id');
        $baseFilter1[] = ['marketable', 'eq', self::MARKETABLE_DOWN];
        $totalMarketableDown = $this->where($baseFilter1)->count('id');
        //警戒库存
        $SettingModel = new Setting();

        $goods_stocks_warn = $SettingModel->getValue('goods_stocks_warn');
        $goods_stocks_warn = $goods_stocks_warn ? $goods_stocks_warn : '10';
        unset($baseFilter['marketable']);
        $productModel = new Products();
        $totalWarn = $productModel->where("(stock - freeze_stock) < " . $goods_stocks_warn)->group('goods_id')->count('id');
        return [
            'totalGoods' => $total,
            'totalMarketableUp' => $totalMarketableUp,
            'totalMarketableDown' => $totalMarketableDown,
            'totalWarn' => $totalWarn,
        ];
    }

    /**
     * 获取重量
     * @param $product_id
     * @return int|mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getWeight($product_id)
    {
        $where[] = ['id', 'eq', $product_id];
        $goods = model('common/Products')->field('goods_id')
            ->where($where)
            ->find();
        if ($goods['goods_id'] != 0) {
            $wh[] = ['id', 'eq', $goods['goods_id']];

            $weight = $this->field('weight')
                ->where($wh)
                ->find();
        } else {
            $weight['weight'] = 0;
        }
        return $weight['weight'] ? $weight['weight'] : 0;
    }

    /**
     * 导出验证
     * @param array $params
     * @return array
     */
    public function exportValidate(&$params = [])
    {
        $result = [
            'status' => false,
            'data' => [],
            'msg' => '参数丢失',
        ];
        return $result;
    }

    /**
     * 获取某个分类的热卖商品
     * @param $cat_id
     * @param int $limit
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getGoodsCatHotGoods($cat_id, $limit = 6)
    {
        $return = [
            'status' => false,
            'msg' => '获取失败',
            'data' => []
        ];
        $where[] = ['is_hot', 'eq', self::HOT_YES];
        $where[] = ['marketable', 'eq', self::MARKETABLE_UP];
        $where[] = ['goods_cat_id', 'eq', $cat_id];
        $return['data']['list'] = $this->field('id,name,image_id,price,brief')
            ->where($where)
            ->limit(0, $limit)
            ->order('ctime DESC')
            ->select();

        $catModel = new GoodsCat();
        $catName = $catModel->getNameById($cat_id);
        $return['data']['name'] = $catName['data'];

        if ($return['data']['list'] !== false) {
            if (count($return['data']['list']) > 0) {
                foreach ($return['data']['list'] as $k => &$v) {
                    $v['image_url'] = _sImage($v['image_id']);
                }
            }
            $return['status'] = true;
            $return['msg'] = '获取成功';
        }
        return $return;
    }

    public function priceLevels()
    {
        return $this->hasMany(GoodsPriceLevels::class, 'goods_id', 'id');
    }

    public function comments()
    {
        return $this->hasMany(GoodsComment::class, 'goods_id', 'id');
    }

    public function relateGoods(bool $required = false)
    {
        $query = $this->belongsToMany(Goods::class, 'relation_goods', 'relation_goods_id', 'main_goods_id');
        if ($required) {
            $query = $query->wherePivot('required', '=', 1);
        }
        return $query;
    }

    public function goodsImages()
    {
        return $this->belongsToMany(Images::class, 'goods_images', 'image_id', 'goods_id');
    }
}
