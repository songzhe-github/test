<?php
   /**
    * WX探案
    * User: SongZhe
    * Date: 2019/8/13
    * Time: 17:02
    */

   namespace app\index\controller;

   use think\Db;
   use ip\IpLocation;

   class Detective extends Conmmon
   {
      /**
       * 获取code，返回openid
       */
      public function getCode()
      {
         if (request()->isPost()) {


            $code = input('post.code');
            if (empty($code)) return jsonResult('你瞅啥', 100);
            $appid = 'wxca069ca328f64bc9';
            $appsecret = 'e10dd26475bd9fbb0d939f1c54a94aeb';
            $data = $this->getOpenid($appid, $appsecret, $code); // 获得 openid 和 session_key
//            return jsonResult('succ',200,$data);

            $user = Db::table('Detective_user')->field('user_id,openid')->where(['openid' => $data['openid']])->find();
            if (empty($user)) {
               $num = substr(time(), -6);
               $arr = [
                  'openid' => $data['openid'],
                  'user_name' => '游客' . $num,
                  'add_date' => date('Y-m-d'),
                  'add_timestamp' => time(),
                  'energyNum' => 8,
                  'free_num' => 3,
               ];
               $uid = Db::table('Detective_user')->insertGetId($arr);
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
         $userInfo = Db::table('Detective_user')
            ->field('openid,add_date,add_timestamp', true)
            ->where(['user_id' => $this->user_id])
            ->find();
         $userInfo['role'] = empty($userInfo['role']) ? [['lv' => 0, 'status' => 0], ['lv' => 0, 'status' => 0]] : json_decode($userInfo['role']);
         $userInfo['free_num'] = date('Y-m-d', $userInfo['offline_timestamp']) < date('Y-m-d', time()) ? 3 : $userInfo['free_num'];
         $userInfo['isDressArr'] = empty($userInfo['isDressArr']) ? [0, 0, 0, 0] : json_decode($userInfo['isDressArr']);

         // 数据库默认家具
         $json_string = file_get_contents('json/Tdetective_config_furniture.json');
         $furnitureArr = json_decode($json_string, true);
         foreach ($furnitureArr as $k => $v) {
            foreach ($v as $kk => $vv) {
               if ($kk == 'furniture_name' || $kk == 'furniture_energyNum' || $kk == 'furniture_unlock_coin' || $kk == 'furniture_up_coin' || $kk == 'furniture_output_vit' || $kk == 'furniture_big_pic' || $kk == 'furniture_small_pic') {
                  unset($furnitureArr[$k][$kk]);
               }
            }
         }
         $furnitureArr = dataGroup($furnitureArr, 'furniture_id');
         $DressClassArr['Drink'] = $furnitureArr[0];
         $DressClassArr['Sofa'] = $furnitureArr[1];
         $DressClassArr['Floor'] = $furnitureArr[2];
         $DressClassArr['Wall'] = $furnitureArr[3];
         if (empty($userInfo['Dress'])) {
            // 新用户
            $userInfo['Dress'] = $DressClassArr;
         } else {
            $UserDressArr1 = json_decode($userInfo['Dress'], true);
            foreach ($DressClassArr as $DressArr_key => $DressArr_value) {
               foreach ($UserDressArr1 as $UserDressArr_key1 => $UserDressArr_value1) {
                  if ($DressArr_key == $UserDressArr_key1) {
                     foreach ($DressArr_value as $kk1 => $vv1) {
                        foreach ($UserDressArr_value1 as $kk2 => $vv2) {
                           if ($vv1['id'] == $vv2['id']) {
                              $DressClassArr[$DressArr_key][$kk1]['status'] = $vv2['status'];
                           }
                        }
                     }
                  }
               }
            }
            $userInfo['Dress'] = $DressClassArr;
         }
         $data['userInfo'] = $userInfo;


         # config配置表
         $config = Db::table('Detective_config')->find();
         if ($config['all_configInfo_status'] == 1) {
            # 关卡配置
//         $passInfo = Db::table('Detective_config_passInfo')->field('detective_id', true)->order('pass_id,question_id')->select();
            $json_string = file_get_contents('json/Tdetective_config_passInfo.json');
            $passInfo = json_decode($json_string, true);

            $passInfoArr = dataGroup($passInfo, 'pass_id');
            foreach ($passInfoArr as $kk => $vv) {
               foreach ($vv as $key => $value) {
                  if (empty($value['answer'])) continue;

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
            $roleArray = Db::table('Detective_config_role')->field('id', true)->select();
            $data['Role'] = dataGroup($roleArray, 'role_id');
         }

         # 家具配置
         $json_string = file_get_contents('json/Tdetective_config_furniture.json');
         $furnitureArray = json_decode($json_string, true);
         $furnitureArray = dataGroup($furnitureArray, 'furniture_id');
         $furnitureClassArr['Drink'] = $furnitureArray[0];
         $furnitureClassArr['Sofa'] = $furnitureArray[1];
         $furnitureClassArr['Floor'] = $furnitureArray[2];
         $furnitureClassArr['Wall'] = $furnitureArray[3];
         $data['Dress'] = $furnitureClassArr;

         # 签到列表
         $signList = Db::table('Detective_signlist')->field('signlist_id', true)->select();
         $sign = Db::table('Detective_sign')->where(['user_id' => $this->user_id, 'status' => 0])->select();
         $end = end($sign);
         if ($end['add_time'] < $this->date && count($sign) >= 7) {
            # 满一周status更新为0
            Db::table('Detective_sign')->where(['user_id' => $this->user_id])->update(['status' => 1]);
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

         # 排行榜 - 关卡
         $rank_pass = Db::table('Detective_rank_pass')->field('user_id,user_name,max_pass')->select();
         $userIds = array_column((array)$rank_pass, 'user_id');
         $is_rank = array_keys($userIds, $this->user_id);
         $user = Db::table('Detective_user')->field('user_id,user_name,max_pass')->where(['user_id' => $this->user_id])->find();
         $user['max_pass'] = $user['max_pass'] + 1;
         if (empty($is_rank)) {
            if (empty($ranking)) {
               $ranking = rand(100, 600);
            }
         } else {
            $ranking = $is_rank[0] + 1;
         }
         $data['rankPass'] = $rank_pass;
         $user['ranking'] = $ranking;
         $data['userRankPass'] = $user;

         # 排行榜 - 装扮值
         $rank_dress = Db::table('Detective_rank_dress')->field('user_id,user_name,dress_value')->select();
         $userIds1 = array_column((array)$rank_dress, 'user_id');
         $is_rank = array_keys($userIds1, $this->user_id);
         $user = Db::table('Detective_user')->field('user_id,user_name,dress_value')->where(['user_id' => $this->user_id])->find();
         if (empty($is_rank)) {
            if (empty($ranking1)) {
               $ranking1 = rand(100, 600);
            }
         } else {
            $ranking1 = $is_rank[0] + 1;
         }
         $data['rankDress'] = $rank_dress;
         $user['ranking'] = $ranking1;
         $data['userRankDress'] = $user;

         # 九宫格
         $app = Db::Table('Detective_app')
            ->field('id,app_id,app_name,app_url,page,play_status,status,sort')
            ->where('status', 0)
            ->order('sort')
            ->select();
         $adv = Db::table('Detective_information_adv')->field('app_id,click_count')->where('date', date('Y-m-d', strtotime('-1day')))->select();
         foreach ($app as $app_key => $app_value) {
            foreach ($adv as $adv_key => $adv_value) {
               if ($app_value['id'] === $adv_value['app_id']) {
                  $app[$app_key]['num'] = $adv_value['click_count'] * 999;
               }
            }
         }
         $data['app'] = $app;

         # 根据IP地址是否开启误点
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

         $data['all_configInfo_status'] = $config['all_configInfo_status']; // 所有配置信息开关（关卡、探员、签到）
         $data['isCanErrorClick'] = $isCanErrorClick; // 误点开关
         $data['API'] = request()->action();
         return jsonResult('首页配置信息', 200, $data);
      }

      # 排行榜
      public function rank()
      {
         # 排行榜 - 关卡
         $rank_pass = Db::table('Detective_rank_pass')->field('user_id,user_name,max_pass')->select();
         $userIds = array_column((array)$rank_pass, 'user_id');
         $is_rank = array_keys($userIds, $this->user_id);
         $user = Db::table('Detective_user')->field('user_id,user_name,max_pass')->where(['user_id' => $this->user_id])->find();
         $user['max_pass'] = $user['max_pass'] + 1;
         if (empty($is_rank)) {
            if (empty($ranking)) {
               $ranking = rand(100, 500);
            }
         } else {
            $ranking = $is_rank[0] + 1;
         }
         $data['rankPass'] = $rank_pass;
         $user['ranking'] = $ranking;
         $data['userRankPass'] = $user;

         # 排行榜 - 装扮值
         $rank_dress = Db::table('Detective_rank_dress')->field('user_id,user_name,dress_value')->select();
         $userIds1 = array_column($rank_dress, 'user_id');
         $is_rank = array_keys($userIds1, $this->user_id);
         $user = Db::table('Detective_user')->field('user_id,user_name,dress_value')->where(['user_id' => $this->user_id])->find();
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

         return jsonResult('排行榜', 200, $data);
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
            'free_num' => $userInfo['free_num'],
            'role' => json_encode($userInfo['role'], JSON_UNESCAPED_UNICODE),
            'isDressArr' => json_encode($userInfo['isDressArr'], JSON_UNESCAPED_UNICODE),
            'Dress' => json_encode($userInfo['Dress'], JSON_UNESCAPED_UNICODE),
            'offline_date' => $this->date,
            'offline_timestamp' => time(),
            'is_newplayer' => $userInfo['is_newplayer']
         ];
//        dump($upData);
         Db::table('Detective_user')->where(['user_id' => $this->user_id])->update($upData);
         return jsonResult('离线成功', 200, $upData);
      }

      # 点击签到
      public function clickSign()
      {
         $user_sign = Db::table('Detective_sign')->where(['user_id' => $this->user_id, 'status' => 0])->select();
         $end = end($user_sign);
         if ($end['add_time'] == $this->date) return jsonResult('今日已签到过', 100);

         $day = $end['day'] == 7 ? 1 : $end['day'] + 1;
         $insert = [
            'user_id' => $this->user_id,
            'day' => $day,
            'add_time' => $this->date,
            'status' => 0,
         ];
         Db::table('Detective_sign')->insert($insert);
         return jsonResult('签到成功', 200);
      }

      # 关卡挑战记录
      public function challenge()
      {
         return;
      }

      # 渠道统计
      public function statistics_channel()
      {
         if (empty($this->user_id)) return jsonResult('error', 110);
         $post = request()->only(['channel', 'scene', 'enter_id'], 'post'); //channel 渠道ID 例如：1001  scene 场景值 enter_id 来源小程序ID
         $post['enter_id'] = $post['enter_id'] ? $post['enter_id'] : 666;

         $record_channel_id = Db::table('Detective_record_channel')->where(['user_id' => $this->user_id, 'add_date' => $this->date])->value('record_channel_id');
         if (empty($record_channel_id)) {
            # 用户从哪个渠道进入
            $record = [
               'user_id' => $this->user_id,
               'enter_id' => $post['enter_id'],
               'channel_id' => $post['channel'],
               'scene' => $post['scene'],
               'add_date' => $this->date,
               'add_time' => date('Y-m-d H:i:s'),
            ];
            Db::table('Detective_record_channel')->insert($record);
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
            $app_id = Db::table('Detective_app')->where(['app_id' => $app_id, 'play_status' => 0])->value('id');
         }

         $is_click = Db::table('Detective_statistics_adv')->where(['user_id' => $this->user_id, 'app_id' => $post['app_id'], 'status' => 1, 'add_time' => $date, 'type' => $post['type']])->value('statistics_id');

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
            Db::table('Detective_statistics_adv')->insert($advData);

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
         Db::table('Detective_watch_video')->insert($post);
         return jsonResult('记录成功', 200);
      }

      public function demo()
      {
         # 九宫格
         $app = Db::Table('Detective_app')
            ->field('id,app_id,app_name,app_url,page,play_status,status,sort')
            ->where('status', 0)
            ->order('sort')
            ->select();
         $adv = Db::table('Detective_information_adv')->field('app_id,click_count')->where('date', date('Y-m-d', strtotime('-1day')))->select();

         foreach ($app as $app_key => $app_value) {
            foreach ($adv as $adv_key => $adv_value) {
               if ($app_value['id'] === $adv_value['app_id']) {
                  $app[$app_key]['num'] = $adv_value['click_count'] * 999;
               }
            }
         }
         halt($app);

      }

   }