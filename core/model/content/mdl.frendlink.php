<?php
include_once('shopObject.php');
/**
 * mdl_frendlink
 *
 * @uses shopObject
 * @package
 * @version $Id: mdl.frendlink.php 1867 2008-04-23 04:00:24Z hujianxin $
 * @copyright 2003-2007 ShopEx
 * @license Commercial
 */
class mdl_frendlink extends shopObject{

    var $idColumn = 'link_id';
    var $textColumn = 'link_name';
    var $adminCtl = 'content/frendlink';
    var $defaultCols = 'link_name,href,image_url,orderlist';
    var $defaultOrder = array('orderlist','desc');
    var $tableName = 'sdb_link';

    function getFieldById($link_id, $aPara){
        $sqlString = 'SELECT '.implode(',', $aPara).' FROM sdb_link WHERE link_id = '.intval($link_id);
        return $this->db->selectrow($sqlString);
    }

    function save($aData,&$msg){
        $storager = &$this->system->loadModel('system/storager');
        if($_FILES){
            $aData['image_url'] = $storager->save_upload($_FILES['link_logo'],'link');
            if(!$aData['image_url'])unset($aData['image_url']);
        }

        if($aData['link_id']){
            $rs = $this->db->query("SELECT * FROM " . $this->tableName . " WHERE link_id=" . intval($aData['link_id']));
            $sql = $this->db->getUpdateSql($rs,$aData);
        }else{
            unset($aData['link_id']);
            $rs = $this->db->query("SELECT * FROM " . $this->tableName . " WHERE 0=1");
            $sql = $this->db->getInsertSql($rs,$aData);
        }
        if(!$sql || $this->db->exec($sql)){
            $msg = __("保存成功");
            return true;
        }else{
            $msg = __("保存失败");
            return false;
        }
    }
}