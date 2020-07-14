<?php
/**
 * 僵尸猎人
 * User: Sz105
 * Date: 2018/3/20
 * Time: 14:11
 */

namespace app\index\controller;

use function PHPSTORM_META\type;
use think\Db;

class Hunter extends Conmmon
{
    # 排行榜
    public function cronTab_rank()
    {
        # 世界排行榜
        $insert = Db::table('hunter_user')->field('user_id,user_name,avatar,max_compound_role,now() as add_time')->order('max_compound_role DESC')->limit(20)->select();
//        dump($insert);
//        die;
        $rank = Db::table('hunter_rank')->select();

        if (empty($rank)) {
            Db::table('hunter_rank')->insertAll($insert);
        } else {
            foreach ($insert as $k => $v) {
                if (empty($v['user_name'])) {
                    $v['user_name'] = '游客';
                }
                Db::table('hunter_rank')->where(['rank_id' => $k + 1])->update($v);
            }
        }


        # 巅峰赛排行榜
        $date = date('Y-m-d', strtotime('-1day'));
//        $date = date('Y-m-d');
        $peak = Db::query("SELECT t.user_id,u.user_name,u.avatar,t.pass_id,t.num,t.add_time FROM (SELECT * FROM `hunter_peak` ORDER BY `num` DESC) t LEFT JOIN hunter_user AS u ON t.user_id=u.user_id WHERE t.add_time = '" . $date . "' GROUP BY t.pass_id");
        foreach ($peak as &$v) {
            $v['user_name'] = empty($v['user_name']) ? '游客' : $v['user_name'];
        }
//        dump($peak);
//        die;
        Db::table('hunter_peaklist')->insertAll($peak);
    }


    /**
     * 获取code，返回openid（弹个鬼）
     */
    public function getHunterCode()
    {
        if (request()->isPost()) {
            $code = input('post.code');
            if (empty($code)) return jsonResult('你瞅啥', 100);
            $data = $this->get_hunter_openid($code); // 获得 openid 和 session_key
            $user = Db::table('hunter_user')->field('user_id,openid,status')->where(['openid' => $data['openid']])->find();

            if (empty($user)) {
                $arr = [
                    'openid' => $data['openid'],
                    'user_name' => '',
                    'avatar' => 'https://yxdrs.zsmgc.com.cn/A/public/app/wdl.png',
                    'add_time' => date('Y-m-d'),
                    'end_time' => date('Y-m-d H:i:s'),
                    'offline_time' => time(),
                ];
                $uid = Db::table('hunter_user')->insertGetId($arr);
                $res['mstr'] = lock_url($data['openid'] . ',' . $uid);

                return jsonResult('请求成功', 200, $res);

            } else {
                Db::table('hunter_user')->where(['user_id' => $user['user_id']])->update(['end_time' => date('Y-m-d H::s')]);
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

        $user = Db::table('hunter_user')->where(['user_id' => $this->user_id])->value('user_id');
        if (empty($user)) return jsonResult('数据不存在', 100);

        $updata = [
            'user_name' => empty($user_name) ? '' : $user_name,
            'avatar' => empty($avatar) ? '' : $avatar . '?aaa=aa.jpg',
            'sex' => empty($sex) ? '' : $sex,
            'city' => empty($city) ? '' : $city,
            'end_time' => date('Y-m-d H:i:s'),
        ];
        Db::table('hunter_user')->where(['user_id' => $this->user_id])->update($updata);
        return jsonResult('用户信息', 200);
    }

    # 首页 线下测试
    public function index()
    {
        if (empty($this->user_id)) return jsonResult('error', 110);
        $time = time();

        # 分享文案
        $share = Db::table('hunter_share_pic')->order('rand()')->select();
        $data['share'] = $share;

        # 首页用户信息
        $user = Db::table('hunter_user')
            ->field('user_id,all_coin,phase,max_pass,max_compound_role,diamond,compoundArr,abilityArr,partnerArr,isPartnerArr,offline_time,new_player_status')
            ->where(['user_id' => $this->user_id])
            ->find();

        # 离线奖励
        $time_num = $time - $user['offline_time'] < 180 ? 0 : $time - $user['offline_time'];
        if ($time_num) {
            $offline_time = $time_num > 360 ? 360 : $time_num;
            $num = floor($offline_time / 60) + 2;
            $user['offline_coin'] = $num * 100 * pow(2, $user['max_compound_role'] - 5) + 5000;
        } else {
            $user['offline_coin'] = 0;
        }

        $partner = Db::table('hunter_partner')->select();
        $a = '[{"posIndex":0,"id":1},{"posIndex":1,"id":0},{"posIndex":2,"id":0},{"posIndex":3,"id":0},{"posIndex":4,"id":0},{"posIndex":5,"id":0},{"posIndex":6,"id":0},{"posIndex":7,"id":0},{"posIndex":8,"id":0},{"posIndex":9,"id":0},{"posIndex":10,"id":0},{"posIndex":11,"id":0},{"posIndex":12,"id":0},{"posIndex":13,"id":0},{"posIndex":14,"id":0},{"posIndex":15,"id":0}]';
        $user['compoundArr'] = empty(json_decode($user['compoundArr'])) ? json_decode($a) : json_decode($user['compoundArr']);
        $user['abilityArr'] = empty(json_decode($user['abilityArr'])) ? [0, 0, 0] : json_decode($user['abilityArr']);
        $user['partnerArr'] = empty(json_decode($user['partnerArr'], true)) ? $partner : json_decode($user['partnerArr'], true);
        $user['isPartnerArr'] = empty(json_decode($user['isPartnerArr'])) ? [0, 0] : json_decode($user['isPartnerArr']);
        $user['all_coin'] = intval($user['all_coin']);
        $data['userInfo'] = $user;

        # 角色配置
        $data['role'] = Db::table('hunter_role')->select();

        # 角色属性
        $data['property'] = Db::table('hunter_property')->select();

        # 关卡配置
        $pass = Db::table('hunter_pass')->select();
        foreach ($pass as &$k) {
            $k['small_coin'] = intval($k['small_coin']);
            $k['boss_coin'] = intval($k['boss_coin']);
            $k['level_coin'] = intval($k['level_coin']);
        }
        $data['pass'] = $pass;

        # 误点开关
        $data['config'] = Db::table('hunter_config')->field('isCanErrorClick')->find();

        # 巅峰赛配置
        $peak_config = Db::table('hunter_peak_config')->find();
        $data['peak_config'] = $peak_config;

        Db::table('hunter_user')->where(['user_id' => $user['user_id']])->update(['end_time' => date('Y-m-d H::s'), 'offline_time' => $time, 'new_player_status' => 1]);
        return jsonResult('succ', 200, $data);
    }

    # 首页
    public function home()
    {
        if (empty($this->user_id)) return jsonResult('error', 110);
        $time = time();

        # 分享文案
        $share = Db::table('hunter_share_pic')->order('rand()')->select();
        $data['share'] = $share;

        # 首页用户信息
        $user = Db::table('hunter_user')
            ->field('user_id,all_coin,phase,max_pass,max_compound_role,diamond,compoundArr,abilityArr,partnerArr,isPartnerArr,offline_time,new_player_status')
            ->where(['user_id' => $this->user_id])
            ->find();

        # 离线奖励
        $time_num = $time - $user['offline_time'] < 180 ? 0 : $time - $user['offline_time'];
        if ($time_num) {
            $offline_time = $time_num > 360 ? 360 : $time_num;
            $num = floor($offline_time / 60) + 2;
            $user['offline_coin'] = $num * 100 * pow(2, $user['max_compound_role'] - 5) + 5000;
        } else {
            $user['offline_coin'] = 0;
        }

        $partner = Db::table('hunter_partner')->select();
        $a = '[{"posIndex":0,"id":1},{"posIndex":1,"id":0},{"posIndex":2,"id":0},{"posIndex":3,"id":0},{"posIndex":4,"id":0},{"posIndex":5,"id":0},{"posIndex":6,"id":0},{"posIndex":7,"id":0},{"posIndex":8,"id":0},{"posIndex":9,"id":0},{"posIndex":10,"id":0},{"posIndex":11,"id":0},{"posIndex":12,"id":0},{"posIndex":13,"id":0},{"posIndex":14,"id":0},{"posIndex":15,"id":0}]';
        $user['compoundArr'] = empty(json_decode($user['compoundArr'])) ? json_decode($a) : json_decode($user['compoundArr']);
        $user['abilityArr'] = empty(json_decode($user['abilityArr'])) ? [0, 0, 0] : json_decode($user['abilityArr']);
        $user['partnerArr'] = empty(json_decode($user['partnerArr'], true)) ? $partner : json_decode($user['partnerArr'], true);
        $user['isPartnerArr'] = empty(json_decode($user['isPartnerArr'])) ? [0, 0] : json_decode($user['isPartnerArr']);
        $user['all_coin'] = intval($user['all_coin']);
        $data['userInfo'] = $user;

        # 角色配置
        $data['role'] = Db::table('hunter_role')->select();

        # 角色属性
        $data['property'] = Db::table('hunter_property')->select();

        # 关卡配置
        $pass = Db::table('hunter_pass')->select();
        foreach ($pass as &$k) {
            $k['small_coin'] = intval($k['small_coin']);
            $k['boss_coin'] = intval($k['boss_coin']);
            $k['level_coin'] = intval($k['level_coin']);
        }
        $data['pass'] = $pass;

        # 误点开关
        $data['config'] = Db::table('hunter_config')->field('isCanErrorClick')->find();

        # 巅峰赛配置
        $peak_config = Db::table('hunter_peak_config')->find();
        $data['peak_config'] = $peak_config;

        Db::table('hunter_user')->where(['user_id' => $user['user_id']])->update(['end_time' => date('Y-m-d H::s'), 'offline_time' => $time, 'new_player_status' => 1]);
        return jsonResult('succ', 200, $data);
    }


    # 排行榜
    public function rank()
    {
        $ranks = Db::table('hunter_rank')->select();
        $userIds = array_column($ranks, 'user_id');
        $is_rank = array_keys($userIds, $this->user_id);

        $user = Db::table('hunter_user')->field('user_id,user_name,avatar,max_compound_role')->where(['user_id' => $this->user_id])->find();
        if (empty($is_rank)) {
            if (empty($ranking)) {
//                $ranking = Db::table('hunter_user')->where(['max_compound_role' => ['>', $user['max_compound_role']]])->count();
                $ranking = rand(100, 5000);
            }
        } else {
            $ranking = $is_rank[0] + 1;
        }

        $user_rank = [
            'user_id' => $this->user_id,
            'user_name' => $user['user_name'],
            'avatar' => $user['avatar'],
            'role_id' => $user['max_compound_role'], // 合成最高等级
            'max_compound_role' => $user['max_compound_role'], // 合成最高等级
            'ranking' => $ranking,
        ];

        $data['ranks'] = $ranks;
        $data['user_rank'] = $user_rank;

        return jsonResult('排行榜', 200, $data);
    }

    # 签到
    public function signList()
    {
        $date = date('Y-m-d');
        $signList = Db::table('hunter_signlist')->field('day,sign_num,type,status')->select();
        $userList = Db::table('hunter_sign')->where(['user_id' => $this->user_id, 'status' => 0])->select();

        $end = end($userList);
        if ($end['add_time'] < $date && count($userList) >= 7) {
            # 满一周status更新为0
            Db::table('hunter_sign')->where(['user_id' => $this->user_id])->update(['status' => 1]);
            $userList = [];
        }

        foreach ($signList as &$v) {
            if ($v['day'] == 7) {
                $v['sign_num'] = array_map('intval', explode(',', $v['sign_num']));
            } else {
                $v['sign_num'] = intval($v['sign_num']);
            }
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
        $user_sign = Db::table('hunter_sign')->where(['user_id' => $this->user_id, 'status' => 0])->select();
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
        Db::table('hunter_sign')->insert($insert);

        return jsonResult('签到成功', 200);
    }

    # 伙伴
    public function partner()
    {
        $partner = Db::table('hunter_partner')->select();
        $count = Db::table('hunter_sign')->where(['user_id' => $this->user_id])->count();
        $video = Db::table('hunter_watch_video')->where(['user_id' => $this->user_id])->count();
        $invite = Db::table('hunter_invite')->where(['user_id' => $this->user_id, 'type' => 1])->count();
        $user = Db::table('hunter_user')->field('partnerArr,isPartnerArr')->where(['user_id' => $this->user_id])->find();
//        dump($user);die;
        $isPartnerArr = empty(json_decode($user['isPartnerArr'])) ? [0, 0] : json_decode($user['isPartnerArr']);
        $Arr = empty(json_decode($user['partnerArr'], true)) ? $partner : json_decode($user['partnerArr'], true);
//        dump($Arr);
//        die;

        foreach ($Arr as $k => $v) {
            if ($v['partner_unlock_type'] == 1) {
                if ($count >= $v['partner_unlock_num']) {
                    $Arr[$k]['partner_unlock_state'] = 1;
                }
            }
            if ($v['partner_unlock_type'] == 2) {
                if ($video >= $v['partner_unlock_num']) {
                    $Arr[$k]['partner_unlock_state'] = 1;
                }
            }
            if ($v['partner_unlock_type'] == 3) {
                if ($invite >= $v['partner_unlock_num']) {
                    $Arr[$k]['partner_unlock_state'] = 1;
                }
            }
            if (in_array($v['partner_id'], $isPartnerArr)) {
                $Arr[$k]['partner_unlock_state'] = 2;
            }

        }

        return jsonResult('伙伴列表', 200, $Arr);
    }

    # 离线 线下准备上传的
    public function offline()
    {
        $post = request()->only(['max_pass', 'coin', 'max_compound_role', 'diamond'], 'post');
        $data1 = input('post.compoundArr/a');
        $data2 = input('post.abilityArr/a');
        $data3 = input('post.partnerArr/a');
        $data4 = input('post.isPartnerArr/a');
        $compoundArr = json_encode($data1);
        $abilityArr = json_encode($data2);
        $partnerArr = json_encode($data3, JSON_UNESCAPED_UNICODE);
        $isPartnerArr = json_encode($data4);

        $user = Db::table('hunter_user')->field('max_pass')->where(['user_id' => $this->user_id])->find();
        if ($user) {
            $upData = [
                'all_coin' => empty($post['coin']) ? 0 : $post['coin'],
                'diamond' => empty($post['diamond']) ? 0 : $post['diamond'],
                'compoundArr' => $compoundArr,
                'abilityArr' => $abilityArr,
                'partnerArr' => $partnerArr,
                'isPartnerArr' => $isPartnerArr,
                'max_compound_role' => $post['max_compound_role'],
                'max_pass' => $post['max_pass'] > $user['max_pass'] ? $post['max_pass'] : $user['max_pass'],
                'offline_time' => time(),
            ];
            Db::table('hunter_user')->where(['user_id' => $this->user_id])->update($upData);
            return jsonResult('离线记录成功', 200, $upData);
        } else {
            return jsonResult('用户不存在', 100);
        }
    }

    # 离线
    public function offline_new()
    {
        $post = request()->only(['max_pass', 'coin', 'max_compound_role', 'diamond'], 'post');
        $data1 = input('post.compoundArr/a');
        $data2 = input('post.abilityArr/a');
        $compoundArr = json_encode($data1);
        $abilityArr = json_encode($data2);

        $user = Db::table('hunter_user')->field('max_pass')->where(['user_id' => $this->user_id])->find();
        if ($user) {
            $upData = [
                'all_coin' => empty($post['coin']) ? 0 : $post['coin'],
                'diamond' => empty($post['diamond']) ? 0 : $post['diamond'],
                'compoundArr' => $compoundArr,
                'abilityArr' => $abilityArr,
                'max_compound_role' => $post['max_compound_role'],
                'max_pass' => $post['max_pass'] > $user['max_pass'] ? $post['max_pass'] : $user['max_pass'],
                'offline_time' => time(),
            ];
            Db::table('hunter_user')->where(['user_id' => $this->user_id])->update($upData);
            return jsonResult('离线记录成功', 200, $upData);
        } else {
            return jsonResult('用户不存在', 100);
        }
    }

    # 挑战记录
    public function challenge()
    {
        $post = request()->only(['pass'], 'post');
        $addData = [
            'user_id' => $this->user_id,
            'pass' => $post['pass'],
            'add_time' => date('Y-m-d'),
        ];
        Db::table('hunter_challenge')->insert($addData);

        $user = Db::table('hunter_user')->field('max_pass')->where(['user_id' => $this->user_id])->find();
        if ($post['pass'] > $user['max_pass']) {
            Db::table('hunter_user')->where(['user_id' => $this->user_id])->update(['max_pass' => $post['pass']]);
            return jsonResult('挑战记录成功', 200);
        } else {
            return jsonResult('挑战记录成功', 100);
        }
    }

    # 巅峰赛挑战记录
    public function peak()
    {
        $post = request()->only(['pass_id', 'num'], 'post');
        if (empty($post['pass_id']) || empty($post['num'])) {
            return jsonResult('error', 110);
        }
        $peak = Db::table('hunter_peak')->field('peak_id,num')->where(['user_id' => $this->user_id, 'add_time' => date('Y-m-d')])->find();
        if (empty($peak)) {
            $insert = [
                'user_id' => $this->user_id,
                'pass_id' => $post['pass_id'],
                'num' => $post['num'],
                'add_time' => date('Y-m-d'),
                'timestamp' => time(),
            ];
            Db::table('hunter_peak')->insert($insert);
            return jsonResult('巅峰赛挑战记录新增成功', 200);
        } else {
            if ($post['num'] > $peak['num']) {
                $upData = [
                    'num' => $post['num'],
                    'timestamp' => time(),
                ];
                Db::table('hunter_peak')->where(['peak_id' => $peak['peak_id']])->update($upData);
                return jsonResult('巅峰赛挑战记录更新成功', 200);
            }
            return jsonResult('没有破纪录', 200);
        }
    }

    # 巅峰赛页面数据
    public function peakList()
    {
        $yesterday = date("Y-m-d", strtotime('-1 day'));
        $passs = [1, 2, 3, 4, 5, 6];
        $peakList = Db::table('hunter_peaklist')->field('prize_id,pass_id,user_id,user_name,avatar,num')->where(['add_time' => $yesterday])->select();
        $res = [];
        foreach ($passs as $k => $v) {
            $res[$k]['state'] = 0;
            $res[$k]['is_can_get'] = 0;

            foreach ($peakList as $j => $i) {
                if ($v == $i['pass_id']) {
                    if ($i['user_id'] == $this->user_id) {
                        $res[$k]['is_can_get'] = 1;
                    }

                    $res[$k]['prize_id'] = $i['prize_id'];
                    $res[$k]['pass_id'] = $i['pass_id'];
                    $res[$k]['user_id'] = $i['user_id'];
                    $res[$k]['user_name'] = $i['user_name'];
                    $res[$k]['avatar'] = $i['avatar'];
                    $res[$k]['num'] = $i['num'];
                    $res[$k]['state'] = 1;
                }
            }
        }
        $data['data'] = $res;

        return jsonResult('巅峰赛页面数据', 200, $data);
    }

    # 领取巅峰赛奖励
    public function getPeak()
    {
        $pass_id = input('post.pass_id');
        $yesterday = date("Y-m-d", strtotime('-1 day'));

        $is_prize = Db::table('hunter_peaklist')->field('prize_id,state')->where(['user_id' => $this->user_id, 'pass_id' => $pass_id, 'add_time' => $yesterday])->find();
        if ($is_prize || $is_prize['state'] == 0) {
            Db::table('hunter_peaklist')->where(['prize_id' => $is_prize['prize_id']])->update(['state' => 1]);
            return jsonResult('领取成功', 200);
        }
        return jsonResult('领取失败', 100);
    }

    # 更多好玩
    public function more()
    {
        # 猜你喜欢
        $date = date('Y-m-d');
        $click = Db::Table('hunter_statistics_adv')->where(['user_id' => $this->user_id, 'status' => 1, 'add_time' => $date])->column('app_id');
        # 点击过和限量的广告ID
        $restrict = Db::Table('hunter_app')
            ->alias('app')
            ->join(['hunter_restrict ' => 'r'], 'app.id=r.app_id and r.num >= app.restrict')
            ->where(['r.add_time' => $date])
            ->column('app.id');
        $not = array_unique(array_merge($click, $restrict)); //合并去重

        # 猜你喜欢
        $res = Db::Table('hunter_app')
            ->field('id,app_id,app_name,app_text,app_url,app_qrcode_url,app_more_url,page')
            ->where(['play_status' => 0, 'id' => ['NOT IN', $not]])
            ->order('play_sort')
            ->select();
        $num = count($res);
        // 少于四个按顺序推送未达到限制的
        if ($num < 8) {
            $not1 = array_column($res, 'id');
            $not2 = array_unique(array_merge($not1, $restrict));
            $limit = 8 - $num;
            $app1 = Db::Table('hunter_app')
                ->field('id,app_id,app_name,app_url,app_more_url,app_qrcode_url,page,play_sort')
                ->where(['id' => ['NOT IN', $not2], 'play_status' => 0])
                ->order('RAND()')
                ->limit($limit)
                ->select();
            $res = array_merge($res, $app1);
        }
        $data['like'] = $res;
//        dump($data);die;

        # 二维码列表
        $data['qrcode'] = Db::Table('hunter_app')
            ->field('id,app_id,app_name,app_qrcode_url,app_url,app_more_url,page')
            ->where(['status' => 0])
            ->select();

        # 首页10个广告
        $data['index_app'] = Db::Table('hunter_app')
            ->field('id,app_id,app_name,app_qrcode_url,app_url,app_more_url,page')
            ->where(['status' => 0])
            ->select();

        $banner = Db::Table('hunter_app')
            ->field('id,app_id,app_name,app_qrcode_url,app_banner_url,page,banner_status')
            ->where(['id' => ['NOT IN', $not], 'banner_status' => 0])
            ->order('banner_sort')
            ->select();
        $banner_num = count($banner);
        if ($banner_num < 4) {
            $limit = 8 - $banner_num;
            $banner_not = array_column($banner, 'id');
            $banner1 = Db::Table('hunter_app')
                ->field('id,app_id,app_name,app_qrcode_url,app_banner_url,page,banner_status')
                ->where(['id' => ['NOT IN', $banner_not], 'banner_status' => 0])
                ->order('RAND()')
                ->limit($limit)
                ->select();
            $banner = array_merge($banner, $banner1);
        }
        $data['banner'] = $banner;
//        $data['more'] = $classify;
        return jsonResult('更多好玩', 200, $data);
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
        Db::table('hunter_record')->insert($data);
        return jsonResult('记录成功', 200);
    }

    # 分享
    public function share()
    {
        $num = input('post.num');
        $text = input('post.text');
        $type = input('post.type');
        $sharepic_id = input('post.sharepic_id');

        if (empty($this->user_id) || empty($num) || empty($sharepic_id)) {
            return jsonResult('参数为空', 100);
        }
        $data = [
            'user_id' => $this->user_id,
            'num' => $num,
            'shares' => '',
            'text' => $text,
            'type' => $type,
            'sharepic_id' => $sharepic_id,
            'add_time' => date('Y-m-d'),
            'timestamp' => time(),
        ];

//            dump($data);die;
        Db::table('hunter_share')->insert($data);
        return jsonResult('分享成功', 200, $num);
    }

    # 点击分享
    public function shareDetail()
    {
        $uid = input('post.uid'); // 分享者
        $num = input('post.num');
        $date = date('Y-m-d');

        $res = Db::table('hunter_share')->field('share_id,shares')->where(['user_id' => $uid, 'num' => $num])->find();
        if (empty($res)) return jsonResult('error', 110);
//            dump($res);die;
        // 添加邀请好友记录
        if ($this->user_id != $uid) {
            $share = explode(',', $res['shares']);
            if (!in_array($this->user_id, $share)) {
                if (empty($res['shares'])) {
                    $str = $this->user_id;
                } else {
                    $share[] = $this->user_id;
                    $str = join(',', $share);
                }
                Db::table('hunter_share')->where(['share_id' => $res['share_id']])->update(['shares' => $str]);
            }

            # 超级福利 （新用户）
            $invite = Db::table('hunter_invite')->where(['share_id' => $this->user_id, 'type' => 1])->value('invite_id');
            if (empty($invite)) {
                $add_time = Db::table('hunter_user')->where(['user_id' => $this->user_id])->value('add_time');
                if ($add_time == $date) {
                    $data = [
                        'user_id' => $uid,
                        'share_id' => $this->user_id,
                        'type' => 1,
                        'add_time' => date('Y-m-d H:i:s'),
                        'timestamp' => time(),
                    ];
                    Db::table('hunter_invite')->insert($data);
                    return jsonResult('超级福利', 200, $data);
                } else {
                    $is_help = Db::table('hunter_invite')->where(['share_id' => $this->user_id, 'type' => 2])->value('invite_id');
                    if (empty($is_help)) {
                        $data = [
                            'user_id' => $uid,
                            'share_id' => $this->user_id,
                            'type' => 2,
                            'add_time' => date('Y-m-d H:i:s'),
                            'timestamp' => time(),
                        ];
                        Db::table('hunter_invite')->insert($data);
                        return jsonResult('好友助力', 200, $data);
                    }
                }

            } else {
                $is_help = Db::table('hunter_invite')->where(['share_id' => $this->user_id, 'type' => 2])->value('invite_id');
                if (empty($is_help)) {
                    $data = [
                        'user_id' => $uid,
                        'share_id' => $this->user_id,
                        'type' => 2,
                        'add_time' => date('Y-m-d H:i:s'),
                        'timestamp' => time(),
                    ];
                    Db::table('hunter_invite')->insert($data);
                    return jsonResult('好友助力', 200, $data);
                }
            }
            $res['uid'] = $uid;
            $res['num'] = $num;
            return jsonResult('我是你爸爸', 100, $res);
        }
    }

    # 超级福利
    public function superWelfare()
    {
        $welfare = Db::table('hunter_invite')
            ->alias('i')
            ->field('u.user_id,u.user_name,u.avatar,i.state')
            ->join(['hunter_user' => 'u'], 'i.share_id=u.user_id', 'LEFT')
            ->where(['i.user_id' => $this->user_id, 'i.type' => 1])
            ->select();
        $res['data'] = $welfare;
        return jsonResult('超级福利列表', 200, $res);
    }

    # 好友助力
    public function friendHelp()
    {
        $welfare = Db::table('hunter_invite')
            ->alias('i')
            ->field('u.user_id,u.user_name,u.avatar')
            ->join(['hunter_user' => 'u'], 'i.share_id=u.user_id', 'LEFT')
            ->where(['i.user_id' => $this->user_id, 'i.type' => 2, 'state' => 1])
            ->limit(5)
            ->select();
        $res['data'] = $welfare;
        return jsonResult('好友助力列表', 200, $res);
    }

    # 领取好友助力奖励  state：1 未领取 2已领取
    public function getFriendHelp()
    {
        $invite = Db::table('hunter_invite')->where(['user_id' => $this->user_id, 'type' => 2, 'state' => 1])->count();
        if ($invite >= 5) {
            Db::table('hunter_invite')->where(['user_id' => $this->user_id, 'type' => 2, 'state' => 1])->limit(5)->update(['state' => 2]);
            return jsonResult('领取成功', 200);
        } else {
            return jsonResult('没有领取资格', 100);
        }
    }

    # 领取超级福利奖励 state：1 未领取 2已领取
    public function getSuperWelfare()
    {
        $uid = input('post.uid');
        $invite = Db::table('hunter_invite')->where(['user_id' => $this->user_id, 'type' => 1, 'share_id' => $uid, 'state' => 1])->count();
        if ($invite) {
            Db::table('hunter_invite')->where(['user_id' => $this->user_id, 'share_id' => $uid])->update(['state' => 2]);
            return jsonResult('领取成功', 200);
        } else {
            return jsonResult('没有领取资格', 100);
        }
    }

    // 数据统计
    public function statistics()
    {
        $user_id = $this->user_id;
        if (empty($user_id)) return jsonResult('error', 110);
        $qd_id = input('post.qd_id');
        $type = empty(input('post.type')) ? 0 : input('post.type');
        $date = date('Y-m-d');

        # 渠道统计
        if ($qd_id) {
            $scene = 0;
            $enter_id = empty(input('post.enter_id')) || input('post.enter_id') == 'undefined' ? $scene : input('post.enter_id');

            $channel = Db::Table('hunter_user')->where(['user_id' => $user_id])->value('channel');
            if (empty($channel)) {
                Db::Table('hunter_user')->where(['user_id' => $user_id])->update(['channel' => $qd_id, 'enter_id' => $enter_id]);
            }

            // 如果用户今天记录过
            $is_qd = Db::Table('hunter_statistics')->where(['qd_id' => $qd_id, 'user_id' => $user_id, 'add_time' => $date])->value('statistics_id');
            if (empty($is_qd)) {
                $userdate = Db::Table('hunter_user')->where(['user_id' => $user_id])->value('add_time');
                $data = [
                    'qd_id' => $qd_id,
                    'user_id' => $user_id,
                    'user_date' => $userdate,
                    'type' => $type,
                    'scene' => $scene,
                    'num' => 1,
                    'add_time' => $date,
                    'timestamp' => strtotime(date('Y-m-d H:i:s')),
                ];
                Db::Table('hunter_statistics')->insert($data);
                $res1['aaa'] = $enter_id;
                return jsonResult('渠道统计记录成功', 200, $res1);
            } else {
                return jsonResult('渠道统计已记录', 200);
            }
        }

        # 广告统计
        $app_id = input('post.app_id');
        if ($app_id) {
            $position = input('post.position');
            $classify = input('post.classify');
            $status = input('post.status');
            $pass = empty(input('post.pass')) ? 0 : input('post.pass');
            $win_status = input('post.win_status');

            $text = '';
            if ($win_status == 1) {
                $text = '成功';
            } elseif ($win_status == 2) {
                $text = '失败';
            }

            if (strlen($app_id) > 6) {
                $app_id = Db::Table('hunter_app')->where(['app_id' => $app_id])->value('id');
            }

            if (empty($adv_id)) {
                $advdata = [
                    'user_id' => $user_id,
                    'app_id' => $app_id,
                    'type' => $type,
                    'position' => $position,
                    'classify' => empty($classify) ? 0 : $classify,
                    'add_time' => date('Y-m-d'),
                    'num' => 1,
                    'pass' => $pass,
                    'text' => $text,
                    'timestamp' => time(),
                    'status' => $status,
                ];
                Db::Table('hunter_statistics_adv')->insert($advdata);

                if ($win_status == 1 && ($type == 3 || $type == 6)) {
                    # 广告限量
                    $restrict_id = Db::Table('hunter_restrict')->where(['app_id' => $app_id, 'add_time' => $date])->value('restrict_id');
                    if (empty($restrict_id)) {
                        $addData = [
                            'app_id' => $app_id,
                            'num' => 1,
                            'add_time' => $date,
                        ];
                        Db::Table('hunter_restrict')->insert($addData);
                    } else {
                        Db::Table('hunter_restrict')->where(['app_id' => $app_id, 'add_time' => $date])->setInc('num');
                    }
                }
                return jsonResult('点击广告记录成功', 200);
            }
        }
    }

    # 渠道统计
    public function statistics_channel()
    {
        $user_id = $this->user_id;
        if (empty($user_id)) return jsonResult('error', 110);
        $qd_id = input('post.qd_id');
        $type = empty(input('post.type')) ? 0 : input('post.type');
        $date = date('Y-m-d');

        # 渠道统计
        if ($qd_id) {
            $scene = 0;
            $enter_id = empty(input('post.enter_id')) || input('post.enter_id') == 'undefined' ? $scene : input('post.enter_id');

            $channel = Db::Table('hunter_user')->where(['user_id' => $user_id])->value('channel');
            if (empty($channel)) {
                Db::Table('hunter_user')->where(['user_id' => $user_id])->update(['channel' => $qd_id, 'enter_id' => $enter_id]);
            }

            // 如果用户今天记录过
            $is_qd = Db::Table('hunter_statistics')->where(['qd_id' => $qd_id, 'user_id' => $user_id, 'add_time' => $date])->value('statistics_id');
            if (empty($is_qd)) {
                $userdate = Db::Table('hunter_user')->where(['user_id' => $user_id])->value('add_time');
                $data = [
                    'qd_id' => $qd_id,
                    'user_id' => $user_id,
                    'user_date' => $userdate,
                    'type' => $type,
                    'scene' => $scene,
                    'num' => 1,
                    'add_time' => $date,
                    'timestamp' => strtotime(date('Y-m-d H:i:s')),
                ];
                Db::Table('hunter_statistics')->insert($data);
                $res1['aaa'] = $enter_id;
                return jsonResult('渠道统计记录成功', 200, $res1);
            } else {
                return jsonResult('渠道统计已记录', 200);
            }
        }
    }

    public function statistics_channel_new()
    {
        $user_id = $this->user_id;
        if (empty($user_id)) return jsonResult('error', 110);

//        $date = date('Y-m-d');
        $post = request()->only(['channel', 'scene', 'enter_id'], 'post'); // scene 场景值 enter_id 来源小程序ID
//        $qd_id = empty(input('post.qd_id')) ? 666 : input('post.qd_id'); //前端传过来的渠道ID
        $enter_id = $post['enter_id'];

        $is_channel = Db::Table('hunter_user')->field('user_id,channel,enter_id')->where(['user_id' => $this->user_id])->find();

//        dump($is_channel);die;
        if (empty(intval($is_channel['channel']))) { // 用户表channel为空

            if ($is_channel['enter_id']) { // 用户表有enter_id channel为0
                $channel = Db::Table('hunter_channel')->where(['enter_id' => $is_channel['enter_id']])->value('qd_id');
                $upData = [
                    'channel' => $channel,
                    'end_time' => date('Y-m-d H:i:s'),
                ];

            } else { // 新用户
                $channel2 = Db::Table('hunter_channel')->where(['enter_id' => $enter_id])->value('qd_id');
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
//            dump($upData);die;
            Db::Table('hunter_user')->where(['user_id' => $user_id])->update($upData);
            return jsonResult('渠道记录成功', 200, $upData);

        } else {
            return jsonResult('渠道已存在', 200);
        }
    }

    # 记录用户观看视频（1.免费试用 2.免费领取金币 3.领取大招 4.免费复活）
    public function watchVideo()
    {
//        $type = input('post.type');
        $text = input('post.text');
        $addData = [
            'user_id' => $this->user_id,
//            'type' => $type,
            'text' => $text,
            'add_time' => date('Y-m-d'),
        ];
        Db::table('hunter_watch_video')->insert($addData);
        return jsonResult('记录成功', 200);
    }

    public function test()
    {   # 猜你喜欢
        $like_sort = [1, 2, 3, 4, 5, 6];
        $like_sort1 = [7, 8, 9, 10];
        $like_num = join(',', $like_sort);
        $like_num1 = join(',', $like_sort1);
        # 固定的广告
        $res1 = Db::table('hunter_app')
            ->field('id,app_id,app_name,app_url,app_more_url,app_qrcode_url,page,play_sort')
            ->where(['play_sort' => ['in', $like_sort], 'play_status' => 0])
            ->order("field(play_sort," . $like_num . ")")
            ->select();

        # 随机的广告
        $res2 = Db::table('hunter_app')
            ->field('id,app_id,app_name,app_url,app_more_url,app_qrcode_url,page,play_sort')
            ->where(['play_sort' => ['in', $like_sort1], 'play_status' => 0])
            ->order("field(play_sort," . $like_num1 . ")")
            ->select();
        $num = array_rand($res2, 2);
//        dump($num);die;

        $like1 = array_slice($res1, 0, 3, false);
        $like2 = array_slice($res1, 3, 3, false);
        $like1[] = $res2[$num[0]];
        $like2[] = $res2[$num[1]];
        $like1 = array_reverse($like1);
        $like2 = array_reverse($like2);

        $res = array_merge($like1, $like2);
        $data['like'] = $res;

        dump($data);
    }


}