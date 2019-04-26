<?php

namespace app\api\controller;

use app\common\controller\Api;
use app\common\model\WorkOrder as WorkOrderModel;

class WorkOrder extends Api
{
    public function workOrderList()
    {
        $result = [
            'status' => true,
            'msg' => '获取成功',
            'data' => []
        ];
        $workOrderModel = new WorkOrderModel();
        $page = input('param.page', 1);
        $pageSize = input('param.pageSize', PAGE_SIZE);
        $type = input('param.type', 'all');
        $where = [];
        if (in_array($type, [WorkOrderModel::TYPE_CONSULT, WorkOrderModel::TYPE_COMPLAIN])) {
            $where['type'] = WorkOrderModel::TYPES[$type];
        }
        $paginator = $workOrderModel->where($where)->order('ctime', 'desc')->paginate($pageSize, false, ['page' => $page]);
        $result['data'] = $paginator->items();
        $result['count'] = $paginator->count();
        return $result;
    }

    // 发起工单
    public function initWorkOrder()
    {
        $result = [
            'status' => true,
            'msg' => '提交成功',
            'data' => null
        ];
        $workOrderModel = new WorkOrderModel();
        $data = [
            'user_id' => $this->userId,
            'title' => input('param.title'),
            'type' => WorkOrderModel::TYPES[input('param.type', WorkOrderModel::TYPE_CONSULT)],
            'status' => WorkOrderModel::STATUS_INIT,
            'ctime' => time()
        ];
        $id = $workOrderModel->insertGetId($data);
        /** @var WorkOrderModel $workOrder */
        $workOrder = $workOrderModel->with('comments')->where(['id' => $id])->find();
        $workOrder->comments()->save([
            'work_order_id' => $id,
            'content' => input('param.content', '您好'),
            'is_reply' => 0,
            'ctime' => time()
        ]);
        $result['data'] = $workOrder;
        return $result;
    }

    // 工单详情
    public function workOrderDetail()
    {
        $result = [
            'status' => false,
            'msg' => '获取失败',
            'data' => null
        ];
        $workOrderModel = new WorkOrderModel();
        $data = $workOrderModel->with('comments.manage')->where(['id' => input('param.id/d')])->find();
        if ($data) {
            $result['status'] = true;
            $result['msg'] = '获取成功';
            $result['data'] = $data;
        }
        return $result;
    }
}