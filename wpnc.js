const apiSettings = {
  "server": "http://flexidb.devmd.co.uk/",
  "url": "http://flexidb.devmd.co.uk/api/v1/",
  "encryption": true,
  "transformCase": true,
  "logsEnable": false,
  "methods": [
    "localSfx",
    "nc"
  ],
  "flexidb": true
}

const wpSettings = {
  "name": "wordpress",
  "server": window.location.origin + "/",
  "url": window.location.origin + "/" + (wpncSettings.wp.permalinks ? "wp-json" : "?rest_route") + "/wp/v2/",
  "encryption": false,
  "methods": [
    "wordpress"
  ]
}

const NOTIFICATION_POST_ADDED = 103
const NOTIFICATION_POST_UPDATED = 104
const NOTIFICATION_POST_REMOVED = 105

const EVENT_TYPES = {
  [NOTIFICATION_POST_ADDED]: 'added',
  [NOTIFICATION_POST_UPDATED]: 'updated',
  [NOTIFICATION_POST_REMOVED]: 'removed'
}

const dfxAPI = new API(apiSettings.url, apiSettings)
const wpApi = new SimpleAPI(wpSettings.url, wpSettings)

dfxAPI.publicKey = "y55p653c0fas2qp9jrbvmhh4qf7l1viqqd5m9ypz4qhw6xwgey2r69mnfky657nl"
dfxAPI.secretKey = "odl9lzbrpnwpfakgzzf74syxudyt1ief"

dfxAPI.request({
  method: 'GET',
  endpoint: 'wpnc/nc_connection',
  data: []
}).then(ncSettings => {
  const {address, port, ssl, session} = ncSettings
  const flags = ssl ? 1 : 0
  const protocol = ssl ? 'wss' : 'ws'
  const url = `${protocol}://${address}:${port}`

  ws = new WebSocket(url)

  handlers = {
    open: function (e) {
      // dispatch(nc.handleMessage(e, ssl)).then(e => dispatch(nc.socketOpened(e)))

      ws.send(flags.toString(16) + ' ' + session)
      console.log('NC opened')
    },
    message: function (e) {
      socketReceivedMessage(e)
      // dispatch(nc.handleMessage(e, ssl)).then(e => dispatch(nc.socketReceivedMessage(e)))
    },
    error: function (e) {
      // dispatch(nc.handleMessage(e, ssl)).then(e => dispatch(nc.socketReceivedError(e)))
      console.error('NC ERROR', e)
    },
    close: function (e) {
      // dispatch(nc.handleMessage(e, ssl)).then(e => dispatch(nc.socketClosed(e)))
      console.log('NC closed')
    }
  }

  Object.keys(handlers).forEach(type => {
    ws.addEventListener(type, handlers[type])
  })
}).catch(e => {
  console.error(e)
})

function socketReceivedMessage (e) {
  const data = JSON.parse(e.data)
  const {code, result} = data
  switch (code) {
    case NOTIFICATION_POST_REMOVED: 
      postRemove(result.post)
      break;
    case NOTIFICATION_POST_ADDED: 
      postAdd(result.post)
      break;
    case NOTIFICATION_POST_UPDATED: 
      postUpdate(result.post)
      break;
  }
}



function postRemove (id) {
  console.log('Post ' + id + ' removed')
  const postArticle = document.getElementById('post-'+id)

  if (wpncSettings.wp.isPostsPage && postArticle) {
    postArticle.remove()
    // possible need to get new pagination or redirrect on previous page
  } 

  if (wpncSettings.wp.isPost && wpncSettings.wp.isPost === id && postArticle) {
    alert('This post has just been deleted. \n You will be redirected on main page')
    window.location.href = wpncSettings.wp.postPageUrl
  }
}



function postUpdate(id) {
  console.log('Post ' + id + ' updated')
  const postArticle = document.getElementById('post-'+id)

  if (postArticle) {
    // simple update, replace content
    wpApi.post().get(id).then(result => {
      const newContent = document.createRange().createContextualFragment(result.rendered_post)
      const parentNode = postArticle.parentNode
      parentNode.replaceChild(newContent, postArticle)
    }).catch(error => {
      console.warn('Unnable to get post', error)
    })
  } else {
    // it should be 'add' by definition, but wordpress fire add immediately on creating empty draft
    // any action after - update
    if (wpncSettings.wp.isPostsPage) {
      wpApi.post().get(id).then(result => {
        const newContent = document.createRange().createContextualFragment(result.rendered_post)
        const parentNode = document.getElementById('main')
        // possible need to remove last child and get new pagination
        // const postsNodes = parentNode.getElementsByClassName('post')
        
        parentNode.prepend(newContent)
      }).catch(error => {
        console.warn('Unnable to get post', error)
      })
    }
  }
}



function postAdd(id) {
  console.log('Post ' + id + ' added')

  // if (wpncSettings.wp.isPostsPage) {
  //   wpApi.post().get(id).then(result => {
  //     const newContent = document.createRange().createContextualFragment(result.rendered_post)
  //     const parentNode = document.getElementById('main')
  //     // possible need to remove last child and get new pagination
  //     // const postsNodes = parentNode.getElementsByClassName('post')
      
  //     parentNode.prepend(newContent)
  //   }).catch(error => {
  //     console.warn('Unnable to get post', error)
  //   })
  // }
}