<?php

namespace app\service\excel\handler;

use app\common\model\Manage as ManageModel;
use app\common\model\ManageRole as ManageRoleModel;
use app\common\model\ManageRoleRel;
use app\common\model\ManageRoleRel as ManageRoleRelModel;
use app\service\excel\BaseHandler;
use think\Db;
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
        $manageRoleRelModel = new ManageRoleRelModel();
        $manageRoleModel = new ManageRoleModel();

        foreach ($importData as $record) {
            $manage['erp_manage_id'] = $record['erp_manage_id'];
            $manage['username'] = $record['user_name'];
            $manage['mobile'] = $record['mobile'];
            $manage['password'] = $record['password'];
            $manage['avatar'] = $record['avatar'];
            $manage['nickname'] = $record['nickname'];
            $manage['utime'] = time();
            $manage['utime'] = time();

            Log::record("管理者导入：『#{$manage['erp_manage_id']}』{$manage['username']}");

            try{
                $manageData = $manageModel->field('id')->where('username', 'eq', $manage['username'])->find();
                $manageModel->startTrans();
                if ($manageData && isset($manageData['id']) && $manageData['id'] != '') {
                    $manage['password'] = encrypt($manage['password']);
                    $res = $manageModel->save($manage, ['id' => $manageData['id']]);
                    if ($res === false) {
                        Log::record("管理者导入失败：『#{$manage['erp_manage_id']}』{$manage['username']}");
                    }
                    $manage_id = $manageData['id'];
                } else {
                    $result = $manageModel->toAdd($manage);
                    $manage_id = $result['insertId'];
                }

                if (!$manage_id) {
                    $manageModel->rollback();
                    $message[] = "管理者导入失败";
                    Log::record("管理者导入失败：『#{$manage['erp_manage_id']}』{$manage['username']}");
                    continue;
                } else {
                    $manageRoleRelModel = new ManageRoleRel();
                    //清空所有的旧角色
                    $manageRoleRelModel->where(['manage_id' => $manage_id])->delete();

                    if (!empty($record['role_name'])) {
                        $roleNames = explode('|', $record['role_name']);
                        foreach ($roleNames as $key => $roleName) {
                            $manageRoleId = $manageRoleModel->getInfoByName($roleName, true);
                            $manageRoleRelModel->insert(['manage_id' => $manage_id, 'role_id' => $manageRoleId]);
                        }
                    }

                    $manageModel->commit();
                    Log::record("管理者导入成功：『#{$manage['erp_manage_id']}』{$manage['username']}");
                }
            }catch (\Exception $exception) {
                Log::record("管理者导入失败：『#{$manage['erp_manage_id']}』{$manage['username']}");
                Log::record("管理者导入失败：{$exception->getMessage()} ## {$manageModel->getLastSql()}");
            }
        }

        return $message;
    }
}