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

class Ball extends Conmmon
{
    /**
     * 获取code，返回openid（弹个鬼）
     */
    public function getBallCode()
    {
        if (request()->isPost()) {
            $code = input('post.code');
            if (empty($code)) return jsonResult('你瞅啥', 100);
            $data = $this->get_ball_openid($code); // 获得 openid 和 session_key
            $user = Db::table('ball_user')->field('user_id,openid,status')->where(['openid' => $data['openid']])->find();

            if (empty($user)) {
                $arr = [
                    'openid' => $data['openid'],
                    'user_name' => '',
                    'avatar' => 'https://yxdrs.zsmgc.com.cn/A/public/app/wdl.png',
                    'add_time' => date('Y-m-d'),
                    'end_time' => date('Y-m-d H:i:s'),
                ];
                $uid = Db::table('ball_user')->insertGetId($arr);
                $res['mstr'] = lock_url($data['openid'] . ',' . $uid);

                return jsonResult('请求成功', 200, $res);

            } else {
                Db::table('ball_user')->where(['user_id' => $user['user_id']])->update(['end_time' => date('Y-m-d H:i:s')]);
                $res['mstr'] = lock_url($data['openid'] . ',' . $user['user_id']);
                return jsonResult('请求成功', 200, $res);
            }
        }
    }

    # 首页
    public function index()
    {
        # 分享文案
        $share = Db::table('ball_share_pic')->order('rand()')->select();
        $data['share'] = $share;

        # 首页用户信息
        $user = Db::table('ball_user')
            ->field('user_id,coin,max_pass')
            ->where(['user_id' => $this->user_id])
            ->find();
        $data['userInfo'] = $user;

        $config = Db::table('ball_config')->field('on_off_status')->find();
        $data['config'] = $config;

        $data['pass'] = Db::table('ball_pass')->select();

        # 盒子页面开关
        Db::table('ball_user')->where(['user_id' => $this->user_id])->update(['end_time' => date('Y-m-d H:i:s')]);
        return jsonResult('succ', 200, $data);
    }

    # 离线
    public function offline()
    {
        $post = request()->only(['max_pass', 'coin'], 'post');

        $user = Db::table('ball_user')->field('max_pass')->where(['user_id' => $this->user_id])->find();
        if ($user) {
            $upData = [
                'coin' => empty($post['coin']) ? 0 : $post['coin'],
                'max_pass' => $post['max_pass'] > $user['max_pass'] ? $post['max_pass'] : $user['max_pass'],
            ];
            Db::table('ball_user')->where(['user_id' => $this->user_id])->update($upData);
            return jsonResult('离线记录成功', 200, $post);
        } else {
            return jsonResult('用户不存在', 100);
        }
    }

    # 挑战记录
    public function challenge()
    {
        $post = request()->only(['pass', 'status'], 'post'); // 1成功 2失败
        $addData = [
            'user_id' => $this->user_id,
            'pass' => $post['pass'],
            'status' => $post['status'],
            'text' => $post['status'] == 1 ? '成功' : '失败',
            'add_time' => date('Y-m-d'),
        ];
        Db::table('ball_challenge')->insert($addData);

        $user = Db::table('ball_user')->field('max_pass')->where(['user_id' => $this->user_id])->find();
        if ($post['pass'] > $user['max_pass']) {
            Db::table('ball_user')->where(['user_id' => $this->user_id])->update(['max_pass' => $post['pass']]);
            return jsonResult('挑战记录成功', 200);
        } else {
            return jsonResult('挑战记录成功', 100);
        }
    }

    # 更多好玩
    public function more()
    {
        # 猜你喜欢
        $res = Db::table('ball_app')
            ->field('id,app_id,app_name,app_qrcode_url,app_url,app_more_url,page')
            ->where(['play_status' => 0])
            ->order('play_sort')
            ->select();
        $data['like'] = $res;
//        dump($data);die;

        # 首页10个广告
        $banner = Db::table('ball_app')
            ->field('id,app_id,app_name,app_banner_url,app_url,app_more_url,page')
            ->where(['banner_status' => 0])
            ->select();

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
        Db::table('ball_record')->insert($data);
        return jsonResult('记录成功', 200);
    }


    // 广告数据统计
    public function statistics_adv()
    {
        $user_id = $this->user_id;
        if (empty($user_id)) return jsonResult('error', 110);
        $type = empty(input('post.type')) ? 0 : input('post.type');
        $date = date('Y-m-d');


        # 广告统计
        $app_id = input('post.app_id');
        if ($app_id) {
            $position = input('post.position');
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
                $app_id = Db::table('ball_app')->where(['app_id' => $app_id])->value('id');
            }

            if (empty($adv_id)) {
                $advdata = [
                    'user_id' => $user_id,
                    'app_id' => $app_id,
                    'type' => $type,
                    'position' => $position,
                    'add_time' => date('Y-m-d'),
                    'num' => 1,
                    'pass' => $pass,
                    'text' => $text,
                    'timestamp' => time(),
                    'status' => $status,
                ];
                Db::table('ball_statistics_adv')->insert($advdata);

                if ($win_status == 1 && ($type == 3 || $type == 6)) {
                    # 广告限量
                    $restrict_id = Db::table('ball_restrict')->where(['app_id' => $app_id, 'add_time' => $date])->value('restrict_id');
                    if (empty($restrict_id)) {
                        $addData = [
                            'app_id' => $app_id,
                            'num' => 1,
                            'add_time' => $date,
                        ];
                        Db::table('ball_restrict')->insert($addData);
                    } else {
                        Db::table('ball_restrict')->where(['app_id' => $app_id, 'add_time' => $date])->setInc('num');
                    }
                }
                return jsonResult('点击广告记录成功', 200);
            }
        }
    }

    public function statistics_channel()
    {
        $user_id = $this->user_id;
        if (empty($user_id)) return jsonResult('error', 110);

//        $date = date('Y-m-d');
        $post = request()->only(['channel', 'scene', 'enter_id'], 'post'); // scene 场景值 enter_id 来源小程序ID
//        $qd_id = empty(input('post.qd_id')) ? 666 : input('post.qd_id'); //前端传过来的渠道ID
        $enter_id = $post['enter_id'];

        $is_channel = Db::table('ball_user')->field('user_id,channel,enter_id')->where(['user_id' => $this->user_id])->find();

//        dump($is_channel);die;
        if (empty(intval($is_channel['channel']))) { // 用户表channel为空

            if ($is_channel['enter_id']) { // 用户表有enter_id channel为0
                $channel = Db::table('ball_channel')->where(['enter_id' => $is_channel['enter_id']])->value('qd_id');
                $upData = [
                    'channel' => $channel,
                    'end_time' => date('Y-m-d H:i:s'),
                ];

            } else { // 新用户
                $channel2 = Db::table('ball_channel')->where(['enter_id' => $enter_id])->value('qd_id');
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
            Db::table('ball_user')->where(['user_id' => $user_id])->update($upData);
            return jsonResult('渠道记录成功', 200, $upData);

        } else {
            return jsonResult('渠道已存在', 200);
        }
    }


    # 记录用户观看视频（1.免费试用 2.免费领取金币 3.领取大招 4.免费复活）
    public function watchVideo()
    {
        $type = input('post.type');
        $text = input('post.text');
        $addData = [
            'user_id' => $this->user_id,
            'type' => $type,
            'text' => $text,
            'add_time' => date('Y-m-d'),
        ];
        Db::table('ball_watch_video')->insert($addData);
        return jsonResult('记录成功', 200);
    }

}