<?php
require_once('plugin.php');
class mdl_passport extends plugin{
    var $plugin_type = 'file';
    var $plugin_name = 'passport';
    var $prefix='passport.';
    var $_passport = null;

    function _verify() {
        if ($plugin = $this->getCurrentPlugin()) {
            if ($this->getFile($plugin)) {
                return true;
            }
        }
        return false;
    }

    function &_load(){
        if ($plugin = $this->getCurrentPlugin()) {
            if(!$this->_passport){
                $obj = &$this->load($plugin);
                $this->_passport = &$obj;
                if(method_exists($obj,'getOptions')||method_exists($obj,'getoptions')){
                    $obj->setConfig($this->getOptions($plugin,true));
                }
            }else{
                $obj = &$this->_passport;
            }
            return $obj;
        }
    }

    function login($userId, $url) {
        if ($this->_verify()) {
            $obj = &$this->_load();
            return $obj->login($userId,$url);
        }        
    }

    function ssoSignin() {
        if ($this->_verify()) {
            $obj = &$this->_load();
            return $obj->ssoSignin();
        }    
    }

    function logout($userId,$url) {
        if ($this->_verify()) {
            $obj = &$this->_load();
            return $obj->logout($userId,$url);
        }                
    }

    function regist($userId,$url) {
        if ($this->_verify()) {
            $status = &$this->system->loadModel('system/status');
            $status->add('MEMBER_REG');
            
            $obj = &$this->_load();
            return $obj->regist($userId,$url);                        
        }
    }

    function setCurrentPlugin($plugin='') {
        return $this->system->setConf('plugin.'.$this->plugin_name.'.config.current_use',$plugin);        
    }

    function getCurrentPlugin() {
        return $this->system->getConf('plugin.'.$this->plugin_name.'.config.current_use');        
    }
    
    
    function getList() {
        if ($p = parent::getList(array(), false)) {
            $current = $this->getCurrentPlugin();
            foreach($p as $k=>$v) {
                $p[$k]['ifvalid'] = (($current==$k)?'true':'false');
                $p[$k]['passport_type'] = $p[$k]['name'];
                unset($p[$k]['name']);
            }
        }
        return $p;
    }

    function savePassport($aData,&$msg){
        if(!$sType = $aData['passport_type']){
            trigger_error(__('参数丢失'),E_USER_ERROR);
        }
        if (!$this->saveCfg($sType, $_POST['config'])){
            return false;
        }
        $sCurrentPlugin = $this->getCurrentPlugin($sType); 
        if ($aData['passport_ifvalid']=='true') {
            if ($sType != $sCurrentPlugin){
                if (!$this->setCurrentPlugin($sType)){
                    return false;
                }
            }
        }else if ($aData['passport_ifvalid']=='false') {
            if ($sType == $sCurrentPlugin){
                if (!$this->setCurrentPlugin()){
                    return false;
                }
            }            
            
        }
        if ($obj=$this->function_judge('implodeUserToUC')){
            $obj->implodeUserToUC();
        }
        return true;
    }

    function passport_encrypt($txt, $key) {
        srand((double)microtime() * 1000000);
        $encrypt_key = md5(rand(0, 32000));
        $ctr = 0;
        $tmp = '';
        for($i = 0;$i < strlen($txt); $i++) {
            $ctr = $ctr == strlen($encrypt_key) ? 0 : $ctr;
            $tmp .= $encrypt_key[$ctr].($txt[$i] ^ $encrypt_key[$ctr++]);
        }
        return base64_encode($this->passport_key($tmp, $key));
    }

    function passport_decrypt($txt, $key) {
        $txt = $this->passport_key(base64_decode($txt), $key);
        $tmp = '';
        for ($i = 0;$i < strlen($txt); $i++) {
            $md5 = $txt[$i];
            $tmp .= $txt[++$i] ^ $md5;
        }
        return $tmp;
    }

    function passport_key($txt, $encrypt_key) {
        $encrypt_key = md5($encrypt_key);
        $ctr = 0;
        $tmp = '';
        for($i = 0; $i < strlen($txt); $i++) {
            $ctr = $ctr == strlen($encrypt_key) ? 0 : $ctr;
            $tmp .= $txt[$i] ^ $encrypt_key[$ctr++];
        }
        return $tmp;
    }

    function passport_encode($array) {
            $arrayenc = array();
            foreach($array as $key => $val) {
                $arrayenc[] = $key.'='.urlencode($val);
            }
            return implode('&', $arrayenc);
    }
    function function_judge($func){
        if($this->_verify()){
            $obj=&$this->_load();
        }
        if (is_object($obj)){
            if (method_exists($obj,$func))
                return $obj;
            else
                return false;
        }
        else{
            return false;
        }
    }
}
?>
