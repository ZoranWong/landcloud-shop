<?php
/**
 * ProductPriceLevelExporter.php
 * User: katherine
 * Date: 19-5-9 下午2:11
 */

namespace app\service\excel\export;

use app\common\model\Area as AreaModel;
use app\common\model\Goods as GoodsModel;
use app\common\model\GoodsPriceLevels as GoodsPriceLevelsModel;
use app\service\excel\BaseGenerator;

class ProductPriceLevelExporter extends BaseGenerator
{

    public function model(): string
    {
        return GoodsPriceLevelsModel::class;
    }

    public function fileName(): string
    {
        return "梯度产品导出";
    }

    public function getExportData($params): array
    {
        $goodsModel = new GoodsModel();
        $areaModel = new AreaModel();

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
        if (count($goodsData)) {
            $i = 0;
            foreach ($goodsData as $key => $goods) {
                foreach ($goods['price_levels'] as $priceLevel) {
                    $i++;
                    $finalList[$i]['erp_goods_id'] = $goods['erp_goods_id'];
                    $finalList[$i]['area_id'] = $priceLevel['area'];
                    $areaList = $areaModel->getArea($priceLevel['area']);
                    $address = '';
                    foreach ($areaList as $area) {
                        $address .= $area['info']['name'] . '';
                    }
                    $finalList[$i]['area'] = $address;
                    $finalList[$i]['level'] = $priceLevel['level'];
                    $finalList[$i]['price'] = $priceLevel['price'];
                    $finalList[$i]['name'] = $priceLevel['name'];
                    $finalList[$i]['buy_num'] = $priceLevel['buy_num'];
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