<?php
require_once('shopObject.php');

class mdl_deliverycorp extends shopObject{
    var $idColumn = 'corp_id'; //表示id的列
    var $textColumn = 'corp_id';
    var $defaultCols = 'name,website,ordernum';
    var $adminCtl = 'trading/deliverycorp';
    var $defaultOrder = array('ordernum','desc');
    var $tableName = 'sdb_dly_corp';

    function getCorpList(){
        $sql="select corp_id,name from sdb_dly_corp where disabled='false' order by ordernum desc";
        return $this->db->select($sql);
    }
}
?>