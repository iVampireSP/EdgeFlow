log(
  '欢迎使用 Edge.st Flow。请确认您的服务器没有更改玩家背包之类的插件，因为这样可能会导致玩家数据冲突，造成数据不同步。'
)

log('感谢您的配合。')

mc.broadcast('Flow 重新载入完成。')
// mc.broadcast('Flow 更新: 一些有趣的提示。')

let config = {
  key: 'Your key here',
  name: 'Server name',
  motd: 'Server Motd',
  ip_port: '123.456.789.1:19132',
  group: 'public',
  receive_chat: true,
  syncMoney: false,
}

const serverAddrPort = '123.456.789.123:3512'

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
let syncMoney = config.syncMoney ?? false
let alert_msg = null

if (syncMoney) {
  log('警告：经济同步已启用，这是一个实验性的功能，可能会造成意想不到的后果。')
}

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
  //   log('服务器地址: ' + serverAddrPort)

  const wsc_connect = wsc.connect('ws://' + serverAddrPort + '/')
  if (wsc_connect) {
    // 发送 Login
    log('正在尝试登录...')

    asyncEvent('login', config.key, (value) => {
      //   log(value)
      if (value == 'failed') {
        log('登录失败，请检查您的配置文件。')
        connected = false
      } else {
        client_id = value.client_id
        alert_msg = value.server.alert

        asyncEvent(
          'server_data',
          {
            motd: config.motd,
            name: config.name,
            version: mc.getServerProtocolVersion(),
            ip_port: config.ip_port,
            group: config.group,
          },
          () => {
            connected = true
            log('登录成功')
            if (alert_msg) {
              log(alert_msg)
            }
            mc.setMotd(config.motd)
          }
        )
      }
    })
  } else {
    log('无法连接到服务器。')
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

    // if (++save_count == 120) {
    //   d.nbt = getNBT(pl)

    //   log('正在自动保存全部玩家数据...')
    //   save_count = 0
    // }

    pl_data.push(d)
  }

  send('update_user', pl_data)
}, 1500)

wsc.listen('onLostConnection', function () {
  // reconnect
  log('正在重新载入Flow...')
  setTimeout(() => {
    reloadFlow()
  }, 5000)
})

wsc.listen('onError', function () {
  // reconnect
  log('连接到 Edge.st 失败，正在重试。')
  setTimeout(() => {
    reloadFlow()
  }, 5000)
})

mc.regPlayerCmd(
  'ts',
  '带你去下一个服务器，如果要加入指定服务器，请在命令后面加上目标服务器ID',
  (player, args) => {
    // 检测 args[0] 是否存在
    if (args[0] === undefined) {
      asyncEvent('next', { xuid: player.xuid, name: player.name }, (value) => {
        if (value) {
          for (var i in value) {
            log(
              '已将' +
                player.name +
                '传送到' +
                value[i].name +
                '(' +
                value[i].ip.toString() +
                ':' +
                parseInt(value[i].port) +
                ')'
            )

            player.transServer(value[i].ip.toString(), parseInt(value[i].port))

            send('broadcast_event', {
              name: config.name,
              msg: player.name + ' 进入了 ' + value[i].name,
              config: config,
            })
          }
        } else {
          player.tell('暂时找不到合适的服务器')
          log(
            '暂时找不到合适的服务器，可能是服务器池中没有与您BDS版本匹配的服务器。请尝试更新BDS。'
          )
        }
      })
    } else {
      asyncEvent('getServer', args[0], (value) => {
        if (!value) {
          player.tell('没有找到目标服务器。')
        } else if (value.alert) {
          player.tell(
            '暂时不能进入服务器 ' +
              value.name +
              '，因为这个服务器还有未处理的警告。'
          )
          player.tell(value.name + '的警告内容: ' + value.alert)
        } else {
          if (value.status == 'active') {
            send('broadcast_event', {
              msg: player.name + ' 直达了 ' + value.name,
              config: config,
            })
            player.transServer(value.ip.toString(), parseInt(value.port))
          } else {
            player.tell('服务器 ' + value.name + ' 暂时不可用。')
          }
        }
      })
    }
  }
)

mc.regPlayerCmd('fs', '手动将你的数据上传到 Flow 网络。', (player) => {
  save_player(player)

  player.tell('手动上传数据到 Flow 网络成功。')
})

mc.regConsoleCmd('tsa', '传送所有玩家到其他服务器', () => {
  asyncEvent('nextAll', null, (value) => {
    if (value) {
      for (var i in value) {
        log('将全部玩家带去： ' + value[i].name)
        let players = mc.getOnlinePlayers()
        for (let i in players) {
          players[i].tell('管理员将您带去 ' + value[i].name)
          setTimeout(() => {
            players[i].transServer(
              value[i].ip.toString(),
              parseInt(value[i].port)
            )
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
  send('player_logout', pl)
  send('broadcast_event', {
    msg: Format.Gold + pl.name + ' 退出了游戏',
    config: config,
  })
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

mc.listen('onPreJoin', (player) => {
  asyncEvent('player_data', player.xuid, (value) => {
    if (!value) {
      player.sendText('嗨，欢迎来到由 Edge.st Flow 网络驱动的服务器！')
    } else {
      player.sendText('本服务器支持 Flow Cluster.')
    }

    if (syncMoney) {
      if (value.money != 0) {
        money.set(player.xuid, value.money)
      }
    }

    if (value.nbt == null) {
      log(
        '玩家 ' +
          player.name +
          ' 在 Flow 服务器中没有数据，将上传本服务器的数据。'
      )
      player.tell('由于您在 Flow 云端没有数据，我们将会上传您当前的数据。')
      return false
    }

    // 写入 NBT
    let readNBT = NBT.parseSNBT(value.nbt)
    let nbt = player.getNbt()

    // nbt.setTag('Offhand', readNBT.getTag('OffHand'))
    // nbt.setTag('Inventory', readNBT.getTag('Inventory'))
    // nbt.setTag('Armor', readNBT.getTag('Armor'))
    nbt.setTag('EnderChestInventory', readNBT.getTag('EnderChest'))

    player.setNbt(nbt)
    player.refreshItems()
  })
})

mc.listen('onJoin', (player) => {
  if (player.isOP() && alert_msg) {
    player.tell(alert_msg)
  }

  send('player_join', player.name)

  send('broadcast_event', {
    msg: Format.Gold + player.name + ' 加入了游戏',
    config: config,
  })
})

mc.listen('onChat', (player, msg) => {
  send('broadcast_chat', {
    name: player.name,
    msg: msg,
    config: config,
  })
})

mc.listen('onPlayerDie', (player, source) => {
  let die_msg
  if (source == null) {
    die_msg = player.name + ' 被玩死了。'
  } else {
    die_msg = player.name + ' 被 ' + source.name + ' 干掉了。'
  }
  send('broadcast_event', {
    msg: die_msg,
    config: config,
  })
})

mc.listen('onConsumeTotem', (player, source) => {
  send('broadcast_event', {
    msg: player.name + ' 超越了生死。',
    config: config,
  })
})

mc.listen('onUseRespawnAnchor', (player, source) => {
  send('broadcast_event', {
    msg: player.name + ' 有想法。',
    config: config,
  })
})

if (syncMoney) {
  mc.listen('beforeMoneySet', (xuid, value) => {
    asyncEvent(
      'money_set',
      { xuid: xuid, value: value, origin: money.get(xuid) },
      (response) => {
        if (response.status) {
          let pl = mc.getPlayer(xuid)
          pl.tell('[+]您的余额已更新为:' + response.value)
          log(pl.name + ' 经济增加至: ' + response.value + '，变动: ' + value)

          money.set(xuid, response.value)
        }
      }
    )
    return true
  })

  mc.listen('beforeMoneyAdd', (xuid, value) => {
    asyncEvent(
      'money_add',
      { xuid: xuid, value: value, origin: money.get(xuid) },
      (response) => {
        if (response.status) {
          let pl = mc.getPlayer(xuid)
          pl.tell('[+]您的余额已更新为:' + response.value)
          log(pl.name + ' 经济增加至: ' + response.value + '，变动: ' + value)

          money.set(xuid, response.value)
        }
      }
    )
    // return true
  })

  mc.listen('beforeMoneyReduce', (xuid, value) => {
    asyncEvent(
      'money_reduce',
      { xuid: xuid, value: value, origin: money.get(xuid) },
      (response) => {
        if (response.status) {
          let pl = mc.getPlayer(xuid)
          pl.tell('[-]您的余额已更新为:' + response.value)
          log(pl.name + ' 经济减少至: ' + response.value + '，变动:  ' + value)

          money.set(xuid, response.value)
        }
      }
    )
    // return true
  })
}

mc.regConsoleCmd('shutdown', '转移所有玩家并关闭服务器。', function (args) {
  asyncEvent('nextAll', null, (value) => {
    if (value) {
      mc.broadcast('服务器即将关闭，所有玩家将会在5s后传送到下一个服务器。', 0)
      setTimeout(() => {
        mc.runcmd('stop')
      }, 5000)
      log('服务器将在5s后关闭。')

      for (var i in value) {
        let players = mc.getOnlinePlayers()
        for (let i in players) {
          setTimeout(() => {
            players[i].transServer(
              value[i].ip.toString(),
              parseInt(value[i].port)
            )
          }, 100)
        }
      }
    } else {
      log(
        '无法传送全部玩家到其他服务器，因为找不到合适的服务器，可能是服务器池中没有与您BDS版本匹配的服务器。请尝试更新BDS。'
      )
      log('服务器将不会关闭。')
    }
  })
})

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

    case 'event':
      if (!config.receive_chat) {
        break
      }
      if (msg.data.client_id !== client_id) {
        var chat_msg = `[${msg.data.server_name}]${msg.data.msg}`
        mc.broadcast(chat_msg, 0)
        log('<Event> ' + chat_msg)
      }

      break

    case 'login_failed':
      log('登录失败。请检查 Token 是否正确。')
      break

    case 'player_chooseing':
      //   get player
      ;(function () {
        const origin_name = msg.data.msg
        log(origin_name + ' 选择中...')
        mc.broadcast(origin_name + ' 选择中...', 0)
        const pl = mc.getPlayer(origin_name)
        if (pl != null) {
          setTimeout(() => {
            pl.rename('[选择中...]' + pl.name)
            setTimeout(() => {
              pl.rename(origin_name)
            }, 1000)
          }, 1000)
        }
      })
      break

    case 'upgrade':
      log('正在更新 Flow. ')
      mc.broadcast(
        'Flow 正在更新，直到出现 Flow 重载完成时，请不要退出游戏。',
        0
      )
      save_count = 119

      setTimeout(() => {
        reloadFlow()
      }, 5000)

      break
  }
})

mc.regPlayerCmd('tui', '传送服务器 UI', (player) => {
  asyncEvent('get_random_servers', null, (value) => {
    let servers = []

    let form = mc.newSimpleForm()

    form.setTitle('接下来去哪里？')
    form.setContent('从下列服务器中进行选择。')

    for (let i in value) {
      servers.push(value[i])
    }

    for (let i in servers) {
      form.addButton(servers[i].name)
    }

    player.sendForm(form, (pl, id) => {
      pl.transServer(servers[id].ip.toString(), parseInt(servers[id].port))
      send('broadcast_event', {
        name: config.name,
        msg: pl.name + ' 通过传送菜单去了 ' + servers[id].name,
        config: config,
      })
    })
  })
})

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
  //   saveNBT.setTag('OffHand', nbt.getTag('Offhand'))
  //   saveNBT.setTag('Inventory', nbt.getTag('Inventory'))
  //   saveNBT.setTag('Armor', nbt.getTag('Armor'))
  saveNBT.setTag('EnderChest', nbt.getTag('EnderChestInventory'))

  return saveNBT.toSNBT()
}

function reloadFlow() {
  mc.runcmd('ll reload flow.js')
  mc.runcmd('ll reload Flow.js')
}
