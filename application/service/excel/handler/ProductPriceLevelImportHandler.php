<?php

namespace app\service\excel\handler;

use app\common\model\Goods as GoodsModel;
use app\common\model\GoodsPriceLevels as GoodsPriceLevelsModel;
use app\service\excel\BaseHandler;
use think\facade\Log;

class ProductPriceLevelImportHandler extends BaseHandler
{

    public function model()
    {
        return GoodsPriceLevelsModel::class;
    }

    public function parseToModel(array $importData)
    {
        $message = [];
        $priceLevelModel = new GoodsPriceLevelsModel();
        $goodsModel = new GoodsModel();

        $erpGoodsIds = [];
        foreach ($importData as $record) {
            $erpGoodsIds[] = $record['erp_goods_id'];
        }

        // 删除所有导入表商品已存在的价格梯度数据
        $goodsIds = $goodsModel->field('id')->whereIn('erp_goods_id', $erpGoodsIds)->column('id');
        $priceLevelModel->whereIn('goods_id', $goodsIds)->delete();

        foreach ($importData as $record) {
            $goods = GoodsModel::where(['erp_goods_id' => $record['erp_goods_id']])->find();
            if (!$goods) {
                Log::record("价格梯度导入失败：产品ERP编号没有对应商品，ERP编号为：#{$record['erp_goods_id']}");
                continue;
            }
            $priceLevel['goods_id'] = $goods->id;
            $priceLevel['area'] = $record['area'];
            $priceLevel['level'] = $record['level'];
            $priceLevel['name'] = $record['name'];
            $priceLevel['buy_num'] = $record['number'];
            $priceLevel['price'] = $record['price'];

            $priceLevelModel->startTrans();
            $goodsPriceLevelsId = $priceLevelModel->doAdd($priceLevel);
            if (!is_string($goodsPriceLevelsId)) {
                $priceLevelModel->rollback();
                $message[] = '价格梯度导入失败';
                Log::record("价格梯度导入失败：ERP编号-『#{$record['erp_goods_id']}』");
                continue;
            } else {
                $priceLevelModel->commit();
                Log::record("价格梯度导入成功：ERP编号-『#{$record['erp_goods_id']}』");
            }
        }

        return $message;
    }
}