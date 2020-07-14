<?php
/**
 * 智商王者
 * User: SongZhe
 * Date: 2018/4/13
 * Time: 16:19
 */

namespace app\index\controller;

class Hrcode extends Conmmon
{
    //获取access_token
    public function hrcode()
    {
        header('content-type:image/png');
        $access = json_decode($this->get_access_token(), true);
        $access_token = $access['access_token'];
        $url = "https://api.weixin.qq.com/wxa/getwxacodeunlimit?access_token=" . $access_token; //接口B

        $data = [
            'scene' => '123',
            'page' => 'pages/index/index',
            'width' => 200,
        ];
        $data = json_encode($data);
        $res = $this->https_request($url, $data);

//        $dir = '/home/wwwroot/default/zswz/public/images/code/';
        $dir = 'D:/wamp64/www/zswz/public/images/code/';
//        create_dirs($path,0777);
        $file_name = "zswz.png";    //要生成的图片名字
        $newFile = fopen($dir . $file_name, "w"); //打开文件准备写入
        fwrite($newFile, $res); //写入二进制流到文件
        fclose($newFile); //关闭文件
        // file_put_contents($dir.$file_name, $result);
        $erweima = 'https://zswz.zsmgc.com.cn/images/code/' . $file_name;
//
        return jsonResult('succ', 200, $erweima);
    }

    public function get_access_token()
    {
//        $appid = 'wxcb758b3b04efcb73';
        $appid = 'wxe4c9dcb3f7bd1bc7'; // 知识达人赛
//        $appsecret = 'a00b210d28a8ae47d9b84d7cfc146ae7';
        $appsecret = 'a4d5ba492952141e3f89f39a5a61fc1a'; // 知识达人赛

        $url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid={$appid}&secret={$appsecret}";
        return $data = $this->curl_get($url);
    }

    /*
    * $url 接口API
    * $data json数据
    */

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

    //获得二维码

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


}