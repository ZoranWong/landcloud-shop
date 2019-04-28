<?php
namespace app\api\controller;
use app\common\model\Images as imageModel;
use app\common\controller\Api;
use app\service\Upload;
use Request;
use think\Container;

class Images extends Api
{
    public function upload()
    {
        $path = "/uploads";
        $upload = Upload::getInstance();
        $info = $upload->upload($path);
        $savepath = $upload->path;
        $imageStorage = $upload->imageStorage;
        if($info)
        {
            $first = array_shift($info);
            $url = $upload->getUrl($savepath);
//            var_dump($url, $savepath);
            $iData['id'] = md5(get_hash($first['name']));
            $iData['type'] = $imageStorage['type'];
            $iData['name'] = strlen($first['name'])>50?substr($first['name'], 0, 50):$first['name'];
            $iData['url'] = $url;
            $iData['ctime'] = time();
            $iData['path'] = $savepath;
            $image_model = new imageModel();
            if($image_model->save($iData))
            {
                $data = [
                    'status' => true,
                    'msg' => '上传成功',
                    'data' => [
                        'image_id' => $iData['id'],
                        'url' => $url,
                        'type' => $first['type'],
                    ]
                ];
                return $data;
            }else {
                return [
                    'data' => '',
                    'status' => false,
                    'msg' => "保存失败"
                ];
            }
        }else {
            return [
                'data' => '',
                'status' => false,
                'msg' => $upload->getError()
            ];
        }
    }
}