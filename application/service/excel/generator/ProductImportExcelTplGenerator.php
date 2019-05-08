<?php

namespace app\service\excel\generator;

use app\common\model\Goods;
use app\service\excel\BaseGenerator;

class ProductImportExcelTplGenerator extends BaseGenerator
{

    public function model(): string
    {
        return Goods::class;
    }

    public function fileName(): string
    {
        return '商品导入指导表';
    }
}