var https_path = 'http://www.moduleshop.top/';
var md5 = require("md5.js")
var sms = require("smscode.js")
var keys = 'eb5c6b3e4505c1fd7878bde2ed8544cf'
var util = require("../utils/util.js")
//GET方法获取数据或提交数据
function fetchGet(url, _data, callback) {
    var user_devtoken = getcache('user_devtoken')
    var timestamp = (new Date()).getTime()
    var new_time = timestamp
    if (user_devtoken) {
        _data.devtoken = user_devtoken
        _data.source = 'developers'
        _data.timeStamp = new_time
        _data.sign = md5.hexMD5(user_devtoken + new_time + keys)
    }
    let par = ''
    for (var i in _data) {
        par += i + '=' + _data[i] + '&'
    }
    wx.request({
        url: url + '?' + par,
        header: {
            'Content-Type': 'application/json'
        },
        success(res) {
            callback(null, res.data)
        },
        fail(e) {
            callback(e)
        }
    })
}


//POST方法获取数据或提交数据
function fetchPost(url, data, callback) {
    var user_devtoken = getcache('user_devtoken')
    var timestamp = (new Date()).getTime()
    var new_time = timestamp
    if (user_devtoken) {
        data.devtoken = user_devtoken
        data.source = 'developers'
        data.timeStamp = new_time
        data.sign = md5.hexMD5(user_devtoken + new_time + keys)
    }
    wx.request({
        method: 'POST',
        url: url,
        header: {
            'content-type': 'application/x-www-form-urlencoded'
        },
        data: data,
        success(res) {
            callback(null, res.data)
        },
        fail(e) {
            console.error(e)
            callback(e)
        }
    })
}



function fetchPostOther(url, data, callback) {
    wx.request({
        method: 'POST',
        url: url,
        header: {
            'content-type': 'application/x-www-form-urlencoded'
        },
        data: data,
        success(res) {
            callback(null, res.data)
        },
        fail(e) {
            console.error(e)
            callback(e)
        }
    })
}


function getSearchMusic(keyword, pageindex, callbackcount, callback) {
    wx.request({
        url: 'https://c.y.qq.com/soso/fcgi-bin/search_for_qq_cp',
        data: {
            g_tk: 5381,
            uin: 0,
            format: 'json',
            inCharset: 'utf-8',
            outCharset: 'utf-8',
            notice: 0,
            platform: 'h5',
            needNewCode: 1,
            w: keyword,
            zhidaqu: 1,
            catZhida: 1,
            t: 0,
            flag: 1,
            ie: 'utf-8',
            sem: 1,
            aggr: 0,
            perpage: 20,
            n: callbackcount, //返回数据的个数
            p: pageindex,
            remoteplace: 'txt.mqq.all',
            _: Date.now()
        },
        method: 'GET',
        header: {
            'content-Type': 'application/json'
        },
        success: function(res) {
            if (res.statusCode == 200) {
                callback(res.data);
            }
        }
    })
}

function callbackfunction(data, callback) {
    callback("null", data);
}

/**
 * 提示成功消息
 */
function success_msg(_msg, _time) {
    var _time = _time == undefined ? 600 : _time
    wx.showToast({
        title: _msg,
        icon: 'success',
        duration: _time
    })
}

/**
 * 提示错误信息
 */
function error_msg(_msg, _time) {
    var _time = _time == undefined ? 600 : _time
    wx.showToast({
        title: _msg,
        icon: 'none',
        duration: _time
    })
}

/**
 * 获取缓存中的数据
 */
function getcache(_name) {
    const _value = wx.getStorageSync(_name)
    return _value
}


/**
 * 写入数据到缓存中
 */
function putcache(_name, _value) {
    wx.setStorage({
        key: _name,
        data: _value
    })
}

/**
 * 加载更多数据
 */
function pagelist(_url, _pages, _data) {
    _data.pages = _pages
    wx.request({
        method: 'POST',
        url: _url,
        header: {
            'content-type': 'application/x-www-form-urlencoded'
        },
        data: _data,
        success(res) {
            callback(null, res.data)
        },
        fail(e) {
            callback(e)
        }
    })
}

function islogin(_name) {
    const userid = getcache(_name)
    if (userid) {
        return userid;
    } else {
        return false;
    }
}

/**
 * 读取公共配置项
 */
function getconfig(_name, callback) {
    wx.request({
        method: 'POST',
        url: https_path + '/publics/api.index/setting',
        header: {
            'content-type': 'application/x-www-form-urlencoded'
        },
        data: {
            key_str: _name
        },
        success(res) {
            callback(null, res.data.data)
        },
        fail(e) {
            callback(e)
        }
    })
}

/**_mobile  发送的号码
 * _types  发送类型
 * 发送验证码
 */
function sendsms(_mobile, _types, callback) {
    const _data = {
        'mobile': _mobile,
        'type': _types,
    }
    wx.request({
        method: 'POST',
        url: https_path + '/publics/api.sms/sendCode',
        header: {
            'content-type': 'application/x-www-form-urlencoded'
        },
        data: _data,
        success(res) {
            callback(null, res.data)
        },
        fail(e) {
            callback(e)
        }
    })
}

//模块化
module.exports = {
    sendsms: sendsms, //发送验证码
    fetchPostOther: fetchPostOther, // POST请求，不带验证
    getconfig: getconfig, //读取配置项
    islogin: islogin, //
    pagelist: pagelist, //加载更多数据
    getcache: getcache, //读取缓存中数据
    putcache: putcache, //写入数据到缓存中
    success_msg: success_msg, //提示成功消息
    error_msg: error_msg, //提示错误信息
    // API
    https_path: https_path, //域名
    callbackfunction: callbackfunction,
    // METHOD
    fetchGet: fetchGet, //GET请求
    fetchPost: fetchPost, //POST请求
    getSearchMusic: getSearchMusic
}