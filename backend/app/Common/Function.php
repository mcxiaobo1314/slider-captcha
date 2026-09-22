<?php

use Carbon\Carbon;

use function Hyperf\Config\config;




/**
 * 获取当前时间
 * @return string
 * @author wave
 */
function getNow()
{
    $now = Carbon::now('Asia/Shanghai');
    return $now->toDateTimeString();
}

/**
 * 获取当前时间戳
 * @return string
 * @author wave
 */
function getTimestamp()
{
    $now = Carbon::now('Asia/Shanghai');
    return $now->timestamp;
}



/**
 * 获取日期格式化
 * @param string $curDate 当前日期 格式 YYYYMMDD 或 YYYY-MM-dd 必须包含日
 * @param string $format 格式化字符串
 * @return string
 * @author wave
 */
function getNowFormat($curDate, $format = 'Ym')
{
    $time = strtotime($curDate);
    $now = Carbon::create(
        date('Y', $time),
        date('m', $time),
        date('d', $time),
        date('H', $time),
        date('i', $time),
        date('s', $time)
    );
    return $now->format($format);
}