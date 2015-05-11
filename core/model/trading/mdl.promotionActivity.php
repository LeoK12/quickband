<?php
include_once('shopObject.php');
class mdl_promotionActivity extends shopObject{
    var $idColumn = 'pmta_id'; //表示id的列
    var $textColumn = 'pmta_name';
    var $defaultCols = 'pmta_id,pmta_name,pmta_time_begin,pmta_time_end,pmta_enabled,pmta_describe';
    var $adminCtl = 'sale/activity';
    var $defaultOrder = array('pmta_id','desc');
    var $tableName = 'sdb_promotion_activity';

    function _filter($filter){
        $where=array(1);
        if($filter['pmta_name']){
            $where[] = 'pmta_name like\'%'.$filter['pmta_name'].'%\'';
        }
        return parent::_filter($filter).' and '.implode($where,' and ');
    }

    function getColumns(){
        $ret = array('_cmd'=>array('label'=>__('操作'),'width'=>150,'html'=>'sale/activity/command.html'));
        return array_merge($ret,parent::getColumns());
    }

    function getActivityById($nId) {
        return $this->db->selectRow('select * from sdb_promotion_activity where pmta_id='.intval($nId));
    }

    function saveActivity($aData) {

        if ($aData['pmta_id']){
            $aRs = $this->db->query('SELECT * FROM sdb_promotion_activity WHERE pmta_id='.$aData['pmta_id']);
            $sSql = $this->db->getUpdateSql($aRs,$aData);
            return (!$sSql || $this->db->exec($sSql));
        }else{
            $aRs = $this->db->query('SELECT * FROM sdb_promotion_activity WHERE 0');
            $sSql = $this->db->getInsertSql($aRs,$aData);
            if ($this->db->exec($sSql)){
                return $this->db->lastInsertId();
            }else{
                return false;
            }
        }
    }

    function delete($filter){
        $pmtaId=$filter['pmta_id'];
        if ($pmtaId[0]=='_ALL_'){
            $sql="select pmta_id from sdb_promotion_activity";
            $row=$this->db->select($sql);
            if ($row){
                foreach($row as $key => $val){
                    $tmpRow[]=$val['pmta_id'];
                }
            }
            if ($this->db->exec('delete from sdb_promotion_activity')){
                return $this->db->exec('delete from sdb_promotion where pmta_id IN('.implode(',',$tmpRow).')');
            }
            else 
                return false;
        }
        else{
            $sSql='delete from sdb_promotion_activity where pmta_id in ('.implode(',',$pmtaId).')';
            if ($this->db->exec($sSql)){
                $sql='delete from sdb_promotion where pmta_id IN('.implode(',',$pmtaId).')';
                return $this->db->exec($sql);
            }
            else
               return false;
        }
    }
}
?>
