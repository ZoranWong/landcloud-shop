<?php
/**
 * Created by PhpStorm.
 * User: wang
 * Date: 2019-06-20
 * Time: 23:20
 */

namespace app\common\model;


use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use itbdw\Ip\IpLocation;
use think\facade\Log;
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
            $this->area_code = $this->ipArea();
        }elseif ($this->user_id) {
            $user = User::where('id', 'eq', $this->user_id)->find();
            $this->area_code = $user ? $user->area_id : $this->ipArea();
        }
    }

    public function ipArea($ip = null)
    {
        if(!$ip){
            $ip = $this->ip;
        }
        try{
            $data = json_encode(IpLocation::getLocation($ip), JSON_UNESCAPED_UNICODE);
            if($data && isset($data['province']) && $data['province']) {
                $area = Area::where('name', 'like', "%{$data['province']}%")->find();
                Log::debug('------- area for database ------'.$area->toJson());
                if($area)
                    return $area->id;
            }
            throw new \Exception();
        }catch (\Exception $exception) {
            $client = new Client();
            try{
                $respone = $client->get("http://ip.taobao.com/service/getIpInfo.php?ip={$ip}");
                $content = $respone->getBody()->getContents();
                $data = json_decode($content, true);
                if($data && $data['code'] == 0) {
                    return  $data['data']['region_id'];
                }
            }catch (\Exception $exception) {
                return 0;
            }
            /**@var Response $respone**/

            return 0;
        }
    }


    public function addRecord($productId, $userId = null)
    {
        $this->user_id = $userId;
        $this->ip = Request::ip();
        Log::debug('---------------- ip area --------------- '. "http://ip.taobao.com/service/getIpInfo.php?ip={$this->ip}");
        $this->date = date('Y-m-d h:i:s');
        $this->product_id = $productId;
        $this->setArea();
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