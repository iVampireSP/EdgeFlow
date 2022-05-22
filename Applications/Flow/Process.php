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

        $this->log($data);

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

            return $this->client_id;
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
        return $this->getServer();
    }

    public function nextAll()
    {
        return $this->getServer();
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

    public function getServer()
    {
        $server = Server::where('token', '!=', $this->session['token'])
            ->where('status', 'active')
            ->where('ip_port', '!=', null)
            ->where('version', $this->session['server']->version)
            ->select(['id', 'name', 'ip_port', 'motd', 'version'])
            ->first();

        if (is_null($server)) {
            return false;
        }

        $ip_port = explode(':', $server->ip_port);
        $server->ip = $ip_port[0];
        $server->port = $ip_port[1];

        return $server;
    }

    public function log($str)
    {
        echo $str . PHP_EOL;
    }
}
