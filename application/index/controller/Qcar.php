<?php
/**
 * QQ荒野飞车
 * User: SongZhe
 * Date: 2019/8/13
 * Time: 17:02
 */

namespace app\index\controller;

use think\Db;
use ip\IpLocation;

class Qcar extends Conmmon
{
    /**
     * 获取code，返回openid
     */
    public function getcarCode()
    {
        if (request()->isPost()) {
            $code = input('post.code');
            if (empty($code)) return jsonResult('你瞅啥', 100);
            $appid = '1110529141';
            $appsecret = 'G4VYvk2zPIEgDelI';
            $data = $this->getQQOpenid($appid, $appsecret, $code); // 获得 openid 和 session_key


            $user = Db::Table('QQ_car_user')->field('user_id,openid')->where(['openid' => $data['openid']])->find();
            if (empty($user)) {
                $arr = [
                    'openid' => $data['openid'],
                    'user_name' => '游客' . time(),
                    'avatar' => 'https://yxdrs.zsmgc.com.cn/A/public/app/youke.png',
                    'add_time' => date('Y-m-d'),
                    'login_date' => date('Y-m-d'),
                    'add_timestamp' => time(),
                    'is_impower' => 0,
                ];
                $uid = Db::Table('QQ_car_user')->insertGetId($arr);
                $res['mstr'] = lock_url($data['openid'] . ',' . $uid);
                return jsonResult('新用户', 200, $res);

            } else {
//                Db::Table('QQ_car_user')->where(['user_id' => $user['user_id']])->update(['end_time' => date('Y-m-d H::s')]);
                $res['mstr'] = lock_url($data['openid'] . ',' . $user['user_id']);
                return jsonResult('老用户', 200, $res);
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

        $user = Db::Table('QQ_car_user')->where(['user_id' => $this->user_id])->value('user_id');
        if (empty($user)) return jsonResult('数据不存在', 100);

        $updata = [
            'user_name' => $user_name,
            'avatar' => empty($avatar) ? '' : $avatar . '?aaa=aa.jpg',
            'sex' => empty($sex) ? '' : $sex,
            'city' => empty($city) ? '' : $city,
            'is_impower' => 1,
            'end_time' => date('Y-m-d H:i:s'),
        ];
        Db::Table('QQ_car_user')->where(['user_id' => $this->user_id])->update($updata);
        return jsonResult('用户信息', 200);
    }

    # 首页配置信息
    public function index()
    {
        # 分享文案
        $data['share'] = config('share.singlecar_share_pic');

        # 用户信息
        $userInfo = Db::Table('QQ_car_user')
            ->field('user_id,user_name,avatar,city,max_pass,max_distance,coin,money,is_impower,is_car,car,add_time')
            ->where(['user_id' => $this->user_id])
            ->find();
        $userInfo['is_car'] = empty($userInfo['is_car']) ? [2, 0] : json_decode($userInfo['is_car']);
        $userInfo['is_newplayer'] = $userInfo['add_time'] == date('Y-m-d') ? 0 : 1;
        $userInfo['is_impower'] = 1;

        // 剩余看视频的次数
        $video_num = Db::Table('QQ_car_watch_video')->where(['type' => 10, 'user_id' => $this->user_id, 'add_time' => date('Y-m-d')])->count();
        $userInfo['video_num'] = (5 - $video_num) <= 0 ? 0 : 5 - $video_num;

        # 是否免费加速
        $userInfo['accelerationTimes'] = 0;

        // 车库
        $res = Db::Table('QQ_car_garages_class')->where(['is_show' => 1])->select();
        $arr = Db::Table('QQ_car_garages')->select();
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


        # 今日榜单
        $rank_one = Db::table('QQ_car_rank_one')->where('user_name', $userInfo['user_name'])->find();
        $is_rank = 0;
        if ($rank_one && $rank_one['status'] == 0) {
            Db::table('QQ_car_rank_one')->where('user_name', $userInfo['user_name'])->update(['status' => 1]);
            $is_rank = 1;
        }
        $rankArr = [
            'is_rank' => $is_rank,
            'ranking' => $is_rank == 1 ? $rank_one['rank_id'] : 0,
        ];
        $data['oneRank'] = $rankArr;

        # 三日榜单
        $rank_three = Db::table('QQ_car_rank_three')->where('user_name', $userInfo['user_name'])->find();
        $is_rank = 0;
        if ($rank_three && $rank_three['status'] == 0) {
            Db::table('QQ_car_rank_three')->where('user_name', $userInfo['user_name'])->update(['status' => 1]);
            $is_rank = 1;
        }
        $rankArr1 = [
            'is_rank' => $is_rank,
            'ranking' => $is_rank == 1 ? $rank_three['rank_id'] : 0,
        ];
        $data['threeRank'] = $rankArr1;


        # 匹配
        $data['match_car'] = Db::Table('QQ_car_garages')->select();

        # 金币配置
        $data['coin_config'] = config('car_coin_config1209');

        # 地图配置
        $map_config = config('car_map_config1212');
        foreach ($map_config as $k => $v) {
            $map_config[$k]['px'] = count($v['mapArr']) * 2000;
        }
        $data['map_config'] = $map_config;

        # 根据IP地址是否开启误点
        $config = Db::Table('QQ_car_config')->find();
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
        $version_number = input('post.version_number');
        if ($version_number == $config['version_number']) {
            $data['isCanErrorClick'] = $isCanErrorClick;
        } else {
            $data['isCanErrorClick'] = 0;
        }

        # 转盘列表
        $data['prizeList'] = Db::table('QQ_car_prizeList')->select();

        # 签到列表
        $signList = Db::table('QQ_car_signlist')->field('day,num,type,status')->select();
        $sign = Db::table('QQ_car_sign')->where(['user_id' => $this->user_id, 'status' => 0])->select();
        $end = end($sign);
        if ($end['add_time'] < $this->date && count($sign) >= 5) {
            # 满一周status更新为0
            Db::table('QQ_car_sign')->where(['user_id' => $this->user_id])->update(['status' => 1]);
            $sign = [];
        }
        foreach ($signList as &$v) {
            foreach ($sign as &$j) {
                if ($v['day'] == $j['day']) {
                    $v['status'] = 1;
                }
            }
        }
        $signInfo['is_sign'] = $end['add_time'] < $this->date ? 0 : 1;
        $signInfo['day'] = count($sign);
        $signInfo['signList'] = $signList;
        $data['signInfo'] = $signInfo;

        $data['probation_status'] = $config['probation_status']; // 是否继续视频弹窗
        $data['left_switch_status'] = $config['left_switch_status']; // 左侧广告开关
        $data['get_interface_status'] = $config['get_interface_status']; // 领取界面开关
        $data['API'] = request()->action();
        return jsonResult('首页配置信息', 200, $data);
    }

    # 排行榜
    public function rank()
    {
        $user_name = Db::table('QQ_car_user')->where('user_id', $this->user_id)->value('user_name');
        # 单日
        $ranks = Db::Table('QQ_car_rank_one')->field('user_name,avatar,max_pass')->select();
        $userIds = array_column($ranks, 'user_name');
        $is_rank = array_keys($userIds, $user_name);

        $user = Db::Table('QQ_car_user')->field('user_name,max_pass')->where('user_id', $this->user_id)->find();
        if (empty($is_rank)) {
            $ranking1 = '99+';
        } else {
            $ranking1 = $is_rank[0] + 1;
        }
        $data['ranks'] = $ranks;
        $user['ranking'] = $ranking1;
        $data['user_rank'] = $user;

        # 三日
        $ranks_three = Db::Table('QQ_car_rank_three')->field('user_name,avatar,max_pass')->select();
        $userIds = array_column($ranks, 'user_name');
        $is_rank = array_keys($userIds, $user_name);

        $user = Db::Table('QQ_car_user')->field('user_name,max_pass')->where('user_id', $this->user_id)->find();
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
        $res = Db::Table('QQ_car_garages_class')->where(['is_show' => 1])->select();
        $arr = Db::Table('QQ_car_garages')->select();
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
        Db::Table('QQ_car_user')->where(['user_id' => $this->user_id])->update($upData);
        return jsonResult('离线成功', 200, $upData);
    }

    # 关卡挑战记录
    public function challenge_bak()
    {
        $post = request()->post(['timestamp', 'max_pass', 'status'], 'post');

        $insert = [
            'user_id' => $this->user_id,
            'timestamp' => $post['timestamp'],
            'max_pass' => $post['max_pass'],
            'add_time' => date('Y-m-d H:i:s'),
            'end_time' => date('Y-m-d H:i:s'),
        ];
        Db::Table('QQ_car_challenge')->insert($insert);
        return jsonResult('点击游戏开始', 200);
    }

    public function challenge()
    {
        $redis = new \Redis();
        $redis->connect('132.232.24.2', 7800);
        $redis->auth('sz1991'); # 如果没有密码则不需要这行
        $post = request()->only(['user_name', 'max_pass'], 'post');
        if (empty($post) || $post['max_pass'] <= 10) return jsonResult('挑战未记录', 100);

        $max_distance = Db::table('QQ_car_user')->where('user_id', $this->user_id)->value('max_distance');
        if ($post['max_pass'] > $max_distance) {
            Db::table('QQ_car_user')->where('user_id', $this->user_id)->update(['max_distance' => $post['max_pass']]);
        }

        $one_length = $redis->zCard('QQ_carRank_one');
        # 一日
        $one_score = $redis->zScore('QQ_carRank_one', $post['user_name']);
        if ($one_score) {
            if ($post['max_pass'] > $one_score) {
                $redis->zAdd('QQ_carRank_one', $post['max_pass'], $post['user_name']);
            }
        } else {
            if ($one_length < 30) {
                $redis->zAdd('QQ_carRank_one', $post['max_pass'], $post['user_name']);
            } else {
                // 返回分数从低到高排序的前10名及分数
                $revRange = $redis->zRange('QQ_carRank_one', 0, 0, true);
                $key = array_keys($revRange);
                $value = array_values($revRange);

                if ((int)$post['max_pass'] > (int)$value[0]) {
                    // 删除成员
                    $redis->zRem('QQ_carRank_one', $key[0]);
                    $redis->zAdd('QQ_carRank_one', $post['max_pass'], $post['user_name']);
                }
            }
        }

        $three_length = $redis->zCard('QQ_carRank_three');
        # 三日
        $three_score = $redis->zScore('QQ_carRank_three', $post['user_name']);
        if ($three_score) {
            if ($post['max_pass'] > $three_score) {
                $redis->zAdd('QQ_carRank_three', $post['max_pass'], $post['user_name']);
            }
        } else {
            if ($three_length < 30) {
                $redis->zAdd('QQ_carRank_three', $post['max_pass'], $post['user_name']);
            } else {
                // 返回分数从高到低排序的前10名及分数
                $revRange = $redis->zRange('QQ_carRank_three', 0, 0, true);
                $key = array_keys($revRange);
                $value = array_values($revRange);

                if ((int)$post['max_pass'] > (int)$value[0]) {
                    // 删除成员
                    $redis->zRem('QQ_carRank_three', $key[0]);
                    $redis->zAdd('QQ_carRank_three', $post['max_pass'], $post['user_name']);
                }
            }
        }

        $rankArr = $redis->zRevRange('QQ_carRank_one', 0, 4, true);
        $ranks = [];
        foreach ($rankArr as $k => $v) {
            $arr['user_name'] = $k;
            $arr['max_pass'] = $v;
            $arr['avatar'] = '';
            array_push($ranks, $arr);
        }
        $res['ranks'] = $ranks;

        $count = $redis->zRevRange('QQ_carRank_one', $one_length - 1, $one_length - 1, true);
        $five = $redis->zRevRange('QQ_carRank_one', 0, 4, true);
        $start = array_values($five)[count(array_values($five)) - 1];
        $end = array_values($count)[count(array_values($count)) - 1];
        if ($post['max_pass'] >= $start) {
            $ranking = $redis->zRevRank('QQ_carRank_one', $post['user_name']) + 1; // 从高到低排序的名次
        } else if ($post['max_pass'] < $start && $post['max_pass'] >= $end) {
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

        $record_channel_id = Db::Table('QQ_car_record_channel')->where(['user_id' => $this->user_id, 'add_date' => $date])->value('record_channel_id');
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
            Db::Table('QQ_car_record_channel')->insert($record);
            return jsonResult('渠道统计成功', 200, $record);
        }
        return jsonResult('今日已统计', 100);
    }

    # 记录用户观看视频
    public function watchVideo()
    {
        $post = request()->only(['type', 'text', 'pass'], 'post');
        if ($post['type'] == 16) {
            $insert = [
                'user_id' => $this->user_id,
                'prize_id' => $post['pass'],
                'add_date' => date('Y-m-d'),
            ];
            Db::table('QQ_car_prize')->insert($insert);
        }
        $post['user_id'] = $this->user_id;
        $post['add_time'] = date('Y-m-d');
        $post['timestamp'] = time();
        Db::Table('QQ_car_watch_video')->insert($post);
        return jsonResult('记录成功', 200);
    }

    # 免费加速
    public function freeAcceleration()
    {
        $insert = [
            'user_id' => $this->user_id,
            'add_time' => date('Y-m-d'),
        ];
        Db::Table('QQ_car_free_acceleration')->insert($insert);
        return jsonResult('用户行为记录成功', 200);
    }

    # 点击签到
    public function drawSign()
    {
        $user_sign = Db::table('QQ_car_sign')->where(['user_id' => $this->user_id, 'status' => 0])->select();
        $end = end($user_sign);
        if ($end['add_time'] == $this->date) return jsonResult('今日已签到过', 100);

        $day = $end['day'] == 7 ? 1 : $end['day'] + 1;
        $insert = [
            'user_id' => $this->user_id,
            'day' => $day,
            'add_time' => $this->date,
            'status' => 0,
        ];
        Db::table('QQ_car_sign')->insert($insert);
        return jsonResult('签到成功', 200);
    }

    # 点击抽奖
    public function drawPrize()
    {
        $prizeNum = Db::table('QQ_car_prize')->where(['user_id' => $this->user_id, 'add_date' => $this->date])->count('id');
        if ($prizeNum >= 10) {
            return jsonResult('今日抽奖次数已达上限', 100);
        }
        $prize_arr = Db::table('QQ_car_prizeList')->field('prizeList_id,gl,num,type')->select();
        $hb = Db::table('QQ_car_prize')->where(['user_id' => $this->user_id, 'prize_id' => 3])->value('id');
        foreach ($prize_arr as $key => $val) {
            $arr[$val['prizeList_id']] = $val['gl'];
            if (empty($hb) && $val['prizeList_id'] == 4) {
                $arr[$val['prizeList_id']] = $val['gl'] + rand(20, 30);
            }
        }
        $id = get_rand($arr); //根据概率获取奖项id
        $rid = $id - 1;
        if ($prize_arr[$rid]['type'] == 3) {
            Db::table('QQ_car_user')->where('user_id', $this->user_id)->setInc('money', floatval($prize_arr[$rid]['num']));
        }
        $insert = [
            'user_id' => $this->user_id,
            'prize_id' => $rid,
            'add_date' => date('Y-m-d'),
        ];
        Db::table('QQ_car_prize')->insert($insert);
        $res['rid'] = $rid;
        return jsonResult('中奖ID', 200, $res);
    }

    # 点击领取任务奖励
    public function drawActivity()
    {
        $activityList_id = input('post.activityList_id');
        if (empty($activityList_id)) return jsonResult('error', 110);

        $activityList = Db::table('QQ_car_activity')->where(['user_id' => $this->user_id, 'activityList_id' => $activityList_id])->find();
        if ($activityList) return jsonResult('该奖励已领取过', 100);
        $insert = [
            'user_id' => $this->user_id,
            'activityList_id' => $activityList_id,
            'add_time' => date('Y-m-d H:i:s'),
        ];
        Db::table('QQ_car_activity')->insert($insert);

        $prize = Db::table('QQ_car_activityList')->where(['activityList_id' => $activityList_id])->value('prize');
        Db::table('QQ_car_user')->where('user_id', $this->user_id)->setInc('money', $prize);
        return jsonResult('成功领取任务奖励');
    }

    # 活动列表
    public function activityList()
    {
        $activityList = Db::table('QQ_car_activityList')->select();
        $activity = Db::table('QQ_car_activity')->where('user_id', $this->user_id)->field('activityList_id')->select();
        foreach ($activityList as $k => $v) {
            foreach ($activity as $i => $j) {
                if ($v['activityList_id'] == $j['activityList_id']) {
                    unset($activityList[$k]);
                }
            }
        }
        $activityList = array_values($activityList);

        # type 1：行驶距离 2：转盘（type：16） 3：签到 4：邀请好友 5：游戏复活（type：2）
        $res = [];
        for ($i = 1; $i <= 5; $i++) {
            if ($i == 1) {
                $res['max_distance'] = Db::table('QQ_car_user')->where('user_id', $this->user_id)->value('max_distance');
            } else if ($i == 2) {
                $res['prize'] = Db::table('QQ_car_prize')->where('user_id', $this->user_id)->count('id');
            } else if ($i == 3) {
                $res['sign'] = Db::table('QQ_car_sign')->where(['user_id' => $this->user_id])->count('sign_id');
            } else if ($i == 4) {
                $res['shareMessage'] = Db::table('QQ_car_shareMessage')->where(['user_id' => $this->user_id])->count('id');
            } else if ($i == 5) {
                $res['watchVideo'] = Db::table('QQ_car_watch_video')->where(['user_id' => $this->user_id, 'add_time' => ['>', '2020-04-30'], 'type' => 2])->count('watch_id');
            }
        }
        foreach ($activityList as &$v) {
            if ($v['type'] == 1) {
                $v['finish_number'] = $res['max_distance'];
                if ($res['max_distance'] >= $v['target_number'] && $v['status'] == 0) {
                    $v['status'] = 1;
                }
            } else if ($v['type'] == 2) {
                $v['finish_number'] = $res['prize'];
                if ($res['prize'] >= $v['target_number'] && $v['status'] == 0) {
                    $v['status'] = 1;
                }
            } else if ($v['type'] == 3) {
                $v['finish_number'] = $res['shareMessage'];
                if ($res['shareMessage'] >= $v['target_number'] && $v['status'] == 0) {
                    $v['status'] = 1;
                }
            } else if ($v['type'] == 4) {
                $v['finish_number'] = $res['shareMessage'];
                if ($res['shareMessage'] >= $v['target_number'] && $v['status'] == 0) {
                    $v['status'] = 1;
                }
            } else if ($v['type'] == 5) {
                $v['finish_number'] = $res['watchVideo'];
                if ($res['watchVideo'] >= $v['target_number'] && $v['status'] == 0) {
                    $v['status'] = 1;
                }
            }
        }
        array_multisort(array_column($activityList, 'status'), SORT_DESC, $activityList);
        return jsonResult('', 200, $activityList);
    }

    # 获取分享信息
    public function getShareMessage()
    {
//        dump(lock_url('aaaaaaaaaaaaaaa,11111111'));die;
        $post = request()->only(['share_mstr']);
        $id = explode(',', unlock_url($post['share_mstr']));
        $share_id = $id[1];
        $is_newplayer = Db::table('QQ_car_user')->where(['user_id' => $this->user_id])->value('car');
        if (empty($is_newplayer)) {
            $share = Db::table('QQ_car_shareMessage')->where('share_id', $this->user_id)->find();
            if (empty($share)) {
                $insert = [
                    'user_id' => $share_id,
                    'share_id' => $this->user_id,
                    'add_time' => date('Y-m-d'),
                ];
                Db::table('QQ_car_shareMessage')->insert($insert);
                return jsonResult('成功分享带新');
            }
        }
        return jsonResult('不是新用户', 100);
    }

    public function demo()
    {

    }


}