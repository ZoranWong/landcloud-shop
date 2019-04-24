<?php

namespace app\service\excel\handler;

use app\common\model\Goods as GoodsModel;
use app\common\model\RelationGoods as RelationGoodsModel;
use app\service\excel\BaseHandler;
use think\facade\Log;

class ProductRelationImportHandler extends BaseHandler
{

    public function model()
    {
        return RelationGoodsModel::class;
    }

    public function parseToModel(array $importData)
    {
        $message = [];
        $relationGoodsModel = new RelationGoodsModel();
        $goodsModel = new GoodsModel();

        $erpGoodsIds = [];
        $erpRelationGoodsIds = [];
        foreach ($importData as $record) {
            $erpGoodsIds[] = $record['erp_goods_id'];
            $erpRelationGoodsIds[] = $record['erp_relation_goods_id'];
        }

        // 删除所有导入表商品已存在的商品关联数据
        $goodsIds = $goodsModel->field('id')->whereIn('erp_goods_id', $erpGoodsIds)->column('id');
        $goodsRelationIds = $goodsModel->field('id')->whereIn('erp_goods_id', $erpRelationGoodsIds)->column('id');
        $goodsErpIds = $goodsModel->field('erp_goods_id')->whereIn('erp_goods_id', $erpGoodsIds)->column('erp_goods_id');
        $goodsRelationErpIds = $goodsModel->field('erp_goods_id')->whereIn('erp_goods_id', $erpRelationGoodsIds)->column('erp_goods_id');
        // 主产品
        $goodsInfo = array_combine($goodsErpIds, $goodsIds);
        // 副产品
        $goodsRelationInfo = array_combine($goodsRelationErpIds, $goodsRelationIds);

        $relationGoodsModel->whereIn('main_goods_id', $goodsIds)->delete();

        $count = 0;
        $dataWaitInsert = [];

        foreach ($importData as $record) {
            if (!isset($goodsInfo[$record['erp_goods_id']])) {
                Log::record("产品关联导入失败：主产品『#{$record['erp_goods_id']}』不存在");
                continue;
            }
            $relationGoods['main_goods_id'] = $goodsInfo[$record['erp_goods_id']];

            if (!isset($goodsRelationInfo[$record['erp_relation_goods_id']])) {
                Log::record("产品关联导入失败：被关联产品『#{$record['erp_relation_goods_id']}』不存在");
                continue;
            }
            $relationGoods['relation_goods_id'] = $goodsRelationInfo[$record['erp_relation_goods_id']];

            switch ($record['required']) {
                case YES:
                default:
                    $relationGoods['required'] = true;
                    break;
                case NO:
                    $relationGoods['required'] = false;
                    break;
            }

            $dataWaitInsert[] = $relationGoods;

            if (++$count >= IMPORT_MAX_ROWS) {
                $relationGoodsModel->insertAll($dataWaitInsert);
                $dataWaitInsert = [];
            }
        }

        $relationGoodsModel->insertAll($dataWaitInsert);

        return $message;
    }
}