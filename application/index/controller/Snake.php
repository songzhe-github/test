<?php
/**
 * Created by PhpStorm.
 * User: SongZhe
 * Date: 2018/12/5
 * Time: 15:15
 */

namespace app\index\controller;

use think\Db;
use think\queue\job\Database;

class Snake extends Conmmon
{
    # 获取code，返回openid（超级鲶鱼）
    public function getSnakeCode()
    {
        if (request()->isPost()) {
            $code = input('post.code');
            if (empty($code)) return jsonResult('你瞅啥', 100);

            $data = $this->getSnakeOpenid($code); // 获得 openid 和 session_key
//            return jsonResult('succ',200,$data);
            $user = Db::table('snake_user')->field('user_id,openid')->where(['openid' => $data['openid']])->find();

            if (empty($user)) {
                $arr = [
                    'openid' => $data['openid'],
                    'user_name' => '',
                    'coin' => 1000,
                    'avatar' => 'https://yxdrs.zsmgc.com.cn/A/public/app/wdl.png',
                    'add_time' => date('Y-m-d'),
                    'end_time' => date('Y-m-d H:i:s'),
                ];
                $uid = Db::table('snake_user')->insertGetId($arr);
                $res['mstr'] = lock_url($data['openid'] . ',' . $uid);
                $res['new_player'] = 1;
                return jsonResult('请求成功', 200, $res);
            } else {
                Db::table('snake_user')->where(['user_id' => $user['user_id']])->update(['end_time' => date('Y-m-d H::s')]);
                $res['mstr'] = lock_url($data['openid'] . ',' . $user['user_id']);
                $res['new_player'] = 0;
                return jsonResult('请求成功', 200, $res);
            }
        }
    }

    # 获取用户信息
    public function getSnakeUserInfo()
    {
        $user_name = input('post.user_name');
        $avatar = input('post.avatar');

        $user = Db::table('snake_user')->where(['user_id' => $this->user_id])->value('user_id');
        if (empty($user)) return jsonResult('数据不存在', 100);

        $updata = [
            'user_name' => empty($user_name) ? '' : $user_name,
            'avatar' => empty($avatar) ? '' : $avatar,
            'sex' => empty($sex) ? 0 : $sex,
            'city' => empty($city) ? '' : $city,
            'end_time' => date('Y-m-d H:i:s'),
        ];
        Db::table('snake_user')->where(['user_id' => $this->user_id])->update($updata);
        return jsonResult('用户信息', 200);
    }

    # 首页
    public function index()
    {
        $date = date('Y-m-d');

        # 分享文案
        $share = Db::table('snake_share_pic')->order('rand()')->find();
        $data['share'] = $share;

        # 首页用户信息
        $user = Db::table('snake_user')
            ->field('user_id,coin,diamond,offline_fruits,pay_fruits,map_id,unlock_map,snake_lv,snake_growth_value,max_fruits_id,clothing_id')
            ->where(['user_id' => $this->user_id])
            ->find();
        $pay_fruits = json_decode($user['pay_fruits'], true);
        $offline_fruits = json_decode($user['offline_fruits'], true);
        $unlock_map = json_decode($user['unlock_map'], true);
        $user['pay_fruits'] = empty($pay_fruits) ? [] : $pay_fruits;
        $user['offline_fruits'] = empty($offline_fruits) ? [] : $offline_fruits;
        $user['unlock_map'] = empty($unlock_map) ? 1 : $unlock_map;
        $user['coin'] = intval($user['coin']);
        $data['userInfo'] = $user;


        $snakes = Db::table('snake_fruits')->select();
        foreach ($snakes as &$v) {
            $v['fruit_up'] = intval($v['fruit_up']);
            $v['fruit_up_coin'] = intval($v['fruit_up_coin']);
        }
        $data['config'] = $snakes;
        $config = Db::table('snake_config')->field('earnings_second,compound_give_rate')->find();
        $data['earnings_second'] = $config['earnings_second']; // 蛇的生产收益（除以）
        $data['compound_give_rate'] = $config['compound_give_rate']; // 弹窗给金币

        $data['is_qd'] = empty($is_qd) ? 0 : 1;
        return jsonResult('succ', 200, $data);
    }

    # 首页
    public function home()
    {
        # 分享文案
        $rand = rand(1, 3);
        $share = Db::table('snake_share_pic')->where(['sharepic_id' => $rand])->find();
        $data['share'] = $share;

        # 首页用户信息
        $user = Db::table('snake_user')
            ->field('user_id,coin,diamond,offline_fruits,pay_fruits,snake_lv,snake_growth_value,max_fruits_id,clothing_id')
            ->where(['user_id' => $this->user_id])
            ->find();
        $pay_fruits = json_decode($user['pay_fruits'], true);
        $offline_fruits = json_decode($user['offline_fruits'], true);
        $user['pay_fruits'] = empty($pay_fruits) ? [] : $pay_fruits;
        $user['offline_fruits'] = empty($offline_fruits) ? [] : $offline_fruits;
        $user['coin'] = intval($user['coin']);
        $data['userInfo'] = $user;

        # 水果购买所需金币
        $fruit = Db::table('snake_fruits_new')->select();
        foreach ($fruit as &$v) {
            $v['fruit_up'] = intval($v['fruit_up']);
        }
        $data['fruit'] = $fruit;

        # 蛇的收益和购买所需金币
        $snake = Db::table('snake_snake')->select();
        $data['snake'] = $snake;

        $config = Db::table('snake_config')->field('earnings_second,compound_give_rate')->find();
        $data['earnings_second'] = $config['earnings_second']; // 蛇的生产收益（除以）
        $data['compound_give_rate'] = $config['compound_give_rate']; // 弹窗给金币
        $data['is_qd'] = empty($is_qd) ? 0 : 1;

        $arr = [
            'clothing_id' => '服饰ID 默认：0',
            'fruit' => '水果配置信息',
            'fruit_up' => '初始值',
            'fruit_up_xs' => '系数',
            'fruit_growth_value' => '成长值',
            'snake_lv' => '等级',
            'snake_growth_value' => '升级所需成长值',
            'snake_second_earnings' => '蛇每秒产生的收益',
            'earnings_second' => '蛇的生产收益的倍数',
            'compound_give_rate' => '给金币倍数',
            '蛇产生收益公式' => 'snake_second_earnings * earnings_second (把除以改为乘以)',
            '购买水果所需金币公式' => 'fruit_up * Math.pow(fruit_up_xs, 购买次数)',
        ];

        $data['备注'] = $arr;
        return jsonResult('succ', 200, $data);
    }

    # 签到
    public function signList()
    {
        $signlist = Db::table('snake_signlist')->field('sign_id,day,sign_name,num,status')->select();
        $sign = Db::table('snake_sign')->where(['user_id' => $this->user_id, 'status' => 0])->select();
        $ids = [];
        foreach ($signlist as &$v) {
            foreach ($sign as $i => $j) {
                if ($v['day'] == $j['day']) {
                    $ids[] = $j['day'];
                    $v['status'] = 1;
                }
            }

            if ($v['day'] == 7) {
                $aaa = explode(',', $v['num']);
                $v['num'] = intval($aaa[0]);
                $v['diamond'] = intval($aaa[1]);
            }
            $v['num'] = intval($v['num']);
            unset($v); // 最后取消掉引用
        }

        $is_qd = Db::table('snake_sign')->where(['user_id' => $this->user_id, 'add_time' => date('Y-m-d')])->value('sign_id');
        if (empty($is_qd) && count($ids) == 7 || empty($sign)) {
            foreach ($signlist as &$v) {
                $v['status'] = 0;
                unset($v); // 最后取消掉引用
            }
        }
        $res['sign'] = $signlist;
        $res['is_qd'] = empty($is_qd) ? 0 : 1;
        return jsonResult('签到列表', 200, $res);
    }

    # 点击签到
    public function clickSign()
    {
        $coin = input('post.coin');
        $qd_id = Db::table('snake_sign')->where(['user_id' => $this->user_id, 'add_time' => date('Y-m-d')])->value('sign_id');
        if ($qd_id) return jsonResult('今日已签到过', 100);
        $day = Db::table('snake_sign')->where(['user_id' => $this->user_id, 'status' => 0])->order('sign_id desc')->value('day');
        if (empty($day)) {
            $today = 1;
        } else {
            $today = $day + 1;
            if ($day >= 7) {
                $today = 1;
                Db::table('snake_sign')->where(['user_id' => $this->user_id, 'status' => 0])->update(['status' => 1]);
            }
        }
        $addData = [
            'user_id' => $this->user_id,
            'day' => $today,
            'add_time' => date('Y-m-d')
        ];
        Db::table('snake_sign')->insert($addData);
        $res['day'] = $today;

        return jsonResult('签到成功', 200, $res);
    }

    # 上线
    public function online()
    {
        $time = time();
        $offline_max_time = 1800;

        # 每秒收益
        $user = Db::table('snake_snake')
            ->field('s.snake_second_earnings,u.snake_lv,u.offline_time')
            ->alias('s')
            ->join(['snake_user' => 'u'], 's.snake_lv=u.snake_lv')
            ->find();
        $second = $time - $user['offline_time'] >= $offline_max_time ? $offline_max_time : $time - $user['offline_time'];
        $earnings_second = Db::table('snake_config')->value('earnings_second');
        $offline_coin = intval($user['snake_second_earnings'] / $earnings_second * $second);
        $res['coin'] = $offline_coin;
        Db::table('snake_user')->where(['user_id' => $this->user_id])->update(['offline_time' => $time]);
        return jsonResult('上线数据', 100, $res);
    }

    # 离线收益
    public function offline()
    {
        $post = request()->only(['coin', 'diamond', 'map_id', 'unlock_map', 'offline_fruits', 'pay_fruits', 'max_fruits_id', 'snake_growth_value', 'snake_lv', 'clothing_id'], 'post');
        $data = input('post.data');
        $arr1 = json_decode($data, true);
        $date = date('Y-m-d');

        $upData = [
            'coin' => $post['coin'],
            'diamond' => $post['diamond'],
            'pay_fruits' => $post['pay_fruits'],
            'offline_fruits' => $post['offline_fruits'],
            'max_fruits_id' => $post['max_fruits_id'],
            'map_id' => empty($post['map_id']) ? 1 : $post['map_id'],
            'unlock_map' => empty($post['unlock_map']) ? 1 : $post['unlock_map'],
            'snake_lv' => $post['snake_lv'],
            'snake_growth_value' => $post['snake_growth_value'],
            'clothing_id' => $post['clothing_id'],
            'offline_time' => time(),
        ];
        Db::table('snake_user')->where(['user_id' => $this->user_id])->update($upData);

        if ($arr1) {
            $arr2 = Db::table('snake_task_record')->where(['user_id' => $this->user_id, 'add_time' => $date])->select();
            $id1 = array_column($arr1, 'task_id');
            $id2 = array_column($arr2, 'task_id');
            $jj = array_intersect($id1, $id2);
            $cj = array_diff($id1, $id2);

            $insert = $updata = [];
            foreach ($arr1 as $k => $v) {
                if (in_array($v['task_id'], $jj)) { // 交集 更新
                    $updata[$k]['task_id'] = $v['task_id'];
                    $updata[$k]['task_accomplish'] = $v['task_accomplish'];
                    $updata[$k]['task_prize_num'] = $v['task_prize_num'];
                    $updata[$k]['status'] = $v['status'];
                }
                if (in_array($v['task_id'], $cj)) { // 差集 新增
                    $insert[$k]['user_id'] = $this->user_id;
                    $insert[$k]['task_id'] = $v['task_id'];
                    $insert[$k]['task_type'] = $v['task_type'];
                    $insert[$k]['task_accomplish'] = $v['task_accomplish'];
                    $insert[$k]['task_prize_num'] = $v['task_prize_num'];
                    $insert[$k]['status'] = $v['status'];
                    $insert[$k]['add_time'] = $date;
                }
            }
            if ($insert) {
                Db::table('snake_task_record')->insertAll($insert);
            }
            if ($updata) {
                foreach ($updata as &$v) {
                    Db::table('snake_task_record')->where(['user_id' => $this->user_id, 'task_id' => $v['task_id'], 'add_time' => $date])->update($v);
                }
            }
        }
        return jsonResult('离线记录成功', 200, $post);
    }

    # 大转盘 （奖品列表）
    public function prizeList()
    {
        $date = date('Y-m-d');
        $prize = Db::table('snake_prize')->select();
        foreach ($prize as $k => $v) {
            if ($v['type'] == 1) {
                $prize[$k]['num'] = $v['prize_name'];
            }
        }

        $res['list'] = $prize;
        $draw_prize_num = Db::table('snake_prizelist')->where(['user_id' => $this->user_id, 'add_time' => $date])->count();
        $res['draw_prize_num'] = 5 - $draw_prize_num;
        return jsonResult('奖品列表', 200, $res);
    }

    # 点击抽奖
    public function drawPrize()
    {
        $fruit_id = input('post.fruit_id');
        $snake_lv = input('post.snake_lv');
        $time = time();
        $date = date('Y-m-d');

        $draw_prize_num = Db::table('snake_prizelist')->where(['user_id' => $this->user_id, 'add_time' => $date])->count();
        if ($draw_prize_num >= 5) return jsonResult('没有抽奖机会了', 100);

        $addData = [
            'user_id' => $this->user_id,
            'add_time' => date('Y-m-d'),
        ];
        Db::table('snake_prizelist')->insert($addData);

        $prize_arr = Db::table('snake_prize')->field('prize_id,gl,num,type')->select();
//            dump($prize_arr);die;
        foreach ($prize_arr as $key => $val) {
            $arr[$val['prize_id']] = $val['gl'];
        }
        $rid = get_rand($arr); //根据概率获取奖项id
        $num = $prize_arr[$rid - 1]['num'];
        $num1 = 0;
        if ($prize_arr[$rid - 1]['type'] == 1) {

            # 每秒收益
            $snake_second_earnings = Db::table('snake_snake')->where(['snake_lv' => $snake_lv])->value('snake_second_earnings');
//            $earnings_second = Db::table('snake_config')->value('earnings_second');
//            $num1 = intval($snake_second_earnings * $earnings_second * $num);
            $num1 = intval($snake_second_earnings * $num);

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

    # 邀请好友
    public function invited()
    {
        $signlist = Db::table('snake_signlist')->field('sign_id,sign_name,num,status')->select();
        $sign = Db::table('snake_sign')->where(['user_id' => $this->user_id, 'status' => 0])->select();
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
        $is_qd = Db::table('snake_sign')->where(['user_id' => $this->user_id, 'add_time' => date('Y-m-d')])->value('sign_id');
        $res['list'] = $signlist;
        $res['is_qd'] = empty($is_qd) ? 0 : 1;
        $data['sign'] = $res;


        return jsonResult('签到列表', 200, $data);
    }

    # 记录用户观看视频
    public function watchVideo()
    {
        $addData = [
            'user_id' => $this->user_id,
            'add_time' => date('Y-m-d'),
        ];
        Db::table('snake_watch_video')->insert($addData);
        return jsonResult('记录成功', 200);
    }

    # 分享
    public function share()
    {
        $num = input('post.num');
        $text = input('post.text');

        if (empty($this->user_id) || empty($num)) {
            return jsonResult('参数为空', 100);
        }
        $data = [
            'user_id' => $this->user_id,
            'num' => $num,
            'shares' => '',
            'text' => $text,
            'status' => 0,
            'add_time' => date('Y-m-d'),
            'timestamp' => time(),
        ];

//            dump($data);die;
        Db::table('snake_share')->insert($data);
        return jsonResult('分享成功', 200, $num);

    }

    # 点击分享
//    public function sharedetail()
//    {
//        $id = input('post.share_id'); // 分享者
//        if (empty($id)) return jsonResult('error', 110);
////            dump($res);die;
//        // 添加邀请好友记录
//        if ($this->user_id != $id) {
//            // 添加邀请好友记录
//            $invite = Db::table('snake_invite')->where(['share_id' => $this->user_id])->value('invite_id');
//            if (empty($invite)) {
//                $data[] = [
//                    'user_id' => $this->user_id,
//                    'share_id' => $id,
//                    'add_time' => date('Y-m-d H:i:s'),
//                ];
//                $data[] = [
//                    'user_id' => $id,
//                    'share_id' => $this->user_id,
//                    'add_time' => date('Y-m-d H:i:s'),
//                ];
//                Db::table('snake_invite')->insertAll($data);
//                return jsonResult('分享数据记录成功', 200);
//            } else {
//                return jsonResult('数据已存在', 100);
//            }
//        }
//    }

    # 点击分享
    public function sharedetail()
    {
        $id = input('post.share_id'); // 分享者
        $num = input('post.num');

//        dump($this->user_id);die;
        $res = Db::table('snake_share')->field('share_id,shares')->where(['user_id' => $id, 'num' => $num])->find();
        if (empty($res)) return jsonResult('error', 110);
//            dump($res);die;
        // 添加邀请好友记录
        if ($res) {
            if ($this->user_id != $id) {

                $share = explode(',', $res['shares']);
                if (!in_array($this->user_id, $share)) {
                    if (empty($res['shares'])) {
                        $str = $this->user_id;
                    } else {
                        $share[] = $this->user_id;
                        $str = join(',', $share);
                    }
                    Db::table('snake_share')->where(['share_id' => $res['share_id']])->update(['shares' => $str]);
                }

                // 添加邀请好友记录
                $invite = db('invite')->where(['share_id' => $this->user_id])->value('invite_id');
                if (empty($invite)) {
                    $data[] = [
                        'user_id' => $this->user_id,
                        'share_id' => $id,
                        'add_time' => date('Y-m-d H:i:s'),
                    ];
                    $data[] = [
                        'user_id' => $id,
                        'share_id' => $this->user_id,
                        'add_time' => date('Y-m-d H:i:s'),
                    ];
                    Db::table('snake_invite')->insertAll($data);
                }
                return jsonResult('分享数据记录成功', 200);
            }

        } else {
            return jsonResult('数据不存在', 100);
        }
    }

    # 数据统计
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

            $channel = Db::table('snake_user')->where(['user_id' => $user_id])->value('channel');
            if (empty($channel)) {
                Db::table('snake_user')->where(['user_id' => $user_id])->update(['channel' => $qd_id, 'enter_id' => $enter_id]);
            }
            $qd = $channel ? $channel : $qd_id;

            // 如果用户今天记录过
            $is_qd = Db::table('snake_statistics')->where(['qd_id' => $qd, 'user_id' => $user_id, 'add_time' => $time])->value('statistics_id');
            if ($is_qd) {
                Db::table('snake_statistics')->where(['statistics_id' => $is_qd])->setInc('num');
                return jsonResult('渠道数据已累加');
            } else {
                $userdate = Db::table('snake_user')->where(['user_id' => $user_id])->value('add_time');
                $data = [
                    'qd_id' => $qd,
                    'user_id' => $user_id,
                    'user_date' => $userdate,
                    'type' => $type,
                    'num' => 1,
                    'add_time' => $time,
                    'timestamp' => strtotime(date('Y-m-d H:i:s')),
                ];
                Db::table('snake_statistics')->insert($data);
                return jsonResult('渠道统计成功', 200);
            }
        }

        if ($app_id) {
            $adv_id = Db::table('snake_statistics_adv')->where(['app_id' => $app_id, 'user_id' => $this->user_id, 'add_time' => $time])->value('statistics_id');
            if (empty($adv_id)) {
                $advdata = [
                    'app_id' => $app_id,
                    'user_id' => $user_id,
                    'type' => $type,
                    'num' => 1,
                    'add_time' => date('Y-m-d'),
                    'timestamp' => time(),
                ];
                Db::table('snake_statistics_adv')->insert($advdata);

                $restrict = db('restrict')->where(['app_id' => $app_id, 'add_time' => $time])->find();
                if (empty($restrict)) {
                    $addData = [
                        'app_id' => $app_id,
                        'num' => 1,
                        'add_time' => $time,
                    ];
                    db('restrict')->insert($addData);
                } else {
                    db('restrict')->where(['app_id' => $app_id, 'add_time' => $time])->setInc('num');
                }

                return jsonResult('点击广告记录成功', 200);
            } else {
                Db::table('snake_statistics_adv')->where(['statistics_id' => $adv_id])->setInc('num');
                return jsonResult('点击广告数据累加', 200);
            }
        }
    }

    # 任务列表
    public function taskList()
    {
        $date = date('Y-m-d');

        // 当天的任务
        $record = Db::table('snake_task_record')->where(['user_id' => $this->user_id, 'add_time' => $date])->select();
//        dump($record);
        $zx = $rc = [];
        foreach ($record as $key => $value) {
            # 主线 #
            if ($value['task_type'] == 1) {
                $zx[] = $value;
            }
            # 日常 #
            if ($value['task_type'] == 2) {
                $rc[] = $value;
            }
        }
        foreach ($zx as $k => $v) {
            if ($v['status'] == 0) {
                unset($zx[$k]);
            }
        }

        # 主线 #
        $id = array_column($zx, 'task_id');
        $zx1 = Db::table('snake_task')->where(['task_id' => ['not in', $id], 'task_type' => 1])->group('task_classify')->order('task_type')->select();
        # 日常 #
        $rc1 = Db::table('snake_task')->where(['task_type' => 2])->order('task_type')->select();

        // 奖励的金币数量
        $snake_lv = Db::table('snake_user')->where(['user_id' => $this->user_id])->value('snake_lv');
        $lv = empty($snake_lv) ? 1 : $snake_lv;
        $snake_second_earnings = Db::table('snake_snake')->where(['snake_lv' => $lv])->value('snake_second_earnings');
        # 主线 #
        foreach ($zx1 as &$v) {
            $v['task_prize_num'] = $v['task_prize_type'] == 1 ? $v['task_prize_num'] * $snake_second_earnings : $v['task_prize_num'];
            $v['task_accomplish'] = $v['status'];
        }
        # 日常 #
        foreach ($rc1 as &$k) {
            $k['task_accomplish'] = 0;
            $k['task_prize_num'] = $k['task_prize_type'] == 1 ? $k['task_prize_num'] * $snake_second_earnings : $k['task_prize_num'];
            foreach ($rc as &$v) {
                if ($k['task_id'] == $v['task_id']) {
                    $k['task_accomplish'] = $v['status'] == 1 ? $k['task_condition'] : $v['task_accomplish'];
                    $k['status'] = $v['status'];
                }
            }
        }
        $res = array_merge($zx1, $rc1);
        return jsonResult('任务列表', 200, $res);
    }

    # 领取任务
    public function getTask()
    {
        $coin = input('post.coin');
        $snake_lv = input('post.snake_lv'); // 蛇等级任务奖励钻石 水果奖励金币
        $task_id = input('post.task_id');
        $date = date('Y-m-d');

        $task = Db::table('snake_task')->where(['task_id' => $task_id])->find();
        if (empty($task)) return jsonResult('任务不存在', 100);

        $isGet = Db::table('snake_task_record')->where(['user_id' => $this->user_id, 'task_id' => $task_id])->value('task_record_id');
        if ($isGet['status'] == 1) return jsonResult($task['task_content'] . ' 已领取过，请勿重复领取', 100);
        if (empty($isGet)) {
            $addData = [
                'user_id' => $this->user_id,
                'task_id' => $task_id,
                'task_type' => $task['task_type'],
                'task_accomplish' => $task['task_condition'],
                'task_prize_num' => $coin,
                'status' => 1,
                'add_time' => $date,
            ];
            Db::table('snake_task_record')->insert($addData);
        } else {
            Db::table('snake_task_record')->where(['user_id' => $this->user_id, 'task_id' => $task_id])->update(['status' => 1]);
        }

        $record = [];
        if ($task['task_type'] == 1) { // 主线任务
            // 当天的任务
            $taskId = $task_id + 1;
            $record = Db::table('snake_task')->where(['task_id' => $taskId, 'task_classify' => $task['task_classify']])->select();
            $snake_second_earnings = Db::table('snake_snake')->where(['snake_lv' => $snake_lv])->value('snake_second_earnings');
            foreach ($record as &$v) {
                $v['task_prize_num'] = $v['task_prize_type'] == 1 ? $v['task_prize_num'] * $snake_second_earnings : $v['task_prize_num'];
                $v['task_accomplish'] = $v['task_condition'] - 1;
            }

            if (empty($record)) {
                $record = [];
            }
        }
        return jsonResult('领取成功', 200, $record);
    }

    # 对话
    public function new_talk()
    {
        $talk['talk_egg'] = Db::table('snake_talk')->where(['talk_type' => 1])->select();
        $talk['talk_snake'] = Db::table('snake_talk')->where(['talk_type' => 0])->select();
        return jsonResult('对话信息', 200, $talk);
    }

    # 蛇的装饰
    public function snakeClothing()
    {
        $clothing = Db::table('snake_clothing')->select();
        $pay_clothing = Db::table('snake_pay_clothing')->where(['user_id' => $this->user_id])->select();
        foreach ($clothing as &$k) {
            foreach ($pay_clothing as &$i) {
                if ($k['clothing_id'] == $i['clothing_id']) {
                    $k['status'] = 1;
                }
            }
        }
        return jsonResult('蛇的装饰', 200, $clothing);
    }

    # 购买装饰
    public function payClothing()
    {
        $clothing_id = input('post.clothing_id');
        $pay = Db::table('snake_pay_clothing')->where(['user_id' => $this->user_id, 'clothing_id' => $clothing_id])->find();
        if ($pay || empty($clothing_id)) return jsonResult('已购买过或服饰不存在', 100);
        $addData = [
            'user_id' => $this->user_id,
            'clothing_id' => $clothing_id,
            'add_time' => date('Y-m-d'),
        ];
        Db::table('snake_pay_clothing')->insert($addData);
        return jsonResult('购买成功', 200);
    }

    #用户行为
    public function record()
    {
        $text = input('post.text');
        $data = [
            'user_id' => $this->user_id,
            'text' => $text,
        ];
        Db::table('snake_record')->insert($data);
        return jsonResult('记录成功', 200);
    }

    # 更多好玩
    public function appMore()
    {
        $switch = Db::table('snake_switch')->find();
        $data['switch'] = $switch;

        # 热门游戏（10格） 0 列表 2 直跳
        if ($switch['more_status'] == 0) {
            $app = Db::table('snake_app')->field('id,app_id,app_name,app_url,app_text,page')->where(['status' => 0])->order('sort')->limit(10)->select();
            $data['app'] = $app;
        } else {
            $app = Db::table('snake_app')->field('id,app_id,app_name,app_url,app_text,page')->where(['status' => 2])->find();
            $data['app'] = $app;
        }

        # 试玩列表（8格）
        $play = Db::table('snake_app')->field('id,app_id,app_name,app_url,page')->where(['play_status' => 0])->limit(6)->order('rand()')->select();
        $data['play'] = $play;

        # 首页顶部
        $data['top'] = $this->push();
        return jsonResult('更多好玩', 200, $data);
    }

    # 广告推送
    public function push()
    {
        $time = date('Y-m-d');
        $play = Db::table('snake_statistics_adv')->where(['user_id' => $this->user_id])->column('app_id');
        $array = Db::query("SELECT a.id FROM snake_restrict AS r LEFT JOIN snake_app as a ON r.app_id = a.id where a.status = 0 and r.add_time = '" . "$time" . "' and r.num >= a.`restrict`;");
        $ids = array_column($array, 'id');
        $play = array_keys(array_flip($play) + array_flip($ids)); // 合并去重
        $app = Db::table('snake_app')->field('id,app_id,app_name,app_url,page')->where(['id' => ['not in', $play], 'status' => 0])->order('sort')->limit(4)->select();
        if (count($app) < 4) {
            $limit = 4 - count($app);
            $app1 = Db::table('snake_app')->field('id,app_id,app_name,app_url,page')->distinct(true)->where(['status' => 0])->order('rand()')->limit($limit)->select();
            $app = array_merge($app, $app1);
        }
        return $app;
    }

    # 对话
    public function talk()
    {
        $talk = Db::table('snake_talk')->select();
        return jsonResult('对话信息', 200, $talk);
    }

    public function aaa()
    {
        $res = lock_url('ow9q84swU5Qnb3eor8-fvVqhpiog,1101');


        dump($res);
//        $superFish = Db::table('snake_superfish')->find();
//        dump($superFish);

        $a = 30;    //鲶鱼的成长值
        $b = 0;     // 初始值
        for ($i = 1; $i <= 30; $i++) {
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


