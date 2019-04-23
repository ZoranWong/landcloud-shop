<?php
/**
 * Created by PhpStorm.
 * User: wangzaron
 * Date: 2019/4/23
 * Time: 3:53 PM
 */

namespace app\common\model;


use think\Db;

class GoodsPriceLevels extends Common
{
    public static function sync($goodsId, $levels) {
        (new static())->where('goods_id', 'eq', $goodsId)->delete();
        foreach ($levels as &$level) {
            $level['goods_id'] = $goodsId;
            $goodsPriceLevel = new static();
            if(!$goodsPriceLevel->save($level)) {
                return false;
            }
        }
        return true;
    }
}