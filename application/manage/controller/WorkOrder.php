<?php

namespace app\manage\controller;

use app\common\controller\Manage;
use app\common\model\WorkOrder as WorkOrderModel;
use think\facade\Request;

class WorkOrder extends Manage
{
    public function index()
    {
        $workOrderModel = new WorkOrderModel();

        $limit = input('limit', 15);

        $paginator = $workOrderModel->paginate($limit);

        if (Request::isAjax()) {
            return ['code' => 0, 'msg' => '', 'count' => $paginator->count(), 'data' => $paginator->items()];
        }

        return $this->fetch();
    }

}