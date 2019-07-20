<?php
/**
 * Created by PhpStorm.
 * User: wang
 * Date: 2019-07-19
 * Time: 23:01
 */

namespace app\service\excel\export;


use app\common\model\Order;
use app\common\model\User;
use app\service\excel\BaseGenerator;
use think\facade\Log;

class OrdersExporter extends BaseGenerator
{

    public function model(): string
    {
        // TODO: Implement model() method.
        return Order::class;
    }

    public function fileName(): string
    {
        // TODO: Implement fileName() method.
        return '订单表';
    }

    public function getExportData($params): array
    {
        // TODO: Implement getExportData() method.
        $filter = json_decode($params, true);

        if (isset($filter['order_ids'])) {
            if ($filter['order_ids']) {
                $filter[] = ['order_id', 'in', explode(',', $filter['order_ids'])];
            }
            unset($filter['order_ids']);
        }

        $userIdList = [];
        if(isset($filter['erp_id'])) {
            $users = User::where('erp_manage_id', 'eq', $filter['erp_id'])->select();
            $userIds = $users->map(function ($user) {
                return $user['id'];
            });
            if($userIds->count())
                $userIdList = array_merge($userIdList, $userIds->toArray());
            unset($filter['erp_id']);
        }

        if (isset($filter['date'])) {
            $dateString = $filter['date'];
            $dateArray = explode(' 到 ', $dateString);
            $sDate = strtotime($dateArray[0] . ' 00:00:00');
            $eDate = strtotime($dateArray[1] . ' 23:59:59');
            $filter[] = ['ctime', ['>=', $sDate], ['<=', $eDate], 'and'];
            unset($filter['date']);
        }

//        if(isset($filter['username'])) {
//            $where[] = array('username|mobile|nickname', 'eq', $filter['username']);
//            $users = User::where($where)->select();
//            $userIds = $users->map(function ($user) {
//                return $user['id'];
//            });
//            if($userIds->count())
//                $userIdList = array_merge($userIdList, $userIds->toArray());
//            unset($filter['username']);
//        }

//        if(isset($filter['ship_mobile'])) {
//            $filter[] = ['ship_mobile', 'eq', $filter['ship_mobile']];
//            unset($filter['ship_mobile']);
//        }
//
//        if(isset($filter['source'])) {
//            $filter[] = ['source', 'eq', $filter['source']];
//            unset($filter['source']);
//        }

        if(count($userIdList) > 0)
            $filter[] = ['user_id', 'in', $userIdList];
        foreach ($filter as $key => $item) {
            if($item === null || $item === ''){
                unset($filter[$key]);
            }
        }

        $result = [
            'status' => true,
            'data' => [],
            'msg' => ''
        ];
        Log::info('------ order filter -----'.json_encode($filter));
        $order = new Order();
        $ordersResult = $order->tableData($filter, true);
        $result['data'] = $ordersResult['data']->toArray();
        return $result;
    }
}