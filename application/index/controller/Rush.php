<?php
/**
 * Created by PhpStorm.
 * User: SongZhe
 * Date: 2019/8/13
 * Time: 17:02
 */

namespace app\index\controller;

use think\cache\Driver;
use think\Db;

class Rush extends Conmmon
{
    /**
     * 获取code，返回openid（暴走冲冲冲）
     */
    public function getRushCode()
    {
        if (request()->isPost()) {
            $code = input('post.code');
            if (empty($code)) return jsonResult('你瞅啥', 100);
            $appid = 'wxf3b7d6e4c7915a82';
            $appsecret = '9ad2c5025c9fc5b1a2e6e7801781b6bb';
            $data = $this->getOpenid($appid, $appsecret, $code); // 获得 openid 和 session_key

            if (empty($user)) {
                $num = substr(time(), -6);
                $arr = [
                    'openid' => $data['openid'],
                    'user_name' => '游客' . $num,
                    'avatar' => 'https://yxdrs.zsmgc.com.cn/A/public/app/youke.png',
                    'add_time' => date('Y-m-d'),
                    'end_time' => date('Y-m-d H:i:s'),
                    'offline_time' => time(),
                    'is_impower' => 0,
                ];
                $uid = Db::table('rush_user')->insertGetId($arr);
                $res['mstr'] = lock_url($data['openid'] . ',' . $uid);
                return jsonResult('请求成功', 200, $res);

            } else {
                Db::table('rush_user')->where(['user_id' => $user['user_id']])->update(['end_time' => date('Y-m-d H::s')]);
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

        $user = Db::table('rush_user')->where(['user_id' => $this->user_id])->value('user_id');
        if (empty($user)) return jsonResult('数据不存在', 100);

        $updata = [
            'user_name' => $user_name,
            'avatar' => empty($avatar) ? '' : $avatar . '?aaa=aa.jpg',
            'sex' => empty($sex) ? '' : $sex,
            'city' => empty($city) ? '' : $city,
            'is_impower' => 1,
            'end_time' => date('Y-m-d H:i:s'),
        ];
        Db::table('rush_user')->where(['user_id' => $this->user_id])->update($updata);
        return jsonResult('用户信息', 200);
    }

    # 首页配置信息
    public function index()
    {
        # 分享文案
        $data['share'] = config('share.rush_share_pic');

        # 用户信息
        $data['userInfo'] = Db::table('rush_user')
            ->field('user_id,user_name,avatar,max_pass,all_coin,is_impower,new_player_status')
            ->where(['user_id' => $this->user_id])
            ->find();

        # 关卡配置信息
        $data['pass_config'] = config('rush_map_config'); // 包含新手引导


        # 关卡解锁条件
        $pass_config = Db::table('rush_pass_unlock')->order('pass_id')->select();
//        dump($pass_config);die;
        $challenge = Db::table('rush_challenge')->field('pass_id,criteria')->where(['user_id' => $this->user_id])->select();
        foreach ($challenge as $i => $j) {
            foreach ($pass_config as $k => $v) {
                if ($v['pass_id'] == $j['pass_id']) {
                    $arr = explode(',', $j['criteria']);
                    if (in_array($v['unlock_id'], $arr)) {
                        $pass_config[$k]['is_unlock'] = 1;
                    }
                }
            }
        }
        $array = [];
        foreach ($pass_config as $k => $v) {
            $array[$v['pass_id']][] = $v;
        }
//        array_unshift($array, []);
        $res = array_values($array);
        $data['pass_unlock'] = $res;


        # 猜你喜欢
        $app = Db::table('rush_app')
            ->field('id,app_id,app_name,app_url,app_more_url,app_banner_url,page,play_status,play_sort')
            ->where(['play_status' => 0])
            ->order('play_sort')
            ->select();
        foreach ($app as $k => $v) {
            $app[$k]['app_banner_url'] = $v['app_more_url'];
        }
        $data['app'] = $app;

        # banner
//        $banner = dengyu($app, 'banner_status', 0);
//        $last_banner = array_column($banner,'banner_sort');
//        array_multisort($last_banner,SORT_ASC,$banner);

        $banner = Db::table('rush_app')
            ->field('id,app_id,app_name,app_banner_url,page,banner_status,banner_sort')
            ->where(['banner_status' => 0])
            ->order('banner_sort')
            ->select();
        $data['banner'] = $banner;

        # 根据IP地址是否开启误点
        $config = Db::table('rush_config')->find();
//        $isCanErrorClick = $config['isCanErrorClick'];
//        if ($isCanErrorClick == 1) {
//            $IP = request()->ip(0, true);
//            $arr = IpLocation::getLocation($IP);
//
//            $str = $arr['province'] . $arr['city'];
//            $city = ['北京', '上海', '广州', '成都', '长沙'];
//            foreach ($city as $k => $v) {
//                $is_exist = strstr($str, $v);
//                if ($is_exist) {
//                    $isCanErrorClick = 0;
//                }
//            }
//        }

        $data['isCanErrorClick'] = $config['isCanErrorClick']; // 误点开关
        $data['probation_status'] = $config['probation_status']; // 是否继续视频弹窗
        $data['left_switch_status'] = $config['left_switch_status']; // 左侧广告开关
        $data['API'] = request()->action();
        return jsonResult('首页配置信息', 200, $data);
    }

    # 离线
    public function offline()
    {
        if (empty($this->user_id)) return jsonResult('error', 110);
        $post = request()->only(['all_coin', 'new_player_status']);

        Db::table('rush_user')->where(['user_id' => $this->user_id])->update($post);
        return jsonResult('离线成功', 200);
    }

    # 关卡挑战记录
    public function challenge()
    {
//        $this->user_id = 2;
        if (empty($this->user_id)) return jsonResult('error', 110);
        $post = request()->only(['pass_id', 'criteria']);
//        dump($post);die;

        $exp = explode(',', $post['criteria']);
        $pass_id = count($exp) > 0 ? $post['pass_id'] + 1 : $post['pass_id'];
        if ($pass_id > 10) {
            $pass_id = 10;
        }

        $max_pass = Db::table('rush_user')->where(['user_id' => $this->user_id])->value('max_pass');
        if ($pass_id > $max_pass) {
            Db::table('rush_user')->where(['user_id' => $this->user_id])->update(['max_pass' => $pass_id]);
        }

        $user_star = Db::table('rush_challenge')->field('criteria,is_receive')->where(['user_id' => $this->user_id, 'pass_id' => $pass_id])->find();
//        dump($user_star);
        $num = empty($user_star['criteria']) ? [] : explode(',', $user_star['criteria']);
        $arr = array_unique(array_merge($exp, $num));
        $criteria = join(',', $arr);

        # 是否达到三星
        $is_receive = 0;
        if ($user_star['is_receive'] == 0 && count($arr) == 3) {
            $is_receive = 1;
            Db::table('rush_challenge')->where(['user_id' => $this->user_id])->update(['is_receive' => 1]);
        }
        $res['is_receive'] = $is_receive;
//        dump($is_receive);
//        die;

        if (empty($user_star)) {
            $addData = [
                'user_id' => $this->user_id,
                'pass_id' => $pass_id,
                'criteria' => $criteria,
                'is_receive' => $is_receive,
                'add_time' => date('Y-m-d'),
                'timestamp' => time(),
            ];
            Db::table('rush_challenge')->insert($addData);
            return jsonResult('关卡纪录成功', 200, $addData);
        } else {
            Db::table('rush_challenge')->where(['user_id' => $this->user_id, 'pass_id' => $pass_id])->update(['criteria' => $criteria]);
            return jsonResult('关卡记录更新成功', 200, $res);
        }
    }

    # 关卡挑战记录
    public function challenge_new()
    {
//        $this->user_id = 2;
        if (empty($this->user_id)) return jsonResult('error', 110);

        $post = request()->only(['pass_id', 'criteria']);

//        dump($post);die;

        $exp = explode(',', $post['criteria']);
        $pass_id = count($exp) > 0 && $exp[0] != '' ? $post['pass_id'] + 1 : $post['pass_id'];

        if ($pass_id >= 19) {
            $pass_id = 19;
        }

        $max_pass = Db::table('rush_user')->where(['user_id' => $this->user_id])->value('max_pass');
        if ($pass_id > $max_pass) {
            Db::table('rush_user')->where(['user_id' => $this->user_id])->update(['max_pass' => $pass_id]);
        }

        $user_star = Db::table('rush_challenge')->field('criteria,is_receive')->where(['user_id' => $this->user_id, 'pass_id' => $post['pass_id']])->find();
//        dump($user_star);
        $num = empty($user_star['criteria']) ? [] : explode(',', $user_star['criteria']);
        $arr = array_unique(array_merge($exp, $num));
        $criteria = join(',', $arr);

        # 是否达到三星
        $is_receive = 0;
        if ($user_star['is_receive'] == 0 && count($arr) == 3) {
            $is_receive = 1;
            Db::table('rush_challenge')->where(['user_id' => $this->user_id])->update(['is_receive' => 1]);
        }
        $res['is_receive'] = $is_receive;
//        dump($is_receive);
//        die;

        if (empty($user_star)) {
            $addData = [
                'user_id' => $this->user_id,
                'pass_id' => $post['pass_id'],
                'criteria' => $criteria,
                'is_receive' => $is_receive,
                'add_time' => date('Y-m-d'),
                'timestamp' => time(),
            ];
            Db::table('rush_challenge')->insert($addData);
            return jsonResult('关卡纪录成功', 200, $addData);
        } else {
            Db::table('rush_challenge')->where(['user_id' => $this->user_id, 'pass_id' => $post['pass_id']])->update(['criteria' => $criteria]);
            return jsonResult('关卡记录更新成功', 200, $pass_id);
        }
    }

    # 广告列表
    public function more()
    {
        $res['app'] = Db::table('rush_app')->field('id,app_id,app_name,app_text,app_url,app_qrcode_url,app_more_url,page')->where(['play_status' => 0])->select();

        return jsonResult('广告列表', 200, $res);
    }

    # 渠道统计
    public function statistics_channel()
    {
        if (empty($this->user_id)) return jsonResult('error', 110);
        $post = request()->only(['channel', 'scene', 'enter_id'], 'post'); //channel 渠道ID 例如：1001  scene 场景值 enter_id 来源小程序ID

        # 用户从哪个渠道进入
        $record = [
            'user_id' => $this->user_id,
            'enter_id' => $post['enter_id'],
            'channel_id' => $post['channel'],
            'scene' => $post['scene'],
            'add_date' => date('Y-m-d'),
            'add_time' => date('Y-m-d H:i:s'),
        ];
        Db::table('rush_record_channel')->insert($record);
        return jsonResult('渠道统计成功', 200, $record);
    }

    # 渠道统计
    public function statistics_channel_bak()
    {
        $user_id = $this->user_id;
        if (empty($user_id)) return jsonResult('error', 110);

//        $date = date('Y-m-d');
        $post = request()->only(['channel', 'scene', 'enter_id'], 'post'); //channel 渠道ID 例如：1001  scene 场景值 enter_id 来源小程序ID
        $enter_id = $post['enter_id'];

        $is_channel = Db::Table('rush_user')->field('user_id,channel,enter_id')->where(['user_id' => $this->user_id])->find();
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
            Db::Table('rush_user')->where(['user_id' => $user_id])->update($upData);
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
            $app_id = Db::Table('rush_app')->where(['app_id' => $app_id, 'play_status' => 0])->value('id');
        }

        $is_click = Db::table('rush_statistics_adv')
            ->where(['user_id' => $this->user_id, 'app_id' => $post['app_id'], 'status' => 1, 'add_time' => $date])
            ->value('statistics_id');

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
            Db::Table('rush_statistics_adv')->insert($advData);

            if ($post['status'] == 1) {
                # 广告限量
                $restrict_id = Db::Table('rush_restrict')->where(['app_id' => $app_id, 'add_time' => $date])->value('restrict_id');
                if (empty($restrict_id)) {
                    $addData = [
                        'app_id' => $app_id,
                        'num' => 1,
                        'add_time' => $date,
                    ];
                    Db::Table('rush_restrict')->insert($addData);
                } else {
                    Db::Table('rush_restrict')->where(['restrict_id' => $restrict_id])->setInc('num');
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
        Db::table('rush_watch_video')->insert($post);
        return jsonResult('记录成功', 200);
    }


    public function demo()
    {
//        $data = array(
//            array(
//                'id' => 5698,
//                'first_name' => 'Bill',
//                'last_name' => 'Gates',
//                'num' => 1,
//            ),
//            array(
//                'id' => 4767,
//                'first_name' => 'Steve',
//                'last_name' => 'Aobs',
//                'num' => 3,
//            ),
//            array(
//                'id' => 3809,
//                'first_name' => 'Mark',
//                'last_name' => 'Zuckerberg',
//                'num' => 2,
//            )
//        );
//
//        //根据字段last_name对数组$data进行降序排列
//        $last_names = array_column($data,'num');
//        array_multisort($last_names,SORT_ASC,$data);
//        var_dump($data);

        # 广告列表
//        $app = Db::table('rush_app')
//            ->field('id,app_id,app_name,app_url,app_banner_url,page,play_status,play_sort,banner_status,banner_sort')
//            ->where(['play_status' => 0])
//            ->order('play_sort')
//            ->select();
//        $data['app'] = $app;
//
//        # banner
//        $banner = dengyu($app, 'banner_status', 0);
//        //根据字段last_name对数组$data进行降序排列
//        $last_names = array_column($banner,'banner_sort');
//        array_multisort($last_names,SORT_ASC,$banner);
//        $data['banner'] = $banner;
//        dump($data);


        # 九宫格
        $app = Db::Table('singleMotor_app')
            ->field('id,app_id,app_name,app_url,app_more_url,app_banner_url,page,play_status,play_sort,status,sort,banner_status,banner_sort')
            ->where('play_status', 0)
            ->order('play_sort')
            ->limit(10)
            ->select();


        # 九宫格
        $data['app'] = dengyu($app, 'play_status', 0);
        # 猜你喜欢
        $data['like'] = dengyu($app, 'status', 0);
        $sort_arr = [];
        foreach ($data['like'] as $key => $value) {
            $sort_arr[] = $value['sort'];
        }
        array_multisort($sort_arr, SORT_ASC, $data['like']);

        # banner
        $data['banner'] = dengyu($app, 'banner_status', 0);
        $sort_arr1 = [];
        foreach ($data['banner'] as $key => $value) {
            $sort_arr1[] = $value['banner_sort'];
        }
        array_multisort($sort_arr1, SORT_ASC, $data['banner']);


//        # 九宫格
//        $data['app'] = $app;
//
//        # 猜你喜欢
//        $like = dengyu($app, 'status', 0);
//        $last_like = array_column($like,'sort');
//        array_multisort($last_like,SORT_ASC,$like);
//        $data['like'] = $like;
//
//        # banner
//        $banner = dengyu($app, 'banner_status', 0);
//        $last_banner = array_column($banner,'banner_sort');
//        array_multisort($last_banner,SORT_ASC,$banner);
//        $data['banner'] = $banner;

        dump($data);

    }

}