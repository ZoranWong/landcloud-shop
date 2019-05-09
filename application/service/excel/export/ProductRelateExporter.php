<?php
/**
 * ProductRelateExporter.php
 * User: katherine
 * Date: 19-5-9 下午3:05
 */

namespace app\service\excel\export;

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

    }
}