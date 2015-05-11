<?php
include_once('shopObject.php');
class mdl_level extends shopObject {

    var $idColumn = 'member_lv_id'; //表示id的列
    var $adminCtl = 'member/level';
    var $textColumn = 'name';
    var $defaultCols = 'name,lv_type,dis_count,pre_id,default_lv,experience';
    var $defaultOrder = array('point','ASC',',dis_count','DESC');
    var $tableName = 'sdb_member_lv';

    function modifier_dis_count(&$rows){
        foreach($rows as $k=>$v){
            $rows[$k] = ($v*100).'%';
        }
    }
    function modifier_point(&$row){
        if (is_array($row))
            foreach($rows as $k => $v){
                $row[$k]=intval($v);
            }
        else
            $row=intval($row);
    }
    function getMLevel($sLv=null){
        $aTemp = $aLevel = array();
        if ($sLv == null || $sLv=='') {
            $aTemp = $this->db->select("SELECT member_lv_id,name FROM sdb_member_lv WHERE disabled = 'false'");
        }else{
            $aTemp = $this->db->select("SELECT member_lv_id,name FROM sdb_member_lv WHERE disabled = 'false' AND member_lv_id in(".$sLv.")");
        }

        #if($aTemp){
        #    foreach($aTemp as $val){
        #        $aLevel[]=array($val['member_lv_id'],$val['name']);
        #    }
        #}
        return $aTemp;
    }

    function getFieldById($nLvId){
        return $this->db->selectRow("SELECT * FROM sdb_member_lv WHERE member_lv_id=".intval($nLvId));
    }

    function recycle($filter){
        $data = $this->db->select("select member_id from sdb_members where member_lv_id in(".implode(',',$filter['member_lv_id']).")");
        if(count($data)>0){
            echo __('系统发现有会员使用该会员等级，请调整会员等级后再删除');
            exit;
        }
        return parent::recycle($filter);
    }

    function saveLevel($aData){
        $aData['default_lv'] = empty($aData['default_lv'])?0:1;
        if($aData['lv_type'] == 'wholesale'){
            $aData['point'] = 0;
        }
        $nLvId=$aData['member_lv_id'];
        $aRs = $this->db->query("SELECT * FROM sdb_member_lv WHERE member_lv_id=".intval($nLvId));
        $sSql = $this->db->getUpdateSql($aRs,$aData);
        return (!$sSql || $this->db->query($sSql));
    }
    function checkLevel($aData,$action,$returnlv=0){
        if($action == 'INSERT'){
            $sql = "select member_lv_id from sdb_member_lv where name=".$this->db->quote($aData['lv_name']);
        }
        if($action == 'UPDATE'){
             $sql = "select member_lv_id from sdb_member_lv where name=".$this->db->quote($aData['name'])." and member_lv_id <> ".intval($aData['member_lv_id']);
        }
        if($row=$this->db->selectRow($sql)){
            if ($returnlv)
                return $row['member_lv_id'];
            else
                return true;
        }else{
            return false;
        }
    }
    /**
    * 检查是否存在默认等级
    */
    function checkMlevel($aData,$action){
        if(!empty($aData['default_lv'])){
            if($action == 'INSERT'){
                $sql = "select member_lv_id from sdb_member_lv WHERE default_lv='1' ";
            }
            if($action =='UPDATE'){
                $sql = "select member_lv_id from sdb_member_lv WHERE default_lv='1' and member_lv_id <> ".intval($aData['member_lv_id']);
            }
      if($this->db->selectRow($sql)){
                return true;
            }else{
                return false;
            }
        }else{
            return false;
        }
    }
    function insertLevel($aData,&$message){
        if($aData['lv_type'] == 'wholesale'){
            $aData['point'] = 0;
        }
        
        $aData['name'] =  $aData['lv_name'];
        $aRs = $this->db->query("SELECT * FROM sdb_member_lv WHERE member_lv_id=0");
        $sSql = $this->db->getInsertSql($aRs,$aData);
        return (!$sSql || $this->db->query($sSql));
    }

    function getDefauleLv(){
        $aTemp = $this->db->selectRow("SELECT member_lv_id FROM sdb_member_lv WHERE default_lv='1'");
        return $aTemp?$aTemp['member_lv_id']:'';
    }

    function checkField($sField,$sTable,$sWhere=''){
        //echo "SELECT $sField FROM ".$sTable.' '.$sWhere.'<br>';
        return $this->db->selectRow("SELECT $sField FROM ".$sTable.' '.$sWhere);

    }
    function delLevel($aLvId){
        $sSql = 'DELETE FROM sdb_member_lv';
        if(count($aLvId)>0){
            $sSql .= ' WHERE member_lv_id IN ('.implode(',',$aLvId).')';
            $this->db->exec('DELETE FROM sdb_goods_lv_price WHERE level_id IN ('.implode(',',$aLvId).')');
            return $this->db->exec($sSql);
        }else{
            return false;
        }
    }
    function checkMemLvType($mMemId){ //查看会员的等级类型是批发会员等级还是普通会员等级，retail为零售会员，wholesale为批发会员等级
        $aRs = $this->db->selectrow('SELECT lv.lv_type FROM sdb_members m
                                                        LEFT JOIN sdb_member_lv lv
                                                        ON m.member_lv_id=lv.member_lv_id
                                                        WHERE m.member_id='.intval($mMemId));
        if($aRs){
            return $aRs['lv_type'];
        }
        return $aRs;
    }

}
?>
