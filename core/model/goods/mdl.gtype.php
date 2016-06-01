<?php
include_once('shopObject.php');
/**
 * mdl_goods_type
 *
 * @uses shopObject
 * @package goods
 * @version $Id: mdl.gtype.php 1985 2008-04-28 06:36:02Z flaboy $
 * @copyright 2003-2007 ShopEx
 * @license Commercial
 */
class mdl_gtype extends shopObject{

    var $idColumn = 'type_id';
    var $textColumn = 'name';
    var $defaultCols = 'name,is_physical';
    var $adminCtl = 'goods/gtype';
    var $defaultOrder = array('type_id','desc');
    var $tableName = 'sdb_goods_type';

    function modifier_supplier_id(&$rows){
        $oSupplier = $this->system->loadModel('distribution/supplier');
        foreach($rows as $k => $v){
            if($v) {
               $rows[$k] = $oSupplier->getSupplierInfo($v,'supplier_brief_name');
               $rows[$k] = $rows[$k]['supplier_brief_name']; 
            }
        }
    }

    function toRemove($type_id){
        if($this->checkDelete($type_id, $result)){
            $sql = 'DELETE FROM sdb_goods_type WHERE type_id = '.intval($type_id);
            $this->db->exec($sql);
            $sql = 'DELETE FROM sdb_type_brand WHERE type_id = '.intval($type_id);
            $this->db->exec($sql);
            $sql = 'DELETE FROM sdb_goods_type_spec WHERE type_id = '.intval($type_id);
            $this->db->exec($sql);
            return true;
        }else{
            if($result == 1){
                trigger_error(__('系统默认通用类型不允许删除'),E_USER_ERROR);
                return false;
            }
            if($result == 2){
                trigger_error(__('类型下存在与之关联的商品，无法删除'),E_USER_ERROR);
                return false;
            }
        }
    }

    function modifier_schema_id($rows){
        $addons = $this->system->loadModel('system/addons');
        foreach($addons->getList('plugin_ident,plugin_name',array('plugin_type'=>'schema')) as $r){
            $map[$r['plugin_ident']] = $r['plugin_name'];
        }
        foreach($rows as $k=>$v){
            if(isset($map[$v])){
                $rows[$k] = $map[$v];
            }
        }
    }

    function getColumns(){
        $ret = array('_cmd'=>array('label'=>__('操作'),'width'=>110,'html'=>'product/gtype/finder_command.html'));
        return array_merge($ret,parent::getColumns());
    }

    function checkDelete($type_id, &$result){
        $aType = $this->getDefault();
        if($aType[0]['type_id'] == $type_id){
            $result = 1;
            return false;
        }
        $row = $this->db->selectrow('SELECT count(*) AS has_goods FROM sdb_goods WHERE type_id ='.intval($type_id));
        if($row['has_goods']>0){
            $result = 2;
            return false;
        }else{
            return true;
        }
    }

    function getTypeDetail($typeid, $str_tag=false){
        $sqlString = 'SELECT * FROM sdb_goods_type WHERE type_id = '.intval($typeid);
        $row = $this->db->selectrow($sqlString);
        $row['props'] = unserialize($row['props']);
        $row['ordernum'] = $this->propsort($row['props']);
        $row['setting'] = unserialize($row['setting']);
        $row['spec'] = unserialize($row['spec']);
        $row['setting']['use_spec'] = true;
        $row['minfo'] = unserialize($row['minfo']);
        $row['params'] = unserialize($row['params']);
        $row['is_physical']=$row['is_physical'];
        $s = 0;
        foreach($row['params'] as $g){
            foreach($g as $p){
                $s = 1;
            }
        }
        if($s==0){
            unset($row['params']);
        }

        if($str_tag){
            $this->arrToStr($row);
        }

        $brand = &$this->system->loadModel('goods/brand');
        $aBrand = $brand->getTypeBrands($typeid);
        $aTmpBrands = array();
        foreach($aBrand as $v){
            $aTmpBrands[] = $v['brand_id'];
        }
        $row['brands'] = $aTmpBrands;

        $row['spec'] = $this->getSpec($typeid);

        return $row;
    }

    function arrToStr(&$data){
        foreach($data['props'] as $k => $row){
            $aTmp = array();
            foreach($row['options'] as $i => $v){
                if($row['optionAlias'][$i]){
                    $aTmp[] = $v.'|'.$row['optionAlias'][$i];
                }else{
                    $aTmp[] = $v;
                }
            }
            $data['props'][$k]['s_props'] = implode(',', $aTmp);
        }
        if($data['params']){
            $i = 0;
            foreach($data['params'] as $gname => $row){
                $i++;
                $aGroup['group'][$i] = $gname;
                foreach($row as $item => $alias){
                    $aGroup['name'][$i][] = $item;
                    $aGroup['alias'][$i][] = $alias;
                }
            }
            $data['a_params'] = $aGroup;
        }
        foreach($data['minfo'] as $k => $row){
            if($row['options']){
                $data['minfo'][$k]['s_minfo'] = implode(',', $row['options']);
            }
        }
    }

    function get($type_id){
        $row = $this->db->selectrow('select type_id,schema_id,setting from sdb_goods_type where type_id='.intval($type_id));
        $row['setting'] = unserialize($row['setting']);

        $props_map = $this->_props_map($type_id);

        $valueMap=array();
        if($row['setting']['inSearch'])
            foreach($row['setting']['inSearch'] as $k=>$v){
                if($props_map[$v['attr']]['type']==P_ENUM){
                    $valueMap[$props_map[$v['attr']]['cell']] = array();
                    $row['setting']['inSearch'][$k]['items'] = &$valueMap[$props_map[$v['attr']]['cell']];
                }
            }
        if($row['setting']['inSelector'])
            foreach($row['setting']['inSelector'] as $k=>$v){
                if($props_map[$v['attr']]['type']==P_ENUM){
                    $valueMap[$props_map[$v['attr']]['cell']] = array();
                    $row['setting']['inSelector'][$k]['column'] = $props_map[$v['attr']]['cell'];
                    $row['setting']['inSelector'][$k]['items'] = &$valueMap[$props_map[$v['attr']]['cell']];
                }
            }
        if(count($valueMap)>1)
            foreach($this->db->select('select value_id,p_value,p_column from sdb_param_values where p_column in('.implode(',',array_keys($valueMap)).') and type_id='.intval($type_id)) as $v){
                $valueMap[$v['p_column']][] = array('label'=>$v['p_value'],'value'=>$v['value_id']);
            }

        return $row;
    }

    function _props_map($type_id){
        $row = $this->db->selectrow('select props from sdb_goods_type where type_id='.intval($type_id));
        return unserialize($row['props']);
    }

    function deliveryInfo($products){
        $info = array();
        if(is_array($products) && count($products))
        foreach($this->db->select('SELECT c.type_id,c.minfo,c.is_physical,c.setting FROM sdb_goods p LEFT JOIN sdb_goods_type c ON c.type_id = p.type_id WHERE p.goods_id IN ('.implode(',',$products).') GROUP BY c.type_id') as $type){
            $setting = unserialize($type['setting']);
            if(($req = unserialize($type['minfo'])) && $setting['use_minfo']){
                $info['custom'][$type['type_id']] = $req;
            }
            if($type['is_physical'])
                $info['physical'] = 1;
        }
        return $info;
    }


    function getTypeObj($id,&$name){
        $type = $this->instance($id);

        $brand = &$this->system->loadModel('goods/brand');
        $typeBrands = $brand->getTypeBrands($id);

        $return = array();
        $name = $type['name'];
        $return['name'] = $type['name'];
        $return['alias'] = $type['alias'];
        $return['schema_id'] = $type['schema_id'];
        $return['is_physical'] = $type['is_physical'];
        $return['props'] = unserialize($type['props']);
        $return['setting'] = unserialize($type['setting']);
        if($return['setting']['use_brand']==1&&$typeBrands){
            $return['brands'] = array();
            foreach($typeBrands as $v){
                if($v['brand_name']!=''){
                    $arr['brand_name'] = $v['brand_name'];
                    $arr['brand_keywords'] = $v['brand_keywords'];
                    $arr['s_brand_id'] = $v['s_brand_id'];
                    $return['brands'][] = $arr;
                }
            }
        }
        $return['spec'] = $this->getSpec($id);
        $return['minfo'] = unserialize($type['minfo']);
        $return['params'] = $this->params_modifier(unserialize($type['params']));
        return $return;
    }

    function params_modifier($data,$forxml = true){
        $return = array();
        if(is_array($data)){
            if($forxml){
                $i = 0;
                foreach($data as $group=>$cont){
                    $return[$i] = array('groupname'=>$group);
                    if(is_array($cont)){
                        foreach($cont as $k=>$v){
                            $item['itemname'] = $k;
                            $item['itemalias'] = explode('|',$v);
                            $return[$i]['groupitems'][] = $item;
                        }
                    }
                    $i++;
                }
            }else{
                foreach($data as $k=>$group){
                    $return[$group['groupname']] = array();
                    if($group['groupitems']&&is_array($group['groupitems'])){
                        foreach($group['groupitems'] as $k1=>$v1){
                            $return[$group['groupname']][$v1['itemname']] = implode('|',$v1['itemalias']);
                        }
                    }
                }
            }
        }
        return $return;
    }

    function fetchSave($data){
        if ($data['props']){
            foreach($data['props'] as $key => $val){
                $data['props'][$key]['show']=1;
            }
        }
        $data['params'] = $this->params_modifier($data['params'],false);
        if($this->db->selectrow('select * from sdb_goods_type where name=\''.$data['name'].'\'')){
            $this->setError(300001);
            trigger_error(__('对不起，本类型名已存在，请重新输入。'),E_USER_ERROR);
        }

        $rs = $this->db->exec('select * from sdb_goods_type where 0=1');
        $sql = $this->db->getInsertSQL($rs,$data);
        if($this->db->exec($sql)){
            $type_id = $this->db->lastInsertId();

            foreach($data['brands'] as $v){
                if(trim($v['brand_name'])){
                    $aBrands[] = $v['brand_name'];
                }
            }
            if($aBrands){
                $brand = &$this->system->loadModel('goods/brand');
                $aBrands = $brand->getBrandsByNames($aBrands);
            }

            $type_brand = array();
            $i = 0;
            $type_brand['type_id'] = $type_id;

            foreach($data['brands'] as $v){
                if($aBrands[$v['brand_name']]){
                    $type_brand['brand_order'] = $i;
                    $type_brand['brand_id'] = $aBrands[$v['brand_name']];
                    $rs_type_brand = $this->db->exec('select * from sdb_type_brand where 0=1');
                    $sql = $this->db->getInsertSQL($rs_type_brand,$type_brand);
                    $this->db->exec($sql);
                    $i++;
                }
            }
            $brand = array();
            foreach($_POST['importbrands'] as $v){
                $brand['s_brand_id'] = $v;
                $brand['brand_name'] = $data['brands'][$v]['brand_name'];
                $brand['brand_keywords'] = $data['brands'][$v]['brand_keywords'];
                $rs_brand = $this->db->exec('select * from sdb_brand where 0=1');
                $sql = $this->db->getInsertSQL($rs_brand,$brand);
                $this->db->exec($sql);
                $brand_id = $this->db->lastInsertId();
                $type_brand['brand_order'] = $i;
                $type_brand['brand_id'] = $brand_id;
                $rs_type_brand = $this->db->exec('select * from sdb_type_brand where 0=1');
                $sql = $this->db->getInsertSQL($rs_type_brand,$type_brand);
                $this->db->exec($sql);
            }
            $aSpec = $this->system->loadModel('goods/specification');

            foreach($data['spec'] as $spec_id => $v){
                if($spec_id){
                    $id = $aSpec->getIdByName($v['name']);
                    $type_spec['spec_id'] = $id;
                    $type_spec['type_id'] = $type_id;
                    $type_spec['spec_style'] = $v['spec_style'];
                    $rs_type_spec = $this->db->exec('select * from sdb_goods_type_spec where 0=1');
//error_log(var_export($type_spec,true),3,'d:\test.txt');
                    $sql = $this->db->getInsertSQL($rs_type_spec,$type_spec);
                    $this->db->exec($sql);
                }
            }

            $this->checkDefined();
            return true;
        }else{
            return false;
        }
    }

    function toSave($data){
        if(!class_exists('schema_'.$data['schema_id'])){
            require(SCHEMA_DIR.$data['schema_id'].'/schema.'.$data['schema_id'].'.php');
        }
        $type = 'schema_'.$data['schema_id'];

        $schema = &$this->system->loadModel('goods/schema');
        return $schema->save($data['schema_id'], $data, $message, get_class_methods($type));
    }
    function nameSave($id,$data)
    {

        $rs = $this->db->exec("SELECT * FROM sdb_goods_type WHERE type_id =".$id);

        $sSql = $this->db->getUpdateSql($rs,array('name' => $data));
       // $this->db->exec($sSql);

        if(!$sSql || $this->db->exec($sSql)){
            return true;
        }else{
            trigger_error(__('配送单据生成失败'), E_USER_ERROR);
            return false;
        }

    }
    function getTypebyAlias($cols='*',$name){
        return $this->db->selectrow('select '.$cols.' from sdb_goods_type where name=\''.$name.'\' or alias like \'%|'.$name.'|%\'');
    }

    function checkTypeByName($name,$id){
        if($this->db->selectrow('select * from sdb_goods_type where name='.$this->db->quote( $name ).'and type_id !='.intval($id)))
            return false;
        return true;
    }

    function checkDefined(){
        return $this->count(array('is_def'=>'false'));
    }

    function getDefault(){
        return $this->getList('*',array('is_def'=>'true'));
    }

    function saveSpec($specid, $aData){
        if($specid){
            $aUpdate = array('spec' => $aData);
            $rs = $this->db->exec("SELECT * FROM sdb_goods_type WHERE type_id=".$specid);
            $sSql = $this->db->getUpdateSql($rs,$aUpdate);
            if(!$sSql || $this->db->exec($sSql)){
                return true;
            }else{
                return false;
            }
        }else{
            return false;
        }
    }
    function getSpec($id,$fm=0){
        $sql="select spec_id,spec_style from sdb_goods_type_spec where type_id=".intval($id);
        $row = $this->db->select($sql);
        if ($row){
            foreach($row as $key => $val){
                if ($fm){
                    if($val['spec_style']<>'disabled'){
                        $attachment=array(
                            "spec_style"=>$val['spec_style']
                        );
                        $tmpRow[$val['spec_id']]=$this->getSpecName($val['spec_id'],$attachment);
                    }
                }
                else{
                    $attachment=array(
                        "spec_style"=>$val['spec_style']
                    );
                    $tmpRow[$val['spec_id']]=$this->getSpecName($val['spec_id'],$attachment);
                }
            }

            return $tmpRow;
        }
        else
            return false;
    }
    function getSpecName($spec_id,$args){
        $sql="select spec_name,spec_type from sdb_specification where spec_id=".intval($spec_id);
        $snRow=$this->db->selectrow($sql);
        $tmpRow['name']=$snRow['spec_name'];
        $tmpRow['spec_type'] = $snRow['spec_type'];
        $tmpRow['spec_memo'] = $snRow['spec_memo'];
        if (is_array($args)){
            foreach($args as $k => $v){
                $tmpRow[$k] = $v;
            }
        }
        $row=$this->getSpecValue($spec_id);
        $tmpRow['spec_value']=$row;
        $tmpRow['type'] = 'spec';
        return $tmpRow;
    }
    function getSpecValue($spec_id){
        $sql="select spec_value,spec_value_id,spec_image from sdb_spec_values where spec_id=".intval($spec_id)." order by p_order,spec_value_id";
        $svRow=$this->db->select($sql);
        if ($svRow){
            foreach($svRow as $key => $val){
                $tmpRow[$val['spec_value_id']]=array(
                        "spec_value"=>$val['spec_value'],
                        "spec_image"=>$val['spec_image']
                );
            }
        }
        return $tmpRow;
    }
    function propsort($prop=array()){
        if (is_array($prop)){
            foreach($prop as $key => $val){
                $tmpP[]=array('ord'=>$val['ordernum'],'key'=>$key);
            }
            for($i=0;$i<count($tmpP);$i++){
                for($j=$i;$j<count($tmpP);$j++){
                    if (intval($tmpP[$i]['ord'])>intval($tmpP[$j]['ord'])){
                        $t=$tmpP[$i];
                        $tmpP[$i]=$tmpP[$j];
                        $tmpP[$j]=$t;
                    }
                }
            }
            if ($tmpP){
                foreach($tmpP as $key => $val){
                    $tmpC[]=$val['key'];
                }
            }
            return $tmpC;
        }
    }


    function typeTransformCheck($sourceTypeId,$destTypeId, &$goods){
        $this->sourceType = $this->getTypeDetail( $sourceTypeId );
        $this->destType = $this->getTypeDetail( $destTypeId );

        return $this->specTransformCheck( $goods['spec_desc'] );
    }
    /**   类型转换
       *   sourceTypeId    source typeid
       *   destTypeId        dest typeid
       *   goods                 goods data
    */
    function typeTransform($sourceTypeId , $destTypeId, &$goods ){
        //类型id相同 不做操作 跳出
        if( $sourceTypeId == $destTypeId )
            return true;

        $this->sourceType = $this->getTypeDetail( $sourceTypeId );         //得到source类型
        $this->destType = $this->getTypeDetail( $destTypeId );                  //得到dest类型

        //生成转换规则
        //捕获转换规则异常
        if( !$this->specTransformCheck($goods['spec_desc']) || !$this->brandTransformCheck() || !$this->propsTransformCheck() || !$this->paramsTransformCheck() ){
            //
            return false;
        }
        //将数据进行转换
        $this->specTransform($goods);
        $this->brandTransform($goods);
        $this->propsTransform($goods);
        $this->paramsTransform($goods);
        return true;
    }

    //规格转换验证
    /**
       *
       */
    function specTransformCheck($goodsSpecDesc = null){

        /* 比对规格
          * 类型绑定规格数须完全相等
          * 类型绑定规格数不一致时 销毁商品绑定规格
          * 影响： 根据规格转换规则 复制给$this->specTransformState
          * $this->specTransformState= array(
          *         'different' => 完全不匹配 执行操作：不复制规格,
          *         'same' => 完全相同 执行操作：复制规格,
          *         'contain' => 规格数量相等 source规格值包含于dest规格值 执行操作：转换规格
          *  )
          *  when $this->specTransformState == 'contain'
          *  specTransformHash            规格id对应hash map
                array(
                    source_spec_id => array(
                        'spec_id' => dest_spec_id
                        'spec_value' => array(
                            source_spec_value_id => dest_spec_value_id
                        )
                    )
                 )
         */
        $this->specTransformError = array();
        if( count( $this->sourceType['spec'] ) != count( $this->destType['spec'] ) ){
            $this->specTransformState = 'different';
        }else{
            if(
                array_diff(
                    array_keys( $this->sourceType['spec'] ),
                    array_keys( $this->destType['spec'] )
                ) ==
                array_diff(
                    array_keys( $this->destType['spec'] ) ,
                    array_keys( $this->sourceType['spec'] )
                )
            ){
                //类型绑定规格完全相同（id） 执行复制
                $this->specTransformState = 'same';
            }else{
                //类型绑定规格不完全相同 规格名须完全相等 source规格值包含于dest规格值

                $specDescHash = array();
                if( !empty($goodsSpecDesc) ){
                    foreach( $goodsSpecDesc as $goodsSpecId => $goodsSpecV ){
                        foreach( $goodsSpecV as $goodsPSpecId => $goodsPSpecV ){
                            $specDescHash[$goodsSpecId][$goodsPSpecV['spec_value_id']] = $goodsPSpecV['spec_value'];
                        }
                    }
                }

                $this->destName = array();
                foreach( $this->destType['spec'] as $destSpecId => $destv )
                    foreach( $destv['spec_value'] as $destSpecValueId => $destSpecv )
                        $destName[$destv['name']][$destSpecv['spec_value']] = array(
                            'spec_id'=>$destSpecId,
                            'spec_value_id'=>$destSpecValueId
                        );

                foreach( $this->sourceType['spec'] as $sourceSpecId => $srcv ){
                    if( !isset( $destName[$srcv['name']] ) ){
                        $this->specTransformState = 'different';
                        return true;
                    }
                    foreach( $srcv['spec_value'] as $sourceSpecValueId => $srcSpecv ){
                        if( isset( $destName[$srcv['name']][$specDescHash[$sourceSpecId][$sourceSpecValueId]] ) ){
                            $this->specTransformHash[$sourceSpecId]['spec_id'] = $destName[$srcv['name']][$specDescHash[$sourceSpecId][$sourceSpecValueId]]['spec_id'];
                            $this->specTransformHash[$sourceSpecId]['spec_value'][$sourceSpecValueId] = $destName[$srcv['name']][$specDescHash[$sourceSpecId][$sourceSpecValueId]]['spec_value_id'];
                        }else if( isset( $destName[$srcv['name']][$srcSpecv['spec_value']] ) ){
                            $this->specTransformHash[$sourceSpecId]['spec_id'] = $destName[$srcv['name']][$srcSpecv['spec_value']]['spec_id'];
                            $this->specTransformHash[$sourceSpecId]['spec_value'][$sourceSpecValueId] = $destName[$srcv['name']][$srcSpecv['spec_value']]['spec_value_id'];
                        }else{
                            $this->specTransformState = 'different';
return true;
                            //$this->specTransformState = 'spec_value different';
                            //$this->specTransformError[$sourceSpecId]['spec_name'] = $srcv['name'];
                            //$this->specTransformError[$sourceSpecId]['spec_value'][] = //$srcSpecv['spec_value'];
                        }
                    }
                }
                unset( $destName );
                $this->specTransformState = 'contain';
            }
        }
        if(!empty( $this->specTransformError ))
            return false;
        return true;
    }

    //规格转换
    /**

    **/
    function specTransform(&$goods){
        if( $goods['spec_desc'] && $this->specTransformState == 'contain' ){
            $goodsSpecDesc = array();
            foreach( $goods['spec_desc'] as $t_specId => $pSpec ){
                foreach( $pSpec as $pSpecId => $pSpecValue ){
                    $pSpecValue['spec_value_id'] = $this->specTransformHash[$t_specId]['spec_value'][$pSpecValue['spec_value_id']];
                    $goodsSpecDesc[$this->specTransformHash[$t_specId]['spec_id']][$pSpecId] = $pSpecValue;
                }
            }
            $goods['spec_desc'] = $goodsSpecDesc;

            foreach( $goods['products'] as $pid => $pro ){
                $aProProps = array();
                foreach( $pro['props'] as $pkey => $pvalue ){
                    foreach( $pvalue as $pSpecId => $pSpecv ){
                        if( $pkey == 'spec_value_id' ){
                            $aProProps[$pkey][$this->specTransformHash[$pSpecId]['spec_id']] = $this->specTransformHash[$pSpecId]['spec_value'][$pSpecv];
                        }else{
                            $aProProps[$pkey][$this->specTransformHash[$pSpecId]['spec_id']] = $pSpecv;
                        }
                    }
                }
                $goods['products'][$pid]['props'] = $aProProps;
            }
        }
        return true;
    }

    //属性转换
    /**
     * this->typePropsHash = array(
     *    source props key => array(
     *          'key' => dest props key,
     *          'dest_type' => dest props type,
     *          'source_type' => source props type,
     * =========================================
     * if 源与目标类型都为输入类型 转换时执行copy
     * =========================================
     * else if源类型与目标类型都为单选类型
     *          'propsSelectHash' => array(
     *              source props k => dest props k
     *              ......
     *          ),
     * =========================================
     * else if源类型与目标类型为不同类型 需要记录单选类型的属性值的key name alia
     *          'options' => array(
     *              'source' => array(
     *                  source props key => array(
     *                      'name' => source props name,
     *                      'alias' => '|'.source props alias.'|'
     *                  )
     *              ),
     *              'dest' => array(
     *                  dest props key => array(
     *                      'name' => source props name,
     *                      'alias' => '|'.source props alias.'|'
     *                  )
     *              )
     *          )
     * =========================================
     *    )
     * )
    */
    function propsTransformCheck(){
        //属性比对
        //目标类型属性整理
        $propsAlias = array();                   //属性别名数组
        $propsOptions = array();             //属性数组
        $propsValueAlias = array();         //属性值别名数组
// ===================================================
        foreach( $this->destType['props'] as $destPropsk => $destPropsv ){
            foreach( explode('|',$destPropsv['alias']) as $aliasName ){
                if( $aliasName == '' )
                    continue;
                $propsAlias[$aliasName] = $destPropsv['name'];
            }
            $propsOptions[$destPropsv['name']]['key'] = $destPropsk;
            $propsOptions[$destPropsv['name']]['type'] = $destPropsv['type'];

        }
        //source属性对应
        //通过别名比对dest属性
        foreach( $this->sourceType['props'] as $sourcePropsk => $sourcePropsv ){
            $destPropsName = '';  //对应的dest属性名
            foreach( explode('|',$sourcePropsv['alias'] ) as $sourceAliasName ){
                if( !$sourceAliasName || !isset( $propsAlias[$sourceAliasName] ) )
                    continue;
                $destPropsName = $propsAlias[$sourceAliasName];
                break;
            }

            if( !$destPropsName ){
                foreach( explode('|',$sourcePropsv['alias']) as $sourceAliasName ){
                    if( !$sourceAliasName || !isset( $propsOptions[$sourceAliasName] ) )
                        continue;
                    $destPropsName = $sourceAliasName;
                    break;
                }
            }

            //通过属性名比对dest属性
            if( !$destPropsName ){
                if( !isset($this->typePropsHash[$sourcePropsk]) && isset( $propsAlias[$sourcePropsv['name']] ) ){
                    $destPropsName = $propsAlias[$sourcePropsv['name']];
                }
            }

            if( !$destPropsName ){
                if( !isset($this->typePropsHash[$sourcePropsk]) && isset( $propsOptions[$sourcePropsv['name']] ) ){
                    $destPropsName = $sourcePropsv['name'];
                }
            }

            if( $destPropsName ){
                $this->typePropsHash[$sourcePropsk]['key'] = $propsOptions[$destPropsName]['key'];
                $this->typePropsHash[$sourcePropsk]['dest_type'] = $propsOptions[$destPropsName]['type'];
                $this->typePropsHash[$sourcePropsk]['source_type'] = $sourcePropsv['type'];
            }
            unset($destPropsName);

            //属性比对成功 比对属性值
            if( isset($this->typePropsHash[$sourcePropsk]) ){

                //目标type属性整理
                $destTypeOpAlias = $this->destType['props'][$this->typePropsHash[$sourcePropsk]['key']]['optionAlias'];
                $destTypeOps = $this->destType['props'][$this->typePropsHash[$sourcePropsk]['key']]['options'];

                if( $this->typePropsHash[$sourcePropsk]['dest_type'] == $this->typePropsHash[$sourcePropsk]['source_type'] ){
                    if( $this->typePropsHash[$sourcePropsk]['dest_type'] == 'select' ){
                        $this->_correspondSelectProps($destTypeOpAlias, $destTypeOps,$sourcePropsk ,$sourcePropsv);
                    }
                }else{
                    $this->_correspondDiffProps($destTypeOpAlias, $destTypeOps,$sourcePropsk ,$sourcePropsv);
                }


            }

        }
        return true;
    }

    function propsTransform(&$goods){
        $_typeProps = array();
        foreach( $this->typePropsHash as $sourcePropsk => $sourcePropsv ){
            if( $sourcePropsv['dest_type'] == $sourcePropsv['source_type'] ){
                switch( $sourcePropsv['dest_type'] ){
                    case 'input':
                        $_typeProps['p_'.$sourcePropsv['key']] = $goods['p_'.$sourcePropsk];
                        break;

                    case 'select':
                        $_typeProps['p_'.$sourcePropsv['key']] = $sourcePropsv['propsSelectHash'][$goods['p_'.$sourcePropsk]];
                        break;
                }
            }else{
                switch( $sourcePropsv['dest_type'] ){
                    case 'input':
                        $_typeProps['p_'.$sourcePropsv['key']] = $sourcePropsv['options']['source'][$goods['p_'.$sourcePropsk]]['name'];
                        break;

                    case 'select':
                        foreach( $sourcePropsv['options']['dest'] as $s_typePropsk => $s_typePropsv ){
                            if( $s_typePropsv['alias'] ){
                                foreach( explode('|',$s_typePropsv['alias']) as $s_typePropsVName ){
                                    if(
                                        strpos($goods['p_'.$sourcePropsk],$s_typePropsVName ) === false &&
                                        strpos($s_typePropsVName,$goods['p_'.$sourcePropsk]) === false
                                    )
                                        continue;
                                    $_typeProps['p_'.$sourcePropsv['key']] = $s_typePropsk;
                                    break;
                                }
                            }
                        }
                        if( !isset( $_typeProps['p_'.$sourcePropsv['key']] ) ){
                            foreach( $sourcePropsv['options']['dest'] as $s_typePropsk => $s_typePropsv ){
                                if(
                                    strpos($goods['p_'.$sourcePropsk],$s_typePropsv['name'] ) === false &&
                                    strpos($s_typePropsv['name'],$goods['p_'.$sourcePropsk]) === false
                                )
                                    continue;
                                $_typeProps['p_'.$sourcePropsv['key']] = $s_typePropsk;
                                break;
                            }
                        }
                        break;
                }
            }
        }
        foreach( $goods as $goodsk => $goodsv ){
            if(substr($goodsk,0,2) == 'p_'){
                unset($goods[$goodsk]);
            }
        }
        foreach( $_typeProps as $typePropsk => $typePropsv ){
            $goods[$typePropsk] = $typePropsv;
        }
        return true;
    }

    /**
     * 源类型与目标类型都为select型时 建立对应关系
     */
    function _correspondSelectProps($destTypeOpAlias, $destTypeOps,$sourcePropsk ,$sourcePropsv){
        $_destTypeOpNameHash = array();
        $_destTypeOpAliasHash = array();
        foreach( $destTypeOpAlias as $d_typeOpAliask => $d_typeOpAliasv ){
            $_destTypeOpNameHash[$destTypeOps[$d_typeOpAliask]] = $d_typeOpAliask;
            if( $d_typeOpAliasv ){
                foreach( explode('|',$d_typeOpAliasv) as $d_typeOpAliasVName )
                    $_destTypeOpAliasHash[$d_typeOpAliasVName] = $destTypeOps[$d_typeOpAliask];
            }
        }
        foreach( $sourcePropsv['optionAlias'] as $s_typeOpAliask => $s_typeOpAliasv ){
            // -------------------------------
            if( $s_typeOpAliasv ){
                foreach( explode('|',$s_typeOpAliasv) as $s_typeOpAliasVName ){
                    if( isset( $_destTypeOpAliasHash[$s_typeOpAliasVName] ) ){
                        $this->typePropsHash[$sourcePropsk]['propsSelectHash'][$s_typeOpAliask] = $_destTypeOpNameHash[$_destTypeOpAliasHash[$s_typeOpAliasVName]];
                        break;
                    }
                }
            }

            // ---------------------------------
            if( $s_typeOpAliasv && !isset($this->typePropsHash[$sourcePropsk]['propsSelectHash'][$s_typeOpAlias]) ){
                foreach( explode('|', $s_typeOpAliasv) as $s_typeOpAliasVName ){
                    if( isset( $_destTypeOpNameHash[$s_typeOpAliasVName] ) ){
                        $this->typePropsHash[$sourcePropsk]['propsSelectHash'][$s_typeOpAliask] = $_destTypeOpNameHash[$s_typeOpAliasVName];
                        break;
                    }
                }
            }

            // ---------------------------------
            if( !isset($this->typePropsHash[$sourcePropsk]['propsSelectHash'][$s_typeOpAlias]) ){
                if( isset( $_destTypeOpAliasHash[$sourcePropskv['options'][$s_typeOpAliask]] ) ){
                    $this->typePropsHash[$sourcePropsk]['propsSelectHash'][$s_typeOpAliask] = $_destTypeOpNameHash[$_destTypeOpAliasHash[$sourcePropskv['options'][$s_typeOpAliask]]];
                }
            }

            // --------------------------------
            if( !isset($this->typePropsHash[$sourcePropsk]['propsSelectHash'][$s_typeOpAlias]) ){
                if( isset( $_destTypeOpNameHash[$sourcePropsv['options'][$s_typeOpAliask]] ) ){
                    $this->typePropsHash[$sourcePropsk]['propsSelectHash'][$s_typeOpAliask] = $_destTypeOpNameHash[$sourcePropsv['options'][$s_typeOpAliask]];
                }
            }

        }
    }

    function _correspondDiffProps($destTypeOpAlias, $destTypeOps,$sourcePropsk ,$sourcePropsv){
        if( $this->typePropsHash[$sourcePropsk]['source_type'] == 'select' ){
            foreach( $sourcePropsv['optionAlias'] as $s_typeOpAliask => $s_typeOpAliasv ){
                $this->typePropsHash[$sourcePropsk]['options']['source'][$s_typeOpAliask] = array(
                    'name' => $sourcePropsv['options'][$s_typeOpAliask] ,
                    'alias' => $s_typeOpAliasv
                );
            }
        }
        if( $this->typePropsHash[$sourcePropsk]['dest_type'] == 'select' ){
            foreach( $destTypeOpAlias as $d_typeOpAliask => $d_typeOpAliasv ){
                $this->typePropsHash[$sourcePropsk]['options']['dest'][$d_typeOpAliask] = array(
                    'name' => $destTypeOps[$d_typeOpAliask] ,
                    'alias' => $d_typeOpAliasv
                );
            }
        }
    }

    /**
     *
     * return array(
     *     source brand id => dest brand id
     * )
     */
    function brandTransformCheck(){
        $oBrand = $this->system->loadModel('goods/brand');
        $sourceBrand = $oBrand->getTypeBrands($this->sourceType['type_id']);
        $destBrand = $oBrand->getTypeBrands($this->destType['type_id']);

        $sourceBrandHash = $this->_createAliasNameIdHash( $sourceBrand, 'brand_keywords', 'brand_name', 'brand_id' );
        $destBrandHash = $this->_createAliasNameIdHash( $destBrand, 'brand_keywords', 'brand_name', 'brand_id' );

        foreach( $destBrandHash['name2id'] as $bk => $bv ){
            if( isset( $sourceBrandHash['name2id'][$bk] ) )
                $this->brandTransformHash[$sourceBrandHash['name2id'][$bk]] = $bv;
            if( isset( $sourceBrandHash['alias2name'][$bk] ) )
                $this->brandTransformHash[$sourceBrandHash['name2id'][$sourceBrandHash['alias2name'][$bk]]] = $bv;
        }

        foreach( $destBrandHash['alias2name'] as $bk => $bv ){
            if( isset( $sourceBrandHash['name2id'][$bk] ) )
                $this->brandTransformHash[$sourceBrandHash['name2id'][$bk]] = $sourceBrandHash['name2id'][$bv];
            if( isset( $sourceBrandHash['alias2name'][$bk] ) )
                $this->brandTransformHash[$sourceBrandHash['name2id'][$sourceBrandHash['alias2name'][$bk]]] = $sourcebrandHash['name2id'][$sourceBrandHash['alias2name'][$bk]];
        }

        return true;
    }

    function brandTransform(&$goods){
        $oBrand = $this->system->loadModel('goods/brand');
        $brandName = $oBrand->getFieldById($goods['brand_id'],array('brand_name'));
        $brandName = $brandName['brand_name'];
        foreach( $this->destType['props'] as $goodsp => $propsv ){
            if(
                (
                    strpos( '|'.$propsv['name'].'|'.$propsv['alias'].'|', '|品牌|') !== false ||
                    strpos( '|'.$propsv['name'].'|'.$propsv['alias'].'|', '|brand|') !== false
                ) && !$goods['p_'.$goodsp]
            ){
                if( $propsv['type'] == 'input' ){
                    $goods['p_'.$goodsp] = $brandName;
                }else{
                    foreach( $propsv['options'] as $propsOpk => $propsOpv ){
                        if( strpos( '|'.$propsOpv.'|'.$propsv['optionAlias'].'|',$brandName ) !== false ){
                            $goods['p_'.$goodsp] = $propsOpk;
                        }
                    }
                }
            }
        }

        if( isset( $this->brandTransformHash[$goods['brand_id']] ) ){
            $goods['brand_id'] = $this->brandTransformHash[$goods['brand_id']];
        }else{
            foreach( $this->sourceType['props'] as $pk => $pv ){
                if(
                    strpos( '|'.$pv['name'].'|'.$pv['alias'].'|','品牌' ) !== false ||
                    strpos( '|'.$pv['name'].'|'.$pv['alias'].'|','brand' ) !== false
                ){
                    if( $pv['type'] == 'input' ){
                        $propsBrandName = $goods['p_'.$pk];
                    }else if( $pv['type'] == 'select' ){
                        $propsBrandName = $pv['options'][$goods['p_'.$pk]];
                    }
                    break;
                }
            }
            if( $propsBrandName ){
                $sql = 'SELECT a.brand_id FROM sdb_brand a LEFT JOIN sdb_type_brand b ON a.brand_id = b.brand_id WHERE a.brand_name = "'.$propsBrandName.'" AND b.type_id = '.$this->destType['type_id'];
                $bId = $this->db->selectrow($sql);
                if( $bId )
                    $goods['brand_id'] = $bId['brand_id'];
            }
        }
        return true;
    }

    function paramsTransformCheck(){
        $sParamsHash = array(
            'alias2name' => array(),
            'name2id' => array()
        );
        $sParams = &$this->sourceType['params'];
        $dParams = &$this->destType['params'];
        foreach( $dParams as $dParamGroup => $dParamv ){
            foreach( $dParamv as $dParamName => $dParamAlias ){
                foreach( explode('|',$dParamAlias) as $dAlias ){
                    if( !$dAlias )
                        continue;
                    $dParamsHash['alias2name'][$dAlias] = $dParamName;
                }
                $dParamsHash['name2id'][$dParamName] = $dParamGroup;
            }
        }
        foreach( $sParams as $sParamGroup => $sParamv ){
            foreach( $sParamv as $sParamName => $sParamAlias ){
                foreach( explode('|',$sParamAlias) as $sAlias ){
                    if( !$sAlias )
                        continue;
                    if( isset( $dParamsHash['alias2name'][$sAlias] ) ){
                        $this->paramTransformHash[$sParamName] = array( 'group' => $dParamsHash['name2id'][$dParamsHash['alias2name'][$sAlias]] , 'name' => $dParamsHash['alias2name'][$sAlias] );
                        break;
                    }
                    if( !isset( $this->paramTransformHash[$sParamName] ) ){
                        if( isset( $dParamsHash['name2id'][$sAlias] ) ){
                            $this->paramTransformHash[$sParamName] = array( 'group' => $dParamsHash['name2id'][$sAlias] , 'name' => $sAlias);
                            break;
                        }
                    }
               }
                if( !isset( $this->paramTransformHash[$sParamName] ) && isset( $dParamsHash['alias2name'][$sParamName] ) )
                    $this->paramTransformHash[$sParamName] = array(
                        'group' => $dParamsHash['name2id'][$dParamsHash['alias2name'][$sParamName]] ,
                        'name' => $dParamsHash['alias2name'][$sParamName]
                    );
                if( !isset( $this->paramTransformHash[$sParamName] ) && isset( $dParamsHash['name2id'][$sParamName] ) )
                    $this->paramTransformHash[$sParamName] = array(
                        'group' => $dParamsHash['name2id'][$sParamName],
                        'name' => $sParamName
                    );
            }
        }
        return true;
    }

    function paramsTransform(&$goods){
        $gParams = $goods['params'];
        unset( $goods['params'] );
        foreach( $gParams as $sParamv ){
            foreach( $sParamv as $spk => $spv ){
                if( isset( $this->paramTransformHash[$spk] ) ){
                    $goods['params'][$this->paramTransformHash[$spk]['group']][$this->paramTransformHash[$spk]['name']] = $spv;
                }
            }
        }
        return true;
    }

    /**
     * 分析数据 迭代别名与名称和id
     * $data            需要分析的array数据
     * $alias           别名key名称
     * $name            name key名称
     * $id              id key 名称
     * $tag             别名分隔符 默认'|'
     * return array(
     *     'alias2name' => array(
     *         alias => name
     *     ),
     *     'name2id' => array(
     *         name => id
     *     )
     *)
     */
    function _createAliasNameIdHash( $data, $alias, $name, $id, $tag = '|' ){
        $rs = array();
        foreach( $data as $aData ){
            foreach( $aData[$alias] as $dataAlias ){
                if( !$dataAlias )
                    continue;
                $rs['alias2name'][$dataAlias] = $aData[$name];
            }
            $rs['name2id'][$aData[$name]] = $aData[$id];
        }
        return $rs;
    }

    function getList($cols,$filter='',$start=0,$limit=20,$orderType=null,$no_paging=false){
        $ident=md5(var_export(func_get_args(),1));
        if(!$this->_dbstorage[$ident]){
            if(!$cols){
                $cols = $this->defaultCols;
            }
            if(!empty($this->appendCols)){
                $cols.=','.$this->appendCols;
            }
            $orderType = $orderType?$orderType:$this->defaultOrder;
            $sql = 'SELECT '.$cols.' FROM '.$this->tableName.' WHERE '.$this->_filter($filter);

            if($orderType)$sql.=' ORDER BY '.(is_array($orderType)?implode($orderType,' '):$orderType);
            if($no_paging)
                $this->_dbstorage[$ident]=$this->db->select($sql);
            else{
                $this->_dbstorage[$ident]=$this->db->selectLimit($sql,$limit,$start);
            }
        }
        return $this->_dbstorage[$ident];
    }

}
?>
