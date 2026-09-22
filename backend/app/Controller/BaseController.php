<?php

namespace App\Controller;


class BaseController extends AbstractController
{
    /**
     * redis 对象
     * @var \Hyperf\Redis\Redis $redis
     * @author wave
     */
    protected $redis;


    /**
     * 构造函数
     * @author wave
     */
    public function __construct()
    {
        $this->redis = $this->container->get(\Hyperf\Redis\Redis::class);
    }
}
