<?php
/**
 * Created by PhpStorm.
 * User: wangzaron
 * Date: 2019/4/23
 * Time: 3:53 PM
 */

namespace app\common\model;


use app\service\excel\Excelable;
use PhpOffice\PhpSpreadsheet\Cell\DataType;

class GoodsPriceLevels extends Common implements Excelable
{
    public static function sync($goodsId, $levels)
    {
        (new static())->where('goods_id', 'eq', $goodsId)->delete();
        if (!$levels) {
            return true;
        }
        foreach ($levels as &$level) {
            $level['goods_id'] = $goodsId;
            $goodsPriceLevel = new static();
            if (!$goodsPriceLevel->save($level)) {
                return false;
            }
        }
        return true;
    }


    public static function excelHeader()
    {
        return [
            ['id' => 'erp_goods_id', 'desc' => '产品ID（ERP U8编号）'],
            ['id' => 'area', 'desc' => '区域（省）'],
            ['id' => 'level', 'desc' => '梯度'],
            ['id' => 'name', 'desc' => '产品规格'],
            ['id' => 'number', 'desc' => '产品数量'],
            ['id' => 'price', 'desc' => '产品价格'],
        ];
    }

    public function doAdd($data = [])
    {
        $result = $this->insert($data);
        if ($result) {
            return $this->getLastInsID();
        }
        return $result;
    }

    public function areaInfo()
    {
        return $this->belongsTo(Area::class, 'area', 'id');
    }

    public static function exportHeader()
    {
        return [
            ['field' => 'erp_goods_id', 'desc' => 'ERP U8编号', 'type' => DataType::TYPE_STRING],
            ['field' => 'area_id', 'desc' => '区域代码', 'type' => DataType::TYPE_NUMERIC],
            ['field' => 'area', 'desc' => '区域', 'type' => DataType::TYPE_STRING],
            ['field' => 'level', 'desc' => '梯度', 'type' => DataType::TYPE_NUMERIC],
            ['field' => 'name', 'desc' => '规格', 'type' => DataType::TYPE_STRING],
            ['field' => 'buy_num', 'desc' => '购买数量', 'type' => DataType::TYPE_STRING],
            ['field' => 'price', 'desc' => '价格', 'type' => DataType::TYPE_STRING],
        ];
    }
}