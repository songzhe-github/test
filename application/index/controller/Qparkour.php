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

   class Qparkour extends Conmmon
   {
      /**
       * 获取code，返回openid
       */
      public function getCode()
      {
         if (request()->isPost()) {
            $code = input('post.code');
            if (empty($code)) return jsonResult('你瞅啥', 100);
            $appid = '1110584478';
            $appsecret = 'RvE8bTopGClhCa79';
            $data = $this->getQQOpenid($appid, $appsecret, $code); // 获得 openid 和 session_key
            $db = Db::connect('multi-platform');
            $user = $db->Table('QQ_parkour_user')->field('user_id,openid')->where(['openid' => $data['openid']])->find();
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
               $uid = $db->table('QQ_parkour_user')->insertGetId($arr);
               $res['mstr'] = lock_url($data['openid'] . ',' . $uid);
               return jsonResult('请求成功', 200, $res);
            } else {
               $db->table('QQ_parkour_user')->where(['user_id' => $user['user_id']])->update(['end_time' => date('Y-m-d H::s')]);
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
         $user = $db->table('QQ_parkour_user')->where(['user_id' => $this->user_id])->value('user_id');
         if (empty($user)) return jsonResult('数据不存在', 100);

         $updata = [
            'user_name' => $user_name,
            'avatar' => empty($avatar) ? '' : $avatar . '?aaa=aa.jpg',
            'sex' => empty($sex) ? '' : $sex,
            'city' => empty($city) ? '' : $city,
            'is_impower' => 1,
            'end_time' => date('Y-m-d H:i:s'),
         ];
         $db->table('QQ_parkour_user')->where(['user_id' => $this->user_id])->update($updata);
         return jsonResult('用户信息', 200);
      }

      # 首页配置信息
      public function index()
      {
         $db = Db::connect('multi-platform');
         # 日活
         $db->table('QQ_parkour_user')->where(['user_id' => $this->user_id])->update(['end_time' => date('Y-m-d H::s')]);

         # 分享文案
         $data['share'] = config('share.sportscar_share_pic');

         # 用户信息
         $data['userInfo'] = $db->table('QQ_parkour_user')->field('user_id,user_name,avatar,max_pass,all_coin,is_impower,new_player_status')->where(['user_id' => $this->user_id])->find();

         # 关卡配置信息
         $data['pass_config'] = config('QQ_parkour_map_config'); // 包含新手引导
         $data['isCanErrorClick'] = $db->table('QQ_parkour_config')->value('isCanErrorClick'); // 误点开关

         # 广告列表

         # 关卡解锁条件
         $pass_config = $db->table('QQ_parkour_pass_unlock')->order('pass_id')->select();
//        dump($pass_config);die;
         $challenge = $db->table('QQ_parkour_challenge')->where(['user_id' => $this->user_id])->select();
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
         return jsonResult('首页配置信息', 200, $data);
      }

      # 离线
      public function offline()
      {
         if (empty($this->user_id)) return jsonResult('error', 110);
         $post = request()->only(['all_coin', 'new_player_status']);

         $db = Db::connect('multi-platform');
         $db->table('QQ_parkour_user')->where(['user_id' => $this->user_id])->update($post);
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
         if ($pass_id > 10) $pass_id = 10;

         $db = Db::connect('multi-platform');
         $max_pass = $db->table('QQ_parkour_user')->where(['user_id' => $this->user_id])->value('max_pass');
         if ($pass_id > $max_pass) {
            $db->table('QQ_parkour_user')->where(['user_id' => $this->user_id])->update(['max_pass' => $pass_id]);
         }

         $user_star = $db->table('QQ_parkour_challenge')->field('criteria,is_receive')->where(['user_id' => $this->user_id, 'pass_id' => $pass_id])->find();
//        dump($user_star);
         $num = empty($user_star['criteria']) ? [] : explode(',', $user_star['criteria']);
         $arr = array_unique(array_merge($exp, $num));
         $criteria = join(',', $arr);

         # 是否达到三星
         $is_receive = 0;
         if ($user_star['is_receive'] == 0 && count($arr) == 3) {
            $is_receive = 1;
            $db->table('QQ_parkour_challenge')->where(['user_id' => $this->user_id])->update(['is_receive' => 1]);
         }
         $res['is_receive'] = $is_receive;
//        dump($is_receive);

         if (empty($user_star)) {
            $addData = [
               'user_id' => $this->user_id,
               'pass_id' => $pass_id,
               'criteria' => $criteria,
               'is_receive' => $is_receive,
               'add_time' => date('Y-m-d'),
               'timestamp' => time(),
            ];
            $db->table('QQ_parkour_challenge')->insert($addData);
            return jsonResult('关卡纪录成功', 200, $addData);
         } else {
            $db->table('QQ_parkour_challenge')->where(['user_id' => $this->user_id, 'pass_id' => $pass_id])->update(['criteria' => $criteria]);
            return jsonResult('关卡记录更新成功', 200, $res);
         }
      }

      # 关卡挑战记录
      public function challenge_new()
      {
         if (empty($this->user_id)) return jsonResult('error', 110);
         $post = request()->only(['pass_id', 'criteria']);

         $exp = explode(',', $post['criteria']);
         $pass_id = count($exp) > 0 && $exp[0] != '' ? $post['pass_id'] + 1 : $post['pass_id'];

         if ($pass_id >= 19) {
            $pass_id = 19;
         }

         $db = Db::connect('multi-platform');
         $max_pass = $db->table('QQ_parkour_user')->where(['user_id' => $this->user_id])->value('max_pass');
         if ($pass_id > $max_pass) {
            $db->table('QQ_parkour_user')->where(['user_id' => $this->user_id])->update(['max_pass' => $pass_id]);
         }

         $user_star = $db->table('QQ_parkour_challenge')->field('criteria,is_receive')->where(['user_id' => $this->user_id, 'pass_id' => $post['pass_id']])->find();
//        dump($user_star);
         $num = empty($user_star['criteria']) ? [] : explode(',', $user_star['criteria']);
         $arr = array_unique(array_merge($exp, $num));
         $criteria = join(',', $arr);

         # 是否达到三星
         $is_receive = 0;
         if ($user_star['is_receive'] == 0 && count($arr) == 3) {
            $is_receive = 1;
            $db->table('QQ_parkour_challenge')->where(['user_id' => $this->user_id])->update(['is_receive' => 1]);
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
            $db->table('QQ_parkour_challenge')->insert($addData);
            return jsonResult('关卡纪录成功', 200, $addData);
         } else {
            $db->table('QQ_parkour_challenge')->where(['user_id' => $this->user_id, 'pass_id' => $post['pass_id']])->update(['criteria' => $criteria]);
            return jsonResult('关卡记录更新成功', 200, $pass_id);
         }
      }


      # 广告列表
      public function more()
      {
         $db = Db::connect('multi-platform');
         $res['app'] = $db->table('QQ_parkour_app')->field('id,app_id,app_name,app_text,app_url,app_qrcode_url,app_more_url,page')->where(['play_status' => 0])->select();
         return jsonResult('广告列表', 200, $res);
      }

      # 渠道统计
      public function statistics_channel()
      {
         $user_id = $this->user_id;
         if (empty($user_id)) return jsonResult('error', 110);

//        $date = date('Y-m-d');
         $post = request()->only(['channel', 'scene', 'enter_id'], 'post'); //channel 渠道ID 例如：1001  scene 场景值 enter_id 来源小程序ID
         $enter_id = $post['enter_id'];

         $db = Db::connect('multi-platform');
         $is_channel = $db->Table('QQ_parkour_user')->field('user_id,channel,enter_id')->where(['user_id' => $this->user_id])->find();
         if (empty(intval($is_channel['channel']))) { // 用户表channel为空

            if ($is_channel['enter_id']) {

               // 用户表有enter_id channel为0
               $channel = $db->Table('channel_product_info')
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
               $channel2 = $db->Table('channel_info')->where(['enter_id' => $enter_id])->value('qd_id');
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
            $db->Table('QQ_parkour_user')->where(['user_id' => $user_id])->update($upData);
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
         $post = request()->only(['app_id', 'type', 'position', 'status', 'pass'], 'post');
         if (empty($user_id) || empty($post['app_id'])) return jsonResult('error', 110);

         # 广告统计
         $app_id = $post['app_id'];

         $db = Db::connect('multi-platform');
         if (strlen($app_id) > 6) {
            $app_id = $db->Table('QQ_parkour_app')->where(['app_id' => $app_id])->value('id');
         }

         $is_click = $db->table('QQ_parkour_statistics_adv')->where(['user_id' => $this->user_id, 'app_id' => $post['app_id'], 'status' => 1, 'add_time' => $date])->value('statistics_id');
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
            $db->Table('QQ_parkour_statistics_adv')->insert($advData);

            if ($post['status'] == 1) {
               # 广告限量
               $restrict_id = $db->Table('QQ_parkour_restrict')->where(['app_id' => $app_id, 'add_time' => $date])->value('restrict_id');
               if (empty($restrict_id)) {
                  $addData = [
                     'app_id' => $app_id,
                     'num' => 1,
                     'add_time' => $date,
                  ];
                  $db->Table('QQ_parkour_restrict')->insert($addData);
               } else {
                  $db->Table('QQ_parkour_restrict')->where(['restrict_id' => $restrict_id])->setInc('num');
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
         $db = Db::connect('multi-platform');
         $db->table('QQ_parkour_watch_video')->insert($post);
         return jsonResult('记录成功', 200);
      }


      public function demo()
      {
         $db = Db::connect('multi-platform');
         $data = $db->Table('QQ_parkour_app')->field('app_name,play_status,play_sort,status,sort')->order('play_status ASC,status ASC')->select();

         dump($data);
      }

   }