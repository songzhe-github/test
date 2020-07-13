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

class Monster extends Conmmon
{
    public function aaa()
    {
        return 222;
    }

    /**
     * 获取code，返回openid
     */
    public function getCode()
    {
        if (request()->isPost()) {
            $code = input('post.code');
            if (empty($code)) return jsonResult('你瞅啥', 100);
            $appid = 'wxa6b260291ff78c20';
            $appsecret = 'efa906b0d63d72f6f3c34feeca6e73b4';
            $data = $this->getOpenid($appid, $appsecret, $code); // 获得 openid 和 session_key

            $user = Db::table('Monster_user')->field('user_id,openid')->where(['openid' => $data['openid']])->find();
            if (empty($user)) {
                $num = substr(time(), -6);
                $arr = [
                    'openid' => $data['openid'],
                    'user_name' => '游客' . $num,
                    'add_date' => $this->date,
                ];
                $uid = Db::table('Monster_user')->insertGetId($arr);
                $res['mstr'] = lock_url($data['openid'] . ',' . $uid);
                return jsonResult('请求成功', 200, $res);

            } else {
                $res['mstr'] = lock_url($data['openid'] . ',' . $user['user_id']);
                return jsonResult('请求成功', 200, $res);
            }
        }
        return jsonResult('亲，你迷路了！', 110);
    }

    # 首页配置信息
    public function index()
    {
        # 分享文案
        $data['share'] = config('share.Snack_share_pic');

        # 用户信息
        $userInfo = Db::table('Monster_user')->field('user_id,user_name,coin,gouYu,clerkInfo,snackInfo,decorationInfo')->where(['user_id' => $this->user_id])->find();

        # 用户的店员信息
        $clerk_config = config('monster_config_clerk');
        $clerk = [];
        foreach ($clerk_config as $clerk_key => $clerk_value) {
            foreach ($clerk_value['list'] as $clerk_key1 => $clerk_value1) {
                if ($clerk_value1['status'] >= 1) {
                    $arr['clerk_lv'] = 0;
                    $arr['clerk_floor'] = null;
                    $arr['clerk_position'] = null;
                    $arr['clerk_level'] = $clerk_value['clerk_level'];
                    $arr['clerk_level'] = $clerk_value['clerk_level'];
                    $arr['clerk_name'] = $clerk_value1['clerk_name'];
                    $arr['status'] = $clerk_value1['status'];
                    $clerk[$clerk_key][] = $arr;
                }
            }
        }
        $clerk[1] = [];
        $clerk[2] = [];
        $clerk[3] = [];
        unset($clerk_config);
        $userInfo['clerkInfo'] = empty($userInfo['clerkInfo']) ? $clerk : json_decode($userInfo['clerkInfo']);

        # 小吃配置信息
        $snack_config = config('monster_config_snack');
        $snack = [];
        foreach ($snack_config as $snack_key => $snack_value) {
            foreach ($snack_value['list'] as $snack_key1 => $snack_value1) {
                if ($snack_value1['status'] >= 1) {
                    $arr1['snack_name'] = $snack_value1['snack_name'];
                    $arr1['snack_position'] = $snack_value1['snack_name'] == '臭豆腐' ? 1 : null;
                    $arr1['status'] = $snack_value1['status'];
                    $snack[$snack_key][] = $arr1;
                }
            }
        }
        $snack[1] = [];
        $snack[2] = [];
        unset($snack_config);
        $userInfo['snackInfo'] = empty($userInfo['snackInfo']) ? $snack : json_decode($userInfo['snackInfo']);

        # 小吃配置信息
        $decoration_config = config('monster_config_decoration');
        $decoration = [];
        foreach ($decoration_config as $decoration_key => $decoration_value) {
            if ($decoration_value['status'] >= 1) {
                $arr2['decoration_lv'] = 0;
                $arr2['status'] = $decoration_value['status'];
                $decoration[] = $arr2;
            }
        }
        $decoration[1] = [];
        $decoration[2] = [];
        $decoration[3] = [];
        unset($snack_config);
        $userInfo['decorationInfo'] = empty($userInfo['decorationInfo']) ? $decoration : json_decode($userInfo['decorationInfo']);

        $data['userInfo'] = $userInfo;

        # 根据IP地址是否开启误点
        $config = Db::table('Monster_config')->find();
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
        $ranks = Db::table('Monster_rank')->field('user_id,user_name,avatar,max_pass')->select();
        $userIds = array_column((array)$ranks, 'user_id');
        $is_rank = array_keys($userIds, $this->user_id);

        $user = Db::table('Monster_user')->field('user_id,user_name,avatar,max_pass')->where(['user_id' => $this->user_id])->find();
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
        $upData = [
            'coin' => $userInfo['coin'],
            'gouYu' => $userInfo['gouYu'],
            'clerkInfo' => json_encode($userInfo['clerkInfo'], JSON_UNESCAPED_UNICODE),
            'snackInfo' => json_encode($userInfo['snackInfo'], JSON_UNESCAPED_UNICODE),
            'decorationInfo' => json_encode($userInfo['decorationInfo'], JSON_UNESCAPED_UNICODE),
            'offline_time' => time(),
        ];
//        dump($upData);
        Db::table('Monster_user')->where(['user_id' => $this->user_id])->update($upData);
        return jsonResult('离线成功', 200, $upData);
    }

    # 记录用户观看视频
    public function watchVideo()
    {
        $post = request()->only(['type', 'text', 'pass'], 'post');
        $post['user_id'] = $this->user_id;
        $post['add_time'] = $this->date;
        $post['timestamp'] = time();
        Db::table('Monster_watch_video')->insert($post);
        return jsonResult('记录成功', 200);
    }

    public function demo()
    {

//      # 店员配置信息
//      $clerk = config('monster_config_clerk');
//      $clerk_json = json_encode($clerk, JSON_UNESCAPED_UNICODE);
//      file_put_contents('monster_config_clerk.json', $clerk_json);
//      # 小吃配置信息
//      $snack = config('monster_config_snack');
//      $snack_json = json_encode($snack, JSON_UNESCAPED_UNICODE);
//      file_put_contents('monster_config_snack.json', $snack_json);
//      # 装修配置信息
//      $decoration = config('monster_config_decoration');
//      $decoration_json = json_encode($decoration, JSON_UNESCAPED_UNICODE);
//      file_put_contents('monster_config_decoration.json', $decoration_json);
//
//      // 把json文件写入文件
//      file_put_contents('monster_config_clerk.json', $clerk_json);
//       // 读取json文件
//      $json_string = file_get_contents('monster_config_clerk.json');
//      // 把JSON字符串转成PHP数组
//      $data = json_decode($json_string, true);
//      dump($data);

    }
}