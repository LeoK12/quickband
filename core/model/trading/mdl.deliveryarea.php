<?php
require_once('shopObject.php');

class mdl_deliveryarea extends shopObject{
    var $idColumn = 'area_id'; //表示id的列
    var $textColumn = 'area_id';
    var $defaultCols = 'name,ordernum';
    var $adminCtl = 'trading/deliveryarea';
    var $defaultOrder = array('ordernum','desc');
    var $tableName = 'sdb_dly_area';
    var $IdGroup=array();//子节点数组

    function getTreeSize(){
        $sql="select count(region_id) as rcount from sdb_regions";
        $row=$this->db->selectrow($sql);
        if ($row['rcount']>100)
            return true;
        else
            return false;
    }
    function getById($regionId=''){
        return $this->db->selectrow("select local_name from sdb_regions where region_id=".intval($regionId));
    }
    function getRegionById($regionId=''){
        $sql='select region_id,p_region_id,local_name,ordernum,region_path from sdb_regions as r where r.p_region_id'.($regionId?('='.intval($regionId)):' is null').' order by ordernum asc,region_id asc';
        $aTemp=$this->db->select($sql);
        if (is_array($aTemp)&&count($aTemp)>0){
            foreach($aTemp as $key => $val){
                $aTemp[$key]['p_region_id']=intval($val['p_region_id']);
                $aTemp[$key]['step'] = intval(substr_count($val['region_path'],','))-1;
                $aTemp[$key]['child_count'] = $this->getChildCount($val['region_id']);
            }
        }
        return $aTemp;
    }
    function getChildCount($region_id){
        $row = $this->db->selectrow('select count(*) as childCount from sdb_regions where p_region_id='.intval($region_id));
        return $row['childCount'];
    }
    function getRegionByParentId($parentId){
        $sql="select region_id,local_name from sdb_regions where region_id=".intval($parentId);
        return $this->db->selectrow($sql);
    }
    function toRemoveArea($regionId){
        /*
        $this->getAllChild($regionId);
        if (count($this->IdGroup)>0){
            foreach($this->IdGroup as $key => $val){
                $this->db->exec('DELETE FROM sdb_regions where region_id='.intval($val));
            }
        }  */
        $tmpRow = $this->db->selectrow("select region_path from sdb_regions where region_id=".intval($regionId));
        $this->db->exec("DELETE FROM sdb_regions where region_id=".intval($regionId));
        $this->toRemoveSubArea($tmpRow['region_path']);
        return true;
    }
    function toRemoveSubArea($path){
        if ($path)
            return $this->db->exec("DELETE FROM sdb_regions where region_path LIKE '%".$path."%'");
    }

    function getAllChild_ex($regionId){

        $sql="select region_path from sdb_regions where region_id=".intval($regionId);
        $tmpRow=$this->db->selectrow($sql);
        $sql="select region_id from sdb_regions where region_path like '%".$tmpRow['region_path']."%'";
        $row=$this->db->select($sql);
        if (is_array($row)&&count($row)>0){
            foreach($row as $key => $val){
                $region_Id[]=$val['region_id'];
            }
        }
        return $region_Id;
    }

    function getAllChild($regionId){
        /*
        $sql="select region_id from sdb_regions where p_region_id=".intval($regionId);
        $aTemp=$this->db->select($sql);
        if (is_array($aTemp)&&count($aTemp)>0){
            foreach($aTemp as $key => $val){
                $this->getAllChild($val['region_id']);
            }
        }
        $this->IdGroup[]=$regionId;   */
        unset($this->IdGroup);
        $sql="select region_path from sdb_regions where region_id=".intval($regionId);
        $tmpRow=$this->db->selectrow($sql);
        $sql="select region_id from sdb_regions where region_path like '%".$tmpRow['region_path']."%'";
        $row=$this->db->select($sql);
        if (is_array($row)&&count($row)>0){
            foreach($row as $key => $val){
                $this->IdGroup[]=$val['region_id'];
            }
        }
    }
    function updateOrderNum($param){
        if (is_array($param)&&count($param)>0){
            foreach($param as $key => $val){
                $val=$val?$val:'50';
                $this->db->exec('UPDATE sdb_regions set ordernum='.intval($val).' where region_id='.intval($key));
            }
        }
        return true;
    }
    function insertDlArea($aData,&$msg){
        if(!trim($aData['local_name'])){
            $msg = __('地区名称不能为空！');
            return false;
        }
        $aData['ordernum']=$aData['ordernum']?$aData['ordernum']:'50';
        if($this->checkDlArea($aData['local_name'],$aData['p_region_id'])){
            $msg = __('该地区名称已经存在！');
            return false;
        }
        $tmp = $this->db->selectrow('select region_path from sdb_regions where region_id='.intval($aData['p_region_id']));
        if (!$tmp)
            $tmp['region_path'] = ",";
        $aData = array_filter($aData);
        $aRs = $this->db->query('SELECT * FROM sdb_regions WHERE 0');
        $sSql = $this->db->GetInsertSql($aRs,$aData);
        if ($this->db->exec($sSql)){
           $regionId = $this->db->lastInsertId();
           $tmp['region_path'] = $tmp['region_path'].$regionId.',';
           $tmp['region_grade'] = count(explode(",",$tmp['region_path']))-2;
           $this->db->exec($sql='UPDATE sdb_regions set region_path='.$this->db->quote($tmp['region_path']).',region_grade='.intval($tmp['region_grade']).' where region_id='.intval($regionId));
            return true;
        }
        else
            return false;
    }
    function getGroupRegionId($regionId){
       $row = $this->db->selectrow($sql='select region_path from sdb_regions where region_id='.intval($regionId));
       $path=$row['region_path'];
       $rows = $this->db->select($sql="select region_id from sdb_regions where region_path like '%".$path."%' and region_id<>".intval($regionId));
       if ($rows){
           foreach($rows as $key => $val){
               $idGroup[]=$val['region_id'];
           }
           return $idGroup;
       }
    }
     function updateDlArea($aData,&$msg){
        if ($aData['region_id']==$aData['p_region_id']){
            $msg = __('上级地区不能为本地区！');
            return false;
        }
        else{
            $idGroup = $this->getGroupRegionId($aData['region_id']);
            if (in_array($aData['p_region_id'],$idGroup)){
                $msg = __('上级地区不能为本地区的子地区！');
                return false;
            }
        }
        if(!$aData['region_id']){
            $msg = __('参数丢失！');
            return false;
        }
        else{
            $cPath=$this->db->selectrow('select region_path from sdb_regions where region_id='.intval($aData['region_id']));
        }
        if(!trim($aData['local_name'])){
            $msg = __('地区名称不能为空！');
            return false;
        }
        if (intval($aData['p_region_id'])){
            $tmp = $this->db->selectrow('select region_path from sdb_regions where region_id='.intval($aData['p_region_id']));
            $aData['region_path'] = $tmp['region_path'].$aData['region_id'].",";
        }
        else{
            $aData['region_path'] = ",".$aData['region_id'].",";
        }
        $aData['ordernum']=$aData['ordernum']?$aData['ordernum']:'50';
        $aData['region_grade'] = count(explode(",",$aData['region_path']))-2;
        $aData = array_filter($aData);
        $aRs = $this->db->query('SELECT * FROM sdb_regions WHERE region_id='.$aData['region_id']);
        $sSql = $this->db->GetUpdateSql($aRs,$aData);
        $this->updateSubPath($cPath['region_path'],$aData['region_path']);
        return (!$sSql || $this->db->exec($sSql));
    }
    function updateSubPath($Opath,$Npath){
        $offset = count(explode(",",$Npath)) - count(explode(",",$Opath));
        return $this->db->exec("update sdb_regions set region_path=replace(region_path,".$this->db->quote($Opath)
            .",".$this->db->quote($Npath)."),region_grade=region_grade + "
            .intval($offset)." where region_path LIKE '%".$Opath."%'");
    }
    function getDlAreaById($aRegionId){
        /*return $this->db->selectrow('SELECT * FROM sdb_dly_area WHERE area_id='.intval($aAreaId));*/
        $sql='select c.region_id,c.local_name,c.p_region_id,c.ordernum,p.local_name as parent_name from sdb_regions as c LEFT JOIN sdb_regions as p ON p.region_id=c.p_region_id where c.region_id='.intval($aRegionId);
        return $this->db->selectrow($sql);
    }
    function checkDlArea($name,$p_region_id){
        /*$aTemp = $this->db->selectrow("SELECT area_id FROM sdb_dly_area WHERE name='".$sName."' order by ordernum desc");
        return $aTemp['area_id']; */
        $aTemp = $this->db->selectrow("SELECT region_id FROM sdb_regions WHERE local_name='".$name."' and p_region_id".($p_region_id?('='.intval($p_region_id)):' is null'));
        return $aTemp['region_id'];

    }
    function getMap($prId=''){
        if ($prId)
            $sql="select region_id,region_grade,local_name,ordernum,(select count(*) from sdb_regions where p_region_id=r.region_id) as child_count from sdb_regions as r where r.p_region_id=".intval($prId)." order by ordernum asc,region_id";
        else
            $sql="select region_id,region_grade,local_name,ordernum,(select count(*) from sdb_regions where p_region_id=r.region_id) as child_count from sdb_regions as r where r.p_region_id is null order by ordernum asc,region_id";
        $row = $this->db->select($sql);
        foreach($row as $key => $val){
            $this->regions[]=array(
                "local_name"=>$val['local_name'],
                "region_id"=>$val['region_id'],
                "region_grade"=>$val['region_grade'],
                "ordernum"=>$val['ordernum']
            );
            if ($val['child_count'])
                $this->getMap($val['region_id']);
        }
    }
}
?>