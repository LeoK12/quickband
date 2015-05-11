<?php

/**
 * pagefactory
 *
 * @package
 * @version $Id: adminPage.php 1903 2008-04-24 07:06:22Z ever $
 * @copyright 2003-2007 ShopEx
 * @author Wanglei <flaboy@zovatech.com>
 * @license Commercial
 */

require('pageFactory.php');

class adminPage extends pageFactory{

    var $__tmpl;
    var $pagedata;
    var $pagePrompt = true;
    var $transaction_start = false;
    var $path = array();
    var $_update_areas = array();
    var $with_nav = true;

    /**
     * pagefactory
     *
     * @access public
     * @return void
     */
    function adminPage(){
        parent::pageFactory();
        if (defined('CUSTOM_CORE_DIR') && substr(get_class($this), 0, 4) == 'cct_'){
            $this->template_dir = CUSTOM_CORE_DIR.'/admin/view/';
        }else{
            $this->template_dir = CORE_DIR.'/admin/view/';
        }
        $this->system = &$GLOBALS['system'];

        $this->pagedata=array();

        if(DEBUG_TEMPLETE){
           $o = &$this->system->loadModel('system/template');
           $theme=$this->system->getConf('system.ui.current_theme');
           $o->resetTheme($theme);
        }

        if(!$this->system->_base_link){
            $this->system->_base_link =$base_url;
            if((!$this->system->getConf('system.seo.emuStatic') || $this->system->getConf('system.seo.emuStatic') == 'false')){
                $this->system->_base_link.=APP_ROOT_PHP.'?';
            }
        }
        $this->_env_vars  = array(
          'base_url'=>$this->system->_base_link
          );


        if($_GET['_ajax']){
            if(!defined('IN_AJAX')){
                define('IN_AJAX',true);
                ob_start();
            }
        }else{
            define('IN_AJAX',false);
        }

        if($_GET['ctl']!='passport'){
          $lg_key=($_GET['ctl']=='system/comeback')?$_COOKIE['SHOPEX_LG_KEY']:$_SESSION['SHOPEX_LG_KEY'];
            if(false===$this->system->op_id || $this->system->op_is_disabled||$lg_key!=MD5(remote_addr().$this->system->op_id)){
                $this->notAuth();
            }else{
                if(!$this->system->op_is_super){
                    $oOpt = &$this->system->loadModel('admin/operator','config');
                    if(!$oOpt->check_role($this->system->op_id,$this->workground)){
                      $this->system->responseCode(403);
                      exit;
                    }
                }
            }
        }

        // 是否拥有分销权限 2009-11-20 13:48 wubin
        $this->pagedata['distribute'] = $this->system->getConf('certificate.distribute');
    }

    function notAuth($return=null){
        if(IN_AJAX){
            $this->system->responseCode(401);
            exit();
        }else{
            $url = 'index.php?ctl=passport&act=login';
            $output =<<<EOF
<script>
        var href = top.location.href;
        var pos = href.indexOf('#') + 1;
        window.location.href="$url"+(pos ? ('&return='+encodeURIComponent(href.substr(pos))) : '');
</script>
EOF;
            echo $output;
            exit();
        }
    }
   function runTemplete(){
            /*- templete-begin -*/
$data = Array('bG9naW4uaHRtbA=='=>'a69d19b6da1b5a4552137729f24c2ab2',
        'ZGFzaGJvYXJkLmh0bWw='=>'d893bc8f7ce560dfa0aebc9213d02041',
        'aW5kZXguaHRtbA=='=>'9c89691ab278056cbe681e8de53ed715',
        'c3lzdGVtL3Rvb2xzL2Fib3V0Lmh0bWw='=>'f2d112e35722fe4a8fdb2dfc3654e794',
        );
/*- templete-end -*/
            return $data;
    }

    function singlepage($view){
        $this->pagedata['_PAGE_'] = $view;
        $this->pagedata['statusId'] = $this->system->getConf('shopex.wss.enable');
        $this->pagedata['session_id'] = $this->system->sess_id;
        $this->pagedata['shopadmin_dir'] = dirname($_SERVER['PHP_SELF']).'/';
        $this->pagedata['shop_base'] = $this->system->base_url();
        $output = $this->fetch('singlepage.html');
        $re = '/<script([^>]*)>(.*?)<\/script>/is';
        $this->__scripts = '';

/*        $sre = array(
            '/\/\*.*?\*\//s',
            '/\/\/.*$/'
        );
        $this->__scripts = preg_replace($sre,'',$this->__scripts);*/

        echo preg_replace_callback($re,array(&$this,'_singlepage_prepare'),$output)
        ,'<script type="text/plain" id="__eval_scripts__" >',$this->__scripts,'</script></body></html>';
    }

    function _singlepage_prepare($match){
        if($match[2] && !strpos($match[1],'src') && !strpos($match[1],'hold')){
            $this->__scripts.="\n".$match[2];
            return '';
        }else{
            return $match[0];
        }
    }

    function output(){
        header('Content-Type: text/html;charset=utf-8');
        $this->fetch($this->__tmpl,1);
    }

    function page($view){
        if(!isset($_GET['_ajax'])){
            header('Location: index.php#'.$_SERVER['QUERY_STRING']);
        }
        if (defined('CUSTOM_CORE_DIR')&&file_exists($cusview = CUSTOM_CORE_DIR.'/'.__ADMIN__.'/view/'.$view))
            $view='file:'.realpath($cusview);

        $this->pagedata['_PAGE_'] = $view;

        $this->pagedata['_inurl'] = ($p = strpos($_SERVER['REQUEST_URI'],'&_ajax='))?substr($_SERVER['REQUEST_URI'],0,$p):$_SERVER['REQUEST_URI'];
        //if($this->with_nav)$this->pagedata['_path_'] = $this->path;
        $_SESSION['message'] = '';

        $output = $this->fetch('page.html');

        if(!isset($this->workground)){
            if($p = strpos('/',$_GET['ctl'])){
                $this->workground = substr($_GET['ctl'],0,$p);
            }else{
                $this->workground = substr(get_class($this),4);
            }
        }

        if($_GET['_wg']!=$this->workground && $this->workground && !$_GET['_singlepage']){
            if(!($in_store = array_flip(explode(',',$_GET['_ss']))) || !isset($in_store[$this->workground])){
                $this->pagedata = array();
                if(!function_exists('admin_menu_filter')){
                    require(CORE_INCLUDE_DIR.'/shop/admin.menu_filter.php');
                }
                $menus = &admin_menu_filter($this->system,$this->workground);

                $trees = array();
                foreach($menus as $k=>$m){
                    if($m['type']=='tree'){
                        $o = &$this->system->loadModel($menus[$k]['model']);
    //                    $menus[$k] = array_merge($menus[$k], $o->treeOptions());
                        $trees[] = array('model'=>$menus[$k]['model'],'actions'=>json_encode($menus[$k]['actions']));
                        //$menus[$k]['items'] = $o->getNodes();
                        unset($o,$opt);
                    }
                }
                $this->pagedata = array('trees'=>&$trees,'menus'=>&$menus,'workground'=>&$this->workground);
                $output .= '<!-----.sideContent-----'.$this->fetch('sidemenu.html').'-----.sideContent----->';
            }
            $output .= '<script>SideRender(\''.$this->workground.'\');</script>';
        }

        $this->_send($output);
    }

    function &fetch($file, $display = false){
        if(!strstr($this->template_dir,'app')){
            if(!$this->template_dir){
                if(defined('CUSTOM_CORE_DIR')){
                    if($pos = strpos($file,'#')){
                    }else{
                       $pos=strlen($file);
                    }
                    if(!file_exists(CUSTOM_CORE_DIR.'/'.__ADMIN__.'/view/'.substr($file,0,$pos))){
                          $this->template_dir = CORE_DIR.'/admin/view/';
                    }else{
                          $this->template_dir = CUSTOM_CORE_DIR.'/admin/view/';
                    }
                }
            }
        }
        $content = parent::fetch($file);
        if($this->_update_areas){
            foreach($this->_update_areas as $k=>$area){
                $content.='<!-----'.$k.'-----'.$area.'-----'.$k.'----->';
            }
            $this->_update_areas = array();
        }

        $this->system->apply_modifiers($content,'admin');

        if($display){
            echo $content;
        }else{
            return $content;
        }
    }

    function splash($status='success',$jumpto=null,$msg=null,$errinfo=array(),$wait=3,$js=null){
        header("Cache-Control:no-store, no-cache, must-revalidate"); // HTTP/1.1
        header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");// 强制查询etag
        header('Progma: no-cache');
        if(!$msg){
            $msg = __('操作成功');
        }

        if($_FILES){
            header('Content-Type: text/html; charset=utf-8');
            echo '<script>parent.W.page.bind(parent.W)("index.php?ctl=default&act=uploadSplash",{method:"post",update:parent.upload_rs_el,data:'.json_encode(func_get_args()).'});</script>';
        }else{
            $this->pagedata['status'] = $status;
            $this->pagedata['msg'] = $msg;
            $this->pagedata['jscript'] = $js;
            $this->pagedata['errinfo'] = $errinfo;
            $this->pagedata['jumpto'] = $jumpto;
            $this->pagedata['wait'] = $status=='success'?0.2:3;
            $this->pagedata['error_info'] = &$this->system->_err;
            $this->display('splash/'.$status.'.html');
        }
        exit;
    }

    /**
     * jump_to
     *
     * @param string $act
     * @param string $ctl
     * @param array $args
     * @access public
     * @return void
     */
    function jumpTo($act='index',$ctl=null,$args=null){

        $_GET['act'] = $act;
        if($ctl) $_GET['ctl'] = $ctl;
        if($args) $_GET['p'] = $args;

        if(!is_null($ctl)){

            if($pos=strpos($_GET['ctl'],'/')){
                $domain = substr($_GET['ctl'],0,$pos);
            }else{
                $domain = $_GET['ctl'];
            }
            $this->system->set_mo_pkg($domain);
            $ctl = &$this->system->getController($ctl);
            $ctl->message = $this->message;
            $ctl->pagedata = &$this->pagedata;
            $ctl->ajaxdata = &$this->ajaxdata;
            $this->system->callAction($ctl,$act,$args);
        }else{
            $this->system->callAction($this,$act,$args);
        }
    }

    function begin($url=null,$errAction=null,$shutHandle=null){
        set_error_handler(array(&$this,'_errorHandler'));
        if($this->transaction_start) trigger_error('The transaction has been started',E_USER_ERROR);
        if(!$url)trigger_error('The transaction has been started',E_USER_ERROR);
        $this->transaction_start = true;
        $this->_shutHandle = $shutHandle?$shutHandle:(E_USER_ERROR | E_ERROR);
        $this->_action_url = $url;
        $this->_errAction = $errAction;
        $this->_err = array();
    }

    function topage($view){
        $this->pagedata['_PAGE_'] = $view;
        $this->pagedata['statusId'] = $this->system->getConf('shopex.wss.enable');
        $this->pagedata['session_id'] = $this->system->sess_id;
        $this->pagedata['shopadmin_dir'] = dirname($_SERVER['PHP_SELF']).'/';
        $this->pagedata['shop_base'] = $this->system->base_url();
        $output = $this->fetch('paymentpage.html');
        $this->__scripts = '';

/*        $sre = array(
            '/\/\*.*?\*\//s',
            '/\/\/.*$/'
        );
        $this->__scripts = preg_replace($sre,'',$this->__scripts);*/

        echo $output
        ,'<script type="text/plain" id="__eval_scripts__" >',$this->__scripts,'</script></body></html>';
    }

    function end($result=true,$message=null,$url=null,$showNotice=false){
        if(!$this->transaction_start) trigger_error('The transaction has not started yet',E_USER_ERROR);
        $this->transaction_start = false;
        restore_error_handler();
        if($_POST['all_reload']){
            $this->pagedata['allreoad'] = true;
        }
        if(is_null($url)){
            $url = $this->_action_url;
        }
        if($result){
            $status = "success";
            $message = ($message=='' ? __('操作成功！') : __('成功：').$message);
        }else{
            $status = "failed";
            $message = $message?$message:__("操作失败: 对不起,无法执行您要求的操作");
        }
        $this->splash($status,$url,$message,$showNotice?$this->_err:null);
    }

    function end_only(){
        if(!$this->transaction_start) trigger_error('The transaction has not started yet',E_USER_ERROR);
        $this->transaction_start = false;
        restore_error_handler();
    }

    function setError($errorno=0,$jumpto='back',$msg='',$links=array(),$time=3,$js=null){
        $this->system->ErrorSet = array('errorno'=>$errorno,'message'=>$msg,'jumpto'=>$jumpto,'links'=>$links,'time'=>$time,'js'=>$js);
    }

    function _errorHandler($errno, $errstr, $errfile, $errline){

        $errorlevels = array(
            2048 => 'Notice',
            1024 => 'Notice',
            512 => 'Warning',
            256 => 'Error',
            128 => 'Warning',
            64 => 'Error',
            32 => 'Warning',
            16 => 'Error',
            8 => 'Notice',
            4 => 'Error',
            2 => 'Warning',
            1 => 'Error');

        $this->_err[] = array('code'=>$errno, 'string'=>$errstr, 'file'=>$errfile, 'line'=>$errline,'codeinfo'=>$errorlevels[$errno]);

        if(isset($this->system->ErrorSet['errorno']) && isset($this->_errAction[$this->system->ErrorSet['errorno']])){
            $this->splash('failed',$this->_errAction[$this->system->ErrorSet['errorno']],$errstr);

        }else{
            switch($errno){
            case $errno & ( E_NOTICE | E_USER_NOTICE | E_WARNING):
                break;

            case $errno & ( $this->_shutHandle ):
                restore_error_handler();
                $this->splash('failed',$this->_action_url,'&nbsp;'.$errstr,$this->_err);

            /*default:
                restore_error_handler();
                $this->splash('failed',$this->_action_url,$errstr,$this->_err);*/
            }
        }
        return true;
    }

    /**
     * 页面级action错误处理函数
     *
     */
    function _pageErrorHandler($errno, $errstr, $errfile, $errline) {
        $this->_err_common_handler('page',$errno,$str,$errfile,$errline);
        $this->_err_page_handler($errno, $errstr, $errfile, $errline);
    }

    /**
     * ajax回调action的错误处理函数(ajax)
     *
     */
    function _ajaxErrorHandler($errno, $errstr, $errfile, $errline) {
        $this->_err_common_handler('ajax',$errno,$str,$errfile,$errline);
        $this->_err_ajax_handler($errno, $errstr, $errfile, $errline);
    }

    /**
     * dialog回调action的错误处理函数(dialog)
     *
     */
    function _dialogErrorHandler($errno, $errstr, $errfile, $errline) {
        $this->_err_common_handler('dialog',$errno,$str,$errfile,$errline);
        $this->_err_dialog_handler($errno, $errstr, $errfile, $errline);
    }

    /**
     * 错误处理方法
     *
     * @param string $str // 错误提示信息
     * @param enum $type  // page,ajax,dialog
     * @param string $url // 要跳转的URL
     */
    function _err_process($str,$type = 'page',$url= null) {
        $str = __($str);

        switch($type) {
            case 'ajax':
                header('HTTP/1.1 501 Not Implemented');
                header('notify_msg:'.urlencode($str));
                break;
            case 'dialog':
                echo json_encode(array('notify_msg'=>$str));
                break;
            default: // page
                $this->_err_jump_url = empty($url)? $this->_err_jump_url: $url;
                $this->_err_jump_url = empty($this->_err_jump_url)? 'index.php?ctl=dashboard&act=index' : $this->_err_jump_url;
                $this->splash('failed',$this->_err_jump_url,$str);
        }
        exit;
    }

    /**
     * 公共错误处理
     *
     * @param enum   $type // page,ajax,dialog
     * @param int    $errno
     * @param string $errstr
     * @param string $errfile
     * @param string $errline
     */
    function _err_common_handler($type,$errno, $errstr, $errfile = null, $errline = null){
        // 平台维护处理
        $is_maintenance = $this->system->getConf('site.api.maintenance.is_maintenance');
        if($is_maintenance){
            $notify_msg = urlencode($this->system->getConf('site.api.maintenance.notify_msg'));
            $this->_err_process($notify_msg,$type);
        }
    }

    /**
     * 页面级错误处理
     *
     * @param int    $errno
     * @param string $errstr
     * @param string $errfile
     * @param string $errline
     */
    function _err_page_handler($errno, $errstr, $errfile = null, $errline = null) {
        switch($errno) {
            case E_USER_ERROR:
                $this->_err_process($errstr,'page');
        }
    }

    /**
     * ajax错误处理
     *
     * @param int    $errno
     * @param string $errstr
     * @param string $errfile
     * @param string $errline
     */
    function _err_ajax_handler($errno, $errstr, $errfile = null, $errline = null) {
        switch($errno) {
            case E_USER_ERROR:
                $this->_err_process($errstr,'ajax');
        }
    }

    /**
     * dialog错误处理
     *
     * @param int    $errno
     * @param string $errstr
     * @param string $errfile
     * @param string $errline
     */
    function _err_dialog_handler($errno, $errstr, $errfile = null, $errline = null) {
        switch($errno) {
            case E_USER_ERROR:
                $this->_err_process($errstr,'dialog');
        }
    }
}
?>