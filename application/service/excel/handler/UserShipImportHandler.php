<?php

namespace app\service\excel\handler;

use app\common\model\User as UserModel;
use app\common\model\UserShip as UserShipModel;
use app\service\excel\BaseHandler;
use think\Exception;
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
            $userShip['erp_user_id'] = trim($record['erp_user_id']);

            if (empty($record['erp_user_id'])) {
                continue;
            }

            $user = UserModel::where(['erp_user_id' => $record['erp_user_id']])->find();
            $userShip['user_id'] = $user->id;
            $userShip['area_id'] = (int)$record['area_id'];
            $userShip['address'] = trim($record['address']);
            $userShip['postal_code'] = trim($record['postal_code']);
            $userShip['name'] = trim($record['name']);
            $userShip['mobile'] = trim($record['mobile']);
            $userShip['utime'] = time();

            $userShipModel->startTrans();

            $userShipData = $userShipModel->where(['user_id' => $userShip['user_id']])->find();
            if ($userShipData && isset($userShipData['id']) && $userShipData['id'] !== '') {
                $res = $userShipData->isUpdate(true)->save($userShip);
                if ($res === false) {
                    Log::warning("用户地址导入失败：用户ERP_ID 【{$userShip['erp_user_id']}】");
                }
                $userShipId = $userShipData['id'];
            } else {
                try {
                    $userShipId = $userShipModel->doAdd($userShip);
                } catch (Exception $exception) {
                    var_dump($userShip);
                    var_dump($userShipModel->getLastSql());
                    var_dump($userShipModel->getError());
                    var_dump($exception);
                    exit;
                }
            }

            if (!$userShipId) {
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