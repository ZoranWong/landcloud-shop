<?php

namespace app\service\excel\handler;

use app\common\model\Manage as ManageModel;
use app\service\excel\BaseHandler;
use think\facade\Log;

class AdministratorImportHandler extends BaseHandler
{

    public function model()
    {
        return ManageModel::class;
    }

    public function parseToModel(array $importData)
    {
        $message = [];
        $manageModel = new ManageModel();

        foreach ($importData as $record) {
            $manage['erp_manage_id'] = $record['erp_manage_id'];
            $manage['username'] = $record['user_name'];
            $manage['mobile'] = $record['mobile'];
            $manage['password'] = encrypt($record['password']);
            $manage['avatar'] = $record['avatar'];
            $manage['nickname'] = $record['nickname'];
            $manage['utime'] = time();
            $manage['utime'] = time();

            Log::record("管理者导入：『#{$manage['erp_manage_id']}』{$manage['username']}");

            $manageModel->startTrans();
            $manageData = $manageModel->field('id')->where(['mobile' => $manage['mobile']])->find();
            if ($manageData && isset($manageData['id']) && $manageData['id'] !== '') {
                Log::record("管理者导入失败：『#{$manage['erp_manage_id']}』{$manage['username']} 已存在，导入失败！");
                $manage_id = $manageData['id'];
            } else {
                $manage_id = $manageModel->toAdd($manage);
            }
            if (!$manage_id) {
                $manageModel->rollback();
                $message[] = "管理者导入失败";
                Log::record("管理者导入失败：『#{$manage['erp_manage_id']}』{$manage['username']}");
                continue;
            } else {
                if (!empty($record['role_name'])) {
                    $manageRoleId = model('common/ManageRole')->getInfoByName($record['role_name'], true);
                    model('common/ManageRoleRel')->insert(['manage_id' => $manage_id, 'role_id' => $manageRoleId]);
                }

                $manageModel->commit();
                Log::record("管理者导入成功：『#{$manage['erp_manage_id']}』{$manage['username']} 导入失败！");
            }
        }

        return $message;
    }
}