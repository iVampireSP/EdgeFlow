<?php

/**
 * This file is part of workerman.
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the MIT-LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @author walkor<walkor@workerman.net>
 * @copyright walkor<walkor@workerman.net>
 * @link http://www.workerman.net/
 * @license http://www.opensource.org/licenses/mit-license.php MIT License
 */

/**
 * 用于检测业务代码死循环或者长时间阻塞等问题
 * 如果发现业务卡死，可以将下面declare打开（去掉//注释），并执行php start.php reload
 * 然后观察一段时间workerman.log看是否有process_timeout异常
 */
//declare(ticks=1);

// namespace Flow;

use Workerman\Lib\Timer;
use \GatewayWorker\Lib\Gateway;
use Applications\Models\Player;
use Applications\Models\Server;

/**
 * 主逻辑
 * 主要是处理 onConnect onMessage onClose 三个方法
 * onConnect 和 onClose 如果不需要可以不用实现并删除
 */
class Events
{
    public static $db = null;
    public static $debug = false;
    public static $redis = null;
    public static $health = 'ng';
    public static $online = 0;
    public static $online_players = 0;

    public static function onConnect($client_id)
    {
        echo '新的连接 ' . $client_id . PHP_EOL;
        $auth_timer_id = Timer::add(30, function ($client_id) {
            self::send('login_failed', 'failed');
            Gateway::closeClient($client_id);
            echo '超时断开 ' . $client_id . PHP_EOL;
        }, array($client_id), false);
        Gateway::updateSession($client_id, array('auth_timer_id' => $auth_timer_id, 'login' => false));

        // 向当前client_id发送数据 
        // Gateway::sendToClient($client_id, "Hello $client_id\r\n");
        // 向所有人发送
        // Gateway::sendToAll("$client_id login\r\n");
    }

    /**
     * 当客户端发来消息时触发
     * @param int $client_id 连接id
     * @param mixed $msg 具体消息
     */
    public static function onMessage($client_id, $msg)
    {
        $msg = json_decode($msg);
        // echo 'Event: ' . $msg->event . PHP_EOL;


        // if ($msg->event !== 'login' && $_SESSION['login'] !== true) {
        //     // echo $msg->event . PHP_EOL;
        //     // echo $_SESSION['auth_timer_id'] . PHP_EOL;
        //     // Gateway::closeCurrentClient();
        //     // return false;
        // }


        $process = new Process($client_id);

        $event = $msg->event;
        // if method exists
        if (method_exists($process, $event)) {
            $process->msg = $msg;
            $return = $process->$event($msg->data);
        } else {
            echo '未知操作:' . $client_id . '->' . $msg->event . PHP_EOL;
        }

        $data = [
            'clientEvent' => $msg->event,
            'data' => $return ?? 'failed',
        ];

        $data = json_encode($data);

        Gateway::sendToCurrentClient($data);

        if (self::$debug) {
            $process->log($data);
        }

        unset($process);

        //     case 'player_logout':
        //         $player = Player::xuid($msg->data->xuid)->first();
        //         if ($msg->data->nbt !== $player->nbt) {
        //             echo "NBT 已更改 " . $player->name . PHP_EOL;
        //             $player->nbt = $msg->data->nbt;
        //             $player->save();
        //         }

        //         echo '玩家退出' . $msg->data->name . '于服务器 ' . $msg->data->config->name . PHP_EOL;
        //         break;



        //     case 'validate_user':
        //         if (isset($msg->data->name)) {
        //             echo '玩家加入:' . $msg->data->name . PHP_EOL;
        //         } else {
        //             echo '有位玩家加入了服务器但是无法获取其名称。' . PHP_EOL;
        //         }
        //         break;

        //     default:
        //         echo '未知操作:' . $client_id . '->' . $msg->event . PHP_EOL;
        //         break;
        // }

        // 向所有人发送 
        // Gateway::sendToAll("$client_id said $message\r\n");


    }

    /**
     * 当用户断开连接时触发
     * @param int $client_id 连接id
     */
    public static function onClose($client_id)
    {
        $server = Server::where('client_id', $client_id)->first();

        self::$online--;
        if (is_null($server)) {
            echo '有一台服务器已经关闭。' . $client_id . PHP_EOL;
        } else {
            $server->status = 'offline';
            $server->client_id = null;
            $server->save();

            echo '服务器离线: ' . $server->name . PHP_EOL;
        }

        echo '在线服务器数量: ' . self::$online . PHP_EOL;


        // 向所有人发送 
        //    GateWay::sendToAll("$client_id logout\r\n");
    }

    public static function sendTo($client_id, $event, $msg)
    {
        $data = [
            'event' => $event,
            'data' => $msg
        ];
        Gateway::sendToClient($client_id, json_encode($data));
    }

    public static function send($event, $msg)
    {
        $data = [
            'event' => $event,
            'data' => $msg
        ];
        Gateway::sendToCurrentClient(json_encode($data));
    }
}
