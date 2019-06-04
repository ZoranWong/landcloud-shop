<?php
/**
 * ProductExporter.php
 * User: katherine
 * Date: 19-5-8 下午3:01
 */

namespace app\service\excel\export;

use app\common\model\Goods as GoodsModel;
use app\common\model\GoodsImages;
use app\service\excel\BaseGenerator;

class ProductExporter extends BaseGenerator
{

    public function model(): string
    {
        return GoodsModel::class;
    }

    public function fileName(): string
    {
        return "商品表";
    }

    public function getExportData($params): array
    {
        $goodsModel = new GoodsModel();
        $imagesModel = new GoodsImages();

        $filter = json_decode($params, true);

        if (isset($filter['ids'])) {
            if ($filter['ids']) {
                $filter['id'] = explode(',', $filter['ids']);
            }
            unset($filter['ids']);
        }

        $result = [
            'status' => true,
            'data' => [],
            'msg' => ''
        ];

        $goodsData = $goodsModel->tableData($filter, false);
        $finalGoodsList = [];
        if ($goodsData['count'] > 0) {
            $goodsList = $goodsData['data'];
            foreach ($goodsList as $key => $goods) {
                $finalGoodsList[$key] = [
                    'erp_goods_id' => $goods['erp_goods_id'],
                    'bn' => $goods['bn'],
                    'name' => $goods['name'],
                    'en_name' => $goods['en_name'],
                    'spes_desc' => $goods['spes_desc'],
                    'brief' => $goods['brief'],
                    'intro' => $goods['intro'],
                    'weight' => $goods['weight'],
                    'length' => $goods['length'],
                    'width' => $goods['width'],
                    'height' => $goods['height'],
                    'unit' => $goods['unit'],
                    'price' => $goods['price'],
                    'mktprice' => $goods['mktprice'],
                    'preferential_price' => $goods['preferential_price'],
                    'promotion_price' => $goods['promotion_price'],
                    'remark' => $goods['remark']
                ];

                $finalGoodsList[$key]['image'] = _sImage($goods['image_id']);
                //取出图片集
                $images = $imagesModel->where(['goods_id' => $goods['id']])->order('sort asc')->select();
                $album = [];
                if (isset($goods['image_url'])) {
                    $album[] = $goods['image_url'];
                }
                if (!$images->isEmpty()) {
                    foreach ($images as $v) {
                        $album[] = _sImage($v['image_id']);
                    }
                }
                $finalGoodsList[$key]['album'] = json_encode($album);
                $finalGoodsList[$key]['category'] = $goods['goodsCat']['name'];
                $finalGoodsList[$key]['type'] = $goods['goodsType']['name'];
                $finalGoodsList[$key]['brand'] = $goods['brand']['name'];
                $finalGoodsList[$key]['keywords'] = !empty($goods['keywords']) ? implode('|', $goods['keywords']) : '';
            }
        } else {
            $result['status'] = false;
            $result['msg'] = '无可导出商品';
        }

        $result['data'] = $finalGoodsList;

        return $result;
    }
}