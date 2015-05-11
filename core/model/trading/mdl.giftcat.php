<?php
include_once('shopObject.php');
class mdl_giftcat extends shopObject{
    var $idColumn = 'giftcat_id'; //表示id的列
    var $textColumn = 'cat';
    var $defaultCols = '_cmd,cat,shop_iffb,orderlist';
    var $adminCtl = 'sale/giftcat';
    var $defaultOrder = array('orderlist','desc');
    var $tableName = 'sdb_gift_cat';


    function getColumns(){
        $ret = array('_cmd'=>array('label'=>__('操作'),'width'=>70,'html'=>'sale/giftcat/command.html'));
        return array_merge($ret,parent::getColumns());
    }

    function _filter($filter) {
        $where=array(1);
        if(is_array($filter['giftcat_id'])){
            foreach($filter['gcat'] as $giftcat_id){
                if($giftcat_id!='_ANY_'){
                    $cats[] = $giftcat_id;
                }
                if(count($cats)>0){
                    $where[] = 'giftcat_id in ('.implode($cats,',').')';
                }
            }
        }

        if ($filter['cat']) {
            $where[] = 'cat like\'%'.$filter['cat'].'%\'';
        }

        if (isset($filter['shop_iffb'])) {
            if ($filter['shop_iffb']===1) {
                $where[] = 'shop_iffb=\'1\'';
            }
        }

        return parent::_filter($filter).' and '.implode($where,' and ');
    }

    //----------------------------------------------------------

    function getTypeById($catid) {
        $sql = 'SELECT * FROM sdb_gift_cat WHERE giftcat_id='.$catid;
        return $this->db->selectRow($sql);
    }

    function addType($aData) {
        if(isset($aData['giftcat_id']))
        $aTemp = $this->db->select('SELECT cat FROM sdb_gift_cat where giftcat_id != '.$aData['giftcat_id']);
        else
        $aTemp = $this->db->select('SELECT cat FROM sdb_gift_cat ');
        foreach($aTemp  as $val){
       	if($aData['cat']==$val['cat']){
             trigger_error(__('分类名称已经存在'), E_USER_ERROR);
             return false;
       	    }
        }
        if ($aData['giftcat_id']){
            $aRs = $this->db->query('SELECT * FROM sdb_gift_cat WHERE giftcat_id='.$aData['giftcat_id']);
            $sSql = $this->db->getUpdateSql($aRs,$aData);
            return (!$sSql || $this->db->exec($sSql));

        }else{
            $aRs = $this->db->query('SELECT * FROM sdb_gift_cat WHERE 0');
            $sSql = $this->db->getInsertSql($aRs,$aData);

            if ($this->db->exec($sSql)){
                return $this->db->lastInsertId();
            }else{
                return false;
            }
        }
    }

    function getInitOrder() {
        $aTemp = $this->db->selectRow('select max(orderlist) as orderlist from sdb_gift_cat');
        return $aTemp['orderlist']+1;
    }


    //+
    function getTypeArr() {
        $aTemp = $this->db->select('SELECT giftcat_id,cat FROM sdb_gift_cat WHERE 1    ORDER BY orderlist desc');
        return $aTemp;
    }
}
?>