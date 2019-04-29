<?php
/**
 * Created by PhpStorm.
 * User: wangzaron
 * Date: 2019/4/29
 * Time: 2:58 PM
 */

namespace app\filesystem;


use League\Flysystem\Filesystem;
use think\Container;

class Storage
{
    /**
     * @var Filesystem $disk
     * */
    protected $fileSystem = null;


    /**
     * @var Storage $instance
     * */
    protected static $instance = null;

    public function __construct($disk = 'local')
    {
        $this->disk($disk);
    }

    public function disk($disk) {
        $adapter = Container::get($disk);
        $this->fileSystem = new Filesystem($adapter);
    }

    public static function __callStatic($name, $arguments)
    {
        // TODO: Implement __call() method.
        if(!self::$instance){
            self::$instance = new static();
        }
        return call_user_func_array([self::$instance->fileSystem, $name], $arguments);
    }

    public function __call($name, $arguments)
    {
        // TODO: Implement __call() method.
        return call_user_func_array([$this->fileSystem, $name], $arguments);
    }
}