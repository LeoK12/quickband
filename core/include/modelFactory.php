<?php
/**
 * modelFactory
 *
 * @package
 * @version $Id: modelFactory.php 1867 2008-04-23 04:00:24Z flaboy $
 * @copyright 2003-2007 ShopEx
 * @author Wanglei <flaboy@zovatech.com>
 * @license Commercial
 */
class modelFactory{

    var $system;
    var $db;

    function modelFactory(){
        $this->system = &$GLOBALS['system'];
        $this->db = &$this->system->database();
    }

    function setError($errorno=0,$jumpto='back',$msg,$links=array(),$time=3,$js=null){
        $this->system->ErrorSet = array('errorno'=>$errorno,'message'=>$msg,'jumpto'=>$jumpto,'links'=>$links,'time'=>$time,'js'=>$js);
    }

}
?>