<?php
/**
 * Created by PhpStorm.
 * User: 光与旅人
 * Date: 2018/3/13
 * Time: 8:32
 */

namespace app\index\controller;

use think\Controller;
use think\Db;

class Conmmon extends Controller
{
   protected $user_id;
   protected $open_id;
   protected $date;

   public function _initialize()
   {
      date_default_timezone_set('Asia/Shanghai');
      $this->date = date('Y-m-d');
      if (request()->isPost()) {
         $mstr = input('post.mstr');
         if ($mstr) {
            $sginData = explode(',', unlock_url($mstr));
            $this->open_id = $sginData[0];
            $this->user_id = $sginData[1];
         }
      }
   }


   //获取微信配置
   public function getMinerConfig()
   {
      return array(
         'APPID' => config('wx.APPID'),
         'APPSECRET' => config('wx.APPSECRET'),
         'ACCESS_TOKEN' => config('token.ACCESS_TOKEN'),
         'ACCESS_TIME' => config('token.ACCESS_TIME')
      );
   }

   /**
    *  获取open_id和session_key
    * */
   public function getOpenid($appid, $appsecret, $code)
   {
      $url = "https://api.weixin.qq.com/sns/jscode2session?appid={$appid}&secret={$appsecret}&js_code={$code}&grant_type=authorization_code";
      $weixin = $this->curl_get($url);
      $data = json_decode($weixin, true);
      return $data;
   }

   /**
    *  获取QQ小程序 open_id和session_key
    * */
   public function getQQOpenid($appid, $appsecret, $code)
   {
      $url = "https://api.q.qq.com/sns/jscode2session?appid={$appid}&secret={$appsecret}&js_code={$code}&grant_type=authorization_code";
      $weixin = $this->curl_get($url);
      $data = json_decode($weixin, true);
      return $data;
   }

   /**
    *  获取QQ小程序 open_id和session_key
    * */
   public function getTTOpenid($appid, $appsecret, $code)
   {
      $url = "https://developer.toutiao.com/api/apps/jscode2session?appid={$appid}&secret={$appsecret}&code={$code}";
      $weixin = $this->curl_get($url);
      $data = json_decode($weixin, true);
      return $data;
   }

   /**
    *  获取VIVO小程序 open_id和session_key
    * */
   public function getVIVOOpenid($pkgName, $token, $timestamp, $nonce, $appKey, $appSecret)
   {
      $str = "appKey={$appKey}&appSecret={$appSecret}&nonce={$nonce}&pkgName={$pkgName}&timestamp={$timestamp}&token={$token}";
      $signature = hash('sha256', $str);
      $url = "https://quickgame.vivo.com.cn/api/quickgame/cp/account/userInfo?pkgName={$pkgName}&token={$token}&timestamp={$timestamp}&nonce={$nonce}&signature={$signature}";
      $weixin = $this->curl_get($url);
      $data = json_decode($weixin, true);
      return $data['data'];
   }

   /**
    *  获取OPPO小程序 open_id和session_key
    * */
   public function getOPPOOpenid($appKey, $appSecret, $pkgName, $timestamp, $token)
   {
      $str = "appKey={$appKey}&appSecret={$appSecret}&pkgName={$pkgName}&timeStamp={$timestamp}&token={$token}";
      $signature = strtoupper(md5($str));
      $url = "https://play.open.oppomobile.com/instant-game-open/userInfo?pkgName={$pkgName}&timeStamp={$timestamp}&token={$token}&sign={$signature}&version=1.0.1";
      $weixin = $this->curl_get($url);
      $data = json_decode($weixin, true);
      return $data['userInfo'];
   }

   /**
    *  获取open_id和session_key（弹个鬼）
    * */
   public function get_openid($code)
   {
      $config = db('config')->find();
      $appid = $config['appid'];
      $appsecret = $config['appsecret'];
      $url = "https://api.weixin.qq.com/sns/jscode2session?appid={$appid}&secret={$appsecret}&js_code={$code}&grant_type=authorization_code";
      $weixin = $this->curl_get($url);
      $data = json_decode($weixin, true);
      return $data;
   }

   public function curl_get($url)
   {
      $curl = curl_init();
      curl_setopt($curl, CURLOPT_URL, $url);
      curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
      curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
      $data = curl_exec($curl);
      $err = curl_error($curl);
      curl_close($curl);
      return $data;
   }


   /**
    *  获取open_id和session_key（弹个鬼）
    * */
   public function get_hunter_openid($code)
   {
      $config = Db::table('hunter_config')->find();
      $appid = $config['appid'];
      $appsecret = $config['appsecret'];
      $url = "https://api.weixin.qq.com/sns/jscode2session?appid={$appid}&secret={$appsecret}&js_code={$code}&grant_type=authorization_code";
      $weixin = $this->curl_get($url);
      $data = json_decode($weixin, true);
      return $data;
   }

   /**
    *  获取open_id和session_key（猫狗大战）
    * */
   public function get_flick_openid($code)
   {
      $config = Db::table('flick_config')->find();
      $appid = $config['appid'];
      $appsecret = $config['appsecret'];
      $url = "https://api.weixin.qq.com/sns/jscode2session?appid={$appid}&secret={$appsecret}&js_code={$code}&grant_type=authorization_code";
      $weixin = $this->curl_get($url);
      $data = json_decode($weixin, true);
      return $data;
   }


   /**
    *  获取open_id和session_key（跑酷）
    * */
   public function get_parkour_openid($code)
   {
      $config = Db::table('parkour_config')->find();
      $appid = $config['appid'];
      $appsecret = $config['appsecret'];
      $url = "https://api.weixin.qq.com/sns/jscode2session?appid={$appid}&secret={$appsecret}&js_code={$code}&grant_type=authorization_code";
      $weixin = $this->curl_get($url);
      $data = json_decode($weixin, true);
      return $data;
   }

   /**
    *  获取open_id和session_key（绝地飞车）
    * */
   public function get_car_openid($code)
   {
      $appid = 'wx90002ed0827602ff';
      $appsecret = '872b34a85d1142e6732e9cbf7563e5a9';
      $url = "https://api.weixin.qq.com/sns/jscode2session?appid={$appid}&secret={$appsecret}&js_code={$code}&grant_type=authorization_code";
      $weixin = $this->curl_get($url);
      $data = json_decode($weixin, true);
      return $data;
   }

   /**
    *  获取open_id和session_key（摩托竞速赛）
    * */
   public function get_motorcycles_openid($code)
   {
      $appid = 'wx24da6ea79b30a45b';
      $appsecret = '18f49b3b2a3c7a3ca8ea983b1c0f6ab0';
      $url = "https://api.weixin.qq.com/sns/jscode2session?appid={$appid}&secret={$appsecret}&js_code={$code}&grant_type=authorization_code";
      $weixin = $this->curl_get($url);
      $data = json_decode($weixin, true);
      return $data;
   }

   /**
    *  获取open_id和session_key（荒野摩托）
    * */
   public function get_singleMotor_openid($code)
   {
      $appid = 'wx9900ea2a555d9c07';
      $appsecret = 'c2ac34621216c2e94288605c597ee0ac';
      $url = "https://api.weixin.qq.com/sns/jscode2session?appid={$appid}&secret={$appsecret}&js_code={$code}&grant_type=authorization_code";
      $weixin = $this->curl_get($url);
      $data = json_decode($weixin, true);
      return $data;
   }

   /**
    *  获取open_id和session_key（弹个鬼）
    * */
   public function get_ball_openid($code)
   {
      $config = Db::table('ball_config')->find();
      $appid = $config['appid'];
      $appsecret = $config['appsecret'];
      $url = "https://api.weixin.qq.com/sns/jscode2session?appid={$appid}&secret={$appsecret}&js_code={$code}&grant_type=authorization_code";
      $weixin = $this->curl_get($url);
      $data = json_decode($weixin, true);
      return $data;
   }

   /**
    *  获取open_id和session_key（疯狂果园）
    * */
   public function getTreeOpenid($code)
   {
      $config = Db::table('tree_config')->find();
      $appid = $config['appid'];
      $appsecret = $config['appsecret'];
      $url = "https://api.weixin.qq.com/sns/jscode2session?appid={$appid}&secret={$appsecret}&js_code={$code}&grant_type=authorization_code";
      $weixin = $this->curl_get($url);
      $data = json_decode($weixin, true);
//        session('session_key', $data['session_key'], 3600);
//        $data['session_key'] = session('session_key');
      return $data;
   }

   /**
    *  获取open_id和session_key（黄金矿工）
    * */
   public function getMinerOpenid($code)
   {
      $appid = config('miner.APPID');
      $appsecret = config('miner.APPSECRET');
      $url = "https://api.weixin.qq.com/sns/jscode2session?appid={$appid}&secret={$appsecret}&js_code={$code}&grant_type=authorization_code";
      $weixin = $this->curl_get($url);
      $data = json_decode($weixin, true);
//        session('session_key', $data['session_key'], 3600);
//        $data['session_key'] = session('session_key');
      return $data;
   }

   /**
    *  获取open_id和session_key（超级鲶鱼）
    * */
   public function getFishOpenid($code)
   {
      $appid = config('fish.APPID');
      $appsecret = config('fish.APPSECRET');
      $url = "https://api.weixin.qq.com/sns/jscode2session?appid={$appid}&secret={$appsecret}&js_code={$code}&grant_type=authorization_code";
      $weixin = $this->curl_get($url);
      $data = json_decode($weixin, true);
//        session('session_key', $data['session_key'], 3600);
//        $data['session_key'] = session('session_key');
      return $data;
   }

   /**
    *  获取open_id和session_key（贪吃大王蛇）
    * */
   public function getSnakeOpenid($code)
   {
//        $config = Db::table('snake_config')->field('appid,appsecret')->find();
      $appid = config('snake.APPID');
      $appsecret = config('snake.APPSECRET');
      $url = "https://api.weixin.qq.com/sns/jscode2session?appid={$appid}&secret={$appsecret}&js_code={$code}&grant_type=authorization_code";
      $weixin = $this->curl_get($url);
      $data = json_decode($weixin, true);
      return $data;
   }

   /**
    *  获取open_id和session_key（弹个鬼）
    * */
   public function get_interstellar_openid($code)
   {
      $config = Db::table('Interstellar_config')->find();
      $appid = $config['appid'];
      $appsecret = $config['appsecret'];
      $url = "https://api.weixin.qq.com/sns/jscode2session?appid={$appid}&secret={$appsecret}&js_code={$code}&grant_type=authorization_code";
      $weixin = $this->curl_get($url);
      $data = json_decode($weixin, true);
      return $data;
   }

   public function checkmsg($content)
   {
      $access = json_decode($this->get_access_token(), true);
      $access_token = $access['access_token'];
      $url = "https://api.weixin.qq.com/wxa/msg_sec_check?access_token={$access_token}";

      return $data = $this->https_request($url, $content);
   }

   public function get_access_token()
   {
      $wx_info = $this->getWxConfig();
      $appid = $wx_info['APPID'];
      $appsecret = $wx_info['APPSECRET'];
      $url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid={$appid}&secret={$appsecret}";
      $data = $this->curl_get($url);
      $data = json_decode($data, true);
      return $data;
   }

   public function getWxConfig()
   {
      return array(
         'APPID' => config('wx.APPID'),
         'APPSECRET' => config('wx.APPSECRET'),
         'ACCESS_TOKEN' => config('token.ACCESS_TOKEN'),
         'ACCESS_TIME' => config('token.ACCESS_TIME')
      );
   }

   /**
    * $url 接口API
    * $data json数据
    */
   protected function https_request($url, $data = null)
   {
      $curl = curl_init();
      curl_setopt($curl, CURLOPT_URL, $url);
      curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
      curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE);
      if (!empty($data)) {
         curl_setopt($curl, CURLOPT_POST, 1);
         curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
      }
      curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
      $output = curl_exec($curl);
      curl_close($curl);
      return $output;
   }

   /**
    *  新增一个临时素材
    */
   public function upload1()
   {
      //获取access_token
      $access_token = $this->getAccessTokenApi();
//        logger($access_token);

      //url 里面的需要2个参数一个 access_token 一个是 type（值可为image、voice、video和缩略图thumb）
      $url = "https://api.weixin.qq.com/cgi-bin/media/upload?access_token=" . $access_token . "&type=image";
      if (class_exists('\CURLFile')) {
         $josn = array('media' => new \CURLFile(realpath("../public/app/gzh.jpg")));
      } else {
         $josn = array('media' => '@' . realpath("../public/app/gzh.jpg"));
      }

      $ret = $this->https_request($url, $josn);
      $row = json_decode($ret);//对JSON格式的字符串进行编码
      return $row->media_id;//得到上传素材后，此素材的唯一标识符media_id
   }

   //过滤敏感词汇

   public function getAccessTokenApi()
   {
      $wx_info = $this->getWxConfig();
      $appid = $wx_info['APPID'];
      $appsecret = $wx_info['APPSECRET'];
      $accesstime = $wx_info['ACCESS_TIME'];


      if (($accesstime + 3600) < time()) {
         $url = 'https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=' . $appid . '&secret=' . $appsecret;
         $output = $this->https_request($url);
         $result = json_decode($output, true);
         $access_token = $result["access_token"];

         $this->setWxConfig(['access_token' => $access_token]);
         return $access_token;

      } else {
         return $wx_info['ACCESS_TOKEN'];
      }
   }

   /**
    * 设置微信config
    * 参数['verify'=>'','access_token'=>'']
    */
   public function setWxConfig($param)
   {
      $wx_info = $this->getWxConfig();
      $access_token = (empty($param['access_token']) ? $wx_info['ACCESS_TOKEN'] : $param['access_token']);

      $config = array(
         'ACCESS_TOKEN' => $access_token,
         'ACCESS_TIME' => time(),
      );

      $path = '../application/extra/token.php';
      $file = include $path;

      $res = array_merge($file, $config);
      $str = '<?php return [';

      foreach ($res as $key => $value) {
         $str .= '\'' . $key . '\'' . '=>' . '\'' . $value . '\'' . ',';
      };
      $str .= ']; ';
      //配置文件更新
      file_put_contents($path, $str);
   }

   /* 新增一个临时素材 */

   public function upload($content)
   {
      //获取access_token
      $access_token = $this->getAccessTokenApi();
//        logger($access_token);

      //url 里面的需要2个参数一个 access_token 一个是 type（值可为image、voice、video和缩略图thumb）
      $url = "https://api.weixin.qq.com/cgi-bin/media/upload?access_token=" . $access_token . "&type=image";
      if (class_exists('\CURLFile')) {
         if ($content === '1') {
            $josn = array('media' => new \CURLFile(realpath("../public/app/gzh.jpg")));
         } elseif ($content === '2') {
            $pic = db('hrcode')->order('hrcode_id desc')->value('hrcode_pic');
            $pic_path = "../public/app/" . $pic;
            logger($pic_path);
            $josn = array('media' => new \CURLFile(realpath($pic_path)));
         }
      } else {
         $josn = array('media' => '@' . realpath("../public/app/gzh.jpg"));
      }

      $ret = $this->https_request($url, $josn);
      $row = json_decode($ret);//对JSON格式的字符串进行编码
      return $row->media_id;//得到上传素材后，此素材的唯一标识符media_id
   }

}