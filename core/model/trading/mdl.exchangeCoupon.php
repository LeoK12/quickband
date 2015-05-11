<?php
include_once('shopObject.php');
class mdl_exchangeCoupon extends shopObject{
    var $idColumn = 'cpns_id'; //表示id的列
    var $textColumn = 'cpns_name';
    var $defaultCols = 'cpns_name,cpns_point';
    var $adminCtl = 'sale/exchangeCoupon';
    var $defaultOrder = array('cpns_id','desc');
    var $tableName = 'sdb_coupons';

     function getList($cols,$filter,$start=0,$limit=20,$orderType=null){
        $sql = 'select '.$cols.',sdb_coupons.pmt_id from sdb_coupons
                        left join sdb_promotion as p on sdb_coupons.pmt_id=p.pmt_id
                        where '.$this->_filter($filter);
        if($orderType)$sql.=' order by '.implode($orderType,' ');
        return $this->db->selectLimit($sql,$limit,$start);
    }

    function _filter($filter) {
        $where=array(1);
        $where[] = 'cpns_type=\'1\'';
        $where[] = 'cpns_point is not null';
        if ($filter['cpns_name']) {
            $where[] = 'cpns_name like\'%'.$filter['cpns_name'].'%\'';
        }

        if (isset($filter['ifvalid'])) {
            if ($filter['ifvalid']===1){
                $curTime = time();
                $where[] = 'cpns_status=\'1\' AND pmt_time_begin <= '.$curTime.' and pmt_time_end >'.$curTime;
            }
        }
        return parent::_filter($filter).' AND cpns_point > 0 AND '.implode($where,' and ');
    }

    function delete($filter) {
        $arrId = $filter['cpns_id'];
        if ($arrId) {
            $strId = substr($strId,-1)==','?substr($strId,0,-1):$strId;
            $sSql = 'update sdb_coupons set cpns_point=null WHERE cpns_id in ('.implode(',', $arrId).')';
            if ($this->db->exec($sSql)) {
                return true;
            } else {
                $msg = __('数据删除失败！');
                return false;
            }
        }else{
            $msg = 'no select';
            return false;
        }
    }

    function saveExchange($aData) {
        $aRs = $this->db->query('SELECT * FROM sdb_coupons WHERE cpns_id='.$aData['cpns_id']);
        $sSql = $this->db->getUpdateSql($aRs,$aData);
        return (!$sSql || $this->db->exec($sSql));

    }

    function modifier_cpns_type(&$rows){
        $array = array(__('A类优惠券'),__('B类优惠券'));
        foreach($rows as $k => $v){
            $rows[$k] = $array[$v];
        }
    }


}
?>
