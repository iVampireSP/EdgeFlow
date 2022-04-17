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
    public static $redis = null;
    /**
     * 当客户端连接时触发
     * 如果业务不需此回调可以删除onConnect
     * 
     * @param int $client_id 连接id
     */
    public static function onConnect($client_id)
    {
        $auth_timer_id = Timer::add(30, function ($client_id) {
            Gateway::closeClient($client_id);
            echo '超时断开 ' . $client_id;
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
        $_SESSION = Gateway::getSession($client_id);

        $msg = json_decode($msg);

        // echo 'Event: ' . $msg->event . PHP_EOL;


        if ($msg->event !== 'login' && $_SESSION['login'] !== true) {
            // echo $msg->event . PHP_EOL;
            // echo $_SESSION['auth_timer_id'] . PHP_EOL;
            Gateway::closeCurrentClient();
        }

        switch ($msg->event) {
            case 'login':

                if ($msg->data == 'edge_flow') {
                    Gateway::closeCurrentClient();
                }

                $server = Server::current($msg->data)->first();
                // $server = self::$db->table('servers')->where('token', $msg->data)->first();

                if ($server !== null) {
                    echo '登录成功 ' . $server->name . PHP_EOL;
                    $server->status = 'active';
                    $server->client_id = $client_id;
                    $server->save();
                    self::send('login_success', $client_id);
                } else {
                    self::send('login_failed', $client_id);
                }

                // self::send('login_failed', $client_id);
                // 记录session，表明认证成功
                Timer::del($_SESSION['auth_timer_id']);
                $_SESSION['login'] = true;
                $_SESSION['token'] = $msg->data;
                $_SESSION['server'] = $server;
                break;

                // 设置服务器信息
            case 'server_data':
                $server = Server::current($_SESSION['token'])->update([
                    'name' => $msg->data->name,
                    'motd' => $msg->data->motd,
                    'version' => $msg->data->version,
                ]);

                break;


            case 'update_user':

                foreach ($msg->data as $user) {
                    $player = Player::xuid($user->xuid)->first();
                    if ($player !== null) {
                        if ($player->name != $user->name) {
                            $player->name = $user->name;
                            $player->save();
                        }
                    } else {
                        $player = new Player();
                        $player->name = $user->name;
                        $player->xuid = $user->xuid;
                        $player->save();
                    }
                }

                // echo '修改用户:' . $client_id . '->' . $msg->event . PHP_EOL;

                break;

            case 'get_player':
                $player = Player::xuid($msg->data)->first();

                if ($player !== null) {
                    self::send('player_data', $player);
                } else {
                    self::send('tell', ['xuid' => $msg->data->xuid, 'code' => 404]);
                }
                break;



            case 'player_logout':
                $player = Player::xuid($msg->data->xuid)->first();
                if ($msg->data->nbt !== $player->nbt) {
                    echo "NBT 已更改 " . $player->name . PHP_EOL;
                    $player->nbt = $msg->data->nbt;
                    $player->save();
                }

                echo '玩家退出' . $msg->data->name . '于服务器 ' . $msg->data->config->name . PHP_EOL;
                break;

            case 'broadcast_chat':
                Gateway::sendToAll(json_encode([
                    'event' => 'chat',
                    'data' => [
                        'name' => $msg->data->name,
                        'msg' => $msg->data->msg,
                        'server_name' => $msg->data->config->name,
                        'client_id' => $client_id
                    ],
                ]));
                break;

            case 'next':
                $server = Server::where('token', '!=', $_SESSION['token'])
                    ->where('status', 'active')
                    ->where('version', $_SESSION['server']->version)
                    ->select(['id', 'name', 'ip_port', 'motd', 'version'])
                    ->first();

                $ip_port = explode(':', $server->ip_port);
                $server->ip = $ip_port[0];
                $server->port = $ip_port[1];

                if ($msg->data->all ?? false) {
                    self::send('transfer_all', $server);
                } else {
                    $server->xuid = $msg->data->xuid ?? null;
                    self::send('transfer', $server);
                }


                break;

            default:
                echo '未知操作:' . $client_id . '->' . $msg->event . PHP_EOL;
                break;
        }

        // 向所有人发送 
        // Gateway::sendToAll("$client_id said $message\r\n");


    }

    /**
     * 当用户断开连接时触发
     * @param int $client_id 连接id
     */
    public static function onClose($client_id)
    {
        $server = Server::where('client_id', $client_id);

        $server->update(['status' => 'offline', 'client_id' => null]);

        echo '服务器离线: ' . $server->first()->name . PHP_EOL;
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
