<?php

namespace app\common\model;

use think\Db;
use think\model\Collection;

/**
 * @property-read Collection|ManageRole[] $children
 * @property-read Collection|ManageRole[] $childrenTree
 * @property-read Collection|Manage[] $managers
 * */
class ManageRole extends Common
{

    protected $autoWriteTimestamp = true;
    protected $updateTime = 'utime';


    protected function tableWhere($post)
    {
        $where = [];


        if (isset($post['name']) && $post['name'] != "") {
            $where[] = ['name', 'like', '%' . $post['name'] . '%'];
        }
        $result['where'] = $where;
        $result['field'] = "*";
        $result['order'] = 'utime desc';
        return $result;
    }



    /**
     * 根据查询结果，格式化数据
     * @param $list  array格式的collection
     * @return mixed
     * @author sin
     */
    protected function tableFormat($list)
    {
        foreach ($list as $k => $v) {
            if ($v['utime']) {
                $list[$k]['utime'] = getTime($v['utime']);
            }

        }
        return $list;
    }

    public function toDel($id)
    {
        $result = [
            'status' => false,
            'data' => [],
            'msg' => ''
        ];

        $where['id'] = $id;

        $mrorModel = new ManageRoleOperationRel();

        Db::startTrans();
        try {
            $this->where($where)->delete();
            $mrorModel->where(['manage_role_id' => $id])->delete();
            Db::commit();
            $result['status'] = true;
            $result['msg'] = '删除成功';
        } catch (\Exception $e) {
            Db::rollback();
            $result['msg'] = $e->getMessage();
        }
        return $result;
    }

    /**
     * 取角色的操作权限数组
     * @param $id
     * @return array|\think\Config
     */
    public function getRoleOperation($id)
    {
        $result = [
            'status' => true,
            'data' => [],
            'msg' => ''
        ];

        $where['id'] = $id;
        $sellerRoleInfo = $this->where($where)->find();
        if (!$sellerRoleInfo) {
            return error_code(11071);
        }
        $mrorModel = new ManageRoleOperationRel();
        $permList = $mrorModel->where(['manage_role_id' => $id])->select();
        if (!$permList->isEmpty()) {
            $nodeList = array_column($permList->toArray(), 'manage_role_id', 'operation_id');
        } else {
            $nodeList = [];
        }

        $operationModel = new Operation();
        $result['data'] = $operationModel->menuTree($operationModel::MENU_MANAGE, $nodeList);
        return $result;
    }

    public function getInfoByName($name = '', $isForce = false)
    {
        if (!$name) {
            return false;
        }
        $manage_role_id = 0;
        $manageRole = $this->field('id')->where([['name', 'like', '%' . $name . '%']])->find();

        if (!$manageRole && $isForce) {
            $this->insert([
                'name' => $name,
            ]);
            $manage_role_id = $this->getLastInsID();
        } elseif ($manageRole) {
            $manage_role_id = $manageRole['id'];
        }
        return $manage_role_id;
    }

    public function children()
    {
        return $this->hasMany(ManageRole::class, 'parent_id', 'id');
    }

    public function parent()
    {
        return $this->belongsTo(ManageRole::class, 'parent_id', 'id');
    }

    public function childrenTree()
    {
        return $this->hasMany(ManageRole::class, 'parent_id', 'id')
            ->with('children');
    }

    public function parentTree()
    {
        return $this->belongsTo(ManageRole::class, 'parent_id', 'id')
            ->with('parent');
    }

    public function managers()
    {
        return $this->belongsToMany(Manage::class, 'lc_manage_role_rel', 'role_id', 'manage_id');
    }

}