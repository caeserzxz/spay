
Vue.component("my-head", {
    template : '<div class="page-hd">\n' +
    '        <div class="header">\n' +
    '            <div class="header-left">\n' +
    '                <a href="javascript:history.go(-1)" class="left-arrow"></a>\n' +
    '            </div>\n' +
    '            <div class="header-title">{{title}}</div>\n' +
    '            <div class="header-right">\n' +
    '                <a href="javascript:;"></a>\n' +
    '            </div>\n' +
    '        </div>\n' +
    '    </div>',
    props : ["title"]
});