<?php


namespace app\service\excel\handler;

use app\common\model\Goods;
use app\common\model\Goods as GoodsModel;
use app\common\model\GoodsImages as GoodsImagesModel;
use app\common\model\Images as ImagesModel;
use app\common\validate\Goods as GoodsValidator;
use app\service\excel\BaseHandler;
use app\service\Upload;
use think\Exception;
use think\facade\Log;

class ProductImportHandler extends BaseHandler
{

    public function model()
    {
        return Goods::class;
    }

    /**
     * @param array $importData
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function parseToModel(array $importData)
    {
        $message = [];
        $goodsModel = new GoodsModel();

        $imagesModel = new ImagesModel();
        $goodsImagesModel = new GoodsImagesModel();

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
            $paths = [];
            if (!empty($record['image_url_prefix'])) {
                $paths = Upload::prefixFiles($record['image_url_prefix']);
            }
            if (!empty($record['intro'])) {
                $intro = Upload::getPrefixFiles($record['intro']);
                if ($intro && count($intro) > 0) {
                    $record['intro'] = "<img src='{$intro[0]}'>";
                }
                $goods['intro'] = "<div class='gooods-intro'>{$record['intro']}</div>";
            }

            if (!empty($record['brand_name'])) {
                $brand_id = model('common/Brand')->getInfoByName($record['brand_name'], true);
                $goods['brand_id'] = $brand_id;
            }
            $goods['weight'] = (double)$record['weight'];
            $goods['length'] = (double)$record['length'];
            $goods['width'] = (double)$record['width'];
            $goods['height'] = (double)$record['height'];
            $goods['unit'] = $record['unit'];
            $goods['mktprice'] = (double)$record['mktprice'];
            $goods['price'] = (double)$record['price'];
            $goods['preferential_price'] = (double)$record['preferential_price'];
            $goods['spes_desc'] = (string)$record['spes_desc'];
            $goods['promotion_price'] = (double)$record['promotion_price'];
            if (!empty($record['keywords'])) {
                $goods['keywords'] = explode('|', $record['keywords']);
            }
            $goods['remark'] = $record['remark'];
            $goods['marketable'] = 1;
            $goods['isdel'] = null;
            $goods['ctime'] = time();
            $goods['utime'] = time();

            $validator = new GoodsValidator();
            if (!$validator->scene('import')->check($goods)) {
                $message[] = $validator->getError();
                Log::warning("产品导入校验失败：{$goods['name']} --- $message");
                continue;
            } else {
                $goodsModel->startTrans();
                $goodsData = $goodsModel->field('id')->where(['erp_goods_id' => $goods['erp_goods_id']])->find();
                if ($goodsData && isset($goodsData['id']) && $goodsData['id'] !== '') {
                    $res = $goodsData->isUpdate(true)->save($goods);
                    if ($res === false) {
                        Log::warning("产品导入失败：产品ERP编码-{$goods['bn']} 产品名称-{$goods['name']}");
                    }
                    $goods_id = $goodsData['id'];
                } else {
                    $goods_id = $goodsModel->doAdd($goods);
                }

                if (!$goods_id) {
                    $goodsModel->rollback();
                    $message[] = '产品导入失败';
                    Log::warning("产品导入失败：产品ERP编码-{$goods['bn']} 产品名称-{$goods['name']}");
                    continue;
                } else {
                    if (count($paths)) {
                        $imagesData = [];
                        foreach ($paths as $imagePath) {
                            $image_id = md5($imagePath . time());
                            $imagesData[] = [
                                'id' => $image_id,
                                'name' => $image_id,
                                'url' => $imagePath,
                                'type' => 'Aliyun',
                                'ctime' => time()];
                        }
                        try {
                            $imagesData = $imagesModel->saveAll($imagesData, false)->column('id');
                        } catch (Exception $exception) {
                            Log::warning("产品导入失败：产品ERP编码-{$goods['bn']} {$imagesModel->getLastSql()}");
                            $goodsModel->rollback();
                            continue;
                        }

                        /** @var Goods $goodsData */
                        $goodsData = $goodsModel->find($goods_id);
                        $goodsData->isUpdate(true)->save(['image_id' => $imagesData[0]]);
                        $goodsData->goodsImages()->sync($imagesData);
                    }

                    $goodsModel->commit();
                    Log::info("产品导入成功：产品ERP编码-{$goods['bn']} 产品名称-{$goods['name']}");
                }
            }
        }

        return $message;
    }
}