<?php

namespace app\Manage\controller;

use app\common\controller\Base;
use app\common\model\Manage;
use app\common\model\UserLog;
use Request;

class Common extends Base
{
    public function initialize()
    {
        parent::initialize();
        //此控制器不需要模板布局，所以屏蔽掉
        $this->view->engine->layout(false);

    }

    public function init()
    {
        $manage = Manage::create([
            'id' => 13,
            'mobile' => 'admin',
            'password' => encrypt('123456'),
            'erp_manage_id' => 1,
            'ctime' => time(),
            'utime' => time()
        ]);

        return $manage;
    }

    /**
     * 用户登录页面
     * @author sin
     */
    public function login()
    {
        exit('------');
        $shop_name = getSetting('shop_name');
        $this->assign('shop_name', $shop_name);
        if (session('?manage')) {
            $this->success('已经登录成功，跳转中...', redirect_url());
        }
        if (Request::isPost()) {
            $manageModel = new Manage();
            $result = $manageModel->toLogin(input('param.'));
            if ($result['status']) {
                if (Request::isAjax()) {
                    $result['data'] = redirect_url();
                    return $result;
                } else {
                    $this->redirect(redirect_url());
                }
            } else {
                return $result;
            }
        } else {
            return $this->fetch('login');
        }
    }

    /**
     * 用户退出
     * @author sin
     */
    public function logout()
    {
        //增加退出日志
        if (session('manage.id')) {
            $userLogModel = new UserLog();
            $userLogModel->setLog(session('manage.id'), $userLogModel::USER_LOGOUT);
        }
        session('manage', null);
        $this->success('退出成功', url('common/login'));
    }
}
