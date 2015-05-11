<?php
class mdl_tools{

    function test_fake_html($is_set=false,&$msg){
        $system=&$GLOBALS['system'];
        $server=$system->loadModel('utility/serverinfo');
        $url = parse_url($system->base_url());
        $code = substr(md5(time()),0,6);
        $content = $server->doHttpQuery($url['path']."/_test_rewrite=1&s=".$code."&a.html");
        if(!strpos($content,'[*['.md5($code).']*]')){
            if(false===strpos(strtolower($_SERVER["SERVER_SOFTWARE"]),'apache')){
                $msg='您的服务器不是apache,无法使用htaccess文件。请手动启用rewrite，否则无法启用伪静态';
                //trigger_error(__('您的服务器不是apache,无法使用htaccess文件。请手动启用rewrite，否则无法启用伪静态'),E_USER_ERROR);
                return false;
            }
            if(file_exists(BASE_DIR.'/'.ACCESSFILENAME)){
                $msg=__('您的系统存在无效的').ACCESSFILENAME.__(', 无法启用伪静态');
                //trigger_error(__('您的系统存在无效的').ACCESSFILENAME.__(', 无法启用伪静态'),E_USER_ERROR);
                return false;
            }else{
                if(($content = file_get_contents(BASE_DIR.'/root.htaccess'))){
                    $content = preg_replace('/RewriteBase\s+.*\//i','RewriteBase '.$url['path'],$content);
                    if(file_put_contents(BASE_DIR.'/'.ACCESSFILENAME,$content)){
                        $content = $server->doHttpQuery($url['path']."/_test_rewrite=1&s=".$code."&a.html");
                        if(!strpos($content,'[*['.md5($code).']*]')){
                            unlink(BASE_DIR.'/'.ACCESSFILENAME);
                            $msg=__('您的系统不支持apache的').ACCESSFILENAME.__(',启用伪静态失败.');
                            //trigger_error(__('您的系统不支持apache的').ACCESSFILENAME.__(',启用伪静态失败.'),E_USER_ERROR);
                            return false;
                        }
                    }else{
                        $msg=__('无法自动生成').ACCESSFILENAME.__(',可能是权限问题,启用伪静态失败');
                        //trigger_error(__('无法自动生成').ACCESSFILENAME.__(',可能是权限问题,启用伪静态失败'),E_USER_ERROR);
                        return false;
                    }
                }else{
                    $msg=__('系统不支持rewrite,同时读取原始root.htaccess文件来生成目标').ACCESSFILENAME.__('文件,因此无法启用伪静态');
                    //trigger_error(__('系统不支持rewrite,同时读取原始root.htaccess文件来生成目标').ACCESSFILENAME.__('文件,因此无法启用伪静态'),E_USER_ERROR);
                    return false;
                }
            }
        }
        if($is_set){
            return $system->setConf('system.seo.emuStatic',true);
        }
        return true;
    }
}

?>