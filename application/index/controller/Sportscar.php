<?php
/**
 * WX酷玩跑车
 * User: SongZhe
 * Date: 2019/8/13
 * Time: 17:02
 */

namespace app\index\controller;

use think\Db;
use ip\IpLocation;

class Sportscar extends Conmmon
{

    public function cronTab_rank()
    {
        $insert = Db::table('sportscar_user')->field('user_id,user_name,avatar,max_pass,now() as add_time')->where('login_date', date('Y-m-d'))->order('max_pass DESC')->limit(30)->select();
        $rank = Db::table('sportscar_rank')->select();
//        dump($insert);die;

        if (empty($rank)) {
            Db::table('sportscar_rank')->insertAll($insert);
        } else {
            foreach ($insert as $k => $v) {
                Db::table('sportscar_rank')->where(['rank_id' => $k + 1])->update($v);
            }
        }
    }


    /**
     * 获取code，返回openid
     */
    public function getcarCode()
    {
        if (request()->isPost()) {
            $code = input('post.code');
            if (empty($code)) return jsonResult('你瞅啥', 100);
            $appid = 'wx7c4d0750325d665e';
            $appsecret = '97d76a28bad5737a784226ac0a136464';
            $data = $this->getOpenid($appid, $appsecret, $code); // 获得 openid 和 session_key

            $user = Db::table('sportscar_user')->field('user_id,openid')->where(['openid' => $data['openid']])->find();
            if (empty($user)) {
                $num = substr(time(), -6);
                $arr = [
                    'openid' => $data['openid'],
                    'user_name' => '游客' . $num,
                    'avatar' => 'https://mzccc.mpmgc.cn/A/public/app/youke.png',
                    'add_time' => date('Y-m-d'),
                    'login_date' => date('Y-m-d'),
                    'add_timestamp' => time(),
                    'is_impower' => 0,
                ];
                $uid = Db::table('sportscar_user')->insertGetId($arr);
                $res['mstr'] = lock_url($data['openid'] . ',' . $uid);
                return jsonResult('请求成功', 200, $res);

            } else {
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

        $user = Db::table('sportscar_user')->where(['user_id' => $this->user_id])->value('user_id');
        if (empty($user)) return jsonResult('数据不存在', 100);

        $updata = [
            'user_name' => $user_name,
            'avatar' => empty($avatar) ? '' : $avatar . '?aaa=aa.jpg',
            'sex' => empty($sex) ? '' : $sex,
            'city' => empty($city) ? '' : $city,
            'is_impower' => 1,
            'end_time' => date('Y-m-d H:i:s'),
        ];
        Db::table('sportscar_user')->where(['user_id' => $this->user_id])->update($updata);
        return jsonResult('用户信息', 200);
    }

    # 首页配置信息
    public function index()
    {

        # 分享文案
        $data['share'] = config('share.sportscar_share_pic');

        # 用户信息
        $userInfo = Db::table('sportscar_user')
            ->field('user_id,user_name,avatar,city,max_pass,coin,is_impower,is_car,car,add_time')
            ->where(['user_id' => $this->user_id])
            ->find();
        $userInfo['is_car'] = empty($userInfo['is_car']) ? [0, 0] : json_decode($userInfo['is_car']);
        $userInfo['is_newplayer'] = $userInfo['add_time'] == date('Y-m-d') ? 0 : 1;
        $userInfo['is_impower'] = 1;

        // 剩余看视频的次数
        $video_num = Db::table('sportscar_watch_video')->where(['type' => 7, 'user_id' => $this->user_id, 'add_time' => date('Y-m-d')])->count();
        $userInfo['video_num'] = (5 - $video_num) < 0 ? 0 : 5 - $video_num;

        // 车库
        $res = Db::table('sportscar_garages_class')->where(['is_show' => 1])->select();
        $arr = Db::table('sportscar_garages')->select();
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
        $data['match_car'] = Db::table('sportscar_garages')->select();

        # 金币配置
        $data['coin_config'] = config('car_coin_config1209');

        # 地图配置
        $map_config = config('car_map_config1212');
        foreach ($map_config as $k => $v) {
            $map_config[$k]['px'] = count($v['mapArr']) * 2000;
        }
        $data['map_config'] = $map_config;

        # 九宫格
        $app = Db::table('sportscar_app')
            ->field('id,app_id,app_name,app_url,app_banner_url,page,play_status,status,sort,banner_status,banner_sort')
            ->where('play_status', 0)
            ->order('play_sort')
            ->select();
        $data['app'] = $app;

        # 猜你喜欢
        $like = dengyu($app, 'status', 0);
        $last_like = array_column($like, 'sort');
        array_multisort($last_like, SORT_ASC, $like);
        $data['like'] = $like;

        # banner
        $banner = dengyu($app, 'banner_status', 0);
        $last_banner = array_column($banner, 'banner_sort');
        array_multisort($last_banner, SORT_ASC, $banner);
        $data['banner'] = $banner;

        # 根据IP地址是否开启误点
        $config = Db::table('sportscar_config')->find();
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

    public function first()
    {
        # 分享文案
        $data['share'] = config('share.singlecar_share_pic');

        # 用户信息
        $userInfo = Db::Table('sportscar_user')
            ->field('user_id,user_name,avatar,city,max_pass,max_distance,coin,money,is_impower,is_car,car,add_time')
            ->where(['user_id' => $this->user_id])
            ->find();
        $userInfo['is_car'] = empty($userInfo['is_car']) ? [0, 0] : json_decode($userInfo['is_car']);
        $userInfo['is_newplayer'] = $userInfo['add_time'] == date('Y-m-d') ? 0 : 1;
        $userInfo['is_impower'] = 1;

        // 剩余看视频的次数
        $video_num = Db::Table('sportscar_watch_video')->where(['type' => 10, 'user_id' => $this->user_id, 'add_time' => date('Y-m-d')])->count();
        $userInfo['video_num'] = (5 - $video_num) <= 0 ? 0 : 5 - $video_num;

        # 是否免费加速
        $userInfo['accelerationTimes'] = 0;

        // 车库
        $res = Db::Table('sportscar_garages_class')->where(['is_show' => 1])->select();
        $arr = Db::Table('sportscar_garages')->select();
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
        $rank_one = Db::table('sportscar_rank_one')->where('user_name', $userInfo['user_name'])->find();
        $is_rank = 0;
        if ($rank_one && $rank_one['status'] == 0) {
            Db::table('sportscar_rank_one')->where('user_name', $userInfo['user_name'])->update(['status' => 1]);
            $is_rank = 1;
        }
        $rankArr = [
            'is_rank' => $is_rank,
            'ranking' => $is_rank == 1 ? $rank_one['rank_id'] : 0,
        ];
        $data['oneRank'] = $rankArr;

        # 三日榜单
        $rank_three = Db::table('sportscar_rank_three')->where('user_name', $userInfo['user_name'])->find();
        $is_rank = 0;
        if ($rank_three && $rank_three['status'] == 0) {
            Db::table('sportscar_rank_three')->where('user_name', $userInfo['user_name'])->update(['status' => 1]);
            $is_rank = 1;
        }
        $rankArr1 = [
            'is_rank' => $is_rank,
            'ranking' => $is_rank == 1 ? $rank_three['rank_id'] : 0,
        ];
        $data['threeRank'] = $rankArr1;


        # 金币配置
        $data['coin_config'] = config('singlecar_coin_config');

        # 匹配
        $data['match_car'] = Db::Table('sportscar_garages')->select();

        # 地图配置
        $map_config = config('sportscar_map_config_bak');
        foreach ($map_config as $k => $v) {
            $map_config[$k]['px'] = count($v['mapArr']) * 2000;
        }
        $data['map_config'] = $map_config;

        # 根据IP地址是否开启误点
        $config = Db::Table('sportscar_config')->find();
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
        $data['prizeList'] = Db::table('sportscar_prizeList')->select();

        # 签到列表
        $signList = Db::table('sportscar_signlist')->field('day,num,type,status')->select();
        $sign = Db::table('sportscar_sign')->where(['user_id' => $this->user_id, 'status' => 0])->select();
        $end = end($sign);
        if ($end['add_time'] < $this->date && count($sign) >= 5) {
            # 满一周status更新为0
            Db::table('sportscar_sign')->where(['user_id' => $this->user_id])->update(['status' => 1]);
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
        $ranks = Db::table('sportscar_rank')->field('user_id,user_name,avatar,max_pass')->select();
        $userIds = array_column($ranks, 'user_id');
        $is_rank = array_keys($userIds, $this->user_id);

        $user = Db::table('sportscar_user')->field('user_id,user_name,avatar,max_pass')->where(['user_id' => $this->user_id])->find();
        if (empty($is_rank)) {
            if (empty($ranking)) {
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
        $res = Db::table('sportscar_garages_class')->where(['is_show' => 1])->select();
        $arr = Db::table('sportscar_garages')->select();
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
        Db::table('sportscar_user')->where(['user_id' => $this->user_id])->update($upData);
        return jsonResult('离线成功', 200, $res);
    }

    # 离线
    public function offline_new()
    {
        if (empty($this->user_id)) return jsonResult('error', 110);
        $userInfo = input('post.userInfo/a');

        // 车库
        $res = Db::table('sportscar_garages_class')->where(['is_show' => 1])->select();
        $arr = Db::table('sportscar_garages')->select();
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
        Db::table('sportscar_user')->where(['user_id' => $this->user_id])->update($upData);
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
//        $res = Db::table('sportscar_challenge')->field('status')->where(['user_id' => $this->user_id, 'timestamp' => $post['timestamp']])->find();
//        if ($res) {
//            if ($res['status'] == 0) {
//                $updata = [
//                    'status' => $post['status'],
//                    'end_time' => date('Y-m-d H:i:s'),
//                ];
//                Db::table('sportscar_challenge')->where(['user_id' => $this->user_id, 'timestamp' => $post['timestamp']])->update($updata);
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
//            Db::table('sportscar_challenge')->insert($insert);
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

        $user = Db::table('sportscar_user')->field('user_id,channel,login_date')->where(['user_id' => $this->user_id])->find();

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
            Db::table('sportscar_user')->where(['user_id' => $this->user_id])->update($upData);
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

                Db::table('sportscar_user')->where('user_id', $this->user_id)->update($upData);
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
            Db::table('sportscar_user')->where(['user_id' => $this->user_id])->update($upData);
            return jsonResult('渠道更新成功', 200, $upData);
        }
    }

    # 渠道统计
    public function statistics_qd_old()
    {
        $user_id = $this->user_id;
        if (empty($user_id)) return jsonResult('error', 110);

//        $date = date('Y-m-d');
        $post = request()->only(['channel', 'scene', 'enter_id'], 'post'); //channel 渠道ID 例如：1001  scene 场景值 enter_id 来源小程序ID
        $enter_id = $post['enter_id'];

        $is_channel = Db::table('sportscar_user')->field('user_id,channel,enter_id')->where(['user_id' => $this->user_id])->find();
        if (empty(intval($is_channel['channel']))) { // 用户表channel为空

            if ($is_channel['enter_id']) {

                // 用户表有enter_id channel为0
                $channel = Db::Table('channel_product_info')
                    ->alias('p')
                    ->join(['channel_info' => 'i'], 'p.enter_id=i.enter_id', 'LEFT')
                    ->where(['i.enter_id' => $is_channel['enter_id']])
                    ->value('i.qd_id');
                $upData = [
                    'channel' => $channel,
                    'end_time' => date('Y-m-d H:i:s'),
                ];

            } else {

                // 新用户
                $channel2 = Db::Table('channel_info')->where(['enter_id' => $enter_id])->value('qd_id');
                if ($channel2) {
                    $upData = [
                        'channel' => $channel2,
                        'scene' => $post['scene'],
                        'enter_id' => $enter_id,
                        'end_time' => date('Y-m-d H:i:s'),
                    ];
                } else {
                    $upData = [
                        'channel' => $post['channel'],
                        'scene' => $post['scene'],
                        'enter_id' => $enter_id,
                        'end_time' => date('Y-m-d H:i:s'),
                    ];
                }
            }
            Db::table('sportscar_user')->where(['user_id' => $user_id])->update($upData);
            return jsonResult('渠道记录成功', 200, $upData);

        } else {
            return jsonResult('渠道已存在', 200);
        }
    }

    # 渠道统计
    public function statistics_channel()
    {
        if (empty($this->user_id)) return jsonResult('error', 110);
        $post = request()->only(['channel', 'scene', 'enter_id'], 'post'); //channel 渠道ID 例如：1001  scene 场景值 enter_id 来源小程序ID
        //        $enter_id = $post['enter_id'];
        $user = Db::table('sportscar_user')->field('user_id,channel,login_date')->where(['user_id' => $this->user_id])->find();

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
            Db::table('sportscar_user')->where(['user_id' => $this->user_id])->update($upData);
            return jsonResult('渠道新增成功', 200, $upData);
        } else {
            // 老用户
            if ($user['login_date'] == date('Y-m-d')) {
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
            Db::table('sportscar_user')->where(['user_id' => $this->user_id])->update($upData);
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
            $app_id = Db::table('sportscar_app')->where(['app_id' => $app_id, 'play_status' => 0])->value('id');
        }

        $is_click = Db::table('sportscar_statistics_adv')->where(['user_id' => $this->user_id, 'app_id' => $post['app_id'], 'status' => 1, 'add_time' => $date, 'type' => $post['type']])->value('statistics_id');

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
            Db::table('sportscar_statistics_adv')->insert($advData);

            if ($post['status'] == 1) {
                # 广告限量
                $restrict_id = Db::table('sportscar_restrict')->where(['app_id' => $app_id, 'add_time' => $date])->value('restrict_id');
                if (empty($restrict_id)) {
                    $addData = [
                        'app_id' => $app_id,
                        'num' => 1,
                        'add_time' => $date,
                    ];
                    Db::table('sportscar_restrict')->insert($addData);
                } else {
                    Db::table('sportscar_restrict')->where(['restrict_id' => $restrict_id])->setInc('num');
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
        Db::table('sportscar_watch_video')->insert($post);
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
//        Db::table('sportscar_record')->insert($insert);
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