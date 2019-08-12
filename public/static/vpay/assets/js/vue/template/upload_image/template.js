
var image_upload =  {
    template : '<div class="weui-cells small_panel" style="margin-top: 0px;">\n' +
    '            <div class="weui-cell no_t" style="padding-top: 2px; padding-bottom: 2px;">\n' +
    '                <div class="weui-cell__bd">\n' +
    '                    <div class="weui-uploader small_uploader">\n' +
    '                        <div class="weui-uploader__bd">\n' +
    '                            <ul class="weui-uploader__files" id="uploaderFiles">\n' +
    '                                <div v-for="(val, key) in image_list" style="display: block">\n' +
    '                                    <li class="weui-uploader__file"  :style="{backgroundImage: \'url(../\' + val + \')\'}">\n' +
    '                                        <span class="weui-badge small_badge" style="margin-left: 65px" v-on:click="del(key)">×</span>\n' +
    '                                    </li>\n' +
    '                                </div>\n' +
    '                            </ul>\n' +
    '                            <div class="weui-uploader__input-box">\n' +
    '                                <input id="uploaderInput" class="weui-uploader__input" type="file" accept="image/*" multiple="" v-on:change="upload($event)">\n' +
    '                            </div>\n' +
    '                        </div>\n' +
    '                    </div>\n' +
    '                </div>\n' +
    '            </div>\n' +
    '        </div>',
    props: ['image_list', 'type'],
    methods: {
        upload: function upload(event) {
            var _this = this;
            if (_this.image_list.length >= 9) {
                $.alert("上传图片不能超过9张");
                return false;
            }
            // console.log($(event.target)[0].files);
            var imgFile = $(event.target)[0].files[0]; //取到上传的图片
            this.imgPreview(imgFile);
        },
        //获取图片
        imgPreview: function imgPreview(file, callback) {
            var self = this;
            //判断支不支持FileReader
            if (!file || !window.FileReader) return;
            if (/^image/.test(file.type)) {
                //创建一个reader
                var reader = new FileReader();

                //将图片转成base64格式
                reader.readAsDataURL(file);
                //读取成功后的回调
                reader.onloadend = function () {
                    var result = this.result;
                    var img = new Image();
                    img.src = result;
                    console.log("********未压缩前的图片大小********");
                    console.log(result.length);
                    img.onload = function () {
                        var data = self.compress(img);
                        self.imgUrl = result;

                        var blob = self.dataURItoBlob(data);

                        console.log("*******base64转blob对象******");
                        console.log(blob);

                        var formData = new FormData();
                        formData.append("file", blob);
                        console.log("********将blob对象转成formData对象********");
                        // console.log(formData.get("file"));
                        var config = {
                            headers: { "Content-Type": "multipart/form-data" }
                        };
                        // 发送请求;
                        // $.alert("发起上传");
                        axios.post("/Img/add", formData, config).then(function (res) {
                            if (res.data.status == 200) {
                                self.image_list.push(res.data.data.src);
                                self.returnData();
                            } else {
                                $.alert("图片上传失败，请重试");
                            }
                        });
                    };
                };
            }
        },

        // 压缩图片
        compress: function compress(img) {
            var canvas = document.createElement("canvas");
            var ctx = canvas.getContext("2d");
            var initSize = img.src.length;
            var width = img.width;
            var height = img.height;
            canvas.width = width;
            canvas.height = height;
            // 铺底色
            ctx.fillStyle = "#fff";
            ctx.fillRect(0, 0, canvas.width, canvas.height);
            ctx.drawImage(img, 0, 0, width, height);

            //进行最小压缩
            var ndata = canvas.toDataURL("image/jpeg", 0.1);

            return ndata;
        },

        // base64转成bolb对象
        dataURItoBlob: function dataURItoBlob(base64Data) {
            var byteString;
            if (base64Data.split(",")[0].indexOf("base64") >= 0) byteString = atob(base64Data.split(",")[1]);else byteString = unescape(base64Data.split(",")[1]);
            var mimeString = base64Data.split(",")[0].split(":")[1].split(";")[0];
            var ia = new Uint8Array(byteString.length);
            for (var i = 0; i < byteString.length; i++) {
                ia[i] = byteString.charCodeAt(i);
            }
            return new Blob([ia], { type: mimeString });
        },

        del: function del(key) {
            if (isNaN(key) || key > this.image_list.length) {
                return false;
            } else {
                this.image_list.splice(key, 1);
            }
        },
        submit: function submit() {},
        returnData: function returnData() {
            this.$emit("get-image-list", this.image_list);
        }
    }
};