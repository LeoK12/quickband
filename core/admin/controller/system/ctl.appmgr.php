<?php
require_once('objectPage.php');
class ctl_appmgr extends adminPage{

    var $workground = 'tools';

    function index(){

        $appmgr = $this->system->loadModel('system/appmgr');
        $apps = $appmgr->getList();
        $this->pagedata['install_url'] = constant('APP_INSTALL_URL');
        foreach($apps as $k=>$app){
            $file = BASE_DIR.'/plugins/app/'.$app['plugin_package'].'/setting.php';
            if(file_exists($file)){
                if($app['status']=='used'){
                    $setting = array();
                    require($file);
                    foreach($setting as $key=>$value){
                        if(!$this->system->getConf('app.'.$app['plugin_ident'].'.'.$key)){
                            $app['unset_setting'] = 1;
                        }
                    }
                }
                $app['has_setting'] = 1;
            }
            $app['plugin_struct'] = unserialize($app['plugin_struct']);
            $apps[$k] = $app;
        }
        $this->pagedata['apps'] = &$apps;

        $this->page('system/appmgr/index.html');
    }

    function install_update(){
        $this->pagedata['update_url'] = 'index.php?ctl=system/appmgr&act=download_update_app&app_status='.$_GET['app_status'].'&url='.$_GET['url'];
        $this->page('system/appmgr/update.html');
    }

    function download_update_app(){
        if(isset($_GET['url'])){
            include(CORE_DIR.'/admin/controller/service/ctl.download.php');
            $download = new ctl_download();
            $_POST = array(
                'download_list'=>array($_GET['url']),
                'succ_url'=>'http://'.$_SERVER['HTTP_HOST'].dirname($_SERVER['PHP_SELF'])
                .'/index.php?ctl=system/appmgr&act=do_update&app_status='.$_GET['app_status']
            );

            $download->start();
        }
        exit;
    }

    function do_update(){
        $task = HOME_DIR.'/tmp/'.$_GET['download'];
        $temp_mess = file_get_contents($task.'/task.php');
        $down_data = unserialize($temp_mess);
        if($url = $down_data['download_list'][0]){
            $filename = substr($url,strrpos($url,"/")+1);
            $file_path = $task.'/'.$filename;
            $dir_name = substr($filename,0,strrpos($filename,"."));
            if(file_exists($file_path)){
                $appmgr = $this->system->loadModel("system/appmgr");
                if(!$appmgr->instal_ol_app($file_path,$dir_name,$msg,true)){
                    echo $msg;
                    exit;
                }else{
                    if($_GET['app_status']=='used'){
                        $this->install($dir_name,'online','update');
                    }else{
                        $this->install($dir_name,'online');
                    }
                };
            }
        }
    }


    





    function app_onlince(){
        $cet_ping = ping_url("http://app.shopex.cn/web/exuser/index.php");
        if(!strstr($cet_ping,'HTTP/1.1 200 OK')){
            $this->pagedata['cen_error'] = true;
            $this->pagedata['error_url'] = $this->system->base_url().'error.html';
        }else{
            $certi = $this->system->loadModel("service/certificate");
            $this->pagedata['cert_id'] =  $certi->getCerti();
        }
        $this->page('system/appmgr/app_online.html');
    }

    function view($ident){
        $appmgr = $this->system->loadModel('system/appmgr');
        $this->pagedata['app'] = $appmgr->info($ident);
        $this->pagedata['propmap'] = array(
            'ver'=>'版本',
            'website'=>'网址',
            'author'=>'作者',
            'baseurl'=>'访问路径',
            'plugin_path'=>'文件路径',
            );
        $this->display('system/appmgr/detail.html');
    }

    function install_online(){
        if(isset($_GET['url'])){
            include(CORE_DIR.'/admin/controller/service/ctl.download.php');
            $download = new ctl_download();
            $_POST = array(
                'download_list'=>array($_GET['url']),
                'succ_url'=>'http://'.$_SERVER['HTTP_HOST'].dirname($_SERVER['PHP_SELF'])
                .'/index.php?ctl=system/appmgr&act=do_install_online'
            );

            $download->start();
        }
        exit;
    }

    function do_install($ident,$type='offline',$is_update=false){
        $appmgr = $this->system->loadModel('system/appmgr');
        $this->begin('index.php?ctl=system/appmgr&act=index');
        if($appmgr->install($ident,$is_update)){
            $this->clear_all_cache();
            if($type=='online'){
                echo "<script>parent.$('dialogContent').getParent('.dialog').retrieve('instance').close(); W.page('index.php?ctl=system/appmgr&act=index');</script>";
                exit;
            }
            $this->end(true,'安装成功','index.php?ctl=system/appmgr&act=index','');
        }else{
            $this->end(false,'安装失败');
        }
    }

    function install($ident,$type='offline',$is_update=false){
        $appmgr = $this->system->loadModel('system/appmgr');
        $app = &$appmgr->load($ident);
        if($app->depend){
            if($err_str = $appmgr->getappByident($app->depend)){
                $this->pagedata['error_msg'] = $err_str;
            };
        }
        
        $this->pagedata['table_prefix'] = 'sdb_'.$ident.'_';

        include('ctlmap.php');
        $map = array();
        $system_ctl_map = &$this->system->getConf('system.ctlmap');
        foreach($app->ctl_mapper() as $k=>$v){
            if($system_ctl_map[$k]){
                list($pkg,$class,$method)= explode(':',$system_ctl_map[$k]);
                $stopapp[$pkg] = $pkg;
            }
            $map[] = isset($ctlmap[$k])?$ctlmap[$k]:$k;
        }

        foreach($app->listener() as $eventOrg=>$handle){
            if($eventOrg=='*'){
                $listen_event[] = '所有';
            }else{
                list($mdl,$event) = explode(':',$eventOrg);
                $mdl = &$this->system->loadModel($mdl);
                if(method_exists($mdl,'events') &&
                    $events = $mdl->events() && $events[$event]){
                    $listen_event[] = $mdl->name.$events[$event]['label'];
                }else{
                    $listen_event[] = $eventOrg;
                }
            }
        }
        if($type=='online') $this->pagedata['online'] = true;
        $this->pagedata['listen_event'] = &$listen_event;
        $this->pagedata['ctl_mapper'] = $map;
        $this->pagedata['app_ident'] = $ident;
        $this->pagedata['stopapp'] = $appmgr->getNameByIdents($stopapp);
        $this->pagedata['app'] = get_object_vars($app);
        $this->pagedata['app_tables'] = $app->dbtables();

        if($is_update){
            $this->pagedata['update'] = 'yes';
            if($diff_tables = $appmgr->get_app_diff($ident)){
                $this->pagedata['app_diff_tables'] = $diff_tables;
            }
        }
        $this->page('system/appmgr/install.html');
    }




    function do_install_online(){      
        $task = HOME_DIR.'/tmp/'.$_GET['download'];
        $temp_mess = file_get_contents($task.'/task.php');
        $down_data = unserialize($temp_mess);
        if($url = $down_data['download_list'][0]){
            $filename = substr($url,strrpos($url,"/")+1);
            $file_path = $task.'/'.$filename;
            $dir_name = substr($filename,0,strrpos($filename,"."));
            if(file_exists($file_path)){
                $appmgr = $this->system->loadModel("system/appmgr");
                if(!$appmgr->instal_ol_app($file_path,$dir_name,$msg)){
                    echo $msg;
                    exit;
                }else{
                    $this->install($dir_name,'online');                  
                };
            }
        }
    }

    function uninstall($ident){
        $appmgr = $this->system->loadModel('system/appmgr');
        $this->pagedata['table_prefix'] = 'sdb_'.$ident.'_';
        $app = &$appmgr->load($ident,'app');

        include('ctlmap.php');
        $map = array();
        foreach($app->ctl_mapper() as $k=>$v){
            $map[] = $ctlmap[$k];
        }
        $this->pagedata['ctl_mapper'] = $map;

        $this->pagedata['app'] = get_object_vars($app);
        $this->pagedata['app_tables'] = $app->dbtables();
        $this->page('system/appmgr/uninstall.html');
    }

    function do_uninstall($ident){
        $appmgr = $this->system->loadModel('system/appmgr');
        $this->begin('index.php?ctl=system/appmgr&act=index');
        if($appmgr->uninstall($ident)){
            $this->clear_all_cache();
            $this->end(true,'软件卸载成功','index.php?ctl=system/appmgr&act=index','');
        }else{
            $this->end(false,'软件卸载失败');
        }
    }

    function enable($ident){
        $appmgr = $this->system->loadModel('system/appmgr');
        $this->begin('index.php?ctl=system/appmgr&act=index');
        $app_model = $appmgr->load($ident);
        if(method_exists($app_model,'enable')){
            $app_model->enable();
        }
        $this->clear_all_cache();
        $this->end($appmgr->enable($ident));
    }

    function disable($ident){
        $appmgr = $this->system->loadModel('system/appmgr');
        $this->begin('index.php?ctl=system/appmgr&act=index');
        $app_model = $appmgr->load($ident);
        if(method_exists($app_model,'disable')){
            $app_model->disable();
        }
        $this->clear_all_cache();
        $this->end($appmgr->disable($ident));
    }
    function setting($ident){
        $appmgr = $this->system->loadModel('system/appmgr');
        $app_model = &$appmgr->load($ident);
        $app = $appmgr->info($ident);
        if(method_exists($app_model,'setting_load')){
            $app_model->setting_load();
        }
        foreach($app['setting'] as $k=>$v){
            $tmp['app.'.$ident.'.'.$k] = $v;
        }
        $app['setting'] = $tmp;
        $this->pagedata['app'] = $app;
      
        $this->display('system/appmgr/setting.html');
    }
    function setting_save($ident){
        $appmgr = $this->system->loadModel('system/appmgr');
        $app = &$appmgr->load($ident);
        if(method_exists($app,'setting_save')){
            $app->setting_save();
        }else{
            $setting = $_POST['setting'];
                foreach($setting as $key=>$val){
                    $this->system->setConf($key,$val);
                }
            }
        echo "保存成功";
    }
    
    function dofeedback($app_id){
        $certi = $this->system->loadModel("service/certificate");
        $certi_id =  $certi->getCerti();
        echo '<iframe src="http://feedback.ecos.shopex.cn/index.php?certi_id='.$certi_id.'&app_key='.$app_id.'" width=500 height=350/>';
    }
       
}