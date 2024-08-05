<?php

namespace app\common\model;

use app\admin\model\Admin;
use think\db\Query;
use think\Model;
use traits\model\SoftDelete;

class Question extends Model
{

    use SoftDelete;

    

    // 表名
    protected $name = 'question';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'integer';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $deleteTime = 'deletetime';

    // 追加属性
    protected $append = [
        'status_text'
    ];

    /**
     * @return static
     */
    public static function getInstance(): self
    {
        return new static();
    }

    /**
     * @param array $scope
     * @author xaboy
     * @day 2020-03-30
     */
    public static function getDB(array $scope = []): Query
    {
        return self::getInstance()->db($scope);
    }


    public function getStatusList()
    {
        return ['normal' => __('Normal'), 'hidden' => __('Hidden')];
    }


    public function getStatusTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['status']) ? $data['status'] : '');
        $list = $this->getStatusList();
        return isset($list[$value]) ? $list[$value] : '';
    }




    public function user()
    {
        return $this->belongsTo('User', 'user_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }

    public function people()
    {
        return $this->hasOne(User::class,'id','user_id');
    }

    public function teacher()
    {
        return $this->hasOne(Admin::class,'id','admin_id');
    }

    public function admin()
    {
        return $this->belongsTo(Admin::class, 'admin_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }


    public function category()
    {
        return $this->belongsTo('Category', 'cid', 'id', [], 'LEFT')->setEagerlyType(0);
    }

    public function getContentAttr($value)
    {
        return $this->get_file_domain($value);
    }

    /**
     * @notes 设置内容图片域名
     * @param $content
     * @return array|string|string[]|null
     * @author 段誉
     * @date 2022/9/26 10:43
     */
    public function get_file_domain($content)
    {
        $preg = '/(<img .*?src=")[^https|^http](.*?)(".*?>)/is';
        $url=request()->domain();
        $fileUrl = $url;
        return preg_replace($preg, "\${1}$fileUrl\${2}\${3}", $content);
    }
}
