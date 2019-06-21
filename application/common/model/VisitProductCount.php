<?php
/**
 * Created by PhpStorm.
 * User: wang
 * Date: 2019-06-20
 * Time: 23:20
 */

namespace app\common\model;


use GuzzleHttp\Client;
use think\facade\Request;

class VisitProductCount extends Common
{
    public function setIpAttr(string  $ip)
    {
        return ip2long($ip);
    }

    public function getIpAttr($value)
    {
        return long2ip($value);
    }

    public function setArea()
    {
        if($this->ip && !$this->user_id) {
            $client = new Client();
            $data = $client->get("http://ip.taobao.com/service/getIpInfo.php?ip={$this->ip}");
            if($data && $data['code'] == 0) {
                $this->area_code = $data['data']['region_id'];
            }
        }elseif ($this->user_id) {
            $user = User::where('user_id', 'eq', $this->user_id)->find();
            $this->area_code = $user ? $user->area_id : 0;
        }
    }


    public function addRecord($productId, $userId = null)
    {
        $this->user_id = $userId;
        $this->ip = Request::ip();
        $this->date = date('Y-m-d h:i:s');
        $this->product_id = $productId;
        return $this->save();
    }

    public function product()
    {
        return $this->belongsTo(Goods::class, 'product_id');
    }

    public function area()
    {
        return $this->belongsTo(Area::class, 'area_code');
    }
}