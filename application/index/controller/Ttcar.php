<?php
/**
 * 头条-酷玩跑车
 * User: SongZhe
 * Date: 2019/8/13
 * Time: 17:02
 */

namespace app\index\controller;

use think\Db;
use ip\IpLocation;

class TtCar extends Conmmon
{

    public function cronTab_rank()
    {
        $insert = Db::table('TT_sportsCar_user')->field('user_id,user_name,avatar,max_pass,now() as add_time')->where('login_date', date('Y-m-d'))->order('max_pass DESC')->limit(30)->select();
        $rank = Db::table('TT_sportsCar_rank')->select();
//        dump($insert);die;

        if (empty($rank)) {
            Db::table('TT_sportsCar_rank')->insertAll($insert);
        } else {
            foreach ($insert as $k => $v) {
                Db::table('TT_sportsCar_rank')->where(['rank_id' => $k + 1])->update($v);
            }
        }
    }

    public function getcarCode()
    {
        if (request()->isPost()) {
            $code = input('post.code');
            if (empty($code)) return jsonResult('你瞅啥', 100);
//            $data = $this->get_car_openid($code); // 获得 openid 和 session_key
            $appid = 'tt2afa5c47b5d040a6';
            $appsecret = '180406e45b281fa6471c12da32eec8a612a2506a';
            $data = $this->getTTOpenid($appid, $appsecret, $code); // 获得 openid 和 session_key
//            return jsonResult('succ', 200, $data);

            $user = Db::table('TT_sportsCar_user')->field('user_id,openid')->where(['openid' => $data['openid']])->find();
            $num = substr(time(), -6);

            if (empty($user)) {
                $arr = [
                    'openid' => $data['openid'],
                    'user_name' => '游客' . $num,
                    'avatar' => '',
                    'add_time' => date('Y-m-d'),
                    'login_date' => date('Y-m-d'),
                    'add_timestamp' => time(),
                    'is_impower' => 0,
                ];
                $uid = Db::table('TT_sportsCar_user')->insertGetId($arr);
                $res['mstr'] = lock_url($data['openid'] . ',' . $uid);
                return jsonResult('请求成功', 200, $res);

            } else {
//                Db::table('TT_sportsCar_user')->where(['user_id' => $user['user_id']])->update(['end_time' => date('Y-m-d H::s')]);
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

        $user = Db::table('TT_sportsCar_user')->where(['user_id' => $this->user_id])->value('user_id');
        if (empty($user)) return jsonResult('数据不存在', 100);

        $updata = [
            'user_name' => $user_name,
            'avatar' => empty($avatar) ? '' : $avatar . '?aaa=aa.jpg',
            'sex' => empty($sex) ? '' : $sex,
            'city' => empty($city) ? '' : $city,
            'is_impower' => 1,
            'end_time' => date('Y-m-d H:i:s'),
        ];
        Db::table('TT_sportsCar_user')->where(['user_id' => $this->user_id])->update($updata);
        return jsonResult('用户信息', 200);
    }

    # 首页配置信息
    public function first()
    {
        # 分享文案
        $data['share'] = config('share.car_share_pic');

        # 用户信息
        $userInfo = Db::table('TT_sportsCar_user')
            ->field('user_id,user_name,avatar,city,max_pass,coin,is_impower,channel,is_car,car,add_time')
            ->where(['user_id' => $this->user_id])
            ->find();
        $userInfo['is_car'] = empty($userInfo['is_car']) ? [0, 0] : json_decode($userInfo['is_car']);
        $userInfo['is_newplayer'] = $userInfo['add_time'] == date('Y-m-d') ? 0 : 1;
        $userInfo['is_impower'] = 1;

        // 剩余看视频的次数
        $video_num = Db::table('TT_sportsCar_watch_video')->where(['type' => 7, 'user_id' => $this->user_id, 'add_time' => date('Y-m-d')])->count();
        $userInfo['video_num'] = (5 - $video_num) < 0 ? 0 : 5 - $video_num;

        // 车库
        $res = Db::table('TT_sportsCar_garages_class')->where(['is_show' => 1])->select();
        $arr = Db::table('TT_sportsCar_garages')->select();
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

        # 金币配置
        $data['coin_config'] = config('car_coin_config1209');

        # 匹配
        $data['match_car'] = Db::table('TT_sportsCar_garages')->select();

        # 地图配置
        $map_config = config('car_map_config1212');
        foreach ($map_config as $k => $v) {
            $map_config[$k]['px'] = count($v['mapArr']) * 2000;
        }
        $data['map_config'] = $map_config;

        # 根据IP地址是否开启误点
        $config = Db::table('TT_sportsCar_config')->find();
        $data['isCanErrorClick'] = 0; // 头条没有误点
        $data['probation_status'] = $config['probation_status']; // 是否继续视频弹窗
        $data['left_switch_status'] = $config['left_switch_status']; // 左侧广告开关
        $data['get_interface_status'] = $config['get_interface_status']; // 领取界面开关
        $data['API'] = request()->action();
        return jsonResult('首页配置信息', 200, $data);
    }

    # 排行榜
    public function rank()
    {
        $ranks = Db::table('TT_sportsCar_rank')->field('user_id,user_name,avatar,max_pass')->select();
        $userIds = array_column($ranks, 'user_id');
        $is_rank = array_keys($userIds, $this->user_id);

        $user = Db::table('TT_sportsCar_user')->field('user_id,user_name,avatar,max_pass')->where(['user_id' => $this->user_id])->find();
        if (empty($is_rank)) {
            if (empty($ranking)) {
//                $ranking = Db::table('TT_sportsCar_user')->where(['max_pass' => ['>', $user['max_pass']]])->count();
                $ranking = rand(100, 1000);
            }
        } else {
            $ranking = $is_rank[0] + 1;
        }
        $user['ranking'] = $ranking;
        $data['ranks'] = $ranks;
        $data['user_rank'] = $user;

        return jsonResult('排行榜', 200, $data);
    }


    # 离线
    public function offline()
    {
        if (empty($this->user_id)) return jsonResult('error', 110);
        $userInfo = input('post.userInfo/a');

        // 车库
        $res = Db::table('TT_sportsCar_garages_class')->where(['is_show' => 1])->select();
        $arr = Db::table('TT_sportsCar_garages')->select();
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
            'system' => $userInfo['system'],
            'car' => json_encode($res, JSON_UNESCAPED_UNICODE),
            'is_car' => json_encode($userInfo['is_car']),
            'end_time' => date('Y-m-d H:i:s'),
            'offline_time' => time(),
        ];
//        dump($upData);
        Db::table('TT_sportsCar_user')->where(['user_id' => $this->user_id])->update($upData);
        return jsonResult('离线成功', 200, $res);
    }

    # 离线
    public function offline_new()
    {
        if (empty($this->user_id)) return jsonResult('error', 110);
        $userInfo = input('post.userInfo/a');

        // 车库
        $res = Db::table('TT_sportsCar_garages_class')->where(['is_show' => 1])->select();
        $arr = Db::table('TT_sportsCar_garages')->select();
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
            'system' => $userInfo['system'],
            'car' => json_encode($res, JSON_UNESCAPED_UNICODE),
            'is_car' => json_encode($userInfo['is_car']),
            'end_time' => date('Y-m-d H:i:s'),
            'offline_time' => time(),
        ];
//        dump($upData);
        Db::table('TT_sportsCar_user')->where(['user_id' => $this->user_id])->update($upData);
        return jsonResult('离线成功', 200, $res);
    }

    # 关卡挑战记录
    public function challenge()
    {
        return;
//        $post = request()->post(['timestamp', 'max_pass', 'status'], 'post');
//
//        if ($post['status'] == 0) {
//            $text = '游戏开始';
//        } elseif ($post['status'] == 1) {
//            $text = '成功';
//        } elseif ($post['status'] == 2) {
//            $text = '失败';
//        }
//
//        $res = Db::table('TT_sportsCar_challenge')->field('status')->where(['user_id' => $this->user_id, 'timestamp' => $post['timestamp']])->find();
//        if ($res) {
//            if ($res['status'] == 0) {
//                $updata = [
//                    'status' => $post['status'],
//                    'end_time' => date('Y-m-d H:i:s'),
//                ];
//                Db::table('TT_sportsCar_challenge')->where(['user_id' => $this->user_id, 'timestamp' => $post['timestamp']])->update($updata);
//                return jsonResult('游戏挑战：' . $text, 200);
//            }
//            return jsonResult('记录失败', 100);
//        } else {
//            $insert = [
//                'user_id' => $this->user_id,
//                'timestamp' => $post['timestamp'],
//                'max_pass' => $post['max_pass'],
//                'add_time' => date('Y-m-d H:i:s'),
//                'end_time' => date('Y-m-d H:i:s'),
//            ];
//            Db::table('TT_sportsCar_challenge')->insert($insert);
//            return jsonResult('点击游戏开始', 200);
//        }
    }

    # 渠道统计
    public function statistics_qd()
    {
        if (empty($this->user_id)) return jsonResult('error', 110);
        $post = request()->only(['channel', 'scene', 'enter_id'], 'post'); //channel 渠道ID 例如：1001  scene 场景值 enter_id 来源小程序ID
        //        $enter_id = $post['enter_id'];
        $date = date('Y-m-d');

        $user = Db::table('TT_sportsCar_user')->field('user_id,channel,login_date')->where(['user_id' => $this->user_id])->find();

        if (empty(intval($user['channel']))) {
            // 新用户 （用户表channel为空）
            $qd_id = Db::Table('channel_info')->where(['enter_id' => $post['enter_id']])->value('qd_id');
            $channel = empty($qd_id) ? $post['channel'] : $qd_id;
            $upData = [
                'channel' => $channel,
                'scene' => $post['scene'],
                'enter_id' => $post['enter_id'],
                'login_date' => date('Y-m-d'),
                'end_time' => date('Y-m-d H:i:s'),
            ];
            Db::table('TT_sportsCar_user')->where(['user_id' => $this->user_id])->update($upData);
            return jsonResult('渠道新增成功', 200, $upData);
        } else {

            if (empty($user['login_date'])) {
                $qd_id = Db::Table('channel_info')->where(['enter_id' => $post['enter_id']])->value('qd_id');
                $channel = empty($qd_id) ? $post['channel'] : $qd_id;
                $upData = [
                    'channel' => $channel,
                    'scene' => $post['scene'],
                    'enter_id' => $post['enter_id'],
                    'login_date' => date('Y-m-d'),
                ];

                Db::table('TT_sportsCar_user')->where('user_id', $this->user_id)->update($upData);
                return jsonResult('渠道更新成功（没login_date）', 300, $upData);
            }
            // 老用户
            if ($user['login_date'] == $date) {
                return jsonResult('渠道更新失败', 300, $user);
            }

            $qd_id = Db::Table('channel_info')->where(['enter_id' => $post['enter_id']])->value('qd_id');
            $channel = empty($qd_id) ? $post['channel'] : $qd_id;
            $upData = [
                'channel' => $channel,
                'scene' => $post['scene'],
                'enter_id' => $post['enter_id'],
                'login_date' => date('Y-m-d'),
            ];
            Db::table('TT_sportsCar_user')->where(['user_id' => $this->user_id])->update($upData);
            return jsonResult('渠道更新成功', 200, $upData);
        }
    }

    # 广告统计
    public function statistics_adv()
    {
        $user_id = $this->user_id;
//        $user_id = 1;
        $date = date('Y-m-d');
        $post = request()->only(['app_id', 'type', 'position', 'status', 'pass'], 'post');
        if (empty($user_id) || empty($post['app_id'])) return jsonResult('error', 110);

        # 广告统计
        $app_id = $post['app_id'];

        if (strlen($app_id) > 6) {
            $app_id = Db::table('TT_sportsCar_app')->where(['app_id' => $app_id, 'play_status' => 0])->value('id');
        }

        $is_click = Db::table('TT_sportsCar_statistics_adv')->where(['user_id' => $this->user_id, 'app_id' => $post['app_id'], 'status' => 1, 'add_time' => $date])->value('statistics_id');

        if (empty($is_click)) {
            $advData = [
                'user_id' => $user_id,
                'app_id' => $app_id,
                'type' => $post['type'],
                'position' => $post['position'],
                'pass' => $post['pass'],
                'add_time' => date('Y-m-d'),
                'timestamp' => time(),
                'status' => $post['status'],
            ];
            Db::table('TT_sportsCar_statistics_adv')->insert($advData);

            if ($post['status'] == 1) {
                # 广告限量
                $restrict_id = Db::table('TT_sportsCar_restrict')->where(['app_id' => $app_id, 'add_time' => $date])->value('restrict_id');
                if (empty($restrict_id)) {
                    $addData = [
                        'app_id' => $app_id,
                        'num' => 1,
                        'add_time' => $date,
                    ];
                    Db::table('TT_sportsCar_restrict')->insert($addData);
                } else {
                    Db::table('TT_sportsCar_restrict')->where(['restrict_id' => $restrict_id])->setInc('num');
                }
            }
            return jsonResult('点击广告记录成功', 200);
        } else {
            return jsonResult('今天广告已记录过', 100);

        }
    }

    # 记录用户观看视频
    public function watchVideo()
    {
        $post = request()->only(['type', 'text', 'pass'], 'post');
        $post['user_id'] = $this->user_id;
        $post['add_time'] = date('Y-m-d');
        $post['timestamp'] = time();
        Db::table('TT_sportsCar_watch_video')->insert($post);
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
//        Db::table('TT_sportsCar_record')->insert($insert);
//        return jsonResult('用户行为记录成功', 200);
    }

    public function getCityIp()
    {

//        $ip = $this->ip();
//        $ip = $this->get_real_ip();

        $ip = request()->ip(0, true);
        $url = 'http://ip.taobao.com/service/getIpInfo.php?ip=' . $ip;
//        $url = 'http://int.dpool.sina.com.cn/iplookup/iplookup.php?format=json&ip=' . $ip;
        $data = $this->curl_get($url);
        $data = json_decode($data, true);
        dump($ip);
        dump($data);
    }

}