<?php
/**
 * Created by PhpStorm.
 * User: SongZhe
 * Date: 2019/8/13
 * Time: 17:02
 */

namespace app\index\controller;

use think\Db;

class Flick extends Conmmon
{
    /**
     * 获取code，返回openid（弹个鬼）
     */
    public function getFlickCode()
    {
        if (request()->isPost()) {
            $code = input('post.code');
            if (empty($code)) return jsonResult('你瞅啥', 100);
            $data = $this->get_flick_openid($code); // 获得 openid 和 session_key
            $user = Db::table('flick_user')->field('user_id,openid,status')->where(['openid' => $data['openid']])->find();
            $num = substr(time(), -6);

            if (empty($user)) {
                $arr = [
                    'openid' => $data['openid'],
                    'user_name' => '游客' . $num,
                    'avatar' => 'https://yxdrs.zsmgc.com.cn/A/public/app/youke.png',
                    'add_time' => date('Y-m-d'),
                    'end_time' => date('Y-m-d H:i:s'),
                    'offline_time' => time(),
                    'is_impower' => 0,
                ];
                $uid = Db::table('flick_user')->insertGetId($arr);
                $res['mstr'] = lock_url($data['openid'] . ',' . $uid);

                return jsonResult('请求成功', 200, $res);

            } else {
                Db::table('flick_user')->where(['user_id' => $user['user_id']])->update(['end_time' => date('Y-m-d H::s')]);
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

        $user = Db::table('flick_user')->where(['user_id' => $this->user_id])->value('user_id');
        if (empty($user)) return jsonResult('数据不存在', 100);

        $updata = [
            'user_name' => $user_name,
            'avatar' => empty($avatar) ? '' : $avatar . '?aaa=aa.jpg',
            'sex' => empty($sex) ? '' : $sex,
            'city' => empty($city) ? '' : $city,
            'is_impower' => 1,
            'end_time' => date('Y-m-d H:i:s'),
        ];
        Db::table('flick_user')->where(['user_id' => $this->user_id])->update($updata);
        return jsonResult('用户信息', 200);
    }

    # 首页配置信息
    public function index()
    {
//        $this->user_id = 1;
        if (empty($this->user_id)) return jsonResult('error', 110);

        # 日活
        Db::table('flick_user')->where(['user_id' => $this->user_id])->update(['end_time' => date('Y-m-d H::s')]);

        # 分享文案
        $data['share'] = Db::table('flick_share_pic')->find();

        # 用户信息
        $data['userInfo'] = Db::table('flick_user')->field('user_id,user_name,avatar,max_pass,coin,is_impower,lv,new_player_status')->where(['user_id' => $this->user_id])->find();

        # 匹配用户角色信息
        $data['matchUser'] = Db::table('flick_match')->select();

        # 广告列表
        $data['app'] = Db::table('flick_app')->field('id,app_id,app_name,app_text,app_url,app_qrcode_url,app_more_url,page')->where(['play_status' => 0])->order('play_sort')->select();


        return jsonResult('首页配置信息', 200, $data);
    }

    # 离线
    public function offline()
    {
        if (empty($this->user_id)) return jsonResult('error', 110);
        $post = request()->only(['max_pass']);

        $user = Db::table('flick_user')->field('max_pass')->where(['user_id' => $this->user_id])->find();
        if ($post['max_pass'] > $user['max_pass']) {
            Db::table('flick_user')->where(['user_id' => $this->user_id])->update(['max_pass' => $post['max_pass']]);
            return jsonResult('离线成功', 200);
        }
        return jsonResult('请求成功', 200);
    }


    # 离线
    public function offline_new()
    {
//        dump($this->user_id);
//        dump($this->open_id);
        if (empty($this->user_id)) return jsonResult('error', 110);
        $post = request()->only(['max_pass', 'new_player_status', 'lv', 'coin']);

        $user = Db::table('flick_user')->field('max_pass')->where(['user_id' => $this->user_id])->find();
        $post['max_pass'] = $post['max_pass'] > $user['max_pass'] ? $post['max_pass'] : $user['max_pass'];
        Db::table('flick_user')->where(['user_id' => $this->user_id])->update($post);
        return jsonResult('请求成功', 200);
    }


    # 渠道统计
    public function statistics_channel()
    {
        $user_id = $this->user_id;
        if (empty($user_id)) return jsonResult('error', 110);

//        $date = date('Y-m-d');
        $post = request()->only(['channel', 'scene', 'enter_id'], 'post'); //channel 渠道ID 例如：1001  scene 场景值 enter_id 来源小程序ID
        $enter_id = $post['enter_id'];

        $is_channel = Db::Table('flick_user')->field('user_id,channel,enter_id')->where(['user_id' => $this->user_id])->find();

//        dump($is_channel);die;
        if (empty(intval($is_channel['channel']))) { // 用户表channel为空

            if ($is_channel['enter_id']) { // 用户表有enter_id channel为0
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

//                // 新用户
//                $channel2 = Db::Table('channel_product_info')
//                    ->alias('p')
//                    ->join(['channel_info' => 'i'], 'p.enter_id=i.enter_id', 'LEFT')
//                    ->where(['i.enter_id' => $is_channel['enter_id']])
//                    ->value('i.qd_id');
//                if ($channel2) {
//                    $upData = [
//                        'channel' => $channel2,
//                        'scene' => $post['scene'],
//                        'enter_id' => $enter_id,
//                        'end_time' => date('Y-m-d H:i:s'),
//                    ];
//                } else {
//                    $upData = [
//                        'channel' => $post['channel'],
//                        'scene' => $post['scene'],
//                        'enter_id' => $enter_id,
//                        'end_time' => date('Y-m-d H:i:s'),
//                    ];
//                }

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
//            dump($upData);die;
            Db::Table('flick_user')->where(['user_id' => $user_id])->update($upData);
            return jsonResult('渠道记录成功', 200, $upData);

        } else {
            return jsonResult('渠道已存在', 200);
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
            $app_id = Db::Table('flick_app')->where(['app_id' => $app_id])->value('id');
        }

        $is_click = Db::table('flick_statistics_adv')->where(['user_id' => $this->user_id, 'app_id' => $post['app_id'], 'status' => 1, 'add_time' => $date])->value('statistics_id');

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
            Db::Table('flick_statistics_adv')->insert($advData);

            if ($post['status'] == 1) {
                # 广告限量
                $restrict_id = Db::Table('flick_restrict')->where(['app_id' => $app_id, 'add_time' => $date])->value('restrict_id');
                if (empty($restrict_id)) {
                    $addData = [
                        'app_id' => $app_id,
                        'num' => 1,
                        'add_time' => $date,
                    ];
                    Db::Table('flick_restrict')->insert($addData);
                } else {
                    Db::Table('flick_restrict')->where(['restrict_id' => $restrict_id])->setInc('num');
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
        Db::table('flick_watch_video')->insert($post);
        return jsonResult('记录成功', 200);
    }

}