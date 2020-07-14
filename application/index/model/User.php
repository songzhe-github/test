<?php
/**
 * Created by PhpStorm.
 * User: 光与旅人
 * Date: 2018/3/13
 * Time: 15:15
 */

namespace app\index\model;

use think\Model;
use think\Db;

class User extends Model
{
    protected $id = 'user_id';

    // 根据条件查询
    public function findById($where, $field = '*')
    {
        return Db::name($this->name)->field($field)->where($where)->find();
    }

    // 新增
    public function add($data)
    {
        if (empty($data['add_time'])) {
            $data['add_time'] = date('Y-m-d H:i:s');
        }

        return Db::name($this->name)->insertGetId($data);
//        return Db::name($this->name)->insert($data);
    }

    // 修改
    public function edit($data)
    {
        $res = $this->isUpdate(true)->allowField(true)->save($data);
        if ($res) {
            return true;
        } else {
            return false;
        }
    }


}