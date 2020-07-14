<?php
/**
 * 微信支付
 * User: 光与旅人
 * Date: 2018/3/14
 * Time: 10:10
 */

namespace app\index\model;

use think\Model;
use think\Db;

class Recharge extends Model
{
    protected $id = 'recharge_id';

    // 根据条件查询
    public function findById($where, $field = '*')
    {
        return Db::name($this->name)->field($field)->order('recharge_id desc')->where($where)->find();
    }

    public function add($data)
    {
        if (empty($data['add_time'])) {
            $data['add_time'] = date('Y-m-d H:i:s');
        }
        return Db::name($this->name)->insertGetId($data);
    }


    /*
     * 充值成功回调
     * $user 用户对象
     * $recharge  充值对象
     * $transaction_id 微信交易流水号
    */
    public function wxSucc($recharge, $transaction_id)
    {
        Db::startTrans();
        try {

            // 更新用户金币
            $pay = db($this->name)->field('recharge_id,user_id,shop_id')->where(['recharge_id' => $recharge['recharge_id']])->find();
            if ($pay['shop_id'] == 1) {
                $shop = 'power_medicine';
            } elseif ($pay['shop_id'] == 2) {
                $shop = 'hint_medicine';
            }
            db('user')->where(['user_id' => $pay['user_id']])->setInc($shop);

            //更新原有充值记录
            $update = array(
                'status' => 1,
                'wechat_sn' => $transaction_id,
            );
            db($this->name)->where(['recharge_id' => $recharge['recharge_id']])->update($update);

            Db::commit();
            return true;

        } catch (\Exception $e) {
            Db::rollback();
        }
        return false;
    }

}