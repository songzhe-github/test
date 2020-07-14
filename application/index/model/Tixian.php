<?php
/**
 * Created by PhpStorm.
 * User: Sz105
 * Date: 2018/3/23
 * Time: 17:06
 */

namespace app\index\model;

use think\Db;
use think\Model;

class Tixian extends Model
{
    public function TXcheck($user_id, $price)
    {

        //查找用户
        $user = Db::name('user')->field('open_id,user_id,user_name,money')->where(['user_id' => $user_id, 'status' => 0])->find();
        if (empty($user)) {
            return jsonResult('用户不存在或非法用户');
        }

        Db::startTrans();
        try {

            // 添加提现记录
            $txdata = [
                'tx_sn' => getSN('TX'),
                'user_id' => $user_id,
                'user_name' => $user['user_name'],
                'price' => $price,
                'balance' => $user['money'] - $price,
                'status' => 0,
                'add_time' => date('Y-m-d H:i:s'),
            ];
            $txid = Db::name('tixian')->insertGetId($txdata);
//            $tixian = Db::name('tixian')->field('tx_id,tx_sn')->where(['tx_id' => $txid])->find();

            // 判断用户是否第一次提现
            if ($price >= 5) {

                if ($user['money'] < $price) {
                    return jsonResult('提现失败', 100);
                }

                //更新用户余额
                $user_money = $user['money'] - $price;
                Db::name('user')->where(['user_id' => $user_id])->update(['money' => $user_money]);

                Db::commit();
                return jsonResult('提现申请成功，请耐心等待...', 100);

            }
//            else {
//
//                /**
//                 * 调用微信官方提现接口
//                 * */
//                include_once(ROOT_PATH . 'extend/wxpay/WxMchPay.php');
//                $mchPay = new \WxMchPay();
//
//                // 用户openid
//                $mchPay->setParameter('openid', $user['open_id']);
//                // 商户订单号
//                $mchPay->setParameter('partner_trade_no', $tixian['tx_sn']);
//                // 校验用户姓名选项
//                $mchPay->setParameter('check_name', 'NO_CHECK');
//                // 企业付款金额  单位为分
//                $mchPay->setParameter('amount', $price * 100);
//                // 企业付款描述信息
//                $mchPay->setParameter('desc', '【印象中】余额提现');
//                // 调用接口的机器IP地址  自定义
//                $mchPay->setParameter('spbill_create_ip', '127.0.0.1'); # getClientIp()
//                // 收款用户姓名
//                // $mchPay->setParameter('re_user_name', 'Max wen');
//                // 设备信息
//                // $mchPay->setParameter('device_info', 'dev_server');
//
//                $postStr = $mchPay->postXmlSSL();
////                echo $postStr;
//
//                if (!empty($postStr)) {
//                    //            logger(4);
//                    $postObj = simplexml_load_string($postStr, null, LIBXML_NOCDATA);
//                    //            logger( $postObj);
//                    $postObj = (array)$postObj;
//                    //            echo $postObj;
//                } else {
//                    return jsonResult('transfers_接口出错');
//                }
//
//                if ($postObj['result_code'] == 'FAIL') {
//                    return jsonResult($postObj['return_msg']);
//                }
//
//                //改变提现表的状态
//                $result = Db::name('tixian')->where(['tx_id' => $tixian['tx_id'], 'user_id' => $user_id])->update(['status' => 1]);
//                if (false !== $result) {
//
//                    if ($user['money'] < $price) {
//                        return jsonResult('提现失败', 100);
//                    }
//
//                    //更新用户余额
//                    $user_money = $user['money'] - $price;
//                    Db::name('user')->where(['user_id' => $user_id])->update(['money' => $user_money]);
//
//                    Db::commit();
//                    return jsonResult('提现成功', 200);
//                }
//            }

        } catch (\Exception $e) {
//            logger($e->getMessage());
            Db::rollback();
        }
        return jsonResult('提现失败，请稍候重试', 300);
    }
}