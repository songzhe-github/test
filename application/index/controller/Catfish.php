<?php
/**
 * Created by PhpStorm.
 * User: SongZhe
 * Date: 2018/12/5
 * Time: 15:15
 */

namespace app\index\controller;

use think\Db;

class Catfish extends Conmmon
{
    # 获取code，返回openid（超级鲶鱼）
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
                    'coin' => 500,
                    'avatar' => 'https://yxdrs.zsmgc.com.cn/A/public/app/miner_wdl.png',
                    'superfish_lv' => 1,
                    'add_time' => date('Y-m-d'),
                    'end_time' => date('Y-m-d H:i:s'),
                ];
                $uid = Db::table('fish_user')->insertGetId($arr);
                $res['mstr'] = lock_url($data['openid'] . ',' . $uid);
                $res['new_player'] = 1;
                return jsonResult('请求成功', 200, $res);

            } else {
                Db::table('fish_user')->where(['user_id' => $user['user_id']])->update(['end_time' => date('Y-m-d H::s')]);
                $res['mstr'] = lock_url($data['openid'] . ',' . $user['user_id']);
                $res['new_player'] = 0;
                return jsonResult('请求成功', 200, $res);

            }
        }
    }

    # 获取用户信息
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

    # 首页
    public function index()
    {
//        $time = date('Y-m-d');
        $time = time();

        # 分享文案
        $share = Db::table('fish_share_pic')->find();
        $data['share'] = $share;

        # 首页用户信息
        $user = Db::table('fish_user')
            ->field('user_id,all_coin,coin,diamond,offline_fishs,pay_fishs,map_id,unlock_map,free_gem_down_time,superfish_lv,superfish_growth_value')
            ->where(['user_id' => $this->user_id])
            ->find();
        $user['all_coin'] = intval($user['all_coin']);
        $user['now_coin'] = intval($user['coin']);
        $user['fishs'] = json_decode($user['offline_fishs'], true);
        $data['userInfo'] = $user;

        # 规则
        $fishs = Db::table('fish_fishs')
            ->field('fish_id,fish_name,fish_up,fish_up_xs,fish_up_zs,fish_up_coin,fish_sy_coin')
            ->where(['type' => 1])
            ->select();
        foreach ($fishs as &$v) {
            $v['fish_sy_coin'] = intval($v['fish_sy_coin']);
        }
        $data['fishs'] = $fishs;

        #免费宝石倒计时
        if ($time >= $user['free_gem_down_time']) {
            $free_gem_down_time['status'] = 1;
        } else {
            $free_gem_down_time['status'] = 0;
            $free_gem_down_time['time'] = $user['free_gem_down_time'] - $time;
        }
        $data['free_gem_down_time'] = $free_gem_down_time;

//        #  每日视频和分享次数
//        $config = Db::table('fish_config')->select();
//        $video_num = Db::table('fish_video')->field('type,count(type) as num')->where(['user_id' => $this->user_id, 'add_time' => $time])->group('type')->select();
//        $share_num = Db::table('fish_share')->field('type,count(type) as num')->where(['user_id' => $this->user_id, 'add_time' => $time])->group('type')->select();
//        foreach ($config as $k => $v) {
//            foreach ($video_num as $i => $j) {
//                if ($v['config_id'] == $j['type']) {
//                    $config[$k]['video_num'] = $v['video_num'] - $j['num'];
//                }
//            }
//
//            foreach ($share_num as $y => $z) {
//                if ($v['config_id'] == $z['type']) {
//                    $config[$k]['share_num'] = $v['share_num'] - $z['num'];
//                }
//            }
//        }
//        $data['config'] = $config;
//        $coin_times = Db::table('fish_times')->find();
//        $data['hc_times'] = $coin_times['hc_times'];      // 合成
//        $data['box_times'] = $coin_times['box_times'];     // 神秘宝箱
//        $data['coin_times'] = $coin_times['coin_times'];    // 金币不足
//        $data['double_coin'] = 3;   //
        $data['second_give_coin'] = 2;    // 每多少秒产生收益

        $data['superfish'] = Db::table('fish_superfish')->find();

        $is_qd = Db::table('fish_sign')->where(['user_id' => $this->user_id, 'add_time' => date('Y-m-d')])->value('sign_id');
        $data['is_qd'] = empty($is_qd) ? 0 : 1;
        return jsonResult('succ', 200, $data);
    }

    # 点击领取免费宝石
    public function getFreeGem()
    {
        $time = time();
        $user = Db::table('fish_user')->field('diamond,free_gem_down_time')->where(['user_id' => $this->user_id])->find();
        if ($time >= $user['down_time']) {
            $upData = [
                'diamond' => $user['diamond'] + 20,
                'free_gem_down_time' => $time + 600,
            ];
            Db::table('fish_user')->where(['user_id' => $this->user_id])->update($upData);
            return jsonResult('领取成功', 200);
        } else {
            return jsonResult('领取失败', 100);
        }
    }

    # 大转盘 （奖品列表）
    public function prizeList()
    {
        $time = time();
        $prize = Db::table('fish_prize')->select();
        foreach ($prize as $k => $v) {
            if ($v['type'] == 1) {
                $prize[$k]['num'] = $v['prize_name'];
            }
        }
        #免费抽奖倒计时
        $user = Db::table('fish_user')->field('free_prize_draw_time')->where(['user_id' => $this->user_id])->find();
        if ($time >= $user['free_prize_draw_time']) {
            $free_gem_down_time['status'] = 1;
        } else {
            $free_gem_down_time['status'] = 0;
            $free_gem_down_time['time'] = $user['free_prize_draw_time'] - $time;
        }
        $res['list'] = $prize;
        $res['free_prize_draw_time'] = $free_gem_down_time;
        return jsonResult('奖品列表', 200, $res);
    }

    # 点击抽奖
    public function drawPrize()
    {
        $time = time();
        $c = 1000;

        Db::table('fish_user')->where(['user_id' => $this->user_id])->update(['free_prize_draw_time' => $time + 600]);
        $prize_arr = Db::table('fish_prize')->field('prize_id,gl,num,type')->select();
//            dump($prize_arr);die;
        foreach ($prize_arr as $key => $val) {
            $arr[$val['prize_id']] = $val['gl'];
        }
        $rid = get_rand($arr); //根据概率获取奖项id
        $num = $prize_arr[$rid - 1]['num'];
        $num1 = 0;
        if ($prize_arr[$rid - 1]['type'] == 1) {
            $num1 = rand($c * $num, $c * ($num + 3));
        } elseif ($prize_arr[$rid - 1]['type'] == 2) {
            $num1 = $num;
        }

        $res = [
            'prize_id' => $rid,
            'num' => $num1,
            'type' => $prize_arr[$rid - 1]['type']
        ];
        return jsonResult('抽奖成功', 200, $res);
    }

    # 签到
    public function signList()
    {
        $signlist = Db::table('fish_signlist')->field('sign_id,sign_name,num,status')->select();
        $sign = Db::table('fish_sign')->where(['user_id' => $this->user_id, 'status' => 0])->select();
//        $ids = [];
        foreach ($signlist as $k => $v) {
//            $arr = [];
//            if ($v['sign_id'] == 7) {
//                $num = explode(',', $v['num']);
//                for ($i = 0; $i < count($num); $i++) {
//                    $arr[] = intval($num[$i]);
//
//                }
//            } else {
//                $arr = intval($v['num']);
//            }
//            $signlist[$k]['num'] = $arr;
//            foreach ($sign as $i => $j) {
//                if ($v['sign_id'] == $j['sign_id']) {
//                    $ids[] = $j['sign_id'];
//                    $signlist[$k]['status'] = 1;
//
////                    $signlist[$k]['status'] = count($ids) >= 7 ? 0 : 1;
//                }
//            }
//        }
//        $is_qd = Db::table('fish_sign')->where(['user_id' => $this->user_id, 'add_time' => date('Y-m-d')])->value('sign_id');
//        if (empty($is_qd) && count($ids) == 7) {
//            foreach ($signlist as $kye => $value) {
//                $signlist[$kye]['status'] = 0;
//            }
//
            $signlist[$k]['num'] = intval($v['num']);
        }
        $res['sign'] = $signlist;
        $res['is_qd'] = empty($is_qd) ? 0 : 1;
        return jsonResult('签到列表', 200, $res);
    }

    # 点击签到
    public function clickSign()
    {
        $type = input('post.type');
        $qd_id = Db::table('fish_sign')->where(['user_id' => $this->user_id, 'add_time' => date('Y-m-d')])->value('qd_id');
        if ($qd_id) return jsonResult('今日已签到过', 100);
//        dump($qd_id);die;
        $sign_id = Db::table('fish_sign')->where(['user_id' => $this->user_id, 'status' => 0])->order('qd_id desc')->value('sign_id');
        if (empty($sign_id)) {
            $day = 1;
        } else {
            $day = $sign_id + 1;
            if ($sign_id >= 7) {
                $day = 1;
                Db::table('fish_sign')->where(['user_id' => $this->user_id, 'status' => 0])->update(['status' => 1]);
            }
        }
        $addData = [
            'user_id' => $this->user_id,
            'sign_id' => $day,
            'add_time' => date('Y-m-d')
        ];
        Db::table('fish_sign')->insert($addData);

        $user = Db::table('fish_user')->field('coin,diamond,phone_ticket')->where(['user_id' => $this->user_id])->find();
        $signlist = Db::table('fish_signlist')->field('type,num')->where(['sign_id' => $day])->find();
        $update = [];

        $num = $type == 2 ? 2 : 1;
        if ($signlist['type'] == 1) {
            $update = [
                'coin' => ($user['coin'] + $signlist['num']) * $num
            ];
        } elseif ($signlist['type'] == 2) {
            $update = [
                'diamond' => ($user['diamond'] + $signlist['num']) * $num
            ];
        } elseif ($signlist['type'] == 3) {
            $update = [
                'phone_ticket' => ($user['phone_ticket'] + $signlist['num']) * $num
            ];
        } elseif ($signlist['type'] == 4) {

            $arr = explode(',', $signlist['num']);
            $update = [
                'coin' => ($user['coin'] + $arr[0]) * $num,
                'diamond' => ($user['diamond'] + $arr[1]) * $num,
                'phone_ticket' => ($user['phone_ticket'] + $arr[2]) * $num
            ];
        }
//        dump($update);die;
        Db::table('fish_user')->where(['user_id' => $this->user_id])->update($update);
        return jsonResult('签到成功', 200);
    }

    # 邀请好友
    public function invited()
    {
        $signlist = Db::table('fish_signlist')->field('sign_id,sign_name,num,status')->select();
        $sign = Db::table('fish_sign')->where(['user_id' => $this->user_id, 'status' => 0])->select();
        foreach ($signlist as $k => $v) {
            $arr = [];
            if ($v['sign_id'] == 7) {
                $num = explode(',', $v['num']);
                for ($i = 0; $i < count($num); $i++) {
                    $arr[] = intval($num[$i]);

                }
            } else {
                $arr = intval($v['num']);
            }
            $signlist[$k]['num'] = $arr;
            foreach ($sign as $i => $j) {
                if ($v['sign_id'] == $j['sign_id']) {
                    $signlist[$k]['status'] = 1;
                }
            }
        }
        $is_qd = Db::table('fish_sign')->where(['user_id' => $this->user_id, 'add_time' => date('Y-m-d')])->value('sign_id');
        $res['list'] = $signlist;
        $res['is_qd'] = empty($is_qd) ? 0 : 1;
        $data['sign'] = $res;


        return jsonResult('签到列表', 200, $data);
    }

    # 上线
    public function online()
    {
        $time = time();
        $user = Db::table('fish_user')
            ->field('all_coin,coin,offline_fishs,pay_fishs,off_time,superfish_lv,superfish_growth_value,map_id,unlock_map')
            ->where(['user_id' => $this->user_id])
            ->find();
        $user_fish = json_decode($user['offline_fishs'], true);
        $pay_fish = json_decode($user['pay_fishs'], true);
        if ($user_fish) {
            $fishs = Db::table('fish_fishs')->field('fish_id,fish_sy_coin')->select();
            $off_time = $time - $user['off_time'];
            $aaa = $off_time >= 600 ? 600 : $off_time;
            $coin = 0;
            foreach ($user_fish as $k => $v) {
                foreach ($fishs as $i => $j) {
                    if ($v['fish_id'] == $j['fish_id']) {
                        $coin += $j['fish_sy_coin'] * $aaa * $v['fish_number'];
                    }
                }
            }

//        dump($coin);die;
            Db::table('fish_user')->where(['user_id' => $this->user_id])->update(['off_time' => $time]);
            $res['fishs'] = $user_fish;
            $res['pay_fishs'] = $pay_fish;
            $res['all_coin'] = intval($user['all_coin']);
            $res['coin'] = intval($coin);
            $res['map_id'] = $user['map_id'];
            $res['unlock_map'] = $user['unlock_map'];
            $res['superfish_lv'] = $user['superfish_lv'];
            $res['superfish_growth_value'] = $user['superfish_growth_value'];
            return jsonResult('上线数据', 200, $res);
        }
        return jsonResult('暂无数据', 100);
    }

    # 离线收益
    public function offline()
    {
        $post = request()->only(['all_coin', 'now_coin', 'map_id', 'unlock_map', 'diamond', 'offline_fishs', 'pay_fishs', 'superfish_lv', 'superfish_growth_value', 'max_fish_id'], 'post');

//        if (empty($post)) return jsonResult('参数为空', 100);
//        dump($post);die;
        $all_coin = Db::table('fish_user')->field('all_coin,unlock_map')->where(['user_id' => $this->user_id])->find();
        $upData = [
            'all_coin' => intval($all_coin['all_coin']) + intval($post['all_coin']),
            'coin' => $post['now_coin'],
            'diamond' => $post['diamond'],
            'pay_fishs' => $post['pay_fishs'],
            'offline_fishs' => $post['offline_fishs'],
            'max_fish_id' => $post['max_fish_id'],
            'map_id' => $post['map_id'],
            'unlock_map' => $post['unlock_map'],
            'superfish_lv' => $post['superfish_lv'],
            'superfish_growth_value' => $post['superfish_growth_value'],
            'off_time' => time(),
        ];
        Db::table('fish_user')->where(['user_id' => $this->user_id])->update($upData);
        return jsonResult('离线记录成功', 200, $post);
    }

    # 商店
    public function shop()
    {
//        $fishs = Db::table('fish_fishs')->field('fish_id,fish_name,fish_pic')->where(['type' => 1])->select();
//        $user_fish = Db::table('fish_user_fish')->field('fish_id,fish_lv')->where(['user_id' => $this->user_id])->select();
//
//        $res = [];
//        foreach ($fishs as $k => $v) {
//            $res[$k]['fish_id'] = $v['fish_id'];
//            $res[$k]['fish_name'] = $v['fish_name'];
//            $res[$k]['fish_pic'] = $v['fish_pic'];
//            $res[$k]['fish_lv'] = 0;
//            foreach ($user_fish as $i => $j) {
//                if ($v['fish_id'] == $j['fish_id']) {
////                        $res[$k]['fish_number'] = strval(round($v['fish_up_coin'] + ($j['fish_lv'] * pow($v['fish_up'], 1 + $j['fish_lv'] * $v['fish_up_xs']))));
//                    $res[$k]['fish_lv'] = $j['fish_lv'];
//                }
//            }
//        }
//        $data['fish'] = $res;
        $shop = Db::table('fish_shop')->select();
        $order = Db::table('fish_order')->where(['user_id' => $this->user_id])->select();
        foreach ($shop as $k => $v) {
            foreach ($order as $i => $j) {
                if ($v['shop_id'] == $j['shop_id']) {
                    $shop[$k]['status'] = $j['status'];
                }
            }
        }
        $data['shop'] = $shop;
        return jsonResult('商店信息', 200, $data);
    }

    # 兑换话费-生成订单
    public function order()
    {
        $time = date('Y-m-d H:i:s');
        $post = request()->only(['nickname', 'number', 'shop_id'], 'post');
        $shop = Db::table('fish_shop')->field('price,num')->where(['shop_id' => $post['shop_id']])->find();
        $phone_ticket = Db::table('fish_user')->where(['user_id' => $this->user_id])->value('phone_ticket');
        if ($phone_ticket < $shop['num']) {
            return jsonResult('话费券不足', 100);
        }

        $addData = [
            'user_id' => $this->user_id,
            'nickname' => $post['nickname'],
            'number' => $post['number'],
            'shop_id' => $post['shop_id'],
            'order_sn' => getSN(),
            'add_time' => $time,
        ];
        Db::table('fish_order')->insert($addData);
        Db::table('fish_user')->where(['user_id' => $this->user_id])->setDec('phone_ticket', $shop['num']);
        $data['order_sn'] = $addData['order_sn'];

        return jsonResult('订单已审核中', 200, $data);
    }

    # 观看视频
    public function video()
    {
        $type = input('post.type');
        $date = date('Y-m-d');
        $video = Db::table('fish_video')->where(['user_id' => $this->user_id, 'type' => $type, 'add_time' => $date])->count();
        $video_num = Db::table('fish_config')->where(['config_id' => $type])->value('video_num');
        if ($video <= $video_num) {
            $addData = [
                'user_id' => $this->user_id,
                'type' => $type, // 1金币不足 2神秘礼物 3离线收益 4合成
                'add_time' => date('Y-m-d'),
            ];
            Db::table('fish_video')->insert($addData);
            return jsonResult('看视频记录成功', 200);
        }
        return jsonResult('视频次数已达上限', 100);
    }

    # 记录用户观看视频
    public function watchVideo()
    {
//        $type = input('post.type');
        $addData = [
            'user_id' => $this->user_id,
//            'type' => $type,
            'add_time' => date('Y-m-d'),
        ];
        Db::table('fish_watch_video')->insert($addData);
        return jsonResult('记录成功', 200);
    }

    # 双倍金币分享
    public function share()
    {
        if (request()->isPost()) {
            $type = input('post.type');

            if (empty($this->user_id) || empty($type)) {
                return jsonResult('error', 100);
            }

            $data = [
                'user_id' => $this->user_id,
                'type' => $type, // 1金币不足 2神秘礼物 3离线收益 4合成
                'add_time' => date('Y-m-d'),
            ];
            Db::table('fish_share')->insert($data);
            return jsonResult('分享成功', 100);
        }
    }


    # 点击分享
    public function sharedetail()
    {
        $id = input('post.share_id'); // 分享者
        if (empty($id)) return jsonResult('error', 110);
//            dump($res);die;
        // 添加邀请好友记录
        if ($this->user_id != $id) {
            // 添加邀请好友记录
            $invite = Db::table('fish_invite')->where(['share_id' => $this->user_id])->value('invite_id');
            if (empty($invite)) {
                $data = [
                    'user_id' => $this->user_id,
                    'share_id' => $id,
                    'add_time' => date('Y-m-d H:i:s'),
                ];
                Db::table('fish_invite')->insert($data);
                $data = [
                    'user_id' => $id,
                    'share_id' => $this->user_id,
                    'add_time' => date('Y-m-d H:i:s'),
                ];
                Db::table('fish_invite')->insert($data);
                return jsonResult('分享数据记录成功', 200);
            } else {
                return jsonResult('数据已存在', 100);
            }
        }
    }


    // 点击广告送钻石
    public function clickAdv()
    {
        $app_id = input('post.app_id');
        $type = input('post.type');
        $c = 200;
        $time = date('Y-m-d');

        $click = Db::table('fish_clickadv')->where(['user_id' => $this->user_id, 'app_id' => $app_id, 'add_time' => $time])->find();
        if (empty($click)) {
            $add = [
                'user_id' => $this->user_id,
                'app_id' => $app_id,
                'type' => $type,
                'add_time' => date('Y-m-d'),
            ];
            Db::table('fish_clickadv')->insert($add);

            $user_diamond = Db::table('fish_user')->where(['user_id' => $this->user_id])->value('diamond');
            Db::table('fish_user')->where(['user_id' => $this->user_id])->setInc('diamond', $c);

            $data['diamond'] = $user_diamond + $c;
            $data['text'] = '获得' . $c . '钻石';
            return jsonResult('获得' . $c . '钻石', 200, $data);
        } else {
            $data['text'] = '请体验其他游戏';
            return jsonResult('请体验其他游戏', 100, $data);
        }
    }

    // 数据统计
    public function statistics()
    {
        $qd_id = input('post.qd_id');
        $app_id = input('post.app_id');
        $type = input('post.type');
        $enter_id = input('post.enter_id') ? input('post.enter_id') : 0;
        $user_id = $this->user_id;
        $time = date('Y-m-d');

        if (empty($user_id) || empty($type)) return jsonResult('error', 100);
        if ($qd_id) {

            $channel = Db::table('fish_user')->where(['user_id' => $user_id])->value('channel');
            if (empty($channel)) {
                Db::table('fish_user')->where(['user_id' => $user_id])->update(['channel' => $qd_id, 'enter_id' => $enter_id]);
            }
            $qd = $channel ? $channel : $qd_id;

            // 如果用户今天记录过
            $is_qd = Db::table('fish_statistics')->where(['qd_id' => $qd, 'user_id' => $user_id, 'add_time' => $time])->value('statistics_id');
            if ($is_qd) {
                Db::table('fish_statistics')->where(['statistics_id' => $is_qd])->setInc('num');
                return jsonResult('渠道数据已累加');
            } else {
                $userdate = Db::table('fish_user')->where(['user_id' => $user_id])->value('add_time');
                $data = [
                    'qd_id' => $qd,
                    'user_id' => $user_id,
                    'user_date' => $userdate,
                    'type' => $type,
                    'num' => 1,
                    'add_time' => $time,
                    'timestamp' => strtotime(date('Y-m-d H:i:s')),
                ];
                Db::table('fish_statistics')->insert($data);
                return jsonResult('渠道统计成功', 200);
            }
        }

        if ($app_id) {
            $adv_id = Db::table('fish_statistics_adv')->where(['app_id' => $app_id, 'user_id' => $this->user_id, 'add_time' => $time])->value('statistics_id');
            if (empty($adv_id)) {
                $advdata = [
                    'app_id' => $app_id,
                    'user_id' => $user_id,
                    'type' => $type,
                    'num' => 1,
                    'add_time' => date('Y-m-d'),
                    'timestamp' => time(),
                ];
                Db::table('fish_statistics_adv')->insert($advdata);

                return jsonResult('点击广告记录成功', 200);
            } else {
                Db::table('fish_statistics_adv')->where(['statistics_id' => $adv_id])->setInc('num');
                return jsonResult('点击广告数据累加', 200);
            }
        }
    }

    #用户行为
    public function record()
    {
        $text = input('post.text');
        $data = [
            'user_id' => $this->user_id,
            'text' => $text,
        ];
        Db::table('fish_record')->insert($data);
        return jsonResult('记录成功', 200);
    }

    # 更多好玩
    public function appMore()
    {
        $switch = Db::table('fish_switch')->find();
        $data['switch'] = $switch;

        if ($switch['more_status'] == 0) {
            $app = Db::table('fish_app')->field('id,app_id,app_name,app_url,page')->where(['status' => 0])->order('sort')->limit(9)->select();
            $data['app'] = $app;
        } else {
            $app = Db::table('fish_app')->field('id,app_id,app_name,app_url,page')->where(['app_name' => '智力问答题'])->find();
            $data['app'] = $app;
        }

//        $top = Db::table('fish_app')->field('id,app_id,app_name,app_url,page')->where(['top_status' => 0])->limit(3)->order('top_sort')->select();
//        $data['top'] = $top;

        $play = Db::table('fish_app')->field('id,app_id,app_name,app_url,page')->where(['play_status' => 0])->limit(4)->order('play_sort')->select();
        $data['play'] = $play;

        return jsonResult('更多好玩', 200, $data);
    }

    public function aaa()
    {
//        echo lock_url('og56G5DIy6RbC42-Ckm5zvc1de7s,381');
//        $superFish = Db::table('fish_superfish')->find();
//        dump($superFish);

        $a = 100;    //鲶鱼的成长值
        $b = 0;     // 初始值
        for ($i = 1; $i <= 100; $i++) {
            $b += 10 * pow(2, $i - 1);
            if ($a <= $b) {
                dump($i);
                break;
            }
        }
        $d = $b - $a;
        dump($b);
        dump($d);


    }

}