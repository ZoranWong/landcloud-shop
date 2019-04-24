<?php


namespace app\service\excel\handler;

use app\common\model\Goods;
use app\common\model\Goods as GoodsModel;
use app\common\validate\Goods as GoodsValidator;
use app\service\excel\BaseHandler;
use think\facade\Log;

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
            $goods['erp_goods_id'] = $record['erp_goods_id'];
            $goods['name'] = $record['name'];
            $goods['en_name'] = $record['en_name'];
            if (!empty($record['cat_name'])) {
                $cat_id = model('common/GoodsCat')->getInfoByName($record['cat_name'], true);
                $goods['goods_cat_id'] = $cat_id;
            }
            $goods['bn'] = $record['bn'];
            $goods['brief'] = $record['brief'];
            if (!empty($record['brand_name'])) {
                $brand_id = model('common/Brand')->getInfoByName($record['brand_name'], true);
                $goods['brand_id'] = $brand_id;
            }
            $goods['weight'] = $record['weight'];
            $goods['length'] = $record['length'];
            $goods['width'] = $record['width'];
            $goods['height'] = $record['height'];
            $goods['unit'] = $record['unit'];
            $goods['mktprice'] = $record['market_price'];
            $goods['price'] = $record['sale_price'];
            $goods['preferential_price'] = $record['preferential_price'];
            $goods['promotion_price'] = $record['promotion_price'];
            if (!empty($record['keywords'])) {
                $goods['keywords'] = explode('|', $record['keywords']);
            }
            $goods['remark'] = $record['remark'];
            $goods['ctime'] = time();
            $goods['utime'] = time();

            Log::info('产品导入：', ['产品名称' => $goods['name'], '产品编码' => $goods['bn']]);

            $validator = new GoodsValidator();
            if (!$validator->scene('import')->check($goods)) {
                $message[] = $validator->getError();
                Log::info('产品导入校验失败：' . $goods['name'] . implode(',', $message));
                continue;
            } else {
                $goodsModel->startTrans();
                $goodsData = $goodsModel->field('id')->where(['bn' => $goods['bn']])->find();
                if ($goodsData && isset($goodsData['id']) && $goodsData['id'] != '') {
                    $res = $goodsModel->updateGoods($goodsData['id'], $goods);
                    if ($res === false) {
                        Log::info('产品导入失败：', ['产品名称' => $goods['name'], '产品编码' => $goods['bn']]);
                    }
                    $goods_id = $goodsData['id'];
                } else {
                    $goods_id = $goodsModel->doAdd($goods);
                }

                if (!$goods_id) {
                    $goodsModel->rollback();
                    $message[] = '产品导入失败';
                    Log::info('产品导入失败：', ['产品名称' => $goods['name'], '产品编码' => $goods['bn']]);
                    continue;
                } else {
                    $goodsModel->commit();
                    Log::info('产品导入成功：', ['产品id：' => $goods_id, '产品名称' => $goods['name'], '产品编码' => $goods['bn']]);
                }
            }
        }

        return $message;
    }
}