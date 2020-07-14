<?php
/**
 * 记忆夺宝
 * User: Sz105
 * Date: 2018/3/20
 * Time: 14:11
 */

namespace app\index\controller;
class Home extends Conmmon
{

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

    # 首页
    public function index()
    {
        # 分享文案
        $share = db('share_pic')->find();
        $data['share'] = $share;
        # 首页用户信息
        $user = db('user')->field('user_id,user_name,all_coin,coin,rune,pass,tank_id,ball_id,tg_count,status')->where(['user_id' => $this->user_id])->find();
        $user['tx_price'] = 10; // 提现限额
//        $user['sj_coin'] = $user['pet_coin']; // 宠物升级所需金币
        $share_num = db('share')->where(['user_id' => $this->user_id, 'add_time' => date('Y-m-d')])->count();
        $user['share_num'] = 10 - $share_num;

        $data['config'] = db('system_config')->find();

//        dump($data);
        $data['userInfo'] = $user;
        return jsonResult('succ', 200, $data);
    }

    # 武器库
    public function tank()
    {
        $type = input('post.type'); // 1 坦克 2 炮弹

        if ($type == 1) {
            $tank = db('tank')->select();
            $user_tank = db('user_tank')->where(['user_id' => $this->user_id])->select();
//            dump($user_tank);die;
            $data = [];
            foreach ($tank as $k => $v) {
                # 当前属性
                $lv = empty($j['tank_lv']) ? 1 : $j['tank_lv'];
                $data[$k]['tank_id'] = $v['tank_id'];
                $data[$k]['tank_name'] = $v['tank_name'];
                $data[$k]['tank_lv'] = $lv;

                $data[$k]['tank_harm1'] = $v['tank_harm'] + ($lv - 1) * 20;
                $data[$k]['tank_harm2'] = $v['tank_harm'] + ($lv - 1) * 20 + 100;
                $data[$k]['tank_real_harm'] = strval($v['tank_real_harm'] + $lv * 0.01);
                $data[$k]['tank_real_boss_harm'] = strval($v['tank_real_boss_harm'] + $lv * 0.01);
                $data[$k]['tank_ss'] = strval($v['tank_ss'] + ($lv - 1) * 0.1);
                $data[$k]['tank_up_coin'] = floor($v['tank_up_coin'] * pow(1.2, $lv - 1));
                $data[$k]['tank_unlock'] = $v['tank_unlock'];

                # 升级后属性
                $data[$k]['sj_tank_harm1'] = $v['tank_harm'] + $lv * 20;
                $data[$k]['sj_tank_harm2'] = $v['tank_harm'] + ($lv * 20) + 100;
                $data[$k]['sj_tank_ss'] = strval($v['tank_ss'] + $lv * 0.1);
                $data[$k]['status'] = $v['status'];
                foreach ($user_tank as $i => $j) {

                    if ($v['tank_id'] == $j['tank_id']) {
                        # 当前属性
                        $lv = empty($j['tank_lv']) ? 1 : $j['tank_lv'];
                        $data[$k]['tank_id'] = $v['tank_id'];
                        $data[$k]['tank_name'] = $v['tank_name'];
                        $data[$k]['tank_lv'] = $lv;
                        $data[$k]['tank_lv'] = $j['tank_lv'];

                        $data[$k]['tank_harm1'] = $v['tank_harm'] + ($lv - 1) * 20;
                        $data[$k]['tank_harm2'] = $v['tank_harm'] + ($lv - 1) * 20 + 100;
                        $data[$k]['tank_real_harm'] = strval($v['tank_real_harm'] + $lv * 0.01);
                        $data[$k]['tank_real_boss_harm'] = strval($v['tank_real_boss_harm'] + $lv * 0.01);
                        $data[$k]['tank_ss'] = strval($v['tank_ss'] + ($lv - 1) * 0.1);
                        $data[$k]['tank_up_coin'] = floor($v['tank_up_coin'] * pow(1.2, $lv - 1));
                        $data[$k]['tank_unlock'] = $v['tank_unlock'];

                        # 升级后属性
                        $data[$k]['sj_tank_harm1'] = $v['tank_harm'] + $lv * 20;
                        $data[$k]['sj_tank_harm2'] = $v['tank_harm'] + ($lv * 20) + 100;
                        $data[$k]['sj_tank_ss'] = strval($v['tank_ss'] + $lv * 0.1);
                        $data[$k]['status'] = 1;
                    }
                }
            }
            return jsonResult('人物详情', 200, $data);

        } else if ($type == 2) {

            $ball = db('ball')->select();
            $user_ball = db('user_ball')->where(['user_id' => $this->user_id])->select();
            $data = [];
            foreach ($ball as $k => $v) {
                # 当前属性
                $lv = empty($j['ball_lv']) ? 1 : $j['ball_lv'];
                $data[$k]['ball_id'] = $v['ball_id'];
                $data[$k]['ball_name'] = $v['ball_name'];
                $data[$k]['ball_attribute'] = $v['ball_attribute'];
                $data[$k]['ball_lv'] = 1;

                $data[$k]['ball_harm1'] = $v['ball_harm'] + ($lv - 1) * 20;
                $data[$k]['ball_harm2'] = $v['ball_harm'] + ($lv - 1) * 20 + 100;
//                        $data[$k]['ball_real_harm'] = strval($v['ball_real_harm'] + $lv * 0.01);
//                        $data[$k]['ball_real_boss_harm'] = strval($v['ball_real_boss_harm'] + $lv * 0.01);
                $data[$k]['ball_speed'] = strval($v['ball_speed'] + ($lv - 1) * 0.1);
                $data[$k]['ball_up_rune'] = floor($v['ball_up_rune'] * pow(1.2, $lv - 1));
                $data[$k]['ball_unlock'] = $v['ball_unlock'];

                # 升级后属性
                $data[$k]['sj_ball_harm1'] = $v['ball_harm'] + $lv * 20;
                $data[$k]['sj_ball_harm2'] = $v['ball_harm'] + ($lv * 20) + 100;
                $data[$k]['sj_ball_speed'] = strval($v['ball_speed'] + $lv * 0.1);
                $data[$k]['status'] = $v['status'];

                foreach ($user_ball as $i => $j) {
                    if ($v['ball_id'] == $j['ball_id']) {

                        # 当前属性
                        $lv = empty($j['ball_lv']) ? 1 : $j['ball_lv'];
                        $data[$k]['ball_lv'] = $lv;

                        $data[$k]['ball_harm1'] = $v['ball_harm'] + ($lv - 1) * 20;
                        $data[$k]['ball_harm2'] = $v['ball_harm'] + ($lv - 1) * 20 + 100;
//                        $data[$k]['ball_real_harm'] = strval($v['ball_real_harm'] + $lv * 0.01);
//                        $data[$k]['ball_real_boss_harm'] = strval($v['ball_real_boss_harm'] + $lv * 0.01);
                        $data[$k]['ball_speed'] = strval($v['ball_speed'] + ($lv - 1) * 0.1);
                        $data[$k]['ball_up_rune'] = floor($v['ball_up_rune'] * pow(1.2, $lv - 1));
                        $data[$k]['ball_unlock'] = $v['ball_unlock'];

                        # 升级后属性
                        $data[$k]['sj_ball_harm1'] = $v['ball_harm'] + $lv * 20;
                        $data[$k]['sj_ball_harm2'] = $v['ball_harm'] + ($lv * 20) + 100;
                        $data[$k]['sj_ball_speed'] = strval($v['ball_speed'] + $lv * 0.1);
                        $data[$k]['status'] = 1;
                    }
                }
            }
            return jsonResult('炮弹详情', 200, $data);
        }
    }

    # 挑战记录
    public function challenge1()
    {
        $post = request()->only(['pass', 'coin', 'rune'], 'post');
        $post['user_id'] = $this->user_id;
        $post['add_time'] = date('Y-m-d H:i:s');


        $user = db('user')->field('coin,rune,pass,tg_count')->where(['user_id' => $this->user_id])->find();
        $pass = $post['pass'] - 1 > $user['pass'] ? $post['pass'] - 1 : $user['pass'];
        $updata = [
            'coin' => $user['coin'] + $post['coin'],
            'rune' => $user['rune'] + $post['rune'],
            'pass' => $pass,
//            'tg_count' => $post['type'] == 1 ? $user['tg_count'] + 1 : $user['tg_count'],
        ];
        db('user')->where(['user_id' => $this->user_id])->update($updata);
        $res = db('challenge')->insert($post);

        if ($res) {
            return jsonResult('挑战记录成功', 200);
        }
    }

    # 购买坦克
    public function payTank()
    {
        $tank_id = input('post.tank_id');
        if (empty($tank_id)) return jsonResult('error', 100);

        $tank = db('user_tank')->where(['user_id' => $this->user_id, 'tank_id' => $tank_id])->value('user_tank_id');
        if ($tank) {
            return jsonResult('已购买过', 100);
        }

        $user_coin = db('user')->where(['user_id' => $this->user_id])->value('coin');
        $tank_coin = db('tank')->where(['tank_id' => $tank_id])->value('tank_unlock');
        if ($user_coin < $tank_coin) {
            return jsonResult('金币不足', 100);
        }

        $addData = [
            'user_id' => $this->user_id,
            'tank_id' => $tank_id,
            'tank_lv' => 1,
            'add_time' => date('Y-m-d H:i:s'),
        ];
        db('user_tank')->insert($addData);
        db('user')->where(['user_id' => $this->user_id])->setDec('coin', $tank_coin);
        $res['coin'] = $user_coin - $tank_coin;
        return jsonResult('购买成功', 200);
    }

    # 升级坦克
    public function upTank()
    {
        $tank_id = input('post.tank_id');
        if (empty($tank_id)) return jsonResult('error', 100);
        $user_coin = db('user')->where(['user_id' => $this->user_id])->value('coin');
        $tank = db('user_tank')
            ->alias('ut')
            ->join('tank t', 'ut.tank_id = t.tank_id')
            ->field('ut.tank_lv,t.tank_up_coin')
            ->where(['ut.user_id' => $this->user_id, 'ut.tank_id' => $tank_id])
            ->find();
        if (empty($tank)) {
            return jsonResult('请先购买坦克', 100);
        }
//        $tank_sj_coin = $tank['tank_lv'] * $tank['tank_up_coin']; // $v['tank_up_coin'] * pow(1.2, $lv - 1);
        $tank_up_coin = floor($tank['tank_up_coin'] * pow(1.2, $tank['tank_lv'] - 1));

//        dump($tank_up_coin);
//        die;

        if ($tank['tank_lv'] >= 50) {
            return jsonResult('等级已达上限', 100);
        }

        if ($user_coin < $tank_up_coin) {
            return jsonResult('金币不足', 100);
        }

        db('user_tank')->where(['user_id' => $this->user_id, 'tank_id' => $tank_id])->setInc('tank_lv');
        db('user')->where(['user_id' => $this->user_id])->setDec('coin', $tank_up_coin);
        $res['coin'] = $user_coin - $tank_up_coin;
        return jsonResult('升级成功', 200);
    }

    # 购买炮弹
    public function payBall()
    {
        $ball_id = input('post.ball_id');
        if (empty($ball_id)) return jsonResult('error', 100);

        $ball = db('user_ball')->where(['user_id' => $this->user_id, 'ball_id' => $ball_id])->value('user_ball_id');
        if ($ball) {
            return jsonResult('已购买过', 100);
        }

        $user_rune = db('user')->where(['user_id' => $this->user_id])->value('rune');
        $ball_rune = db('ball')->where(['ball_id' => $ball_id])->value('ball_unlock');
        if ($user_rune < $ball_rune) {
            return jsonResult('符文不足', 100);
        }

        $addData = [
            'user_id' => $this->user_id,
            'ball_id' => $ball_id,
            'ball_lv' => 1,
            'add_time' => date('Y-m-d H:i:s'),
        ];
        db('user_ball')->insert($addData);
        db('user')->where(['user_id' => $this->user_id])->setDec('rune', $ball_rune);
        $res['coin'] = $user_rune - $ball_rune;
        return jsonResult('购买成功', 200);
    }

    # 升级炮弹
    public function upBall()
    {
        $ball_id = input('post.ball_id');
        if (empty($ball_id)) return jsonResult('error', 100);
        $user_rune = db('user')->where(['user_id' => $this->user_id])->value('rune');
        $ball = db('user_ball')
            ->alias('ub')
            ->join('ball b', 'ub.ball_id = b.ball_id')
            ->field('ub.ball_lv,b.ball_up_rune')
            ->where(['ub.user_id' => $this->user_id, 'ub.ball_id' => $ball_id])
            ->find();
//        $ball_up_rune = $ball['ball_lv'] * $ball['ball_up_rune'];
        $ball_up_rune = $ball['ball_up_rune'] * pow(1.2, $ball['ball_lv'] - 1);;


        if ($ball['ball_lv'] >= 50) {
            return jsonResult('等级已达上限', 100);
        }

//        dump($tank_sj_coin);die;
        if ($user_rune < $ball_up_rune) {
            return jsonResult('符文不足', 100);
        }

        db('user_ball')->where(['user_id' => $this->user_id, 'ball_id' => $ball_id])->setInc('ball_lv');
        db('user')->where(['user_id' => $this->user_id])->setDec('rune', $ball_up_rune);
        $res['coin'] = $user_rune - $ball_up_rune;
        return jsonResult('升级成功', 200);
    }

    #使用坦克
    public function useTank()
    {
        $tank_id = input('post.tank_id');

        $user_tank = db('user_tank')->where(['user_id' => $this->user_id, 'tank_id' => $tank_id])->find();
        if (empty($user_tank)) {
            return jsonResult('请先购买此坦克', 100);
        }

        db('user')->where(['user_id' => $this->user_id])->update(['tank_id' => $tank_id]);
        return jsonResult('使用成功', 200);
    }

    #使用炮弹
    public function useBall()
    {
        $ball_id = input('post.ball_id');

        $user_tank = db('user_ball')->where(['user_id' => $this->user_id, 'ball_id' => $ball_id])->find();
        if (empty($user_tank)) {
            return jsonResult('请先购买此炮弹', 100);
        }

        db('user')->where(['user_id' => $this->user_id])->update(['ball_id' => $ball_id]);
        return jsonResult('使用成功', 200);
    }

    # 挑战记录
    public function challenge()
    {
        $post = request()->only(['pass', 'coin', 'rune'], 'post');
//        $post = request()->only(['pass', 'coin', 'rune', 'type'], 'post');
        $type = input('post.type'); // 0 失败 1 成功
        $post['user_id'] = $this->user_id;
        $post['add_time'] = date('Y-m-d H:i:s');

        $user = db('user')->field('coin,rune,pass,tg_count,tg_all_count')->where(['user_id' => $this->user_id])->find();
        if ($type == 0) {
            $pass = $post['pass'] - 1 > $user['pass'] ? $post['pass'] - 1 : $user['pass'];
        } elseif ($type == 1) {
            $pass = $post['pass'];
        }
        $updata = [
            'coin' => $user['coin'] + $post['coin'],
            'rune' => $user['rune'] + $post['rune'],
            'pass' => $pass,
            'tg_count' => $type == 1 ? $user['tg_count'] + 1 : $user['tg_count'],
            'tg_all_count' => $type == 1 ? $user['tg_all_count'] + 1 : $user['tg_all_count'],
        ];
        db('user')->where(['user_id' => $this->user_id])->update($updata);

        if ($type == 1) {
            $addData = [
                'user_id' => $this->user_id,
                'tg_time' => date('Y-m-d H:i:s'),
                'is_dh' => 0,
            ];
            db('order')->insert($addData);
        }

        $res = db('challenge')->insert($post);
        if ($res) {
            return jsonResult('挑战记录成功', 200);
        }
    }

    # 兑换商城
    public function shop()
    {
        $shop = db('shop')->select();
        return jsonResult('兑换商城', 200, $shop);
    }

    // 排行榜
    public function paihang()
    {
        $paihang = db('user')->field('user_id,user_name,avatar,tg_all_count')->where(['tg_all_count' => ['>', 0]])->order('tg_all_count desc')->limit(10)->select();
        return jsonResult('排行榜', 200, $paihang);
    }

    # 兑换订单记录
    public function order()
    {
        $post = Request()->only(['nickname', 'address', 'number'], 'post');

//        dump(111);die;
        if (empty($post)) {
            return jsonResult('error', 100);
        }

        $tg = db('order')->where(['user_id' => $this->user_id, 'is_dh' => 0])->value('order_id');
        if (empty($tg)) {
            return jsonResult('您的通关次数未达到兑换条件', 100);
        }

        $order_sn = db('order')->where(['user_id' => $this->user_id, 'status' => 0])->value('order_sn');
        if (!empty($order_sn)) {
            $res['order_sn'] = $order_sn;
            return jsonResult('重复申请', 200, $res);
        }

        $addData = [
            'user_id' => $this->user_id,
            'nickname' => $post['nickname'],
            'number' => $post['number'],
            'address' => $post['address'],
            'order_sn' => getSN(),
            'add_time' => date('Y-m-d H:i:s'),
            'status' => 0,
        ];
        $id = db('order')->insert($addData);
        if ($id) {
//            db('user')->where(['user_id' => $this->user_id])->setDec('tg_count');
            $upData = [
                'is_dh' => 1,
                'order_id' => $addData['order_sn'],
                'dh_time' => $addData['add_time'],
            ];
            db('tg')->where(['user_id' => $this->user_id])->update($upData);
            $res['order_sn'] = $addData['order_sn'];
            return jsonResult('提交成功', 200, $res);
        } else {
            return jsonResult('提交失败', 200);
        }
    }

    # 更多好玩
    public function appMore1()
    {
        $type = input('post.type');
        $switch = db('switch')->find();
        $data['status'] = $switch;
        $data['more_status'] = $switch['more_status'];
        $data['play_status'] = $switch['play_status'];

        if ($type == 1) {
            // 试玩一下
            $more = db('app')->field('id,app_id,app_name,app_url,page')->where(['play_status' => 0])->limit(6)->order('play_sort')->select();

//            dump($more);die;
            $adv = db('clickadv')->where(['user_id' => $this->user_id, 'add_time' => date('Y-m-d')])->select();
            foreach ($more as $k => $v) {
                $more[$k]['status'] = 0;
                foreach ($adv as $i => $j) {
                    if ($v['id'] == $j['app_id']) {
                        $more[$k]['status'] = 1;
                    }
                }
            }
            $data['more'] = $more;

        } else {
            // 侧拉广告
            if ($switch['more_status'] == 0) {
                $data['more'] = db('app')->field('id,app_id,app_name,app_text,app_url,page')->where(['status' => 0])->order('sort')->limit(9)->select();
            } else {
                $data['more'] = db('app')->field('id,app_id,app_name,app_url,page')->where(['more_status' => 0])->find();
            }

        }

//        dump($data);
        return jsonResult('更多好玩', 200, $data);
    }

    # 更多好玩
    public function appMore()
    {
        $type = input('post.type');
        $switch = db('switch')->find();
        $data['status'] = $switch;
        $data['more_status'] = $switch['more_status'];
        $data['play_status'] = $switch['play_status'];

        if ($type == 1) {
            // 试玩一下
            $more = db('app')->field('id,app_id,app_name,app_url,page')->where(['play_status' => 0])->limit(6)->order('play_sort')->select();

//            dump($more);die;
            $adv = db('clickadv')->where(['user_id' => $this->user_id, 'type' => 2, 'add_time' => date('Y-m-d')])->select();
            foreach ($more as $k => $v) {
                $more[$k]['status'] = 0;
                foreach ($adv as $i => $j) {
                    if ($v['id'] == $j['app_id']) {
                        $more[$k]['status'] = 1;
                    }
                }
            }
            $data['more'] = $more;

        } else if ($type == 2) {
            // 侧拉广告
            if ($switch['more_status'] == 0) {
                $more = db('app')->field('id,app_id,app_name,app_text,app_url,page')->where(['status' => 0])->order('sort')->limit(9)->select();
                $adv = db('clickadv')->where(['user_id' => $this->user_id, 'type' => 2, 'add_time' => date('Y-m-d')])->select();
                foreach ($more as $k => $v) {
                    $more[$k]['status'] = 0;
                    foreach ($adv as $i => $j) {
                        if ($v['id'] == $j['app_id']) {
                            $more[$k]['status'] = 1;
                        }
                    }
                }
                $data['more'] = $more;
            } else {
                $data['more'] = db('app')->field('id,app_id,app_name,app_url,page')->where(['more_status' => 0])->find();
            }

        } else if ($type == 3) {
            $data['more'] = db('app')->field('id,app_id,app_name,app_url,page')->where(['banner_status' => 0])->select();
        }

//        dump($data);
        return jsonResult('更多好玩', 200, $data);
    }

    // 点击广告送金币
    public function clickAdv()
    {
//        $type = input('post.type');
//        $c = $type == 2 ? 1500 : 1000;
        $c = 1000;

        $time = date('Y-m-d');
        $app_id = input('post.app_id');

        $click = db('clickadv')->where(['user_id' => $this->user_id, 'app_id' => $app_id, 'add_time' => $time])->find();
        if (empty($click)) {
            $add = [
                'user_id' => $this->user_id,
                'app_id' => $app_id,
//                'type' => $type,
                'type' => 0,
                'add_time' => date('Y-m-d'),
            ];
            db('clickadv')->insert($add);

            $user_coin = db('user')->where(['user_id' => $this->user_id])->value('coin');
            db('user')->where(['user_id' => $this->user_id])->setInc('coin', $c);

            $data['coin'] = $user_coin + $c;
            $data['coin_text'] = '获得' . $c . '金币';
            return jsonResult('获得' . $c . '金币', 200, $data);
        } else {
            $data['coin_text'] = '请体验其他游戏';
            return jsonResult('请体验其他游戏', 100, $data);
        }
    }

    // 跟踪用户行为
    public function record()
    {
        $text = input('post.text');
        $data = [
            'user_id' => $this->user_id,
            'text' => $text,
            'add_time' => date('Y-m-d'),
            'timestamp' => time(),
        ];
        db('record')->insert($data);
        return jsonResult('记录成功', 200);
    }

    # 开始挑战
    public function start()
    {
        $tili = db('user')->where(['user_id' => $this->user_id])->value('power');
        if ($tili < 5) {
            return jsonResult('体力不足', 100);
        }

        return jsonResult('开始挑战', 200);
    }

    # 每日签到
    public function checkSign()
    {
        // 判断今日是否签到
        $date = date('Y-m-d');
        $qd = db('sign')->where(['user_id' => $this->user_id, 'add_time' => $date])->column('sign_id');
        if (empty($qd)) {
            $data = [
                'user_id' => $this->user_id,
                'add_time' => date('Y-m-d'),
            ];
            db('sign')->insert($data);

            // 赠送体力药水
            db('user')->where(['user_id' => $this->user_id])->setInc('power_medicine');

            return jsonResult('签到成功', 200);
        } else {
            return jsonResult('今日已签到', 100);
        }
    }

    # 分享+金币
    public function addtilishare()
    {
        if (request()->isPost()) {
            $user_id = input('post.user_id'); // 分享者ID
            $num = input('post.num'); // 分享者ID
            $text = input('post.text');
            $c = 5;

            if (empty($user_id)) return jsonResult('参数为空');

            $data = [
                'user_id' => $this->user_id,
                'num' => $num,
                'shares' => '',
                'text' => $text,
                'status' => 1,
                'add_time' => date('Y-m-d H:i:s'),
            ];
            db('share')->insert($data);

            $stime = date('Y-m-d 00:00:00');
            $share = db('share')->where(['user_id' => $this->user_id, 'status' => 1, 'add_time' => ['>=', $stime]])->select();
            if (count($share) <= 5) {

                // 累积获得金币
                $user = db('user')->field('tili,total')->where(['user_id' => $this->user_id])->find();
                $updata = [
                    'tili' => $user['tili'] + $c,
                    'total' => $user['total'] + $c,
                ];
                db('user')->where(['user_id' => $this->user_id])->update($updata);

                // 记录用户获得金币
                $gain = [
                    'user_id' => $this->user_id,
                    'text' => '每日分享',
                    'tili' => '+' . $c,
                    'add_time' => date('Y-m-d H:i:s')
                ];
                db('gain')->insert($gain);

                $tili = db('user')->field('tili')->where(['user_id' => $this->user_id])->find();
                return jsonResult('今天第' . count($share) . '次分享,获得' . $c . '金币', 200, $tili);
            } else {
                return jsonResult(config('adv.YQ_TEXT'), 100);
            }
        }
    }

    # 分享
    public function share()
    {
        if (request()->isPost()) {
            $num = input('post.num');

            if (empty($this->user_id) || empty($num)) {
                return jsonResult('参数为空', 100);
            }

            $count = db('share')->where(['user_id' => $this->user_id, 'add_time' => date('Y-m-d')])->count();
            if ($count <= 10) {
                $data = [
                    'user_id' => $this->user_id,
                    'num' => $num,
                    'shares' => '',
                    'text' => '',
                    'status' => 0,
                    'add_time' => date('Y-m-d'),
                    'timestamp' => time(),
                ];
                db('share')->insert($data);
                return jsonResult('分享成功', 200, $num);
            } else {
                return jsonResult('分享失败', 100);
            }
        }
    }

    # 点击分享
    public function sharedetail()
    {
        if (request()->isPost()) {
            $id = input('post.share_id'); // 分享者
//            $this->user_id = 1;
//            $num = input('post.num');
            // 添加邀请好友记录
            $invite = db('invite')->field('invite_id')->where(['user_id' => $id])->whereOr(['share_id' => $id])->find();
            if (empty($invite)) {
                $data = [
                    'user_id' => $this->user_id,
                    'share_id' => $id,
                    'add_time' => date('Y-m-d H:i:s'),
                ];
                db('invite')->insert($data);
                $data1 = [
                    'user_id' => $id,
                    'share_id' => $this->user_id,
                    'add_time' => date('Y-m-d H:i:s'),
                ];
                db('invite')->insert($data1);
                return jsonResult('点击分享进入', 200);

            } else {
                return jsonResult('数据已存在', 100);
            }
        }
    }

    # 点击分享
    public function sharedetail1()
    {
        $id = input('post.share_id'); // 分享者
        $num = input('post.num');
        $c = config('adv.YQ_COIN');

        $b['id'] = $id;
        $b['num'] = $num;

        $res = db('share')->field('share_id,shares,add_time')->where(['user_id' => $id, 'num' => $num])->find();
        if (empty($res) || ($this->user_id == $id)) {
            return jsonResult('数据不存在', 100, $b);
        }

        // 添加邀请好友记录
        $invite = db('invite')->field('invite_id')->where(['user_id' => $this->user_id])->whereOr(['share_id' => $this->user_id])->find();
        if (empty($invite)) {
            $data = [
                'user_id' => $this->user_id,
                'share_id' => $id,
                'add_time' => date('Y-m-d H:i:s'),
            ];
            db('invite')->insert($data);
            $data1 = [
                'user_id' => $id,
                'share_id' => $this->user_id,
                'add_time' => date('Y-m-d H:i:s'),
            ];
            db('invite')->insert($data1);

            $share = explode(',', $res['shares']);
            if (!in_array($this->user_id, $share)) {
                if (empty($res['shares'])) {
                    $str = $this->user_id;
                } else {
                    $share[] = $this->user_id;
                    $str = join(',', $share);
                }
                db('share')->where(['share_id' => $res['share_id']])->update(['shares' => $str]);
            }

            // 累积获得金币
            db('user')->where(['user_id' => $id])->setInc('coin', $c);

            return jsonResult('点击分享进入', 200);

        } else {
            return jsonResult('数据已存在', 100);
        }
    }

    // 数据统计
    public function statistics()
    {
        $qd_id = input('post.qd_id');
        $app_id = input('post.app_id');
        $type = input('post.type');
        $user_id = $this->user_id;
        $time = date('Y-m-d');

        if (empty($user_id) || empty($type)) return jsonResult('error', 100);
        if ($qd_id) {
//            if ($qd_id > 2000 || $qd_id < 99) {
//                $qd_id = 666;
//            }

            $channel = db('user')->where(['user_id' => $user_id])->value('channel');
            if (empty($channel)) {
                db('user')->where(['user_id' => $user_id])->update(['channel' => $qd_id]);
            }
            $qd = $channel ? $channel : $qd_id;

            // 如果用户今天记录过
            $is_qd = db('statistics')->where(['qd_id' => $qd, 'user_id' => $user_id, 'add_time' => $time])->value('statistics_id');
            if ($is_qd) {
                db('statistics')->where(['statistics_id' => $is_qd])->setInc('num');
                return jsonResult('渠道数据已累加');
            } else {
                $userdate = db('user')->where(['user_id' => $user_id])->value('add_time');
                $data = [
                    'qd_id' => $qd,
                    'user_id' => $user_id,
                    'user_date' => $userdate,
                    'type' => $type,
                    'num' => 1,
                    'add_time' => $time,
                    'timestamp' => strtotime(date('Y-m-d H:i:s')),
                ];
                db('statistics')->insert($data);
                return jsonResult('渠道统计成功');
            }
        }

        if ($app_id) {
            $adv_id = db('statistics_adv')->where(['app_id' => $app_id, 'user_id' => $this->user_id, 'add_time' => $time])->value('statistics_id');
            if (empty($adv_id)) {
                $advdata = [
                    'app_id' => $app_id,
                    'user_id' => $user_id,
                    'type' => $type,
                    'num' => 1,
                    'add_time' => date('Y-m-d'),
                    'timestamp' => time(),
                ];
                db('statistics_adv')->insert($advdata);

                return jsonResult('点击广告记录成功', 200);
            } else {
                db('statistics_adv')->where(['statistics_id' => $adv_id])->setInc('num');
                return jsonResult('点击广告数据累加', 200);
            }
        }
    }

    # 娃娃通知
    public function message()
    {
        $message = db('message')->order('rand()')->limit(30)->select();
        foreach ($message as $k => $v) {
            $message[$k]['user_name'] = func_substr_replace($v['user_name']);
        }
//        dump($message);
        return jsonResult('娃娃通知', 200, $message);
    }

    # 关联APP
    public function app()
    {
        $data = db('app')->where(['app_name' => '趣玩盒子'])->find();
        return jsonResult('请求成功', 200, $data);
    }

    # 游戏规则
    public function rule()
    {
        $rule = db('rule')->select();
        return jsonResult('游戏规则', 200, $rule);
    }


    public function test()
    {
        db('record')->where(['add_time' => ['<=', '2019-02-20']])->delete();
    }

}