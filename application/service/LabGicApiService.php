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
    protected function __construct()
    {
        $this->host = config('api.app.lab_gic_host');
        $this->http = new Client();
    }

    public function getUserBalance($id)
    {
        $method = '';
        $this->http->get("{$this->host}{$method}", ['query' => ['']]);
    }

    public function getUsersBalance($ids)
    {

    }

    public function getProductStock($id)
    {

    }

    public function getProductsStock($ids)
    {

    }
}