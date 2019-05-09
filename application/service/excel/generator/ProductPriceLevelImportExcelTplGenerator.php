<?php

namespace app\service\excel\generator;

use app\common\model\GoodsPriceLevels as GoodsPriceLevelsModel;
use app\service\excel\BaseGenerator;

class ProductPriceLevelImportExcelTplGenerator extends BaseGenerator
{

    public function model(): string
    {
        return GoodsPriceLevelsModel::class;
    }

    public function fileName(): string
    {
        return "产品价格梯度导入指导表";
    }

    public function getExportData($params): array
    {
        // TODO: Implement getExportData() method.
    }
}