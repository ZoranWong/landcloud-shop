<?php
// +----------------------------------------------------------------------
// | labgic [ 小程序商城 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2018 http://jihainet.com All rights reserved.
// +----------------------------------------------------------------------
// | Author: mark <jima@jihainet.com>
// +----------------------------------------------------------------------
namespace app\Manage\controller;

use app\common\controller\Manage;
use app\common\model\Brand;
use app\common\model\Goods as goodsModel;
use app\common\model\GoodsCat;
use app\common\model\GoodsGrade;
use app\common\model\GoodsImages;
use app\common\model\GoodsPriceLevels;
use app\common\model\GoodsType;
use app\common\model\GoodsTypeParams;
use app\common\model\Products;
use app\common\model\RelationGoods;
use app\common\model\UserGrade;
use app\common\validate\Goods as GoodsValidate;
use app\common\validate\Products as ProductsValidate;
use Request;
use think\Db;
use think\db\Query;
use think\facade\Log;

/***
 * 商品
 * Class Goods
 * @package app\seller\controller
 * User: wjima
 * Email:1457529125@qq.com
 * Date: 2018-01-11 17:20
 */
class Goods extends Manage
{

    private $spec = [];//规格数组
    static $sku_item;//规格
    static $deep_key;//规格深度
    static $total_item;//总规格


    /**
     * 商品列表
     * @return mixed
     */
    public function index()
    {
        $goodsModel = new goodsModel();
        $statics = $goodsModel->staticGoods();
        $this->assign('statics', $statics);
        $this->assign('searchData', session('searchData'));
        if (Request::isAjax()) {

            $filter = input('request.') ?? session('searchData');
            session('searchData', $filter);
            return $goodsModel->tableData($filter);
        }

        return $this->fetch('index');
    }

    public function add()
    {
        $this->_common();
        //处理会员价
        if ($this->view->gradelist) {
            $gradelist = [];
            foreach ($this->view->gradelist as $key => $value) {
                $gradelist[$key] = $value;
                $gradelist[$key]['grade_price'] = 0;
            }
        }
        $levels = [];
        $this->assign('levels', json_encode($levels));
        $this->assign('goodsId', null);
        $this->assign('tags', "[]");
//        $priceLevelsTable = $this->fetch('goodsPriceLevels');
        return $this->fetch('add');
    }

    /**
     * 编辑商品公共数据
     * User: wjima
     * Email:1457529125@qq.com
     * Date: 2018-01-12 17:34
     */
    private function _common()
    {
        //类型
        $categoriesModel = new GoodsCat();
        $categories = $categoriesModel->getAllCat();
        $this->assign('categoryList', $categories);
//        var_dump($categories);exit();

        //品牌
        $brandModel = new Brand();
        $brandList = $brandModel->getAllBrand();
        $this->assign('brandList', $brandList);

        //会员等级
        $gradeModel = new UserGrade();
        $gradelist = $gradeModel->getAll();
        $this->assign('gradelist', $gradelist);
        $areaModel = new \app\common\model\Area();
        $areas = $areaModel->getArea();
        $this->assign('areas', json_encode($areas[0]['list']));

        hook('goodscommon', $this);//商品编辑、添加时增加钩子

    }

    /**
     * 获取子分类信息
     * @return array
     * User: wjima
     * Email:1457529125@qq.com
     * Date: 2018-01-12 17:51
     */
    public function getCat()
    {

        $id = input('post.cat_id/d');
        if ($id) {

            $goodsCatModel = new GoodsCat();
            $catList = $goodsCatModel->getCatByParentId($id);

            return [
                'data' => $catList,
                'msg' => '获取成功',
                'status' => true,
            ];
        } else {
            return [
                'data' => '',
                'msg' => '关键参数丢失',
                'status' => false,
            ];
        }
    }

    protected function syncPriceLevels($goodsId)
    {
        $levels = \think\facade\Request::post('levels') ?: \think\facade\Request::put('levels');
        if (!isset($levels)) {
            $levels = [];
        }
        return GoodsPriceLevels::sync($goodsId, $levels);
    }

    protected function syncRelationGoods($goodsId)
    {
        $relations = \think\facade\Request::post('relations') ?: \think\facade\Request::put('relations');
        return RelationGoods::sync($goodsId, $relations);
    }

    /**
     * 保存商品
     * User:wjima
     * Email:1457529125@qq.com
     * @return array
     */
    public function doAdd()
    {
        $result = [
            'status' => false,
            'msg' => '',
            'data' => '',
        ];
        //商品数据组装并校检
        $checkData = $this->checkGoodsInfo();
        if (!$checkData['status']) {
            $result['msg'] = $checkData['msg'];
            return $result;
        }
        $data = $checkData['data'];
        //验证商品数据
        $goodsModel = new goodsModel();
        $productsModel = new Products();
        $goodsModel->startTrans();
        $goods_id = $goodsModel->doAdd($data['goods']);
        if (!$goods_id) {
            Db::rollback();
            $result['msg'] = '商品数据保存失败';
            return $result;
        }

        if (!$this->syncPriceLevels($goods_id)) {
            Db::rollback();
            $result['msg'] = '商品数据保存失败';
            return $result;
        }

        if (!$this->syncRelationGoods($goods_id)) {
            Db::rollback();
            $result['msg'] = '商品数据保存失败';
            return $result;
        }

        $open_spec = input('post.open_spec', 0);
        if ($open_spec) {
            //多规格
            $product = input('post.product/a', []);
            $total_stock = $price = $costprice = $mktprice = 0;
            $isExitDefalut = false;
            foreach ($product as $key => $val) {
                $tmp_product['goods']['price'] = isset($val['price']) ? $val['price'] : 0;
                $tmp_product['goods']['costprice'] = isset($val['costprice']) ? $val['costprice'] : 0;
                $tmp_product['goods']['mktprice'] = isset($val['mktprice']) ? $val['mktprice'] : 0;
                $tmp_product['goods']['marketable'] = isset($val['marketable']) ? $val['marketable'] : $productsModel::MARKETABLE_DOWN;
                $tmp_product['goods']['stock'] = isset($val['stock']) ? $val['stock'] : 0;
                $sn = get_sn(4);
                $tmp_product['goods']['sn'] = isset($val['sn']) ? $val['sn'] : $sn;
                $tmp_product['goods']['product_spes'] = $key;
                $tmp_product['goods']['image_id'] = isset($val['image_id']) ? $val['image_id'] : '';
                $tmp_product['goods']['is_defalut'] = isset($val['is_defalut']) ? $productsModel::DEFALUT_YES : $productsModel::DEFALUT_NO;

                if ($tmp_product['goods']['is_defalut'] == $productsModel::DEFALUT_YES) {
                    $isExitDefalut = true;
                }
                $checkData = $this->checkProductInfo($tmp_product, $goods_id);
                if (!$checkData['status']) {
                    $result['msg'] = $checkData['msg'];
                    Db::rollback();
                    return $result;
                }
                $data['product'] = $checkData['data']['product'];
                $product_id = $productsModel->doAdd($data['product']);
                if (!$product_id) {
                    $goodsModel->rollback();
                    $result['msg'] = '货品数据保存失败';
                    return $result;
                }
                $total_stock = $total_stock + $tmp_product['goods']['stock'];
                if ($tmp_product['goods']['is_defalut'] == $productsModel::DEFALUT_YES) {//todo 取商品默认价格
                    $price = $tmp_product['goods']['price'];
                    $costprice = $tmp_product['goods']['costprice'];
                    $mktprice = $tmp_product['goods']['mktprice'];
                }
            }
            if (!$isExitDefalut) {
                $result['msg'] = '请选择默认货品';
                $goodsModel->rollback();
                return $result;
            }
            //更新总库存
            $upData['stock'] = $total_stock;
            $upData['price'] = $price;
            $upData['costprice'] = $costprice;
            $upData['mktprice'] = $mktprice;
            $goodsModel->updateGoods($goods_id, $upData);
        } else {
            $sn = get_sn(4);
            $data['goods']['sn'] = input('post.goods.sn', $sn);//货品编码
            $data['goods']['is_defalut'] = $productsModel::DEFALUT_YES;
            //$data['product'] = $checkData['data']['product'];
            $checkData = $this->checkProductInfo($data, $goods_id);

            if (!$checkData['status']) {
                $result['msg'] = $checkData['msg'];
                $goodsModel->rollback();
                return $result;
            }
            $data = $checkData['data'];
            $product_id = $productsModel->doAdd($data['product']);
            if (!$product_id) {
                $goodsModel->rollback();
                $result['msg'] = '货品数据保存失败';
                return $result;
            }
        }
        //保存会员价
        $grade_price = input('post.goods.grade_price/a', []);
        if ($grade_price) {
            $grade_price_arr = [];
            foreach ($grade_price as $key => $value) {
                $grade_price_arr[] = [
                    'goods_id' => $goods_id,
                    'grade_id' => $key,
                    'grade_price' => $value,
                ];
            }
            $goodsGrade = new GoodsGrade();
            $goodsGrade->where(['goods_id' => $goods_id])->delete();
            if (!$goodsGrade->saveAll($grade_price_arr)) {
                $goodsModel->rollback();
                $result['msg'] = '会员价保存失败';
                return $result;
            }
        }

        //保存图片
        if (isset($data['images']) && count($data['images']) > 1) {
            $imgRelData = [];
            $i = 0;
            foreach ($data['images'] as $key => $val) {
                if ($key == 0) {
                    continue;
                }
                $imgRelData[$i]['goods_id'] = $goods_id;
                $imgRelData[$i]['image_id'] = $val;
                $imgRelData[$i]['sort'] = $i;
                $i++;
            }
            $goodsImagesModel = new GoodsImages();
            if (!$goodsImagesModel->batchAdd($imgRelData, $goods_id)) {
                $goodsModel->rollback();
                $result['msg'] = '商品图片保存失败';
                return $result;
            }
        }
        $goodsModel->commit();

        array_push($data, ['goods_id' => $goods_id]);
        hook('addgoodsafter', $data);//添加商品后增加钩子

        $result['msg'] = '保存成功';
        $result['status'] = true;
        return $result;
    }

    /**
     * 校检并返回商品信息
     * User:wjima
     * Email:1457529125@qq.com
     * @return mixed
     */
    private function checkGoodsInfo($isEdit = false)
    {
        $result = [
            'status' => false,
            'msg' => '',
            'data' => '',
        ];
        $bn = get_sn(3);
        $data['goods']['name'] = input('post.goods.name', '');
        $data['goods']['goods_cat_id'] = input('post.goods_cat_id', '0');
        $data['goods']['goods_type_id'] = input('post.goods_type_id', 0);
        $data['goods']['brand_id'] = input('post.goods.brand_id', 0);
        $data['goods']['bn'] = input('post.goods.bn', $bn);
        if (empty($data['goods']['bn'])) {
            $data['goods']['bn'] = $bn;
        }
        $data['goods']['brief'] = input('post.goods.brief', '');
        $data['goods']['intro'] = input('post.goods.intro', '');
        $data['goods']['price'] = input('post.goods.price', '');
        $data['goods']['costprice'] = input('post.goods.costprice', '');
        $data['goods']['mktprice'] = input('post.goods.mktprice', '');
        $data['goods']['weight'] = input('post.goods.weight', '');
        $data['goods']['stock'] = input('post.goods.stock', '');
        $data['goods']['unit'] = input('post.goods.unit', '');
        $data['goods']['marketable'] = input('post.goods.marketable', '2');
        $data['goods']['is_recommend'] = input('post.goods.is_recommend', '2');
        $data['goods']['erp_goods_id'] = input('post.goods.erp_goods_id', '');
        $keywords = input('post.goods.keywords', null);
        if ($keywords) {
            if (is_string($keywords)) {
                $keywords = json_decode($keywords);
            }
            $data['goods']['keywords'] = $keywords;
        }
//        var_dump(input('post.goods.erp_goods_id'));exit;
        $data['goods']['is_hot'] = input('post.goods.is_hot', '2');
        $data['goods']['spes_desc'] = input('post.goods.spes_desc', '');
        Log::debug('--------spes_desc---------'.input('post.goods.spes_desc', ''));
        $open_spec = input('post.open_spec', 0);
        $specdesc = input('post.spec/a', []);
        if ($specdesc && $open_spec) {
            if (count($specdesc) == 1) {//优化只一个规格的情况
                $product = input('post.product/a', []);
                foreach ((array)$specdesc as $key => $val) {
                    foreach ($val as $k => $v) {
                        $temp_product_key = $key . ':' . $v;
                        if (!isset($product[$temp_product_key])) {
                            unset($specdesc[$key][$k]);
                        }
                    }
                }
            }
            $data['goods']['spes_desc'] = serialize($specdesc);
        }

        //商品参数处理
        $params = [];
        $tempParams = input('post.goods.params/a', []);
        if ($tempParams) {
            foreach ($tempParams as $key => $val) {
                if (is_array($val)) {
                    foreach ($val as $vk => $vv) {
                        $params[$key][] = $vk;
                    }
                } elseif ($val !== '') {
                    $params[$key] = $val;
                }
            }
            $data['goods']['params'] = serialize($params);
        } else {
            $data['goods']['params'] = '';
        }
        $images = input('post.goods.img/a', []);
        if (count($images) <= 0) {
            $result['msg'] = '请先上传图片';
            return $result;
        }
        $data['goods']['image_id'] = reset($images);
        $data['images'] = $images;

        if ($isEdit) {
            $data['goods']['id'] = input('post.goods.id/d', 0);
            $validate = new GoodsValidate();
            if (!$validate->scene('edit')->check($data['goods'])) {
                $result['msg'] = $validate->getError();
                return $result;
            }
        } else {
            $validate = new GoodsValidate();
            if (!$validate->check($data['goods'])) {
                $result['msg'] = $validate->getError();
                return $result;
            }
        }

        $result['data'] = $data;
        $result['status'] = true;
        return $result;
    }

    /**
     * 检查并组装货品数据
     * User:wjima
     * Email:1457529125@qq.com
     * @return bool
     */
    private function checkProductInfo($data, $goods_id = 0, $isEdit = false)
    {
        $result = [
            'status' => false,
            'msg' => '',
            'data' => '',
        ];
        if (!$goods_id) {
            $result['msg'] = '商品ID不能为空';
            return $result;
        }
        $productsModel = new Products();
        //单规格
        $data['product']['goods_id'] = $goods_id;
        $data['product']['sn'] = $data['goods']['sn'];//货品编码
        $data['product']['price'] = $data['goods']['price'];//货品价格
        $data['product']['costprice'] = $data['goods']['costprice'];//货品成本价
        $data['product']['mktprice'] = $data['goods']['mktprice'];//货品市场价
        $data['product']['marketable'] = $data['goods']['marketable'];//是否上架
        $data['product']['stock'] = $data['goods']['stock'];//货品库存
        $data['product']['image_id'] = $data['goods']['image_id'];//货品图片
        $data['product']['is_defalut'] = $data['goods']['is_defalut'] ? $data['goods']['is_defalut'] : $productsModel::DEFALUT_YES;//是否默认货品
        $open_spec = input('post.open_spec', 0);
        if ($open_spec && $data['goods']['product_spes']) {
            $data['product']['spes_desc'] = $data['goods']['product_spes'];
        }
        if (!$data['product']['sn']) {
            $data['product']['sn'] = get_sn(4);
        }
        if ($isEdit) {
            $validate = new ProductsValidate();
            if (!$validate->scene('edit')->check($data['product'])) {
                $result['msg'] = $validate->getError();
                return $result;
            }
        } else {
            $validate = new ProductsValidate();
            if (!$validate->check($data['product'])) {
                $result['msg'] = $validate->getError();
                return $result;
            }
        }

        $result['data'] = $data;
        $result['status'] = true;
        return $result;
    }

    /**
     * User: wjima
     * Email:1457529125@qq.com
     * Date: 2018-01-23 11:32
     */
    public function getSpec()
    {
        $result = [
            'status' => false,
            'msg' => '关键参数丢失',
            'data' => '',
        ];
        $this->view->engine->layout(false);
        $type_id = input('post.type_id');
        if (!$type_id) {
            return $result;
        }
        $goodsTypeModel = new GoodsType();
        $res = $goodsTypeModel->getTypeValue($type_id);

        $html = '';

        if ($res['status'] == true) {

            $this->assign('typeInfo', $res['data']);
            if (!$res['data']['spec']->isEmpty()) {
                $spec = [];
                foreach ($res['data']['spec']->toArray() as $key => $val) {
                    $spec[$key]['name'] = $val['spec']['name'];
                    $spec[$key]['specValue'] = $val['spec']['getSpecValue'];
                }
                $this->assign('spec', $spec);
            }
            if ($res['data']['spec']->isEmpty()) {
                $this->assign('canOpenSpec', 'false');
            } else {
                $this->assign('canOpenSpec', 'true');
            }
            //获取参数信息
            $goodsTypeParamsModel = new GoodsTypeParams();
            $typeParams = $goodsTypeParamsModel->getRelParams($type_id);
            $this->assign('typeParams', $typeParams);
            $areaModel = new \app\common\model\Area();
            $areas = $areaModel->getArea();
            $this->assign('areas', json_encode($areas[0]['list']));
            $levels = [];
            $this->assign('levels', json_encode($levels));
            $this->assign('goodsId', null);
            $html = $this->fetch('getSpec');
            $result['status'] = true;
            $result['msg'] = '获取成功';
            $result['data'] = $html;
        }
        return $result;
    }

    /***
     * 生成多规格html
     * @return array
     * User: wjima
     * Email:1457529125@qq.com
     * Date: 2018-01-23 15:34
     */
    public function getSpecHtml()
    {
        $result = [
            'status' => false,
            'msg' => '关键参数丢失',
            'data' => '',
        ];
        $this->view->engine->layout(false);
        $spec = input('post.spec/a');
        $goods_id = input('post.goods_id/d', 0);
        $goodsDefault = input('post.goods/a', []);
        $products = [];
        if ($goods_id) {
            $goodsModel = new goodsModel();
            $goods = $goodsModel->getOne($goods_id, 'id,image_id');
            if (!$goods['status']) {
                return '商品不存在';
            }
            $products = $goods['data']->products;
        }
        if ($spec) {
            $specValue = [];
            $total = count($spec);
            foreach ($spec as $key => $val) {
                $this->spec[] = $key;
            }
            $items = $this->getSkuItem($spec, -1);
            foreach ((array)$items as $key => $val) {
                $items[$key]['price'] = $goodsDefault['price'];
                $items[$key]['costprice'] = $goodsDefault['costprice'];
                $items[$key]['mktprice'] = $goodsDefault['mktprice'];
                if (isset($goodsDefault['sn']) && $goodsDefault['sn']) {
                    $items[$key]['sn'] = $goodsDefault['sn'] . '-' . ($key + 1);
                }
                $items[$key]['stock'] = $goodsDefault['stock'];
            }
            if ($products) {
                foreach ($items as $key => $val) {
                    foreach ($products as $product) {
                        if ($val['spec_name'] == $product['spes_desc']) {
                            $items[$key] = array_merge((array)$val, (array)$product);
                            $items[$key]['product_id'] = $product['id'];
                        }
                    }
                }
            }
            $this->assign('items', $items);
        }
        $html = $this->fetch('getSpecHtml');
        $result['data'] = $html;
        $result['status'] = true;
        $result['msg'] = '获取成功';
        return $result;

    }


    private function getSkuItem($data, $index = -1, $sku_item = [])
    {
        self::$total_item = array();
        if ($index < 0) {
            self::$deep_key = count($data) - 1;
            $this->getSkuItem($data, 0, $sku_item);
        } else {
            if ($index == 0) {
                $first = $data[$this->spec[$index]];

                foreach ($first as $key => $value) {
                    self::$total_item[$key] = array(
                        'spec_name' => $this->spec[$index] . ':' . $value,
                        'spec_key' => $this->spec[$index],
                    );
                }
            } else {
                $first = $data[$this->spec[$index]];

                if (count($sku_item) >= count($first)) {
                    foreach ($first as $key => $value) {
                        foreach ($sku_item as $s => $v) {

                            self::$total_item[] = array(
                                'spec_name' => $v['spec_name'] . ',' . $this->spec[$index] . ':' . $value,
                                'spec_key' => $v['spec_key'] . '_' . $this->spec[$index],
                            );
                        }
                    }
                } else {
                    if ($sku_item) {
                        foreach ($sku_item as $key => $value) {
                            foreach ($first as $fkey => $fvalue) {
                                self::$total_item[] = array(
                                    'spec_name' => $value['spec_name'] . ',' . $this->spec[$index] . ':' . $fvalue,
                                    'spec_key' => $value['spec_key'] . '_' . $this->spec[$index],
                                );
                            }
                        }
                    }
                }
            }
            if ($index < self::$deep_key) {
                $this->getSkuItem($data, $index + 1, self::$total_item);
            }
        }
        return self::$total_item;

    }

    protected function assignRelationGoods($id)
    {
        $goods = RelationGoods::where('main_goods_id', 'eq', $id)->select();
        $this->assign('relationGoods', $goods ? json_encode($goods->toArray()) : '[]');
    }

    protected function assignPriceLevels($id)
    {
        $levels = GoodsPriceLevels::where('goods_id', 'eq', $id)->select();
        $this->assign('levels', $levels ? json_encode($levels->toArray()) : '[]');
    }

    /***
     * 编辑商品
     * User: wjima
     * Email:1457529125@qq.com
     * Date: 2018-02-06 9:52
     */
    public function edit()
    {
        $goods_id = input("id");
        $goodsModel = new goodsModel();
        $productsModel = new Products();
        $goods = $goodsModel->getOne($goods_id, '*');

        if (!$goods['status']) {
            $this->error("无此商品");
        }
        $this->assign('open_spec', '0');
        $this->assign('data', $goods['data']);
        $this->assign('products', $goods['data']['products']);
        $this->assign('product', $goods['data']['products'][0]);
        $this->assign('tags', json_encode($goods['data']['keywords'] ?: []));
//        if ($goods['data']['spes_desc'] != '') {
//            $this->assign('open_spec', '1');
//        } else {
//            $this->assign('open_spec', '0');
//        }
        //类型
//        $goodsTypeModel = new GoodsType();
        $res = $this->getEditSpec($goods['data']['goods_type_id'], $goods['data']);
        $this->assign('spec_html', $res['data']);
        $goodsCatModel = new GoodsCat();
        $catids = $goodsCatModel->getCatIdsByLastId($goods['data']['goods_cat_id']);
        $this->assign('catids', $catids);

        $this->_common();
        //处理会员价
        $goodsGradeModel = new GoodsGrade();
        $goodsGrade = $goodsGradeModel->getGradePrice($goods_id);
        if ($this->view->gradelist) {
            $gradelist = [];
            foreach ($this->view->gradelist as $key => $value) {
                $gradelist[$key] = $value;
                if ($goodsGrade['status']) {
                    foreach ($goodsGrade['data'] as $k => $v) {
                        if ($value['id'] == $v['grade_id']) {
                            $gradelist[$key]['grade_price'] = $v['grade_price'];
                        }
                    }
                } else {
                    $gradelist[$key]['grade_price'] = 0;
                }
            }
        }
        $this->assign('gradelist', $gradelist);
        $this->assignPriceLevels($goods_id);
        $this->assignRelationGoods($goods_id);
        $this->assign('goodsId', $goods_id);

        return $this->fetch('edit');
    }

    /**
     * 编辑商品
     */
    public function doEdit()
    {
        $result = [
            'status' => false,
            'msg' => '',
            'data' => '',
        ];
        //商品数据组装并校检
        $checkData = $this->checkGoodsInfo(true);
        if (!$checkData['status']) {
            $result['msg'] = $checkData['msg'];
            return $result;
        }
        $data = $checkData['data'];
        Log::debug('----------- goods data ----------'. json_encode($data));
        //验证商品数据
        $goodsModel = new goodsModel();
        $productsModel = new Products();
        $goodsModel->startTrans();
        $updateRes = $goodsModel->updateGoods($data['goods']['id'], $data['goods']);
        $goods_id = $data['goods']['id'];
        if ($updateRes === false) {
            Db::rollback();
            $result['msg'] = '商品数据保存失败';
            return $result;
        }
        if (!$this->syncPriceLevels($goods_id)) {
            Db::rollback();
            $result['msg'] = '商品数据保存失败';
            return $result;
        }

        if (!$this->syncRelationGoods($goods_id)) {
            Db::rollback();
            $result['msg'] = '商品数据保存失败';
            return $result;
        }

        $productIds = [];
        $products = $productsModel->field('id')->where(['goods_id' => $goods_id])->select()->toArray();
        $productIds = array_column($products, 'id');

        $open_spec = input('post.open_spec', 0);

        if ($open_spec) {
            //多规格
            $product = input('post.product/a', []);
            $total_stock = $price = $costprice = $mktprice = 0;
            $isExitDefalut = false;
            $exit_product = [];
            if (isset($product['id']) && !$product['id']) {
                unset($product['id']);
            }

            foreach ($product as $key => $val) {
                $tmp_product['goods']['price'] = !empty($val['price']) ? $val['price'] : 0;
                $tmp_product['goods']['costprice'] = !empty($val['costprice']) ? $val['costprice'] : 0;
                $tmp_product['goods']['mktprice'] = !empty($val['mktprice']) ? $val['mktprice'] : 0;
                $tmp_product['goods']['marketable'] = !empty($val['marketable']) ? $val['marketable'] : $productsModel::MARKETABLE_UP;
                $tmp_product['goods']['stock'] = !empty($val['stock']) ? $val['stock'] : 0;
                $sn = get_sn(4);
                $tmp_product['goods']['sn'] = !empty($val['sn']) ? $val['sn'] : $sn;
                $tmp_product['goods']['product_spes'] = $key;
                $tmp_product['goods']['image_id'] = !empty($val['image_id']) ? $val['image_id'] : '';
                $tmp_product['goods']['is_defalut'] = !empty($val['is_defalut']) ? $productsModel::DEFALUT_YES : $productsModel::DEFALUT_NO;
                if ($tmp_product['goods']['is_defalut'] == $productsModel::DEFALUT_YES) {
                    $isExitDefalut = true;
                }

                if (isset($val['id']) && $val['id']) {
                    $tmp_product['product']['id'] = $val['id'];
                    $checkData = $this->checkProductInfo($tmp_product, $goods_id, true);

                } else {
                    unset($tmp_product['product']['id']);
                    $checkData = $this->checkProductInfo($tmp_product, $goods_id);
                }
                if (!$checkData['status']) {
                    $result['msg'] = $checkData['msg'];
                    $goodsModel->rollback();
                    return $result;
                }
                $data['product'] = $checkData['data']['product'];

                if (isset($val['id']) && $val['id']) {

                    $productRes = $productsModel->updateProduct($val['id'], $data['product']);
                    if (in_array($val['id'], $productIds)) {
                        $productIds = unsetByValue($productIds, $val['id']);
                    }
                    if ($val['id']) {
                        $exit_product[] = $val['id'];
                    }
                } else {
                    unset($data['product']['id']);
                    $productRes = $productsModel->doAdd($data['product']);
                    if (is_numeric($productRes)) {
                        $exit_product[] = $productRes;
                    }
                }
                if ($productRes === false) {
                    $goodsModel->rollback();
                    $result['msg'] = '货品数据保存失败';
                    return $result;
                }

                $total_stock = $total_stock + $tmp_product['goods']['stock'];
                if ($tmp_product['goods']['is_defalut'] == $productsModel::DEFALUT_YES) {//todo 取商品默认价格
                    $price = $tmp_product['goods']['price'];
                    $costprice = $tmp_product['goods']['costprice'];
                    $mktprice = $tmp_product['goods']['mktprice'];
                }
            }
            if (!$isExitDefalut) {
                $result['msg'] = '请选择默认货品';
                $goodsModel->rollback();
                return $result;
            }
            //更新总库存
            $upData['stock'] = $total_stock;
            $upData['price'] = $price;
            $upData['costprice'] = $costprice;
            $upData['mktprice'] = $mktprice;
            $goodsModel->updateGoods($goods_id, $upData);
            //删除多余规格
            $productsModel->where([['id', 'not in', $exit_product], ['goods_id', '=', $goods_id]])->delete();
        } else {
            $sn = get_sn(4);
            $data['goods']['sn'] = input('post.goods.sn', $sn);//货品编码
            $data['goods']['is_defalut'] = $productsModel::DEFALUT_YES;
            //$data['product'] = $checkData['data']['product'];
            $data['product']['id'] = input('post.product.id/d', 0);
            if ($data['product']['id']) {
                $checkData = $this->checkProductInfo($data, $goods_id, true);
            } else {
                $checkData = $this->checkProductInfo($data, $goods_id);
            }
            if (!$checkData['status']) {
                $result['msg'] = $checkData['msg'];
                $goodsModel->rollback();
                return $result;
            }
            $data = $checkData['data'];

            if ($data['product']['id']) {
                if (in_array($data['product']['id'], $productIds)) {
                    $productIds = unsetByValue($productIds, $data['product']['id']);
                }
                $updateRes = $productsModel->updateProduct($data['product']['id'], $data['product']);
            } else {
                $updateRes = $productsModel->doAdd($data['product']);
            }

            if ($updateRes === false) {
                $goodsModel->rollback();
                $result['msg'] = '货品数据保存失败';
                return $result;
            }
        }
        //删除多余货品数据
        if ($productIds) {
            $productsModel->deleteProduct($productIds);
        }
        //保存会员价
        $grade_price = input('post.goods.grade_price/a', []);
        if ($grade_price) {
            $grade_price_arr = [];
            foreach ($grade_price as $key => $value) {
                $grade_price_arr[] = [
                    'goods_id' => $goods_id,
                    'grade_id' => $key,
                    'grade_price' => $value,
                ];
            }
            $goodsGrade = new GoodsGrade();
            $goodsGrade->where(['goods_id' => $goods_id])->delete();
            if (!$goodsGrade->saveAll($grade_price_arr)) {
                $goodsModel->rollback();
                $result['msg'] = '会员价保存失败';
                return $result;
            }
        }

        //保存图片
        if (isset($data['images']) && count($data['images']) >= 1) {
            $imgRelData = [];
            $i = 0;
            foreach ($data['images'] as $key => $val) {
                if ($key == 0) {
                    continue;
                }
                $imgRelData[$i]['goods_id'] = $goods_id;
                $imgRelData[$i]['image_id'] = $val;
                $imgRelData[$i]['sort'] = $i;
                $i++;
            }
            $goodsImagesModel = new GoodsImages();
            if (!$goodsImagesModel->batchAdd($imgRelData, $goods_id)) {
                $goodsModel->rollback();
                $result['msg'] = '商品图片保存失败';
                return $result;
            }
        }
        $goodsModel->commit();
        hook('editgoodsafter', $data);//编辑商品后增加钩子
        $result['msg'] = '保存成功';
        $result['status'] = true;
        return $result;
    }

    /**
     * 商品删除
     * User: wjima
     * Email:1457529125@qq.com
     * Date: 2018-02-06 10:42
     */
    public function del()
    {
        $result = [
            'status' => false,
            'msg' => '关键参数丢失',
            'data' => '',
        ];
        $goods_id = input("id");
        $goodsModel = new goodsModel();
        if (!$goods_id) {
            return $result;
        }
        $delRes = $goodsModel->delGoods($goods_id);
        if (!$delRes['status']) {
            $result['msg'] = $delRes['msg'];
            return $result;
        }
        $result['status'] = true;
        $result['msg'] = '删除成功';
        return $result;
    }

    private function getEditSpec($type_id, $goods)
    {
        $result = [
            'status' => false,
            'msg' => '获取成功',
            'data' => '',
        ];

//        if (!$type_id) {
//            return $result;
//        }

        //$spes_desc = unserialize($goods['spes_desc']);
        $this->assign('goods', $goods);
        $goodsTypeModel = new GoodsType();
        $res = $goodsTypeModel->getTypeValue($type_id);

//        $html = '';

        if (true) {
            $this->assignPriceLevels($goods['id']);
            $this->assignRelationGoods($goods['id']);
            $this->assign('goodsId', $goods['id']);
            $areaModel = new \app\common\model\Area();
            $areas = $areaModel->getArea();
            $this->assign('areas', json_encode($areas[0]['list']));
            $this->view->engine->layout(false);

            $html = $this->fetch('editGetSpecHtml');
            $this->view->engine->layout(true);

            $result['status'] = true;
            $result['data'] = $html;
        }
        return $result;
    }


    /**
     * 评论列表
     * @return array|mixed
     */
    public function commentList()
    {
        if (!Request::isAjax()) {
            $goods_id = input('goods_id');
            $this->assign('goods_id', $goods_id);
            return $this->fetch('commentList');
        } else {
            $goods_id = input('goods_id');
            $page = input('page', 1);
            $limit = input('limit', 10);
            $res = model('common/GoodsComment')->getList($goods_id, $page, $limit);
            if ($res['status']) {
                foreach ($res['data']['list'] as $k => $v) {
                    $v['nickname'] = $v['user']['nickname'];
                    $v['evaluate'] = config('params.comment')[$v['score']];
                    $v['ctime'] = date('Y-m-d H:i:s', $v['ctime']);
                }

                $return_data = [
                    'status' => true,
                    'msg' => '获取评价成功',
                    'count' => $res['data']['count'],
                    'data' => $res['data']['list'],
                ];
            } else {
                $return_data = [
                    'status' => false,
                    'msg' => '获取评价失败',
                    'count' => $res['count'],
                    'data' => $res['data'],
                ];
            }
            return $return_data;
        }
    }


    /**
     * 获取单条评价
     * @return mixed
     */
    public function getCommentInfo()
    {
        $id = input('id');
        $res = model('common/GoodsComment')->getCommentInfo($id);
        return $res;
    }


    /**
     * 商家回复
     * @return mixed
     */
    public function sellerContent()
    {
        $id = input('id');
        $seller_content = input('seller_content');
        $res = model('common/GoodsComment')->sellerComment($id, $seller_content);
        return $res;
    }


    /**
     * 显示不显示
     * @return mixed
     */
    public function setDisplay()
    {
        $id = input('id');
        $res = model('common/GoodsComment')->setDisplay($id);
        return $res;
    }

    /**
     * 批量上下架
     * @return array
     */
    public function batchMarketable()
    {
        $result = [
            'status' => false,
            'data' => [],
            'msg' => '参数丢失',
        ];
        $ids = input('ids/a', []);
        $type = input('type/s', 'up');
        if (count($ids) <= 0) {
            return $result;
        }
        $goodsModel = new goodsModel();
        $res = $goodsModel->batchMarketable($ids, $type);
        if ($res !== false) {
            $result['status'] = true;
            $result['msg'] = '操作成功';
        } else {
            $result['msg'] = '操作失败';
        }
        return $result;
    }

    /**
     * 批量删除商品
     * @return array
     */
    public function batchDel()
    {
        $result = [
            'status' => false,
            'data' => [],
            'msg' => '参数丢失',
        ];
        $ids = input('ids/a', []);
        if (count($ids) <= 0) {
            return $result;
        }
        $goodsModel = new goodsModel();
        foreach ($ids as $goods_id) {
            $delRes = $goodsModel->delGoods($goods_id);
            if (!$delRes['status']) {
                $result['msg'] = $delRes['msg'];
                return $result;
            }
        }
        $result['status'] = true;
        $result['msg'] = '删除成功';
        return $result;
    }

    /**
     * 商品搜索
     * @return mixed
     */
    public function goodsSearch()
    {
        $this->_common();
        $this->assign('searchData', session('searchData'));
//        var_dump(session('searchData')); exit;
        $this->view->engine->layout(false);
        return $this->fetch('goodsSearch');
    }

    /**
     * 更改状态
     * @return array
     */
    public function changeState()
    {
        $result = [
            'status' => false,
            'data' => [],
            'msg' => '参数丢失',
        ];
        $id = input('post.id/d', 0);
        $state = input('post.state/s', 'true');
        $type = input('post.type/s', 'hot');

        if (!$id) {
            return $result;
        }
        $iData = [];
        if ($state == 'true') {
            $state = '1';
        } else {
            $state = '2';
        }
        if ($type == 'hot') {
            $iData['is_hot'] = $state;
        } elseif ($type == 'rec') {
            $iData['is_recommend'] = $state;
        }
        if (!$iData) {
            return $result;
        }
        $goodsModel = new goodsModel();
        if ($goodsModel->save($iData, ['id' => $id])) {
            $result['msg'] = '设置成功';
            $result['status'] = true;
        } else {
            $result['msg'] = '设置失败';
            $result['status'] = false;
        }
        return $result;
    }

    /**
     * 更新排序
     * @return array
     */
    public function updateSort()
    {
        $result = [
            'status' => false,
            'data' => [],
            'msg' => '参数丢失',
        ];
        $field = input('post.field/s');
        $value = input('post.value/d');
        $id = input('post.id/d', '0');
        if (!$field || !$value || !$id) {
            $result['msg'] = '参数丢失';
            $result['status'] = false;
        }
        $goodsModel = new goodsModel();
        if ($goodsModel->updateGoods($id, [$field => $value])) {
            $result['msg'] = '更新成功';
            $result['status'] = true;
        } else {
            $result['msg'] = '更新失败';
            $result['status'] = false;
        }
        return $result;
    }

    public function goodsForRelation()
    {
        $result = [
            'status' => false,
            'msg' => '获取成功',
            'data' => '',
        ];
        $goodsId = \think\facade\Request::get('goods_id');
        $search = \think\facade\Request::get('search');
        $limit = \think\facade\Request::get('limit');
        $query = \app\common\model\Goods::with(['defaultImage'])->where('id', 'neq', $goodsId);
        if ($search) {
            $query->where(function ($query) use ($search) {
                /**@var Query $query * */
                $query->where('name', 'like', "%{$search}%")
                    ->whereOr('erp_goods_id', 'like', "%{$search}%")
                    ->whereOr('bn', 'like', "%{$search}%");
            });
        }
        $relationGoods = $goodsId ? RelationGoods::where('main_goods_id', 'eq', $goodsId)->select() : null;
        if ($relationGoods && $relationGoods->count() > 0) {
            $field = '';
            foreach ($relationGoods as $relationGood) {
                $field .= ", {$relationGood['relation_goods_id']}";
            }
            $field = trim($field, ',');
            $query->order(Db::raw("`id` NOT IN ( {$field})"));
        }

        $goods = $query->paginate($limit);
        $result['sql'] = $query->getLastSql();
        $count = $query->count();
        $result['count'] = $count;
        $result['code'] = 0;
        $result['status'] = true;
        $result['data'] = (new \app\common\model\Goods())->tableFormat($goods->getCollection());
        return $result;
    }

}
