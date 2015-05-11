<?php
include_once('plugin.php');
require_once('shopObject.php');
class mdl_addons extends shopObject{

    var $defaultCols = 'plugin_name,plugin_base,plugin_type,plugin_desc';
    var $idColumn = 'plugin_id'; //表示id的列
    var $textColumn = 'plugin_name';
    var $tableName = 'sdb_plugins';
    var $_in_app = null;

    function mdl_addons(){
        parent::shopObject();
        $this->alltypes = array(
            'io'=>__('输入输出'),
            'schema'=>__('商品插件'),
            'hook'=>__('事件处理'),
            'pmt'=>__('优惠规则'),
            'local'=>__('地区插件'),
            'messenger'=>__('消息发送'),
            'pay'=>__('支付插件'),
            'passport'=>__('登陆插件'),
            'action'=>__('网店机器人动作'),
        );
    }


    function refresh(){
        $this->in_database = array();
        $type = 0;
        $this->_read_dir($type,PLUGIN_DIR);
        if(count($this->in_database[$type])>0){
            $sql = 'delete from sdb_plugins where plugin_id not in('.implode(',',$this->in_database[$type]).') and plugin_base="'.$type.'"';
        }else{
            $sql = 'delete from sdb_plugins where plugin_base="'.$type.'"';
        }
        return $this->db->exec($sql);
    }

    function _read_dir($base_id,$base_path,$dir=''){
        if ($handle = opendir($base_path.$dir)) {
            while (false !== ($file = readdir($handle))) {
                if($file{0}!='.' && substr($file,0,4)!='app.'){
                    if(is_dir($base_path.$dir.'/'.$file)){
                        if(!$this->_in_app && ($info = $this->_is_app_plugin($base_id,$base_path,$dir.'/'.$file))){
                            $this->_in_app = $info['plugin_ident'];
                            if($plugin_id = $this->_to_database($info)){
                                $this->in_database[$base_id][] = $plugin_id;
                                $this->_read_dir($base_id,$base_path,$dir.'/'.$file);
                                $this->_in_app = $null;
                            }
                        }else{
                            $this->_read_dir($base_id,$base_path,$dir.'/'.$file);
                        }
                    }elseif(substr($file,-4,4)=='.php' && ($info = $this->_is_file_plugin($base_id,$base_path,$dir.'/'.$file))){
                        if($plugin_id = $this->_to_database($info)){
                            $this->in_database[$base_id][] = $plugin_id;
                        }
                    }
                }
            }
            closedir($handle);
        }
    }

    function _to_database($info){
        $rs = $this->db->exec($s = 'select * from sdb_plugins where plugin_base="'.intval($info['plugin_base']).'" and plugin_path="'.$info['plugin_path'].'"');
        $r = $this->db->getRows($rs,1);
        $info['plugin_package'] = $this->_in_app;
        if($r[0]['plugin_id']){
            $this->db->exec($this->db->getupdatesql($rs,$info));
            return $r[0]['plugin_id'];
        }else{
            if($this->_in_app){
                $info['disabled'] = 'true';
            }
            if($this->db->exec($this->db->getinsertsql($rs,$info))){
                return $this->db->lastinsertid();
            }else{
                return false;
            }
        }
    }

    function _is_app_plugin($base_id,$base_path,$dir){

        $basename = basename($dir);
        if(file_exists($f = $base_path.$dir.'/app.'.$basename.'.php')){
            $info = $this->_get_class(file_get_contents($f),'app_'.$basename);
            $plugin = array();
            foreach($info['props'] as $k=>$v){
                $plugin['plugin_'.$k] = $v;
            }
            $plugin['plugin_version']=$info['props']['ver'];
            $plugin['plugin_ident']=$basename;
            $plugin['plugin_path']=$dir.'/app.'.$basename.'.php';
            $plugin['plugin_base']=$base_id;
            $plugin['plugin_type']='app';
            $plugin['plugin_mode']='dir';
            $plugin['plugin_struct']=&$info;
            $plugin['plugin_mtime']=filemtime($f);
            if(!isset($plugin['plugin_name'])){
                $plugin['plugin_name'] = $plugin['plugin_ident'];
            }

            if(isset($info['funcs']['getoptions'])){
                $plugin['plugin_hasopts']='true';
            }else{
                $plugin['plugin_hasopts']='false';
            }

            return $plugin;
        }
        return false;
    }

    function _is_file_plugin($base_id,$base_path,$file){
        $basename = strtolower(basename($file,'.php'));
        $classname = str_replace('.','_',$basename);
        if($p=strpos($basename,'.')){
            $info = $this->_get_class(file_get_contents($base_path.$file),$classname);
            $plugin = array();
            foreach($info['props'] as $k=>$v){
                $plugin['plugin_'.$k] = $v;
            }
            $plugin['plugin_ident']=substr($basename,$p+1);
            $plugin['plugin_path']=$file;
            $plugin['plugin_base']=$base_id;
            $plugin['plugin_type']=substr($basename,0,$p);
            $plugin['plugin_mode']='file';
            $plugin['plugin_struct']=&$info;
            $plugin['plugin_mtime']=filemtime($base_path.$file);
            if(!isset($plugin['plugin_name'])){
                $plugin['plugin_name'] = $plugin['plugin_ident'];
            }

            if(isset($info['funcs']['getoptions'])){
                $plugin['plugin_hasopts']='true';
            }else{
                $plugin['plugin_hasopts']='false';
            }
            return $plugin;
        }else{
            return false;
        }
    }

    function _get_class($content,$classname){
        if(!function_exists('get_class_struct')){
            require(CORE_INCLUDE_DIR.'/funcs/get_class_struct.php');
        }
        return get_class_struct($content,$classname);
    }

    function &load($name,$type){
        if(($type=='app'||$type=='shop' ||$type=='admin') && !class_exists('app')){
            require('app.php');
        }
        $list = $this->getlist('plugin_id,plugin_path,plugin_struct,plugin_config,plugin_base',array('plugin_type'=>$type,'plugin_ident'=>$name),0,1,'plugin_base desc');
        if($data = $list[0]){
            return $this->plugin_instance($data);
        }else{
            return false;
        }
    }

    function plugin_instance($data){
        $sturct = unserialize($data['plugin_struct']);
        $classname = $sturct['class_name'];
        if(!$classname){
            return false;
        }

        if($data['plugin_base']==0){ //系统插件
            if(file_exists(PLUGIN_DIR.$data['plugin_path'])){
                require_once(PLUGIN_DIR.$data['plugin_path']);
            }else{
                return false;
            }
        }else{ //模板插件
//                require_once(CORE_DIR.'/plugins'.$data['plugin_path']);
        }

        $obj = new $classname;
        $obj->charset = &$this->system->loadModel('utility/charset');
        $obj->plugin_path = realpath((($data['plugin_base']==0)?PLUGIN_DIR:'tpl').$data['plugin_path']);
        $obj->plugin_id = $data['plugin_id'];
        $obj->plugin_config = $data['plugin_config']?unserialize($data['plugin_config']):array();
        return $obj;
    }
}
?>