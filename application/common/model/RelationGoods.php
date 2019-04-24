<?php
/**
 * Created by PhpStorm.
 * User: wangzaron
 * Date: 2019/4/24
 * Time: 11:53 AM
 */

namespace app\common\model;


class RelationGoods extends Common
{
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
}