
var count_down =  {
    template :
    '<div class="tc text-main fs28" style="margin-bottom: 0.25rem;">\n' +
    '        倒计时：' +
    '<span  v-text="hour < 0 ? 0 : hour"></span>：' +
    '<span  v-text="minute < 0 ? 0: minute"></span>：' +
    '<span  v-text="second"></span>\n' +
    '    </div>',

    props: ['time'],
    data : function (){
        return {
            hour : 0,
            minute: 0,
            second : 0,
        }
    },
    mounted : function(){
        this.start();
    },
    methods: {
        start: function () {
            let that = this;
            //时间转化
            that.transition();
            if (!that.time)
                    return ;
            // 倒计时
            let interval = window.setInterval(() => {
                if (--that.second <= 0) {
                    if(--that.minute < 0){
                        if(--that.hour < 0){
                            $.alert('时间已到，请重新加载页面');
                            window.clearInterval(interval);
                        }else {
                            that.minute = 59;
                        }
                    }else {
                        that.second = 59;
                    }
                }
            }, 1000);
        },
        //秒 =》 时分秒
        transition : function (){
            var _time =this.time;
            this.second =  _time % 60
            this.minute = parseInt( _time / 60) % 60;
            this.hour = parseInt(parseInt( _time / 60) / 60);
        }

    }
};