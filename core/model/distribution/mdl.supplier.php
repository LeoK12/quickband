<?php
include_once('shopObject.php');

class mdl_supplier extends shopObject{
    
    var $idColumn = 'supplier_id';
    var $textColumn = 'supplier_brief_name';
    var $defaultCols = 'sp_id,supplier_id,supplier_brief_name,status,supplier_pline_id,sync_time';
    var $appendCols = '';
    var $adminCtl = 'distribution/supplier';
    var $defaultOrder = array('has_new',' ASC',',sp_id',' DESC');
    var $tableName = 'sdb_supplier';
    var $typeName = 'supplier';

    function getColumns($filter){

        $columns = array(
            'sp_id'=>array('label'=>'id','class'=>'span-3','fuzzySearch'=>1,'required'=>true, 'primary' => true), 
            'supplier_id'=>array('label'=>'供应商id','class'=>'span-8','fuzzySearch'=>1,'required'=>true, 'primary' => true),  
            'supplier_brief_name'=>array('label'=>'供应商简称','class'=>'span-3' ), 
            'status'=>array('label'=>'分销状态','class'=>'span-2','readonly'=>true),
            'supplier_pline_id'=>array('label'=>'产品线id','class'=>'span-2'),  
            'sync_time'=>array('label'=>'同步时间','class'=>'span-2','readonly'=>true),  
        );
        return $columns;
    }

    function _filter($filter){
        $where = array(1);
        if( $filter['supplier_brief_name'] ){
            $where[] = ' supplier_brief_name LIKE "%'.$filter['supplier_brief_name'].'%" ';
        }
        return implode($where, ' AND ');
    }

    function updateSupplierHasNew($supplierId){
        $data = array(
            'has_new' => 'false'
        );
        $rs = $this->db->exec('SELECT * FROM sdb_supplier WHERE supplier_id = '.$supplierId);
        $sql = $this->db->getUpdateSql($rs,$data);
        return $this->db->exec($sql);
    }
    function updateSupplierSynctime($supplierId, $time=null){
        $data = array(
            'sync_time' => ($time==null?time():$time)
        );
        $rs = $this->db->exec('SELECT * FROM sdb_supplier WHERE supplier_id = '.$supplierId);
        $sql = $this->db->getUpdateSql($rs,$data);
        return $this->db->exec($sql);
    }

    function getSyncDataList($supplierId, $searchData, &$count, $page=1,$limit=20){
        $isTableExist = $this->db->select('SHOW TABLES LIKE "'.$this->db->prefix.'data_sync_'.$supplierId.'" ');
        if( empty( $isTableExist ) )
            return null;
        $where = '';
        //选择更新内容
        switch( $searchData['update_content'] ){
            case '1':   //新增商品
                $where = ' AND type = "goods" AND command = 6 ';
                break;
            case '2':   //更新商品
                $where = ' AND type = "goods" AND command = 4 ';
                break;
            case '3':   //商品上架
                $where = ' AND type = "goods" AND command = 1 AND marketable="true" ';
                break;
            case '4':   //货品库存紧张
                $where = '';
                break;
            case '5':   //货品库存变更
                $where = ' AND type = "product" AND command = 2 AND store>0 ';
                break;
            case '6':   //货品库存为0
                $where = ' AND type = "product" AND command = 2 AND store=0 ';
                break;
            case '7':   //商品下架
                $where = ' AND type = "goods" AND command = 1 AND marketable="false" ';
                break;
            case '8':   //商品删除
                $where = ' AND type = "goods" AND command = 7 ';
                break;
            case '9':   //更新货品
                $where = ' AND type = "goods" AND command = 5 ';
                break;
            case '10':   //商品图片更新
                $where = ' AND type = "goods" AND command = 3 ';
                break;
        }

        //选择操作状态
        switch( $searchData['ctrl_status'] ){
            case '1':   //已下载未编辑
                $where .= ' AND command = 6 AND status = "unmodified" ';
                break;
            case '2':   //未操作
                $where .= ' AND status = "unoperated" ';
                break;
            case '3':   //上游新品到货，我未新增
                $where .= ' AND command = 6 AND status = "unoperated" ';
                break;
            case '4':   //上游更新商品信息，我未更新
                $where .= ' AND command = 4 AND statuts = "unmodified" ';
                break;
            case '5':   //上游恢复供货，我未上架
                $where .= ' AND command = 1 AND marketable="true" AND status = "unoperated" ';
                break;
            case '6':   //上游库存状态变化，我未同步
                $where .= ' AND command = 2 AND status = "unoperated" ';
                break;
            case '7':   //上游停止供货，我未下架
                $where .= ' AND (command = 7 OR (command = 1 AND marketable="false")) AND status = "unoperated" ';
                break;
            case '8':   //已操作
                $where .= ' AND status = "done" ';
                break;
            case '9':   //已新增
                $where .= ' AND status = "done" AND command = 6 ';
                break;
            case '10':  //已上架
                $where .= ' AND marketable="true" AND command = 1 AND status = "done" ';
                break;
            case '11':  //已同步库存
                $where .= ' AND command = 2 AND status = "done" ';
                break;
            case '12':  //已下架
                $where .= ' AND marketable="false" AND command = 1 AND status = "done" ';
                break;
            case '13':  //已删除
                $where .= ' AND stauts = "done" AND command = 7 ';
                break;
            case '14':  //已更新
                $where .= ' AND status = "done" AND command = 4 ';
                break;
        }
        if( $searchData['s_update_time'] )
            $where .= ' AND last_modify >= '.$searchData['s_update_time'];
        if( $searchData['e_update_time'] )
            $where .= ' AND last_modify <= '.($searchData['e_update_time']+3600*24);
        if( $searchData['search_name'] ){
            $where .= ' AND (bn="'.$searchData['search_name'].'" OR name LIKE "%'.$searchData['search_name'].'%" )';
        }
        $count = $this->db->selectrow('SELECT COUNT(command_id) AS c FROM sdb_data_sync_'.$supplierId.' WHERE if_show = "true" '.$where);
        $count = $count['c'];
        $slist = $this->db->select('SELECT * FROM sdb_data_sync_'.$supplierId.' WHERE if_show = "true" '.$where.' ORDER BY last_modify DESC LIMIT '.(($page-1)*$limit).', '.$limit);
        $oDatasync = $this->system->loadModel('distribution/datasync');
        foreach($slist as $k => $v){
            if( $v['command'] == '4' ){
                $slist[$k]['show_download'] = $oDatasync->checkGoodsDownload($supplierId, $v['object_id'])?1:0;
            }
            $slist[$k]['command_info'] = unserialize($v['command_info']);
            if($v['command'] == '1'){
                $slist[$k]['command_type'] = $v['command'] . "-" . ($v['marketable']=='true'?'1':'2');
            }else{
                $slist[$k]['command_type'] = $v['command'];
            }
        }
        return $slist;
    }

    /*
    function updatePlineInSupplier($supplierId, $plineId){
        $pline = $this->db->selectrow('SELECT supplier_pline_id FROM sdb_supplier WHERE supplier_id = '.$supplierId);
        $newPline = array();
        if( $pline['supplier_pline_id'] ){
            foreach( explode($pline['supplier_pline_id']) as $v ){
                $newPline[] = $v;
            }
        }
        foreach( $plineId as $nv ){
            if( !in_array( $pv,$newPline ) )
                $newPline[] = $nv;
        }
        $rs = $this->db->exec('SELECT * FROM sdb_supplier WHERE supplier_id = '.$supplierId);
        $data = array('supplier_pline_id'=>implode(',',$newPline));
        $sql = $this->db->getUpdateSQL($rs, $data);
        $this->db->exec($sql);
    }
     */
    function updateSyncStatus($commandId,$supplierId,$status){
        $rs = $this->db->exec('SELECT * FROM sdb_data_sync_'.$supplierId.' WHERE command_id = '.$commandId);
        $data = array( 'status'=>$status );
        $sql = $this->db->getUpdateSQL($rs,$data);
        return $this->db->exec($sql);
    }

    function getCommandInfo($commandId, $supplierId){
        $sql = 'SELECT command_info FROM sdb_data_sync_'.$supplierId.' WHERE command_id = '.$commandId;
        $rs = $this->db->selectrow($sql);
        return unserialize( $rs['command_info'] );
    }

    function updateGoodsMarketable($supplierId,$supplierGoodsId,$marketAble){
        $data = array('marketable'=>$marketAble);
        $sql = 'SELECT * FROM sdb_goods WHERE supplier_id = '.$supplierId.' AND supplier_goods_id = '.$supplierGoodsId;
        $rs = $this->db->exec($sql);
        $sql = $this->db->getUpdateSQL($rs, $data);
        return $this->db->exec($sql);
    }
    function removeGoods($supplierId,$supplierGoodsId){
        $goodsId = $this->db->selectrow('SELECT goods_id FROM sdb_goods WHERE supplier_id = '.$supplierId.' AND supplier_goods_id = '.$supplierGoodsId);
        $goodsId = $goodsId['goods_id'];
        $this->db->exec('UPDATE sdb_goods SET disabled = "true" WHERE goods_id = '.$goodsId);
        $objProduct = $this->system->loadModel('goods/products');
        return $objProduct->setDisabled(array($goodsId), 'true');
    }

    function getSupplierInfo( $supplierId, $col = '*' ){
        return $this->db->selectrow('SELECT '.$col.' FROM sdb_supplier WHERE supplier_id = '.$supplierId);
    }
    
    function getLocalGoodsId( $supplierId, $objectId ){
        $sql = 'SELECT goods_id FROM sdb_goods WHERE supplier_id = '.$supplierId.' AND supplier_goods_id = '.$objectId;
        $rs = $this->db->selectrow($sql);
        return $rs['goods_id'];
    }

    function getSourceBnByLocalBn( $localBn ){
        $bn = $this->db->selectrow('SELECT source_bn FROM sdb_supplier_pdtbn WHERE local_bn = "'.$localBn.'"');
        return $bn['source_bn'];
    }
    
    /**
     * 更新SupplierPdtBn表
     *
     * @param array $newBns
     * @param array $delBns
     * @param int $supplier_id
     */
    function updateSupplierPdtBn($newBns,$delBns,$supplier_id){
        foreach( $newBns as $oldBn => $newBn ){
            // 不修改原来的bn 而是增加对应关系 解决老订单的商品询价的问题 2010-01-11 16:12 wubin 
            //$this->db->exec('UPDATE sdb_supplier_pdtbn SET local_bn = "'.$newBn.'" WHERE local_bn = "'.$oldBn.'"');
            $srcBn = $this->getSourceBnByLocalBn($oldBn);
            $this->db->exec('INSERT INTO sdb_supplier_pdtbn(local_bn,source_bn,supplier_id) VALUES("'.$newBn.'","'.$srcBn.'","'.$supplier_id.'")');
        }
        if(!empty($delBns)){
            $this->db->exec('DELETE sdb_supplier_pdtbn WHERE local_bn IN ("'.implode('","',array_keys($delBns)).'")');
        }
    }

    function getDoSyncJobList(){
        $sql = 'SELECT supplier_id FROM sdb_job_data_sync GROUP BY supplier_id';
        $rs = $this->db->select($sql);
        $ret = array();
        foreach( $rs as $v )
            $ret[] = $v['supplier_id'];
        return $ret;
    }

    function filterSupplierList(&$list){
        foreach( $list as $k => $v ){
            $sql = 'SELECT job_id FROM sdb_job_data_sync WHERE supplier_id = '.$v['supplier_id'];
            $rs = $this->db->selectrow($sql);
            if( $rs ){
                $list[$k]['sync_loading'] = 'true';
            }
        }
    }
/*
    function checkImgDownload($commandId){
        $sql = 'SELECT img_sync_id FROM sdb_image_sync WHERE failed = "true" AND command_id = '.$commandId;
        $rs = $this->db->selectrow($sql); 
        if( !empty($rs) )
            return 'true';
        return 'false';
    }
*/
    function updateGoodsImageFailed($commandId,$supplierId){
        $sql = 'UPDATE sdb_image_sync SET failed = "false" WHERE failed = "true" AND command_id = '.$commandId;
        $this->db->exec($sql);
        
        $sql = "UPDATE sdb_sdb_data_sync_".$supplierId." SET img_down_failed='false' WHERE img_down_failed='true' AND command_id=".$commandId;
        $this->db->exec($sql);
    }

    function getSupplierApiList(){
        $sql = 'SELECT supplier_id FROM sdb_job_apilist GROUP BY supplier_id';
        $rs = $this->db->select($sql);
        $res = array();
        foreach( $rs as $k=>$v ){
            $res[$k]['supplier_id'] = $v['supplier_id'];
        }
        return $res;
    }
    
    /**
     * 执行该supplier相关的api列表任务
     * 根据api_action和api_name的不同，执行不同的动作
     *
     * @param int $supplier_id
     * @param string $api_name
     * @param string $api_action
     * 
     * @return int 1:有任务执行，0:没有任务要执行
     */
    function doSupplierApiListJob($supplier_id,$api_name=NULL,$api_action=NULL){
        $sql = "SELECT * FROM sdb_job_apilist WHERE supplier_id=".floatval($supplier_id);
        if(!empty($api_name)){
            $sql .= " AND api_name='".$api_name."'";
        }
        if(!empty($api_action)){
            $sql .= " AND api_action='".$api_action."'";
        }
        
        $api_job = $this->db->selectrow($sql);
        
        if(!empty($api_job)){
            if(empty($api_name)){
                $api_name = $api_job['api_name'];
            }
            if(empty($api_action)){
                $api_action = $api_job['api_action'];
            }
            
            switch($api_action){
                case 'distribution/datasync|filterUpdateList_1' :
                    if($api_name == 'getGoodsIdByPline'){
                        $api_params = unserialize($api_job['api_params']);
                        $command_type = $api_params['command_type'];
                        unset($api_params['command_type']);
                        $rs = $this->db->query("SELECT * FROM sdb_job_apilist WHERE job_id=".$api_job['job_id']);
                        $sql = $this->db->GetUpdateSQL($rs,array('api_params'=>serialize($api_params)));
                        $this->db->exec($sql);
                        
                        $oDataSync = $this->system->loadModel('distribution/datasync');
                        $oDataSync->filterUpdateList_2($supplier_id,$command_type);
                    }
                    break;
                case 'distribution/datasync|addSyncTmpData' :
                    if($api_name == 'getBrands' || $api_name == 'getTypes' || $api_name == 'getSpecifications' || $api_name == 'getCategories'){
                        $oDataSync = $this->system->loadModel('distribution/datasync');
                        $oDataSync->doSyncTmpData($supplier_id,$api_name);
                    }
                    break;
                case 'distribution/datasync|doCostSyncJob' :
                    $oCostSync = $this->system->loadModel('distribution/costsync');
                    $oCostSync->doCostSyncJob($supplier_id);
                    break;
            }
            return 1;
        }else{
            return 0;
        }
    }

    /**
     * 删除临时表的数据
     *
     * @param int $supplier_id
     */
    function clearTmpData($supplier_id){
        $this->db->exec("DELETE FROM sdb_sync_tmp WHERE supplier_id=".floatval($supplier_id));
    }
    
    /**
     * 取消指定供应商的任务(删除) 2009-11-10 11:57 wubin
     * 
     * @param int $supplier_id
     */
    function cancelTask($supplier_id) {
        $sSql = "DELETE FROM sdb_job_apilist WHERE supplier_id='".$supplier_id."'";
        $this->db->exec($sSql);
        
        $sSql = "DELETE FROM sdb_job_data_sync WHERE supplier_id='".$supplier_id."'";
        $this->db->exec($sSql);
        
        $this->clearTmpData($supplier_id);
        
        $sSql = "DELETE FROM sdb_autosync_task WHERE supplier_id='".$supplier_id."'";
        $this->db->exec($sSql);
    }
}

?>
