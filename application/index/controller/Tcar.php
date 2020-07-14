<?php
/**
 * 头条-绝地飞车赛
 * User: SongZhe
 * Date: 2019/8/13
 * Time: 17:02
 */

namespace app\index\controller;

use think\Db;
use ip\IpLocation;
use think\cache\driver\Redis;

class Tcar extends Conmmon
{
    /**
     * 获取code，返回openid
     */
    public function getcarCode()
    {
        if (request()->isPost()) {
            $code = input('post.code');
            if (empty($code)) return jsonResult('你瞅啥', 100);
            $appid = 'tt8451f2ec1b6ba5d8';
            $appsecret = 'da211e41e57eb03950e45f8b89003cfc542f5999';
            $data = $this->getTTOpenid($appid, $appsecret, $code); // 获得 openid 和 session_key

            $user = Db::Table('TT_car_user')->field('user_id,openid')->where(['openid' => $data['openid']])->find();
            if (empty($user)) {
                $arr = [
                    'openid' => $data['openid'],
                    'user_name' => '游客' . time(),
                    'avatar' => '',
                    'add_time' => date('Y-m-d'),
                    'add_timestamp' => time(),
                    'is_impower' => 0,
                ];
                $uid = Db::Table('TT_car_user')->insertGetId($arr);
                $res['mstr'] = lock_url($data['openid'] . ',' . $uid);
                return jsonResult('请求成功', 200, $res);

            } else {
//                Db::Table('TT_car_user')->where(['user_id' => $user['user_id']])->update(['end_time' => date('Y-m-d H::s')]);
                $res['mstr'] = lock_url($data['openid'] . ',' . $user['user_id']);
                return jsonResult('请求成功', 200, $res);
            }
        }
    }

    # 获取用户信息
    public function getUserInfo()
    {
        $user_name = input('post.user_name');
        $avatar = input('post.avatar');
        $sex = input('post.sex');
        $city = input('post.city');

        $user = Db::Table('TT_car_user')->where(['user_id' => $this->user_id])->value('user_id');
        if (empty($user)) return jsonResult('数据不存在', 100);

        $updata = [
            'user_name' => $user_name,
            'avatar' => empty($avatar) ? '' : $avatar . '?aaa=aa.jpg',
            'sex' => empty($sex) ? '' : $sex,
            'city' => empty($city) ? '' : $city,
            'is_impower' => 1,
            'end_time' => date('Y-m-d H:i:s'),
        ];
        Db::Table('TT_car_user')->where(['user_id' => $this->user_id])->update($updata);
        return jsonResult('用户信息', 200);
    }

    # 首页配置信息
    public function index()
    {

//        dump(unlock_url('MGpOejV5cXZlNjVMbHJBUGQ5ckVQRFlHUURjN1M%3D'));die;
        # 分享文案
        $data['share'] = config('share.singlecar_share_pic');

        # 用户信息
        $userInfo = Db::Table('TT_car_user')
            ->field('user_id,user_name,avatar,city,max_pass,max_distance,coin,is_impower,is_car,car,add_time')
            ->where(['user_id' => $this->user_id])
            ->find();

        $userInfo['is_car'] = empty($userInfo['is_car']) ? [1, 0] : json_decode($userInfo['is_car']);
        $userInfo['is_newplayer'] = $userInfo['add_time'] == date('Y-m-d') ? 0 : 1;
        $userInfo['is_impower'] = 1;

        // 剩余看视频的次数
        $video_num = Db::Table('TT_car_watch_video')->where(['type' => 10, 'user_id' => $this->user_id, 'add_time' => date('Y-m-d')])->count();
        $userInfo['video_num'] = (5 - $video_num) <= 0 ? 0 : 5 - $video_num;

        # 是否免费加速
        $userInfo['accelerationTimes'] = 0;

        # 今日榜单
        $rank_one = Db::table('TT_car_rank_one')->where('user_name', $userInfo['user_name'])->find();
        $is_rank = 0;
        if ($rank_one && $rank_one['status'] == 0) {
            Db::table('TT_car_rank_one')->where('user_name', $userInfo['user_name'])->update(['status' => 1]);
            $is_rank = 1;
        }
        $rankArr = [
            'is_rank' => $is_rank,
            'ranking' => $is_rank == 1 ? $rank_one['rank_id'] : 0,
        ];
        $data['oneRank'] = $rankArr;

        # 三日榜单
        $rank_three = Db::table('TT_car_rank_three')->where('user_name', $userInfo['user_name'])->find();
        $is_rank = 0;
        if ($rank_three && $rank_three['status'] == 0) {
            Db::table('TT_car_rank_three')->where('user_name', $userInfo['user_name'])->update(['status' => 1]);
            $is_rank = 1;
        }
        $rankArr1 = [
            'is_rank' => $is_rank,
            'ranking' => $is_rank == 1 ? $rank_three['rank_id'] : 0,
        ];
        $data['threeRank'] = $rankArr1;


        // 车库
        $res = Db::Table('TT_car_garages_class')->where(['is_show' => 1])->select();
        $arr = Db::Table('TT_car_garages')->select();
        foreach ($res as $k => $v) {
            foreach ($arr as $i => $j) {
                if ($v['car_id'] == $j['class_id']) {
                    $res[$k]['carpic'][] = $j;
                }
            }
        }

        if (empty($userInfo['car'])) {
            $userInfo['car'] = $res;
        } else {
            $car = json_decode($userInfo['car'], true);
//            dump($car);die;
            for ($i = 0; $i < count($car); $i++) {
                foreach ($res[$i]['carpic'] as $k => $v) {
                    foreach ($car[$i]['carpic'] as $y => $z) {
                        if ($v['carpic_id'] == $z['carpic_id']) {
                            $res[$i]['carpic'][$k]['watch_num'] = $z['watch_num'];
                            $res[$i]['carpic'][$k]['status'] = $z['status'];
                        }
                    }
                }
            }
            $userInfo['car'] = $res;
        }
        $data['userInfo'] = $userInfo;

        # 匹配
        $data['match_car'] = Db::Table('TT_car_garages')->select();

        # 金币配置
        $data['coin_config'] = config('singlecar_coin_config');

        # 地图配置
        $map_config = config('TT_singlecar_map_config');
        foreach ($map_config as $k => $v) {
            $map_config[$k]['px'] = count($v['mapArr']) * 2000;
        }
        $data['map_config'] = $map_config;


        # 根据IP地址是否开启误点
        $config = Db::Table('TT_car_config')->find();
        $isCanErrorClick = $config['isCanErrorClick'];
        if ($isCanErrorClick == 1) {
            $IP = request()->ip(0, true);
            $arr = IpLocation::getLocation($IP);

            $str = $arr['province'] . $arr['city'];
            $city = ['北京', '上海', '广州', '成都'];
            foreach ($city as $k => $v) {
                $is_exist = strstr($str, $v);
                if ($is_exist) {
                    $isCanErrorClick = 0;
                }
            }
        }

        $data['isCanErrorClick'] = $isCanErrorClick; // 误点开关
        $data['probation_status'] = $config['probation_status']; // 是否继续视频弹窗
        $data['left_switch_status'] = $config['left_switch_status']; // 左侧广告开关
        $data['get_interface_status'] = $config['get_interface_status']; // 领取界面开关
        $data['API'] = request()->action();
        return jsonResult('首页配置信息', 200, $data);
    }

    # 排行榜
    public function rank()
    {
        $user_name = Db::table('TT_car_user')->where('user_id', $this->user_id)->value('user_name');
        # 单日
        $ranks = Db::Table('TT_car_rank_one')->field('user_name,avatar,max_pass')->select();
        $userIds = array_column($ranks, 'user_name');
        $is_rank = array_keys($userIds, $user_name);

        $user = Db::Table('TT_car_user')->field('user_name,max_pass')->where('user_id', $this->user_id)->find();
        if (empty($is_rank)) {
            $ranking1 = '99+';
        } else {
            $ranking1 = $is_rank[0] + 1;
        }
        $data['ranks'] = $ranks;
        $user['ranking'] = $ranking1;
        $data['user_rank'] = $user;

        # 三日
        $ranks_three = Db::Table('TT_car_rank_three')->field('user_name,avatar,max_pass')->select();
        $userIds = array_column($ranks, 'user_name');
        $is_rank = array_keys($userIds, $user_name);

        $user = Db::Table('TT_car_user')->field('user_name,max_pass')->where('user_id', $this->user_id)->find();
        if (empty($is_rank)) {
            $ranking2 = '99+';
        } else {
            $ranking2 = $is_rank[0] + 1;
        }
        $data['threeDay_ranks'] = $ranks_three;
        $user['ranking'] = $ranking2;
        $data['threeDay_user_rank'] = $user;

        return jsonResult('排行榜', 200, $data);
    }

    # 离线
    public function offline()
    {
        if (empty($this->user_id)) return jsonResult('error', 110);
        $userInfo = input('post.userInfo/a');

        // 车库
        $res = Db::Table('TT_car_garages_class')->where(['is_show' => 1])->select();
        $arr = Db::Table('TT_car_garages')->select();
        foreach ($res as $k => $v) {
            foreach ($arr as $i => $j) {
                if ($v['car_id'] == $j['class_id']) {
                    $res[$k]['carpic'][] = $j;
                }
            }
        }
        $car = $userInfo['car'];
        for ($i = 0; $i < count($car); $i++) {
            foreach ($res[$i]['carpic'] as $k => $v) {
                foreach ($car[$i]['carpic'] as $y => $z) {
                    if ($v['carpic_id'] == $z['carpic_id']) {
                        $res[$i]['carpic'][$k]['watch_num'] = $z['watch_num'];
                        $res[$i]['carpic'][$k]['status'] = $z['status'];
                    }
                }
            }
        }

        $upData = [
            'coin' => $userInfo['coin'],
            'max_pass' => $userInfo['max_pass'],
            'max_distance' => $userInfo['max_distance'],
            'system' => $userInfo['system'],
            'car' => json_encode($res, JSON_UNESCAPED_UNICODE),
            'is_car' => json_encode($userInfo['is_car']),
            'end_time' => date('Y-m-d H:i:s'),
            'offline_time' => time(),
        ];
//        dump($upData);
        Db::Table('TT_car_user')->where(['user_id' => $this->user_id])->update($upData);
        return jsonResult('离线成功', 200, $upData);
    }

    public function challenge()
    {
        $redis = new \Redis();
        $redis->connect('132.232.24.2', 7800);
        $redis->auth('sz1991'); # 如果没有密码则不需要这行
        $post = request()->only(['user_name', 'max_pass'], 'post');

        if (empty($post) || $post['max_pass'] <= 0) return jsonResult('挑战未记录', 100);

        $one_length = $redis->zCard('TT_carRank_one');
        # 一日
        $one_score = $redis->zScore('TT_carRank_one', $post['user_name']);
        if ($one_score) {
            if ($post['max_pass'] > $one_score) {
                $redis->zAdd('TT_carRank_one', $post['max_pass'], $post['user_name']);
            }
        } else {
            if ($one_length < 100) {
                $redis->zAdd('TT_carRank_one', $post['max_pass'], $post['user_name']);
            } else {
                // 返回分数从高到低排序的前10名及分数
                $revRange = $redis->zRange('TT_carRank_one', 0, $one_length - 1, true);
                $key = array_keys($revRange);
                $value = array_values($revRange);

                if ((int)$post['max_pass'] > (int)$value[0]) {
                    // 删除成员
                    $redis->zRem('TT_carRank_one', $key[0]);
                    $redis->zAdd('TT_carRank_one', $post['max_pass'], $post['user_name']);
                }
            }
        }

        $three_length = $redis->zCard('TT_carRank_three');
        # 一日
        $three_score = $redis->zScore('TT_carRank_three', $post['user_name']);
        if ($three_score) {
            if ($post['max_pass'] > $three_score) {
                $redis->zAdd('TT_carRank_three', $post['max_pass'], $post['user_name']);
            }
        } else {
            if ($three_length < 100) {
                $redis->zAdd('TT_carRank_three', $post['max_pass'], $post['user_name']);
            } else {
                // 返回分数从高到低排序的前10名及分数
                $revRange = $redis->zRange('TT_carRank_three', 0, $three_length - 1, true);
                $key = array_keys($revRange);
                $value = array_values($revRange);

                if ((int)$post['max_pass'] > (int)$value[0]) {
                    // 删除成员
                    $redis->zRem('TT_carRank_three', $key[0]);
                    $redis->zAdd('TT_carRank_three', $post['max_pass'], $post['user_name']);
                }
            }
        }

        $rankArr = $redis->zRevRange('TT_carRank_one', 0, 4, true);
        $ranks = [];
        foreach ($rankArr as $k => $v) {
            $arr['user_name'] = $k;
            $arr['max_pass'] = $v;
            $arr['avatar'] = '';
            array_push($ranks, $arr);
        }
        $res['ranks'] = $ranks;

        $count = $redis->zRevRange('TT_carRank_one', $one_length - 1, $one_length - 1, true);
        $five = $redis->zRevRange('TT_carRank_one', 0, 4, true);
        $start = array_values($five)[count(array_values($five)) - 1];
        $end = array_values($count)[count(array_values($count)) - 1];
        if ($post['max_pass'] >= $start) {
            $ranking = $redis->zRevRank('TT_carRank_one', $post['user_name']) + 1; // 从高到低排序的名次
        } elseif ($post['max_pass'] < $start && $post['max_pass'] >= $end) {
            $ranking = rand(5, $one_length);
        } else {
            $ranking = '99+';
        }
        $res['user_rank'] = [
            'user_name' => $post['user_name'],
            'max_pass' => $post['max_pass'],
            'ranking' => $ranking,
        ];
        return jsonResult('挑战记录', 200, $res);
    }


    public function challenge_bak()
    {
        $redis = new \Redis();
        $redis->connect('132.232.24.2', 7800);
        $redis->auth('sz1991'); # 如果没有密码则不需要这行
        $post = request()->only(['user_name', 'max_pass'], 'post');

        if (empty($post) || $post['max_pass'] <= 0) return jsonResult('挑战未记录', 100);

        $one_length = $redis->zCard('TT_carRank_one');
        # 一日
        $one_score = $redis->zScore('TT_carRank_one', $post['user_name']);
        dump($one_length);
        dump($one_score);
        if ($one_score) {
            dump('redis库存在');
            if ($post['max_pass'] > $one_score) {
                dump('传过来的数据大于库里面的数据');
                $redis->zAdd('TT_carRank_one', $post['max_pass'], $post['user_name']);
            }
        } else {
            dump('redis库不存在');
            if ($one_length < 100) {
                dump('总数小于100人');
                $redis->zAdd('TT_carRank_one', $post['max_pass'], $post['user_name']);
            } else {
                dump('总数大于100人');
                // 返回分数从高到低排序的前10名及分数
                $revRange = $redis->zRange('TT_carRank_one', 0, $one_length - 1, true);
                $key = array_keys($revRange);
                $value = array_values($revRange);

                if ((int)$post['max_pass'] > (int)$value) {
                    dump($revRange);
                    dump($value);
                    // 删除成员
                    $redis->zRem('TT_carRank_one', $key[0]);
                    $redis->zAdd('TT_carRank_one', $post['max_pass'], $post['user_name']);
                }
            }
        }

        $three_length = $redis->zCard('TT_carRank_three');
        # 一日
        $three_score = $redis->zScore('TT_carRank_three', $post['user_name']);
        if ($three_score) {
            if ($post['max_pass'] > $three_score) {
                $redis->zAdd('TT_carRank_three', $post['max_pass'], $post['user_name']);
            }
        } else {
            if ($three_length < 100) {
                $redis->zAdd('TT_carRank_three', $post['max_pass'], $post['user_name']);
            } else {
                // 返回分数从高到低排序的前10名及分数
                $revRange = $redis->zRange('TT_carRank_three', 0, $three_length - 1, true);
                $key = array_keys($revRange);
                $value = array_values($revRange);

                if ((int)$post['max_pass'] > (int)$value) {
                    // 删除成员
                    $redis->zRem('TT_carRank_three', $key[0]);
                    $redis->zAdd('TT_carRank_three', $post['max_pass'], $post['user_name']);
                }
            }
        }

        $rankArr = $redis->zRevRange('TT_carRank_one', 0, 4, true);
        $ranks = [];
        foreach ($rankArr as $k => $v) {
            $arr['user_name'] = $k;
            $arr['max_pass'] = $v;
            $arr['avatar'] = '';
            array_push($ranks, $arr);
        }
        $res['ranks'] = $ranks;

        $count = $redis->zRevRange('TT_carRank_one', $one_length - 1, $one_length - 1, true);
        $five = $redis->zRevRange('TT_carRank_one', 0, 4, true);
        $start = array_values($five)[count(array_values($five)) - 1];
        $end = array_values($count)[count(array_values($count)) - 1];
        if ($post['max_pass'] >= $start) {
            $ranking = $redis->zRevRank('TT_carRank_one', $post['user_name']) + 1; // 从高到低排序的名次
        } elseif ($post['max_pass'] < $start && $post['max_pass'] >= $end) {
            $ranking = rand(5, $one_length);
        } else {
            $ranking = '99+';
        }
        $res['user_rank'] = [
            'user_name' => $post['user_name'],
            'max_pass' => $post['max_pass'],
            'ranking' => $ranking,
        ];
        return jsonResult('挑战记录', 200, $res);
    }

    # 渠道统计
    public function statistics_channel()
    {
        if (empty($this->user_id)) return jsonResult('error', 110);
        $post = request()->only(['channel', 'scene', 'enter_id'], 'post'); //channel 渠道ID 例如：1001  scene 场景值 enter_id 来源小程序ID
        //        $enter_id = $post['enter_id'];
        $date = date('Y-m-d');

        $record_channel_id = Db::Table('TT_car_record_channel')->where(['user_id' => $this->user_id, 'add_date' => $date])->value('record_channel_id');
        if (empty($record_channel_id)) {
            # 用户从哪个渠道进入
            $record = [
                'user_id' => $this->user_id,
                'enter_id' => $post['enter_id'],
                'channel_id' => $post['channel'],
                'scene' => $post['scene'],
                'add_date' => $date,
                'add_time' => date('Y-m-d H:i:s'),
            ];
            Db::Table('TT_car_record_channel')->insert($record);
            return jsonResult('渠道统计成功', 200, $record);
        }
        return jsonResult('今日已统计', 100);
    }

    # 记录用户观看视频
    public function watchVideo()
    {
        $post = request()->only(['type', 'text', 'pass'], 'post');
        $post['user_id'] = $this->user_id;
        $post['add_time'] = date('Y-m-d');
        $post['timestamp'] = time();
        Db::Table('TT_car_watch_video')->insert($post);
        return jsonResult('记录成功', 200);
    }

    # 用户行为记录
    public function record()
    {
        return;
//        $post = request()->only(['pass', 'text']);
//        $insert = [
//            'user_id' => $this->user_id,
//            'pass' => $post['pass'],
//            'text' => $post['text'],
//            'add_time' => date('Y-m-d H:i:s'),
//        ];
//        Db::Table('TT_car_record')->insert($insert);
//        return jsonResult('用户行为记录成功', 200);
    }

    # 免费加速
    public function freeAcceleration()
    {
        $insert = [
            'user_id' => $this->user_id,
            'add_time' => date('Y-m-d'),
        ];
        Db::Table('TT_car_free_acceleration')->insert($insert);
        return jsonResult('用户行为记录成功', 200);
    }


    public function demo()
    {
        $redis = new \Redis();
        $redis->connect('132.232.24.2', 7800);
        $redis->auth('sz1991'); # 如果没有密码则不需要这行
        $post = request()->only(['user_name', 'max_pass'], 'post');

        if (empty($post) || $post['max_pass'] <= 0) return jsonResult('挑战未记录', 100);

        $one_length = $redis->zCard('TT_carRank_one');
        # 一日
        $one_score = $redis->zScore('TT_carRank_one', $post['user_name']);
        if ($one_score) {
            if ($post['max_pass'] > $one_score) {
                $redis->zAdd('TT_carRank_one', $post['max_pass'], $post['user_name']);
            }
        } else {
            if ($one_length < 100) {
                $redis->zAdd('TT_carRank_one', $post['max_pass'], $post['user_name']);
            } else {
                // 返回分数从高到低排序的前10名及分数
                $revRange = $redis->zRevRange('TT_carRank_one', 0, $one_length - 1, true);
                $key = array_keys($revRange);
                $value = array_values($revRange);

                if ((int)$post['max_pass'] > (int)$value) {
                    // 删除成员
                    $redis->zRem('TT_carRank_one', $key[0]);
                    $redis->zAdd('TT_carRank_one', $post['max_pass'], $post['user_name']);
                }
            }
        }

        # 三日
        $score = $redis->zScore('TT_carRank_three', $post['user_name']);
        if ($score) {
            if ($post['max_pass'] > $score) {
                $redis->zAdd('TT_carRank_three', $post['max_pass'], $post['user_name']);
            }
        } else {
            // 统计成员个数
            $three_length = $redis->zCard('TT_carRank_three');
            if ($three_length < 100) {
                $redis->zAdd('TT_carRank_three', $post['max_pass'], $post['user_name']);
            } else {
                // 返回分数从高到低排序的前10名及分数
                $revRange = $redis->zRevRange('TT_carRank_three', 0, $three_length - 1, true);
                $key = array_keys($revRange);
                $value = array_values($revRange);

                if ((int)$post['max_pass'] > (int)$value) {
                    // 删除成员
                    $redis->zRem('TT_carRank_three', $key[0]);
                    $redis->zAdd('TT_carRank_three', $post['max_pass'], $post['user_name']);
                }
            }
        }

        $rankArr = $redis->zRevRange('TT_carRank_one', 0, 4, true);
        $ranks = [];
        foreach ($rankArr as $k => $v) {
            $arr['user_name'] = $k;
            $arr['max_pass'] = $v;
            $arr['avatar'] = '';
            array_push($ranks, $arr);
        }
        $res['ranks'] = $ranks;

        $count = $redis->zRevRange('TT_carRank_one', $one_length - 1, $one_length - 1, true);
        $five = $redis->zRevRange('TT_carRank_one', 0, 4, true);
        $start = array_values($five)[count(array_values($five)) - 1];
        $end = array_values($count)[count(array_values($count)) - 1];
        if ($post['max_pass'] >= $start) {
            $ranking = $redis->zRevRank('TT_carRank_one', $post['user_name']); // 从高到低排序的名次
        } elseif ($post['max_pass'] < $start && $post['max_pass'] >= $end) {
            $ranking = rand(5, $one_length);
        } else {
            $ranking = '99+';
        }
        $res['user_rank'] = [
            'user_name' => $post['user_name'],
            'max_pass' => $post['max_pass'],
            'ranking' => $ranking,
        ];
        return jsonResult('挑战记录', 200, $res);
    }

}