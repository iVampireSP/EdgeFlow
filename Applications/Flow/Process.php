<?php

use Workerman\Lib\Timer;
use \GatewayWorker\Lib\Gateway;
use Applications\Models\Player;
use Applications\Models\Server;

class Process
{
    public $session;
    public $client_id;
    public $msg;

    // 构造
    public function __construct($client_id)
    {
        $this->client_id = $client_id;
        $this->session = Gateway::getSession($this->client_id);
    }

    public function updateSession($key, $value)
    {
        Gateway::updateSession($this->client_id, [$key => $value]);
        return true;
    }


    public function login($data)
    {
        $this->log('有一台服务器正在请求连接到 Flow 网络...');

        $server = Server::current($data)->first();
        // $server = self::$db->table('servers')->where('token', $data)->first();

        if ($server !== null) {
            $this->log('登录成功 ' . $server->name);
            $server->status = 'active';
            $server->client_id = $this->client_id;
            $server->save();


            Timer::del($this->session['auth_timer_id']);
            $this->updateSession('login', true);
            $this->updateSession('token', $data);
            $this->updateSession('server', $server);

            Events::$online++;
            $this->log('当前在线服务器数量: ' . Events::$online);


            return ['client_id' => $this->client_id, 'server' => $server];
        } else {
            $this->log('登录失败');
        }

        return 'failed';
    }

    public function server_data($data)
    {
        $data->group = $data->group ?? 'public';
        Server::current($this->session['token'])->update([
            'name' => $data->name,
            'motd' => $data->motd,
            'version' => $data->version,
            'group' => $data->group,
        ]);

        if ($data->group != 'public') {
            $this->log('服务器: ' . $data->name . ' 已加入组: ' . $data->group);
        }

        Gateway::joinGroup($this->client_id, $data->group);
        $this->updateSession('group', $data->group);
        // Gateway::updateSession($this->client_id, ['group' => $data->group]);



        return true;
    }

    public function update_user($data)
    {

        foreach ($data as $user) {
            $player = Player::xuid($user->xuid)->first();
            if (!is_null($player)) {
                if ($player->name !== $user->name) {
                    $player->name = $user->name;
                } else {
                    if (isset($user->nbt)) {
                        $player->nbt = $user->nbt;
                    }

                    $player->save();
                }
            } else {
                $player = new Player();
                $player->name = $user->name;
                $player->xuid = $user->xuid;

                $player->save();
            }
        }

        return true;
    }

    public function next()
    {
        $this->flow_event('player_chooseing', $this->msg->name);
        return $this->getServers(1);
    }

    public function nextAll()
    {
        return $this->getServers(1);
    }

    public function player_join($name)
    {
        Events::$online_players++;
        $this->log('玩家: ' . $name . ' 加入了游戏。');
        $this->log('在线玩家数量: ' . Events::$online_players);
    }

    public function player_data($xuid)
    {
        $player = Player::xuid($xuid)->first();
        if ($player !== null) {
            return $player;
        } else {
            return false;
        }
    }

    public function broadcast_chat($data)
    {
        $this->log('[' . $this->session['group'] . ']广播聊天: ' . $data->name . "[{$data->config->name}]说:" . $data->msg);
        Gateway::sendToGroup($this->session['group'], json_encode([
            'event' => 'chat',
            'data' => [
                'name' => $data->name,
                'msg' => $data->msg,
                'server_name' => $data->config->name,
                'client_id' => $this->client_id
            ],
        ]));

        return true;
    }

    public function broadcast_event($data)
    {
        $this->log('[' . $this->session['group'] . ']广播事件: ' . "[{$data->config->name}]:" . $data->msg);
        Gateway::sendToGroup($this->session['group'], json_encode([
            'event' => 'event',
            'data' => [
                'msg' => $data->msg,
                'server_name' => $data->config->name,
                'client_id' => $this->client_id
            ],
        ]));

        return true;
    }

    public function flow_event($name, $msg)
    {
        $this->log('Flow 事件: ' . "{$name}:" . $msg);
        Gateway::sendToAll(json_encode([
            'event' => $name,
            'data' => [
                'msg' => $msg,
                'server_name' => 'Flow',
                'client_id' => 0
            ],
        ]));
    }

    public function get_random_servers()
    {
        return $this->getServers(5);
    }

    public function player_logout($player)
    {
        $this->log('玩家: ' . $player->name . ' 离开了服务器');
        Events::$online_players--;
        $this->log('在线玩家数量玩家: ' . Events::$online_players);

        return true;
    }

    public function getServer($id)
    {
        $server = Server::select(['name', 'status', 'ip_port', 'alert'])->find($id);
        if ($server !== null) {
            $ip_port = explode(':', $server->ip_port);
            $server->can = 1;
            $server->ip = $ip_port[0];
            $server->port = $ip_port[1];
            return $server;
        } else {
            return false;
        }
    }

    public function getServers($num = 1)
    {
        $server_query = Server::where('token', '!=', $this->session['token']);
        $this_server = $server_query->first();

        $this->log($this_server->name . ':正在搜索 ' . $this_server->group . ' 组的服务器');

        $servers = $server_query
            ->where('status', 'active')
            ->where('alert', null)
            ->where('ip_port', '!=', null)
            ->where('version', $this->session['server']->version)
            ->where('group', $this_server->group)
            ->select(['id', 'name', 'ip_port', 'motd', 'version'])
            ->inRandomOrder()
            ->take($num)
            ->get();

        if (!$servers->count()) {
            return false;
        }

        $output = [];

        foreach ($servers as $server) {
            $ip_port = explode(':', $server->ip_port);
            $output[] = [
                'id' => $server->id,
                'name' => $server->name,
                'ip' => $ip_port[0],
                'port' => $ip_port[1],
                'motd' => $server->motd,
                'version' => $server->version,
            ];
        }

        return $output;
    }

    public function money_add($data)
    {
        $player = Player::xuid($data->xuid)->first();
        if ($player->money == 0) {
            $player->money = $data->origin;
        } else {
            $player->money += $data->value;
        }

        $player->save();

        return [
            'status' => true,
            'value' => $player->money,
        ];
    }

    public function getOnline()
    {
        return ['servers' => Events::$online, 'players' => Events::$online_players];
    }

    public function money_reduce($data)
    {
        $player = Player::xuid($data->xuid)->first();
        if ($player->money == 0) {
            $player->money = $data->origin;
        } else {
            if ($player->money - $data->value < 0) {
                $player->money += $data->value;
            } else {
                $player->money -= $data->value;
            }
        }

        $player->save();

        return [
            'status' => true,
            'value' => $player->money,
        ];

        // $this->log($data);
    }

    public function log($str)
    {
        echo $str . PHP_EOL;
    }
}
