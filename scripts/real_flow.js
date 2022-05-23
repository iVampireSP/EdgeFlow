let config = {
  key: 'Your key here',
  name: 'Server name',
  motd: 'Server Motd',
  ip_port: '123.456.789.1:19132',
  receive_chat: true,
}

const serverAddrPort = '10.37.129.2:3512'

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
let save_count = 0

const send = async (event, data) => {
  let json = JSON.stringify({
    event: event,
    data: data,
  })

  wsc.send(json)

  return new Promise((resolve, reject) => {
    wsc.listen('onTextReceived', (msg) => {
      msg = JSON.parse(msg)

      if (msg.clientEvent === event) {
        // log('data' + msg.data)
        resolve(msg.data)
      } else {
        reject(msg.data)
      }
    })
  })
}

function connect() {
  let connect_name = 'Edge.st Flow'

  if (config.key == 'Your key here' || config.key === null) {
    connect_name = 'Edge.st Public Flow'
  }
  log('正在连接到 ' + connect_name)

  if (wsc.connect('ws://' + serverAddrPort)) {
    // 发送 Login
    log('正在尝试登录...')

    asyncEvent('login', config.key, (value) => {
      if (value == 'failed') {
        log('登录失败，请检查您的配置文件。')
        connected = false
      } else {
        client_id = value

        asyncEvent(
          'server_data',
          {
            motd: config.motd,
            name: config.name,
            version: mc.getBDSVersion(),
            ip_port: config.ip_port,
          },
          () => {
            connected = true
            log('登录成功')
            mc.setMotd(config.motd)
          }
        )
      }
    })
  }

  return connected
}

connect()

var inter = setInterval(() => {
  if (!connected) {
    return false
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

    if (++save_count == 60) {
      d.nbt = getNBT(pl)

      log('正在自动保存全部玩家数据...')
      save_count = 0
    }

    pl_data.push(d)
  }

  send('update_user', pl_data)
}, 1500)

wsc.listen('onLostConnection', function () {
  // reconnect
  log('尝试重新连接...')
  connect()
})

wsc.listen('onError', function () {
  // reconnect
  log('连接到 Edge.st 失败，请重新启动服务器。')
  clearInterval(inter)
})

mc.regPlayerCmd('ts', '带你去下一个服务器', (player) => {
  asyncEvent('next', { xuid: player.xuid }, (value) => {
    if (value) {
      log(
        '已将' +
          player.name +
          '传送到' +
          value.ip.toString() +
          ':' +
          parseInt(value.port)
      )
      player.transServer(value.ip.toString(), parseInt(value.port))
    } else {
      player.tell('暂时找不到合适的服务器')
      log(
        '暂时找不到合适的服务器，可能是服务器池中没有与您BDS版本匹配的服务器。请尝试更新BDS。'
      )
    }
  })
})

mc.regPlayerCmd('fs', '手动将你的数据上传到 Flow 网络。', (player) => {
  save_player(player)

  player.tell('手动上传数据到 Flow 网络成功。')
})

mc.regConsoleCmd('tsa', '传送所有玩家到其他服务器', () => {
  asyncEvent('nextAll', null, (value) => {
    if (value) {
      if (value) {
        log('将全部玩家带去： ' + value.name)
        let players = mc.getOnlinePlayers()
        for (let i in players) {
          players[i].tell('管理员将您带去 ' + value.name)
          setTimeout(() => {
            players[i].transServer(value.ip.toString(), parseInt(value.port))
          }, 100)
        }
      }
    } else {
      log(
        '无法传送全部玩家到其他服务器，因为找不到合适的服务器，可能是服务器池中没有与您BDS版本匹配的服务器。请尝试更新BDS。'
      )
    }
  })
})

// mc.listen('onJoin', (player) => {
//     await send('validate_user', player, () => {
//     })
// })

mc.listen('onLeft', (pl) => {
  save_player(pl)
})

function save_player(pl) {
  let d = [
    {
      name: pl.name,
      xuid: pl.xuid,
      pos: [pl.pos.x, pl.pos.y, pl.pos.z],
      gameMode: pl.gameMode,
      maxHealth: pl.maxHealth,
      health: pl.health,
      nbt: getNBT(pl),
      config: config,
    },
  ]

  send('update_user', d)
}

mc.listen('onJoin', (player) => {
  asyncEvent('player_data', player.xuid, (value) => {
    player.sendText('Edge Standing 强力驱动')

    if (!value) {
      player.sendText('嗨，欢迎来到由 Flow 网络驱动的服务器！')
    }

    if (value.nbt == null) {
      return false
    }

    // 写入 NBT
    let readNBT = NBT.parseSNBT(value.nbt)
    let nbt = player.getNbt()

    nbt.setTag('Offhand', readNBT.getTag('OffHand'))
    nbt.setTag('Inventory', readNBT.getTag('Inventory'))
    nbt.setTag('Armor', readNBT.getTag('Armor'))
    nbt.setTag('EnderChestInventory', readNBT.getTag('EnderChest'))

    value.setNbt(nbt)
    value.refreshItems()
  })
})

mc.listen('onChat', (player, msg) => {
  // 检查玩家是否绑定账号
  send('broadcast_chat', {
    name: player.name,
    msg: msg,
    config: config,
  })
})

// mc.regConsoleCmd('shutdown', '转移所有玩家并关闭服务器。', function (args) {
//   send('next', {
//     all: true,
//   })
//   mc.broadcast('服务器即将关闭，所有玩家将会传送到下一个服务器。', 0)

//   log('服务器将在5s后关闭。')
//   setTimeout(() => {
//     mc.runcmd('stop')
//   }, 5000)
// })

wsc.listen('onTextReceived', (msg) => {
  // log(msg)
  msg = JSON.parse(msg)

  switch (msg.event) {
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

    case 'upgrade':
      log('正在更新 Flow. ')
      mc.runcmd('ll reload flow.js')

      break
  }
})

// mc.regPlayerCmd('tui', '传送服务器 UI', (player) => {
//   send('getRandomServers', { player: { name: player.name } })
// })

function asyncEvent(event, data, callback) {
  const next = async () => {
    return await send(event, data)
  }

  next().then((value) => {
    callback(value)
  })
}

function getNBT(pl) {
  let nbt = pl.getNbt()
  let saveNBT = NBT.createTag(NBT.Compound)
  saveNBT.setTag('OffHand', nbt.getTag('Offhand'))
  saveNBT.setTag('Inventory', nbt.getTag('Inventory'))
  saveNBT.setTag('Armor', nbt.getTag('Armor'))
  saveNBT.setTag('EnderChest', nbt.getTag('EnderChestInventory'))

  return saveNBT.toSNBT()
}
