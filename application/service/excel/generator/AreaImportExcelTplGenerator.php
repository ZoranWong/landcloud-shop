<?php

namespace app\service\excel\generator;

use app\common\model\Area as AreaModel;
use app\service\excel\BaseGenerator;

class AreaImportExcelTplGenerator extends BaseGenerator
{

    public function model(): string
    {
        return AreaModel::class;
    }

    public function fileName(): string
    {
        return '地区填写指导表';
    }
}