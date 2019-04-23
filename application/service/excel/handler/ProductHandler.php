<?php


namespace app\service\excel\handler;

use app\common\model\Goods;
use app\common\model\Goods as GoodsModel;
use app\service\excel\BaseHandler;

class ProductHandler extends BaseHandler
{

    public function model()
    {
        return Goods::class;
    }

    public function parseToModel(array $importData)
    {
        $message = [];
        $goodsModel = new GoodsModel();

        foreach ($importData as $record) {
            $goodsModel['erp_goods_id'] = $record['erp_goods_id'];
            $goodsModel['name'] = $record['name'];
            $goodsModel['en_name'] = $record['en_name'];
            if (!empty($record['cat_name'])) {
                $cat_id = model('common/GoodsCat')->getInfoByName($record['cat_name'], true);
                $goods['goods_cat_id'] = $cat_id;
            }
            $goodsModel['bn'] = $record['bn'];
            $goodsModel['brief'] = $record['brief'];
            if (!empty($record['brand_name'])) {
                $brand_id = model('common/Brand')->getInfoByName($record['brand_name'], true);
                $goods['brand_id'] = $brand_id;
            }
            $goodsModel['weight'] = $record['weight'];
            $goodsModel['length'] = $record['length'];
            $goodsModel['width'] = $record['width'];
            $goodsModel['height'] = $record['height'];
            $goodsModel['unit'] = $record['unit'];
            $goodsModel['mktprice'] = $record['market_price'];
            $goodsModel['price'] = $record['sale_price'];
            $goodsModel['preferential_price'] = $record['preferential_price'];
            $goodsModel['promotion_price'] = $record['promotion_price'];
            if (!empty($record['keywords'])) {
                $goodsModel['keywords'] = explode('|', $record['keywords']);
            }
            $goodsModel['remark'] = $record['remark'];
            $goodsModel['ctime'] = time();
            $goodsModel['utime'] = time();
        }

        return $message;
    }
}