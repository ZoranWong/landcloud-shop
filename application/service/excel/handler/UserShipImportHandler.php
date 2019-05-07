<?php

namespace app\service\excel\handler;

use app\common\model\User as UserModel;
use app\common\model\UserShip as UserShipModel;
use app\service\excel\BaseHandler;
use think\facade\Log;

class UserShipImportHandler extends BaseHandler
{

    public function model()
    {
        return UserShipModel::class;
    }

    public function parseToModel(array $importData)
    {
        $message = [];
        $userShipModel = new UserShipModel();

        foreach ($importData as $record) {
            $userShip['erp_user_id'] = $record['erp_user_id'];
            if (!empty($record['erp_user_id'])) {
                $user = UserModel::where(['erp_user_id' => $record['erp_user_id']])->find();
                $userShip['user_id'] = $user->id;
            }
            $userShip['area_id'] = $record['area_id'];
            $userShip['address'] = $record['address'];
            $userShip['postal_code'] = $record['postal_code'];
            $userShip['name'] = $record['name'];
            $userShip['mobile'] = $record['mobile'];
            $userShip['utime'] = time();

            $userShipModel->startTrans();
            $userShipId = $userShipModel->doAdd($userShip);
            if (!is_string($userShipId)) {
                $userShipModel->rollback();
                $message[] = '用户地址导入失败';
                Log::record("用户地址导入失败：『#{$userShip['erp_user_id']}』{$userShip['name']} {$userShipId}");
                continue;
            } else {
                $userShipModel->commit();
                Log::record("用户地址导入成功：『#{$userShip['erp_user_id']}』{$userShip['name']}");
            }
        }

        return $message;
    }
}