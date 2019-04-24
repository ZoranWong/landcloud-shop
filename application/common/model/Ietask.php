<?php
// +----------------------------------------------------------------------
// | JSHOP [ 小程序商城 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2018 http://jihainet.com All rights reserved.
// +----------------------------------------------------------------------
// | Author: mark <jima@jihainet.com>
// +----------------------------------------------------------------------
namespace app\common\model;

/**
 * 导入导出任务类
 * Class Cart
 * @package app\common\model
 * @author keinx
 */
class Ietask extends Common
{
    protected $autoWriteTimestamp = true;
    protected $createTime = 'ctime';
    protected $updateTime = 'utime';

    CONST TYPE_EXPORT = 1;//导出
    CONST TYPE_INPORT = 2;//导入

    CONST WAIT_STATUS = 0;//等待执行
    CONST EXPORT_PROCESS_STATUS = 1;//正在导出
    CONST EXPORT_SUCCESS_STATUS = 2;//导出成功
    CONST EXPORT_FAIL_STATUS = 3;//导出失败
    CONST IMPORT_PROCESS_STATUS = 4;//正在导入
    CONST IMPORT_SUCCESS_STATUS = 5;//导入成功
    CONST IMPORT_FAIL_STATUS = 6;//导入失败
    CONST IMPORT_BREAK_STATUS = 7;//导入中断
    CONST IMPORT_PART_STATUS = 8;//部分导入

    /**
     * 加入导出任务
     * @param $data
     * @param string $model
     * @return bool
     */
    public function addExportTask($data, $job = 'Goods')
    {

        if ($this->save($data)) {
            $exportData['task_id'] = $this->id;
            $exportData['params'] = $data['params'];
            $jobClass = 'app\job\export\\' . $job . '@exec';
            $queueRes = \think\Queue::push($jobClass, $exportData);//加入导出队列
            return $queueRes;
        } else {
            return false;
        }

    }

    /**
     * 加入导入任务
     * @param $data
     * @param string $job
     * @return bool
     */
    public function addImportTask($data, $job = 'product')
    {
        if ($this->save($data)) {
            $importData['task_id'] = $this->id;
            $importData['params'] = $data['params'];
            $job = ucwords($job);
            $jobClass = "app\\service\\excel\\handler\\{$job}Handler@handle";
            $queueRes = \think\Queue::push($jobClass, $importData);//加入导出队列

            return $queueRes;
        } else {
            return false;
        }
    }

    public function tableWhere($post)
    {
        $where = $whereOr = [];
        if (isset($post['name']) && $post['name'] != "") {
            $where[] = ['name', 'like', "%" . $post['name'] . "%"];
        }
        if (isset($post['status']) && $post['status'] !== "") {
            $where[] = ['status', 'eq', $post['status']];
        }
        $result['where'] = $where;
        $result['whereOr'] = $whereOr;

        $result['field'] = "*";
        $result['order'] = ['id' => 'desc'];
        return $result;
    }

    public function tableFormat($list)
    {
        if ($list) {
            foreach ($list as $key => $val) {
                $list[$key]['utime'] = getTime($val['utime']);
                $list[$key]['ctime'] = getTime($val['ctime']);
                if ($list[$key]['type'] == self::TYPE_EXPORT) {
                    $list[$key]['type'] = "导出";
                } else {
                    $list[$key]['type'] = "导入";
                }
                switch ($list[$key]['status']) {
                    case self::WAIT_STATUS:
                        $list[$key]['status'] = "等待执行";
                        break;
                    case self::EXPORT_PROCESS_STATUS:
                        $list[$key]['status'] = "正在导出";
                        break;
                    case self::EXPORT_SUCCESS_STATUS:
                        $list[$key]['status'] = "导出成功";
                        break;
                    case self::EXPORT_FAIL_STATUS:
                        $list[$key]['status'] = "导出失败";
                        break;
                    case self::IMPORT_PROCESS_STATUS:
                        $list[$key]['status'] = "正在导入";
                        break;
                    case self::IMPORT_SUCCESS_STATUS:
                        $list[$key]['status'] = "导入成功";
                        break;
                    case self::IMPORT_FAIL_STATUS:
                        $list[$key]['status'] = "导入失败";
                        break;
                    case self::IMPORT_BREAK_STATUS:
                        $list[$key]['status'] = "导入中断";
                        break;
                    case self::IMPORT_PART_STATUS:
                        $list[$key]['status'] = "部分导入";
                        break;
                    default :
                        $list[$key]['status'] = "等待执行";
                        break;
                }
            }
        }
        return parent::tableFormat($list); // TODO: Change the autogenerated stub
    }

}