<?php
require(CORE_DIR.'/kernel.php');
require(CORE_DIR.'/func_ext.php');

class adminCore extends kernel{

    var $_base_url;
    var $_err = array();
    var $ErrorSet = array();
    var $op_id = false;
    var $op_is_super = null;
    var $op_is_disabled = null;
    var $_op_config_modified = false;
    var $__old_session_str = null;

    function adminCore(){
        define('PHP_SELF',dirname($_SERVER['PHP_SELF'] ? $_SERVER['PHP_SELF'] : $_SERVER['SCRIPT_NAME']));
        parent::kernel();
        if(file_exists(BASE_DIR.'/upgrade.php')){
            $upgrade = $this->loadModel('system/upgrade');
            $upgrade->exec($_GET['act']);
        }elseif($_POST['api_url'] == 'time_auth'){
            header("Content-type:text/html;charset=utf-8");
            $this->shopex_auth=$this->loadModel('service/certificate');
            if($this->shopex_auth->check_api()){
                require(CORE_INCLUDE_DIR.'/shop/core.time_auth.php');
                core_time_auth($this);
                exit;
            }
        }else{
            define('__ADMIN__','admin');
            require('adminPage.php');
            $mod = $_GET['ctl']?$_GET['ctl']:'default';
            $act = $_GET['act']?$_GET['act']:'index';

            $this->request = array('action'=>array('controller'=>$mod,'method'=>$act));
            $this->request['action']['ident'] = strtolower('admin:'.
                $this->request['action']['controller'].
                ':'.$this->request['action']['method']);

            $this->db = &$this->database();
            $this->__session_start();

            if($_POST['_DTYPE_DATE']){
                foreach($_POST['_DTYPE_DATE'] as $k){
                    $_POST[$k] = empty($_POST[$k])?$_POST[$k]:strtotime($_POST[$k]);    //php4和php5对strtotime('')的行为不一致，所以加判断
                }
                $_POST['_DTYPE_DATE'] = null;
                unset($_POST['_DTYPE_DATE']);
            }

            if($_POST['_DTYPE_BOOL']){
                foreach($_POST['_DTYPE_BOOL'] as $k){
                    $_POST[$k] = $_POST[$k]!='false';
                }
                $_POST['_DTYPE_BOOL'] = null;
                unset($_POST['_DTYPE_BOOL']);
            }

            if($_POST['_DTYPE_TIME']){
                foreach($_POST['_DTYPE_TIME'] as $k){
                    if($_POST[$k]){
                        $_POST[$k] = empty($_POST[$k])?$_POST[$k]:strtotime($_POST[$k]);    //php4和php5对strtotime('')的行为不一致，所以加判断
                        if(isset($_POST['_DTIME_']['H'][$k])){
                            $_POST[$k]+=$_POST['_DTIME_']['H'][$k]*3600+$_POST['_DTIME_']['M'][$k]*60;
                        }
                    }
                    unset($_POST['_DTIME_']['H'][$k],$_POST['_DTIME_']['M'][$k]);
                }
                $_POST['_DTYPE_TIME'] = null;
                unset($_POST['_DTYPE_TIME']);
            }
/*            foreach($_POST['_DTIME_']['H'] as $t=>$h){
                $_POST[$k] .= $h.':'.$_POST['_DTIME_']['M'][$t];
            }*/
            unset($_POST['_DTIME_']);

            $controller = &$this->getController($mod);
            $this->ctl = &$controller;
            if(!is_object($controller)){
                $this->responseCode(404);
                exit();
            }
            if(!$this->callAction($controller,$act,$_GET['p'])){
                $this->responseCode(404);
                exit();
            }
        }
    }

   function __session_start(){
        if(isset($_GET['sess_id'])){
            $this->sess_id = $_GET['sess_id'];
            if($_COOKIE['SHOPEX_SID']!=$_GET['sess_id'])
                setcookie('SHOPEX_SID',$this->sess_id);
        }elseif($_COOKIE['SHOPEX_SID']){
            $this->sess_id = $_COOKIE['SHOPEX_SID'];
        }else{
            $this->sess_id = md5(microtime().remote_addr().mt_rand(0,9999));
            setcookie('SHOPEX_SID',$this->sess_id);
        }
        if(!($row = $this->db->selectrow('SELECT s.op_id,s.sess_data,o.name,o.username,o.super,o.status,o.disabled,o.config
                FROM sdb_op_sessions s
                left join sdb_operators o
                on o.op_id = s.op_id
                WHERE s.sess_id = \''.$this->sess_id.'\'',true,true))
            || !($_SESSION = unserialize($row['sess_data'])) ){
            $_SESSION = array();
        }else{
            $this->__old_session_str = md5($row['sess_data']);
        }

        if($row['op_id']){
            $this->op_id = $row['op_id'];
            $this->op_is_super = $row['super'];
            $this->op_name = $row['name']?$row['name']:$row['username'];
            $this->op_is_disabled = ( $row['disabled']=='true' || $row['status']!=1 );
            if(($this->op_config = unserialize($row['config'])) && isset($this->op_config['timezone'])){
                $GLOBALS['user_timezone'] = $this->op_config['timezone'];
            }else{
                $GLOBALS['user_timezone'] = $this->getConf('system.timezone.default');
            }
        }

        register_shutdown_function(array(&$this,'__session_close'));
    }

    function __session_close($writeBack = true){
        if($this->__session_closed){
            return;
        }
        $this->__session_closed = true;
        if(!$writeBack){
            return;
        }

        if($this->_op_config_modified && $this->op_id){
            $aRs = $this->db->exec('select config from sdb_operators where op_id='.intval($this->op_id));
            $sql = $this->db->GetUpdateSql($aRs,array('config'=>$this->op_config));
            if($sql){
                $this->db->exec($sql,true,true);
            }
        }

        $aRs = $this->db->exec("SELECT * FROM sdb_op_sessions WHERE sess_id='".$this->sess_id."'",true,true);
        
        if($this->op_id){
            $status = 1;
        }else{
            $status = 0;
        }

        $sess = serialize($_SESSION);
        $aTemp = array(
            'sess_id'=>$this->sess_id,
            'op_id'=>$this->op_id+0,
            'last_time'=>time(),
            'sess_data'=>$sess,
            'status'=>$status,
            'ip'=>remote_addr(),
        );
        if($this->__old_session_str == md5($sess)){
            unset($aTemp['sess_data']);
        }
        $sess=null;
        unset($sess);

        $sql = $this->db->GetUpdateSql($aRs,$aTemp,true);
        if(!$sql || $this->db->exec($sql,true,true)){
            return true;
        }else{
            return false;
        }
    }

    function setExpries($time){;}

    /**
     * &getController
     *
     * @param mixed $mod
     * @access public
     * @return void
     */
    function &getController($mod,$args=null){
        if(!class_exists('pageFactory')){
            require('pageFactory.php');
        }
        $baseName = basename($mod,$args);
        $dirName = dirname($mod);

        if($dirName=='plugins'){
            $addon = &$this->loadModel('system/addons');
            $object = &$addon->load($baseName,'admin');
            $object->template_dir = dirname($object->plugin_path).'/';
            $object->db = &$this->database();
        }else{
            if (defined('CUSTOM_CORE_DIR') && file_exists($cusfname = CUSTOM_CORE_DIR.'/'.__ADMIN__.'/controller/'.$dirName.'/cct.'.$baseName.'.php')){
                $fname = $cusfname;
                $mod_name='cct_'.$baseName;
            }else{
                $fname = CORE_DIR.'/admin/controller/'.$dirName.'/ctl.'.$baseName.'.php';
                $mod_name = 'ctl_'.$baseName;
            }
            $loaded = @require($fname);
            if(!$loaded)
                return false;
            $object = new $mod_name($this);
        }

        $object->system = &$this;
        $object->controller = $mod;
        return $object;
    }

    function get_op_conf($key){
        return $this->op_config[$key];
    }

    function set_op_conf($key,$value){
        $this->op_config[$key] = $value;
        $this->_op_config_modified = true;
    }

    function mkUrl(){
        return 'javascript:void(0);';
    }

    function sfile($file,$file_bak=null,$use=false){
        $this->__session_close();
        parent::sfile($file,$file_bak,$use);
    }

}
