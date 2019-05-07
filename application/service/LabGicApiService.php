<?php
/**
 * Created by PhpStorm.
 * User: wang
 * Date: 2019-04-26
 * Time: 08:56
 */

namespace app\service;


use GuzzleHttp\Client;
use think\facade\Log;

class LabGicApiService
{
    /**
     * @var LabGicApiService $instance
     * */
    protected static $instance = null;
    protected $host = null;
    protected $http = null;
    protected $apiKey = '52654953';
    const SUCCESS_CODE = '200';

    protected function __construct()
    {
        $this->host = config('app.lab_gic_host');
        $this->http = new Client();
    }

    public static function getInstance()
    {
        if (!self::$instance) {
            self::$instance = new static();
        }
        return self::$instance;
    }

    public static function __callStatic($name, $arguments)
    {
        // TODO: Implement __callStatic() method.
        $method = 'get'.ucfirst($name);
        return call_user_func([self::getInstance(), $method], $arguments);
    }

    public function getUserBalance($id)
    {
        $method = '/api/api/GetCurrCreditSP';
        $id = urlencode(base64_encode(json_encode($id)));
        $code = md5($this->apiKey . $id);
        $response = $this->http->get("{$this->host}{$method}", ['query' => ['CusCode' => $id, 'ApiKey' => $code]]);
        $data = json_decode($response->getBody()->getContents(), true);
        if ($data['code'] === self::SUCCESS_CODE) {
            return $data['data']['CurrCredit'];
        } else {
            return 0;
        }
    }

    public function getUsersBalance($ids)
    {

    }

    public function getProductStock($id)
    {
        $method = '/api/api/GetStockSP';
        $id = urlencode(base64_encode(json_encode($id)));
        $code = md5($this->apiKey . $id);
        $response = $this->http->get("{$this->host}{$method}", ['query' => ['InvCode' => $id, 'ApiKey' => $code]]);
        $data = json_decode($response->getBody()->getContents(), true);
        if ($data['code'] === self::SUCCESS_CODE) {
            $list = $data['data'];
            return $this->productStock($list);
        } else {
            return null;
        }
    }

    protected function productStock($erpStocks)
    {
        $result = [
            'list' => [],
            'total' => 0
        ];
        foreach ($erpStocks as $item) {
            $result['list'][] = [
                'store' => $item['StockName'],
                'stock_num' => $item['Qty']
            ];
            $result['total'] += $item['Qty'];
        }
    }

    public function getProductsStock($ids)
    {
        $method = '/api/api/GetStockSP_all';
        $ids = urlencode(base64_encode(json_encode($ids)));
        $code = md5($this->apiKey . $ids);
        $response = $this->http->get("{$this->host}{$method}", ['query' => ['InvCode' => $ids, 'ApiKey' => $code]]);
        $data = json_decode($response->getBody()->getContents(), true);
        if ($data['code'] === self::SUCCESS_CODE) {
            $list = $data['data'];
            $result = [];
            foreach ($list as $key => $item) {
                $result[$key] = $this->productStock($item);
            }
            return $result;
        } else {
            return null;
        }
    }
}