<?php
/**
 * UserExporter.php
 * User: katherine
 * Date: 19-5-9 下午3:49
 */

namespace app\service\excel\export;

use app\common\model\User as UserModel;
use app\service\excel\BaseGenerator;

class UserExporter extends BaseGenerator
{

    public function model(): string
    {
        return UserModel::class;
    }

    public function fileName(): string
    {
        return "";
    }

    public function getExportData($params): array
    {
        $userModel = new UserModel();

        $filter = json_encode($params, true);

        if (isset($filter['ids'])) {
            if ($filter['ids']) {
                $filter['id'] = explode(',', $filter['ids']);
            }
            unset($filter['ids']);
        }

        $result = [
            'status' => true,
            'data' => [],
            'msg' => ''
        ];

        $userData = $userModel->tableData($filter, false);
        $finalList = [];
        if ($userData['count'] > 0) {
            $userList = $userData['data'];
            foreach ($userList as $key => $user) {
                $finalList[$key]['erp_user_id'] = $user['erp_user_id'];
                $finalList[$key]['username'] = $user['username'];
                $finalList[$key]['mobile'] = $user['mobile'];
                $finalList[$key]['birthday'] = $user['birthday'];
                $finalList[$key]['avatar'] = $user['avatar'];
                $finalList[$key]['nickname'] = $user['nickname'];
                $finalList[$key]['erp_manage_id'] = $user['erp_manage_id'];
                $finalList[$key]['erp_manage_name'] = $user['erp_manage_name'];
                $finalList[$key]['company'] = $user['company'];
                $finalList[$key]['password'] = '';
                $finalList[$key]['sex'] = UserModel::SEX[$user['sex']];
                $finalList[$key]['status'] = UserModel::STATUS[$user['status']];
                $finalList[$key]['area_id'] = $user['area_id'];
                $finalList[$key]['area_name'] = $user['register_area']['name'];
            }
        } else {
            $result['status'] = false;
            $result['msg'] = '无可导出商品';
        }

        $result['data'] = $finalList;

        return $result;
    }
}