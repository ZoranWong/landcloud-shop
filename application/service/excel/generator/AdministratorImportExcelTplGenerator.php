<?php

namespace app\service\excel\generator;

use app\common\model\Manage as AdministratorModel;
use app\service\excel\BaseGenerator;

class AdministratorImportExcelTplGenerator extends BaseGenerator
{

    public function model(): string
    {
        return AdministratorModel::class;
    }

    public function fileName(): string
    {
        return "管理员导入指导表";
    }

    public function getExportData($params): array
    {
        // TODO: Implement getExportData() method.
    }
}