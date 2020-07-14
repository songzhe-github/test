<?php
/**
 * QQ 雷电之翼
 * User: SongZhe
 * Date: 2019/5/31
 * Time: 10:19
 */

namespace app\index\controller;

use think\Db;

//date_default_timezone_set('Asia/Shanghai');

class Tgalaxide extends Conmmon
{
    /**
     * 获取code，返回openid（星际英雄）
     */
    public function getCode()
    {
        if (request()->isPost()) {
            $code = input('post.code');
            if (empty($code)) return jsonResult('你瞅啥', 100);
            $appid = 'tt68bd1e00f7875c03';
            $appsecret = '12a082a7a1210bc040b1c34389ccb3a702fb3449';
            $data = $this->getTTOpenid($appid, $appsecret, $code); // 获得 openid 和 session_key

            $db = Db::connect('multi-platform');
            $user = $db->table('TT_galaxide_user')->field('user_id,openid,status,new_player_status')->where(['openid' => $data['openid']])->find();
            if (empty($user)) {
                $arr = [
                    'openid' => $data['openid'],
                    'user_name' => '',
                    'avatar' => 'https://yxdrs.zsmgc.com.cn/A/public/app/wdl.png',
                    'add_time' => date('Y-m-d'),
                    'timestamp' => time(),
                    'end_time' => date('Y-m-d H:i:s'),
                ];
                $uid = $db->table('TT_galaxide_user')->insertGetId($arr);
                $res['mstr'] = lock_url($data['openid'] . ',' . $uid);
                return jsonResult('请求成功', 200, $res);

            } else {
                $db->table('TT_galaxide_user')->where(['user_id' => $user['user_id']])->update(['end_time' => date('Y-m-d H::s')]);
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

        $db = Db::connect('multi-platform');
        $user = $db->table('TT_galaxide_user')->where(['user_id' => $this->user_id])->value('user_id');
        if (empty($user)) return jsonResult('数据不存在', 100);

        $updata = [
            'user_name' => empty($user_name) ? '' : $user_name,
            'avatar' => empty($avatar) ? '' : $avatar,
            'sex' => empty($sex) ? '' : $sex,
            'city' => empty($city) ? '' : $city,
            'end_time' => date('Y-m-d H:i:s'),
        ];
        $db->table('TT_galaxide_user')->where(['user_id' => $this->user_id])->update($updata);
        return jsonResult('用户信息', 200);
    }

    public function home()
    {
        $version_number = input('post.version_number');

        # 分享图
        $data['share'] = config('share.QQ_galaxide_share_pic');

        $db = Db::connect('multi-platform');
        # 首页用户信息
        $user = $db->table('TT_galaxide_user')
            ->field('user_id,user_name,avatar,coin,role_id,max_pass,new_player_status,scene,get_free_coin_time,skill,role,free_coin')
            ->where(['user_id' => $this->user_id])
            ->find();

        $user['skill'] = json_decode($user['skill'], true);
        if (empty($user['skill'])) $user['skill'] = [
            'skill1' => 1,
            'skill2' => 1
        ];

        $user['free_coin'] = empty($user['free_coin']) ? [
            'free_coin' => 0,
            'made_coin_lv' => 1,
            'addition_coin_lv' => 1,
        ] : json_decode($user['free_coin'], true);

        # 用户的机甲
        $shop = $db->table('TT_galaxide_shop')->select();
        $roles = json_decode($user['role'], true);
        if (!empty($roles)) {
            foreach ($shop as $k => $v) {
                foreach ($roles as $i => $j) {
                    if ($v['role_id'] == $j['role_id']) {
                        $shop[$k]['role_atk_lv'] = $j['role_atk_lv'];
                        $shop[$k]['role_speed_lv'] = $j['role_speed_lv'];
                        $shop[$k]['role_unlock_coin'] = $j['role_unlock_coin'];
                        $shop[$k]['status'] = $j['status'];
                    }
                }
            }
            $user['role'] = $shop;
        } else {
            $user['role'] = $shop;

        }

        # 离线收益
        $offline_coin = 0;
        if ($user['get_free_coin_time'] + 10 < time()) {
            $offline_coin += floor((time() - $user['get_free_coin_time']) / 10);
            if ($offline_coin > 100) {
                $user['free_coin']['free_coin'] = 100;
            } else {
                $user['free_coin']['free_coin'] = $offline_coin;
            }
        }
        $data['userInfo'] = $user;

        # 金币配置
        $data['free_coin'] = $db->table('TT_galaxide_free_coin')->select();

        # 关卡配置
        $pass = $db->table('TT_galaxide_pass2')->select();
        $data['pass'] = $pass;

        # 开关配置 （渠道进入才有误点）
        $config = $db->table('TT_galaxide_config')->field('isCanErrorClick,record_status,version_number')->find();
        if ($version_number != $config['version_number']) {
//        if ($version_number != $config['version_number'] || $user['scene'] != 1037) {
            $config['isCanErrorClick'] = 0;
        }
        $data['config'] = $config;

        # 角色配置
        $role = $db->table('TT_galaxide_role')
            ->field('role_id,role_name,role_lv,role_hp,role_up_coin,role_speed,role_speed,role_hz,role_atk,role_atk_show')
            ->select();
        foreach ($role as &$v) {
            $v['role_atk_bfb'] = $v['role_lv'] * 10 + 100;
            $v['role_speed_bfb'] = $v['role_lv'] * 10 + 100;
        }
        $data['role'] = $role;
        $data['skill_stk'] = 0.5;
        $db->table('TT_galaxide_user')->where(['user_id' => $user['user_id']])->update(['end_time' => date('Y-m-d H::s'), 'get_free_coin_time' => time()]);

        return jsonResult('succ', 200, $data);
    }

    # 排行榜
    public function rank()
    {
        $db = Db::connect('multi-platform');
        $ranks = $db->table('TT_galaxide_rank')->select();
        $userIds = array_column($ranks, 'user_id');
        $is_rank = array_keys($userIds, $this->user_id);

        $user = $db->table('TT_galaxide_user')->field('user_id,user_name,avatar,max_pass')->where(['user_id' => $this->user_id])->find();
        if (empty($is_rank)) {
            if (empty($ranking)) {
//                $ranking = $db->table('TT_galaxide_user')->where(['max_pass' => ['>', $user['max_pass']]])->count();
                $ranking = rand(100, 1000);
            }
        } else {
            $ranking = $is_rank[0] + 1;
        }

        $user_rank = [
            'user_id' => $this->user_id,
            'user_name' => $user['user_name'],
            'avatar' => $user['avatar'],
            'max_pass' => $user['max_pass'],
            'ranking' => $ranking,
        ];

        $data['ranks'] = $ranks;
        $data['user_rank'] = $user_rank;

        return jsonResult('排行榜', 200, $data);
    }

    # 签到列表
    public function signList()
    {
        $date = date('Y-m-d');
        $db = Db::connect('multi-platform');
        $signList = $db->table('TT_galaxide_signlist')->field('day,sign_num,type,status')->select();
        $userList = $db->table('TT_galaxide_sign')->where(['user_id' => $this->user_id, 'status' => 0])->select();

        $end = end($userList);
        if ($end['add_time'] < $date && count($userList) >= 7) {
            # 满一周status更新为0
            $db->table('TT_galaxide_sign')->where(['user_id' => $this->user_id])->update(['status' => 1]);
            $userList = [];
        }

        foreach ($signList as &$v) {
            foreach ($userList as &$j) {
                if ($v['day'] == $j['day']) {
                    $v['status'] = 1;
                }
            }
        }

        # 今天是否签到
        $data['is_sign'] = $end['add_time'] < $date ? 0 : 1;
        $data['day'] = count($userList);
        $data['signList'] = $signList;

        return jsonResult('签到列表', 200, $data);
    }

    # 点击签到
    public function sign()
    {
        $date = date('Y-m-d');
        $db = Db::connect('multi-platform');
        $user_sign = $db->table('TT_galaxide_sign')->where(['user_id' => $this->user_id, 'status' => 0])->select();
        $end = end($user_sign);
        if ($end['add_time'] == $date) {
            return jsonResult('今日已签到过', 100);
        }

        $day = $end['day'] == 7 ? 1 : $end['day'] + 1;
        $insert = [
            'user_id' => $this->user_id,
            'day' => $day,
            'add_time' => $date,
            'status' => 0,
        ];
        $db->table('TT_galaxide_sign')->insert($insert);

        return jsonResult('签到成功', 200);
    }

    # 升级角色
    public function upRole()
    {
        $role_id = Db::connect('multi-platform')->table('TT_galaxide_user')->where(['user_id' => $this->user_id])->value('role_id');

    }

    public function challenge()
    {
        $post = request()->only(['pass', 'status'], 'post'); // status 1成功 2失败
        if (empty($this->user_id)) return jsonResult('error', 100);
        $db = Db::connect('multi-platform');
        $max_pass = $db->table('TT_galaxide_user')->where(['user_id' => $this->user_id])->value('max_pass');

        if ($post['pass'] > $max_pass) {
            $db->table('TT_galaxide_user')->where(['user_id' => $this->user_id])->update(['max_pass' => $post['pass']]);
        }

        $insert = [
            'user_id' => $this->user_id,
            'pass' => $post['pass'],
            'status' => $post['status'],
            'text' => $post['status'] == 1 ? '成功' : '失败',
            'add_time' => date('Y-m-d'),
        ];
        $db->table('TT_galaxide_challenge')->insert($insert);
        return jsonResult('闯关记录成功', 200);
    }

    # 离线记录
    public function offline()
    {
        if (empty($this->user_id)) return jsonResult('error', 100);
        $post = request()->only(['coin', 'role_id', 'offline_coin', 'skill'], 'post');
        $skill = input('post.skill/a');
        if ($skill) {
            $post['skill'] = json_encode($skill, JSON_UNESCAPED_UNICODE);
        }
        $post['get_free_coin_time'] = time();
//        dump($post);die;
        Db::connect('multi-platform')->table('TT_galaxide_user')->where(['user_id' => $this->user_id])->update($post);
        return jsonResult('离线记录成功', 200);
    }

    # 离线记录
    public function offline_new()
    {
        if (empty($this->user_id)) return jsonResult('error', 100);
        $userInfo = input('post.userInfo/a');
        $userInfo['role'] = json_encode($userInfo['role']);
        $userInfo['skill'] = json_encode($userInfo['skill']);
        $userInfo['free_coin'] = json_encode($userInfo['free_coin']);
        $userInfo['get_free_coin_time'] = time();
        Db::connect('multi-platform')->table('TT_galaxide_user')->where(['user_id' => $this->user_id])->update($userInfo);
        return jsonResult('离线记录成功', 200);
    }

    public function adverts()
    {
        $date = date('Y-m-d');
        $db = Db::connect('multi-platform');
        $click_today = $db->table('TT_galaxide_statistics_adv')->where(['user_id' => $this->user_id, 'status' => 1, 'add_time' => $date])->column('app_id');
        $click = $db->table('TT_galaxide_statistics_adv')->where(['user_id' => $this->user_id, 'status' => 1])->column('app_id');

        # 点击过和限量的广告ID
        $restrict = $db->table('TT_galaxide_app')
            ->alias('app')
            ->join(['TT_galaxide_restrict ' => 'r'], 'app.id=r.app_id and r.num >= app.restrict')
            ->where(['r.add_time' => $date])
            ->column('app.id');
        $not = array_unique(array_merge($click_today, $restrict)); //合并去重
        $banner_not = array_unique(array_merge($click, $restrict)); //合并去重

        # 猜你喜欢
        $res = $db->table('TT_galaxide_app')
            ->field('id,app_id,app_name,app_text,app_url,app_qrcode_url,app_more_url,page')
            ->where(['play_status' => 0, 'id' => ['NOT IN', $not]])
//            ->limit(8)
            ->order('play_sort')
            ->select();
        $num = count($res);
        // 少于四个按顺序推送未达到限制的
        if ($num < 8) {
            $not1 = array_column($res, 'id');
            $not2 = array_unique(array_merge($not1, $restrict));
            $limit = 8 - $num;
            $app1 = $db->table('TT_galaxide_app')
                ->field('id,app_id,app_name,app_url,app_more_url,app_qrcode_url,page,play_sort')
                ->where(['id' => ['NOT IN', $not2], 'play_status' => 0])
                ->order('RAND()')
                ->limit($limit)
                ->select();
            $res = array_merge($res, $app1);
        }
        $data['like'] = $res;

        # banner广告
        $banner = $db->table('TT_galaxide_app')
            ->field('id,app_id,app_name,app_text,app_qrcode_url,app_banner_url,page')
            ->where(['id' => ['NOT IN', $banner_not], 'banner_status' => 0])
            ->order('banner_sort')
            ->select();
        $data['banner'] = $banner;
        return jsonResult('广告列表', 200, $data);
    }

    public function more()
    {
        $date = date('Y-m-d');
        $db = Db::connect('multi-platform');
        $click = $db->table('TT_galaxide_statistics_adv')->where(['user_id' => $this->user_id, 'status' => 1, 'add_time' => $date])->column('app_id');
        # 点击过和限量的广告ID
        $restrict = $db->table('TT_galaxide_app')
            ->alias('app')
            ->join(['TT_galaxide_restrict ' => 'r'], 'app.id=r.app_id and r.num >= app.restrict')
            ->where(['r.add_time' => $date])
            ->column('app.id');
        $not = array_unique(array_merge($click, $restrict)); //合并去重

        # 猜你喜欢
        $res = $db->table('TT_galaxide_app')
            ->field('id,app_id,app_name,app_text,app_url,app_qrcode_url,app_more_url,page')
            ->where(['play_status' => 0, 'id' => ['NOT IN', $not]])
//            ->limit(8)
            ->order('play_sort')
            ->select();
        $num = count($res);
        // 少于四个按顺序推送未达到限制的
        if ($num < 8) {
            $not1 = array_column($res, 'id');
            $not2 = array_unique(array_merge($not1, $restrict));
            $limit = 8 - $num;
            $app1 = $db->table('TT_galaxide_app')
                ->field('id,app_id,app_name,app_url,app_more_url,app_qrcode_url,page,play_sort')
                ->where(['id' => ['NOT IN', $not2], 'play_status' => 0])
                ->order('RAND()')
                ->limit($limit)
                ->select();
            $res = array_merge($res, $app1);
        }
        $data['like'] = $res;

        # banner广告
        $banner = $db->table('TT_galaxide_app')
            ->field('id,app_id,app_name,app_text,app_qrcode_url,app_banner_url,page')
            ->where(['id' => ['NOT IN', $not], 'banner_status' => 0])
            ->order('banner_sort')
            ->select();
        $banner_num = count($banner);
        if ($banner_num < 5) {
            $limit = 8 - $banner_num;
            $banner_not = array_column($banner, 'id');
            $banner1 = $db->table('TT_galaxide_app')
                ->field('id,app_id,app_name,app_text,app_qrcode_url,app_banner_url,page')
                ->where(['id' => ['NOT IN', $banner_not], 'banner_status' => 0])
                ->order('RAND()')
                ->limit($limit)
                ->select();
            $banner = array_merge($banner, $banner1);
        }
        $data['banner'] = $banner;
        return jsonResult('广告列表', 200, $data);

    }

    // 数据统计
    public function statistics()
    {
        $user_id = $this->user_id;
        if (empty($user_id)) return jsonResult('error', 110);

//        $date = date('Y-m-d');
        $post = request()->only(['qd_id', 'type', 'scene', 'enter_id'], 'post'); // scene 场景值 enter_id 来源小程序ID
//        $qd_id = input('post.qd_id'); //前端传过来的渠道ID
        $enter_id = $post['enter_id'];

        $db = Db::connect('multi-platform');
        $is_channel = $db->table('TT_galaxide_user')->field('user_id,channel,enter_id')->where(['user_id' => $this->user_id])->find();

//        dump($is_channel);die;
        if (empty(intval($is_channel['channel']))) { // 用户表channel为空

            if ($is_channel['enter_id']) { // 用户表有enter_id channel为0
                $channel = $db->table('TT_galaxide_channel')->where(['enter_id' => $is_channel['enter_id']])->value('qd_id');
                $upData = [
                    'channel' => $channel,
                    'end_time' => date('Y-m-d H:i:s'),
                ];

            } else { // 新用户
                $channel2 = $db->table('TT_galaxide_channel')->where(['enter_id' => $enter_id])->value('qd_id');
                if ($channel2) {
                    $upData = [
                        'channel' => $channel2,
                        'scene' => $post['scene'],
                        'enter_id' => $enter_id,
                        'end_time' => date('Y-m-d H:i:s'),
                    ];
                } else {
                    $upData = [
                        'channel' => $post['qd_id'],
                        'scene' => $post['scene'],
                        'enter_id' => $enter_id,
                        'end_time' => date('Y-m-d H:i:s'),
                    ];
                }
            }
//            dump($upData);die;
            $db->table('TT_galaxide_user')->where(['user_id' => $user_id])->update($upData);
            return jsonResult('渠道记录成功', 200, $upData);

        } else {
            return jsonResult('渠道已存在', 200);
        }

    }

    # 广告统计
    public function statistics_adv()
    {
        $user_id = $this->user_id;
        $date = date('Y-m-d');
        $post = request()->only(['app_id', 'type', 'position', 'status', 'pass', 'win_status'], 'post');
        if (empty($user_id) || empty($post['app_id'])) return jsonResult('error', 110);

        # 广告统计
        $app_id = $post['app_id'];

        $text = '';
        if ($post['win_status'] == 1) {
            $text = '成功';
        } elseif ($post['win_status'] == 2) {
            $text = '失败';
        }

        $db = Db::connect('multi-platform');
        if (strlen($app_id) > 6) {
            $app_id = $db->table('TT_galaxide_app')->where(['app_id' => $app_id])->value('id');
        }

        $is_click = $db->table('TT_galaxide_statistics_adv')->where(['user_id' => $this->user_id, 'app_id' => $post['app_id'], 'status' => 1, 'add_time' => $date])->value('statistics_id');

        if (empty($is_click)) {
            $advData = [
                'user_id' => $user_id,
                'app_id' => $app_id,
                'type' => $post['type'],
                'position' => $post['position'],
                'classify' => empty($classify) ? 0 : $classify,
                'add_time' => date('Y-m-d'),
                'num' => 1,
                'pass' => $post['pass'],
                'text' => $text,
                'timestamp' => time(),
                'status' => $post['status'],
            ];
            $db->table('TT_galaxide_statistics_adv')->insert($advData);

            if ($post['status'] == 1) {
                # 广告限量
                $restrict_id = $db->table('TT_galaxide_restrict')->where(['app_id' => $app_id, 'add_time' => $date])->value('restrict_id');
                if (empty($restrict_id)) {
                    $addData = [
                        'app_id' => $app_id,
                        'num' => 1,
                        'add_time' => $date,
                    ];
                    $db->table('TT_galaxide_restrict')->insert($addData);
                } else {
                    $db->table('TT_galaxide_restrict')->where(['restrict_id' => $restrict_id])->setInc('num');
                }
            }
            return jsonResult('点击广告记录成功', 200);
        } else {
            return jsonResult('今天广告已记录过', 100);

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
        Db::connect('multi-platform')->table('TT_galaxide_record')->insert($data);
        return jsonResult('记录成功', 200);
    }

    # 记录用户观看视频（1.暂停 2.技能1 3.技能2）
    public function watchVideo()
    {
        $post = request()->only(['type', 'text', 'pass'], 'post');
        $post['user_id'] = $this->user_id;
        $post['add_time'] = date('Y-m-d');
        $post['timestamp'] = time();
        Db::connect('multi-platform')->table('TT_galaxide_watch_video')->insert($post);
        return jsonResult('记录成功', 200);
    }


}