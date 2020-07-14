<?php
/**
 * 物理飞车
 * User: SongZhe
 * Date: 2019/8/13
 * Time: 17:02
 */

namespace app\index\controller;

use think\Db;
use ip\IpLocation;

class Singlecar extends Conmmon
{

    public function cronTab_rank()
    {
        $insert = Db::Table('singlecar_user')
            ->alias('u')
            ->field('u.user_id,u.user_name,u.avatar,u.max_distance,now() as add_time')
            ->join(['singlecar_record_channel' => 'rc'], 'u.user_id = rc.user_id', 'RIGHT')
            ->where(['rc.add_date' => date('Y-m-d')])
            ->order('u.max_distance DESC')
            ->group('rc.user_id')
            ->limit(30)
            ->select();

        $arr = [];
        foreach ($insert as $k => $v) {
            $arr[$k]['user_id'] = $v['user_id'];
            $arr[$k]['user_name'] = $v['user_name'];
            $arr[$k]['avatar'] = $v['avatar'];
            $arr[$k]['max_pass'] = $v['max_distance'];
            $arr[$k]['add_time'] = $v['add_time'];
        }
        $rank = Db::Table('singlecar_rank')->select();

        if (empty($rank)) {
            Db::Table('singlecar_rank')->insertAll($arr);
        } else {
            foreach ($arr as $k => $v) {
                Db::Table('singlecar_rank')->where(['rank_id' => $k + 1])->update($v);
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
            $appid = 'wx0ceb417ac3bd3b66';
            $appsecret = '4e95056fb94cdfcde960f6aa6cdd82da';
            $data = $this->getOpenid($appid, $appsecret, $code); // 获得 openid 和 session_key

            $user = Db::Table('singlecar_user')->field('user_id,openid')->where(['openid' => $data['openid']])->find();
            $num = substr(time(), -6);
            if (empty($user)) {
                $arr = [
                    'openid' => $data['openid'],
                    'user_name' => '游客' . $num,
                    'avatar' => 'https://yxdrs.zsmgc.com.cn/A/public/app/youke.png',
                    'add_time' => date('Y-m-d'),
                    'login_date' => date('Y-m-d'),
                    'add_timestamp' => time(),
                    'is_impower' => 0,
                ];
                $uid = Db::Table('singlecar_user')->insertGetId($arr);
                $res['mstr'] = lock_url($data['openid'] . ',' . $uid);
                return jsonResult('请求成功', 200, $res);

            } else {
//                Db::Table('singlecar_user')->where(['user_id' => $user['user_id']])->update(['end_time' => date('Y-m-d H::s')]);
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

        $user = Db::Table('singlecar_user')->where(['user_id' => $this->user_id])->value('user_id');
        if (empty($user)) return jsonResult('数据不存在', 100);

        $updata = [
            'user_name' => $user_name,
            'avatar' => empty($avatar) ? '' : $avatar . '?aaa=aa.jpg',
            'sex' => empty($sex) ? '' : $sex,
            'city' => empty($city) ? '' : $city,
            'is_impower' => 1,
            'end_time' => date('Y-m-d H:i:s'),
        ];
        Db::Table('singlecar_user')->where(['user_id' => $this->user_id])->update($updata);
        return jsonResult('用户信息', 200);
    }

    # 首页配置信息
    public function index() // 1.2.4
    {
        # 日活
//        Db::Table('singlecar_user')->where('user_id', $this->user_id)->update(['login_date' => date('Y-m-d')]);

        # 分享文案
        $data['share'] = config('share.singlecar_share_pic');

        # 用户信息
        $userInfo = Db::Table('singlecar_user')
            ->field('user_id,user_name,avatar,city,max_pass,max_distance,coin,is_impower,is_car,car,add_time')
            ->where(['user_id' => $this->user_id])
            ->find();
        $userInfo['is_car'] = empty($userInfo['is_car']) ? [0, 0] : json_decode($userInfo['is_car']);
        $userInfo['is_newplayer'] = $userInfo['add_time'] == date('Y-m-d') ? 0 : 1;
        $userInfo['is_impower'] = 1;

        // 剩余看视频的次数
        $video_num = Db::Table('singlecar_watch_video')->where(['type' => 10, 'user_id' => $this->user_id, 'add_time' => date('Y-m-d')])->count();
        $userInfo['video_num'] = (5 - $video_num) < 0 ? 0 : 5 - $video_num;

        # 是否免费加速
        $accelerationTimes = Db::Table('singlecar_free_acceleration')->where(['user_id' => $this->user_id, 'add_time' => date('Y-m-d')])->find();
        $userInfo['accelerationTimes'] = $accelerationTimes ? 0 : 1;

        // 车库
        $res = Db::Table('singlecar_garages_class')->where(['is_show' => 1])->select();
        $arr = Db::Table('singlecar_garages')->select();
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
        $data['coin_config'] = config('singlecar_coin_config');

        # 匹配
        $data['match_car'] = Db::Table('singlecar_garages')->select();

        # 地图配置
        $map_config = config('singlecar_map_config');
        foreach ($map_config as $k => $v) {
            $map_config[$k]['px'] = count($v['mapArr']) * 2000;
        }
        $data['map_config'] = $map_config;

        # 九宫格
        $app = Db::Table('singlecar_app')
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
        $config = Db::table('singlecar_config')->find();
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

    # 首页配置信息
    public function index_bak() // 1.2.4
    {
        # 日活
//        Db::Table('singlecar_user')->where('user_id', $this->user_id)->update(['login_date' => date('Y-m-d')]);

        # 分享文案
        $data['share'] = config('share.motorcycles_share_pic');

        # 用户信息
        $userInfo = Db::Table('singlecar_user')
            ->field('user_id,user_name,avatar,city,max_pass,max_distance,coin,is_impower,is_car,car,add_time')
            ->where(['user_id' => $this->user_id])
            ->find();
        $userInfo['is_car'] = empty($userInfo['is_car']) ? [0, 0] : json_decode($userInfo['is_car']);
        $userInfo['is_newplayer'] = $userInfo['add_time'] == date('Y-m-d') ? 0 : 1;
        $userInfo['is_impower'] = 1;

        // 剩余看视频的次数
        $video_num = Db::Table('singlecar_watch_video')->where(['type' => 10, 'user_id' => $this->user_id, 'add_time' => date('Y-m-d')])->count();
        $userInfo['video_num'] = (5 - $video_num) < 0 ? 0 : 5 - $video_num;

        # 是否免费加速
        $accelerationTimes = Db::Table('singlecar_free_acceleration')->where(['user_id' => $this->user_id, 'add_time' => date('Y-m-d')])->find();
        $userInfo['accelerationTimes'] = $accelerationTimes ? 0 : 1;

        // 车库
        $res = Db::Table('singlecar_garages_class')->where(['is_show' => 1])->select();
        $arr = Db::Table('singlecar_garages')->select();
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
        $data['coin_config'] = config('singlecar_coin_config');

        # 匹配
        $data['match_car'] = Db::Table('singlecar_garages')->select();

        # 地图配置
        $map_config = config('singlecar_map_config_20200411');
        foreach ($map_config as $k => $v) {
            $map_config[$k]['px'] = count($v['mapArr']) * 2000;
        }
        $data['map_config'] = $map_config;

        # 九宫格
        $app = Db::Table('singlecar_app')
            ->field('id,app_id,app_name,app_url,app_banner_url,page,play_status,status,sort,banner_status,banner_sort')
            ->where('play_status', 0)
            ->order('play_sort')
            ->limit(10)
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
        $config = Db::table('singlecar_config')->find();
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

    # 首页配置信息
    public function first() // 1.2.4
    {
        # 分享文案
        $data['share'] = config('share.motorcycles_share_pic');

        # 用户信息
        $userInfo = Db::Table('singlecar_user')
            ->field('user_id,user_name,avatar,city,max_pass,max_distance,coin,is_impower,is_car,car,add_time')
            ->where(['user_id' => $this->user_id])
            ->find();
        $userInfo['is_car'] = empty($userInfo['is_car']) ? [0, 0] : json_decode($userInfo['is_car']);
        $userInfo['is_newplayer'] = $userInfo['add_time'] == date('Y-m-d') ? 0 : 1;
        $userInfo['is_impower'] = 1;

        // 剩余看视频的次数
        $video_num = Db::Table('singlecar_watch_video')->where(['type' => 10, 'user_id' => $this->user_id, 'add_time' => date('Y-m-d')])->count();
        $userInfo['video_num'] = (5 - $video_num) < 0 ? 0 : 5 - $video_num;

        # 是否免费加速
        $accelerationTimes = Db::Table('singlecar_free_acceleration')->where(['user_id' => $this->user_id, 'add_time' => date('Y-m-d')])->find();
        $userInfo['accelerationTimes'] = $accelerationTimes ? 0 : 1;

        // 车库
        $res = Db::Table('singlecar_garages_class')->where(['is_show' => 1])->select();
        $arr = Db::Table('singlecar_garages')->select();
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
        $data['coin_config'] = config('singlecar_coin_config');

        # 匹配
        $data['match_car'] = Db::Table('singlecar_garages')->select();

        # 地图配置
        $map_config = config('singlecar_map_config_bak');
        foreach ($map_config as $k => $v) {
            $map_config[$k]['px'] = count($v['mapArr']) * 2000;
        }
        $data['map_config'] = $map_config;

        # 九宫格
        $app = Db::Table('singlecar_app')
            ->field('id,app_id,app_name,app_url,app_banner_url,page,play_status,status,sort,banner_status,banner_sort')
            ->where('play_status', 0)
            ->order('play_sort')
            ->limit(10)
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
        $config = Db::table('singlecar_config')->find();
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
        $ranks = Db::Table('singlecar_rank')->field('user_id,user_name,avatar,max_pass')->select();
        $userIds = array_column($ranks, 'user_id');
        $is_rank = array_keys($userIds, $this->user_id);

        $user = Db::Table('singlecar_user')->field('user_id,user_name,avatar,max_pass')->where(['user_id' => $this->user_id])->find();
        if (empty($is_rank)) {
            if (empty($ranking)) {
//                $ranking = Db::Table('singlecar_user')->where(['max_pass' => ['>', $user['max_pass']]])->count();
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
    public function offline() // 1.2.4
    {
        if (empty($this->user_id)) return jsonResult('error', 110);
        $userInfo = input('post.userInfo/a');

        // 车库
        $res = Db::Table('singlecar_garages_class')->where(['is_show' => 1])->select();
        $arr = Db::Table('singlecar_garages')->select();
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
        Db::Table('singlecar_user')->where(['user_id' => $this->user_id])->update($upData);
        return jsonResult('离线成功', 200, $upData);
    }

    # 关卡挑战记录
    public function challenge()
    {
        return;
//        $post = request()->post(['timestamp', 'max_pass', 'status'], 'post');
//
//        $insert = [
//            'user_id' => $this->user_id,
//            'timestamp' => $post['timestamp'],
//            'max_pass' => $post['max_pass'],
//            'add_time' => date('Y-m-d H:i:s'),
//            'end_time' => date('Y-m-d H:i:s'),
//        ];
//        Db::Table('singlecar_challenge')->insert($insert);
//        return jsonResult('点击游戏开始', 200);

    }

    # 渠道统计
    public function statistics_channel()
    {
        if (empty($this->user_id)) return jsonResult('error', 110);
        $post = request()->only(['channel', 'scene', 'enter_id'], 'post'); //channel 渠道ID 例如：1001  scene 场景值 enter_id 来源小程序ID
        //        $enter_id = $post['enter_id'];
        $date = date('Y-m-d');

        $record_channel_id = Db::Table('singlecar_record_channel')->where(['user_id' => $this->user_id, 'add_date' => $date])->value('record_channel_id');
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
            Db::table('singlecar_record_channel')->insert($record);
            return jsonResult('渠道统计成功', 200, $record);
        }
        return jsonResult('今日已统计', 100);
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
            $app_id = Db::Table('singlecar_app')->where(['app_id' => $app_id, 'play_status' => 0])->value('id');
        }

        $is_click = Db::Table('singlecar_statistics_adv')->where(['user_id' => $this->user_id, 'app_id' => $post['app_id'], 'status' => 1, 'add_time' => $date])->value('statistics_id');

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
            Db::Table('singlecar_statistics_adv')->insert($advData);

            if ($post['status'] == 1) {
                # 广告限量
                $restrict_id = Db::Table('singlecar_restrict')->where(['app_id' => $app_id, 'add_time' => $date])->value('restrict_id');
                if (empty($restrict_id)) {
                    $addData = [
                        'app_id' => $app_id,
                        'num' => 1,
                        'add_time' => $date,
                    ];
                    Db::Table('singlecar_restrict')->insert($addData);
                } else {
                    Db::Table('singlecar_restrict')->where(['restrict_id' => $restrict_id])->setInc('num');
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
        Db::Table('singlecar_watch_video')->insert($post);
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
//        Db::Table('singlecar_record')->insert($insert);
//        return jsonResult('用户行为记录成功', 200);
    }


    # 免费加速
    public function freeAcceleration()
    {
        $insert = [
            'user_id' => $this->user_id,
            'add_time' => date('Y-m-d'),
        ];
        Db::Table('singlecar_free_acceleration')->insert($insert);
        return jsonResult('用户行为记录成功', 200);
    }

}