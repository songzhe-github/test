<?php

return [

    'template' => [
        'view_path' => './template/index/',
        'view_depr' => '/',

    ],

    'view_replace_str' => [
        '__ROOT__' => WEB_URL,
        '__INDEX__' => WEB_URL . '/index.php',
        '__ADMIN__' => WEB_URL . '/public',
        '__HOME__' => WEB_URL . '/template/index/public',
        '__VIEW__' => WEB_URL . '/template/index',
    ],


];