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

class Singlemotor extends Conmmon
{

    public function cronTab_rank()
    {
        $insert = Db::Table('singleMotor_user')->field('user_id,user_name,avatar,max_distance,now() as add_time')->where('login_date', date('Y-m-d'))->order('max_distance DESC')->limit(30)->select();
        $arr = [];
        foreach ($insert as $k => $v) {
            $arr[$k]['user_id'] = $v['user_id'];
            $arr[$k]['user_name'] = $v['user_name'];
            $arr[$k]['avatar'] = $v['avatar'];
            $arr[$k]['max_pass'] = $v['max_distance'];
            $arr[$k]['add_time'] = $v['add_time'];
        }
        $rank = Db::Table('singleMotor_rank')->select();

        if (empty($rank)) {
            Db::Table('singleMotor_rank')->insertAll($arr);
        } else {
            foreach ($arr as $k => $v) {
                Db::Table('singleMotor_rank')->where(['rank_id' => $k + 1])->update($v);
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
            $appid = 'wx9900ea2a555d9c07';
            $appsecret = 'c2ac34621216c2e94288605c597ee0ac';
            $data = $this->getOpenid($appid, $appsecret, $code); // 获得 openid 和 session_key

            $user = Db::Table('singleMotor_user')->field('user_id,openid')->where(['openid' => $data['openid']])->find();
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
                $uid = Db::Table('singleMotor_user')->insertGetId($arr);
                $res['mstr'] = lock_url($data['openid'] . ',' . $uid);
                return jsonResult('请求成功', 200, $res);

            } else {
//                Db::Table('singleMotor_user')->where(['user_id' => $user['user_id']])->update(['end_time' => date('Y-m-d H::s')]);
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

        $user = Db::Table('singleMotor_user')->where(['user_id' => $this->user_id])->value('user_id');
        if (empty($user)) return jsonResult('数据不存在', 100);

        $updata = [
            'user_name' => $user_name,
            'avatar' => empty($avatar) ? '' : $avatar . '?aaa=aa.jpg',
            'sex' => empty($sex) ? '' : $sex,
            'city' => empty($city) ? '' : $city,
            'is_impower' => 1,
            'end_time' => date('Y-m-d H:i:s'),
        ];
        Db::Table('singleMotor_user')->where(['user_id' => $this->user_id])->update($updata);
        return jsonResult('用户信息', 200);
    }

    # 首页配置信息
    public function index()
    {
        # 分享文案
        $data['share'] = config('share.singlemotor_share_pic');

        # 用户信息
        $userInfo = Db::Table('singleMotor_user')
            ->field('user_id,user_name,avatar,city,max_pass,max_distance,coin,is_impower,is_car,car,is_role,role,add_time')
            ->where(['user_id' => $this->user_id])
            ->find();
        $userInfo['is_car'] = empty($userInfo['is_car']) ? [0, 0] : json_decode($userInfo['is_car']);
        $userInfo['is_impower'] = 1;
//        $userInfo['is_newplayer'] = $userInfo['add_time'] == date('Y-m-d') ? 0 : 1;
        $userInfo['is_newplayer'] = 1;

        // 剩余看视频的次数
        $video_num = Db::Table('singleMotor_watch_video')->where(['type' => 10, 'user_id' => $this->user_id, 'add_time' => date('Y-m-d')])->count();
        $userInfo['video_num'] = (5 - $video_num) < 0 ? 0 : 5 - $video_num;

        # 是否免费加速
        $accelerationTimes = Db::Table('singleMotor_free_acceleration')->where(['user_id' => $this->user_id, 'add_time' => date('Y-m-d')])->find();
        $userInfo['accelerationTimes'] = $accelerationTimes ? 0 : 1;

        // 角色商城
        $roleArr = Db::Table('singleMotor_garages_role')->select();
        if (empty($userInfo['role'])) {
            $userInfo['role'] = $roleArr;

        } else {
            $role = json_decode($userInfo['role'], true);
            foreach ($roleArr as $key => $vale) {
                foreach ($role as $k => $v) {
                    if ($vale['role_id'] == $v['role_id']) {
                        $roleArr[$key]['status'] = $v['status'];
                    }
                }
            }
            $userInfo['role'] = $roleArr;
        }

        // 车库
        $res = Db::Table('singleMotor_garages_class')->where(['is_show' => 1])->select();
        $arr = Db::Table('singleMotor_garages')->select();
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
        $data['coin_config'] = config('singlemotor_coin_config1202');

        # 匹配
        $data['match_car'] = Db::Table('singleMotor_garages')->select();

        # 地图配置
        $map_config = config('singlemotor_map_config1213');
        foreach ($map_config as $k => $v) {
            $map_config[$k]['px'] = count($v['mapArr']) * 2000;
        }
        $data['map_config'] = $map_config;

        # 九宫格
        $app = Db::Table('singleMotor_app')
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
        $config = Db::table('singleMotor_config')->find();
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
        $data['ZJD_status'] = $config['ZJD_status']; // 砸金蛋开关
        $data['API'] = request()->action();
        return jsonResult('首页配置信息', 200, $data);
    }

    # 首页配置信息
    public function first() // 1.2.4
    {
        # 日活
//        Db::Table('singleMotor_user')->where('user_id', $this->user_id)->update(['login_date' => date('Y-m-d')]);

        # 分享文案
        $data['share'] = config('share.singlemotor_share_pic');

        # 用户信息
        $userInfo = Db::Table('singleMotor_user')
            ->field('user_id,user_name,avatar,city,max_pass,max_distance,coin,is_impower,is_car,car,is_role,role,add_time')
            ->where(['user_id' => $this->user_id])
            ->find();
        $userInfo['is_car'] = empty($userInfo['is_car']) ? [0, 0] : json_decode($userInfo['is_car']);
        $userInfo['is_impower'] = 1;
        $userInfo['is_newplayer'] = $userInfo['add_time'] == date('Y-m-d') ? 0 : 1;

        // 剩余看视频的次数
        $video_num = Db::Table('singleMotor_watch_video')->where(['type' => 10, 'user_id' => $this->user_id, 'add_time' => date('Y-m-d')])->count();
        $userInfo['video_num'] = (5 - $video_num) < 0 ? 0 : 5 - $video_num;

        # 是否免费加速
        $accelerationTimes = Db::Table('singleMotor_free_acceleration')->where(['user_id' => $this->user_id, 'add_time' => date('Y-m-d')])->find();
        $userInfo['accelerationTimes'] = $accelerationTimes ? 0 : 1;

        // 角色商城
        $roleArr = Db::Table('singleMotor_garages_role')->select();
        if (empty($userInfo['role'])) {
            $userInfo['role'] = $roleArr;

        } else {
            $role = json_decode($userInfo['role'], true);
            foreach ($roleArr as $key => $vale) {
                foreach ($role as $k => $v) {
                    if ($vale['role_id'] == $v['role_id']) {
                        $roleArr[$key]['status'] = $v['status'];
                    }
                }
            }
            $userInfo['role'] = $roleArr;
        }

        // 车库
        $res = Db::Table('singleMotor_garages_class')->where(['is_show' => 1])->select();
        $arr = Db::Table('singleMotor_garages')->select();
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
        $data['coin_config'] = config('singlemotor_coin_config1202');

        # 匹配
        $data['match_car'] = Db::Table('singleMotor_garages')->select();

        # 地图配置
        $map_config = config('singlemotor_map_config1213');
        foreach ($map_config as $k => $v) {
            $map_config[$k]['px'] = count($v['mapArr']) * 2000;
        }
        $data['map_config'] = $map_config;

        # 九宫格
        $app = Db::Table('singleMotor_app')
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

        $data['probation_status'] = 1; // 是否继续视频弹窗
        $data['isCanErrorClick'] = 0; // 误点开关
        $data['left_switch_status'] = 1; // 左侧广告开关
        $data['API'] = request()->action();
        return jsonResult('首页配置信息', 200, $data);
    }

    # 排行榜
    public function rank()
    {
        $ranks = Db::Table('singleMotor_rank')->field('user_id,user_name,avatar,max_pass')->select();
        $userIds = array_column($ranks, 'user_id');
        $is_rank = array_keys($userIds, $this->user_id);

        $user = Db::Table('singleMotor_user')->field('user_id,user_name,avatar,max_pass')->where(['user_id' => $this->user_id])->find();
        if (empty($is_rank)) {
            if (empty($ranking)) {
//                $ranking = Db::Table('singleMotor_user')->where(['max_pass' => ['>', $user['max_pass']]])->count();
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

        // 角色商城
        $roleArr = Db::Table('singleMotor_garages_role')->select();
        $role = $userInfo['role'];
        foreach ($roleArr as $key => $vale) {
            foreach ($role as $k => $v) {
                if ($vale['role_id'] == $v['role_id']) {
                    $roleArr[$key]['status'] = $v['status'];
                }
            }
        }

        // 车库
        $res = Db::Table('singleMotor_garages_class')->where(['is_show' => 1])->select();
        $arr = Db::Table('singleMotor_garages')->select();
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
            'role' => json_encode($roleArr, JSON_UNESCAPED_UNICODE),
            'is_role' => json_encode($userInfo['is_role']),
            'end_time' => date('Y-m-d H:i:s'),
            'offline_time' => time(),
        ];
//        dump($upData);
        Db::Table('singleMotor_user')->where(['user_id' => $this->user_id])->update($upData);
        return jsonResult('离线成功', 200, $upData);
    }

    # 离线
    public function offline_new()
    {
        if (empty($this->user_id)) return jsonResult('error', 110);
        $userInfo = input('post.userInfo/a');

        // 车库
        $res = Db::Table('singleMotor_garages_class')->where(['is_show' => 1])->select();
        $arr = Db::Table('singleMotor_garages')->select();
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
//            'system' => $userInfo['system'],
            'car' => json_encode($res, JSON_UNESCAPED_UNICODE),
            'is_car' => json_encode($userInfo['is_car']),
            'offline_time' => time(),
        ];
//        dump($upData);
        Db::Table('singleMotor_user')->where(['user_id' => $this->user_id])->update($upData);
        return jsonResult('离线成功', 200, $res);
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
//        Db::Table('singleMotor_challenge')->insert($insert);
//        return jsonResult('点击游戏开始', 200);

    }

    # 渠道统计
    public function statistics_channel()
    {
        if (empty($this->user_id)) return jsonResult('error', 110);
        $post = request()->only(['channel', 'scene', 'enter_id'], 'post'); //channel 渠道ID 例如：1001  scene 场景值 enter_id 来源小程序ID
        //        $enter_id = $post['enter_id'];
//        $date = date('Y-m-d');

        # 用户从哪个渠道进入
        $record = [
            'user_id' => $this->user_id,
            'enter_id' => $post['enter_id'],
            'channel_id' => $post['channel'],
            'scene' => $post['scene'],
            'add_date' => date('Y-m-d'),
            'add_time' => date('Y-m-d H:i:s'),
        ];
        Db::table('singleMotor_record_channel')->insert($record);
        return jsonResult('渠道统计成功', 200, $record);

//        $user = Db::Table('singleMotor_user')->field('user_id,channel,login_date')->where(['user_id' => $this->user_id])->find();
//        if (empty(intval($user['channel']))) {
//            // 新用户 （用户表channel为空）
//            $qd_id = Db::Table('channel_info')->where(['enter_id' => $post['enter_id']])->value('qd_id');
//            $channel = empty($qd_id) ? $post['channel'] : $qd_id;
//            $upData = [
//                'channel' => $channel,
//                'scene' => $post['scene'],
//                'enter_id' => $post['enter_id'],
//                'login_date' => date('Y-m-d'),
//                'end_time' => date('Y-m-d H:i:s'),
//            ];
//            Db::Table('singleMotor_user')->where(['user_id' => $this->user_id])->update($upData);
//            return jsonResult('渠道新增成功', 200, $upData);
//        } else {
//
//            if (empty($user['login_date'])) {
//                $qd_id = Db::Table('channel_info')->where(['enter_id' => $post['enter_id']])->value('qd_id');
//                $channel = empty($qd_id) ? $post['channel'] : $qd_id;
//                $upData = [
//                    'channel' => $channel,
//                    'scene' => $post['scene'],
//                    'enter_id' => $post['enter_id'],
//                    'login_date' => date('Y-m-d'),
//                ];
//
//                Db::Table('singleMotor_user')->where('user_id', $this->user_id)->update($upData);
//                return jsonResult('渠道更新成功（没login_date）', 300, $upData);
//            }
//            // 老用户
//            if ($user['login_date'] == $date) {
//                return jsonResult('渠道更新失败', 300, $user);
//            }
//
//            $qd_id = Db::Table('channel_info')->where(['enter_id' => $post['enter_id']])->value('qd_id');
//            $channel = empty($qd_id) ? $post['channel'] : $qd_id;
//            $upData = [
//                'channel' => $channel,
//                'scene' => $post['scene'],
//                'enter_id' => $post['enter_id'],
//                'login_date' => date('Y-m-d'),
//            ];
//            Db::Table('singleMotor_user')->where(['user_id' => $this->user_id])->update($upData);
//            return jsonResult('渠道更新成功', 200, $upData);
//        }
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
            $app_id = Db::Table('singleMotor_app')->where(['app_id' => $app_id, 'play_status' => 0])->value('id');
        }

        $is_click = Db::Table('singleMotor_statistics_adv')->where(['user_id' => $this->user_id, 'app_id' => $post['app_id'], 'status' => 1, 'add_time' => $date])->value('statistics_id');

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
            Db::Table('singleMotor_statistics_adv')->insert($advData);

            if ($post['status'] == 1) {
                # 广告限量
                $restrict_id = Db::Table('singleMotor_restrict')->where(['app_id' => $app_id, 'add_time' => $date])->value('restrict_id');
                if (empty($restrict_id)) {
                    $addData = [
                        'app_id' => $app_id,
                        'num' => 1,
                        'add_time' => $date,
                    ];
                    Db::Table('singleMotor_restrict')->insert($addData);
                } else {
                    Db::Table('singleMotor_restrict')->where(['restrict_id' => $restrict_id])->setInc('num');
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
        Db::Table('singleMotor_watch_video')->insert($post);
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
//            'add_date' => date('Y-m-d'),
//            'add_time' => date('Y-m-d H:i:s'),
//        ];
//        Db::Table('singleMotor_record')->insert($insert);
//        return jsonResult('用户行为记录成功', 200);
    }

    # 免费加速
    public function freeAcceleration()
    {
        $insert = [
            'user_id' => $this->user_id,
            'add_time' => date('Y-m-d'),
        ];
        Db::Table('singleMotor_free_acceleration')->insert($insert);
        return jsonResult('用户行为记录成功', 200);
    }

    public function demo()
    {


//        # 根据IP显示误点开关
//        $ip = request()->ip(0, true);
//        $url = 'http://ip.taobao.com/service/getIpInfo.php?ip=' . $ip;
////        $url = 'http://int.dpool.sina.com.cn/iplookup/iplookup.php?format=json&ip=' . $ip;
//        $data = $this->curl_get($url);
//        $data = json_decode($data, true);
//        $city = $data['data']['city'];
////        $city = '北京';
//        $cityArr = ['北京', '上海', '深圳'];
//        if (in_array($city, $cityArr)) {
//            $data['isCanErrorClick'] = 0;
//        } else {
//            $data['isCanErrorClick'] = 1;
//        }

//        echo phpinfo();
//        $map_config = config('car_map_config_2');
//        $arr = [];
//        foreach ($map_config as $k => $v) {
//            $num = 0;
//            foreach ($v['mapArr'] as $i => $j) {
//                if($j['map_id'] < 20){
//                    $num += 1000;
//                }
//                if ($j['map_id'] >=20) {
//                    $num += 2000;
//                }
//                $arr[$k]['pass_id'] = $v['pass_id'];
//                $arr[$k]['px'] = $num;
//            }
//        }
//
//        return jsonResult('succ', 200, $arr);


//        # 地图配置
//        $map_config = config('car_map_config');
////        dump($map_config);
////        die;
//
//        foreach ($map_config as $k => $v) {
//                $map_config[$k]['px'] = count($v['mapArr']) * 2000;
//        }
//        $data['map_config'] = $map_config;

    }


}