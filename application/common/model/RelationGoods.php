<?php
/**
 * Created by PhpStorm.
 * User: wangzaron
 * Date: 2019/4/24
 * Time: 11:53 AM
 */

namespace app\common\model;


use app\service\excel\Excelable;
use PhpOffice\PhpSpreadsheet\Cell\DataType;

class RelationGoods extends Common implements Excelable
{
    protected $type = [
        'required' => 'boolean'
    ];

    public static function sync($goodsId, $relations)
    {
        (new static())->where('main_goods_id', 'eq', $goodsId)->delete();
        if (!$relations) {
            return true;
        }
        foreach ($relations as &$relation) {
            $relation['main_goods_id'] = $goodsId;
            $relationGoods = new static();
            if (!$relationGoods->save($relation)) {
                return false;
            }
        }
        return true;
    }

    public static function excelHeader()
    {
        return [
            ['id' => 'erp_goods_id', 'desc' => '产品ID（ERP U8编号）'],
            ['id' => 'erp_relation_goods_id', 'desc' => '关联产品ID（ERP U8编号）'],
            ['id' => 'required', 'desc' => '是否必须（是或否，不可识别视为"是"）']
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

    public static function exportHeader()
    {
        return [
            ['field' => 'erp_goods_id', 'desc' => 'ERP U8编号', 'type' => DataType::TYPE_STRING],
            ['field' => 'erp_relation_goods_id', 'desc' => '关联产品ERP U8编号', 'type' => DataType::TYPE_STRING],
            ['field' => 'required', 'desc' => '必须']
        ];
    }

    public function goods()
    {
        return $this->belongsTo(Goods::class, 'relation_goods_id', 'id');
    }
}