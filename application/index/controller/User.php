<?php
/**
 * Created by PhpStorm.
 * User: 光与旅人
 * Date: 2018/3/13
 * Time: 13:45
 */

namespace app\index\controller;

use think\Db;

class User extends Conmmon
{

    /**
     * 获取code，返回openid（弹个鬼）
     */
    public function getCode()
    {
        if (request()->isPost()) {
            $code = input('post.code');
            if (empty($code)) return jsonResult('你瞅啥', 100);

            $data = $this->get_openid($code); // 获得 openid 和 session_key
            $user = db('user')->field('user_id,openid,status')->where(['openid' => $data['openid']])->find();

            if (empty($user)) {
                $arr = [
                    'openid' => $data['openid'],
                    'user_name' => '',
                    'avatar' => 'https://yxdrs.zsmgc.com.cn/A/public/app/wdl.png',
                    'add_time' => date('Y-m-d'),
                    'end_time' => date('Y-m-d H:i:s'),
                ];
                $uid = db('user')->insertGetId($arr);
                $res['mstr'] = lock_url($data['openid'] . ',' . $uid);

                $user_tank = [
                    'user_id' => $uid,
                    'tank_id' => 1,
                    'tank_lv' => 1,
                    'add_time' => date('Y-m-d H:i:s'),
                ];
                db('user_tank')->insert($user_tank);
                $user_ball = [
                    'user_id' => $uid,
                    'ball_id' => 1,
                    'add_time' => date('Y-m-d H:i:s'),
                ];
                db('user_ball')->insert($user_ball);

                return jsonResult('请求成功', 200, $res);

            } else {
                db('user')->where(['user_id' => $user['user_id']])->update(['end_time' => date('Y-m-d H::s')]);
                $res['mstr'] = lock_url($data['openid'] . ',' . $user['user_id']);
                return jsonResult('请求成功', 200, $res);
            }
        }
    }

    // 获取用户信息
    public function getUserInfo()
    {
        $user_name = input('post.user_name');
        $avatar = input('post.avatar');
        $sex = input('post.sex');
        $city = input('post.city');

        $user = db('user')->where(['user_id' => $this->user_id])->value('user_id');
        if (empty($user)) return jsonResult('数据不存在', 100);

        $updata = [
            'user_name' => empty($user_name) ? '' : $user_name,
            'avatar' => empty($avatar) ? '' : $avatar,
            'sex' => empty($sex) ? '' : $sex,
            'city' => empty($city) ? '' : $city,
            'end_time' => date('Y-m-d H:i:s'),
        ];
        db('user')->where(['user_id' => $this->user_id])->update($updata);
        return jsonResult('用户信息', 200);
    }


    /**
     * 获取code，返回openid（黄金矿工）
     */
    public function getMinerCode()
    {
        if (request()->isPost()) {
            $code = input('post.code');
            if (empty($code)) return jsonResult('你瞅啥', 100);

            $data = $this->getMinerOpenid($code); // 获得 openid 和 session_key
//            return jsonResult('succ',200,$data);
            $user = Db::table('miner_user')->field('user_id,openid,status')->where(['openid' => $data['openid']])->find();

            if (empty($user)) {
                $arr = [
                    'openid' => $data['openid'],
                    'user_name' => '游客',
                    'coin' => '1',
                    'avatar' => 'https://yxdrs.zsmgc.com.cn/A/public/app/miner_wdl.png',
                    'add_time' => date('Y-m-d'),
                    'end_time' => date('Y-m-d H:i:s'),
                ];

                $uid = Db::table('miner_user')->insertGetId($arr);
                $res['mstr'] = lock_url($data['openid'] . ',' . $uid);
                return jsonResult('请求成功', 200, $res);

            } else {
                Db::table('miner_user')->where(['user_id' => $user['user_id']])->update(['end_time' => date('Y-m-d H::s')]);
                $res['mstr'] = lock_url($data['openid'] . ',' . $user['user_id']);
                return jsonResult('请求成功', 200, $res);
            }
        }
    }

    // 获取用户信息
    public function getMinerUserInfo()
    {
        $user_name = input('post.user_name');
        $avatar = input('post.avatar');
        $sex = input('post.sex');
        $city = input('post.city');

        $user = Db::table('miner_user')->where(['user_id' => $this->user_id])->value('user_id');
        if (empty($user)) return jsonResult('数据不存在', 100);

        $updata = [
            'user_name' => empty($user_name) ? '' : $user_name,
            'avatar' => empty($avatar) ? '' : $avatar,
            'sex' => empty($sex) ? '' : $sex,
            'city' => empty($city) ? '' : $city,
            'end_time' => date('Y-m-d H:i:s'),
        ];
        Db::table('miner_user')->where(['user_id' => $this->user_id])->update($updata);
        return jsonResult('用户信息', 200);
    }


    /**
     * 获取code，返回openid（超级鲶鱼）
     */
    public function getFishCode()
    {
        if (request()->isPost()) {
            $code = input('post.code');
            if (empty($code)) return jsonResult('你瞅啥', 100);

            $data = $this->getFishOpenid($code); // 获得 openid 和 session_key
//            return jsonResult('succ',200,$data);
            $user = Db::table('fish_user')->field('user_id,openid,status')->where(['openid' => $data['openid']])->find();

            if (empty($user)) {
                $arr = [
                    'openid' => $data['openid'],
                    'user_name' => '',
                    'coin' => 1,
                    'avatar' => 'https://yxdrs.zsmgc.com.cn/A/public/app/miner_wdl.png',
                    'add_time' => date('Y-m-d'),
                    'end_time' => date('Y-m-d H:i:s'),
                ];
                $uid = Db::table('fish_user')->insertGetId($arr);
                $res['mstr'] = lock_url($data['openid'] . ',' . $uid);


                return jsonResult('请求成功', 200, $res);

            } else {
                Db::table('fish_user')->where(['user_id' => $user['user_id']])->update(['end_time' => date('Y-m-d H::s')]);
                $res['mstr'] = lock_url($data['openid'] . ',' . $user['user_id']);
                return jsonResult('请求成功', 200, $res);
            }
        }
    }

    // 获取用户信息
    public function getFishUserInfo()
    {
        $user_name = input('post.user_name');
        $avatar = input('post.avatar');
        $sex = input('post.sex');
        $city = input('post.city');

        $user = Db::table('fish_user')->where(['user_id' => $this->user_id])->value('user_id');
        if (empty($user)) return jsonResult('数据不存在', 100);

        $updata = [
            'user_name' => empty($user_name) ? '' : $user_name,
            'avatar' => empty($avatar) ? '' : $avatar,
            'sex' => empty($sex) ? 0 : $sex,
            'city' => empty($city) ? '' : $city,
            'end_time' => date('Y-m-d H:i:s'),
        ];
        Db::table('fish_user')->where(['user_id' => $this->user_id])->update($updata);
        return jsonResult('用户信息', 200);
    }


    public function aaa()
    {

        $mstr = 'T2M4RVdScVV5WTNVZ1BGRDFTMDFFV1Q5RWZZNjJiMS1hV1VQREFJa3lnMVV1'; // 黄金矿工
        echo unlock_url($mstr);
    }

}