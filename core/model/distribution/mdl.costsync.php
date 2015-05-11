<?php
/**
 * mdl_costsync
 *
 * @uses modelFactory
 * @package
 * @version $Id: mdl.costsync.php 2009-09-10 10:08:30Z wubin $
 * @copyright 2003-2009 ShopEx
 * @author wubin <wubin@shopex.cn>
 * @license Commercial
 */


class mdl_costsync extends modelFactory{
    // 每次同步任务的条数
    var $jobLimit = 100;
    
    /**
     * 成本价同步
     * 
     * @access public
     * @return void
     * @todo  同步 (B2B的销售价*所在会员等级的折扣率) 做为同步下来的货品成本价,如果存在与B2B绑定的会员级别设定价格,则使用设定价格
     */
    function mdl_costsync(){
        parent::modelFactory();
        $token = $this->system->getConf('certificate.token');
        $this->api = $this->system->api_call(PLATFORM,PLATFORM_HOST,PLATFORM_PATH,PLATFORM_PORT,$token);
    }
    
    /**
     * 获取要提交的的API参数(配置使用)
     *
     * @param  int     $supplier_id // 供应商license
     * @param  boolean $isAdd    // 是否是添加时的处理
     * @return array(
     *            'api_name'     => 'xxx',   // 提交平台的api名称
     *            'api_version'  => 'xxx',   // 当前API版本
     *            'api_params'   => array(   // 提交api时,要附带的参数
     *                                 'id'     =>  'xxx',  // 供应商license
     *                                 'version' => 'xxx',  // 要获取数据的版本号 查看平台API3.0 getMemberPrice文档
     *                              ),
     *            'api_action'   => 'xxx',   // api动作 不同处理的标识
     *            'limit'        => 'xxx',   // 每次要获取的数据量    
     *         )
     */
    function getApiParams($supplier_id = 0,$isAdd = false) {
        $aApiParams = array(
                    'api_name'    => 'getMemberPrice',
                    'api_version' => API_VERSION,
                    'api_params'  => array(),
                    'api_action'  => 'distribution/costsync|doCostSyncJob',
                    'limit'       => $this->jobLimit
                 );
                 
        // 如果版本号不为0,说明本地没有同步成本价数据,所以使用0获取全部的数据
        if($isAdd) {
            $version_id = $this->getMaxVersionId($supplier_id);
                  
            $aApiParams['api_params']['version'] = $version_id;
            $aApiParams['api_params']['id']      = $supplier_id;
        }
        
        return $aApiParams;
    }
    
    /**
     * 请求平台上的成本价信息
     *
     * @param int $supplier_id   // 供应商licenseid
     * @param int $pages         // 当前页数
     * @param int $limit         // 每页数
     * @param int $version_id    // 版本号
     * @return array(
     *             0=>array(
     *                 'version'   => 'xxx',  // 版本号
     *                 'row_count' => 'xxx',  // 数据总量
     *                 'bn'        => 'xxx',  // 供应商货品bn
     *                 'price'     => 'xxx',  // 供应商货品成本价(对应于分销商的会员等级)
     *             ),
     *             ......
     *         )
     */
    function getCostSync($supplier_id,$pages = 1,$limit = 0,$version_id = 0){
         $aApiParams = $this->getApiParams($supplier_id,true);
         
         // 每页数
         if(empty($limit)) $limit = $aApiParams['limit'];
         
         // 版本号
         if(empty($version_id)) $version_id = $aApiParams['api_params']['version'];
         
         return $this->api->getApiData($aApiParams['api_name'],$aApiParams['api_version'],array('pages'=>$pages,'counts'=>$limit,'id'=>$supplier_id,'version'=>$version_id),true,true);
    }
    
    /**
     * 获取指定供应商需要同步的数据总量
     *
     * @param  int $supplier_id  // 供应商license
     * @return int 
     */
    function getCostSyncCount($supplier_id) {
        $aList = $this->getCostSync($supplier_id,1,1);
        if(is_array($aList)) {
            return intval($aList[0]['row_count']);
        } else {
            return 0;
        } 
    }
    
    /**
     * 生成指定供应商的同步任务
     * 
     * @param int $supplier_id
     * @return void
     */
    function generateCostSyncJob($supplier_id) {
        $oSyncJob = $this->system->loadModel("distribution/syncjob");
        $aApiParams = $this->getApiParams($supplier_id,true);
        
        // sdb_job_apilist
        $oSyncJob->addApiListJob($supplier_id,$aApiParams['api_name'],$aApiParams['api_params'],$aApiParams['api_version'],$aApiParams['api_action'],$aApiParams['limit']);
    }
    
    /**
     * 获取一条指定供应商的同步任务 (返回的是平台上的数据 2009-09-15)
     *
     * @param int $supplier_id
     * @return array
     */
    function getCostSyncJob($supplier_id) {
        $oSyncJob   = $this->system->loadModel('distribution/syncjob');
        $aApiParams = $this->getApiParams($supplier_id);
        return $oSyncJob->doApiListJob($supplier_id,$aApiParams['api_name'],$aApiParams['api_version'],$aApiParams['api_action']);
    }
    
    /**
     * 获取指定供应商的同步任务数
     *
     * @param int $supplier_id
     * @return int
     */
    function getCostSyncJobCount($supplier_id) {
        $aApiParams = $this->getApiParams($supplier_id);
        $aList = $this->db->selectrow("SELECT count(*) AS num FROM sdb_job_apilist WHERE supplier_id = '".$supplier_id."' AND api_name='".$aApiParams['api_name']."' ");
        return $aList['num'];
    }
    
    /**
     * 做指定供应商的成本价同步任务
     *
     * @param int $supplier_id
     * @return string (done|continue)
     */
    function doCostSyncJob($supplier_id) {
        $version_id = $this->getCostSyncJobVersion($supplier_id);
        $aList = $this->getCostSyncJob($supplier_id);
        
        if(empty($aList)) {
            $this->updateCostSyncInfo($supplier_id);  // 更新成本价同步信息(goods_id & product_id)
            $this->updateProductCost($supplier_id);   // 更新货品成本价
            $this->updateGoodsCost($supplier_id);     // 更新商品成本价
            return "done";
        }
        
        foreach($aList as $row) {
            $aData = array(
               'supplier_id' => $supplier_id,
               'bn'          => $row['bn'],
               'version_id'  => $row['version'],
               'cost'        => doubleval($row['price'])
            );
            
            $this->addCostSync($aData);
        }
        
        // 更新同步版本号 2009-09-27 18:09:42 wubin
        $this->updateCostSyncVersion($supplier_id,$version_id);
        
        return "continue";
    }
    
    /**
     * 更新成本价同步版本号
     *
     * @param int $supplier_id
     * @param int $version_id
     * @return boolean
     */
    function updateCostSyncVersion($supplier_id,$version_id){
        $max_version_id = $this->getMaxVersionId($supplier_id);
        $rs = $this->db->exec("SELECT * FROM sdb_cost_sync WHERE supplier_id=".$supplier_id." AND version_id > '".$version_id."'");
        $sSql = $this->db->GetUpdateSql($rs,array('version_id'=>$max_version_id));
        return $this->db->exec($sSql);
    }
    
    /**
     * 获取此次同步成本价的版本号信息
     *
     * @param  int $supplier_id
     * @return int
     */
    function getCostSyncJobVersion($supplier_id) {
        $aApiParams = $this->getApiParams();
        $sSql = "SELECT api_params FROM sdb_job_apilist WHERE api_name='".$aApiParams['api_name']."' AND supplier_id = '".$supplier_id."'";
        $aResult = $this->db->selectrow($sSql);
        if(empty($aResult)) return 0;
        $aResult = unserialize($aResult['api_params']);
        return $aResult['version'];
    }
    
    /**
     * 增加一条成本价同步数据
     *
     * @param array $data // array(
     *                          'supplier_id' => 'xxx',  // 供应商license
     *                          'bn'          => 'xxx',  // 货品bn号 对应于 sdb_supplier_pdtbn.source_bn
     *                          'version_id'  => 'xxx',  // 版本号
     *                          'cost'        => 'xxx'   // 成本价
     *                       ) 
     * @return boolean
     */
    function addCostSync($data){
        $sSql = "REPLACE INTO sdb_cost_sync(`supplier_id`,`bn`,`version_id`,`cost`) 
                 VALUES('".$data['supplier_id']."','".$data['bn']."','".$data['version_id']."','".$data['cost']."')";
        return $this->db->exec($sSql);
    }
    
    /**
     * 返回定价时的现显字段(必须包含cost字段)
     *
     * @param string $str
     * @return string
     */
    function getCostSyncCols($str) {
        if(strpos($str,'cost')) return $str;
        
        // 如果存在市场价
        if(strpos($str,'mktprice')) return str_replace('mktprice','mktprice,cost',$str);
        // 如果存在类型
        if(strpos($str,'type_id')) return str_replace('type_id','type_id,cost',$str);
        // 如果存在分类
        if(strpos($str,'cat_id')) return str_replace('cat_id','cat_id,cost',$str);
        
        return str_replace('name','name,cost',$str);
    }
    
    /**
     * 获取最大的版本号
     *
     * @param int     $supplier_id
     * @param boolean $isexist     // 必须是同步到本地了商品(用于定价使用,goods_id !=0 goods_id不能关联上的)
     * @return int
     */
    function getMaxVersionId($supplier_id,$isexist = false) {
        $where = ($isexist)? ' AND goods_id <> 0' : '';
        $aList = $this->db->selectrow("SELECT MAX(version_id) AS max_version_id FROM sdb_cost_sync WHERE supplier_id = '".$supplier_id."'".$where);
        return intval($aList['max_version_id']);
    }
    
    /**
     * 获取本地指定版本号的的成本价同步记录数(getCostSyncCount 是从平台获取需要同步的数据量)
     *
     * @param int $supplier_id
     * @param int $version_id
     * @return int
     */
    function getCostSyncAmount($supplier_id,$version_id = null) {
        $where[] = " supplier_id = '".$supplier_id."' ";
        
        if($version_id != null) {
            $where[] = " version_id = '".$version_id."' ";
        }
        
        $sSql = "SELECT count(*) AS num FROM sdb_cost_sync WHERE ".implode(' AND ',$where);
        $aResult = $this->db->selectrow($sSql);
        return intval($aResult['num']);
    }
    
    /**
     * 更新价格同步信息(使用sdb_supplier_pdtbn 来更新 goods_id 和product_id )
     *
     * @param int $supplier_id
     * @param int $goods_id   
     * @return boolean
     */
    function updateCostSyncInfo($supplier_id,$goods_id = 0) {
        $sWhere = ($goods_id)? ' AND p.goods_id = \''.$goods_id.'\' ' : '';
        $sSql = "UPDATE sdb_cost_sync AS c, sdb_products AS p,sdb_supplier_pdtbn AS s 
                 SET c.goods_id = p.goods_id,c.product_id = p.product_id 
                 WHERE s.supplier_id = c.supplier_id AND p.bn = s.local_bn AND c.bn = s.source_bn AND c.supplier_id = '".$supplier_id."'".$sWhere;
        return $this->db->exec($sSql);
    }
    
    /**
     * 更新同步货品的成本价(更新表 sdb_products)
     *
     * @param int $supplier_id
     * @param int $goods_id
     * @param int $version_id
     * @return boolean
     */
    function updateProductCost($supplier_id,$goods_id = 0,$version_id = 0) {
        $sWhere = ($goods_id)? ' AND p.goods_id = \''.$goods_id.'\' ' : '';
        
        if(empty($version_id)) $version_id = $this->getMaxVersionId($supplier_id);
        
        $sSql = "UPDATE sdb_products AS p,sdb_cost_sync AS c 
                 SET p.cost = c.cost 
                 WHERE p.product_id = c.product_id AND c.supplier_id = '".$supplier_id."' AND c.version_id = '".$version_id."'".$sWhere;
        return $this->db->exec($sSql);
    }
    
    /**
     * 更新同步货品的成本价(更新sdb_goods)
     *
     * @param int $supplier_id
     * @param int $goods_id
     * @param int $version_id
     * @return boolean
     */
    function updateGoodsCost($supplier_id,$goods_id = 0,$version_id = 0) {
        $sWhere = ($goods_id)? ' AND g.goods_id = \''.$goods_id.'\' ' : '';
        
        if(empty($version_id)) $version_id = $this->getMaxVersionId($supplier_id);
        
        $sSql = "UPDATE sdb_cost_sync AS c,sdb_goods AS g
                 SET g.cost = c.cost 
                 WHERE g.goods_id = c.goods_id AND c.supplier_id = '".$supplier_id."' AND c.version_id = '".$version_id."'".$sWhere;
        return $this->db->exec($sSql);
    }
    
    /**
     * 更新单个货品成本价(用于询价时的处理)
     * @param int   $product_id
     * @param float $price
     * @return string 'succss|failure|pass'
     */
    function updateAloneProductCost($product_id,$price){
        // 判断是否需要更新货品成本价
        $aResult = $this->db->selectrow("SELECT goods_id,cost FROM sdb_products WHERE product_id='".$product_id."'");
        if($aResult['cost'] == $price) return "pass";
        
        // 更新货品成本价(sdb_products)
        $sSql = "UPDATE sdb_products SET cost='".$price."' WHERE product_id='".$product_id."'";
        if(!$this->db->exec($sSql)) return 'failure';
        
        // 判断是否需要更新商品成本价
        $aResult = $this->db->selectrow("SELECT goods_id,MIN(cost) AS cost FROM sdb_products WHERE goods_id = '".$aResult['goods_id']."' GROUP BY goods_id");
        if($aResult['cost'] < $price) return 'success';
        
        $sSql = "UPDATE sdb_goods SET cost='".$price."' WHERE goods_id='".$aResult['goods_id']."'";
        if(!$this->db->exec($sSql)) return 'failure';
        
        return 'success';
    }
    
    /**
     * 获取指定供应成本价同步状态
     *
     * @param int $supplier_id
     * @return array(
     *            'status'=>'xxxx',  // syncing(同步中),having(存在新的成本价更新),done(完成)
     *            'num'   =>'xxxx',  // 已同步个数
     *         )
     */
    function getCostSyncStatus($supplier_id) {
        // 是否同步过成本价(同步过成本价的个数)
        $aResult['num'] = $this->getCostSyncAmount($supplier_id);
        
        /* 不是实时去平台取平台数据 而是"刷新"后将是否有更新状态写入到sdb_supplier.has_cost_new 2010-01-12 17:15 wubin
        if($this->getCostSyncJobCount($supplier_id)) {// 是否正在同步(优先)
            $aResult['status'] = 'syncing';
        }else if ($this->getCostSyncCount($supplier_id)){ // 是否存在成本价更新
            $aResult['status'] = 'having'; 
        }else{
            $aResult['status'] = 'done'; 
        }*/
        
        return $aResult;
    }
    
    /**
     * 获取供应商成本价同步状态
     *
     * @param array $aSuppier // array(
     *                              0=>array(
     *                                  'supplier_id'=>'xxx',
     *                                   ...
     *                              ),
     *                              ......
     *                           ) 
     * @return array(
     *             0=>array(
     *                   'supplier_id'=>'xxx',
     *                   'cost_sync_status'
     *                    ...
     *              ),
     *              ......
     *         ) 
     */
    function getSupplierCostSyncStatus($aSupplier) {
        foreach($aSupplier as $key=>$row) {
            // 如果status!=1 说明没有正式成立分销关系 不做成本价是否有更新的处理
            if($row['sync_time_for_plat'] && ($row['status'] == 1)) {
                $aTemp = $this->getCostSyncStatus($row['supplier_id']);
                if($this->getCostSyncJobCount($row['supplier_id'])) { // 是否存在同步任务,存在则 存在同步
                    $aTemp['status'] = 'syncing';
                }else if($row['has_cost_new'] == 'true'){
                    $aTemp['status'] = 'having';
                }else {
                    $aTemp['status'] = 'done';
                }
                $aSupplier[$key]['costsync'] = $aTemp;
            }
        }
        return $aSupplier;
    }
    
    /**
     * 获取所有的价格同步任务
     * 
     * @return array(
     *            0=>array(
     *                  'supplier_id' => 'xxx', // 供应高license
     *                  'num'         => 'xx',  // 任务数 
     *               ),
     *            ...
     *         )
     */
    function getCostSyncList() {
        $aApiParams = $this->getApiParams();
        $sSql = "SELECT supplier_id,COUNT(*) AS num FROM sdb_job_apilist WHERE api_name='".$aApiParams['api_name']."' GROUP BY supplier_id";
        return $this->db->select($sSql);
    }
    
    /**
     * 获取更新完的成本价同的数量(本次同步与本地商品信息绑定了的数量)
     *
     * @param unknown_type $supplier_id
     */
    function getCostSyncDoneCount($supplier_id){
        $max_version_id = $this->getMaxVersionId($supplier_id);
        $sSql = "SELECT count(*) AS num FROM sdb_cost_sync WHERE supplier_id='".$supplier_id."' AND goods_id <> 0 AND version_id = '".$max_version_id."' GROUP BY goods_id";
        $aResult = $this->db->selectrow($sSql);
        return $aResult['num'];
    }
}
?>