<?php

namespace app\publics\controller;
use app\ClientbaseController;

class Index  extends ClientbaseController{
	/**
	 * APP下载页
	 */
	public function downApp()
	{
        $down_info['android_link'] = settings('android_link');
		$down_info['android_code'] = settings('android_code')?("/static/vpay/upload/qr_code/android_code/".str_replace("\\","/",settings('android_code'))):"";
        $down_info['ios_link'] = settings('ios_link');
        $down_info['ios_code'] = settings('ios_code')?("/static/vpay/upload/qr_code/ios_code/".str_replace("\\","/",settings('ios_code'))):"";
        $this->assign("down_info", $down_info);
        return $this->fetch();
	}
}
