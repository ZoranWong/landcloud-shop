<?php

namespace app\service\excel\generator;

use app\common\model\User as UserModel;
use app\service\excel\BaseGenerator;

class UserImportExcelTplGenerator extends BaseGenerator
{
    public function model(): string
    {
        return UserModel::class;
    }

    public function fileName(): string
    {
        return '用户导入指导表';
    }
}