<?php
/**
 * Created by PhpStorm.
 * User: wang
 * Date: 2019-04-26
 * Time: 07:49
 */

namespace app\service;
use org\Upload as OUpload;
/**
 *
 * */
class Upload extends OUpload
{
    /**
     * @var Upload $instance
     * */
    protected static  $instance = null;
    public function __construct()
    {
        $filetypes = [
            'image' => [
                'title' => 'Image files',
                'extensions' => 'jpg,jpeg,png,gif,bmp4'
            ],
        ];
        $allAllowedExts = array();
        foreach ($filetypes as $mfiletype) {
            array_push($allAllowedExts, $mfiletype['extensions']);
        }

        $allAllowedExts = implode(',', $allAllowedExts);
        $allAllowedExts = explode(',', $allAllowedExts);
        $allAllowedExts = array_unique($allAllowedExts);
        $uploadMaxFilesize = config('jshop.upload_filesize');
        $uploadMaxFilesize = empty($uploadMaxFilesize) ? 5242880 : $uploadMaxFilesize;//默认5M

        if (isset($_FILES['upfile'])) {
            $file_extension = get_file_extension($_FILES['upfile']['name']);
            $savepath = '/static/uploads/images/' . get_hash_dir($_FILES['upfile']['name']);
        } else {
            $file_extension = get_file_extension($_FILES['file']['name']);
            $savepath = '/static/uploads/images/' . get_hash_dir($_FILES['file']['name']);
        }

        //上传处理类
        $config = array(
            'rootPath' => ROOT_PATH . DIRECTORY_SEPARATOR . 'public',
            'savePath' => $savepath,
            'maxSize' => $uploadMaxFilesize,
            'saveName' => array(
                'uniqid',
                ''
            ),
            'exts' => $allAllowedExts,
            'autoSub' => false,
        );

        $imageStorage = config('jshop.image_storage');
        if (!$imageStorage) {
            $imageStorage = [
                'type' => 'Local',
            ];
        }
        //增加后台设置，如果设置则用后台设置的
        if (getSetting('image_storage_params')) {
            $imageStorage = array_merge(['type' => getSetting('image_storage_type')], getSetting('image_storage_params'));
        }
        //增加后台设置，如果设置则用后台设置的
        if (getSetting('image_storage_params')) {
            $imageStorage = array_merge(['type' => getSetting('image_storage_type')], getSetting('image_storage_params'));
        }
        parent::__construct($config, $imageStorage['type'], $imageStorage);
    }


    public static function __callStatic($name, $arguments)
    {
        // TODO: Implement __callStatic() method.
        if(!self::instance){
            self::$instance = new static();
        }

        return call_user_func([self::$instance, $name], $arguments);
    }
}