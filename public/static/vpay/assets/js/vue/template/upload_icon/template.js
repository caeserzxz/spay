
var myimage ;

Vue.component('upload-one', {
    template : '<div class="weui-cells small_panel">\n' +
    '            <div class="weui-cell no_t">\n' +
    '                <div class="weui-cell__bd">\n' +
    '                    <div class="fwbold">上传头像</div>\n' +
    '                    <div class="weui-uploader small_uploader">\n' +
    '                        <div class="weui-uploader__bd">\n' +
    '                            <ul class="weui-uploader__files" id="uploaderIcon">\n' +
    '                                    <div v-if="myimage">\n' +
    '                                    <li class="weui-uploader__file"  :style="{backgroundImage: \'url(\' + myimage + \')\'}">\n' +
    '                                    </li>\n' +
    '                                </div>\n' +
    '                            </ul>\n' +
    '                            <div class="weui-uploader__input-box">\n' +
    '                                <input id="uploaderInput_Icon" class="weui-uploader__input" type="file" accept="image/*" multiple="" v-on:change="upload($event)">\n' +
    '                            </div>\n' +
    '                        </div>\n' +
    '                    </div>\n' +
    '                </div>\n' +
    '            </div>\n' +
    '        </div>',
    props: ['image'],
    data:function(){
        return {
            myimage : ""
        };
    },
    methods : {
        upload : function(event){
            var _this = this;
            console.log($(event.target)[0].files);
            var imgFile = $(event.target)[0].files[0];//取到上传的图片
            var formData=new FormData();//通过formdata上传
            formData.append('file',imgFile);
            formData.append('type',"icon");
            $.ajax({
                url: '/Img/add',
                type: 'POST',
                cache: false,
                data: formData,
                processData: false, //data 是formData对象
                contentType: false
            }).done(function(res) {
                res = JSON.parse(res)
                console.log(res);
                if(res.status == 200){
                    _this.myimage = res.data.src;
                    myimage = res.data.src;
                } else
                    alert(res.message);
            }).fail(function(res) {});
        },

    }
})
