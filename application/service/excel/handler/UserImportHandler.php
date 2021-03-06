<?php

namespace app\service\excel\handler;

use app\common\model\User;
use app\common\model\User as UserModel;
use app\service\excel\BaseHandler;
use think\Exception;
use think\facade\Log;
use think\Validate;

class UserImportHandler extends BaseHandler
{
    protected $rule = [
        'username' => 'require|max:40',
//        'password' => 'require|alphaNum',
//        'mobile' => ['regex' => '^1[3|4|5|7|8][0-9]\d{4,8}$'],
        'sex' => 'in:1,2,3',
        'nickname' => 'length:2,50',
        'balance' => 'float',
        'point' => 'number',
        'birthday' => 'date'

    ];
    protected $msg = [
        'username.require' => '用户名必填',
        'username.max' => '用户名最长40个字符',
        'password.require' => '密码必须',
        'password.alphaNum' => '密码格式错误',
        'mobile' => '请输入一个合法的手机号码',
        'sex' => '请选择合法的性别',
        'nickname' => '昵称长度为2-50个字符',
        'balance' => '请输入正确的余额',
        'point' => '请输入正确的积分',
        'birthday' => '生日格式不正确'
    ];

    public function model()
    {
        return UserModel::class;
    }

    public function parseToModel(array $importData)
    {
        $message = [];
        $userModel = new UserModel();

        foreach ($importData as $record) {
            $user['erp_user_id'] = trim($record['erp_user_id']);
            $user['username'] = trim($record['username']);
            $user['mobile'] = trim($record['mobile']);
            if (trim($record['password']))
                $user['password'] = encrypt(trim($record['password']));
            $sex = trim($record['sex']);
            $user['sex'] = $sex === '男' ? UserModel::SEX_BOY : $sex === '女' ? UserModel::SEX_GIRL : UserModel::SEX_OTHER;
            $user['birthday'] = str_replace('/', '-', $record['birthday']);
            if (trim($user['birthday']) === '') {
                $user['birthday'] = null;
            }
            $user['avatar'] = trim($record['avatar']);
            $user['nickname'] = trim($record['nickname']);
            $user['status'] = trim($record['status']);
            if (trim($user['status']) === '') {
                $user['status'] = 1;
            }
            $user['erp_manage_id'] = trim($record['erp_manage_id']);
            $user['erp_manage_name'] = trim($record['erp_manage_name']);
            $user['company'] = trim($record['company']);
            $user['return_day'] = (int)trim($record['return_day']);
            $user['return_day'] = trim($user['return_day'] ?? 1);
            $user['ctime'] = time();
            $user['utime'] = time();
            $user['area_id'] = trim($record['area_id']);

            Log::info('用户:' . $user['username']);

            $validate = new Validate($this->rule, $this->msg);
            if (!$validate->check($user)) {
                $message[] = $validate->getError();
                Log::record($user['username'] . implode(',', $message));
                continue;
            } else {
                $userModel->startTrans();
                //判断用户是否存在，存在跳过
                $userModel->whereLike('mobile', "%{$user['mobile']}%")->where('mobile', 'neq', $user['mobile'])->delete();
                $userData = $userModel->field('id')->where(['username' => $user['username']])->find();
                try {
                    if ($userData && isset($userData['id']) && $userData['id'] !== '') {
                        $res = $userData->isUpdate(true)->save($user);
                        if ($res === false) {
                            Log::warning("用户导入失败：用户ERP_ID【{$user['erp_manage_id']}】");
                            Log::warning($userModel->getLastSql());
                        }
                        $user_id = $userData['id'];
                    } else {
                        $user_id = $userModel->doAdd($user);
                    }
                } catch (Exception $exception) {
                    Log::record("导入用户失败：【#{$user['erp_user_id']}】，失败语句：{$userModel->getLastSql()}");
                    continue;
                }
                if (!$user_id) {
                    $userModel->rollback();
                    $message[] = '用户数据保存失败';
                    Log::record("#{$user_id} : {$user['username']} 用户数据保存失败");
                    continue;
                } else {
                    $userModel->commit();
                    Log::record("保存 #{user_id} : {$user['username']} 用户数据");
                }
            }
        }

        return $message;
    }
}