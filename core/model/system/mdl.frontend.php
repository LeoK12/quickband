<?php
if(!class_exists('pageFactory')){
  require(CORE_INCLUDE_DIR.'/pageFactory.php');
}
class mdl_frontend extends pageFactory{

    function mdl_frontend(){
        parent::pagefactory();
        $this->_register_resource('systmpl',array(
            array($this,"_get_systmpl_template"),
            array($this,"_get_systmpl_timestamp")));

        $this->_register_resource("page", array(array(&$this,"_get_page_template"),
            array(&$this,"_get_page_timestamp")));
    }

    function _get_systmpl_template ($tpl_name, &$tpl_source, &$tpl_obj){
        $systmpl = &$this->system->loadModel('content/systmpl');
        $tpl_source = $systmpl->get($tpl_name);
        return $tpl_source!==false;
    }

    function _get_systmpl_timestamp($tpl_name, &$tpl_timestamp, &$tpl_obj){
        $db = &$this->system->database();
        if ($aRet = $db->selectrow("SELECT edittime FROM sdb_systmpl WHERE tmpl_name = '$tpl_name'")) {
            $tpl_timestamp = $aRet['edittime'];
            $tpl_timestamp = max($tpl_timestamp,$this->versionTimeStamp);
            return true;
        } else {
            $systmpl = &$this->system->loadModel('content/systmpl');
            $tpl_timestamp = filemtime($systmpl->_file($tpl_name));
            if(!is_bool($tpl_timestamp)){
                $tpl_timestamp = max($tpl_timestamp,$this->versionTimeStamp);
                return true;
            }else{
                return false;
            }
        }
    }

    function _get_page_template($tpl_name, &$tpl_source, &$tpl_obj) {
        $db = &$this->system->database();
        $row = $db->selectrow('select page_content from sdb_pages where page_name="'.$tpl_name.'" or page_name="' . urlencode($tpl_name) . '" ');
        if ($row) {
            $tpl_source = $row['page_content'];
            $this->_fix_tpl($tpl_source,'page',$tpl_name);
            return true;
        } else {
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
            $tpl_timestamp = max($row['page_time'],$this->versionTimeStamp);
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

}
