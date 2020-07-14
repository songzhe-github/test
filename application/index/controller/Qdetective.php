<?php
/**
 * QQ-末日射击
 * User: SongZhe
 * Date: 2019/8/13
 * Time: 17:02
 */

namespace app\index\controller;

use think\Db;
use ip\IpLocation;

class Qdetective extends Conmmon
{
    /**
     * 获取code，返回openid
     */
    public function getCode()
    {
        if (request()->isPost()) {
            $code = input('post.code');
            if (empty($code)) return jsonResult('你瞅啥', 100);
            $appid = '1110434003';
            $appsecret = '9umRzHX77nOX45dl';
            $data = $this->getTTOpenid($appid, $appsecret, $code); // 获得 openid 和 session_key

            $user = Db::table('QQ_Detective_user')->field('user_id,openid')->where(['openid' => $data['openid']])->find();
            if (empty($user)) {
                $num = substr(time(), -6);
                $arr = [
                    'openid' => $data['openid'],
                    'user_name' => '游客' . $num,
                    'add_date' => date('Y-m-d'),
                    'add_timestamp' => time(),
                    'energyNum' => 10,
                    'is_impower' => 0,
                ];
                $uid = Db::table('QQ_Detective_user')->insertGetId($arr);
                $res['mstr'] = lock_url($data['openid'] . ',' . $uid);
                return jsonResult('请求成功', 200, $res);

            } else {
                $res['mstr'] = lock_url($data['openid'] . ',' . $user['user_id']);
                return jsonResult('请求成功', 200, $res);
            }
        }
    }

    # 首页配置信息
    public function index()
    {
        # 分享文案
        $data['share'] = config('share.Detective_share_pic');

        # 用户信息
        $userInfo = Db::table('QQ_Detective_user')
            ->field('user_id,user_name,avatar,max_pass,coin,role,furniture,energyNum,is_impower')
            ->where(['user_id' => $this->user_id])
            ->find();
        $userInfo['role'] = empty($userInfo['role']) ? [['lv' => 0, 'status' => 0], ['lv' => 0, 'status' => 0]] : json_decode($userInfo['role']);
        $userInfo['furniture'] = empty($userInfo['furniture']) ? [['lv' => 0, 'status' => 0], ['lv' => 0, 'status' => 0]] : json_decode($userInfo['furniture']);
        $data['userInfo'] = $userInfo;

        # 关卡配置
        $passInfo = Db::table('QQ_Detective_config_passInfo')->field('pass_id,pic,small_pic,description,analysis,tips,answer,prize')->select();
        $passInfoArr = dataGroup($passInfo, 'pass_id');
        foreach ($passInfoArr as $kk => $vv) {
            foreach ($vv as $key => $value) {
                if (empty($value['answer']) || empty($value['prize'])) continue;
                $answerArr = explode(',', $value['answer']);
                $answer['posX'] = (int)$answerArr[0];
                $answer['posY'] = (int)$answerArr[1];
                $answer['radius'] = (int)$answerArr[2];
                $passInfoArr[$kk][$key]['answer'] = $answer;

                $prizeArr = explode(',', $value['prize']);
                $prize['coin_num'] = (int)$prizeArr[0];
                $passInfoArr[$kk][$key]['prize'] = $prize;
            }
        }
        $data['PassInfo'] = $passInfoArr;

        # 探员配置
        $roleArray = Db::table('QQ_Detective_config_role')->field('role_id,role_lv,role_unlock_coin,role_up_coin,role_output_coin')->select();
        $data['Role'] = dataGroup($roleArray, 'role_id');

        # 家具配置
        $roleArray = Db::table('QQ_Detective_config_furniture')->field('furniture_id,furniture_lv,furniture_unlock_coin,furniture_up_coin,furniture_output_vit')->select();
        $data['Furniture'] = dataGroup($roleArray, 'furniture_id');

        # 签到列表
        $signList = Db::table('QQ_Detective_signlist')->field('day,num,type,status')->select();
        $sign = Db::table('QQ_Detective_sign')->where(['user_id' => $this->user_id, 'status' => 0])->select();
        $end = end($sign);
        if ($end['add_time'] < $this->date && count($sign) >= 7) {
            # 满一周status更新为0
            Db::table('QQ_Detective_sign')->where(['user_id' => $this->user_id])->update(['status' => 1]);
            $sign = [];
        }
        foreach ($signList as &$value) {
            foreach ($sign as &$z) {
                if ($value['day'] == $z['day']) {
                    $value['status'] = 1;
                }
            }
        }
        $signInfo['is_sign'] = $end['add_time'] < $this->date ? 0 : 1;
        $signInfo['day'] = count($sign);
        $signInfo['signList'] = $signList;
        $data['signInfo'] = $signInfo;

        $rank_pass = Db::table('QQ_Detective_rank_pass')->field('user_id,user_name,max_pass')->select();
        $userIds = array_column($rank_pass, 'user_id');
        $is_rank = array_keys($userIds, $this->user_id);

        $user = Db::table('QQ_Detective_user')->field('user_id,user_name,max_pass')->where(['user_id' => $this->user_id])->find();
        if (empty($is_rank)) {
            if (empty($ranking)) {
                $ranking = rand(100, 1000);
            }
        } else {
            $ranking = $is_rank[0] + 1;
        }
        $data['rankPass'] = $rank_pass;
        $user['ranking'] = $ranking;
        $data['userRankPass'] = $user;

        $rank_dress = Db::table('QQ_Detective_rank_dress')->field('user_id,user_name,dress_value')->select();
        $userIds1 = array_column($rank_dress, 'user_id');
        $is_rank = array_keys($userIds1, $this->user_id);

        $user = Db::table('QQ_Detective_user')->field('user_id,user_name,dress_value')->where(['user_id' => $this->user_id])->find();
        if (empty($is_rank)) {
            if (empty($ranking1)) {
                $ranking1 = rand(100, 1000);
            }
        } else {
            $ranking1 = $is_rank[0] + 1;
        }
        $data['rankDress'] = $rank_dress;
        $user['ranking'] = $ranking1;
        $data['userRankDress'] = $user;


        # 九宫格
        $app = Db::table('QQ_Detective_app')
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
        $config = Db::table('QQ_Detective_config')->find();
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
        $data['API'] = request()->action();
        return jsonResult('首页配置信息', 200, $data);
    }

    # 排行榜
    public function rank()
    {
        $rank_pass = Db::table('QQ_Detective_rank_pass')->field('user_id,user_name,max_pass')->select();
        $userIds = array_column($rank_pass, 'user_id');
        $is_rank = array_keys($userIds, $this->user_id);

        $user = Db::table('QQ_Detective_user')->field('user_id,user_name,max_pass')->where(['user_id' => $this->user_id])->find();
        if (empty($is_rank)) {
            if (empty($ranking)) {
                $ranking = rand(100, 1000);
            }
        } else {
            $ranking = $is_rank[0] + 1;
        }
        $data['rank_pass'] = $rank_pass;
        $user['ranking'] = $ranking;
        $data['user_rank_pass'] = $user;

        $rank_dress = Db::table('QQ_Detective_rank_dress')->field('user_id,user_name,dress_value')->select();
        $userIds = array_column($rank_dress, 'user_id');
        $is_rank = array_keys($userIds, $this->user_id);

        $user = Db::table('QQ_Detective_user')->field('user_id,user_name,dress_value')->where(['user_id' => $this->user_id])->find();
        if (empty($is_rank)) {
            if (empty($ranking)) {
                $ranking = rand(100, 1000);
            }
        } else {
            $ranking = $is_rank[0] + 1;
        }
        $data['rank_dress'] = $rank_dress;
        $user['ranking'] = $ranking;
        $data['user_rank_dress'] = $user;

        return jsonResult('排行榜', 200, $data);
    }

    # 点击签到
    public function clickSign()
    {
        $user_sign = Db::table('QQ_Detective_sign')->where(['user_id' => $this->user_id, 'status' => 0])->select();
        $end = end($user_sign);
        if ($end['add_time'] == $this->date) return jsonResult('今日已签到过', 100);

        $day = $end['day'] == 7 ? 1 : $end['day'] + 1;
        $insert = [
            'user_id' => $this->user_id,
            'day' => $day,
            'add_time' => $this->date,
            'status' => 0,
        ];
        Db::table('QQ_Detective_sign')->insert($insert);
        return jsonResult('签到成功', 200);
    }

    # 离线
    public function offline()
    {
        if (empty($this->user_id)) return jsonResult('error', 110);
        $userInfo = input('post.userInfo/a');
        $upData = [
            'max_pass' => $userInfo['max_pass'],
            'coin' => $userInfo['coin'],
            'energyNum' => $userInfo['energyNum'],
            'dress_value' => $userInfo['dress_value'],
            'role' => json_encode($userInfo['role']),
            'furniture' => json_encode($userInfo['furniture']),
            'offline_time' => time(),
        ];
//        dump($upData);
        Db::table('QQ_Detective_user')->where(['user_id' => $this->user_id])->update($upData);
        return jsonResult('离线成功', 200);
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
//        $res = Db::table('QQ_Detective_challenge')->field('status')->where(['user_id' => $this->user_id, 'timestamp' => $post['timestamp']])->find();
//        if ($res) {
//            if ($res['status'] == 0) {
//                $updata = [
//                    'status' => $post['status'],
//                    'end_time' => date('Y-m-d H:i:s'),
//                ];
//                Db::table('QQ_Detective_challenge')->where(['user_id' => $this->user_id, 'timestamp' => $post['timestamp']])->update($updata);
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
//            Db::table('QQ_Detective_challenge')->insert($insert);
//            return jsonResult('点击游戏开始', 200);
//        }
    }

    # 渠道统计
    public function statistics_channel()
    {
        if (empty($this->user_id)) return jsonResult('error', 110);
        $post = request()->only(['channel', 'scene', 'enter_id'], 'post'); //channel 渠道ID 例如：1001  scene 场景值 enter_id 来源小程序ID
        $post['enter_id'] = $post['enter_id'] ? $post['enter_id'] : 666;
        $date = date('Y-m-d');

        $record_channel_id = Db::table('QQ_Detective_record_channel')->where(['user_id' => $this->user_id, 'add_date' => $date])->value('record_channel_id');
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
            Db::table('QQ_Detective_record_channel')->insert($record);
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
            $app_id = Db::table('QQ_Detective_app')->where(['app_id' => $app_id, 'play_status' => 0])->value('id');
        }

        $is_click = Db::table('QQ_Detective_statistics_adv')->where(['user_id' => $this->user_id, 'app_id' => $post['app_id'], 'status' => 1, 'add_time' => $date, 'type' => $post['type']])->value('statistics_id');

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
            Db::table('QQ_Detective_statistics_adv')->insert($advData);

            if ($post['status'] == 1) {
                # 广告限量
                $restrict_id = Db::table('QQ_Detective_restrict')->where(['app_id' => $app_id, 'add_time' => $date])->value('restrict_id');
                if (empty($restrict_id)) {
                    $addData = [
                        'app_id' => $app_id,
                        'num' => 1,
                        'add_time' => $date,
                    ];
                    Db::table('QQ_Detective_restrict')->insert($addData);
                } else {
                    Db::table('QQ_Detective_restrict')->where(['restrict_id' => $restrict_id])->setInc('num');
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
        Db::table('QQ_Detective_watch_video')->insert($post);
        return jsonResult('记录成功', 200);
    }


}