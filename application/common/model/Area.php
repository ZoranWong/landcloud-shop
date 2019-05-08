<?php
// +----------------------------------------------------------------------
// | JSHOP [ 小程序商城 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2018 http://jihainet.com All rights reserved.
// +----------------------------------------------------------------------
// | Author: mark <jima@jihainet.com>
// +----------------------------------------------------------------------

namespace app\common\model;

use app\service\excel\Excelable;
use think\facade\Cache;

/**
 * 地区模型
 * Class Area
 * @package app\common\model
 * @author keinx
 */
class Area extends Common implements Excelable
{

    const PROVINCE_DEPTH = 1;
    const CITY_DEPTH = 2;
    const COUNTY_DEPTH = 3;
    const FOUR_DEPTH = 4;
    const PROVINCE_PARENT_ID = 0;           //根节点

    public $areaList; //地区数据

    /**
     * 指定region id的下级信息
     * @param $areaId
     * @return array|\PDOStatement|string|\think\Collection 所有地区数据数组
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getAllChild($areaId)
    {
        return $this->where([
            'parent_id' => intval($areaId),
        ])->select();
    }


    /**
     * 获取地区最后一级ID
     * todo:给微信使用
     * @param $countyName
     * @param $cityName
     * @param $provinceName
     * @param $postalCode
     * @return int|mixed|string
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getThreeAreaId($countyName, $cityName, $provinceName, $postalCode)
    {
        $where1[] = ['name', 'eq', $countyName];
        $where1[] = ['depth', 'eq', self::COUNTY_DEPTH];
        $county = $this->where($where1)
            ->select();
        if (count($county) > 0) {
            if (count($county) > 1) {
                $where2[] = ['name', 'eq', $cityName];
                $where2[] = ['depth', 'eq', self::CITY_DEPTH];
                $city = $this->where($where2)
                    ->find();
                foreach ($county as $k => $v) {
                    if ($v['parent_id'] == $city['id']) {
                        $id = $v['id'];
                    }
                }
            } else {
                $id = $county[0]['id'];
            }
        } else {
            $where2[] = ['name', 'eq', $cityName];
            $where2[] = ['depth', 'eq', self::CITY_DEPTH];
            $city = $this->where($where2)
                ->find();
            if ($city) {
                //创建区域
                $county_data['parent_id'] = $city['id'];
                $county_data['depth'] = self::COUNTY_DEPTH;
                $county_data['name'] = $countyName;
                $county_data['postal_code'] = $postalCode;
                $id = $this->insertGetId($county_data);
            } else {
                $where3[] = ['name', 'eq', $provinceName];
                $where3[] = ['depth', 'eq', self::PROVINCE_DEPTH];
                $province = $this->where($where3)
                    ->find();
                if ($province) {
                    //创建城市
                    $city_data['parent_id'] = $province['id'];
                    $city_data['depth'] = self::CITY_DEPTH;
                    $city_data['name'] = $cityName;
                    $city_id = $this->insertGetId($city_data);

                    //创建区域
                    $county_data['parent_id'] = $city_id;
                    $county_data['depth'] = self::COUNTY_DEPTH;
                    $county_data['name'] = $countyName;
                    $county_data['postal_code'] = $postalCode;
                    $id = $this->insertGetId($county_data);
                } else {
                    //创建省份
                    $province_data['parent_id'] = self::PROVINCE_PARENT_ID;
                    $province_data['depth'] = self::PROVINCE_DEPTH;
                    $province_data['name'] = $provinceName;
                    $province_id = $this->insertGetId($province_data);

                    //创建城市
                    $city_data['parent_id'] = $province_id;
                    $city_data['depth'] = self::CITY_DEPTH;
                    $city_data['name'] = $cityName;
                    $city_id = $this->insertGetId($city_data);

                    //创建区域
                    $county_data['parent_id'] = $city_id;
                    $county_data['depth'] = self::COUNTY_DEPTH;
                    $county_data['name'] = $countyName;
                    $county_data['postal_code'] = $postalCode;
                    $id = $this->insertGetId($county_data);
                }
            }
        }
        return $id;
    }


    /**
     * 获取地区全部名称
     * @param $area_id
     * @return string
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getAllName($area_id)
    {
        if ($area_id) {
            $data = array();
            $this->recursive($area_id, $data);
            $result = $this->structuralTransformation($data);
            $name = '';
            for ($i = 1; $i <= count($result); $i++) {
                $name .= $result[$i] . '-';
            }
            $name = rtrim($name, "-");
        } else {
            $name = '';
        }
        return $name;
    }


    /**
     * 区域递归查询
     * @param $area_id
     * @param array $data
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function recursive($area_id, &$data = array())
    {
        $info = $this->where('id', 'eq', $area_id)
            ->find();
        $data[] = array('depth' => $info['depth'], 'name' => $info['name']);
        if ($info['depth'] != self::PROVINCE_DEPTH) {
            $this->recursive($info['parent_id'], $data);
        }
    }


    /**
     * 地区结果转换
     * @param $data
     * @return array
     */
    public function structuralTransformation($data)
    {
        $new_data = array();
        foreach ($data as $k => $v) {
            $new_data[$v['depth']] = $v['name'];
        }
        return $new_data;
    }


    /**
     * 获取地区列表
     * @param $type
     * @param $id
     * @return array|\PDOStatement|string|\think\Collection
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getAreaList($type, $id)
    {
        switch ($type) {
            case 'province':
                $depth = self::PROVINCE_DEPTH;
                break;
            case 'city':
                $depth = self::CITY_DEPTH;
                break;
            case 'area':
                $depth = self::COUNTY_DEPTH;
                break;
            case 'four':
                $depth = self::FOUR_DEPTH;
                break;
            default:
                $depth = self::PROVINCE_DEPTH;
                break;
        }

        $data = $this->field('name, id')
            ->where('depth', 'eq', $depth)
            ->where('parent_id', 'eq', $id)
            ->select();
        return $data;
    }


    /**
     * 添加地区
     * @param $data
     * @return int|string
     */
    public function add($data)
    {
        Cache::set('area_tree', '');//清理地区缓存
        return $this->insert($data);
    }


    /**
     * 获取详情
     * @param $id
     * @return null|static
     * @throws \think\exception\DbException
     */
    public function getAreaInfo($id)
    {
        return $this->get($id);
    }


    /**
     * 编辑存储
     * @param $id
     * @param $data
     * @return static
     */
    public function edit($id, $data)
    {
        Cache::set('area_tree', '');//清理地区缓存
        return $this->where('id', 'eq', $id)
            ->update($data);
    }


    /**
     * 删除地区
     * @param $id
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function del($id)
    {
        Cache::set('area_tree', '');//清理地区缓存
        $is_parent = $this->where('parent_id', 'eq', $id)->find();
        if ($is_parent) {
            $result = array(
                'status' => false,
                'msg' => '该地区下存在关联地区，无法删除',
                'data' => array(),
            );
        } else {
            $res = $this->destroy($id);
            if ($res) {
                $result = array(
                    'status' => true,
                    'msg' => '删除成功',
                    'data' => array(),
                );
            } else {
                $result = array(
                    'status' => false,
                    'msg' => '删除失败',
                    'data' => array(),
                );
            }
        }
        return $result;
    }


    /**
     * 根据id来返回省市区信息，如果没有查到，就返回省的列表
     * @param int $id //省市区id，不传为直接取省的信息
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getArea($id = 0)
    {
        $data = $this->getParentArea($id);
        //如果没有找到地区，那么就返回一级的省列表
        if (!$data) {
            $data[0]['list'] = $this->field('id,name,parent_id')->where(array('parent_id' => self::PROVINCE_PARENT_ID))->select();
        }
        return $data;
    }


    /**
     * 递归取得父节点信息
     * @param $id
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getParentArea($id)
    {
        $data['info'] = $this->field('id,name,parent_id')->where(array('id' => $id))->find();
        if ($data['info']) {
            $data['list'] = $this->field('id,name,parent_id')->where(array('id' => $data['info']['parent_id']))->select();
            if ($data['info']['parent_id'] != self::PROVINCE_PARENT_ID) {
                //上面还有节点
                $pdata = $this->getParentArea($data['info']['parent_id']);
                if ($pdata) {
                    $pdata[] = $data;
                }
            } else {
                $pdata[] = $data;
            }
        } else {
            return [];
        }
        return $pdata;
    }


    /**
     * 获取所有省市区信息
     * @param array $checked
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getTreeArea($checked = [])
    {
        $return_data = [
            'status' => false,
            'msg' => '查询失败',
            'data' => [],
        ];
        $area_tree = Cache::get('area_tree');
        if ($area_tree) {
            $list = json_decode($area_tree, true);
        } else {
            $list = $this->where([])->select()->toArray();
            Cache::set('area_tree', json_encode($list));
        }
        $tree = $this->resolve2($list, $checked);
        $return_data['data'] = $tree;
        $return_data['msg'] = '查询成功';
        $return_data['status'] = true;
        return $return_data;
    }


    /**
     * 组装地区数据
     * @param int $list
     * @param array $checked
     * @return mixed
     */
    private function resolve2($list = 0, $checked = [])
    {
        foreach ($list as $key => $val) {
            $isChecked = '0';
            //判断是否选中的数据
            if (in_array($val['id'], (array)$checked)) {
                $isChecked = '1';
            }
            $isLast = false;
            $chid = $this->where(['parent_id' => $val['id']])->count();
            if (!$chid) {
                $isLast = true;
            }
            $area_tree[$key] = [
                'id' => $val['id'],
                'title' => $val['name'],
                'isLast' => $isLast,
                'level' => $val['depth'],
                'parentId' => $val['parent_id'],
                "checkArr" => [
                    'type' => '0',
                    'isChecked' => $isChecked,
                ]
            ];
        }
        return $area_tree;
    }


    /**
     * 根据输入的查询条件，返回所需要的where
     * @param $post
     * @return mixed
     */
    protected function tableWhere($post)
    {
        $where = [];
        if (isset($post['parent_id']) && $post['parent_id'] !== '') {
            $where[] = ['parent_id', 'eq', $post['parent_id']];
        }
        $result['where'] = $where;
        $result['field'] = "*";
        $result['order'] = [];
        return $result;
    }


    /**
     * @param $list
     * @return mixed
     */
    protected function tableFormat($list)
    {
        foreach ($list as $key => $val) {
            if ($val) {
                $child = $this->where([
                    'parent_id' => intval($val['id']),
                ])->count('id');
                if ($child > 0) {
                    $list[$key]['child'] = 'true';
                } else {
                    $list[$key]['child'] = 'false';
                }
            }
        }
        return $list;
    }

    public static function excelHeader()
    {
        return [
            ['id' => 'province', 'desc' => '省份'],
            ['id' => 'city', 'desc' => '市'],
            ['id' => 'county', 'desc' => '县/区'],
            ['id' => 'area_id', 'desc' => '地区代码']
        ];
    }

    public function getAllArea()
    {
        $areas = [];

        $provinces = $this->getAreaList('province', 0);
        $i = 0;
        foreach ($provinces as $province) {
            $areas[$i]['province_name'] = $province['name'];
            $areas[$i]['province_id'] = $province['id'];
            $cities = $this->getAreaList('city', $province->id);
            $j = 0;
            foreach ($cities as $city) {
                $areas[$i]['cities'][$j]['city_name'] = $city['name'];
                $areas[$i]['cities'][$j]['city_id'] = $city->id;
                $counties = $this->getAreaList('area', $city->id);
                $k = 0;
                foreach ($counties as $county) {
                    $areas[$i]['cities'][$j]['counties'][$k]['county_name'] = $county['name'];
                    $areas[$i]['cities'][$j]['counties'][$k]['county_id'] = $county->id;
                    $fouths = $this->getAreaList('four', $county->id);
                    $m = 0;
                    foreach ($fouths as $fouth) {
                        $areas[$i]['cities'][$j]['counties'][$k]['fouths'][$m]['fouth_name'] = $fouth['name'];
                        $areas[$i]['cities'][$j]['counties'][$k]['fouths'][$m]['fouth_id'] = $fouth->id;
                        $m++;
                    }
                    $k++;
                }
                $j++;
            }
            $i++;
        }

        return $areas;
    }

    public function getAllArea2()
    {
        $areas = [];

        $provinces = $this->getAreaList('province', 0);
        $i = 0;
        foreach ($provinces as $province) {
            $areas[$i]['province'] = $province['name'];
            $cities = $this->getAreaList('city', $province->id);
            foreach ($cities as $city) {
                $areas[$i]['city'] = $city['name'];
                $counties = $this->getAreaList('area', $city->id);
                foreach ($counties as $county) {
                    $areas[$i]['county'] = $county['name'];
                    $areas[$i]['area_id'] = $county->id;
                    $i++;
                }
                if (!count($counties)) {
                    $areas[$i]['area_id'] = $city->id;
                    $i++;
                }
            }
            if (!count($cities)) {
                $areas[$i]['area_id'] = $province->id;
                $i++;
            }
        }

        return $areas;
    }

    public static function exportHeader()
    {
        // TODO: Implement exportHeader() method.
    }
}