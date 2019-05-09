<?php

namespace app\service\excel\generator;

use app\common\model\RelationGoods as RelationGoodsModel;
use app\service\excel\BaseGenerator;

class ProductRelationImportExcelTplGenerator extends BaseGenerator
{

    public function model(): string
    {
        return RelationGoodsModel::class;
    }

    public function fileName(): string
    {
        return "产品关联信息导入指导表";
    }

    public function getExportData($params): array
    {
        // TODO: Implement getExportData() method.
    }
}