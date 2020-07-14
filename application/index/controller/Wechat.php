<?php
/**
 * Created by PhpStorm.
 * User: Zach
 * Date: 2017/9/21
 * Time: 11:02
 */

namespace app\index\controller;

use think\Db;

class wechat extends Conmmon
{
    protected $fromUsername = '';
    protected $toUsername = '';

    public function _initialize()
    {
        if (isset($_GET['echostr'])) {
            $this->valid();
        } else {
            $this->response();
        }
    }

    //接入验证
    private function valid()
    {
        $get = input('get.');
        $signature = $get['signature'];
        $timestamp = $get['timestamp'];
        $nonce = $get['nonce'];
        $echoStr = $get['echostr'];
        $wx_token = config('wx.GHOST_TOKEN');
        $tmpArr = array($wx_token, $timestamp, $nonce);
        sort($tmpArr, SORT_STRING);
        $tmpStr = implode($tmpArr);
        $tmpStr = sha1($tmpStr);

        if ($tmpStr == $signature) {
            ob_clean();
            echo $echoStr;
            exit;
        }
        return false;
    }

    //接受消息

    /**
     *  弹个鬼 - 客服消息
     */
    public function response()
    {
        $postStr = file_get_contents("php://input", 'r');
//        logger($postStr);
        if (!empty($postStr)) {
            libxml_disable_entity_loader(true);
            //2.处理消息类型，并设置回复类型和内容
            $postObj = simplexml_load_string($postStr, 'SimpleXMLElement', LIBXML_NOCDATA);
            //判断该数据包是否是订阅de事件推送
//            if (strtolower($postObj->MsgType) == 'event') {
//                //如果是关注 subscribe事件
//                if (strtolower($postObj->Event) == 'subscribe') {
//                    $content = '欢迎关注我的微信公众号！';
//                    $this->receiveEvent($postObj, $content);
//                }
//            }

            if (strtolower($postObj->MsgType) == 'text') {

                $text = trim($postObj->Content);

                $content = $text;
                $this->receiveEvent($postObj, $content);

                exit;
            }
        }
    }

    //接受消息

    private function receiveEvent($object, $content)
    {
        $template = "<xml>
                            <ToUserName><![CDATA[%s]]></ToUserName>
                            <FromUserName><![CDATA[%s]]></FromUserName>
                            <CreateTime>%s</CreateTime>
                            <MsgType><![CDATA[%s]]></MsgType>
                            <Content><![CDATA[%s]]></Content>
                            </xml>";
        $info = sprintf($template, $object->FromUserName, $object->ToUserName, time(), 'text', $content);
        echo $info;
    }

    public function response1()
    {
        $postStr = file_get_contents("php://input");
//        logger($postStr);
        if (!empty($postStr)) {
            libxml_disable_entity_loader(true);
            $postObj = simplexml_load_string($postStr, 'SimpleXMLElement', LIBXML_NOCDATA);
            $postObj = (array)$postObj;
            $this->fromUsername = $postObj['FromUserName']; //发送方帐号（一个OpenID）
//            logger($this->fromUsername);
            $this->toUsername = $postObj['ToUserName'];     //开发者微信号
            $createtime = $postObj['CreateTime'];            //消息创建时间
            if (!empty($postObj['Content'])) {
                $content = trim($postObj['Content']);         //文本消息内容
                logger($content);
            }
            //消息类型
            $msgtype = trim($postObj['MsgType']);

            $textTpl = [];
//            if ($msgtype === 'event') {
            if ($msgtype === 'text') {

                if ($content === '1') {
                    //接受普通消息
                    $media_id = $this->upload();
                    $textTpl = array(
                        "touser" => $this->fromUsername,
                        "msgtype" => "image",
                        "image" => [
                            "media_id" => $media_id,
                        ],
                    );
                }

//                $textTpl = array(
//                    "touser" => $this->fromUsername,
//                    "msgtype" => "link",
//                    "link" => [
//                        "title" => '点我关注 让我提醒你！',
//                        "description" => '关注“群发祝福”公众号，定时提醒节日祝福发送。',
//                        "url" => config('adv.GZH_URL'),
//                        "thumb_url" => 'https://qfzf.zsmgc.com.cn/A/public/app/qfzf.png',
//                    ],
//                );

            }

            $textTpl = json_encode($textTpl, JSON_UNESCAPED_UNICODE);
            $access_token = $this->getAccessTokenApi();
//            logger($access_token);

            $url = "https://api.weixin.qq.com/cgi-bin/message/custom/send?access_token=" . $access_token;
            $aaa = $this->https_request($url, $textTpl);
            die;
        }
    }

    // 关注自动回复

    public function response2()
    {
        $postStr = file_get_contents("php://input");
//        logger($postStr);
        if (!empty($postStr)) {
            libxml_disable_entity_loader(true);
            $postObj = simplexml_load_string($postStr, 'SimpleXMLElement', LIBXML_NOCDATA);
            $postObj = (array)$postObj;
            $this->fromUsername = $postObj['FromUserName']; //发送方帐号（一个OpenID）
//            logger($this->fromUsername);
            $this->toUsername = $postObj['ToUserName'];     //开发者微信号
            $createtime = $postObj['CreateTime'];            //消息创建时间
            if (!empty($postObj['Content'])) {
                $content = trim($postObj['Content']);         //文本消息内容
//                logger($content);
            }
            //消息类型
            $msgtype = trim($postObj['MsgType']); // event 自动回复 text 文本

            $textTpl = [];
            if ($msgtype === 'text') {

                if ($content === '1') {
                    //接受普通消息
                    $media_id = $this->upload($content);
                    $textTpl = array(
                        "touser" => $this->fromUsername,
                        "msgtype" => "image",
                        "image" => [
                            "media_id" => $media_id,
                        ],
                    );
                }

                if ($content === '2') {
                    //接受普通消息
                    $media_id = $this->upload($content);
                    $textTpl = array(
                        "touser" => $this->fromUsername,
                        "msgtype" => "image",
                        "image" => [
                            "media_id" => $media_id,
                        ],
                    );

                    $user_id = db('user')->where(['open_id' => $this->fromUsername])->value('user_id');
                    $adddata = [
                        'user_id' => $user_id,
                        'add_time' => date('Y-m-d H:i:s'),
                    ];
                    db('hf')->insertGetId($adddata);
                }

//                $textTpl = array(
//                    "touser" => $this->fromUsername,
//                    "msgtype" => "link",
//                    "link" => [
//                        "title" => '点我关注 让我提醒你！',
//                        "description" => '关注“群发祝福”公众号，定时提醒节日祝福发送。',
//                        "url" => config('adv.GZH_URL'),
//                        "thumb_url" => 'https://qfzf.zsmgc.com.cn/A/public/app/qfzf.png',
//                    ],
//                );

            }

            $textTpl = json_encode($textTpl, JSON_UNESCAPED_UNICODE);
            $access_token = $this->getAccessTokenApi();
//            logger($access_token);

            $url = "https://api.weixin.qq.com/cgi-bin/message/custom/send?access_token=" . $access_token;
            $aaa = $this->https_request($url, $textTpl);
            die;
        }
    }


}