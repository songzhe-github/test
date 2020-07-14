<?php
/**
 * 头条-末日射击
 * User: SongZhe
 * Date: 2019/8/13
 * Time: 17:02
 */

namespace app\index\controller;

use think\Db;
use ip\IpLocation;

class Qsnipe extends Conmmon
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

            $user = Db::Table('QQ_snipe_user')->field('user_id,openid')->where(['openid' => $data['openid']])->find();
            $num = substr(time(), -6);
            if (empty($user)) {
                $arr = [
                    'openid' => $data['openid'],
                    'user_name' => '游客' . $num,
                    'max_pass' => 0,
                    'add_time' => date('Y-m-d'),
                ];
                $uid = Db::Table('QQ_snipe_user')->insertGetId($arr);
                $res['mstr'] = lock_url($data['openid'] . ',' . $uid);
                return jsonResult('请求成功', 200, $res);

            } else {
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

        $user = Db::Table('QQ_snipe_user')->where('user_id', $this->user_id)->value('user_id');
        if (empty($user)) return jsonResult('数据不存在', 100);

        $updata = [
            'user_name' => $user_name,
            'avatar' => empty($avatar) ? '' : $avatar . '?aaa=aa.jpg',
            'sex' => empty($sex) ? '' : $sex,
            'city' => empty($city) ? '' : $city,
            'end_time' => date('Y-m-d H:i:s'),
        ];
        Db::Table('QQ_snipe_user')->where('user_id', $this->user_id)->update($updata);
        return jsonResult('用户信息', 200);
    }

    # 首页配置信息
    public function index()
    {
        $version_number = input('post.version_number');

        # 分享文案
        $data['share'] = config('share.snipe_share_pic');

        # 用户信息
        $userInfo = Db::Table('QQ_snipe_user')
            ->field('user_id,user_name,max_pass,coin,diamond,role_lv,skill_lv,selectGunInfo,gunInfo,new_player')
            ->where('user_id', $this->user_id)
            ->find();
        $userInfo['selectGunInfo'] = empty($userInfo['selectGunInfo']) ? [0, 0] : json_decode($userInfo['selectGunInfo']);
        $userInfo['new_player'] = $userInfo['new_player'] ?? 0;

        # 武器库
        $arsenal = Db::Table('QQ_snipe_config_arsenal')->field('class_id,gun_name,lv,unlock_type,unlock_coin,unlock_video,video_num,is_unlock')->select();
        $arsenalArr = $this->dataGroup($arsenal, 'class_id');
        if (empty($userInfo['gunInfo'])) {
            // 新用户
            $userInfo['gunInfo'] = $arsenalArr;
        } else {
            // 老用户
            $gunInfo = json_decode($userInfo['gunInfo'], true);
            for ($i = 0; $i < count($arsenalArr); $i++) {
                foreach ($arsenalArr[$i] as &$value) {
                    foreach ($gunInfo[$i] as &$v) {
                        if ($value['gun_name'] == $v['gun_name']) {
                            $value['lv'] = $v['lv'];
                            $value['video_num'] = $v['video_num'];
                            $value['is_unlock'] = $v['is_unlock'];
                        }
                    }
                }
            }
            $userInfo['gunInfo'] = $arsenalArr;
        }
        $data['userInfo'] = $userInfo;

        # 关卡配置信息
        $snipePassInfo = Db::table('QQ_snipe_config_passInfo')->order('Pass')->select();
        foreach ($snipePassInfo as &$value) {
            $PassInfoArr = explode(',', trim($value['PassInfo']));
            if ($PassInfoArr) {
                $PassInfo['bulletNum'] = empty($PassInfoArr[0]) ? 0 : (float)$PassInfoArr[0];
                $PassInfo['timeBar'] = empty($PassInfoArr[1]) ? 0 : (float)$PassInfoArr[1];
                $PassInfo['killNum'] = empty($PassInfoArr[2]) ? 0 : (float)$PassInfoArr[2];
                $PassInfo['rewardNum'] = empty($PassInfoArr[3]) ? 0 : (float)$PassInfoArr[3];
                $value['PassInfo'] = $PassInfo;
            }

            $PassTextLongArr = explode(',', trim($value['PassTextLong']));
            if ($PassTextLongArr) {
                $PassTextLong['time'] = empty($PassTextLongArr[0]) ? 0 : (float)$PassTextLongArr[0];
                $PassTextLong['type'] = empty($PassTextLongArr[1]) ? 0 : (float)$PassTextLongArr[1];
                $value['PassTextLong'] = $PassTextLong;
            }

            $BuildArray = explode(';', trim($value['Build']));
            $BuildArray = array_filter($BuildArray);
            if ($BuildArray) {
                $arr1 = [];
                for ($i = 0; $i < count($BuildArray); $i++) {
                    $BuildArr = explode(',', $BuildArray[$i]);
                    $Build['type'] = empty($BuildArr[0]) ? 0 : (float)$BuildArr[0];
                    $Build['level'] = empty($BuildArr[1]) ? 0 : (float)$BuildArr[1];
                    $Build['posX'] = empty($BuildArr[2]) ? 0 : (float)$BuildArr[2];
                    $Build['posY'] = empty($BuildArr[3]) ? 0 : (float)$BuildArr[3];
                    $arr1[] = $Build;
                }
                $value['Build'] = $arr1;
            }

            $HumanArray = trim((string)$value['Human']);
            $HumanArray = explode(';', $HumanArray);
            $HumanArray = array_filter($HumanArray);
            $HumanArr1 = [];
            foreach ($HumanArray as $i => $j) {
                $Human1 = str_replace("{", "[", $j);
                $Human2 = str_replace("}", "]", $Human1);
                $Human2 = str_replace("\n", " ", $Human2);
                $Human3 = "[" . $Human2 . "]";
                $HumanArr = json_decode($Human3);
                $Human['type'] = empty($HumanArr[0]) ? 0 : (float)$HumanArr[0];
                $Human['actionType'] = empty($HumanArr[1]) ? 0 : (float)$HumanArr[1];
                $Human['actionTime'] = empty($HumanArr[2]) ? 0 : (float)$HumanArr[2];
                $Human['startPosX'] = empty($HumanArr[3]) ? 0 : (float)$HumanArr[3];
                $Human['startPosY'] = empty($HumanArr[4]) ? 0 : (float)$HumanArr[4];
                $Human['scaleX'] = empty($HumanArr[5]) ? 0 : (float)$HumanArr[5];
                $Human['moveArray'] = empty($HumanArr[6]) ? [] : $HumanArr[6];
                $Human['talkArray'] = empty($HumanArr[7]) ? [] : $HumanArr[7];
                $HumanArr1[] = $Human;
            }
            $value['Human'] = $HumanArr1;

            $EnemyArray = trim($value['Enemy']);
            $EnemyArray = explode(';', $EnemyArray);
            $EnemyArray = array_filter($EnemyArray);
            $EnemyArr1 = [];
            foreach ($EnemyArray as $i => $j) {
                $Enemy1 = str_replace("{", "[", $j);
                $Enemy2 = str_replace("}", "]", $Enemy1);
                $Enemy3 = "[" . $Enemy2 . "]";
                $EnemyArr = json_decode($Enemy3);

                $Enemy['type'] = empty($EnemyArr[0]) ? 0 : (float)$EnemyArr[0];
                $Enemy['hp'] = empty($EnemyArr[1]) ? 0 : (float)$EnemyArr[1];
                $Enemy['actionType'] = empty($EnemyArr[2]) ? 0 : (float)$EnemyArr[2];
                $Enemy['actionTime'] = empty($EnemyArr[3]) ? 0 : (float)$EnemyArr[3];
                $Enemy['startPosX'] = empty($EnemyArr[4]) ? 0 : (float)$EnemyArr[4];
                $Enemy['startPosY'] = empty($EnemyArr[5]) ? 0 : (float)$EnemyArr[5];
                $Enemy['scaleX'] = empty($EnemyArr[6]) ? 0 : (float)$EnemyArr[6];
                $Enemy['moveArray'] = empty($EnemyArr[7]) ? [] : $EnemyArr[7];
                $EnemyArr1[] = $Enemy;
            }
            $value['Enemy'] = $EnemyArr1;

            $OtherArray = trim($value['Other']);
            $OtherArray = explode(';', $OtherArray);
            $OtherArray = array_filter($OtherArray);
            $OtherArr1 = [];
            foreach ($OtherArray as $i => $j) {
                $Other1 = str_replace("{", "[", $j);
                $Other2 = str_replace("}", "]", $Other1);
                $Other3 = "[" . $Other2 . "]";
                $OtherArr = json_decode($Other3);

                $Other['type'] = empty($OtherArr[0]) ? 0 : (float)$OtherArr[0];
                $Other['hp'] = empty($OtherArr[1]) ? 0 : (float)$OtherArr[1];
                $Other['actionType'] = empty($OtherArr[2]) ? 0 : (float)$OtherArr[2];
                $Other['actionTime'] = empty($OtherArr[3]) ? 0 : (float)$OtherArr[3];
                $Other['startPosX'] = empty($OtherArr[4]) ? 0 : (float)$OtherArr[4];
                $Other['startPosY'] = empty($OtherArr[5]) ? 0 : (float)$OtherArr[5];
                $Other['scaleX'] = empty($OtherArr[6]) ? 0 : (float)$OtherArr[6];
                $Other['level'] = empty($OtherArr[7]) ? 0 : (float)$OtherArr[7];
                $Other['moveArray'] = empty($OtherArr[8]) ? [] : $OtherArr[8];
                $OtherArr1[] = $Other;
            }
            $value['Other'] = $OtherArr1;

            # 每关结束奖励
            $prizeData = explode(';', $value['Prize']);
            $prizeList = [];
            foreach ($prizeData as $i => $j) {
                $prizeArr = '[' . $j . ']';
                $prizeArr = json_decode($prizeArr);
                $prize1['type'] = $prizeArr[0] ?? 0;
                $prize1['prizeNum'] = $prizeArr[1] ?? 0;
                $prizeList[] = $prize1;
            }
            $value['Prize'] = $prizeList;
        }
        $data['passInfo'] = $snipePassInfo;

        # 枪的配置信息
        $snipeGunInfo = Db::table('QQ_snipe_config_gunInfo')->field('rifleNo,sniperNo')->find();
        $rifleNoArray = explode(';', trim($snipeGunInfo['rifleNo']));
        $rifleNoArray = array_filter($rifleNoArray);
        $rifleNo = [];
        foreach ($rifleNoArray as $i => $j) {
            $rifleNo1 = str_replace("{", "[", $j);
            $rifleNo2 = str_replace("}", "]", $rifleNo1);
            $rifleNoArr = json_decode($rifleNo2);
            $rifleNo[]['rifleInfo'] = $rifleNoArr;
        }
        $snipeGunInfo['rifleNo'] = $rifleNo;

        $sniperNoArray = explode(';', trim($snipeGunInfo['sniperNo']));
        $sniperNoArray = array_filter($sniperNoArray);
        $sniperNoArr = [];
        foreach ($sniperNoArray as $i => $j) {
            $sniperNo1 = str_replace("{", "[", $j);
            $sniperNo2 = str_replace("}", "]", $sniperNo1);
            $sniperNoArr[]['sniperInfo'] = json_decode($sniperNo2);
        }
        $snipeGunInfo['sniperNo'] = $sniperNoArr;
        $data['gunInfo'] = $snipeGunInfo;


        # 军衔系统
        $data['admiralRank'] = Db::table('QQ_snipe_config_admiralRank')->field('lv,admiralRank_name,harm,probability,gem_num')->select();

        # 技能配置
        $skillInfo = Db::table('QQ_snipe_config_skill')->field('skill_name,skill_text,skill_level_name,skill_type,skill_number')->select();
        $data['skillInfo'] = $this->dataGroup($skillInfo, 'skill_type');

        # 被动技能配置
        $skillInfo = Db::table('QQ_snipe_config_skill_passive')->field('skill_name,skill_lv,skill_number,skill_harm')->select();
        $data['skillPassiveInfo'] = $skillInfo;

        # 签到列表
        $signList = Db::table('QQ_snipe_signlist')->field('day,num,type,status')->select();
        $sign = Db::table('QQ_snipe_sign')->where(['user_id' => $this->user_id, 'status' => 0])->select();
        $end = end($sign);
        if ($end['add_time'] < $this->date && count($sign) >= 7) {
            # 满一周status更新为0
            Db::table('QQ_snipe_sign')->where(['user_id' => $this->user_id])->update(['status' => 1]);
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

        # 根据IP地址是否开启误点
        $config = Db::Table('QQ_snipe_config')->find();
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
        $data['isCanErrorClick'] = $version_number != $config['version_number'] ? 0 : $isCanErrorClick; // 误点开关
        $data['API'] = request()->action();
        return jsonResult('首页配置信息', 200, $data);
    }

    # 点击签到

    protected function dataGroup($dataArr, $keyStr)
    {
        $newArr = [];
        foreach ($dataArr as $k => $val) {    //数据根据日期分组
            $newArr[$val[$keyStr]][] = $val;
        }
        $data = [];
        foreach ($newArr as $k => $v) {
            array_push($data, $v);
        }
        return $data;
    }

    # 排行榜

    public function drawSign()
    {
        $user_sign = Db::table('QQ_snipe_sign')->where(['user_id' => $this->user_id, 'status' => 0])->select();
        $end = end($user_sign);
        if ($end['add_time'] == $this->date) return jsonResult('今日已签到过', 100);

        $day = $end['day'] == 7 ? 1 : $end['day'] + 1;
        $insert = [
            'user_id' => $this->user_id,
            'day' => $day,
            'add_time' => $this->date,
            'status' => 0,
        ];
        Db::table('QQ_snipe_sign')->insert($insert);
        return jsonResult('签到成功', 200);
    }

    # 离线

    public function rank()
    {
        $ranks = Db::Table('QQ_snipe_rank_one')->field('user_id,user_name,max_pass')->select();
        $userIds = array_column($ranks, 'user_id');
        $is_rank = array_keys($userIds, $this->user_id);

        $user = Db::Table('QQ_snipe_user')->field('user_name,max_pass')->where('user_id', $this->user_id)->find();
        if (empty($is_rank)) {
            $ranking = '30+';
        } else {
            $ranking = $is_rank[0] + 1;
        }
        $user['ranking'] = $ranking;
        $data['ranks'] = $ranks;
        $data['user_rank'] = $user;

        return jsonResult('排行榜', 200, $data);
    }

    # 渠道统计

    public function offline()
    {
        if (empty($this->user_id)) return jsonResult('error', 110);
        $userInfo = input('post.userInfo/a');
        $upData = [
            'coin' => $userInfo['coin'],
            'diamond' => $userInfo['diamond'],
            'max_pass' => $userInfo['max_pass'],
            'role_lv' => $userInfo['role_lv'],
            'new_player' => $userInfo['new_player'],
            'gunInfo' => json_encode($userInfo['gunInfo']),
            'selectGunInfo' => json_encode($userInfo['selectGunInfo']),
            'offline_time' => date('Y-m-d H:i:s'),
        ];
        Db::table('QQ_snipe_user')->where('user_id', $this->user_id)->update($upData);
        return jsonResult('离线成功', 200);
    }

    # 记录用户观看视频

    public function statistics_channel()
    {
        if (empty($this->user_id)) return jsonResult('error', 110);
        $post = request()->only(['channel', 'scene', 'enter_id'], 'post'); //channel 渠道ID 例如：1001  scene 场景值 enter_id 来源小程序ID
        $post['enter_id'] = $post['enter_id'] ? $post['enter_id'] : 666;
        $date = date('Y-m-d');

        $record_channel_id = Db::Table('QQ_snipe_record_channel')->where(['user_id' => $this->user_id, 'add_date' => $date])->value('record_channel_id');
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
            Db::Table('QQ_snipe_record_channel')->insert($record);
            return jsonResult('渠道统计成功', 200, $record);
        }
        return jsonResult('今日已统计', 100);
    }

    # 根据某个字段分组

    public function watchVideo()
    {
        $post = request()->only(['type', 'text', 'pass'], 'post');
        $post['user_id'] = $this->user_id;
        $post['add_time'] = date('Y-m-d');
        $post['timestamp'] = time();
        Db::Table('QQ_snipe_watch_video')->insert($post);
        return jsonResult('记录成功', 200);
    }

    public function demo()
    {
        # 关卡配置信息
        $snipePassInfo = Db::table('QQ_snipe_config_passInfo')->select();
        foreach ($snipePassInfo as &$value) {


//            $array = ['Human', 'Enemy', 'Other'];
//            for ($i=0;$i<count($array);$i++ ) {
//                $HumanArray = $value["$array[0]"];
////                dump($value["$array[$i]"]);die;
//                dump($HumanArray);
//                dump("++++++++++++++++++++++++++++++++++++++++");
//                $HumanArray = explode(';', $HumanArray);
//                dump($HumanArray);
//                $HumanArray = array_filter($HumanArray);
//                $HumanArr1 = [];
//                foreach ($HumanArray as $i => $j) {
//                    $Human1 = str_replace("{", "[", $j);
//                    $Human2 = str_replace("}", "]", $Human1);
//                    $Human3 = "[" . $Human2 . "]";
//                    $HumanArr = json_decode($Human3);
//                    $Human['type'] = empty($HumanArr[0]) ? 0 : (float)$HumanArr[0];
//                    $Human['actionType'] = empty($HumanArr[1]) ? 0 : (float)$HumanArr[1];
//                    $Human['actionTime'] = empty($HumanArr[2]) ? 0 : (float)$HumanArr[2];
//                    $Human['startPosX'] = empty($HumanArr[3]) ? 0 : (float)$HumanArr[3];
//                    $Human['startPosY'] = empty($HumanArr[4]) ? 0 : (float)$HumanArr[4];
//                    $Human['scaleX'] = empty($HumanArr[5]) ? 0 : (float)$HumanArr[5];
//                    $Human['moveArray'] = empty($HumanArr[6]) ? [] : $HumanArr[6];
//                    $HumanArr1[] = $Human;
//                }
//                $value["$array[$i]"] = $HumanArr1;
//            }
//            $PassInfoArr = explode(',', trim($value['PassInfo']));
//            if ($PassInfoArr) {
//                $PassInfo['bulletNum'] = empty($PassInfoArr[0]) ? 0 : (float)$PassInfoArr[0];
//                $PassInfo['timeBar'] = empty($PassInfoArr[1]) ? 0 : (float)$PassInfoArr[1];
//                $PassInfo['killNum'] = empty($PassInfoArr[2]) ? 0 : (float)$PassInfoArr[2];
//                $PassInfo['rewardNum'] = empty($PassInfoArr[3]) ? 0 : (float)$PassInfoArr[3];
//                $value['PassInfo'] = $PassInfo;
//            }
//
//            $BuildArray = explode(';', trim($value['Build']));
//            $BuildArray = array_filter($BuildArray);
//            if ($BuildArray) {
//                $arr1 = [];
//                for ($i = 0; $i < count($BuildArray); $i++) {
//                    $BuildArr = explode(',', $BuildArray[$i]);
//                    $Build['type'] = empty($BuildArr[0]) ? 0 : (float)$BuildArr[0];
//                    $Build['level'] = empty($BuildArr[1]) ? 0 : (float)$BuildArr[1];
//                    $Build['posX'] = empty($BuildArr[2]) ? 0 : (float)$BuildArr[2];
//                    $Build['posY'] = empty($BuildArr[3]) ? 0 : (float)$BuildArr[3];
//                    $arr1[] = $Build;
//                }
//                $value['Build'] = $arr1;
//            }

            $HumanArray = trim($value['Human']);
            $HumanArray = explode(';', $HumanArray);
            $HumanArray = array_filter($HumanArray);
//            dump($HumanArray);
            $HumanArr1 = [];
            foreach ($HumanArray as $i => $j) {
                $Human1 = str_replace("{", "[", $j);
                $Human2 = str_replace("}", "]", $Human1);
                $Human2 = str_replace("\n", " ", $Human2);
                $Human3 = "[" . $Human2 . "]";
                $HumanArr = json_decode($Human3);
                dump($HumanArr);
//                dump($HumanArr);
                $Human['type'] = empty($HumanArr[0]) ? 0 : (float)$HumanArr[0];
                $Human['actionType'] = empty($HumanArr[1]) ? 0 : (float)$HumanArr[1];
                $Human['actionTime'] = empty($HumanArr[2]) ? 0 : (float)$HumanArr[2];
                $Human['startPosX'] = empty($HumanArr[3]) ? 0 : (float)$HumanArr[3];
                $Human['startPosY'] = empty($HumanArr[4]) ? 0 : (float)$HumanArr[4];
                $Human['scaleX'] = empty($HumanArr[5]) ? 0 : (float)$HumanArr[5];
                $Human['moveArray'] = empty($HumanArr[6]) ? [] : $HumanArr[6];
                $Human['talkArray'] = empty($HumanArr[7]) ? [] : $HumanArr[7];
                $HumanArr1[] = $Human;
            }
            $value['Human'] = $HumanArr1;

//
//            $EnemyArray = trim($value['Enemy']);
//            $EnemyArray = explode(';', $EnemyArray);
//            $EnemyArray = array_filter($EnemyArray);
//            $EnemyArr1 = [];
//            foreach ($EnemyArray as $i => $j) {
//                $Enemy1 = str_replace("{", "[", $j);
//                $Enemy2 = str_replace("}", "]", $Enemy1);
//                $Enemy3 = "[" . $Enemy2 . "]";
//                $EnemyArr = json_decode($Enemy3);
//
//                $Enemy['type'] = empty($EnemyArr[0]) ? 0 : (float)$EnemyArr[0];
//                $Enemy['hp'] = empty($EnemyArr[1]) ? 0 : (float)$EnemyArr[1];
//                $Enemy['actionType'] = empty($EnemyArr[2]) ? 0 : (float)$EnemyArr[2];
//                $Enemy['actionTime'] = empty($EnemyArr[3]) ? 0 : (float)$EnemyArr[3];
//                $Enemy['startPosX'] = empty($EnemyArr[4]) ? 0 : (float)$EnemyArr[4];
//                $Enemy['startPosY'] = empty($EnemyArr[5]) ? 0 : (float)$EnemyArr[5];
//                $Enemy['scaleX'] = empty($EnemyArr[6]) ? 0 : (float)$EnemyArr[6];
//                $Enemy['moveArray'] = empty($EnemyArr[7]) ? [] : $EnemyArr[7];
//                $EnemyArr1[] = $Enemy;
//            }
//            $value['Enemy'] = $EnemyArr1;
//
//            $OtherArray = trim($value['Other']);
//            $OtherArray = explode(';', $OtherArray);
//            $OtherArray = array_filter($OtherArray);
//            $OtherArr1 = [];
//            foreach ($OtherArray as $i => $j) {
//                $Other1 = str_replace("{", "[", $j);
//                $Other2 = str_replace("}", "]", $Other1);
//                $Other3 = "[" . $Other2 . "]";
//                $OtherArr = json_decode($Other3);
//
//                $Other['type'] = empty($OtherArr[0]) ? 0 : (float)$OtherArr[0];
//                $Other['hp'] = empty($OtherArr[1]) ? 0 : (float)$OtherArr[1];
//                $Other['actionType'] = empty($OtherArr[2]) ? 0 : (float)$OtherArr[2];
//                $Other['actionTime'] = empty($OtherArr[3]) ? 0 : (float)$OtherArr[3];
//                $Other['startPosX'] = empty($OtherArr[4]) ? 0 : (float)$OtherArr[4];
//                $Other['startPosY'] = empty($OtherArr[5]) ? 0 : (float)$OtherArr[5];
//                $Other['level'] = empty($OtherArr[7]) ? 0 : (float)$OtherArr[7];
//                $Other['moveArray'] = empty($OtherArr[8]) ? [] : $OtherArr[8];
//                $OtherArr1[] = $Other;
//            }
//            $value['Other'] = $OtherArr1;
        }
        $data['passInfo'] = $snipePassInfo;


//        $snipeGunInfo = Db::table('QQ_snipe_config_gunInfo')->field('rifleNo,sniperNo')->select();
//        foreach ($snipeGunInfo as &$val) {
//            $rifleNoArray = explode(';', $val['rifleNo']);
//            $rifleNoArray = array_filter($rifleNoArray);
//            $rifleNo = [];
//            foreach ($rifleNoArray as $i => $j) {
//                $rifleNo1 = str_replace("{", "[", $j);
//                $rifleNo2 = str_replace("}", "]", $rifleNo1);
//                $rifleNoArr = json_decode($rifleNo2);
//                $rifleNo[]['rifleInfo'] = $rifleNoArr;
//            }
//            $val['rifleNo'] = $rifleNo;
//
//
//            $sniperNoArray = explode(';', $val['sniperNo']);
//            $sniperNoArray = array_filter($sniperNoArray);
//            $sniperNoArr = [];
//            foreach ($sniperNoArray as $i => $j) {
//                $sniperNo1 = str_replace("{", "[", $j);
//                $sniperNo2 = str_replace("}", "]", $sniperNo1);
//                $sniperNoArr[]['sniperInfo'] = json_decode($sniperNo2);
//            }
//            $val['sniperNo'] = $sniperNoArr;
//        }
//        $data['gunInfo'] = $snipeGunInfo;
//        return jsonResult('succ', 200, $data);
    }

    public function test()
    {
        # 技能配置
        $skillInfo = Db::table('QQ_snipe_config_skill')->field('skill_name,skill_text,skill_level_name,skill_type,skill_number')->select();
        $data['skillInfo'] = $this->dataGroup($skillInfo, 'skill_type');
        return jsonResult('', 200, $data);
    }

}