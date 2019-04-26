<?php
/**
 * Created by PhpStorm.
 * User: wang
 * Date: 2019-04-26
 * Time: 08:56
 */

namespace app\service;


use GuzzleHttp\Client;

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
        $this->host = config('api.app.lab_gic_host');
        $this->http = new Client();
    }

    public function getUserBalance($id)
    {
        $method = '/api/api/GetCurrCreditSP';
        $code = md5($this->apiKey.$id);
        $response = $this->http->get("{$this->host}{$method}", ['query' => ['CusCode' => $id, 'ApiKey' => $code]]);
        $data = json_decode($response->getBody()->getContents(), true);
        if($data['code'] === self::SUCCESS_CODE){
            return $data['data']['CurrCredit'];
        }else{
            return 0;
        }
    }

    public function getUsersBalance($ids)
    {

    }

    public function getProductStock($id)
    {
        $method = '/api/api/GetStockSP';
        $code = md5($this->apiKey.$id);
        $response = $this->http->get("{$this->host}{$method}", ['query' => ['InvCode' => $id, 'ApiKey' => $code]]);
        $data = json_decode($response->getBody()->getContents(), true);
        if($data['code'] === self::SUCCESS_CODE){
            $list = $data['data'];
            $result = [
                'list' => [],
                'total' => 0
            ];
            foreach ($list as $item) {
                $result['list'][] = [
                    'store' => $item['StockName'],
                    'stock_num' => $item['Qty']
                ];
                $result['total'] += $item['Qty'];
            }
            return $result;
        }else{
            return null;
        }
    }

    public function getProductsStock($ids)
    {

    }
}