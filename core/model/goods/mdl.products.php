<?php
/**
 * mdl_product
 *
 * @uses modelFactory
 * @package
 * @version $Id: mdl.products.php 2042 2008-04-29 05:31:30Z ever $
 * @copyright 2003-2007 ShopEx
 * @author Wanglei <flaboy@zovatech.com>
 * @license Commercial
 */
include_once('shopObject.php');
class mdl_products extends shopObject{

    var $idColumn = 'goods_id';
    var $textColumn = 'name';
    var $defaultCols = 'bn,name,cat_id,price,store,marketable,brand_id,weight,d_order,uptime,type_id,supplier_id';
    var $appendCols = 'goods_id,image_default,thumbnail_pic,brief,pdt_desc,mktprice,big_pic';
    var $adminCtl = 'goods/product';
    var $defaultOrder = array('d_order',' DESC',',p_order',' DESC');
    var $tableName = 'sdb_goods';
    var $hasTag = true;
    var $typeName = 'goods';
    var $keywordsColumn='bn';
    var $globalTmp;        //零时全局变量
    var $name='商品';

    function getColumns($filter,$from = null){
        $ret = parent::getColumns();
        $ret['_cmd'] = array('label'=>__('操作'),'width'=>75,'html'=>'product/finder_command.html');
        if($from=='from'){
            $ret['keyword'] = array('type'=>'longtext','label'=>__('商品关键字'),'width'=>30,'editable'=>false,'hidden'=>true,'filtertype'=>'bool');
            //对多添加的筛选条件修改schema
            $ret['bn']['type'] = 'longtext';
            $ret['bn']['label'] = __('货号');
            $ret['bn']['width']=30;
            $ret['bn']['editable'] = false;
            $ret['bn']['filtertype'] = 'email';
            //规格
            $ret['spec_desc']['type']='bool';
            $ret['spec_desc']['editable']=true;
            $ret['spec_desc']['filtertype'] = 'yes';
            $ret['spec_desc']['label'] = __('是否多规格');
            $ret['spec_desc']['required'] = false;
            $ret['spec_desc']['hidden'] = true;
            $ret['spec_desc']['filterdefalut']= false;
            //销售价
            $ret['price']['default']= '';
            //分类
            $ret['cat_id']['type']= 'object:goods/productCat';
            $ret['cat_id']['required']= true;
            $ret['cat_id']['default']= '';
            $ret['cat_id']['width']= 75;
            $ret['cat_id']['label']= __('分类');
            $ret['cat_id']['editable']= true;
            $ret['cat_id']['filtertype']= 'yes';
            $ret['cat_id']['filterdefalut']= true;

            //供应商
            if($this->system->getConf('certificate.distribute')){
                //开启分销权限
                $ret['supplier_id']['type'] = 'object:distribution/supplier';
                $ret['supplier_id']['requited'] = false;
                $ret['supplier_id']['default'] = '';
                $ret['supplier_id']['filtertype'] = 'yes';
                $ret['supplier_id']['filterdefalut'] = true;
            }
        }
        return $ret;
    }

    function modifier_supplier_id(&$rows){
        $oSupplier = $this->system->loadModel('distribution/supplier');
        foreach($rows as $k => $v){
            if($v) {
                $rows[$k] = $oSupplier->getSupplierInfo($v,'supplier_brief_name');
                $rows[$k] = $rows[$k]['supplier_brief_name'];
            }
        }
    }

    function searchOptions(){
        $arr = parent::searchOptions();
        return array_merge($arr,array(
                'bn'=>__('货号'),
                'keyword'=>__('商品关键字'),
            ));
    }
    function modifier_marketable(&$row){
        foreach( $row as $k => $v ){
            $row[$k] = ($v=='true')?'是':'<font class="cell-inside" color="red">否</font>';
        }
    }

    function filter_getList($g){/*前台搜索商品规格筛选*/
        $sql='select goods_id from sdb_products where pdt_desc like \'%'.$g['pdt_desc'].'%\'and marketable=\'true\' and goods_id ='.$g['goods_id'].'';
        return $this->db->select($sql);
    }

    function getList($cols,$filter='',$start=0,$limit=20,$orderType=null){
        if(!function_exists('goods_list')) require(CORE_INCLUDE_DIR.'/core/goods.list.php');
          return goods_list($cols,$filter,$start,$limit,$orderType, $this);
    }
   
    function _getGoodsIdList(){
        return $this->db->select("SELECT goods_id FROM sdb_goods");
    }

    function modifier_cat_id(&$rows){
        foreach($rows as $k => $v){
            if($v < 1) $rows[$k] = __('<span class="cell" style="border:0px;color:#F90;">未分类</span>');
            else{
                $oCat = &$this->system->loadModel('goods/productCat');
                $aCat = $oCat->instance($v, 'cat_name');
                $rows[$k] = $aCat['cat_name'];
            }
        }
    }

    function _filter($filter,$tbase=''){
        if(isset($filter['keyword'])){
            $filter['keyword'] = addslashes($filter['keyword']);
        }
        if(!function_exists('goods_filter')) require(CORE_INCLUDE_DIR.'/core/goods.filter.php');
        $where = goods_filter($filter,$this);
        return parent::_filter($filter,$tbase,$where);
    }

    function events(){
        $ret = array(
            __LINE__=>array('label'=>__('价格升高'),'params'=>array(
                    'has_sup'=>array('label'=>__('价格升高量'),'type'=>'number'),
                )),
            __LINE__=>array('label'=>__('价格减少'),'params'=>array(
                    'has_sup'=>array('label'=>__('价格降低量'),'type'=>'number'),
                )),
            __LINE__=>array('label'=>__('库存增加'),'params'=>array(
                    'has_sup'=>array('label'=>__('库存增加量'),'type'=>'number'),
                )),
            __LINE__=>array('label'=>__('库存减少'),'params'=>array(
                    'has_sup'=>array('label'=>__('库存减少量'),'type'=>'number'),
                )),
            __LINE__=>array('label'=>__('上架')),
            __LINE__=>array('label'=>__('下架')),
            __LINE__=>array('label'=>__('删除')),
            __LINE__=>array('label'=>__('被下单')),
            __LINE__=>array('label'=>__('被访问')),
            );
        $global_params = array(
                __LINE__=>array('label'=>__('库存'),'type'=>'number'),
                __LINE__=>array('label'=>__('价格'),'type'=>'number'),
                __LINE__=>array('label'=>__('本周销售量'),'type'=>'number'),
                __LINE__=>array('label'=>__('总销售量'),'type'=>'number'),
                __LINE__=>array('label'=>__('本周访问量'),'type'=>'number'),
                __LINE__=>array('label'=>__('总访问量'),'type'=>'number'),
            );
        foreach($ret as $k=>$v){
            if($ret[$k]['params']){
                $ret[$k]['params'] = array_merge($ret[$k]['params'],$global_params);
            }else{
                $ret[$k]['params'] = &$global_params;
            }
        }
        return $ret;
    }

    function getGoodsIdByBn( $bn , $searchType = 'has') {

        switch($searchType){
            case'nohas':
                $goodsId = $this->db->select('SELECT g.goods_id FROM sdb_goods g LEFT JOIN sdb_products p ON g.goods_id = p.goods_id WHERE g.bn NOT LIKE "%'.$bn.'%" OR p.bn NOT LIKE "%'.$bn.'%"');
                break;
            case'tequal':
                $goodsId = $this->db->select('SELECT g.goods_id FROM sdb_goods g LEFT JOIN sdb_products p ON g.goods_id = p.goods_id WHERE g.bn in( "'.$bn.'") OR p.bn in( "'.$bn.'")');
                break;
            case'has':
            default:
                $goodsId = $this->db->select('SELECT g.goods_id FROM sdb_goods g LEFT JOIN sdb_products p ON g.goods_id = p.goods_id WHERE g.bn LIKE "%'.$bn.'%" OR p.bn LIKE "%'.$bn.'%"');
                break;
            case'head':
                $goodsId = $this->db->select('SELECT g.goods_id FROM sdb_goods g LEFT JOIN sdb_products p ON g.goods_id = p.goods_id WHERE g.bn LIKE "'.$bn.'%" OR p.bn LIKE "'.$bn.'%"');
                break;
            case'foot':
                $goodsId = $this->db->select('SELECT g.goods_id FROM sdb_goods g LEFT JOIN sdb_products p ON g.goods_id = p.goods_id WHERE g.bn LIKE "%'.$bn.'" OR p.bn LIKE "%'.$bn.'"');
                break;
        }

        $rs = array();
        foreach( $goodsId as $key=>$val) {
            if(!in_array($val['goods_id'],$rs)){
                $rs[] = $val['goods_id'];
            }
        }

        return $rs;
    }

    function wFilter($words){
        $replace = array(",","+");
        $enStr=preg_replace("/[^chr(128)-chr(256)]+/is"," ",$words);
        $otherStr=preg_replace("/[chr(128)-chr(256)]+/is"," ",$words);
        $words=$enStr.' '.$otherStr;
        $return=str_replace($replace,' ',$words);
        $word=preg_split('/\s+/s',trim($return));
        $GLOBALS['search_array']=$word;

        $oGoods = &$this->system->loadModel('trading/goods');
        foreach($word as $k=>$v){
            if($v){
                $goodsId = array();
                foreach($oGoods->getGoodsIdByKeyword(array($v)) as $idv)
                    $goodsId[] = $idv['goods_id'];
                foreach( $this->db->select('SELECT goods_id FROM sdb_products WHERE bn = \''.trim($v).'\' ') as $pidv)
                    $goodsId[] = $pidv['goods_id'];
                $sql[]='(name LIKE \'%'.$word[$k].'%\' or bn like \''.$word[$k].'%\' '.( $goodsId?' or goods_id IN ('.implode(',',$goodsId).') ':'' ).')';
            }
        }
        return implode('and',$sql);
    }


    function getSparePrice(&$list,$memberLevel,$onMarketable = true){
        if(!function_exists('goods_get_spare_price')) require(CORE_INCLUDE_DIR.'/core/goods.get_spare_price.php');
        return goods_get_spare_price($list,$memberLevel,$onMarketable , $this);
    }

    function orderBy($id=null){
        $order=array(
            array('label'=>__('默认'),'sql'=>implode($this->defaultOrder,'')),
            array('label'=>__('按发布时间 新->旧'),'sql'=>'last_modify desc'),
            array('label'=>__('按发布时间 旧->新'),'sql'=>'last_modify'),
            array('label'=>__('按价格 从高到低'),'sql'=>'price desc'),
            array('label'=>__('按价格 从低到高'),'sql'=>'price'),
            array('label'=>__('访问周次数'),'sql'=>'view_w_count desc'),
            array('label'=>__('总访问次数'),'sql'=>'view_count desc'),
            array('label'=>__('周购买次数'),'sql'=>'buy_count desc'),
            array('label'=>__('总购买次数'),'sql'=>'buy_w_count desc'),
            array('label'=>__('评论次数'),'sql'=>'comments_count desc'),
        );
        if($id){
            return $order[$id];
        }else{
            return $order;
        }

    }

    function getLastModify($_time){
        if(is_array($_time)){
            $result = $this->db->selectRow('SELECT last_modify FROM sdb_goods WHERE goods_id IN ("'.$_time[0].'","'.$_time[1].'") Order By last_modify Desc');
        }else{
            $result = $this->db->selectRow("SELECT last_modify FROM sdb_goods Order By last_modify Desc");
        }

        return $result['last_modify'];
    }
    function getPath($gid,$method=null){
        $row = $this->db->selectrow('select cat_id,name from sdb_goods where goods_id='.intval($gid));
        $goods = &$this->system->loadModel('goods/productCat');
        $ret = $goods->getPath($row['cat_id'],$method);
        $ret[] = array('type'=>'goods','title'=>$row['name']);
        return $ret;
    }
    function getFilterByTypeId($p){
        if(!function_exists('goods_filter_of_type')) require(CORE_INCLUDE_DIR.'/core/goods.filter_of_type.php');
        return goods_filter_of_type($p , $this);
    }
    function getFilter($p){
        if(!function_exists('goods_get_filter')) require(CORE_INCLUDE_DIR.'/core/goods.get_filter.php');
        return goods_get_filter($p , $this);
    }

    function setEnabled($finderResult,$status){
        $where = $finderResult? $this->_filter($finderResult):'goods_id in ('.implode(',',$finderResult['goods_id']).')';
        $sql = 'update sdb_goods set marketable="'.($status?'true':'false').'" where '.$where;
        $this->db->exec($sql);
        if(isset($finderResult['goods_id'])){
            if($status) $sta = 'true';
            else $sta = 'false';
            foreach($finderResult['goods_id'] as $key=>$val){
                if($val != '_ALL_'){
                $oGoods = &$this->system->loadModel('trading/goods');
                $oGoods->updateUpDownTime($sta,$val);
                }
            }
        }
        $status = &$this->system->loadModel('system/status');
        $status->count_goods_online();
        $status->count_goods_hidden();
        $status->count_galert();

        return true;
    }

    function setDisabled($aGid, $status='true'){
        foreach($aGid as $gid){
            $this->db->exec('UPDATE sdb_products SET disabled = '.$this->db->quote($status).' WHERE goods_id = '.intval($gid));
        }
        $status = &$this->system->loadModel('system/status');
        $status->count_goods_online();
        $status->count_goods_hidden();
        $status->count_galert();
        return true;
    }

    /**
     * getFieldById
     *
     * @param array $aFeild
     * @param int $id
     * @access public
     * @return void
     */
    function getFieldById($id, $aFeild=array('*')){
        $sqlString = "SELECT ".implode(',', $aFeild)." FROM sdb_products WHERE product_id = ".intval($id);
        return $this->db->selectrow($sqlString);
    }

    /**
     * 通过bn号查找货品信息 (from b2c 2009-11-13 13:18 wubin)
     *
     */
    function getFieldByBn($bn, $aFeild=array('*')){
        $sqlString = "SELECT ".implode(',', $aFeild)." FROM sdb_products WHERE bn = '".$bn."'";
        return $this->db->selectrow($sqlString);
    }

    function toUpdateStore($productId, $goodsId=0, $number=0, $gtype='goods'){
        if($gtype=='goods'){
            $aProduct = $this->getFieldById($productId, array('goods_id,store,freez'));
            if($aProduct['store'] !== null){
                $this->db->exec("UPDATE sdb_products SET store = ".($aProduct['store']>intval($number) ? "store - ".intval($number) : 0)
                        .", freez = ".($aProduct['freez']>intval($number) ? "freez - ".intval($number) : 0)
                        ." WHERE product_id = ".intval($productId));
            }
        }else{
            $aProduct['goods_id'] = $productId;
        }

        $g = &$this->system->loadModel('trading/goods');
        $aGoods = $g->getFieldById($aProduct['goods_id'], array('store'));
        if($aGoods['store'] !== null){
            $this->db->exec("UPDATE sdb_goods SET store = ".($aGoods['store']>intval($number) ? "store - ".intval($number) : 0)
                    ." WHERE goods_id = ".intval($aProduct['goods_id']));
        }
        $status = &$this->system->loadModel('system/status');
        $status->count_galert();
    }

    function updateRate($aGid){
        $aBakid = $aGid;
        foreach($aGid as $gid1){
            $aInsert['goods_1'] = $gid1;
            $aInsert['manual'] = 'both';
            foreach($aBakid as $gid2){
                if($gid1 != $gid2){
                    $aRet = $this->db->select('SELECT rate FROM sdb_goods_rate
                        WHERE ((goods_1 = '.intval($gid1).' AND goods_2 = '.intval($gid2)
                        .') OR (goods_1 = '.intval($gid2).' AND goods_2 = '.intval($gid1).')) AND rate < 100');
                    $aInsert['goods_2'] = $gid2;
                    if(count($aRet) > 0){
                        $aInsert['rate'] = ($aRet[0]['rate']>98 ? 99: $aRet[0]['rate']+1);
                        $this->db->exec('UPDATE sdb_goods_rate SET rate = '.$this->db->quote($aInsert['rate'])
                            .' WHERE (goods_1 = '.intval($gid1).' AND goods_2 = '.intval($gid2)
                            .') OR (goods_1 = '.intval($gid2).' AND goods_2 = '.intval($gid1).')');
                    }else{
                        $aInsert['rate'] = 1;
                        $rs = $this->db->exec('SELECT * FROM sdb_goods_rate WHERE 0=1');
                        $sql = $this->db->getUpdateSQL($rs, $aInsert);
                        if($sql) $this->db->exec($sql);
                    }
                }
            }
            reset($aBakid);
        }
        return true;
    }

    function toInsertLink($gid, $aData){
        if(!function_exists('goods_insert_link')) require(CORE_INCLUDE_DIR.'/core/goods.insert_link.php');
        return goods_insert_link($gid, $aData , $this);
    }

    //for export
    function getTypeTitles($typeid){
        $g = &$this->system->loadModel('goods/gtype');
        $gtype = $g->getTypeObj($typeid,$name);
        $return = array();
        foreach($gtype['props'] as $k=>$v){
            $return['p_'.$k] = 'props:'.$gtype['props'][$k]['name'];
        }
        foreach($gtype['params'] as $k=>$group){
            if($group['groupitems']&&is_array($group['groupitems'])){
                foreach($group['groupitems'] as $k1=>$v1){
                    $return['a_'.$group['groupname'].'->'.$v1['itemname']] = 'params:'.$group['groupname'].'->'.$v1['itemname'];
                }
            }
        }
        return $return;
    }

    function getMarketGoods($type){
        $aRet = $this->db->selectRow('SELECT count(goods_id) as goodscount FROM sdb_goods where marketable="'.$type.'" and goods_type="normal" and disabled="false" ');
        return $aRet['goodscount'];
    }

    //for export
    function getColTitles($cols=''){
        $cols = explode(',',$cols);
        $columns = $this->getColumns();
        $return = array();
        foreach($columns as $k=>$v){
            if(in_array($k,$cols)){
                $return[$k] = 'col:'.$v['label'];
            }
        }
        return $return;
    }

    //for export
    function getPriceTitles(){
        $l = &$this->system->loadModel('member/level');
        $level = $l->getMLevel();
        $return = array();
        foreach($level as $k=>$v){
            $return['m_'.$v['member_lv_id']] = 'price:'.$v['name'];
        }
        return $return;
    }

    //返回商品导出数组  其中 参数表的key为 a_组名|||参数名
    function getGoodsExportData($v,$proto,$t_name,$props,$params){
        if(!function_exists('goods_export')) require(CORE_INCLUDE_DIR.'/core/goods.export.php');
        return goods_export($v,$proto,$t_name,$props,$params , $this);
    }

    function getTypeExportTitle(&$gtype){
        $id_title = array('t_name'=>'','bn'=>__('bn:商品货号'),'i_bn'=>__('ibn:规格货号'));
        $g = &$this->system->loadModel('goods/gtype');
        $id_title['t_name'] = '*:'.$gtype['name'];
        $col_title1 = $this->getColTitles('name,cat_id,marketable,brand,spec,mktprice,cost,price');
        $mp_title = $this->getPriceTitles();
        $col_title2 = $this->getColTitles('store,store_place,weight,unit,brief,intro,thumbnail_pic,image_file');
        if($gtype['type_id']){
            $type_title = $this->getTypeTitles($gtype['type_id']);
        }
        return array_merge($id_title,$col_title1,$mp_title,$col_title2,$type_title);
    }

    function checkImportData($aData,$aFile){
        if(!function_exists('goods_check_import')) require(CORE_INCLUDE_DIR.'/core/goods.check_import.php');
        return goods_check_import($aData,$aFile, $this);
    }

    function writeData(){
        if(!function_exists('goods_insert')) require(CORE_INCLUDE_DIR.'/core/goods.insert.php');
        return goods_insert($this);
    }

    function importGoods(&$proto,$gtype){
        if(!function_exists('goods_import')) require(CORE_INCLUDE_DIR.'/core/goods.import.php');
        return goods_import($proto,$gtype , $this);
    }

    function checkGoodsSpec(&$proto){
        if(!function_exists('goods_check_gspec')) require(CORE_INCLUDE_DIR.'/core/goods.check_gspec.php');
        return goods_check_gspec($proto , $this);
    }

    function get_arr_specid_by_name($data){
        $objSpec = &$this->system->loadModel('goods/specification');
        $aSpec = $objSpec->getSpecidListByName($data);
        foreach($data as $v){
            foreach($aSpec as $rows){
                if($rows['spec_name'] == $v){
                    $aTmp[$rows['spec_id']] = $rows['spec_name'];
                    break;
                }
            }
        }
        return $aTmp;
    }

    //$isSpec是否多规格商品；false否 true=是
    function importProduct($proto, $isSpec=false){
        if(!function_exists('goods_import_product')) require(CORE_INCLUDE_DIR.'/core/goods.import_product.php');
        return goods_import_product($proto, $isSpec, $this);
    }

    function insertCsvData(){
        $handle = @fopen(HOME_DIR.'/tmp/uploadGoodsCsvData', "r");
        if($handle){
            while (!feof($handle)) {
                $buffer = fgets($handle, 32768);
                $aData = unserialize($buffer);
            if($aData['name'] == 'goods'){
                    if($goodsMark && $aPdtDesc){
            if($aPdtDesc['store'] === '' || $aPdtDesc['store'] === null){
                $sql = 'UPDATE sdb_goods SET store = null,pdt_desc='.$this->db->quote(serialize($aPdtDesc['pdt_desc'])).' WHERE goods_id='.intval($goodsId);
            }else{
                $sql = 'UPDATE sdb_goods SET pdt_desc='.$this->db->quote(serialize($aPdtDesc['pdt_desc'])).' WHERE goods_id='.intval($goodsId);
            }
                        $this->db->exec($sql);
                        $aPdtDesc = array();

            }
            if(!array_key_exists('store', $aPdtDesc)){
            $aPdtDesc = array('store' => 0);
            }

                    if(!($goodsId = $this->importGoodsLine($aData['content'])) && $aData['content']['goods_id']){
                        $goodsId = $aData['content']['goods_id'];
                    }
                    $goodsMark = true;
                }
                if($aData['name'] == 'product'){
                    $aData['content']['goods_id'] = $goodsId;
                    $this->importProductLine($aData['content'], $aPdtDesc);
                }
            }

            if($goodsId && $aPdtDesc){
        if($aPdtDesc['store'] === '' || $aPdtDesc['store'] === null){
            $sql = 'UPDATE sdb_goods SET store = null,pdt_desc='.$this->db->quote(serialize($aPdtDesc['pdt_desc'])).' WHERE goods_id='.intval($goodsId);
        }else{
            $sql = 'UPDATE sdb_goods SET pdt_desc='.$this->db->quote(serialize($aPdtDesc['pdt_desc'])).' WHERE goods_id='.intval($goodsId);
        }
                $this->db->exec($sql);
            }
            fclose($handle);
        }
        @unlink(HOME_DIR.'/tmp/uploadGoodsCsvData');
    }

    function csvLog($errType, $aData){

        switch($errType){
            case 'error':
            $fp = fopen(HOME_DIR.'/tmp/uploadGoodsCsvError','a');
            $out = $aData;
            fwrite($fp,$out);
            fclose($fp);
            break;
            case 'warning':
            $fp = fopen(HOME_DIR.'/tmp/uploadGoodsCsvWarning','a');
            $out = $aData;
            fwrite($fp,$out);
            fclose($fp);
            break;
            case 'data':
            $fp = fopen(HOME_DIR.'/tmp/uploadGoodsCsvData','a');
            $out = "\n".serialize($aData);
            fwrite($fp,$out);
            fclose($fp);
            break;
            case 'tmp':
            $fp = fopen(HOME_DIR.'/tmp/uploadGoodsCsvTmp','a');
            $out = "\n".serialize($aData);
            fwrite($fp,$out);
            fclose($fp);
            break;
        }
    }

    function importGoodsLine(&$aData){
        if(!function_exists('goods_import_goodsline')) require(CORE_INCLUDE_DIR.'/core/goods.import_goodsline.php');
        return goods_import_goodsline($aData , $this);
    }

    //导入货品行
    function importProductLine(&$aData, &$aPdtDesc){
        if(!function_exists('goods_import_productline')) require(CORE_INCLUDE_DIR.'/core/goods.import_productline.php');
        return goods_import_productline($aData, $aPdtDesc , $this);
    }


    function countNum(){
        $conunt= $this->db->selectRow('select count("goods_id") as goodsnum from sdb_goods where disabled="false" and goods_type="normal"');
        return $conunt['goodsnum'];
    }

    function getProductLevel($productId){
        $oLevel = &$this->system->loadModel('member/level');
        $levelItem = $oLevel->getFieldById(intval($this->system->request['member_lv']));
        $priceDisplayType = 0;
        if($levelItem['lv_type'] == 'retail') //零售会员
            $priceDisplayType = $this->system->getConf('site.retail_member_price_display');
        else if($levelItem['lv_type'] == 'wholesale') //批发会员
            $priceDisplayType = $this->system->getConf('site.wholesale_member_price_display');
        else
            return null;

        $sql = 'SELECT member_lv_id, name FROM sdb_member_lv WHERE disabled = "false" ';
        switch ( intval($priceDisplayType) ){
            case 0:
                return null;
                break;
            case 1:
                $sql.=' AND lv_type = "retail" ';
                break;
            case 2:
                $sql.=' AND lv_type = "wholesale" ';
                break;
            case 3:
                break;
        }
        if(intval($priceDisplayType))
            return $this->db->select($sql);
        return null;
    }

    function addSellLog($data){

        $orderData = $this->db->selectrow('SELECT o.member_id, m.uname,o.ship_email FROM sdb_orders o LEFT JOIN sdb_members m ON o.member_id = m.member_id WHERE o.order_id = '.$data['order_id']);
        $orderItem = $this->db->select('SELECT p.price, p.goods_id, i.product_id, p.name,p.pdt_desc, i.nums FROM sdb_order_items i LEFT JOIN sdb_products p ON p.product_id = i.product_id WHERE i.order_id = '.$data['order_id']);
        foreach( $orderItem as $iKey => $iValue ){
            $sql = 'INSERT INTO sdb_sell_logs (member_id,name,price,goods_id,product_id,product_name,pdt_desc,number,createtime) VALUES ( "'.($orderData['member_id']?$orderData['member_id']:0).'", "'.($orderData['uname']?$orderData['uname']:$orderData['ship_email']).'", "'.$iValue['price'].'", "'.$iValue['goods_id'].'", "'.$iValue['product_id'].'", "'.htmlspecialchars($iValue['name']).'", "'.$iValue['pdt_desc'].'" , "'.$iValue['nums'].'", "'.time().'" )';
            $this->db->exec($sql);
        }
    }

    function getGoodsSellLogList($gid,$page,$limit=20){
        $sql = 'SELECT * FROM sdb_sell_logs WHERE goods_id = '.$gid.' ORDER BY log_id DESC ';
        return $this->db->selectPager($sql,$page,$limit);
    }

    function getBatchEditInfo($filter){
        $r = $this->db->selectrow('select count( goods_id ) as count from sdb_goods where '.$this->_filter($filter));
        return $r;
    }

    function batchUpdateByOperator( $goods_id, $tableName, $updateName , $updateValue, $operator=null , $fromName = null ){
        $sql = '';  //review: 注意$this->db->quote 必要的数据
        if( $operator == '-' ){

            $sql = 'UPDATE '.$tableName.' SET '.$updateName.' = 0 WHERE '.( strstr($tableName, ',')?' a.goods_id = b.goods_id AND a.':'' ).' goods_id IN ('.implode(',',$goods_id).') AND '.$updateName.' IS NOT NULL AND '.( $fromName?$fromName:$updateName ).'<='.$updateValue;
            $this->db->exec($sql);

            $sql = 'UPDATE '.$tableName.' SET '.$updateName.' = '.( $fromName?$fromName:$updateName ).' '.$operator.' '.$updateValue.' WHERE '.( strstr($tableName, ',')?' a.goods_id = b.goods_id AND a.':'' ).' goods_id IN ('.implode(',',$goods_id).') AND '.$updateName.' IS NOT NULL AND '.( $fromName?$fromName:$updateName ).'>'.$updateValue;
            $this->db->exec($sql);


        }else{

            $sql = 'UPDATE '.$tableName.' SET '.$updateName.' = round('.( $operator?( $fromName?$fromName:$updateName ).' '.$operator.' '.$updateValue:'"'.$updateValue.'"' ).', 3) WHERE '.( strstr($tableName, ',')?' a.goods_id = b.goods_id AND a.':'' ).' goods_id IN ('.implode(',',$goods_id).') ';
            $this->db->exec($sql);
        }
        return true;
    }

    function batchUpdateMemberPriceByOperator( $goods_id, $updateLvId, $updateValue, $operator=null , $fromName = null ){
        if(!function_exists('goods_update_member_price')) require(CORE_INCLUDE_DIR.'/core/goods.update_member_price.php');
        return goods_update_member_price($goods_id, $updateLvId, $updateValue, $operator, $fromName , $this);
    }

    function synchronizationStore($goods_id){
        $storeSum1 = $this->db->select('SELECT goods_id FROM sdb_products WHERE goods_id in ('.implode(',',$goods_id).') AND store IS NULL GROUP BY goods_id');
        $nullStore = array();
        foreach( $storeSum1 as $aStore ){
            $nullStore[$aStore['goods_id']] = 1;
        }
        $storeSum = $this->db->select('SELECT goods_id, sum(store) as storesum FROM sdb_products WHERE goods_id in ('.implode(',',$goods_id).') GROUP BY goods_id');
        foreach($storeSum as $v){
            $this->db->exec('UPDATE sdb_goods SET store = '.( isset( $nullStore[$v['goods_id']] )?'null':intval($v['storesum']) ).' WHERE goods_id = '.intval($v['goods_id']));
        }
        return true;
    }

    function batchUpdateText( $goods_id, $updateType , $updateName , $updateValue ){ //review: 注意$this->db->quote 必要的数据
        $sql = 'UPDATE sdb_goods SET ';
        switch($updateType){
            case 'name':
                $sql .= $updateName.' = "'.$updateValue.'" WHERE goods_id in ('.implode(',',$goods_id).')';
                break;

            case 'add':
                $sql .= $updateName.' = CONCAT("'.$updateValue['front'].'",'.$updateName.',"'.$updateValue['after'].'") WHERE goods_id in ('.implode(',',$goods_id).')';
                break;

            case 'replace':
                $sql .= $updateName.' = REPLACE( '.$updateName.', "'.$updateValue['front'].'" , "'.$updateValue['after'].'" ) WHERE goods_id in ('.implode(',',$goods_id).') AND REPLACE( '.$updateName.', "'.$updateValue['front'].'" , "'.$updateValue['after'].'" ) != "" ';
                break;
        }
        $this->db->exec($sql);
        return true;
    }

    function batchUpdateInt( $goods_id, $updateName, $updateValue , $tableName = '' ){
        $sql = 'UPDATE '.( $tableName?$tableName:'sdb_goods').' SET '.$updateName.' = '.$updateValue.' WHERE goods_id in ( '.implode(',', $goods_id).' )';
        $this->db->exec($sql);
        return true;
    }

    function batchUpdateArray( $goods_id , $tableName, $updateName, $updateValue ){
        $addSql = array();
        foreach( $updateName as $k => $v )
            $addSql[] = $v.' = "'.$updateValue[$k].'" ';
        $sql = 'UPDATE '.$tableName.' SET '.implode(',', $addSql).' WHERE goods_id in ('.implode(',',$goods_id).') ';
        $this->db->exec($sql);
        return true;
    }

    function getGoodsIdByFilter($filter){
        $sql = 'SELECT goods_id FROM sdb_goods WHERE '.$this->_filter($filter);
        $goodsList = $this->db->select($sql);
        $func=create_function('$r','return$r["goods_id"];');
        return array_map($func,$goodsList);
    }

    function getProductLvPrice($goodsId){
        $sql = 'SELECT goods_id, bn, pdt_desc, product_id, cost, price , mktprice FROM sdb_products WHERE goods_id IN ('.implode(',',$goodsId).')';
        $proList = $this->db->select($sql);

        $levelList = $this->db->select('SELECT goods_id, product_id, level_id, price AS mprice FROM sdb_goods_lv_price WHERE goods_id IN ('.implode(',',$goodsId).')');
        $returnData = array();
        $lvPrice = array();
        foreach( $levelList as $level )
            $lvPrice[$level['product_id']][$level['level_id']] = $level['mprice'] ;

        foreach( $proList as $pro )
            $returnData[$pro['goods_id']][] = array('product_id'=>$pro['product_id'],'bn'=>$pro['bn'], 'pdt_desc'=>$pro['pdt_desc'], 'price'=>$pro['price'], 'lv_price'=>$lvPrice[$pro['product_id']], 'cost'=>$pro['cost'],'mktprice'=>$pro['mktprice'] );

        return $returnData;
    }

    function getProductStore($goodsId){
        $sql = 'SELECT goods_id, bn, pdt_desc, product_id, store FROM sdb_products WHERE goods_id IN ('.implode(',',$goodsId).')';
        $proList = $this->db->select($sql);
        $returnData = array();
        foreach( $proList as $pro )
            $returnData[$pro['goods_id']][] = array( 'product_id'=>$pro['product_id'],'bn'=>$pro['bn'], 'pdt_desc'=>$pro['pdt_desc'], 'store'=>$pro['store'] );
        return $returnData;
    }

    function batchUpdateStore($store){
        foreach( $store as $goods )
            foreach( $goods as $proId => $pstore )
                $this->db->exec('UPDATE sdb_products SET store = '.(intval($pstore)<0?0:intval($pstore)).' WHERE product_id = '.intval($proId));
        return true;
    }

    function batchUpdatePrice($pricedata){
        if(!function_exists('goods_update_price')) require(CORE_INCLUDE_DIR.'/core/goods.update_price.php');
        return goods_update_price($pricedata , $this);
    }

    function toTurnType($data,&$count){

        $cols = array(
            'goods_id','cat_id','type_id','spec_desc','pdt_desc',
            'p_1','p_2','p_3','p_4','p_5','p_6','p_7','p_8','p_9','p_10',
            'p_11','p_12','p_13','p_14','p_15','p_16','p_17','p_18','p_19','p_20',
            'p_21','p_22','p_23','p_24','p_25','p_26','p_27','p_28'
            );
        $goodsList = $this->getList(implode(',',$cols),$data['_filter'], ($data['step']-1)*100 , 100 );
        if( empty( $goodsList ) ){
            return 'finish';
            exit;
        }
        $count = count($goodsList);
        //做类型转换
        $oGoods = $this->system->loadModel('trading/goods');
        $oGType = $this->system->loadModel('goods/gtype');
        $oCat = $this->system->loadModel('goods/productCat');
        foreach( $goodsList as $aGoods ){

        $aGoods['products'] = $oGoods->getProducts($aGoods['goods_id']);
            switch($data['turn_data']){
                case 'cat':
                    if($data['trans_type_comm']==1){
                        $typeid = $oCat->getFieldById($data['type_value'], array('type_id'));      
                        $oldTypeId = $aGoods['type_id'];
                        $aGoods['type_id'] = $typeid['type_id'];
                        $aGoods['cat_id'] = $data['type_value'];
                    }else{
                        $oldTypeId = $aGoods['type_id'];
                        $aGoods['cat_id'] = $data['type_value'];
                    }
                    break;
                case 'type':
                default:
                    $oldTypeId = $aGoods['type_id'];
                    $aGoods['type_id'] = $data['type_value'];
                    break;
            }
            $aGoods['spec_desc'] = unserialize($aGoods['spec_desc']);
            $aGoods['pdt_desc'] = unserialize($aGoods['pdt_desc']);
            
            foreach( $aGoods['products'] as $prok => $aPro ){
                $aGoods['products'][$prok]['props'] = unserialize($aPro['props']);
            }
            foreach($aGoods as $gCols => $gVal){
                if( substr($gCols,0,2) == 'p_' && $gVal === null ){
                    unset($aGoods[$gCols]);
                }
            }
      
        if($oGType->typeTransform($oldTypeId , $aGoods['type_id'], $aGoods) ){
                foreach( $aGoods['products'] as $aPro ){
                    $rs = $this->db->exec('SELECT * FROM sdb_products WHERE product_id = '.$aPro['product_id']);
                    $proData = array(
                        'last_modify' => time(),
                        'props' => serialize($aPro['props'])
                    );
                    $sql = $this->db->getUpdateSQL($rs, $proData );
                    $this->db->exec($sql);
                }
                foreach( $aGoods['products'] as $prok => $prov ){
                    foreach( $prov['spec_value_id'] as $gSpecId => $gSpecVid ){
                        $indexData = array(
                            'type_id' => $aGoods['type_id'],
                            'spec_id' => $gSpecid,
                            'spec_value_id' => $gSpecVid,
                            'goods_id' => $aGoods['goods_id'],
                            'product_id' => $prov['product_id']
                        );
                    }
                }
                unset($aGoods['products']);
                $rs = $this->db->exec('SELECT * FROM sdb_goods WHERE goods_id = '.$aGoods['goods_id']);
                $aGoods['spec_desc'] = serialize($aGoods['spec_desc']);
                $aGoods['pdt_desc'] = serialize($aGoods['pdt_desc']);
                $sql = $this->db->getUpdateSQL($rs, $aGoods);
                if($this->db->exec($sql)){
                    $oGoods->updateGoodsIndex($aGoods['goods_id'],$indexData);
                }
            }
        }
        return 'continue';
    }

    function setProNameByGoodsId($gid,$name){
        $name=array('name' => $name);
        $rs = $this->db->exec('SELECT * FROM sdb_products  WHERE goods_id ='.$gid);
        $sql = $this->db->getUpdateSQL($rs, $name);
        return !$sql || $this->db->exec($sql);
    }

    function update( $data, $filter ){
        if( parent::update($data, $filter) == true ){
            $agids = $this->db->select('SELECT goods_id FROM sdb_goods WHERE '.$this->_filter($filter));
            $gids = array();
            foreach( $agids as $id ){
                $gids[] = $id['goods_id'];
            }
            $this->syncProNameByGoodsId($gids);
            return true;
        }else{
            return false;
        }
    }


    function syncProNameByGoodsId($gids){
        $sql = 'UPDATE sdb_products p , sdb_goods g SET p.name= g.name WHERE g.goods_id = p.goods_id AND g.goods_id IN ('.(implode(',',$gids)).')';
        return $this->db->exec($sql);
    }

    function getPicsByGoodsId($gids){

         $sql = 'select goods_id,thumbnail_pic,small_pic,big_pic from sdb_goods where goods_id IN ('.(implode(',',$gids)).')';
         return $this->db->select($sql);
    }

    function countGoodsNum(){
        $conunt= $this->db->selectRow('select count("goods_id") as goodsnum from sdb_goods');
        return $conunt['goodsnum'];
    }

}
