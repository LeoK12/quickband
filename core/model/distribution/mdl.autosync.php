<?php
/**
 * mdl_autosync
 *
 * @uses shopObject
 * @package
 * @version $Id: mdl.autosync.php 2009-09-10 15:34:30Z wubin $
 * @copyright 2003-2009 ShopEx
 * @author wubin <wubin@shopex.cn>
 * @license Commercial
 */

require_once('shopObject.php');

class mdl_autosync extends shopObject{
    var $idColumn = 'rule_id'; //表示id的列
    var $textColumn = 'rule_name';
    var $defaultCols = '_cmd,rule_name,supplier_op_id,local_op_id,memo';
    var $adminCtl = 'dustribution/autosync';
    var $defaultOrder = array('rule_id','desc');
    var $tableName = 'sdb_autosync_rule';
    var $rule = null;
    
  function getColumns(){
        $ret = array(
            '_cmd'=>array('label'=>__('操作'),'width'=>70,'html'=>'distribution/auto_command.html'),
            'rule_id'=>array('label'=>__('配置编号'),'class'=>'span-3'),
            'rule_name'=>array('label'=>__('对象'),'class'=>'span-5',),
            'supplier_op_id'=>array('label'=>__('供应商操作'),'class'=>'span-5',),
            'local_op_id'=>array('label'=>__('本地操作'),'class'=>'span-5',),
            'memo'=>array('label'=>__('备注'),'class'=>'span-5'),
        );
        return array_merge(parent::getColumns(),$ret);
    }
   
    /**
     * 获取供应商所有操作
     *
     * @return array
     */
    function getSupplierOPList() {
        return array(
                1 => array(
                          'name'=>'商品上架',
                          'op_items'=>array(0,1),
                          'checked'=>1,
                       ),
               2 => array(
                          'name'=>'货品库存变更',
                          'op_items'=>array(0,4),
                          'checked'=>4,
                      ),
              3 => array(
                           'name'=>'商品图片更新',
                           'op_items'=>array(0,6),
                           'checked'=>6,
                      ), 
              4 => array(
                           'name'=>'商品更新',
                           'op_items'=>array(0,7),
                           'checked'=>7,
                     ),
              5 => array(
                          'name'=>'货品更新',
                          'op_items'=>array(0,5),
                          'checked'=>5,
                     ), 
              6 => array(
                          'name'=>'商品新增',
                          'op_items'=>array(0,8),
                          'checked'=>8,
                     ),
              7 => array(
                          'name'=>'商品删除',
                          'op_items'=>array(0,2,3),
                          'checked'=>2,
                     ), 
              8 => array(
                          'name'=>'商品下架',
                          'op_items'=>array(0,2),
                          'checked'=>2,
                     ),
              9 => array(
                          'name'=>'货品库存为0',
                          'op_items'=>array(0,4),
                          'checked'=>4,
                     ),
              10 => array(
                          'name'=>'中断产品线分销权限',
                          'op_items'=>array(0,2,3),
                          'checked'=>0,
                     ),
        );
    }
    
    /**
     * 供应商操作信息
     * 
     * @param int $supplier_op_id
     * @return array(
     *            'name'     => 'xxx',       // 操作名称
     *            'op_items' => array(x,x),  // 本地操作选项
     *            'checked'  => 'xxxx'       // 默认选中项
     *         )
     */
    function getSupplierOP($supplier_op_id) {
        $aList = $this->getSupplierOPList();
        
        return $aList[$supplier_op_id];
    }
    
    /**
     * 获取本地的所有操作
     * 
     * @return array
     */ 
    function getLocalOPList() {
        return array(
           0 => '手动操作',
           1 => '自动商品上架',
           2 => '自动商品下架',
           3 => '自动删除商品',
           4 => '自动同步库存',
           5 => '自动更新货品',
           6 => '自动更新图片',
           7 => '自动更新商品',
           8 => '自动新增商品'
        );
    }
    
    /**
     * 获取本地操作
     *
     * @param int $local_sop_id
     * @return string
     */
    function getLocalOP($local_sop_id){
        $aList = $this->getLocalOPList();
        
        return $aList[$local_sop_id];
    }
    
    /**
     * 获取配置的详细信息
     *
     * @param int    $rule_id
     * @param string $columns
     * @return array
     */
    function getRuleInfo($rule_id,$columns = "*") {
        return $this->db->selectrow('SELECT '.$columns.' FROM sdb_autosync_rule WHERE rule_id = \''.$rule_id.'\' and disabled = \'false\'');
    }
    
    /**
     * 获取指定配置编号的规则信息
     *
     * @param int $rule_id
     * @return array
     */
    function getRuleRelationInfo($rule_id) {
        $aList = $this->db->select('SELECT * FROM sdb_autosync_rule_relation WHERE rule_id=\''.$rule_id.'\'');
        
        $oSupplier = $this->system->loadModel('distribution/supplier');
        
        foreach($aList as $key=>$row) {
            // 供应商信息为空
            if(empty($row['supplier_id'])) continue;

            $aInfo =  $oSupplier->getSupplierInfo($row['supplier_id']);
            
            if($aInfo['supplier_pline']) {
                $aPline = unserialize($aInfo['supplier_pline']);
            
                // 产品线不为空
                if(!empty($aPline)) {
                    foreach($aPline as $key1=>$row1) {
                        if($row1['pline_name']) break; // 如果存在产品线名称则跳出,为了兼容以前的版本
                    
                        $aPline[$key1]['pline_name'] = $this->generatePlineName($row1);
                    }
                }            
                $aList[$key]['pline_list'] = $aPline;
            } else {
                $aList[$key]['pline_id'] = 0;
            }
        }
        
        return $aList;
    }
   
    /**
     * 新增一条同步配置记录
     *
     * @param array $data // array(
     *                          'supplier_op_id' => 'xxx',  // 供应商操作
     *                          'local_op_id'    => 'xxx',  // 本地操作
     *                          'memo'           => 'xxx',  // 备注
     *                          'rule'           => array(
     *                                                  'rule_relation_id'=>array(...), // 规则编号(新增为空)
     *                                                  'supplier_id'=>array(...),      // 供应商编号
     *                                                  'pline_id'=>array(...),         // 产品线编号
     *                                              )
     *                       )
     * @return boolean
     */
    function insert($data){
        $rule = $data['rule'];
        unset($data['rule']);
        unset($data['__']);
        
        $data['rule_name'] = $this->generateRuleName($rule);
        
        // 新增同步配置记录
        addslashes_array($data);
        if(!($rule_id = $this->_insert($data))) trigger_error('添加失败!',E_USER_ERROR);
        
        // 加入规则记录
        return $this->insertRuleRelation($rule_id,$rule);
    }
    
    /**
     * 生成一条同步配置记录
     *
     * @param array $data // array(
     *                          'supplier_op_id' => 'xxx',  // 供应商操作
     *                          'local_op_id'    => 'xxx',  // 本地操作
     *                          'memo'           => 'xxx',  // 备注
     *                          'rule_name'      => 'xxx',  // 规则名称
     *                       )
     * @return int | false
     */
    function _insert($data) {
        $rs = $this->db->exec('select * from '.$this->tableName.' where 0=1');
        
        $this->_checkColumns($data);
        
        $sql = $this->db->GetInsertSQL($rs,$data);
        
        if($sql && $this->db->exec($sql)){
            return $this->db->lastInsertId();
        }else{
            return false;
        }
    }
    
    /**
     * 检测字段
     *
     * @param array $data
     * @return void
     */
    function _checkColumns(&$data){
        foreach($data as $k=>$v){
            $data[$k] = trim($data[$k]);
        }
        
        $cols = $this->getColumns();
        $cols[$this->textColumn]['required'] = true;
        foreach($cols as $k=>$p){
            if($p['required']){
                if(!$data[$k]){
                    trigger_error('<b>'.$p['label'].'</b> 不能为空！',E_USER_ERROR);
                }
            }
        }
    }
    
    /**
     * 根据规则生成配置的名称 (供应商/产品线,供应商/产品线,...)
     * @param array $rule // array(
     *                          'rule_relation_id'=>array(...), // 规则编号(新增为空)
     *                          'supplier_id'=>array(...),      // 供应商编号
     *                          'pline_id'=>array(...),         // 产品线编号
     *                       );
     * @return string // 供应商名称1/产品线11,产品线12/供应商名称2/产品线21,产品线22...
     */
    function generateRuleName($rule) {
        return $this->_generateRuleName($this->_changeRuleDataFormat($rule));
    }
    /**
     * 转换配置数据的格式
     *
     * @param array $rule // array(
     *                          'rule_relation_id'=>array(...), // 规则编号(新增为空)
     *                          'supplier_id'=>array(...),      // 供应商编号
     *                          'pline_id'=>array(...),         // 产品线编号
     *                       );
     * @return array(
     *            'xxxx' => array( // 供应商license
     *                         'name'  => 'xxxx', // 供应商名称
     *                         'items' => array(  // 供应商下的产品线信息 '产品线编号'=>'产品线名称'
     *                                       'xxxx' => 'xxx',
     *                                        ....
     *                                    )
     *                      ),
     *             ....
     *         )
     */
    function _changeRuleDataFormat($rule){
        $oSupplier = $this->system->loadModel('distribution/supplier');
        
        $aResult = array();
        foreach($rule['supplier_id'] as $key=>$row){
            // 供应商为所有的处理
            if(empty($row)) {
                $aResult[$row]['name'] = '所有';
                $aResult[$row]['items'] = array(0=>'所有');
                continue;
            }
            // 指定供应商的处理(名称)
            if(empty($aResult[$row])) {
                $aInfo =  $oSupplier->getSupplierInfo($row);
                
                $aResult[$row]['name'] = $aInfo['supplier_brief_name'];
                $aResult[$row]['supplier_pline'] = unserialize($aInfo['supplier_pline']);
            }
            
            // 指定产品的线的处理
            $aResult[$row]['items'][$rule['pline_id'][$key]] = empty($rule['pline_id'][$key])? '所有' : $aResult[$row]['supplier_pline'][$rule['pline_id'][$key]]['pline_name'];
            if(empty($aResult[$row]['items'][$rule['pline_id'][$key]])) { // 如果不存在产品线名称则用cat_id|barnd_id生成定义 为了兼容以前的版本
                $aResult[$row]['items'][$rule['pline_id'][$key]] = $this->generatePlineName($aResult[$row]['supplier_pline'][$rule['pline_id'][$key]]);
            }
        }
        return $aResult;
    }
    
    /**
     * 根据规则生成配置的名称 (供应商/产品线,供应商/产品线,...)(private)
     *
     * @param array $aData // array(
     *                          'xxxx' => array( // 供应商license
     *                                      'name'  => 'xxxx', // 供应商名称
     *                                      'items' => array(  // 供应商下的产品线信息 '产品线编号'=>'产品线名称'
     *                                                   'xxxx' => 'xxx',
     *                                                    ....
     *                                                 )
     *                                    ),
     *                             ....
     *                        )
     * @return string // 供应商名称1/产品线11,产品线12/供应商名称2/产品线21,产品线22...
     */
    function _generateRuleName($aData){
        $aResult = array();
        foreach($aData as $key =>$row){
            $sTemp = $row['name'].'/'; // 供应商名称
            
            $aTemp = array_values($row['items']);
            $aResult[] = $sTemp.implode(',',$aTemp);
        }
        return implode(' & ',$aResult);
    }
    
    /**
     * 生成一个产品线名称
     *
     * @param array $data // array(
     *                          'cat_id'=>'x',   // 分类
     *                          'brand_id'=>'x', // 品牌
     *                       )
     * @return string
     */
    function generatePlineName($data){
        //if($data['cat_id'] == '-1' && $data['brand_id'] == '-1') return "所有";  
        return '产品线 '.$data['cat_id'].' | '.$data['brand_id'];
    }
    
    /**
     * 删除规则
     * 
     * @param array | int $rule_id  // 规则编号
     * @return boolean
     */
    function deleteRuleRelation($rule_id) {
         if(is_array($rule_id)) {
             $rule_id = implode("','",$rule_id);
         }
         return $this->db->exec('DELETE FROM sdb_autosync_rule_relation WHERE rule_id IN (\''.$rule_id.'\')');
    }
    
    /**
     * 新增规则
     * 
     * @param int   $rule_id  // 配置编号
     * @param array $data     // array(
     *                              'supplier_id' => array(...),  // 供应商id
     *                              'pline_id'    => array(...),  // 产品线id
     *                           );
     * @return boolean
     */
    function insertRuleRelation($rule_id,$data) {
        $rule = array();
        $oSupplier = $this->system->loadModel('distribution/supplier');
        foreach($data['supplier_id'] as $key=>$row) {
            if(!$this->_tempPline[$row]) {
                $aTemp = $oSupplier->getSupplierInfo($row);
                $this->_tempPline[$row] = unserialize($aTemp['supplier_pline']);
            }
            
            if($data['pline_id'][$key]){
                $aTemp = $this->_tempPline[$row][$data['pline_id'][$key]];
            } else {
                $aTemp = array(); // 所有产品线
            }
            $sDesc = serialize($aTemp);
            
            $rule[]= "'".$rule_id."','".$row."','".$data['pline_id'][$key]."','".$sDesc."'";
        }
        
        if(empty($rule)) return false;
        
        $values = implode('),(',$rule);
        return $this->db->exec("INSERT INTO sdb_autosync_rule_relation(`rule_id`,`supplier_id`,`pline_id`,`memo`) VALUES(".$values.")");
    }
    
    /**
     * 更新配置
     *
     * @param array $data
     * @param int $filter
     * @return boolean
     */
    function update($data,$filter) {
        $rule = $data['rule'];
        unset($data['rule_id']);
        unset($data['rule']);
        unset($data['__']);
        
        $data['rule_name'] = $this->generateRuleName($rule);
        addslashes_array($data);
        // 新增同步配置记录
        if(!($this->_update($data,$filter))) trigger_error('修改失败!',E_USER_ERROR);
        
        // 删除规则记录
        $this->deleteRuleRelation($filter['rule_id']);
        
        // 加入规则记录
        $this->insertRuleRelation($filter['rule_id'],$rule);
        
        return true;
    }
    
    /**
     * 更新配置
     * 
     * @param array $data
     * @param int $filter
     * @return int | false
     */
    function _update($data,$filter) {
        $rs = $this->db->exec('SELECT * from '.$this->tableName.' WHERE rule_id=\''.$filter['rule_id'].'\'');
        
        $this->_checkColumns($data);
        
        $sql = $this->db->GetUpdateSQL($rs,$data);
        
        if($sql){
            if($this->db->exec($sql)){
                return $this->db->affect_row();
            }else{
                return false;
            }
        }else{
            return true;
        }
    }
    
    /**
     * 生成配置文件
     * 
     * @return boolean // 生成是否成功
     * @todo   生成一个数组系列化文件,数组格式为
     *         $rule[$supplier_op_id][$supplier_id][$pline_id] =  array(
     *                                                               ['cat_id']      => 'xx',
     *                                                               ['brand_id']    => 'xx',
     *                                                               ['local_op_id'] => 'xx',
     *                                                            )
     */
    function generateAutoSyncConfigFile() {
        $sSql = "SELECT r.supplier_op_id,r.local_op_id,sp.supplier_pline,re.supplier_id,re.pline_id,re.memo 
                       FROM sdb_autosync_rule_relation AS re  
                       LEFT JOIN sdb_autosync_rule AS r ON r.rule_id = re.rule_id 
                       LEFT JOIN sdb_supplier AS sp ON sp.supplier_id = re.supplier_id 
                       WHERE r.disabled = 'false' 
                       ORDER BY r.rule_id ASC";
        $aRule = $this->db->select($sSql);
        
        // 转换格式
        $aRule = $this->_changeArray($aRule);
        
        $filename =  HOME_DIR."/cache/autosync.php";
        $fp = fopen($filename,'w');
        
        $flag =fwrite($fp,'<?php exit();?>'.serialize($aRule));
        
        fclose($fp);
        
        return $flag;
    }
    
    /**
     * 数组格式转换
     *
     * @param array $data // array(
     *                          'supplier_op_id' => 'xx',  // 供应商操作ID
     *                          'local_op_id'    => 'xx',  // 本地操作ID
     *                          'supplier_pline' => 'xx',  // 产品线数组系列化字串
     *                          'pline_id'       => 'xx',  // 产品线ID
     *                          'supplier_id'    => 'xx',  // 供应商ID
     *                       )
     * @return array // $data[$supplier_op_id][$supplier_id][$pline_id] = array(
     *                                                                       'cat_id'      => 'xxx',
     *                                                                       'brand_id'    => 'xxx',
     *                                                                       'local_op_id' => 'xxx',
     *                                                                    )
     */
    function _changeArray($data){
        $aRule = array();
        
        if(empty($data) || !is_array($data)) return $aRule;
        
        foreach($data as $row) {
            // 如果供应商ID为0 (所有供应商)
            if($row['supplier_id'] == 0) {
                $aRule[$row['supplier_op_id']][0][0] = array('local_op_id'=>$row['local_op_id']);
                continue;
            }
            
            // 如果产品线ID为0 (所有产品线)
            if($row['pline_id'] == 0 || empty($row['supplier_pline']) ) {
                $aRule[$row['supplier_op_id']][$row['supplier_id']][0] = array('local_op_id'=>$row['local_op_id']);
            }else{
                // 规则中存入产品线信息 2009-12-01 18:58 wubin
                if($row['memo']) {
                    $pline = unserialize($row['memo']);
                } else {
                $pline = unserialize($row['supplier_pline']);
                    $pline = $pline[$row['pline_id']];
                }
                
                if(!empty($pline)) {
                    $aRule[$row['supplier_op_id']][$row['supplier_id']][$row['pline_id']] = array(
                                                                                               'local_op_id'=>$row['local_op_id'],
                                                                                               'cat_id'     =>$pline['cat_id'],
                                                                                               'brand_id'   =>$pline['brand_id'],
                                                                                            );                  
                } else {
                    if(empty($row['supplier_pline'])) { // 如果供应商产品线为空
                        $aRule[$row['supplier_op_id']][$row['supplier_id']][0] = array('local_op_id'=>$row['local_op_id']);
                    }
                }
            }

        }
        
        return $aRule;
    }
    
    /**
     * 检测是否需要自动处理(public)
     * 
     * @param  int | array  $data  // int   command_id (必须存在$supplier_id)
     *                                array array(
     *                                        'command'     => 'xxx',   // 操作状态
     *                                        'supplier_id' => 'xxx',   // 供应商ID
     *                                        'cat_id'      => 'xxx',   // 分类ID
     *                                        'brand_id'    => 'xxx',   // 品牌ID  
     *                                        'store'       => 'xxx',   // 库存   null 为无限库存
     *                                        'marketable'  => 'xxx',   // 上下架 true | false
     *                                         ...        
     *                                      )
     * @return int  // 本地处理方法 详情查看$this->getLocalOPList();
     */
    function isNeedAutoSync($data,$supplier_id = 0){
        // 如果传入的ID为command_id
        if(!is_array($data)) {
             if(!$supplier_id) return 0;
             
             $data = $this->getCommandDetail($data,$supplier_id);
             
             if(empty($data)) return 0; // 不存在该记录
        }
        
        // 判断上架,下架
        if($data['command'] == 1) {
            if($data['marketable'] == 'false') $data['command'] = 8;
        }
        
        // 判断库存是否为0
        if($data['command'] == 2) {
            if($data['store'] == 0) $data['command'] = 9;
        }
        
        return $this->_isNeedAutoSync($data);
    }
    
    /**
     * 检测是否需要自动处理(private)
     *
     * @param  array $data
     * @return int
     * @todo   当存在 $rule[$supplier_op_id][0]...   可以直接返回  $rule[$supplier_op_id][0][0]['local_op_id']
     *         当存在 $rule[$supplier_op_id][xx][0]  可以直接返回 $rule[$supplier_op_id][xx][0]['local_op_id']
     *         当存在 $rule[$supplier_op_id][xx][xx] 判断 $rule[$supplier_op_id][xx][xx]['cat_id']
     *                                                   $rule[$supplier_op_id][xx][xx]['brand_id']
     *                                              如果 ok 返回 $rule[$supplier_op_id][xx][xx]['local_op_id']
     *                                              否则返回 0
     */
    function _isNeedAutoSync($data) {
        if(is_null($this->rule)) {
        // 获取配置文件
        $filename =  HOME_DIR."/cache/autosync.php";
        
        if(!file_exists($filename)) return 0;
        
        $aRule = substr(file_get_contents($filename),15);
        $aRule = unserialize($aRule);
            $this->rule = $aRule;
        } else {
            $aRule = $this->rule;
        }
        
        // 自动配置为空
        if(empty($aRule)) return 0;
        
        // 所有供应商
        if($aRule[$data['command']][0]) return $aRule[$data['command']][0][0]['local_op_id'];
        
        // 所有产品线
        if($aRule[$data['command']][$data['supplier_id']][0]) return $aRule[$data['command']][$data['supplier_id']][0]['local_op_id'];
        
        foreach($aRule[$data['command']][$data['supplier_id']] as $row) {
            // cat_id == -1 | brand_id == -1 (产品线)所有
            if( ($row['cat_id']) == -1 && ($row['brand_id'] == -1) ) return $row['local_op_id'];
            
            // cat_id == -1
            if( ($row['cat_id'] == -1) && ($row['brand_id'] == $data['brand_id']) ) return $row['local_op_id'];
            
            // brand_id == -1
            if( ($row['brand_id'] == -1) && ($row['cat_id'] == $data['cat_id']) ) return $row['local_op_id'];
            
            // 全匹配cat_id && brand_id
            if( ($row['cat_id'] == $data['cat_id']) && ($row['brand_id'] = $data['brand_id']) )  return $row['local_op_id'];
        }
        
        return 0;
    }
    
    /**
     * 获取指定$command_id 和 $sypplier_id的详细信息 (可以写在mdl.supplier.php文件中 )
     *
     * @param int $command_id
     * @param int $supplier_id
     * @return array
     */
    function getCommandDetail($command_id, $supplier_id){
        return $this->db->selectrow('SELECT * FROM sdb_data_sync_'.$supplier_id.' WHERE command_id = '.$command_id);
    }
    
    /**
     * 按指定的$local_op_id 同步信息
     *
     * @param int $supplier_id
     * @param int $command_id
     * @param int $local_op_id
     * @return boolean
     */
    function doSync($supplier_id,$command_id,$local_op_id) {
        
        $data = $this->getCommandDetail($command_id,$supplier_id);
        
        // 任务没有处理则,做作任务 2009-09-17 16:59:12 wubin
        if($data['status'] == 'unoperated') {
            if(!empty($data)) {
                switch($local_op_id) {
                    case 1: // 商品上架
                        $this->_doEnMarketable($data);
                        break;
                    case 2: // 商品下架
                         $this->_doDisMarketable($data);
                        break;
                    case 3: // 删除商品
                        $this->_doDeleteGoods($data);
                        break;
                    case 4: // 同步库存
                        $this->_doUpdateStore($data);
                        break;
                    case 5: // 更新货品
                        $this->_doUpdateProducts($data);
                         break;
                    case 6: // 更新图片
                        $this->_doUpdateImages($data);
                        break;
                    case 7: // 更新商品
                        $this->_doUpdateGoods($data);
                         break;
                    case 8: // 新增商品
                        $this->_doAddGoods($data);
                        break;
                 }
            }
        }
        
        // 删除同步任务
        return $this->deleteAutoSyncTask($supplier_id, $command_id);
    }
    
    /**
     * 新增自动同步任务
     * 
     * @param int $supplier_id
     * @param int $command_id 
     * @return string ('pass' | 'succ' | 'fail')
     */
    function addAutoSyncTask( $supplier_id, $command_id) {
        if(!($local_op_id = $this->isNeedAutoSync($command_id,$supplier_id))) return 'pass';
        
        $aData = array(
                   'supplier_id' => $supplier_id,
                   'command_id'  => $command_id,
                   'local_op_id' => $local_op_id
                 );
                 
        $rs   = $this->db->exec('SELECT * FROM sdb_autosync_task WHERE 0=1');
        $sSql = $this->db->GetInsertSQL($rs,$aData);
        
        if($this->db->exec($sSql)) {
            return 'succ';
        } else {
            return 'fail';
        }
    }
    
    /**
     * 删除自动同步任务
     *
     */
    function deleteAutoSyncTask($supplier_id, $command_id) {
        return $this->db->exec('DELETE FROM sdb_autosync_task WHERE supplier_id = \''.$supplier_id.'\' AND command_id = \''.$command_id.'\' ');
    }
    
    /**
     * 获取指定供应商的自动同步任务个数
     *
     * @param int $supplier_id
     * @return int
     */
    function getAutoSyncTaskCount($supplier_id) {
       $aCount =  $this->db->selectrow('SELECT count(*) AS num FROM sdb_autosync_task WHERE supplier_id = \''.$supplier_id.'\'');
       return $aCount['num'];
    }
    
    /**
     * 获取一条指定供应商的自动同步任务
     * 
     * @param int $supplier_id
     * @return array
     */
    function getAutoSyncTask($supplier_id) {
       return  $this->db->selectrow('SELECT * FROM sdb_autosync_task WHERE supplier_id = \''.$supplier_id.'\'');
    }
    
    /**
     * 商品上架
     *
     * @param array $data
     * @return void
     */
    function _doEnMarketable($data) {
        $oSupplier = $this->system->loadModel('distribution/supplier');
        
        $oSupplier->updateGoodsMarketable($data['supplier_id'],$data['object_id'],'true');
        $oSupplier->updateSyncStatus($data['command_id'],$data['supplier_id'],'done');
    }
    
    /**
     * 商品下架
     *
     * @param array $data
     * @return void
     */
    function _doDisMarketable($data) {
        $oSupplier = $this->system->loadModel('distribution/supplier');
        
        $oSupplier->updateGoodsMarketable($data['supplier_id'],$data['object_id'],'false');
        $oSupplier->updateSyncStatus($data['command_id'],$data['supplier_id'],'done');
    }
    
    /**
     * 删除商品
     *
     * @param array $data
     * @return void
     */
    function _doDeleteGoods($data) {
        $oSupplier = $this->system->loadModel('distribution/supplier');
        
        $oSupplier->removeGoods($data['supplier_id'],$data['object_id']);
        $oSupplier->updateSyncStatus($data['command_id'],$data['supplier_id'],'done');
    }
    
    /**
     * 同步库存
     *
     * @param array $data
     * @return void
     */
    function _doUpdateStore($data) {
        $oDataSync = $this->system->loadModel('distribution/datasync');
        $oSupplier = $this->system->loadModel('distribution/supplier');
        
        $oDataSync->syncProductStore($data['supplier_id'],$data['object_id']);
        $oSupplier->updateSyncStatus($data['command_id'],$data['supplier_id'],'done');
    }
    
    /**
     * 更新货品
     *
     * @param array $data
     * @return void
     */
    function _doUpdateProducts($data) {
        $oDataSync = $this->system->loadModel('distribution/datasync');
        $oSupplier = $this->system->loadModel('distribution/supplier');
        
        $oDataSync->updateGoodsProduct($data['supplier_id'],$data['object_id'],$data['command_id']);
        $oSupplier->updateSyncStatus($data['command_id'],$data['supplier_id'],'unmodified');
    }
    
    /**
     * 更新图片
     *
     * @param array $data
     * @return void
     */
    function _doUpdateImages($data) {
        $oDataSync = $this->system->loadModel('distribution/datasync');
        $oSupplier = $this->system->loadModel('distribution/supplier');
        
        // 下载图片任务
        $oSupplier->updateGoodsImageFailed($data['command_id'],$data['supplier_id']);
        $oDataSync->updateGoodsImage($data['command_id'],$data['supplier_id'],$data['object_id']);
        
        $oSupplier->updateSyncStatus($data['command_id'],$data['supplier_id'],'done');
    }
    
    /**
     * 更新商品
     *
     * @param array $data
     * @return void
     */
    function _doUpdateGoods($data) {
        $oDataSync = $this->system->loadModel('distribution/datasync');
        $oSupplier = $this->system->loadModel('distribution/supplier');
        $oBrand = $this->system->loadModel('goods/brand');
        
        $bFlag = false; // 否需要修改分类和品牌编号
        // 是否要下载分类,品牌等数据 2009-11-19 18:25 wubin
        if($oDataSync->checkGoodsDownload($data['supplier_id'],$data['object_id'])) {
            // 下载品牌和分类 2009-10-30 17:24 wubin
            $syncInfo = $oDataSync->preDownload($data['supplier_id'],$data['object_id'],$data['command_id'],null,false);
            $oSupplier->updateSyncStatus($data['command_id'],$data['supplier_id'],'unmodified');
            $oCat = $this->system->loadModel('goods/productCat');
            $oBrand = $this->system->loadModel('goods/brand');
            $oBrand->brand2json();
            $oCat->cat2json();
            
            $bFlag = true;
        }
        
        $aGoods = $oDataSync->getSupplierGoodsInfo($data['supplier_id'],$data['object_id']);
        
        $aData = array(
                    'unit'     => $aGoods['unit'],
                    'brief'    => $aGoods['brief'],
                    'name'     => $aGoods['name'],
                    'weight'   => $aGoods['weight'],
                    'intro'    => $aGoods['intro'],
                    'mktprice' => $aGoods['mktprice'],   // 市场价 2009-10-12 13:36 wubin bug:0013564
                    'params'   => $aGoods['params'],
                 );
         // 商品属性合并
         $aData = array_merge($aData,$this->getSupplierGoodsProperty($aGoods));
         
       if($bFlag) { // 运行过preDownload,有新增的品牌或类型
            $aGoods['type_id']  = $syncInfo['locals']['local_type_id'];
            $aGoods['brand_id'] = $syncInfo['locals']['local_brand_id'];
            
            if($aGoods['type_id']) $aData['type_id']  = $aGoods['type_id'];
            if($aGoods['brand_id']) {
                $aData['brand_id'] = $aGoods['brand_id'];
                $aTemp = $oBrand->getFieldById($aData['brand_id'],array('brand_name'));
                $aData['brand'] = $aTemp['brand_name'];
            }
            
         } else {
             // 已下载到b2c的品牌和类型
             $aData['type_id'] = $oDataSync->_getLocalTypeByPlatType($data['supplier_id'],$aGoods['type_id']);
             $aData['brand_id'] = $oDataSync->_getLocalBrandByPlatBrand($data['supplier_id'],$aGoods['brand_id']);
             
             $aTemp = $oBrand->getFieldById($aData['brand_id'],array('brand_name'));
             $aData['brand'] = $aTemp['brand_name'];
         }
        
         $rs = $this->db->exec('SELECT * FROM sdb_goods WHERE supplier_goods_id = \''.$data['goods_id'].'\' AND supplier_id=\''.$data['supplier_id'].'\'');
         $sSql = $this->db->GetUpdateSQL($rs,$aData);
         $this->db->exec($sSql);
         
         $oSupplier = $this->system->loadModel('distribution/supplier');
         $oSupplier->updateSyncStatus($data['command_id'],$data['supplier_id'],'unmodified');
    }
    
    /**
     * 根据配置操作失去产品线时的操作
     * 
     * 强烈注意：因为会有默认产品线代表所有商品的缘故，所以当从所有商品切换成某几条产品线时只能知道新赋予了哪些产品线而无法获知删除了哪些产品线，所以，这边传递进来的pline是指拥有的产品线信息。处理的时候也是除了这目前拥有的产品线之外的所有该供应商的商品都会自动按照“失去产品线时的操作”进行操作
     * 
     * @param int $supplier_id
     * @param array $pline , 目前拥有的产品线
     * 
     * @return void
     */
    function doLoosePline($supplier_id,$pline){
        $rule_info = $this->db->selectrow("SELECT a.local_op_id FROM sdb_autosync_rule AS a 
                                                  LEFT JOIN sdb_autosync_rule_relation AS b ON a.rule_id=b.rule_id 
                                                  WHERE (b.supplier_id=".$supplier_id." or b.supplier_id=0) AND a.supplier_op_id=10 AND a.disabled='false'");
        if($rule_info && $rule_info['local_op_id'] != 0){
            if(is_array($pline)){
                foreach($pline as $k=>$v){
                    if($v['cat_id'] == "-1" && $v['brand_id'] == "-1"){
                        //do nothing and exit
                        return true;
                    }else if($v['cat_id'] == "-1"){
                        $where[] = "(d.brand_id<>".$v['brand_id'].")";
                    }else if($v['brand_id'] == "-1"){
                        $where[] = "(d.cat_id NOT IN (".$v['cat_id']."))";
                    }else{
                        $where[] = "(d.cat_id NOT IN (".$v['cat_id'].") AND d.brand_id<>".$v['brand_id'].")";
                    }
                }
                
                $sql = "SELECT d.goods_id FROM sdb_data_sync_".$supplier_id." AS d INNER JOIN sdb_goods AS g ON d.goods_id=g.supplier_goods_id WHERE g.supplier_id=".$supplier_id." AND d.command=6 AND (".implode(" AND ",$where).")";
                $goods = $this->db->select($sql);
                $goods_id = array();
                foreach($goods as $v){
                    $goods_id[] = $v['goods_id'];
                }

                $update = "SELECT * FROM sdb_goods WHERE supplier_id=".$supplier_id." AND supplier_goods_id IN (".implode(",",$goods_id).")";
                switch($rule_info['local_op_id']){
                    case 2: //自动下架
                            $params = array('marketable'=>'false');
                            $update .= " AND marketable='true'";
                            break;
                    case 3: //自动删除
                            $params = array('disabled'=>'true');
                            $update .= " AND disabled='false'";
                            break;
                    default :
                            $params = array();
                            break;
                }
                
                if(!empty($params)){
                    $rs = $this->db->query($update);
                    $sql = $this->db->GetUpdateSQL($rs,$params);
                    $this->db->exec($sql);
                }

            }
        }
    }
    
    /**
     * 获取b2b商品的属性(只要商品的属性)
     * 
     * @param array $date // 查看 (datesync/getSupplierGoodsInfo)
     * @return array(
     *            'p_1'=>'xx',
     *            'p_2'=>'xx',
     *            'p_3'=>'xx',
     *             ...
     *         )
     */
    function getSupplierGoodsProperty($aData) {
        if(!is_array($aData)) return array();
        
        foreach($aData as $key => $val) {
            if(!preg_match("/^p_\d{1,2}$/",$key)) unset($aData[$key]);
        }
        return $aData;
    }
    
    /**
     * 新增商品
     *
     * @param array $data
     * @return void
     */
    function _doAddGoods($data) {
        $oDataSync = $this->system->loadModel('distribution/datasync');
        $oSupplier = $this->system->loadModel('distribution/supplier');
        
        // 下载商品
        $syncInfo = $oDataSync->downloadGoods($data['command_id'],$data['supplier_id'],$data['object_id']);
        // 更新类型和品牌数据 2009-10-12 14:10 wubin bug:0013558
        $oCat = $this->system->loadModel('goods/productCat');
        $oBrand = $this->system->loadModel('goods/brand');
        $oBrand->brand2json();
        $oCat->cat2json();
  
        $oSupplier->updateSyncStatus($data['command_id'],$data['supplier_id'],'unmodified');
    }
    
    /**
     * 获取还有未做完的自动同步记录的供应商license列表(中断后任务继续)
     * 
     * @return array(
     *            0=>array(
     *                'supplier_id'=>'xxx',
     *            ),
     *            ...
     *         )
     */
    function getAutoSyncSupplierList() {
        return $this->db->select('SELECT supplier_id FROM sdb_autosync_task GROUP BY supplier_id');
    }
    
    /**
     * 是否存在产品线名称  2009-11-23 10:33 wubin
     * 
     * @param array $aData // array(
     *                          'xx'=> array( // 产品线编号
     *                                    'cat_id'     => 'xxx',
     *                                    'brand_id'   => 'xxx',
     *                                    'pline_name' => 'xxx' // 产品线名称,如果是1.22以前的版本的产品线,本地可能不存在
     *                                 ),
     *                           ...
     *                        )
     * @return boolean
     */
    function isExistPlineName($aData){
        // 检测产品线,如果产品线名称不存在,则返回false
         foreach($aData as $key=>$row) {
               if(isset($row['pline_name'])) return true; // 如果存在产品线名称则跳出,为了兼容以前的版本
               return false;
         }
    }

    /**
     * 更新产品线(补充产品线名称)
     *
     * @param int   $supplier_id
     * @param array $aData  // 从平台上获取的产品线信息 array(
     *                                                 array(
     *                                                    'pline_id'   => 'xxx',
     *                                                    'pline_name' => 'xxx',
     *                                                    'cat_id'     => 'xxx',
     *                                                    'brand_id'   => 'xxx',
     *                                                     ...
     *                                                 ),
     *                                                 array(...),
     *                                                 ....
     *                                              )
     * @return array // array(
     *                     'xxx'=>array( // 产品线编号
     *                               'brand_id'   =>'xx',
     *                               'cat_id'     =>'xx',
     *                               'pline_name' => 'xx'
     *                            ),
     *                      ...
     *                  )
     */
    function fillPlineName($supplier_id,$aData) {
        // 获取产品线数据
        $oSupplier = $this->system->loadModel('distribution/supplier');
        $aInfo =  $oSupplier->getSupplierInfo($supplier_id);
        $aPline = unserialize($aInfo['supplier_pline']);
        
        $aData = array_change_key($aData,'pline_id');
        
        foreach($aPline as $key => $row) {
            $aPline[$key]['pline_name'] = $aData[$key]['pline_name'];
        }
        
        // 更新到供应商列表中
        $sPline = serialize($aPline);
        $sSql = "UPDATE sdb_supplier SET supplier_pline='".$sPline."' WHERE supplier_id ='".$supplier_id."'";
        $this->db->exec($sSql);
        
        return $aPline;
    }

    /**
     * 自动更新配置名称
     *
     * @param array $rows
     */
    function modifier_rule_name(&$rows) {
         foreach($rows as $key => $val){
             $rows[$key] = "<span title='".htmlspecialchars($val,ENT_QUOTES)."'>".htmlspecialchars($val,ENT_QUOTES)."</span>";
         }
    }
    
    /**
     * 自动更新配置供应商操作
     *
     * @param array $rows
     */
    function modifier_supplier_op_id(&$rows){
        foreach($rows as $key => $val){
             if ($val) {
                 $aTemp = $this->getSupplierOP($val);
                 $rows[$key] = $aTemp['name'];
             } else {
                 $rows[$key] = "-";
            }
        }
    }
    
    /**
     * 自动更新配置本地操作
     *
     * @param array $rows
     */
    function modifier_local_op_id(&$rows){
        foreach($rows as $key => $val){
            $rows[$key] = $this->getLocalOP($val);
        }
    }
}
?>