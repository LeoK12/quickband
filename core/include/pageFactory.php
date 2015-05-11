<?php

/**
 * pagefactory
 *
 * @package
 * @version $Id: pageFactory.php 1867 2008-04-23 04:00:24Z flaboy $
 * @copyright 2003-2007 ShopEx
 * @author Wanglei <flaboy@zovatech.com>
 * @license Commercial
 */
class pagefactory{

    var $__tmpl;
    var $pagedata = array();
    var $left_delimiter            = "<{";
    var $right_delimiter            = "}>";
    var $force_compile        =0;    // force a compile regardless of saved state
    var $default_resource_type = 'file';
    var $_files = array();

    var $_vars        = null;
    var $_plugins        = array(      'modifier'      => array(),
                                       'function'      => array(),
                                       'block'         => array(),
                                       'compiler'      => array(),
                                       'resource'      => array(),
                                       'prefilter'     => array(),
                                       'input'          => array(),
                                       'compile_modifier' => array(),
                                       'outputfilter'  => array());
    var $_file        = "";        // the current file we are processing
    var $_compile_obj    = null;
    var $_env_vars = array();
    var $_null = null;
    var $_resource_type = 1;
    var $_resource_time;
    var $_sections = array();
    var $_foreach = array();
    var $enable_strip_whitespace = true;

    function pagefactory(){
        $this->system = &$GLOBALS['system'];
        $this->_vars = &$this->pagedata;
        $this->files = array();
        if(defined('HOME_DIR')){
          $this->compile_dir = HOME_DIR.'/cache/front_tmpl/';
        }else{
          $this->compile_dir = BASE_DIR.'/home/cache/front_tmpl/';
        }
        $this->memcache=&$this->system->memcache;
        $this->versionTimeStamp = @filemtime(HOME_DIR.'/cache/cachedata.stat.php');
      //$this->versionTimeStamp = @filemtime(CORE_DIR.'/version12.txt');
        $db = &$this->system->database();
        $this->lang = &$this->system->lang;

        $this->_register_resource("widgets", array(array(&$this,"_get_widgets_template"),
        array(&$this,"_get_widgets_timestamp")));

      $this->_register_resource("border", array(array(&$this,"_get_border_template"),
        array(&$this,"_get_border_timestamp")));

        $this->_register_resource("user", array(array(&$this,"_get_user_template"),
        array(&$this,"_get_user_timestamp")));

        $this->_register_resource("shop", array(array(&$this,"_get_shop_template"),
            array(&$this,"_get_shop_timestamp")));

        $this->_register_resource("page", array(array(&$this,"_get_page_template"),
            array(&$this,"_get_page_timestamp")));

        $this->_register_resource('systmpl',array(array($this,"_get_systmpl_template"),
            array($this,"_get_systmpl_timestamp")));

        $this->_plugins['function']['respath'] = array(&$this,'_respath');

    }


    function _get_widgets_timestamp($tpl_name, &$tpl_timestamp, &$smarty) {

        if($p = strpos($tpl_name,':')) $tpl_name = substr($tpl_name,0,$p);
        $tpl_timestamp = filemtime(PLUGIN_DIR.'/widgets/'.$tpl_name);

        if (!is_bool($tpl_timestamp)) {
            $tpl_timestamp = max($tpl_timestamp,$this->versionTimeStamp);
            return true;
        } else {
            return false;
        }
    }
    function _get_widgets_template($tpl_name, &$tpl_source, &$smarty) {

        $this->_in_widgets = dirname($tpl_name);
        if($p = strpos($tpl_name,':')) $tpl_name = substr($tpl_name,0,$p);
        $tpl_source = file_get_contents(PLUGIN_DIR.'/widgets/'.$tpl_name);

        if (!is_bool($tpl_source)) {
            $this->_fix_tpl($tpl_source,'widgets',$tpl_name);
            return true;
        } else {
            return false;
        }
    }

   
   function _get_border_template($tpl_name, &$tpl_source, &$smarty) {

        $tplname=explode("#",$tpl_name);
        $tpl_source=file_get_contents(THEME_DIR.'/'.$smarty->theme.'/'.$tplname[0]);
        //$tpl_source = file_get_contents(PLUGIN_DIR.'/borders/'.$tpl_name);

        if (!is_bool($tpl_source)) { 
            $this->_fix_tpl($tpl_source,'border',$tplname[0]);
            return true; 
        } else { 
            return false; 
        } 
    } 

    function _get_border_timestamp($tpl_name, &$tpl_timestamp, &$smarty) { 

        $tplname=explode("#",$tpl_name);
        $tpl_timestamp = filemtime(THEME_DIR.'/'.$smarty->theme.'/'.$tplname[0]);
        //$tpl_timestamp = filemtime(PLUGIN_DIR.'/borders/'.$tpl_name);

        if (!is_bool($tpl_timestamp)) { 
         $tpl_timestamp = max($tpl_timestamp,$this->versionTimeStamp);
            return true; 
        } else { 
            return false; 
        } 
    }

    function new_dom_id(){
        if(!$this->_new_dom_base){
            $this->_new_dom_base = substr(md5($_SERVER['REQUEST_URI']),0,5);
        }
        return 'el-'.$this->_new_dom_base.'-'.(substr(md5(rand()),0,5));
    }

    function clear_cache(){
        $args = func_get_args();
        foreach($args as $file){
            $file = $this->_get_resource($file);

            //$name = md5((($this->_resource_type == 1) ? $this->template_dir.$file : $this->_resource_type . "_" . $file).$this->lang.$this->__ident);

         $name=md5((($this->_resource_type == 1) ? $this->template_dir.$file : $this->_resource_type . "_" . $file).$this->__ident).$filename.(($this->lang)?('-'.$this->lang):'');

            unlink($this->compile_dir.$name.'.php');
        }
    }

    function clear_all_cache(){
        touch(HOME_DIR.'/cache/cache.status');
    }

    function _set_compile($key,&$content){

        return file_put_contents($this->compile_dir.$key.'.php',$content);
    }

    function _respath($params){

        if($params['type']=='user'){
            if(function_exists('template_files')){
                return template_files($this,$this->system).'/'.$params['name'].'/';
            }else{
                return $this->system->base_url().'themes/'.$params['name'].'/';
            }
        }elseif($params['type']=='widgets'){
            return $this->system->base_url().'plugins/widgets/'.$params['name'].'/';
        }
    }
    function _get_page_template($tpl_name, &$tpl_source, &$tpl_obj) {

        $db = &$this->system->database();
        $row = $db->selectrow('select page_content from sdb_pages where page_name="'.$tpl_name.'" or page_name="' . urlencode($tpl_name) . '" ');
        if ($row) {
            //file_put_contents('sdfsdfsdf.txt',1);
            $tpl_source = $row['page_content'];
            $this->_fix_tpl($tpl_source,'page',$tpl_name);
            return true;
        } else {
            //file_put_contents('sdfsdfsdf.txt',2);
            $file = CORE_DIR.'/html/pages/'.$tpl_name.'.html';

            if(file_exists($file)){
                $tpl_source = file_get_contents($file);
                return true;
            }else{
                return false;
            }
        }
    }

    function _get_page_timestamp($tpl_name, &$tpl_timestamp, &$tpl_obj)
    {
        $db = &$this->system->database();
        $row = $db->selectrow('select page_time,page_title from sdb_pages where page_name="'.$tpl_name.'" or page_name="' . urlencode($tpl_name) . '"');
        if ($row) {
            $tpl_timestamp = $row['page_time'];
            return true;
        } else {
            $file = CORE_DIR.'/html/pages/'.$tpl_name.'.html';
            if(file_exists($file)){
                $tpl_timestamp = filemtime($file);
                return true;
            }else{
                return false;
            }
        }
    }

    function _get_shop_template($tpl_name, &$tpl_source, &$smarty) {

        if (defined('CUSTOM_CORE_DIR')){
            if (file_exists(CUSTOM_CORE_DIR.'/shop/view/'.$tpl_name))
                $tpl_source = file_get_contents(CUSTOM_CORE_DIR.'/shop/view/'.$tpl_name);
            else
                $tpl_source = file_get_contents(CORE_DIR.'/shop/view/'.$tpl_name);
        }
        else
            $tpl_source = file_get_contents(CORE_DIR.'/shop/view/'.$tpl_name);
        if (!is_bool($tpl_source)) {
            $this->_fix_tpl($tpl_source,'shop',$tpl_name);
            return true;
        } else {
            return false;
        }
    }

    function _get_shop_timestamp($tpl_name, &$tpl_timestamp, &$smarty) {
        if (defined('CUSTOM_CORE_DIR')){
            if (file_exists(CUSTOM_CORE_DIR.'/shop/view/'.$tpl_name))
                $tpl_timestamp = filemtime(CUSTOM_CORE_DIR.'/shop/view/'.$tpl_name);
            else
                $tpl_timestamp = filemtime(CORE_DIR.'/shop/view/'.$tpl_name);
        }
        else
            $tpl_timestamp = filemtime(CORE_DIR.'/shop/view/'.$tpl_name);
        if (!is_bool($tpl_timestamp)) {
            $tpl_timestamp = max($tpl_timestamp,$this->versionTimeStamp);
            return true;
        } else {
            return false;
        }
    }


    function _get_user_template($tpl_name, &$tpl_source, &$smarty) {
        $tpl_source = file_get_contents(THEME_DIR.'/'.$tpl_name);
        if (!is_bool($tpl_source)) {
            $this->_fix_tpl($tpl_source,'user',$tpl_name);
            return true;
        } else {
            return false;
        }
    }

    function _get_user_timestamp($tpl_name, &$tpl_timestamp, &$smarty) {
        $tpl_timestamp = is_file(THEME_DIR.'/'.$tpl_name)?filemtime(THEME_DIR.'/'.$tpl_name):false;//.substr($tpl_name,0,strpos($tpl_name,':')));
        if (!is_bool($tpl_timestamp)) {
         $tpl_timestamp = max($tpl_timestamp,$this->versionTimeStamp);
            return true;
        } else {
            return false;
        }
    }




    function _fix_tpl(&$tpl,$type,$name){
        if(substr($tpl,0,3)=="\xEF\xBB\xBF"){
            $tpl = substr($tpl,3);
        }
        if($type!='admin'){

            switch($type){
            case 'block':
            case 'user':
                $pos = strpos($name,'/');
                $name = substr($name,0,$pos);
                $type = 'user';
                break;

            case 'shop':
                break;
            case 'widgets':
                $pos = strpos($name,'/');
                $name = substr($name,0,$pos);
                $tpl = preg_replace('/(["|\'])(images\/.*?["|\'])/','\1<{respath type="widgets" name="'.$name.'" }>\2',$tpl);
                break;
            }

            $from = array(
                '/((?:background|src|href)\s*=\s*["|\'])(?:\.\/|\.\.\/)?(images\/.*?["|\'])/is',
                '/((?:background|background-image):\s*?url\()(?:\.\/|\.\.\/)?(images\/)/is',
                '/<!--[^<|>|{|\n]*?-->/'
            );
            $to = array(
                '\1<{respath type="'.$type.'" name="'.$name.'" }>\2',
                '\1<{respath type="'.$type.'" name="'.$name.'" }>\2',
                ''
            );

            $tpl = preg_replace($from,$to,$tpl);

        }
        return $tpl;
    }

    function assign($key, $value = null){
        if($key){
            $this->_vars[$key] = &$val;
        }
    }

    function register_prefilter($function){
        $_name = (is_array($function)) ? $function[1] : $function;
        $this->_plugins['prefilter'][$_name] = $function;
    }

    function _register_resource($type, $functions){

        if (isset($functions[1])){

            $this->_plugins['resource'][$type] = $functions;
        }else{
            $this->trigger_error("malformed function-list for '$type' in register_resource");
        }
    }

    function template_exists($file){

        //$this->template_dir=$this->template_dir?$this->template_dir:(CORE_DIR.'/admin/view/');
        if(!strstr($this->template_dir,'app')){
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

        if (file_exists($fname = $this->template_dir.$file)){


            $this->_resource_time = filemtime($fname);
            $this->_resource_type = 1;
            return true;
        }else{
            if (file_exists($file)){
                $this->_resource_time = filemtime($file);
                $this->_resource_type = "file";
                return true;
            }
            return false;
        }
    }

    function _get_resource($file){
        $this->__ident='';
        $_resource_name = explode(':', trim($file));

        if($this->default_resource_type!='file' && count($_resource_name) == 1){
            $this->_resource_type = $this->default_resource_type;
            $exists = isset($this->_plugins['resource'][$this->_resource_type]) && call_user_func_array($this->_plugins['resource'][$this->_resource_type][1], array($file, &$resource_timestamp, &$this));
            if (!$exists){
                return false;
                $this->trigger_error("file '$file' does not exist", E_USER_ERROR);
            }
            $this->_resource_time = $resource_timestamp;
        }elseif (count($_resource_name) == 1 || $_resource_name[0] == "file"){
            if($_resource_name[0] == "file"){
                $file = substr($file, 5);
            }
            if($p = strpos($file,'#')){
                $this->__ident = substr($file,$p);
                $file = substr($file,0,$p);
            }

            $exists = $this->template_exists($file);
            if (!$exists){
                return false;
                $this->trigger_error("file '$file' does not exist", E_USER_ERROR);
            }
        }else{
       
            $this->_resource_type = $_resource_name[0];
            $file = substr($file, strlen($this->_resource_type) + 1);



            $exists = isset($this->_plugins['resource'][$this->_resource_type]) && call_user_func_array($this->_plugins['resource'][$this->_resource_type][1], array($file, &$resource_timestamp, &$this));
 
         if (!$exists){
               if(file_exists($file)){
                   return $file;
               }
               return false;
               $this->trigger_error("file '$file' does not exist", E_USER_ERROR);
            }
            $this->_resource_time = $resource_timestamp;
        }

   
     

        return $file;
    }

    function _fetch_compile_include($_tpl_include_file, $_tpl_include_vars=null){
        array_unshift($this->_files,$_tpl_include_file);
        $this->_current_file = $_tpl_include_file;
        $this->_vars = array_merge($this->_vars, $_tpl_include_vars);
        $_tpl_include_file = $this->_get_resource($_tpl_include_file);
        $_compiled_output = $this->_fetch_compile($_tpl_include_file);
        array_shift($this->_files);
        return $_compiled_output;
    }

    function __run_compiled($key,$resource_time,&$output){
        $file = $this->compile_dir.$key.'.php';
        if(file_exists($file) && !(filemtime($file) < max($resource_time,$this->versionTimeStamp))){
            ob_start();
            include($file);
            $output = ob_get_contents();
            ob_end_clean();
            return true;
        }else{
            return false;
        }
    }

    function &_fetch_compile($file){
       
        $this->_current_file = $file;

        $filename=urlencode(basename($file));

       // $name = md5((($this->_resource_type == 1) ? $this->template_dir.$file : $this->_resource_type . "_" . $file).$this->lang.$this->__ident);

       $name=md5((($this->_resource_type == 1) ? $this->template_dir.$file : $this->_resource_type . "_" . $file).$this->__ident).$filename.(($this->lang)?('-'.$this->lang):'');


       if($this->force_compile || !$this->__run_compiled($name,$this->_resource_time,$output)){
          $file_contents = '';


            if($this->_resource_type == 1 || $this->_resource_type == "file"){
                if(file_exists($this->template_dir.$file)){
                    $file_contents = file_get_contents($this->template_dir.$file);
                }
            }else{
                call_user_func_array($this->_plugins['resource'][$this->_resource_type][0],array($file, &$file_contents, &$this));
            }

            if(file_exists($file)){
                $file_contents = file_get_contents($file); 
            }


            $this->_file = $file;
            if (!is_object($this->_compile_obj)){
                $this->_compile_obj = &$this->system->loadModel('system/tramsy');
                $this->_compile_obj->_parent = &$this;
                $this->_compile_obj->enable_strip_whitespace = &$this->enable_strip_whitespace;
            }
            $this->_compile_obj->_require_stack = array();
            $this->_compile_obj->_plugins = &$this->_plugins;
            $this->_compile_obj->left_delimiter = &$this->left_delimiter;
            $this->_compile_obj->right_delimiter = &$this->right_delimiter;
            $output = &$this->_compile_obj->_compile_file($file_contents,false);
            $this->_compile_obj->post_compile($output);
            $this->_set_compile($name,$output);
            ob_start();

            eval(' ?>' . $output);
            $output = ob_get_contents();
            ob_end_clean();
        }

        if(count($this->_plugins['outputfilter'])>0){
            foreach($this->_plugins['outputfilter'] as $filter_func){
                $output = $filter_func($output);
            }
        }
        return $output;
     }

    function trigger_error($error_msg, $error_type = E_USER_ERROR, $file = null, $line = null){
        if(isset($file) && isset($line)){
            $info = ' ('.basename($file).", line $line)";
        }else{
            $info = null;
        }
        trigger_error('Template: '.$pos." $error_msg$info", $error_type);
    }

    function display($file){
       $this->__tmpl = $file;
       $this->output();
    }

    function &fetch($file, $display = false){

        $this->_files = array($file);
        $output = &$this->_fetch_compile($this->_get_resource($file));
        array_shift($this->_files);
        if ($display){
            echo $output;
        }else{
            return $output;
        }
    }

    /**
     * output
     *
     * @param int $expired_time
     * @param mixed $fetch
     * @access public
     * @return void
     */
    function output(){
        header('Content-Type: text/html;charset=utf-8');
        $output = &$this->fetch($this->__tmpl);
        $this->_send($output);
    }

    function _send(&$output){
        $etag = md5($output);
        header("Cache-Control:no-store, no-cache, must-revalidate"); // HTTP/1.1
        header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");// 强制查询etag
        header('Etag: '.$etag);
        header('Progma: no-cache');
        if(isset($_SERVER['HTTP_IF_NONE_MATCH']) && ($_SERVER['HTTP_IF_NONE_MATCH'] == $etag)){
            header('HTTP/1.1 304 Not Modified',true,304);
            exit(0);
        }else{
            header('Content-Type: text/html; charset=utf-8');
            echo $output;
        }
        exit(0);
    }

}
?>