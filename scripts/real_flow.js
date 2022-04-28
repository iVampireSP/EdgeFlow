let config = {
    key: 'Your key here',
    name: 'Server name',
    motd: 'Server Motd',
    ip_port: '123.456.789.1:19132',
    receive_chat: true
}

/* 不要编辑以下代码! Do not modify anything below this line! */


if (!File.exists('plugins/Flow')) {
    File.createDir('plugins/Flow')
}

if (File.exists('plugins/Flow/config.json')) {
    config = JSON.parse(File.readFrom('plugins/Flow/config.json'))
} else {
    File.writeTo('plugins/Flow/config.json', JSON.stringify(config))
    log('请修改 plugins/Flow/config.json，然后执行ll reload Flow.js')
}

if (config.key == 'Your key here') {
    log('请修改 plugins/Flow/config.json。')
}


var wsc = new WSClient()
var connected = false
var client_id = null

function connect() {
    let connect_name = "Edge.st Flow"

    if (config.key == 'Your key here' || config.key === null) {
        connect_name = 'Edge.st Public Flow'
    }
    log('正在连接到 ' + connect_name)
    
    if (wsc.connect('ws://1.117.63.82:3512')) {

        // 发送 Login
        log('正在尝试登录...')
        send('login', config.key);
    } else {
        connected = false
    }


    return connected

}

connect()

var inter = setInterval(() => {
    if (!connected) {
        return false;
    }


    // 每隔一段时间向 Edge 汇报服务器信息
    let players = mc.getOnlinePlayers()
    let pl_data = []

    for (let i in players) {
        let pl = players[i]

        let d = {
            name: pl.name,
            xuid: pl.xuid,
            pos: [pl.pos.x, pl.pos.y, pl.pos.z],
            gameMode: pl.gameMode,
            maxHealth: pl.maxHealth,
            health: pl.health,
        }

        pl_data.push(d)
    }

    send('update_user', pl_data)

}, 1000)

wsc.listen("onLostConnection", function () {
    // reconnect
    log('尝试重新连接...')
    connect()
})

wsc.listen("onError", function () {
    // reconnect
    log('连接到 Edge.st 失败，请重新启动服务器。')
    clearInterval(inter)
})

mc.regPlayerCmd("ts", "带你去下一个服务器", (player) => {
    send('next', {
        xuid: player.xuid,
        all: false
    })
});

mc.regPlayerCmd("fs", "手动将你的数据上传到 Flow 网络。", (player) => {
    save_player(player)
});

mc.regConsoleCmd("tsa", "传送所有玩家到其他服务器", () => {
    send('next', {
        all: true
    })
});

function send(event, data) {
    // log('Client: ' + event)
    var json = JSON.stringify({
        event: event,
        data: data
    })

    return wsc.send(json)
}


mc.listen('onJoin', (player) => {
    send('validate_user', player)
})

// mc.regPlayerCmd('my', 'Your account', (player) => {
//     send('validateUser', player)
// })

mc.listen('onLeft', (pl) => {
    save_player(pl)

})

function save_player(pl) {
    let nbt = pl.getNbt()
    let saveNBT = NBT.createTag(NBT.Compound)
    saveNBT.setTag("OffHand", nbt.getTag("Offhand"))
    saveNBT.setTag("Inventory", nbt.getTag("Inventory"))
    saveNBT.setTag("Armor", nbt.getTag("Armor"))
    saveNBT.setTag("EnderChest", nbt.getTag("EnderChestInventory"))

    let d = {
        name: pl.name,
        xuid: pl.xuid,
        pos: [pl.pos.x, pl.pos.y, pl.pos.z],
        gameMode: pl.gameMode,
        maxHealth: pl.maxHealth,
        health: pl.health,
        nbt: saveNBT.toSNBT(),
        config: config
    }

    send('player_logout', d)

}

mc.listen('onJoin', (player) => {
    // 检查玩家是否绑定账号
    send('get_player', player.xuid);
})

mc.listen('onChat', (player, msg) => {
    // 检查玩家是否绑定账号
    send('broadcast_chat', {
        name: player.name,
        msg: msg,
        config: config
    });
})

mc.regConsoleCmd("shutdown","转移所有玩家并关闭服务器。",function(args){
    send('next', {
        all: true
    })
    mc.broadcast('服务器即将关闭，所有玩家将会传送到下一个服务器。', 0)
    
    log('服务器将在5s后关闭。')
    setTimeout(() => {
        mc.runcmd('stop')
    }, 5000)
});


var this_player
wsc.listen('onTextReceived', (msg) => {
    // log(msg)
    msg = JSON.parse(msg)

    switch (msg.event) {
        case 'login_success':
            // send server data
            send('server_data', {
                motd: config.motd,
                name: config.name,
                version: mc.getBDSVersion(),
                ip_port: config.ip_port
            })
            connected = true
            client_id = msg.data
            log('登录成功')
            mc.setMotd(config.motd)
            break

        case 'login_failed':
            connected = false
            clearInterval(inter)
            log('登录失败，请检查 Key 是否正确。');
            break

        case 'tell':
            var this_player = mc.getPlayer(msg.data.xuid)

            let message = null
            if (msg.data.message === undefined) {
                switch (msg.data.code) {
                    case 404:
                        message = '您的游戏账号尚未绑定到 Flow，同步功能可能不会被启用。'
                        break;
                }
            } else {
                message = msg.data.message
            }

            this_player.tell(message)

            break

        case 'player_data':
            var this_player = mc.getPlayer(msg.data.name)

            this_player.sendText('Edge Standing 强力驱动')

            if (msg.data.nbt == null) {
                this_player.sendText('你的数据无需同步')

                break
            }

            // 写入 NBT 
            let readNBT = NBT.parseSNBT(msg.data.nbt)
            let nbt = this_player.getNbt()

            nbt.setTag("Offhand", readNBT.getTag("OffHand"))
            nbt.setTag("Inventory", readNBT.getTag("Inventory"))
            nbt.setTag("Armor", readNBT.getTag("Armor"))
            nbt.setTag("EnderChestInventory", readNBT.getTag("EnderChest"))

            this_player.setNbt(nbt)
            // this_player.tell('数据已经重新同步，请破坏或者拾取/使用任意方块以刷新物品栏。')
            this_player.refreshItems()

            break


        case 'chat':
            if (!config.receive_chat) {
                break
            }
            if (msg.data.client_id !== client_id) {
                var chat_msg = `[${msg.data.server_name}]${msg.data.name}: ${msg.data.msg}`
                mc.broadcast(chat_msg, 0)
                log('<Chat> ' + chat_msg)
            }

            break


        case 'transfer_all':
            if (msg.data == null) {
                log('暂时找不到合适的服务器。')
            } else {
                log('将全部玩家带去： ' + msg.data.name)

                let players = mc.getOnlinePlayers()

                for (let i in players) {
                    players[i].tell('正在前往 ' + msg.data.name)
                    setTimeout(() => {
                        players[i].transServer(msg.data.ip.toString(), parseInt(msg.data.port))
                    }, 100)
                }

            }
            break;

        case 'transfer':
            if (msg.data == null) {
                log('暂时找不到合适的服务器。')
            } else {
                var this_player = mc.getPlayer(msg.data.xuid)

                this_player.transServer(msg.data.ip.toString(), parseInt(msg.data.port))

            }
            break;
            
        case 'upgrade':
            log('正在更新 Flow. ')
            mc.runcmd("ll reload flow.js");
            
            break;

    }
});