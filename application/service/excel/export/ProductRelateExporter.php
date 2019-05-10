<?php
/**
 * ProductRelateExporter.php
 * User: katherine
 * Date: 19-5-9 下午3:05
 */

namespace app\service\excel\export;

use app\common\model\Goods as GoodsModel;
use app\common\model\RelationGoods as RelationGoodsModel;
use app\service\excel\BaseGenerator;

class ProductRelateExporter extends BaseGenerator
{

    public function model(): string
    {
        return RelationGoodsModel::class;
    }

    public function fileName(): string
    {
        return '';
    }

    public function getExportData($params): array
    {
        $goodsModel = new GoodsModel();

        $filter = json_encode($params, true);

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

        $goodsData = $goodsModel::with('relateGoods,priceLevels.areaInfo')
            ->field('*')->where(function ($query) use ($filter) {
                if (isset($filter['id'])) {
                    $query->whereIn('id', $filter['id']);
                }
            })->select();

        $goodsData = $goodsModel->tableFormat($goodsData);

        $finalList = [];
        $i = 0;
        if (count($goodsData)) {
            foreach ($goodsData as $key => $goods) {
                foreach ($goods['relate_goods'] as $relateGoods) {
                    $i++;
                    $finalList[$i]['erp_goods_id'] = $goods['erp_goods_id'];
                    $finalList[$i]['erp_relation_goods_id'] = $relateGoods['erp_goods_id'];
                    $finalList[$i]['required'] = $relateGoods['pivot']['required'] ? '是' : '否';
                }
            }
        } else {
            $result['status'] = false;
            $result['msg'] = '无可导出商品';
        }

        $result['data'] = $finalList;

        return $result;
    }
}