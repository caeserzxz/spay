(function(win){
    var callMobile = function (handlerInterface, handlerMethod, parameters){
        var dic = {'handlerInterface':handlerInterface,'function':handlerMethod,'parameters': parameters};
        win.webkit.messageHandlers[handlerInterface].postMessage(dic);
    }
    var init = function(){
        if (/(iPhone|iPad|iPod|iOS)/i.test(navigator.userAgent)){
            var instruct = {};
            if ( !win.app || typeof win.app.wxLogin !== 'function') {
                instruct.wxLogin = function () {  //获取微信开放平台code
                    callMobile('wxLogin','wxLoginCallback',{});
                }
            }
            if ( !win.app || typeof win.app.getLocBySDK !== 'function') {
                instruct.getLocBySDK = function () {  //获取百度定位经纬度
                    callMobile('getLocBySDK','GetLocCallBack',{});
                }
            }
            if (!win.app || typeof win.app.wxPay !== 'function') {
                instruct.wxPay = function (payString, url) {  //调起微信支付
                    callMobile('wxPay','wxLoginCallback',{payString, url});
                }
            }
            if (!win.app || typeof win.app.AliPay !== 'function') {
                instruct.AliPay = function (payString) {  //调起支付宝支付
                    callMobile('AliPay','aliPayCallback',{payString});
                }
            }
            win.app = instruct;
        }
    }
    win.init_ISO = init;
})(window);
init_ISO();