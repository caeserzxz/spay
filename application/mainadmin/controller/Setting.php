<?php
namespace app\mainadmin\controller;
use app\AdminController;
use app\mainadmin\model\SettingsModel;
use think\Db;
use think\Request;
/**
 * 设置
 * Class Index
 * @package app\store\controller
 */
class Setting extends AdminController
{
	/*------------------------------------------------------ */
	//-- 优先执行
	/*------------------------------------------------------ */
	public function initialize(){
        parent::initialize();
        $this->Model = new SettingsModel();
    }
	/*------------------------------------------------------ */
	//-- 首页
	/*------------------------------------------------------ */
    public function index(){

		$this->assign("setting", $this->Model->getRows());
        return $this->fetch();
    }
	/*------------------------------------------------------ */
	//-- 保存配置
	/*------------------------------------------------------ */
    public function save(){
        $set = input('post.setting');
		$res = $this->Model->editSave($set);
		if ($res == false) return $this->error();
		return $this->success('设置成功.');
    }

    /**
     * vpay 配置信息
     */
    public function vpayConfig(){
		$this->assign("setting", $this->Model->getRows());
        return $this->fetch('vpayConfig');
    }

    /**
     * vpay 下载设置
     */
    public function down_seting(){

        if(request()->isPost()){
            $set = $this->Model->getRows();
            if($set['android_link']){

            }
           $data = input('post.setting');
            $android_code = $_FILES['android_code'];
            if($android_code){
                $android_code_path = $this->upQuestionsWrite('android_code');
                if($android_code_path){
                    $data['android_code'] = $android_code_path;
                }
            }

            $ios_code = $_FILES['ios_code'];
            if($ios_code){
                $ios_code_path = $this->upQuestionsWrite('ios_code');
                if($ios_code_path){
                    $data['ios_code'] = $ios_code_path;
                }
            }

            $res = $this->Model->editSave($data);
            if ($res == false) return $this->error();
            return $this->success('设置成功.');
//           $this->upQuestionsWrite('android_apk');
        }else{

            $this->assign("setting", $this->Model->getRows());
            return $this->fetch('down_seting');
        }
    }

    public function upQuestionsWrite($file_name)
    {
        // 获取表单上传文件
        $file = request()->file($file_name);
        if(empty($file)) {
            $this->error('请选择上传文件');
        }
        // 移动到框架应用根目录/public/uploads/ 目录下
        $info = $file->move(UPLOAD_PATH.'/qr_code/'.$file_name);
        //如果不清楚文件上传的具体键名，可以直接打印$info来查看
        //获取文件（文件名），$info->getFilename()  ***********不同之处，笔记笔记哦
        //获取文件（日期/文件名），$info->getSaveName()  **********不同之处，笔记笔记哦
        $filename = $info->getSaveName();  //在测试的时候也可以直接打印文件名称来查看

        if($filename){
            return $filename;
        }else{
            // 上传失败获取错误信息
            $this->error($file->getError());
        }
    }

    public function matching_config(){
        $this->assign("setting", $this->Model->getRows());
        return $this->fetch('matching_config');
    }
}
