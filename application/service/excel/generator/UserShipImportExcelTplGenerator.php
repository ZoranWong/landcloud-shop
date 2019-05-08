<?php

namespace app\service\excel\generator;

use app\common\model\UserShip as UserShipModel;
use app\service\excel\BaseGenerator;

class UserShipImportExcelTplGenerator extends BaseGenerator
{

    public function model(): string
    {
        return UserShipModel::class;
    }

    public function fileName(): string
    {
        return "用户地址导入指导表";
    }
}