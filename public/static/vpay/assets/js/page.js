(function (doc, win) {
    var docEl = doc.documentElement,
        resizeEvt = 'orientationchange' in window ? 'orientationchange' : 'resize',
        recalc = function () {
            var clientWidth = docEl.clientWidth;
            if(clientWidth > 750){
                clientWidth = 750;
            }
            if (!clientWidth) return;
            docEl.style.fontSize = 75 * (clientWidth / 375) + 'px';
        };

    if (!doc.addEventListener) return;
    win.addEventListener(resizeEvt, recalc, false);
    doc.addEventListener('DOMContentLoaded', recalc, false);

  var color = [
    {
      main: '#2ac5ff'
    },
    {
      main: '#5FB878'
    },
    {
      main: '#03152A'
    }
  ];

  var curMainColor = color[0].main;

  var theme = function () {
    var body = document.getElementsByTagName("body")[0];
    var style = document.createElement('style');
    style.setAttribute("type", "text/css");
    style.setAttribute("id", "themeColor");
    var styleStr =
      '.weui-btn_primary{background-image: linear-gradient(250deg, ' + curMainColor + ' 0%, ' + curMainColor + ' 100%), linear-gradient(' + curMainColor + ', ' + curMainColor + ');}' +
      '.weui-navbar__item.weui-bar__item--on{color: ' + curMainColor + '}' +
      '.weui-navbar__item.weui-bar__item--on:after{background-color: ' + curMainColor + '; box-shadow: none;}' +
      '.text-blue{ color: ' + curMainColor + '}' +
      '.inline-btn{ border-color: ' + curMainColor + '; background-color:#fff;}' +
      '.code_block .copybtn_box .inline-btn{color: ' + curMainColor + '}' +
      '.weui-dialog__btn{color: ' + curMainColor + '}' +
      '.my_qbtopbg,.page-hd_imgbg,.qbtopbg{ background-color: ' + curMainColor + '}' +
      '.qb_addbtn::after, .qb_addbtn::before{background-color: ' + curMainColor + '}' +
      '.bottom-tabbar a.active .label{ color: ' + curMainColor + '}' +
      '.weui-switch:checked, .weui-switch-cp__input:checked ~ .weui-switch-cp__box{ border-color: ' + curMainColor + '; background-color: ' + curMainColor + '}' +
      '.icon_cp_b{ color: ' + curMainColor + '}' +
      '.bottom-tabbar a.active .icon:before{ color: ' + curMainColor + '}' +
      '.inline-btn.tj_btn{  background-color: '+curMainColor+'}';
    style.innerHTML = styleStr;
    body.appendChild(style);
  }
  window.onload = function () {
   //  theme();
  }

})(document, window);




/* *
* 调用此方法发送HTTP请求。
*
* @public
* @param   {string}    url           请求的URL地址
* @param   {mix}       data          发送参数
* @param   {Function}  callback      回调函数
* @param   {string}    type          请求的方式，有"GET"和"POST"两种
* @param   {boolean}   asyn          是否异步请求的方式,true：异步，false：同步,没有回调函数必须同步否则将发生错误
* @param   {string}    dataType      响应类型，有"JSON"、"XML"和"TEXT"三种
* iqgmy
*/
function jq_ajax(url,data,callback,type,async,dataType){
  if (typeof(callback) != 'undefined') async = true;
  type = (type != 'get' && type!= 'GET') ? 'POST' : type;
  async = typeof(async) == 'undefined' ? false : async;
  dataType = typeof(dataType) == 'undefined' ? 'json' : dataType;

  var jq_ajax_result = new Object;
  if (typeof(data) == 'object'){
    var date_str = '';
    for(var key in data ) date_str += key+'='+encodeURIComponent(data[key])+'&';
    data = date_str;
  }
  $.ajax({
       url:  url,
       type: type,
       data: data,
       dataType: dataType,
     async: async,
       success: function(result){
       jq_ajax_result = result;
       if (callback == '') return false;
         if (typeof(callback) == 'function') return callback(result);
       if (typeof(callback) != 'undefined') return eval(callback+'(result)');
       },
     error: function(){
       jq_ajax_result.code = 0;
       jq_ajax_result.msg = '网络异常，请重新尝试.';
       if (callback == '') return false;
         if (typeof(callback) == 'function') return callback(jq_ajax_result);
       if (typeof(callback) != 'undefined') return eval(callback+'(jq_ajax_result)');
     }
     });

  return jq_ajax_result;
}
