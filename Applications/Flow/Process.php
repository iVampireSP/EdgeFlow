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
        $this->log('正在验证登录...');

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

            return ['client_id' => $this->client_id, 'server' => $server];
        } else {
            $this->log('登录失败');
        }

        return 'failed';
    }

    public function server_data($data)
    {
        Server::current($this->session['token'])->update([
            'name' => $data->name,
            'motd' => $data->motd,
            'version' => $data->version,
        ]);

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
        return $this->getServers(1);
    }

    public function nextAll()
    {
        return $this->getServers(1);
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
        $this->log('广播聊天: ' . $data->name . "[{$data->config->name}]说:" . $data->msg);
        Gateway::sendToAll(json_encode([
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
        $this->log('广播事件: ' . "[{$data->config->name}]:" . $data->msg);
        Gateway::sendToAll(json_encode([
            'event' => 'event',
            'data' => [
                'msg' => $data->msg,
                'server_name' => $data->config->name,
                'client_id' => $this->client_id
            ],
        ]));

        return true;
    }

    public function get_random_servers()
    {
        return $this->getServers(5);
    }

    public function player_logout($player)
    {
        // var_dump($player);
        // $this->log('玩家退出' . $player->name . '于服务器 ' . $player->config->name);
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
        $servers = Server::where('token', '!=', $this->session['token'])
            ->where('status', 'active')
            ->where('alert', null)
            ->where('ip_port', '!=', null)
            ->where('version', $this->session['server']->version)
            ->select(['id', 'name', 'ip_port', 'motd', 'version'])
            ->inRandomOrder()
            ->take($num)
            ->get();

        if (count($servers) === 0) {
            return false;
        }


        // $this->log('num:' . $num);

        foreach ($servers as $server) {
            $ip_port = explode(':', $server->ip_port);
            $server->ip = $ip_port[0];
            $server->port = $ip_port[1];
        }

        return $servers;
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

    public function money_reduce($data)
    {
        $player = Player::xuid($data->xuid)->first();
        if ($player->money == 0) {
            $player->money = $data->origin;
        } else {
            $player->money -= $data->value;
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
