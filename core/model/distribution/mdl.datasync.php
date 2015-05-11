<?php
/**
 * mdl_datasync
 *
 * @uses modelFactory
 * @package
 * @version $Id: mdl.datasync.php 2009-04-30 05:31:30Z hujianxin $
 * @copyright 2003-2009 ShopEx
 * @author hujianxin <hjx@shopex.cn>
 * @license Commercial
 */

class mdl_datasync extends modelFactory{
    function mdl_datasync(){
        parent::modelFactory();

        $token = $this->system->getConf('certificate.token');
        $this->api = $this->system->api_call(PLATFORM,PLATFORM_HOST,PLATFORM_PATH,PLATFORM_PORT,$token);
    }

    /**
     * 检查该商品是否需要下载
     * 当商品信息更新时，如果该商品的类型、规格、品牌发生了变化，那么需要重新下载
     *
     * @param int $supplier_id
     * @param int $goods_id
     * @return boolean，需要下载类型、规格、品牌则为true，反之false
     */
    function checkGoodsDownload($supplier_id, $goods_id){
        $brand_flag = false;
        $type_flag = false;
        $spec_flag = false;

        if($tmp_data = $this->db->selectrow("SELECT command_info FROM sdb_data_sync_".floatval($supplier_id)." WHERE command=4 AND goods_id=".intval($goods_id))){
            $goods_info_tmp = unserialize($tmp_data['command_info']);
            $goods_info = $goods_info_tmp['goods_info'];
        }else{
            $goods_info = $this->api->getApiData('getGoodsByID',API_VERSION,array('supplier_id'=>$supplier_id,'id'=>$goods_id),true,true);
        }
        $brand_id = $goods_info['brand_id'];
        $type_id = $goods_info['type_id'];
        $spec_info = $this->api->getApiData('getSpecificationByGoodsID',API_VERSION,array('supplier_id'=>$supplier_id,'id'=>$goods_id),true,true);

        $brand_flag = $this->downloadBrand($supplier_id,$brand_id,NULL,false);
        $type_flag = $this->downloadType($supplier_id,$type_id,false);
        if(empty($spec_info)){
            $spec_flag = false;
        }else{
            foreach($spec_info as $spec){
                if($this->downloadSpecification($supplier_id,$spec,NULL,false)){
                    $spec_flag = true;
                    break;
                }
            }
        }

        return ($brand_flag || $type_flag || $spec_flag);
    }



    /**
     * 下载上游商品的分类结构到本地的某分类中
     * 如果上游商品的分类结构与本地的某分类下的一个分类结构完全一致，则直接将商品挂在本地的该分类结构下，如果不一致，则新增这个分类结构
     * 不关联分类结构和平台上原对应类型见的关联
     *
     * @param int $cat_id, 本地的分类id，0是根
     * @param int $supplier_id, 供应商id
     * @param int $supplier_cat_id, 上游商品的分类id
     * @return mixed, 1. 如果flag为true, 则返回 array(
     *                  'cat_id' => 如果需要下载了则为$supplier_cat_id插入本地分类表的cat_id，未下载为本地该分类结构的叶子分类的cat_id，
     *                  'cat_path' => xxx, name|name|name，如果没新增则为空
     *                )
     *                2. 如果flag为false，如果需要下载则返回 array('if_download'=>true)，不需要下载则返回 array('if_download'=>false,'local_cat_id'=>xx)
     */
    function downloadCat($cat_id,$supplier_id,$supplier_cat_id,$flag=true){
        $if_download = false;
        $return_cat = array();
        $cat_id = intval($cat_id);
        //根据$supplier_id,$supplier_cat_id，获取平台分类的信息$plat_cat_info
        if($tmp_data=$this->db->selectrow("SELECT * FROM sdb_sync_tmp WHERE supplier_id=".floatval($supplier_id)." AND s_type='goods_cat' AND ob_id=".intval($supplier_cat_id))){
            $plat_cat_info = unserialize($tmp_data['s_data']);
        }else{
            $plat_cat_info = $this->api->getApiData('getCategoryByID',API_VERSION,array('supplier_id'=>$supplier_id,'id'=>$supplier_cat_id),true,true);
        }

        if(!empty($plat_cat_info)){
            $plat_cat_path = explode(",",$plat_cat_info['cat_path']);

            if($cat_id != 0){
                $sql = "SELECT * FROM sdb_goods_cat WHERE cat_id=".intval($cat_id);
                $local_current_cat = $this->db->selectrow($sql);
            }

            $sql = "SELECT * FROM sdb_goods_cat WHERE parent_id=".intval($cat_id);
            $local_cats = $this->db->select($sql);

            //检查是否需要下载分类
            if($local_cats){
                $plat_cat_len = count($plat_cat_path);
                if(empty($plat_cat_path[0])){   //该分类在上游就是一级分类(lv=1)，判断和$local_cats中有没有相同的名字，如果有则不要下载了，没有则要下载
                    foreach($local_cats as $v){
                        /*if($v['cat_name'] == $plat_cat_info['cat_name']){*/
                        if(strcasecmp(trim($v['cat_name']), trim($plat_cat_info['cat_name'])) == 0){   //由于数据库查询时匹配名字采用的是case-insensitive，所以这边比对也用case-insensitive
                            $if_download = false;
                            $return_cat_id = $v['cat_id'];
                            break;
                        }else{
                            $if_download = true;
                        }
                    }
                }else{  //该分类在上游至少是二级分类以上
                    $tmp_cat_ids = array();
                    for($i=0;$i<$plat_cat_len;$i++){
                        if(empty($plat_cat_path[$i])){
                            break;
                        }

                        if($tmp_data=$this->db->selectrow("SELECT * FROM sdb_sync_tmp WHERE supplier_id=".floatval($supplier_id)." AND s_type='goods_cat' AND ob_id=".intval($plat_cat_path[$i]))){
                            $tmp_plat_cat_info = unserialize($tmp_data['s_data']);
                        }else{
                            $tmp_plat_cat_info = $this->api->getApiData('getCategoryByID',API_VERSION,array('supplier_id'=>$supplier_id,'id'=>$plat_cat_path[$i]),true,true);
                        }

                        if(empty($tmp_cat_ids)){//只是第一次会是空的，如果下面有哪次判断到$tmp_cat_ids为空时，则直接break，表示需要下载的
                            $tmp_local_cats = $local_cats;
                        }else{
                            $sql = "SELECT * FROM sdb_goods_cat WHERE parent_id IN (".implode(",",$tmp_cat_ids).")";
                            $tmp_local_cats = $this->db->select($sql);
                            $tmp_cat_ids = array(); //清空ids，保证只存这次的
                            if(empty($tmp_local_cats)){ //如果找不到，则说明符合上次$tmp_cat_ids中的子节点不存在符合平台的目录树结构
                                $if_download = true;
                                break;
                            }
                        }

                        foreach($tmp_local_cats as $v){
                            /*if($v['cat_name'] == $tmp_plat_cat_info['cat_name']){*/
                            if(strcasecmp(trim($v['cat_name']), trim($tmp_plat_cat_info['cat_name'])) == 0){   //由于数据库查询时匹配名字采用的是case-insensitive，所以这边比对也用case-insensitive
                                $tmp_cat_ids[] = $v['cat_id'];
                            }
                        }
                        if(empty($tmp_cat_ids)){
                            //如果找不到同名的非叶子节点，则退出，表示需要下载
                            $if_download = true;
                            break;
                        }

                        unset($tmp_plat_cat_info);
                        unset($tmp_local_cats);
                    }

                    //这个分支表示肯定是二级分类以上，只有在$if_download为true时，$tmp_cat_ids才为空，所以这里保证了$tmp_cat_ids肯定有值，需要用上游商品对应的那个最后一个节点$plat_cat_info来判断以$tmp_cat_ids为父节点的节点是否有相同的名字
                    if(!$if_download){
                        $if_download = true;    //假设是需要下载的，方便判断。因为只要有一个相同就设置$if_download=false不需要下载了
                        $sql = "SELECT * FROM sdb_goods_cat WHERE parent_id IN (".implode(",",$tmp_cat_ids).")";
                        $tmp_local_cats = $this->db->select($sql);
                        if(!empty($tmp_local_cats)){    //还有子节点则需要进一步判断，没有了就肯定$if_download=true需要下载
                            foreach($tmp_local_cats as $v){
                                /*if($v['cat_name'] == $plat_cat_info['cat_name']){*/
                                if(strcasecmp(trim($v['cat_name']), trim($plat_cat_info['cat_name'])) == 0){   //由于数据库查询时匹配名字采用的是case-insensitive，所以这边比对也用case-insensitive
                                    $if_download = false;
                                    $return_cat_id = $v['cat_id'];
                                    break;
                                }
                            }
                        }
                    }
                }
            }else{
                $if_download = true;
            }

            if($flag){
                if($if_download){
                    //$myparent:当前商品 的分类插入点
                    //$mycat_path:当前商品 的分类插入点的cat_path
                    $myparent_id = $cat_id;
                    if($cat_id != 0){
                        if($local_current_cat['cat_path']==","){
                            $mycat_path = $local_current_cat['cat_id'].",";
                        }else{
                            $mycat_path = $local_current_cat['cat_path'] . $local_current_cat['cat_id'] . ",";
                        }
                    }else{
                        $mycat_path = ",";
                    }

                    $plat_cat_len = count($plat_cat_path);
                    for($i=0;$i<$plat_cat_len;$i++){    //将该商品的分类路径（不包括自己），添加到本地分类中
                        if(empty($plat_cat_path[$i])){
                            break;
                        }

                        $plat_cat_id = $plat_cat_path[$i];
                        //根据$supplier_id,$plat_cat_id，获取平台该分类的信息，$tmp_plat_cat_info
                        if($tmp_data=$this->db->selectrow("SELECT * FROM sdb_sync_tmp WHERE supplier_id=".floatval($supplier_id)." AND s_type='goods_cat' AND ob_id=".intval($plat_cat_path[$i]))){
                            $tmp_plat_cat_info = unserialize($tmp_data['s_data']);
                        }else{
                            $tmp_plat_cat_info = $this->api->getApiData('getCategoryByID',API_VERSION,array('supplier_id'=>$supplier_id,'id'=>$plat_cat_path[$i]),true,true);
                        }

                        if($tmp_local_cats_info = $this->db->selectrow("SELECT cat_id FROM sdb_goods_cat WHERE cat_name='".addslashes($tmp_plat_cat_info['cat_name'])."' AND parent_id=".$myparent_id)){
                            $myparent_id = $tmp_local_cats_info['cat_id'];
                        }else{
                            $new_plat_cat_info = array(
                                'parent_id' => $myparent_id,
                                'supplier_id' => $supplier_id,
                                'supplier_cat_id' => $plat_cat_path[$i],
                                'cat_path' => $mycat_path,
                                'is_leaf' => 'false',
                                'cat_name' => $tmp_plat_cat_info['cat_name'],
                                'p_order' => $tmp_plat_cat_info['p_order'],
                                'goods_count' => $tmp_plat_cat_info['goods_count'],
                                'tabs' => $tmp_plat_cat_info['tabs'],
                                'finder' => $tmp_plat_cat_info['finder'],
                                'addon' => $tmp_plat_cat_info['addon'],
                                'child_count' => 0
                            );

                            $rs = $this->db->query("SELECT * FROM sdb_goods_cat WHERE 0=1");
                            $sql = $this->db->GetInsertSQL($rs, $new_plat_cat_info);
                            if($sql && !$this->db->exec($sql)){
                                trigger_error('SQL Error:'.$sql,E_USER_NOTICE);
                                return false;
                            }

                            $tmp_myparent_id = $myparent_id;
                            $myparent_id = $this->db->lastInsertId();

                            //更新父分类的is_leaf为false，以及child_count+1
                            $this->db->exec("UPDATE sdb_goods_cat SET child_count=child_count+1, is_leaf='false' WHERE cat_id=".intval($tmp_myparent_id));

                        }

                        $return_cat_path[] = $tmp_plat_cat_info['cat_name'];

                        if($mycat_path == ','){
                            $mycat_path = $myparent_id . ",";
                        }else{
                            $mycat_path .= $myparent_id . ",";
                        }
                    }

                    //添加该商品所在的分类到本地分类结构的最后
                    $new_plat_cat_info = array(
                        'parent_id' => $myparent_id,
                        'supplier_id' => $supplier_id,
                        'supplier_cat_id' => $supplier_cat_id,
                        'cat_path' => $mycat_path,
                        'is_leaf' => 'true',
                        'cat_name' => $plat_cat_info['cat_name'],
                        'p_order' => $plat_cat_info['p_order'],
                        'goods_count' => $plat_cat_info['goods_count'],
                        'tabs' => $plat_cat_info['tabs'],
                        'finder' => $plat_cat_info['finder'],
                        'addon' => $plat_cat_info['addon'],
                        'child_count' => 0
                    );

                    $return_cat_path[] = $plat_cat_info['cat_name'];

                    $rs = $this->db->query("SELECT * FROM sdb_goods_cat WHERE 0=1");
                    $sql = $this->db->GetInsertSQL($rs, $new_plat_cat_info);
                    if($sql && !$this->db->exec($sql)){
                        trigger_error('SQL Error:'.$sql,E_USER_NOTICE);
                        return false;
                    }

                    $return_cat_id = $this->db->lastInsertId();

                    //更新父分类的is_leaf为false，以及child_count+1
                    $this->db->exec("UPDATE sdb_goods_cat SET child_count=child_count+1, is_leaf='false' WHERE cat_id=".intval($myparent_id));

                }

                $return_cat['cat_id'] = $return_cat_id;
                $return_cat['cat_path'] = isset($return_cat_path)?implode("|",$return_cat_path):"";
                return $return_cat;
            }else{
                $local_cat_id = $return_cat_id;
                return $if_download?array('if_download'=>true):array('if_download'=>false,'local_cat_id'=>$local_cat_id);
            }
        }else{
            return false;
        }
    }

    /**
     * 下载上游商品的规格
     * 如果supplier_id和supplier_spec_id在规格表中存在，则判断该记录的规格名和平台规格名＋时间戳是否一致，一致就不操作，否则新增平台规格＋时间戳的新规格
     * 如果supplier_id和supplier_spec_id在规格表中不存在，新增平台规格＋时间戳的新规格
     *
     * @param int $supplier_id，供应商id
     * @param array $spec_info，上游商品的规格信息
     *                          array(
     *                              'spec_id' => xxx,
     *                              'spec_name' => xxx,
     *                              'spec_type' => xxx,
     *                              'spec_memo' => xxx,
     *                              'p_order' => xxx,
     *                              'struct' => array(
     *                                              array(
     *                                                  'spec_value_id' => xxx,
     *                                                  'spec_value' => xxx,
     *                                                  'spec_image' => xxx,
     *                                                  'p_order' => xxx
     *                                              ),
     *                                              array(
     *                                                  'spec_value_id' => xxx,
     *                                                  'spec_value' => xxx,
     *                                                  'spec_image' => xxx,
     *                                                  'p_order' => xxx
     *                                              )
     *                                          ),
     *                              'last_modify' => xxx
     *                          )
     * @param $command_id，更新列表的id
     * @param boolean $flag, 控制是否要真正下载，为false时，只判断是否需要下载，不实际下载
     * @return mix，1.如果flag为true，则返回array(
     *                                      'spec_id' => xxx,   //插入到本地的规格spec_id
     *                                      'spec_name' => xxx,
     *                                      'supplier_id' => xxx,
     *                                      'supplier_spec_id' => xxx,
     *                                      'download' => true|false, //是否真正下载了
     *                                      'detail' => array(
     *                                                      array(
     *                                                          'spec_value_id' => xxx,
     *                                                          'spec_id' => xxx,
     *                                                          'spec_value' => xxx,
     *                                                          'spec_image' => xxx,
     *                                                          'supplier_id' => xxx,
     *                                                          'supplier_spec_value_id' => xxx
     *                                                      ),
     *                                                      array(
     *                                                          'spec_value_id' => xxx,
     *                                                          'spec_id' => xxx,
     *                                                          'spec_value' => xxx,
     *                                                          'spec_image' => xxx,
     *                                                          'supplier_id' => xxx,
     *                                                          'supplier_spec_value_id' => xxx
     *                                                      ),
     *                                                  )
     *                                  )
     *              2.如果flag为false，直接返回boolean，需要下载则为true，不需要下载则为false
     */
    function downloadSpecification($supplier_id,$spec_info,$command_id=NULL,$flag=true){
        $return_spec_info = array();
        $if_download = false;

        $sql = "SELECT * FROM sdb_specification WHERE supplier_id=".floatval($supplier_id)." AND supplier_spec_id=".intval($spec_info['spec_id'])." ORDER BY spec_id DESC"; //只取最新的一条数据
        $local_spec = $this->db->selectrow($sql);

        if(empty($local_spec)){
            $if_download = true;
        }else{
            if($local_spec['lastmodify'] < $spec_info['last_modified']){
                $if_download = true;
            }else{
                $if_download = false;
            }
        }

        if($flag){
            if($if_download){
                $new_spec_info = array(
                    'spec_name' => $spec_info['spec_name'],
                    'spec_type' => $spec_info['spec_type'],
                    'spec_memo' => $spec_info['spec_memo'],
                    'p_order' => $spec_info['p_order'],
                    'supplier_id' => $supplier_id,
                    'supplier_spec_id' => $spec_info['spec_id'],
                    'lastmodify' => $spec_info['last_modified']
                );

                $rs = $this->db->query("SELECT * FROM sdb_specification WHERE 0=1");
                $sql = $this->db->GetInsertSQL($rs, $new_spec_info);
                if($sql && !$this->db->exec($sql)){
                    trigger_error('SQL Error:'.$sql,E_USER_NOTICE);
                    return false;
                }
                $spec_id = $this->db->lastInsertId();

                $return_spec_info = array(
                    'download' => true,
                    'spec_id' => $spec_id,
                    'spec_name' => $spec_info['spec_name'],
                    'supplier_id' => $supplier_id,
                    'supplier_spec_id' => $spec_info['spec_id'],
                    'detail' => array()
                );

                //将平台和本地规格id的对应关系存在本类的缓存中，方便_getLocalSpecByPlatSpec的读取
                $this->local_spec[md5($supplier_id.$spec_info['supplier_spec_id']."spec")] = $spec_id;

                //新增sdb_spec_value表记录
                if(!empty($spec_info['struct'])){
                    $sync_job = $this->system->loadModel('distribution/syncjob');
                    foreach($spec_info['struct'] as $spec_value){
                        $spec_value_info = array(
                            'spec_id' => $spec_id,
                            'spec_value' => $spec_value['spec_value'],
                            'spec_image' => $spec_value['spec_image'],
                            'p_order' => $spec_value['p_order'],
                            'supplier_id' => $supplier_id,
                            'supplier_spec_value_id' => $spec_value['spec_value_id']
                        );

                        $rs = $this->db->query("SELECT * FROM sdb_spec_values WHERE 0=1");
                        $sql = $this->db->GetInsertSQL($rs,$spec_value_info);
                        $this->db->exec($sql);

                        $spec_value_id = $this->db->lastInsertId();

                        $return_spec_info['detail'][] = array_merge($spec_value_info,array('spec_value_id'=>$spec_value_id));

                        //将平台和本地的规格值id的对应关系存在本类的缓存中，方便_getLocalSpecValueByPlatSpecValue的读取
                        $this->local_spec_value[md5($spec_id.$supplier_id.$spec_value['spec_value_id']."spec_value")] = $spec_value_id;

                        //将规格图片加入图片下载列表中
                        if($spec_info['spec_type'] == 'image'){
                            $type = 'spec_value';
                            $object_id = $spec_value['spec_value_id'];
                            $sync_job->insertImageSyncList($command_id,$type,$supplier_id,$object_id);
                        }
                    }
                }

                return $return_spec_info;
            }else{
                $return_spec_info = array(
                    'download' => false,
                    'spec_id' => $local_spec['spec_id'],
                    'spec_name' => $local_spec['spec_name'],
                    'supplier_id' => $supplier_id,
                    'supplier_spec_id' => $spec_info['spec_id'],
                    'detail' => array()
                );
                return $return_spec_info;
            }
        }else{
            return $if_download;
        }
    }

    /**
     * 融合上游商品的品牌
     * 如果supplier_id和supplier_brand_id在品牌表中存在，则有品牌地址?继续:下载品牌地址，有logo?继续:下载logo，有品牌详细说明?继续:下载详细说明
     * 如果supplier_id和supplier_brand_id在品牌表中不存在，则品牌名存在?继续:新增品牌，有品牌别名一致?继续:新增品牌，有品牌地址?继续:下载品牌地址，有logo?继续:下载logo，有品牌详细说明?继续:下载详细说明
     *
     * @param int $supplier_id，供应商id
     * @param int $supplier_brand_id，上游商品的品牌id
     * @param int $command_id，更新列表的id
     * @param boolean $flag，控制是否要真正融合，为false时，只判断是否需要融合，不实际融合
     * @return mix，1.如果flag为true，返回array(
     *                                  'action' => 'add|update|none',   //是融合的或者新增的，也可能是未操作
     *                                  'brand_id' => xxx,  //和本地融合的brand_id或者新插入在本地的brand_id
     *                                  'brand_name' => xxx,
     *                                  'brand_keywords' => xxx
     *                              )
     *                              或者false，没有需要下载的
     *              2.如果flag为false，直接返回boolean，需要融合或下载则为true，不需要融合或下载则为false
     */
    function downloadBrand($supplier_id,$supplier_brand_id,$command_id=NULL,$flag=true){
        $return_brand = array();

        $if_merge = false;
        $action = '';

        //根据$supplier_id,$supplier_brand_id,获取该上游商品的品牌信息，如果本地临时数据已经下载完，那么直接读取本地数据
        if($tmp_data=$this->db->selectrow("SELECT * FROM sdb_sync_tmp WHERE supplier_id=".floatval($supplier_id)." AND s_type='brand' AND ob_id=".intval($supplier_brand_id))){
            $brand_info = unserialize($tmp_data['s_data']);
        }else{
            $brand_info = $this->api->getApiData('getBrandByID',API_VERSION,array('supplier_id'=>$supplier_id,'id'=>$supplier_brand_id),true,true);
        }

        if(!empty($brand_info)){
            $sql = "SELECT * FROM sdb_brand WHERE disabled='false' AND  brand_name='".addslashes($brand_info['brand_name'])."'";
            $local_brand = $this->db->selectrow($sql);
            if(empty($local_brand)){
                $action = 'add';
            }else{
                if(!empty($brand_info['brand_keywords'])){
                    $sql .= " AND brand_keywords='".addslashes($brand_info['brand_keywords'])."'";
                }else{
                    $sql .= " AND (brand_keywords IS NULL OR brand_keywords='')";
                }
                if($local_brand = $this->db->selectrow($sql)){
                    $action = 'update';
                }else{
                    $action = 'add';
                }
            }

            $return_brand['action'] = $action;

            if($action == 'update'){
                $local_brand_update = array();
                if(empty($local_brand['brand_url']) && !empty($brand_info['brand_url'])){
                    $local_brand_update['brand_url'] = $brand_info['brand_url'];
                    $if_merge = true;
                }
                if(empty($local_brand['brand_logo']) && !empty($brand_info['brand_logo'])){
                    $local_brand_update['brand_logo'] = $brand_info['brand_logo'];

                    //brand的logo加入图片更新列表
                    if($flag && !$this->_checkRemoteImage($brand_info['brand_logo'])){
                        $sync_job = $this->system->loadModel('distribution/syncjob');
                        $type = 'brand_logo';
                        $object_id = $supplier_brand_id;
                        $sync_job->insertImageSyncList($command_id,$type,$supplier_id,$object_id);
                    }

                    $if_merge = true;
                }
                if(empty($local_brand['brand_desc']) && !empty($brand_info['brand_desc'])){
                    $local_brand_update['brand_desc'] = $brand_info['brand_desc'];
                    $if_merge = true;
                }

                if($flag){
                    if(!empty($local_brand_update)){
                        $rs = $this->db->query('SELECT * FROM sdb_brand WHERE brand_id='.$local_brand['brand_id']);
                        $sql = $this->db->GetUpdateSQL($rs, $local_brand_update);
                        $this->db->exec($sql);
                    }else{
                        $return_brand['action'] = 'none';
                    }
                    $brand_id = $local_brand['brand_id'];

                    $return_brand['brand_id'] = $brand_id;
                    $return_brand['brand_name'] = $local_brand['brand_name'];
                    $return_brand['brand_keywords'] = $local_brand['brand_keywords'];
                    return $return_brand;
                }else{
                    return $if_merge;
                }
            }else if($action == 'add'){
                if($flag){
                    $new_brand_info = array(
                        'supplier_id' => $supplier_id,
                        'supplier_brand_id' => $supplier_brand_id,
                        'brand_url' => $brand_info['brand_url'],
                        'brand_logo' => $brand_info['brand_logo'],
                        'brand_name' => $brand_info['brand_name'],
                        'brand_desc' => $brand_info['brand_desc'],
                        'brand_keywords' => $brand_info['brand_keywords']
                    );

                    $rs = $this->db->query("SELECT * FROM sdb_brand WHERE 0=1");
                    $sql = $this->db->GetInsertSQL($rs, $new_brand_info);
                    if($sql && !$this->db->exec($sql)){
                        trigger_error('SQL Error:'.$sql,E_USER_NOTICE);
                        return false;
                    }
                    $brand_id = $this->db->lastInsertId();

                    if(!$this->_checkRemoteImage($brand_info['brand_logo'])){
                        $sync_job = $this->system->loadModel('distribution/syncjob');
                        $type = 'brand_logo';
                        $object_id = $supplier_brand_id;
                        $sync_job->insertImageSyncList($command_id,$type,$supplier_id,$object_id);
                    }

                    $return_brand['brand_id'] = $brand_id;
                    $return_brand['brand_name'] = $brand_info['brand_name'];
                    $return_brand['brand_keywords'] = $brand_info['brand_keywords'];

                    return $return_brand;
                }else{
                    return true;
                }
            }
        }else{
            return false;
        }

    }

    /**
     * 下载上游商品的类型
     * 如果supplier_id和supplier_type_id在类型表中存在，则判断该记录的类型名和平台类型名＋时间戳是否一致，一致就不操作，否则新增平台类型＋时间戳的新类型
     * 如果supplier_id和supplier_type_id在类型表中不存在，新增平台类型＋时间戳的新类型
     *
     * @param int $supplier_id，供应商id
     * @param int $supplier_type_id，上游商品的类型id
     * @param boolean $flag，控制是否要真正下载，为false时，只判断是否需要下载，不实际下载
     * @return mix，1.如果flag为true，返回array(
     *                                  'download' => true|false,
     *                                  'type_id' => xxx,   //插入到本地的type_id
     *                                  'name' => xxx
     *                              )
     *              2.如果flag为false，直接返回boolean，需要下载则为true，不需要下载则为false
     */
    function downloadType($supplier_id,$supplier_type_id,$flag=true){
        $return_type = array();
        $if_download = false;

        //根据$supplier_id,$supplier_type_id获取该上游商品的类型信息，如果本地临时数据已经下载完，那么直接读取本地数据
        if($tmp_data=$this->db->selectrow("SELECT * FROM sdb_sync_tmp WHERE supplier_id=".floatval($supplier_id)." AND s_type='goods_type' AND ob_id=".intval($supplier_type_id))){
            $type_info = unserialize($tmp_data['s_data']);
        }else{
            $type_info = $this->api->getApiData('getTypeByID',API_VERSION,array('supplier_id'=>$supplier_id,'id'=>$supplier_type_id),true,true);
        }

        if(empty($type_info)){
            return false;
        }

        $sql = "SELECT * FROM sdb_goods_type WHERE supplier_id=".floatval($supplier_id)." AND supplier_type_id=".intval($supplier_type_id)." ORDER BY type_id DESC";    //只检查最新的一条数据
        $local_type = $this->db->selectrow($sql);

        if(empty($local_type)){
            $if_download = true;
        }else{
            if($local_type['lastmodify'] < $type_info['last_modify']){
                $if_download = true;
            }else{
                $if_download = false;
            }
        }

        if($flag){
            if($if_download){
                $new_type_info = array(
                    'name' => $type_info['name'],
                    'alias' => $type_info['alias'],
                    'is_physical' => $type_info['is_physical'],
                    'supplier_id' => $supplier_id,
                    'supplier_type_id' => $supplier_type_id,
                    'setting' => $type_info['setting'],
                    'params' => $type_info['params'],
                    'ret_func' => $type_info['ret_func'],
                    'spec' => $type_info['spec'],
                    'minfo' => $type_info['minfo'],
                    'dly_func' => $type_info['dly_func'],
                    'props' => $type_info['props'],
                    'schema_id' => 'custom',
                    'lastmodify' => $type_info['last_modify']
                );

                $rs = $this->db->query("SELECT * FROM sdb_goods_type WHERE 0=1");
                $sql = $this->db->GetInsertSQL($rs, $new_type_info);
                if($sql && !$this->db->exec($sql)){
                    trigger_error('SQL Error:'.$sql,E_USER_NOTICE);
                    return false;
                }
                $type_id = $this->db->lastInsertId();

                $return_type = array(
                    'download' => true,
                    'type_id' => $type_id,
                    'name' => $type_info['name']
                );

                return $return_type;
            }else{
                $return_type = array(
                    'download' => false,
                    'type_id' => $local_type['type_id']
                );

                return $return_type;
            }
        }else{
            return $if_download;
        }
    }

    /**
     * 根据规格下载平台上绑定的类型，并在本地做绑定关系
     *
     * @param int $supplier_id，供应商id
     * @param int $supplier_spec_id，供应商的spec_id
     * @param int $spec_id，供应商的spec_id插入到本地的spec_id
     * @return array, array(
     *                  $type_id => $type_name,
     *                  $type_id => $type_name
     *              )
     */
    function bindSpecWithType($supplier_id,$supplier_spec_id,$spec_id){
        $return = array();
        //下载规格后，需要取得平台对应的绑定类型的信息
        $plat_bind_type_info = $this->api->getApiData('getTypeBySpecId',API_VERSION,array('supplier_id'=>$supplier_id,'id'=>$supplier_spec_id),true,true);

        if(!empty($plat_bind_type_info)){
            foreach($plat_bind_type_info as $type){
                $down_type = $this->downloadType($supplier_id,$type['type_id']);
                if(!empty($down_type)){
                    if($down_type['download']){
                        $return[$down_type['type_id']] = $down_type['name'];
                    }
                    $bind_type_id = $down_type['type_id'];
                    if(!$this->db->selectrow("SELECT * FROM sdb_goods_type_spec WHERE spec_id=".intval($spec_id)." AND type_id=".intval($bind_type_id))){
                        $rs = $this->db->query("SELECT * FROM sdb_goods_type_spec WHERE 0=1");
                        $sql = $this->db->GetInsertSQL($rs,array('spec_id'=>$spec_id,'type_id'=>$bind_type_id));
                        $this->db->exec($sql);
                    }
                }
            }
        }

        return $return;
    }

    /**
     * 根据品牌下载平台上绑定的类型，并在本地做绑定关系
     *
     * @param int $supplier_id，供应商id
     * @param int $supplier_brand_id，供应商的brand_id
     * @param int $brand_id，供应商的brand_id插入到本地的brand_id
     * @return array, array(
     *                  $type_id => $type_name,
     *                  $type_id => $type_name
     *              )
     */
    function bindBrandWithType($supplier_id,$supplier_brand_id,$brand_id){
        $return = array();
        //下载品牌后，需要取得平台对应的绑定类型的信息
        $plat_bind_type_info = $this->api->getApiData('getTypeByBrandId',API_VERSION,array('supplier_id'=>$supplier_id,'id'=>$supplier_brand_id),true,true);
        if(!empty($plat_bind_type_info)){
            foreach($plat_bind_type_info as $type){
                $down_type = $this->downloadType($supplier_id,$type['type_id']);
                if(!empty($down_type)){
                    if($down_type['download']){
                        $return[$down_type['type_id']] = $down_type['name'];
                    }
                    $bind_type_id = $down_type['type_id'];
                    if(!$this->db->selectrow("SELECT * FROM sdb_type_brand WHERE type_id=".intval($bind_type_id)." AND brand_id=".intval($brand_id))){
                        $rs = $this->db->query("SELECT * FROM sdb_type_brand WHERE 0=1");
                        $sql = $this->db->GetInsertSQL($rs,array('type_id'=>$bind_type_id,'brand_id'=>$brand_id));
                        $this->db->exec($sql);
                    }
                }
            }
        }

        return $return;
    }

    /**
     * 根据类型下载平台上绑定的规格，并在本地做绑定关系
     *
     * @param int $supplier_id，供应商id
     * @param int $supplier_type_id，供应商的type_id
     * @param int $type_id，供应商的type_id插入到本地后的type_id
     * @param int $command_id，更新列表的id
     * @return array, array(
     *                  $spec_id => $spec_name,
     *                  $spec_id => $spec_name
     *              )
     */
    function bindTypeWithSpec($supplier_id,$supplier_type_id,$type_id,$command_id){
        $return = array();
        //下载类型后，需要取得平台对应的绑定规格的信息
        $plat_bind_spec_info = $this->api->getApiData('getSpecificationByTypeId',API_VERSION,array('supplier_id'=>$supplier_id,'id'=>$supplier_type_id),true,true);
        if(!empty($plat_bind_spec_info)){
            foreach($plat_bind_spec_info as $spec){
                $down_spec = $this->downloadSpecification($supplier_id,$spec,$command_id);
                if($down_spec['download']){
                    $return[$down_spec['spec_id']] = $down_spec['spec_name'];
                }
                $bind_spec_id = $down_spec['spec_id'];
                if(!$this->db->selectrow("SELECT * FROM sdb_goods_type_spec WHERE spec_id=".intval($bind_spec_id)." AND type_id=".intval($type_id))){
                    $rs = $this->db->query("SELECT * FROM sdb_goods_type_spec WHERE 0=1");
                    $sql = $this->db->GetInsertSQL($rs,array('spec_id'=>$bind_spec_id,'type_id'=>$type_id));
                    $this->db->exec($sql);
                }
            }
        }

        return $return;
    }

    /**
     * 根据类型下载平台上绑定的品牌，并在本地做绑定关系
     *
     * @param int $supplier_id，供应商id
     * @param int $supplier_type_id，供应商的type_id
     * @param int $type_id，供应商的type_id插入到本地的type_id
     * @param int $command_id，更新列表的id
     * @return array, array(
     *                  'add' => array(
     *                      $brand_id => $brand_name,
     *                      $brand_id => $brand_name,
     *                  ),
     *                  'update' => array(
     *                      $brand_id => $brand_name,
     *                      $brand_id => $brand_name,
     *                  )
     *              )
     */
    function bindTypeWithBrand($supplier_id,$supplier_type_id,$type_id,$command_id){
        $return = array();
        //下载类型后，需要取得平台对应的绑定品牌的信息
        $plat_bind_brand_info = $this->api->getApiData('getBrandByTypeID',API_VERSION,array('supplier_id'=>$supplier_id,'id'=>$supplier_type_id),true,true);
        if(!empty($plat_bind_brand_info)){
            foreach($plat_bind_brand_info as $brand){
                $down_brand = $this->downloadBrand($supplier_id,$brand['brand_id'],$command_id);
                if(!empty($down_brand)){
                    $return[$down_brand['action']][$down_brand['brand_id']] = $down_brand['brand_name'];
                    $bind_brand_id = $down_brand['brand_id'];
                    if(!$this->db->selectrow("SELECT * FROM sdb_type_brand WHERE type_id=".intval($type_id)." AND brand_id=".intval($bind_brand_id))){
                        $rs = $this->db->query("SELECT * FROM sdb_type_brand WHERE 0=1");
                        $sql = $this->db->GetInsertSQL($rs,array('type_id'=>$type_id,'brand_id'=>$bind_brand_id));
                        $this->db->exec($sql);
                    }
                }
            }
        }

        return $return;
    }

    /**
     * 下载商品并关联上游下载下来的商品的类型、品牌、规格之间的关系，下载的商品全都下架
     *
     * @param int $command_id，根新列表的id
     * @param int $supplier_id，供应商id
     * @param int $supplier_goods_id，上游商品id
     * @param int $cat_id，本地分类id，如果cat_id不为NULL，则表示下载到本地的该cat_id分类下，如果为NULL，则表示下载到本地的未分类中去，如果未分类不存在，则增加该分类
     * @param boolean $flag，是否真正下载，如果true则下载，如果false则只下载商品对应的类型、分类、规格、品牌变动
     * @return array ，array(
     *                  'type' => array(
     *                              $type_id => $type_name,
     *                              $type_id => $type_name
     *                          ),
     *                  'spec' => array(
     *                              $spec_id => $spec_name,
     *                              $spec_id => $spec_name
     *                          ),
     *                  'brand' => array(
     *                              'add' => array(
     *                                  $brand_id => $brand_name,
     *                                  $brand_id => $brand_name,
     *                              ),
     *                              'update' => array(
     *                                  $brand_id => $brand_name,
     *                                  $brand_id => $brand_name,
     *                              )
     *                          ),
     *                  'cat' => array(
     *                              $cat_id => $cat_path,
     *                              $cat_id => $cat_path
     *                          ),
     *                  'locals' => array(
     *                              'local_type_id' => $local_type_id,
     *                              'local_spec_id' => $local_spec_id,
     *                              'local_brand_id' => $local_brand_id
     *                          )
     *              )
     */
    function downloadGoods($command_id,$supplier_id,$supplier_goods_id,$cat_id=NULL,$flag=true){
        $plat_goods_info = $this->api->getApiData('getGoodsByID',API_VERSION,array('supplier_id'=>$supplier_id,'id'=>$supplier_goods_id),true,true);
        if(!empty($plat_goods_info)){
            //新增goods，新增sdb_supplier_pdtbn关系记录
            if($flag){
                if(!$this->_checkGoodsExist($supplier_id,$supplier_goods_id)){
                    $time = time();
                    $marketable = 'false';

                    $return = $this->preDownload($supplier_id,$supplier_goods_id,$command_id);

                    $supplier_cat_id = $plat_goods_info['cat_id'];
                    
                    if(is_null($cat_id)){   //表示不指定下载到b2c的某个分类
                        $tmp_down_cat = $this->downloadCat($cat_id,$supplier_id,$supplier_cat_id,false);
                        if($tmp_down_cat['if_download']){    //判断分类是否需要下载，如果在不指定b2c分类的情况下，则进入未分类
                            $tmp_cat = $this->db->selectrow("SELECT cat_id FROM sdb_goods_cat WHERE cat_name='未分类'");
                            if(!empty($tmp_cat)){
                                $local_cat_id = $tmp_cat['cat_id'];
                            }else{
                                $cat_info = array(
                                    'parent_id' => 0,
                                    'cat_path' => ',',
                                    'is_leaf' => 'true',
                                    'cat_name' => '未分类',
                                    'disabled' => 'true'
                                );
                                $rs = $this->db->query("SELECT * FROM sdb_goods_cat WHERE 0=1");
                                $sql = $this->db->GetInsertSQL($rs, $cat_info);
                                $this->db->exec($sql);
                                $local_cat_id = $this->db->lastInsertId();
                            }
                        }else{
                            $local_cat_id = $tmp_down_cat['local_cat_id'];
                        }
                    }else{
                        $down_cat = $this->downloadCat($cat_id,$supplier_id,$supplier_cat_id);
                        if($down_cat['cat_path'] != ""){
                            $return['cat'] = $down_cat;
                        }
                        $local_cat_id = $down_cat['cat_id']?$down_cat['cat_id']:0;
                    }
                    
                    $return['locals']['local_cat_id'] = $local_cat_id;

                    $sync_job = $this->system->loadModel('distribution/syncjob');

                    //$aData为商品数据，注意将goods_id注销掉，将cat_id,type_id,brand_id,brand,lastmodify修正
                    $aData = $plat_goods_info;
                    unset($aData['goods_id']);
                    $aData['cat_id'] = $local_cat_id;
                    $aData['type_id'] = $return['locals']['local_type_id'];
                    $aData['brand_id'] = $return['locals']['local_brand_id'];
                    $aData['supplier_id'] = $supplier_id;
                    $aData['supplier_goods_id'] = $supplier_goods_id;
                    $aData['marketable'] = $marketable;
                    $aData['bn'] = $this->localBn($supplier_id,$plat_goods_info['bn']);
                    $aData['disabled'] = 'false';
                    $aData['udfimg'] = 'false'; //不处理上游自定义图片
                    $aData['last_modify'] = time();
                    if(empty($aData['score_setting'])){
                        unset($aData['score_setting']);
                    }
                    if(isset($aData['spec'])){
                        unset($aData['spec']);
                    }
                    if(isset($aData['spec_desc'])){
                        unset($aData['spec_desc']);
                    }
                    if(isset($aData['pdt_desc'])){
                        unset($aData['pdt_desc']);
                    }
                    if(empty($aData['ws_policy'])){
                        unset($aData['ws_policy']);
                    }

                    $rs = $this->db->query('SELECT * FROM sdb_goods WHERE 0=1');
                    $sql = $this->db->GetInsertSQL($rs, $aData);
                    if($sql && !$this->db->exec($sql)){
                        trigger_error('SQL Error:'.$sql,E_USER_NOTICE);
                        return false;
                    }

                    $local_goods_id = $this->db->lastInsertId();

                    //如果是udfimg，则要抓取thumbnail_pic，暂时不抓该图片
                    /*if($aData['udfimg'] == 'true'){
                        $type = 'udfimg';
                        $object_id = $plat_goods_info['goods_id'];
                        $sync_job->insertImageSyncList($command_id,$type,$supplier_id,$object_id);
                    }*/

                    $this->addProducts($supplier_id,$supplier_goods_id,$local_goods_id);

                    //获取该商品的gimage信息，加入图片更新列表数据库
                    $gimages = $this->api->getApiData('getImagesByGoodsId',API_VERSION,array('supplier_id'=>$supplier_id,'id'=>$supplier_goods_id),true,true);

                    foreach($gimages as $gimage){
                        $this->addGimage($command_id,$supplier_id,$local_goods_id,$gimage);
                    }

                    //获取平台商品原本的image_default的gimage_id在本地对应的gimage_id
                    $image_default = $this->_getLocalGimageByPlatGimage($supplier_id,$plat_goods_info['image_default']);

                    //更新商品的image_default字段
                    $rs = $this->db->query("SELECT * FROM sdb_goods WHERE goods_id=".intval($local_goods_id));
                    $sql = $this->db->GetUpdateSQL($rs,array('image_default'=>$image_default));
                    $this->db->exec($sql);

                    // 新增商品后更新成本价 wubin 2009-09-22 16:04:25
                    $oCostSync = $this->system->loadModel('distribution/costsync');
                    $oCostSync->updateCostSyncInfo($supplier_id,$local_goods_id);  // 更新成本价同步信息(goods_id & product_id)
                    $oCostSync->updateProductCost($supplier_id,$local_goods_id);   // 更新货品成本价
                    $oCostSync->updateGoodsCost($supplier_id,$local_goods_id);     // 更新商品成本价
                }else{
                    //有相同供应商id和上游商品id的商品了，就不新增了
                    $goods_info = $this->db->selectrow("SELECT * FROM sdb_goods WHERE supplier_id=".floatval($supplier_id)." AND supplier_goods_id=".intval($supplier_goods_id));

                    if($goods_info['disabled'] == 'true'){
                        $params['disabled'] = 'false';
                    }

                    if($goods_info['marketable'] == 'false'){
                        $params['marketable'] = 'true';
                    }

                    $rs = $this->db->query("SELECT * FROM sdb_goods WHERE supplier_id=".floatval($supplier_id)." AND supplier_goods_id=".intval($supplier_goods_id));
                    $sql = $this->db->GetUpdateSQL($rs,$params);
                    if($sql){
                        $this->db->exec($sql);
                    }
                    return false;
                }
            }


            $this->_changeSyncStatus($supplier_id,$supplier_goods_id);

            return $return;
        }else{
            //找不商品信息
            return false;
        }
    }

    /**
     * 将除了command为6(新增)以外的status都改成done，表示已经做过该操作了
     *
     * @param int $supplier_id
     * @param int $supplier_goods_id
     */
    function _changeSyncStatus($supplier_id,$supplier_goods_id){
        $table_name = "sdb_data_sync_" . $supplier_id;
        $rs = $this->db->query("SELECT * FROM " . $table_name . " WHERE command<>6 AND goods_id=".intval($supplier_goods_id));
        $sql = $this->db->GetUpdateSQL($rs,array('status'=>'done'));
        if($sql){
            $this->db->exec($sql);
        }
    }

    /**
     * 添加gimage
     *
     * @param int $command_id，同步列表的id
     * @param int $supplier_id，供应商id
     * @param int $local_goods_id，本地对应的商品id
     * @param array $gimage，上游的gimage信息
     */
    function addGimage($command_id,$supplier_id,$local_goods_id,$gimage){
        $is_remote = $this->_checkRemoteImage($gimage['source'])?'true':'false';
        $gimage_info = array(
            'goods_id' => $local_goods_id,
            'is_remote' => $is_remote,
            'source' => $gimage['source'],
            'src_size_width' => $gimage['src_size_width'],
            'src_size_height' => $gimage['src_size_height'],
            //'small' => $gimage['small'],
            //'big' => $gimage['big'],
            //'thumbnail' => $gimage['thumbnail'],
            'supplier_id' => $supplier_id,
            'supplier_gimage_id' => $gimage['gimage_id'],
            'sync_time' => $gimage['up_time'],
            'up_time' => time()
        );

        $rs = $this->db->query('SELECT * FROM sdb_gimages WHERE 0=1');
        $sql = $this->db->GetInsertSQL($rs, $gimage_info);
        $this->db->exec($sql);

        $local_gimage_id = $this->db->lastInsertId();

        //将平台和本地的gimage_id的对应关系存在本类的缓存中，方便_getLocalGimageByPlatGimage的读取
        $this->local_gimage[md5($supplier_id.$gimage['gimage_id']."gimage")] = $local_gimage_id;

        //加入图片更新列表数据库，如果是远程图片就不加入列表了
        if($is_remote == 'false'){
            $sync_job = $this->system->loadModel('distribution/syncjob');
            $type = 'gimage';
            $object_id = $gimage['gimage_id'];
            $sync_job->insertImageSyncList($command_id,$type,$supplier_id,$object_id,$gimage['sync_time']);
        }
    }

    /**
     * 新增货品信息，在新增货品信息时会修改所属的goods的pdt_desc和spec_info
     *
     * @param int $supplier_id
     * @param int $supplier_goods_id
     * @param int $local_goods_id
     */
    function addProducts($supplier_id,$supplier_goods_id,$local_goods_id){
        $tmp_props = array();   //用来修正为本地属性的id
        $tmp_goods_images = array();    //用来修正为本地属性的id
        //$products 获取该商品下的所有货品信息
        $products = $this->api->getApiData('getProductsByGoodsID',API_VERSION,array('supplier_id'=>$supplier_id,'id'=>$supplier_goods_id),true,true);

        $local_goods_info = $this->db->selectrow("SELECT * FROM sdb_goods WHERE goods_id=".intval($local_goods_id));

        $local_goods_pdt_desc = unserialize($local_goods_info['pdt_desc']);    //需要修正的goods的pdt_desc描述信息
        $local_goods_spec_desc = unserialize($local_goods_info['spec_desc']);  //需要修正的goods的spec_info描述信息
        $local_goods_spec = unserialize($local_goods_info['spec']);  //需要修正的goods的spec描述信息

        if(!empty($products)){
            $min_cost = NULL;
            foreach($products as $product){
                //新增货品记录的过程
                $plat_product_id = $product['product_id'];
                unset($product['product_id']);

                //处理product的props字段，将里面的spec_id,spec_value_id改成本地对应的值
                $tmp_props = unserialize($product['props']);
                $tmp_goods_images = $product['goods_images'];


                if(!empty($tmp_props)){

                    $local_spec_ids = array();
                    $t_spec = array();
                    foreach($tmp_props['spec'] as $spec_id=>$v){
                        //重组props的spec，用本地的spec_value_id 换取 远端的spec_value_id
                        $local_spec_id = $this->_getLocalSpecByPlatSpec($supplier_id,$spec_id);
                        $t_spec[$local_spec_id] = $v;

                        $local_spec_ids[] = $local_spec_id;
                    }
                    unset($tmp_props['spec']);
                    $tmp_props['spec'] = $t_spec;

                    $t_spec_value_id = array();
                    foreach($tmp_props['spec_value_id'] as $spec_id=>$v){
                        $local_spec_id = $this->_getLocalSpecByPlatSpec($supplier_id,$spec_id);
                        $local_spec_value_id = $this->_getLocalSpecValueByPlatSpecValue($local_spec_id,$supplier_id,$v);
                        if($spec_id != $local_spec_id){
                            //重组goods_images，用本地的spec_value_id 换取 远端的spec_value_id
                            $tmp_goods_images[$local_spec_value_id] = $tmp_goods_images[$tmp_props['spec_value_id'][$spec_id]];
                            unset($tmp_goods_images[$tmp_props['spec_value_id'][$spec_id]]);

                        }
                            //重组props的spec_value_id，用本地的spec_value_id 换取 远端的spec_value_id
                            $t_spec_value_id[$local_spec_id] = $local_spec_value_id;


                        }
                    unset($tmp_props['spec_value_id']);
                    $tmp_props['spec_value_id'] = $t_spec_value_id;

                    $t_spec_private_value_id = array();
                    foreach($tmp_props['spec_private_value_id'] as $spec_id=>$v){
                        //重组props的spec_private_value_id，用本地的spec_value_id 换取 远端的spec_value_id
                        $local_spec_id = $this->_getLocalSpecByPlatSpec($supplier_id,$spec_id);
                        $t_spec_private_value_id[$local_spec_id] = $v;
                    }
                    unset($tmp_props['spec_private_value_id']);
                    $tmp_props['spec_private_value_id'] = $t_spec_private_value_id;

                    foreach($local_spec_ids as $v){ //修正spec_desc
                        $spec_value_id = $tmp_props['spec_value_id'][$v];
                        $spec_private_value_id = $tmp_props['spec_private_value_id'][$v];
                        $spec_info = $this->db->selectrow("SELECT spec_name,spec_type FROM sdb_specification WHERE spec_id=".$v);
                        $spec_value_info = $this->db->selectrow("SELECT spec_value,spec_image FROM sdb_spec_values WHERE spec_value_id=".$spec_value_id);
                        $local_goods_spec_desc[$v][$spec_private_value_id] = array(
                            'spec_value' => $spec_value_info['spec_value'],
                            'spec_type' => $spec_info['spec_type'],
                            'spec_value_id' => $spec_value_id,
                            'spec_image' => $spec_value_info['spec_image'],
                            'spec_goods_images' => $tmp_goods_images[$spec_value_id]
                        );


                        $local_goods_spec[$v] = $spec_info['spec_name'];
                    }
                }

                $source_product_bn = $product['bn'];
                $local_product_bn = $this->localBn($supplier_id,$source_product_bn);
                $product['goods_id'] = $local_goods_id;
                $product['bn'] = $local_product_bn;
                $product['props'] = serialize($tmp_props);
                $product['last_modify'] = $time;
                $product['uptime'] = $time;
                $product['name'] = $product['name'];

                $min_cost = $min_cost===NULL?$product['cost']:min($min_cost,$product['cost']);    //cost已经是下游在上游购买该商品时的会员价了

                if($tmp = $this->db->selectrow("SELECT p.product_id FROM sdb_products AS p,sdb_supplier_pdtbn AS sp WHERE sp.source_bn='".$source_product_bn."' AND sp.supplier_id=".intval($supplier_id)." AND sp.local_bn=p.bn")){
                    $local_product_id = $tmp['product_id'];

                    unset($product['bn']);
                    $rs = $this->db->query("SELECT * FROM sdb_products WHERE product_id=".intval($local_product_id));
                    $sql = $this->db->GetUpdateSQL($rs,$product);
                    $this->db->exec($sql);
                }else{
                    $rs = $this->db->query('SELECT * FROM sdb_products WHERE 0=1');
                    $sql = $this->db->GetInsertSQL($rs, $product);
                    $this->db->exec($sql);

                    $local_product_id = $this->db->lastInsertId();

                    //新增sdb_goods_spec_index记录
                    foreach($tmp_props['spec_value_id'] as $key_spec_id=>$value_spec_value_id){
                        $this->addGoodsSpecIndex($local_type_id,$key_spec_id,$value_spec_value_id,$local_goods_id,$local_product_id);
                    }

                    //新增sdb_supplier_pdtbn记录
                    $this->addSuplierPdtbn($supplier_id,$local_product_bn,$source_product_bn);
                }


                //修正goods的pdt_desc,spec_desc,spec描述信息
                //如果为空，则说明是商品的默认货品，那么pdt_desc,spec_desc,spec字段就无需再修正了，直接留空
                if(!empty($product['pdt_desc'])){
                    //pdt_desc处理
                    $local_goods_pdt_desc[$local_product_id] = $product['pdt_desc'];

                    //spec_desc处理
                    //$local_goods_spec_desc已在上面处理完毕

                    //spec处理
                    //$local_goods_spec已在上面处理完毕
                }else{
                    $local_goods_pdt_desc = "";
                    $local_goods_spec_desc = "";
                    $local_goods_spec = "";
                }
            }


            $goods_update_info = array();
            if(!empty($local_goods_pdt_desc)){
                $goods_update_info['pdt_desc'] = serialize($local_goods_pdt_desc);
            }else{
                $goods_update_info['pdt_desc'] = '';
            }
            if(!empty($local_goods_spec_desc)){
                $goods_update_info['spec_desc'] = serialize($local_goods_spec_desc);
            }else{
                $goods_update_info['spec_desc'] = '';
            }
            if(!empty($local_goods_spec)){
                $goods_update_info['spec'] = serialize($local_goods_spec);
            }else{
                $goods_update_info['spec'] = '';
            }
            $goods_update_info['cost'] = $min_cost;

            if(!empty($goods_update_info)){
                //更新商品的pdt_desc和spec_desc字段
                $rs = $this->db->query("SELECT * FROM sdb_goods WHERE goods_id=".intval($local_goods_id));
                $sql = $this->db->GetUpdateSQL($rs,$goods_update_info);
                $this->db->exec($sql);
            }
        }
    }

    /**
     * 更新商品的货品信息，对应操作5
     * 干掉该商品的所有货品，再将平台上该商品的货品下载下来
     *
     * @param int $supplier_id
     * @param int $supplier_goods_id
     * @param int $command_id，更新列表的id
     * @return array ，array(
     *                  'type' => array(
     *                              $type_id => $type_name,
     *                              $type_id => $type_name
     *                          ),
     *                  'spec' => array(
     *                              $spec_id => $spec_name,
     *                              $spec_id => $spec_name
     *                          ),
     *                  'brand' => array(
     *                              'add' => array(
     *                                  $brand_id => $brand_name,
     *                                  $brand_id => $brand_name,
     *                              ),
     *                              'update' => array(
     *                                  $brand_id => $brand_name,
     *                                  $brand_id => $brand_name,
     *                              )
     *                          ),
     *                  'cat' => array(
     *                              $cat_id => $cat_path,
     *                              $cat_id => $cat_path
     *                          ),
     *                  'locals' => array(
     *                              'local_type_id' => $local_type_id,
     *                              'local_spec_id' => $local_spec_id,
     *                              'local_brand_id' => $local_brand_id
     *                          )
     *              )
     */
    function updateGoodsProduct($supplier_id,$supplier_goods_id,$command_id){
        $return = $this->preDownload($supplier_id,$supplier_goods_id,$command_id);

        $local_goods_info = $this->db->selectrow("SELECT * FROM sdb_goods WHERE supplier_id=".floatval($supplier_id)." AND supplier_goods_id=".intval($supplier_goods_id));
        $local_goods_id = $local_goods_info['goods_id'];

        $local_product_info = $this->db->select("SELECT * FROM sdb_products WHERE goods_id=".$local_goods_id);

        /*
        //先删除商品的所有货品(比较流氓，开会讨论结果)、sdb_goods_spec_index记录、sdb_supplier_pdtbn记录
        $this->db->exec("DELETE FROM sdb_supplier_pdtbn WHERE local_bn IN (SELECT bn FROM sdb_products WHERE goods_id=".intval($local_goods_id).")");
        $this->db->exec("DELETE FROM sdb_products WHERE goods_id=".intval($local_goods_id));
        $this->db->exec("DELETE FROM sdb_goods_spec_index WHERE goods_id=".intval($local_goods_id));
        */

        //根据货号获取可对应的本地product_id，将对应不到的product_id删除相关记录
        $products = $this->api->getApiData('getProductsByGoodsID',API_VERSION,array('supplier_id'=>$supplier_id,'id'=>$supplier_goods_id),true,true);
        $tmp_product_id = array();
        if(!empty($products)){
            foreach($products as $v){
                $tmp = $this->db->selectrow("SELECT p.product_id FROM sdb_products AS p,sdb_supplier_pdtbn AS sp WHERE sp.source_bn='".$v['bn']."' AND sp.supplier_id=".intval($supplier_id)." AND sp.local_bn=p.bn");
                if($tmp){
                    $tmp_product_id[] = $tmp['product_id'];
                }
            }
        }
        foreach($local_product_info as $v){
            if(!in_array($v['product_id'],$tmp_product_id)){
                $this->db->exec("DELETE FROM sdb_supplier_pdtbn WHERE local_bn='".$v['bn']."'");
                $this->db->exec("DELETE FROM sdb_products WHERE product_id=".$v['product_id']);
                $this->db->exec("DELETE FROM sdb_goods_spec_index WHERE product_id=".$v['product_id']);
            }
        }

        //删除商品的pdt_desc，spec_desc
        $rs = $this->db->query("SELECT * FROM sdb_goods WHERE supplier_id=".$supplier_id." AND supplier_goods_id=".$supplier_goods_id);
        $sql = $this->db->GetUpdateSQL($rs,array('spec'=>'','pdt_desc'=>'','spec_desc'=>''));
        if($sql){
            $this->db->exec($sql);
        }

        $this->addProducts($supplier_id,$supplier_goods_id,$local_goods_id);

        //获取货品的总库存数，取货品最小销售价作为商品销售价，最小重量作为商品重量
        $product_info = $this->db->select("SELECT store,price,weight,cost FROM sdb_products WHERE goods_id=".intval($local_goods_id));
        $store = 0;
        $price = NULL;
        $weight = NULL;
        $min_cost = NULL;

        foreach($product_info as $product){
            if(is_null($product['store']) || $product['store'] === ''){
                $store = NULL;
            }else{
                if(!is_null($store)){
                    $store += $product['store'];
                }
            }

            if(is_null($price)){
                $price = empty($product['price'])?0.00:$product['price'];
            }else{
                $price = min($price,$product['price']);
            }

            if(is_null($weight)){
                $weight = empty($product['weight'])?0.00:$product['weight'];
            }else{
                $weight = min($weight,$product['weight']);
            }

            if(is_NULL($min_cost)){
                $min_cost = $product['cost'];
            }else{
                $min_cost = min($min_cost,$product['cost']);
            }
        }

        $rs = $this->db->query("SELECT * FROM sdb_goods WHERE goods_id=".intval($local_goods_id));
        $update_info = array('store'=>$store,'price'=>$price,'weight'=>$weight,'cost'=>$min_cost);
        $sql = $this->db->GetUpdateSQL($rs,$update_info);
        $this->db->exec($sql);

        return $return;
    }

    /**
     * 下载商品相关的类型、品牌、规格，并做关联关系
     *
     * @param int $supplier_id
     * @param int $supplier_goods_id
     * @param int $command_id
     * @return array ，array(
     *                  'type' => array(
     *                              $type_id => $type_name,
     *                              $type_id => $type_name
     *                          ),
     *                  'spec' => array(
     *                              $spec_id => $spec_name,
     *                              $spec_id => $spec_name
     *                          ),
     *                  'brand' => array(
     *                              'add' => array(
     *                                  $brand_id => $brand_name,
     *                                  $brand_id => $brand_name,
     *                              ),
     *                              'update' => array(
     *                                  $brand_id => $brand_name,
     *                                  $brand_id => $brand_name,
     *                              )
     *                          ),
     *                  'cat' => array(
     *                              $cat_id => $cat_path,
     *                              $cat_id => $cat_path
     *                          )
     *                  'locals' => array(
     *                              'local_type_id' => $local_type_id,
     *                              'local_spec_id' => $local_spec_id,
     *                              'local_brand_id' => $local_brand_id
     *                          )
     *              )
     */
    function preDownload($supplier_id,$supplier_goods_id,$command_id){
        $return = array(
            'type' => array(),
            'spec' => array(),
            'brand' => array('add'=>array(),'update'=>array()),
            'cat' => array(),
            'locals' => array()
        );
        //根据supplier_id，goods_id获取对应的brand_id,type_id,spec_info，并试图下载变更的品牌类型规格
        $plat_goods_info = $this->api->getApiData('getGoodsByID',API_VERSION,array('supplier_id'=>$supplier_id,'id'=>$supplier_goods_id),true,true);

        $supplier_brand_id = $plat_goods_info['brand_id'];
        $supplier_type_id = $plat_goods_info['type_id'];
        $supplier_spec_info = $this->api->getApiData('getSpecificationByGoodsID',API_VERSION,array('supplier_id'=>$supplier_id,'id'=>$supplier_goods_id),true,true);

        if(!empty($supplier_spec_info)){
            foreach($supplier_spec_info as $spec_info){
                $down_spec = $this->downloadSpecification($supplier_id,$spec_info,$command_id);
                if($down_spec['download']){
                    $return['spec'] = $return['spec'] + array($down_spec['spec_id']=>$down_spec['spec_name']);
                }
                $local_spec_id = $down_spec['spec_id'];
                $tmp_type = $this->bindSpecWithType($supplier_id,$spec_info['spec_id'],$local_spec_id);
                $return['type'] = $return['type'] + $tmp_type;
            }
            unset($tmp_type);
        }

        if(!empty($supplier_brand_id)){
            $down_brand = $this->downloadBrand($supplier_id,$supplier_brand_id,$command_id);
            if(!empty($down_brand)){
                if($down_brand['action'] != 'none'){
                    $return['brand'][$down_brand['action']] = $return['brand'][$down_brand['action']] + array($down_brand['brand_id']=>$down_brand['brand_name']);
                }
                $local_brand_id = $down_brand['brand_id'];
                $tmp_type = $this->bindBrandWithType($supplier_id,$supplier_brand_id,$local_brand_id);
                $return['type'] = $return['type'] + $tmp_type;
            }
        }

        if(!empty($supplier_type_id)){
            $down_type = $this->downloadType($supplier_id,$supplier_type_id);
            if(!empty($down_type)){
                if($down_type['download']){
                    $return['type'] = $return['type'] + array($down_type['type_id']=>$down_type['name']);
                }
                $local_type_id = $down_type['type_id'];

                $tmp_brand = $this->bindTypeWithBrand($supplier_id,$supplier_type_id,$local_type_id,$command_id);
                if(isset($tmp_brand['add'])){
                    $return['brand']['add'] = $return['brand']['add'] + $tmp_brand['add'];
                }else if(isset($tmp_brand['update'])){
                    $return['brand']['update'] = $return['brand']['update'] + $tmp_brand['update'];
                }

                $tmp_spec = $this->bindTypeWithSpec($supplier_id,$supplier_type_id,$local_type_id,$command_id);
                $return['spec'] = $return['spec'] + $tmp_spec;
            }
        }

        $return['locals']['local_type_id'] = $local_type_id;
        $return['locals']['local_spec_id'] = $local_spec_id;
        $return['locals']['local_brand_id'] = $local_brand_id;

        return $return;
    }

    /**
     * 新增sdb_goods_spec_index记录
     *
     * @param int $type_id
     * @param int $spec_id
     * @param int $spec_value_id
     * @param int $goods_id
     * @param int $product_id
     */
    function addGoodsSpecIndex($type_id,$spec_id,$spec_value_id,$goods_id,$product_id){
        $spec_index_info = array(
            'type_id' => $type_id,
            'spec_id' => $spec_id,
            'spec_value_id' => $spec_value_id,
            'goods_id' => $goods_id,
            'product_id' => $product_id
        );

        $rs = $this->db->query("SELECT * FROM sdb_goods_spec_index WHERE 0=1");
        $sql = $this->db->GetInsertSQL($rs,$spec_index_info);
        $this->db->exec($sql);
    }

    /**
     * 新增sdb_supplier_pdtbn记录
     *
     * @param int $supplier_id
     * @param string $local_bn
     * @param string $source_bn
     */
    function addSuplierPdtbn($supplier_id,$local_bn,$source_bn){
        $supplier_info = $this->db->selectrow("SELECT * FROM sdb_supplier WHERE supplier_id=".floatval($supplier_id));
        $sp_id = $supplier_info['sp_id'];
        $supplier_pdtbn_info = array(
            'sp_id' => $sp_id,
            'supplier_id' => $supplier_id,
            'local_bn' => $local_bn,
            'source_bn' => $source_bn
        );
        $rs = $this->db->query("SELECT * FROM sdb_supplier_pdtbn WHERE 0=1");
        $sql = $this->db->GetInsertSQL($rs,$supplier_pdtbn_info);
        $this->db->exec($sql);
    }

    /**
     * 检查goods是否存在
     *
     * @param int $supplier_id
     * @param int $supplier_goods_id
     * @return boolean，存在就true，不存在就false
     */
    function _checkGoodsExist($supplier_id,$supplier_goods_id){
        $goods_info = $this->db->selectrow("SELECT goods_id FROM sdb_goods WHERE supplier_id=".floatval($supplier_id)." AND supplier_goods_id=".intval($supplier_goods_id));
        if(empty($goods_info)){
            return false;
        }else{
            return true;
        }
    }

    /**
     * 生成本地货号
     *
     * @param int $supplier_id，供应商id
     * @param string $bn，上游货号
     * @return string, 本地货号
     */
    function localBn($supplier_id,$bn){
        $rand_width = 2;
        $rand_atom = array(0,1,2,3,4,5,6,7,8,9,'a','b','c','d','e','f','g','h','i','j','k','l','m','n','o','p','q','r','s','t','u','v','w','x','y','z');
        $sp_id_width = 4;
        $rand_bn = '';

        $sql = "SELECT sp_id FROM sdb_supplier WHERE supplier_id=".floatval($supplier_id);
        $supplier = $this->db->selectrow($sql);
        $sp_id = $supplier['sp_id'];
        $sp_id_len = strlen($sp_id);
        if($sp_id_len < $sp_id_width){
            $prex = '';
            for($i=0;$i<$sp_id_width-$sp_id_len;$i++){
                $prex .= '0';
            }
            $local_sp_id = $prex . $sp_id;
        }else{
            $local_sp_id = $sp_id;
        }

        for($i=0;$i<$rand_width;$i++){
            $rand = rand(0,35);
            $rand_bn .= $rand_atom[$rand];
        }

        $local_bn = $local_sp_id . $rand_bn . $bn;

        return $local_bn;
    }

    /**
     * 根据供应商id,上游商品的spec_id，获取对应下载到本地的spec_id
     *
     * @param int $supplier_id
     * @param int $supplier_spec_id
     * @return int
     */
    function _getLocalSpecByPlatSpec($supplier_id,$supplier_spec_id){
        $key = md5($supplier_id.$supplier_spec_id."spec");
        if(!isset($this->local_spec[$key])){
            $local_spec_info = $this->db->selectrow("SELECT spec_id FROM sdb_specification WHERE supplier_id=".floatval($supplier_id)." AND supplier_spec_id=".intval($supplier_spec_id)." ORDER BY spec_id DESC");
            $this->local_spec[$key] = $local_spec_info['spec_id'];//取最新的一条
            return $this->local_spec[$key];
        }else{
            return $this->local_spec[$key];
        }
    }

    /**
     * 根据供应商id，上游商品在本地的spec_id(可以根据_getLocalSpecByPlatSpec获得)，上游商品的规格值id，获取本地对应的规格值id
     *
     * @param int $spec_id
     * @param int $supplier_id
     * @param int $plat_spec_value_id
     * @return int
     */
    function _getLocalSpecValueByPlatSpecValue($spec_id,$supplier_id,$plat_spec_value_id){
        $key = md5($spec_id.$supplier_id.$plat_spec_value_id."spec_value");
        if(!isset($this->local_spec_value[$key])){
            $local_spec_value_info = $this->db->selectrow("SELECT spec_value_id FROM sdb_spec_values WHERE spec_id=".intval($spec_id)." AND supplier_id=".floatval($supplier_id)." AND supplier_spec_value_id=".intval($plat_spec_value_id)." ORDER BY spec_value_id DESC");
            $this->local_spec_value[$key] = $local_spec_value_info['spec_value_id'];
            return $this->local_spec_value[$key];
        }else{
            return $this->local_spec_value[$key];
        }
    }

    /**
     * 根据供应商id，上游商品的gimage_id，获取本地对应的gimage_id
     *
     * @param int $supplier_id
     * @param int $supplier_gimage_id
     * @return int
     */
    function _getLocalGimageByPlatGimage($supplier_id,$supplier_gimage_id){
        $key = md5($supplier_id.$supplier_gimage_id."gimage");
        if(!isset($this->local_gimage[$key])){
            $local_gimage_info = $this->db->selectrow("SELECT gimage_id FROM sdb_gimages WHERE supplier_id=".floatval($supplier_id)." AND supplier_gimage_id=".intval($supplier_gimage_id)." ORDER BY gimage_id DESC");
            $this->local_gimage[$key] = $local_gimage_info['gimage_id'];
            return $this->local_gimage[$key];
        }else{
            return $this->local_gimage[$key];
        }
    }

    /**
     * 根据供应商id，上游商品的type_id，获取本地对应的type_id
     *
     * @param int $supplier_id
     * @param int $supplier_type_id
     * @return int
     */
    function _getLocalTypeByPlatType($supplier_id,$supplier_type_id){
        $key = md5($supplier_id.$supplier_type_id."type");
        if(!isset($this->local_type[$key])){
            $local_type_info = $this->db->selectrow("SELECT type_id FROM sdb_goods_type WHERE supplier_id=".floatval($supplier_id)." AND supplier_type_id=".intval($supplier_type_id)." ORDER BY type_id DESC");
            $this->local_type[$key] = $local_type_info['type_id'];
            return $this->local_type[$key];
        }else{
            return $this->local_type[$key];
        }
    }

    /**
     * 根据供应商id，上游商品的brand_id，获取本地对应的brand_id
     *
     * @param int $supplier_id
     * @param int $supplier_brand_id
     * @return int
     */
    function _getLocalBrandByPlatBrand($supplier_id,$supplier_brand_id){
        $key = md5($supplier_id.$supplier_brand_id."brand");
        if(!isset($this->local_brand[$key])){
            // 从平台上获取brand_name&brand_keywords 查找本地的brand_id  2009-10-13 13:20 wubin
            $brand_info = $this->api->getApiData('getBrandByID',API_VERSION,array('supplier_id'=>$supplier_id,'id'=>$supplier_brand_id),true,true);
            addslashes_array($brand_info);
            if($brand_info['brand_keywords']){
                $local_brand_info = $this->db->selectrow("SELECT brand_id FROM sdb_brand WHERE brand_name='".$brand_info['brand_name']."' AND brand_keywords='".$brand_info['brand_keywords']."' ORDER BY brand_id DESC");
            }else{
                $local_brand_info = $this->db->selectrow("SELECT brand_id FROM sdb_brand WHERE brand_name='".$brand_info['brand_name']."' AND (brand_keywords='' OR brand_keywords IS NULL) ORDER BY brand_id DESC");
            }

            $this->local_brand[$key] = $local_brand_info['brand_id'];
            return $this->local_brand[$key];
        }else{
            return $this->local_brand[$key];
        }
    }

    /**
     * 检查是否是远程图片，判断前7位是否为imgget:，如果是则是本地图片，否则为远程图片
     *
     * @param string $image_path
     * @return boolean，是远程图片就返回true，不然返回false
     */
    function _checkRemoteImage($image_path){
        $check = substr($image_path,0,7);
        if($check == 'imgget:'){
            return false;
        }else{
            return true;
        }
    }

    /**
     * 根据平台中的供应商列表更新本地的供应商列表的状态(有供销关系取消,本地的status修改为0) 
     *  现在的机制是供应商全取,如果按列表方式去取的话,本方法得重新考虑 wubin 2010-01-11 17:17 wubin
     *
     * @param array $aPlatSupplier // 格式同从平台取回的数据格式一样array(
     *                                                              0=>array('supplier_id'=>'xxx',...),
     *                                                              1=>array(...),
     *                                                              ...
     *                                                         )
     */
    function updateBreakSupplierStatus($aPlatSupplier) {
        $aLocSupplier = $this->db->select("SELECT supplier_id FROM sdb_supplier");
        foreach($aLocSupplier as $row) {
            $aLoc = $row['supplier_id'];
        }
        foreach($aPlatSupplier as $row) {
            $aPlat = $row['supplier_id'];
        }
        $aDiff = array_diff($aLoc,$aPlat);
        if($aDiff) {
            $aDiff = array_values($aDiff);
            $this->db->exec("UPDATE sdb_supplier SET status=0 WHERE supplier_id IN('".explode("','",$aDiff)."')");
        }
    }

    /**
     * 同步供应商列表
     *
     * @param int $supplier_id，供应商id，如果不填，则同步全部，否则同步该供应商
     * @param int $page，当前页码
     * @param int $limit，每页显示记录数
     * @param int $count，总记录数
     * @return mix ，同步单个供应商时，会返回同步结果，批量同步返回true、false
     */
    function syncSupplier($supplier_id=NULL,&$count,$page=1,$limit=0){
        $return = array();
        if(is_null($supplier_id)){
            if($limit != 0){
                $params = array('pages'=>$page,'counts'=>$limit);
            }else{
                $params = array();
            }
            $supplier_list = $this->api->getApiData('getSuppliers',API_VERSION,$params,true,true);
            if(!empty($supplier_list)){
                $count = $supplier_list[0]['row_count'];
                foreach($supplier_list as $v){
                    $this->_syncSupplier($v);
                }
                // 将断了分销关系的供应商status设成0 2010-01-11 17:38 wubin
                $this->updateBreakSupplierStatus($supplier_list);
                return true;
            }else{
                return false;
            }
        }else{
            $params = array('id'=>$supplier_id);
            $supplier = $this->api->getApiData('getSupplierById',API_VERSION,$params,true,true);
            if(!empty($supplier)){
                return $this->_syncSupplier($supplier);
            }else{
                return false;
            }
        }
    }

    /**
     * 同步远程供应商信息到本地数据库
     *
     * @param array $plat_supplier_info，远程供应商信息
     * @return array，array(
     *                  'supplier_brief_name' => xxx,
     *                  'status' => xxx,
     *                  'action' => 'none|update|add'
     *              )
     */
    function _syncSupplier($plat_supplier_info){
        $return = array();
        $supplier_domain_info = $this->api->getApiData('getDomain',API_VERSION,array('id'=>$plat_supplier_info['supplier_id']));
        $local_supplier_info = $this->db->selectrow("SELECT * FROM sdb_supplier WHERE supplier_id=".floatval($plat_supplier_info['supplier_id']));
        if(!empty($local_supplier_info)){
            $supplier_info_update = array();
            if($local_supplier_info['brief_name'] != $plat_supplier_info['brief_name']){
                $supplier_info_update['supplier_brief_name'] = $plat_supplier_info['brief_name'];
                $return['supplier_brief_name'] = $plat_supplier_info['brief_name'];
            }else{
                $return['supplier_brief_name'] = $local_supplier_info['brief_name'];
            }

            if($local_supplier_info['status'] != $plat_supplier_info['bind_status']){
                $supplier_info_update['status'] = $plat_supplier_info['bind_status'];
                $return['status'] = $plat_supplier_info['bind_status'];
            }else{
                $return['status'] = $local_supplier_info['status'];
            }

            $supplier_info_update['has_new'] = $this->checkSupplierHasSync($plat_supplier_info['supplier_id'])?'true':'false';
            $supplier_info_update['domain'] = isset($supplier_domain_info['domain'])?$supplier_domain_info['domain']:'';

            // 是否存在成本价更新 2009-01-12 17:07 wubin
            $oCostSync = $this->system->loadModel('distribution/costsync');
            $supplier_info_update['has_cost_new'] = $oCostSync->getCostSyncCount($plat_supplier_info['supplier_id'])?'true':'false';

            if(!empty($supplier_info_update)){
                $rs = $this->db->query("SELECT * FROM sdb_supplier WHERE supplier_id=".floatval($plat_supplier_info['supplier_id']));
                $sql = $this->db->GetUpdateSQL($rs,$supplier_info_update);
                $this->db->exec($sql);

                $return['action'] = 'update';
            }else{
                $return['action'] = 'none';
            }

            return $return;
        }else{
            $supplier_info_insert = array(
                'supplier_id' => $plat_supplier_info['supplier_id'],
                'supplier_brief_name' => $plat_supplier_info['brief_name'],
                'status' => $plat_supplier_info['bind_status'],
                'has_new' => $this->checkSupplierHasSync($plat_supplier_info['supplier_id'])?'true':'false',
                'domain' => $supplier_domain_info['domain']
            );

            $rs = $this->db->query("SELECT * FROM sdb_supplier WHERE 0=1");
            $sql = $this->db->GetInsertSQL($rs,$supplier_info_insert);
            $this->db->exec($sql);

            $return = array(
                'supplier_brief_name' => $plat_supplier_info['brief_name'],
                'status' => $plat_supplier_info['status'],
                'action' => 'add'
            );

            return $return;
        }
    }

    /**
     * 检查供应商是否有商品同步
     *
     * @param int $supplier_id
     * @return boolean，true:有新商品需要同步，false:没有商品需要同步
     */
    function checkSupplierHasSync($supplier_id){
        $time = time();
        $flag = false;  //是否需要同步
        $pline_id = array();
        $supplier_info = $this->db->selectrow("SELECT * FROM sdb_supplier WHERE supplier_id=".floatval($supplier_id));
        $params = array(
            'supplier_id' => $supplier_id,
            'last_sync_time' => empty($supplier_info['sync_time_for_plat'])?0:$supplier_info['sync_time_for_plat'],
            'pages' => 1,
            'counts' => 1
        );

        $pline_info = $this->api->getApiData('getProductLineList',API_VERSION,array('id'=>$supplier_id),true,true);

        if(!empty($pline_info)){
            foreach($pline_info as $pline){
                $pline_id[] = $pline['pline_id'];
                $tmp_pline_info[$pline['pline_id']] = $pline;
            }
            unset($pline_info);
            $pline_info = $tmp_pline_info;
            $params['pline'] = json_encode($pline_id);
            $sync_list = $this->api->getApiData('getUpdateList',API_VERSION,$params,true,true);

            if(empty($sync_list)){
                $flag = false;
            }else{
                $flag = true;
            }
        }else{
            $flag = false;
        }

        $old_supplier_pline = unserialize($supplier_info['supplier_pline']);
        $old_supplier_pline = empty($old_supplier_pline)?array():$old_supplier_pline;
        $old_pline_id = array_keys($old_supplier_pline);

        if(!$flag){
            //如果有新的产品线赋予权限，且这些产品线下有商品，那么就表示需要同步
            $new_pline_id = array_diff($pline_id,$old_pline_id);
            if(!empty($new_pline_id)){
                $goods_id_list = $this->api->getApiData("getGoodsIdByPline",API_VERSION,array('supplier_id'=>$supplier_id,'pline'=>json_encode($new_pline_id),'pages'=>1,'counts'=>1),true,true);
                if(!empty($goods_id_list)){
                    $flag = true;
                }
            }
        }
        if(!$flag){
            //如果有删除某产品线，那么要更新同步列表，把那些产品线的商品置为不可见，所以也需要同步
            $del_pline_id = array_diff($old_pline_id,$pline_id);
            if(!empty($del_pline_id)){
                $flag = true;
            }
        }
        if(!$flag){
            //如果有某产品线更新了其分类或品牌对应关系，那么也需要同步
            $same_pline_id = array_intersect($pline_id,$old_pline_id);
            if(!empty($same_pline_id)){
                foreach($same_pline_id as $v){
                    // 增加了产品线名称,为了以前的数据比对,现在unset掉产品线名称 wubin 2009-09-17
                    unset($old_supplier_pline[$v]['pline_name']);

                    if($old_supplier_pline[$v] != array('cat_id'=>$pline_info[$v]['cat_id'].($pline_info[$v]['child_cat_path']==''?'':','.$pline_info[$v]['child_cat_path']),'brand_id'=>$pline_info[$v]['brand_id'])){
                        $flag = true;
                        break;
                    }
                }
            }
        }

        return $flag;
    }

    /**
     * 根据产品线来控制原有 更新列表中记录是否要显示
     * 1.如果新增一条产品线，那么找到该产品线下的所有goods_id，并把原有的更新列表中action为6(新增商品记录)的if_show置为true，并更新时间
     * 2.如果删除了一条产品线，那么找到该产品线下的所有goods_id，并把原有的更新列表中所有该goods_id的记录的if_show置为false
     * 3.如果修改了一条产品线，那么要做2、1
     *
     */
    function filterUpdateList_1($supplier_id,$command_type='sync'){
        $time = time();
        $pline_id = array();
        $new_pline_id = array();
        $del_pline = array();
        $simple_pline_info = array();

        $supplier_info = $this->db->selectrow("SELECT * FROM sdb_supplier WHERE supplier_id=".floatval($supplier_id));
        $pline_info = $this->api->getApiData('getProductLineList',API_VERSION,array('id'=>$supplier_id),true,true);

        if(!empty($pline_info)){
            foreach($pline_info as $v){
                $pline_id[] = $v['pline_id'];
                $tmp_pline_info[$v['pline_id']] = $v;
                $simple_pline_info[$v['pline_id']] = array(
                    'pline_name'=>$v['pline_name'],   // 新增加产品线名称 $2009-9-1 wubin@shopex.cn
                    'cat_id' => $v['cat_id'].($v['child_cat_path']==''?'':','.$v['child_cat_path']),
                    'brand_id' => $v['brand_id']
                );
            }
            unset($pline_info);
            $pline_info = $tmp_pline_info;
        }else{
            //获取产品线信息失败则什么都不做
            return true;
        }

        if($pline_info[0]['pline_id'] == 1){
            $new_pline_id = array(1);
        }else{
            $old_supplier_pline = unserialize($supplier_info['supplier_pline']);
            $old_supplier_pline = empty($old_supplier_pline)?array():$old_supplier_pline;
            $old_pline_id = array_keys($old_supplier_pline);


            $new_pline_id = array_diff($pline_id,$old_pline_id);
            $del_pline_id = array_diff($old_pline_id,$pline_id);
            if(!empty($del_pline_id)){
                foreach($del_pline_id as $del_id){
                    $del_pline[$del_id] = $old_supplier_pline[$del_id];
                }
            }

            $same_pline_id = array_intersect($pline_id,$old_pline_id);
            if(!empty($same_pline_id)){
                foreach($same_pline_id as $v){
                    // 比对新加入'pline_name' wubin 2009-09-18 13:31:15
                   if($old_supplier_pline[$v] != array('cat_id'=>$pline_info[$v]['cat_id'].($pline_info[$v]['child_cat_path']==''?'':','.$pline_info[$v]['child_cat_path']),'brand_id'=>$pline_info[$v]['brand_id'],'pline_name'=>$pline_info[$v]['pline_name'])){
                        $new_pline_id[] = $v;
                        $del_pline[$v] = $old_supplier_pline[$v];
                    }
                }
            }

            if(!empty($del_pline)){
                //如果有发生需要删除产品的情况，那么需要根据配置自动处理失去的产品线下的商品如何处理
                $autosync = $this->system->loadModel('distribution/autosync');
                $autosync->doLoosePline($supplier_id,$simple_pline_info);
                
                $remain_pline = $old_supplier_pline;
                $del_pline_id = array_keys($del_pline);
                foreach($del_pline_id as $v){   //原本的产品线信息去除需要删除的，留下剩下的
                    unset($remain_pline[$v]);
                }
                $new_remain_pline = array();

                if(!empty($remain_pline)){
                    //合并剩余产品线的权限范围，比如array('cat_id'=>'4,3,2','brand_id'=>'5')，array('cat_id'=>'5,4,2','brand_id'=>'5')，合并的权限结果就是array('cat_id'=>'5,4,3,2','brand_id'=>'5')
                    foreach($remain_pline as $v){
                        if(isset($new_remain_pline[$v['brand_id']])){
                            if($v['cat_id'] == '-1' || $new_remain_pline[$v['brand_id']]['cat_id'] == "-1"){
                                $new_remain_pline[$v['brand_id']] = array(
                                    'cat_id' => '-1',
                                    'brand_id' => $v['brand_id']
                                );
                            }else{
                                $tmp1 = explode(",",$new_remain_pline[$v['brand_id']]['cat_id']);
                                $tmp2 = explode(",",$v['cat_id']);
                                $new_remain_pline[$v['brand_id']] = array(
                                    'cat_id'=>implode(",",array_unique(array_merge($tmp1,$tmp2))),
                                    'brand_id'=>$v['brand_id']
                                );  //这里的数组下标只是为了方便按照brand_id合并和今后迅速定位时有用，没有实际意义
                                unset($tmp1);
                                unset($tmp2);
                            }
                        }else{
                            $new_remain_pline[$v['brand_id']] = array(
                                'cat_id' => $v['cat_id'],
                                'brand_id' => $v['brand_id']
                            );
                        }
                    }
                    //因为某些产品线的brand_id为-1，所以要把-1的产品线的cat_id权限都合并到其余的brand_id组合的cat_id上去
                    if(!empty($new_remain_pline)){
                        $tmp_new_remain_pline = $new_remain_pline;
                        if(isset($tmp_new_remain_pline[-1])){
                            unset($tmp_new_remain_pline[-1]);
                        }
                        $all_brand_id = array_keys($tmp_new_remain_pline);

                        foreach($tmp_new_remain_pline as $k=>$v){   //$k是该条数据对应的brand_id，$tmp_new_remain_pline的作用是排除brand_id为-1的情况，另外处理
                            if(isset($new_remain_pline[-1])){
                                if($v['cat_id'] == "-1"){   //如果cat_id为-1，那么什么都不用做，因为对于该brand_id都有权限
                                    //do nothing
                                }else{
                                    $tmp1 = explode(",",$v['cat_id']);
                                    $tmp2 = explode(",",$new_remain_pline[-1]['cat_id']);

                                    $stay_cat = implode(",",array_unique(array_merge($tmp1,$tmp2)));
                                    $del_brand = $v['brand_id'];
                                    $rs = $this->db->query("SELECT * FROM sdb_data_sync_".$supplier_id." WHERE if_show='true' AND cat_id NOT IN ({$stay_cat}) AND brand_id=".intval($del_brand));
                                    $sql = $this->db->GetUpdateSQL($rs,array('if_show'=>'false'));
                                    $this->db->exec($sql);

                                    unset($tmp1);
                                    unset($tmp2);
                                }
                            }else{
                                if($v['cat_id'] == "-1"){
                                    //do nothing
                                }else{
                                    $rs = $this->db->query("SELECT * FROM sdb_data_sync_".$supplier_id." WHERE if_show='true' AND cat_id NOT IN (".$v['cat_id'].") AND brand_id=".intval($v['brand_id']));
                                    $sql = $this->db->GetUpdateSQL($rs,array('if_show'=>'false'));
                                    $this->db->exec($sql);
                                }
                            }
                        }

                        //处理brand_id是-1的情况，分开处理的原因是要获取除-1以外的不需要处理的brand_id
                        if(isset($new_remain_pline[-1])){
                            if($new_remain_pline[-1]['cat_id'] == "-1"){    //代表是-1,-1的组合，表示全部商品
                                //do nothing
                            }else{
                                $query = "SELECT * FROM sdb_data_sync_".$supplier_id." WHERE if_show='true' AND cat_id NOT IN(".$new_remain_pline[-1]['cat_id'].")";
                                if(isset($all_brand_id) && !empty($all_brand_id)){
                                     $query .= "AND brand_id NOT IN (".implode(",",$all_brand_id).")";
                                }
                                $rs = $this->db->query($query);
                                $sql = $this->db->GetUpdateSQL($rs,array('if_show'=>'false'));
                                $this->db->exec($sql);
                            }
                        }else{
                            //除了在$all_brand_id中出现过的brand_id外，都是没有权限的
                            $rs = $this->db->query("SELECT * FROM sdb_data_sync_".$supplier_id." WHERE if_show='true' AND brand_id NOT IN (".implode(",",$all_brand_id).")");
                            $sql = $this->db->GetUpdateSQL($rs,array('if_show'=>'false'));
                            $this->db->exec($sql);
                        }
                    }
                }else{
                    $rs = $this->db->query("SELECT * FROM sdb_data_sync_".$supplier_id." WHERE if_show='true'");
                    $sql = $this->db->GetUpdateSQL($rs,array('if_show'=>'false'));
                    $this->db->exec($sql);
                }
            }
        }

        if(!empty($new_pline_id)){
            //由于获取goods_id的记录过大，所以必须加任务异步取
            $api_name = "getGoodsIdByPline";
            $api_params = array('supplier_id'=>$supplier_id,'pline'=>json_encode(array_values($new_pline_id)),'command_type'=>$command_type); //command_type不是api所需要的参数，但是为了后面能传递是下载引发的还是更新引发的，所以临时拼接了下，需要在请求api前被处理掉
            $api_version = API_VERSION;
            $api_action = "distribution/datasync|filterUpdateList_1";
            $sync_job = $this->system->loadModel("distribution/syncjob");
            $sync_job->addApiListJob($supplier_id,$api_name,$api_params,$api_version,$api_action,100);
        }

        //更新supplier表，把supplier_pline字段更新，更新时注意cat_id是“,”分割的字符窜
        $rs = $this->db->query("SELECT * FROM sdb_supplier WHERE supplier_id=".floatval($supplier_id));
        $sSupplier_pline = serialize($simple_pline_info);
        // addslashes_array($sSupplier_pline); // 执行sql时会进行addslashes处理
        $sql = $this->db->GetUpdateSQL($rs,array('supplier_pline'=>$sSupplier_pline));
        $this->db->exec($sql);
    }

    /**
     * 继续filterUpdateList_1的工作，把新增的pline下的goods_id显示状态设为可显示
     *
     * @param string $supplier_id
     *
     * @return 0    无任务需要作
     *         1    继续做任务
     */
    function filterUpdateList_2($supplier_id,$command_type){
        $time = time();
        $api_name = "getGoodsIdByPline";
        $api_version = API_VERSION;
        $api_action = "distribution/datasync|filterUpdateList_1";

        $sync_job = $this->system->loadModel("distribution/syncjob");
        $data = $sync_job->doApiListJob($supplier_id,$api_name,$api_version,$api_action);
        if(!empty($data)){
            foreach($data as $goods_id){
                $supplier_goods_id[] = $goods_id['ob_id'];
            }
            
            if($command_type == 'sync'){
                $oAutoSync = &$this->system->loadModel('distribution/autosync');
                
                $command_info = $this->db->select("SELECT command_id FROM sdb_data_sync_".$supplier_id." WHERE if_show='false' AND command=6 AND goods_id IN (".implode(",",$supplier_goods_id).")");
                if($command_info){
                    foreach($command_info as $v){
                        $oAutoSync->addAutoSyncTask($supplier_id,$v['command_id']);
                    }
                }
            }
            
            $rs = $this->db->query("SELECT * FROM sdb_data_sync_".$supplier_id." WHERE if_show='false' AND command=6 AND goods_id IN (".implode(",",$supplier_goods_id).")");
            $sql = $this->db->GetUpdateSQL($rs,array('if_show'=>'true','command_type'=>$command_type));
            $this->db->exec($sql);
            
            if($downloaded_goods=$this->db->select("SELECT supplier_goods_id FROM sdb_goods WHERE supplier_id=".floatval($supplier_id)." AND supplier_goods_id IN (".implode(",",$supplier_goods_id).")")){
                foreach($downloaded_goods as $id){
                    $d_goods_id[] = $id['supplier_goods_id'];
                }
                
                if($command_type == 'sync'){
                    $command_info = $this->db->select("SELECT command_id FROM sdb_data_sync_".$supplier_id." WHERE if_show='false' AND command<>6 AND goods_id IN (".implode(",",$d_goods_id).")");
                    if($command_info){
                        foreach($command_info as $v){
                            $oAutoSync->addAutoSyncTask($supplier_id,$v['command_id']);
                        }
                    }
                }
                
                $rs = $this->db->query("SELECT * FROM sdb_data_sync_".$supplier_id." WHERE if_show='false' AND command<>6 AND goods_id IN (".implode(",",$d_goods_id).")");
                $sql = $this->db->GetUpdateSQL($rs,array('if_show'=>'true','command_type'=>$command_type));
                $this->db->exec($sql);
            }
            return 1;
        }else{
            return 0;
        }
    }


    /**
     * 获取供应商产品线列表
     * 一下子都取完，不分页
     *
     * @param int $supplier_id
     * @return array, array(
     *                  array(
     *                      'pline_id' => xxx,
     *                      'pline_name' => xxx,
     *                      'custom_name' => xxx,
     *                      'disabled' => xxx,
     *                      'cat_id' => xxx,
     *                      'brand_id' => xxx,
     *                      'last_modified' => xxx,
     *                      'child_cat_path' => xxx,xxx
     *                  ),
     *                  array(
     *                      'pline_id' => xxx,
     *                      'pline_name' => xxx,
     *                      'custom_name' => xxx,
     *                      'disabled' => xxx,
     *                      'cat_id' => xxx,
     *                      'brand_id' => xxx,
     *                      'last_modified' => xxx,
     *                      'child_cat_path' => xxx,xxx
     *                  ),
     *              )
     */
    function getProductLine($supplier_id){
        return $this->api->getApiData('getProductLineList',API_VERSION,array('id'=>$supplier_id),true,true);
    }

    /**
     * 更新商品图片，直接进同步列表，如果下载失败会将该command_id的状态改成有未下载完成的图片，然后调用downloadImage(true)
     *
     * @param int $command_id，同步列表的id
     * @param int $supplier_id，供应商的id
     * @param int $supplier_goods_id，供应商的商品id
     */
    function updateGoodsImage($command_id,$supplier_id,$supplier_goods_id){
        $plat_gimages = $this->api->getApiData('getImagesByGoodsId',API_VERSION,array('supplier_id'=>$supplier_id,'id'=>$supplier_goods_id),true,true);
        $local_goods = $this->db->selectrow("SELECT goods_id FROM sdb_goods WHERE supplier_id=".floatval($supplier_id)." AND supplier_goods_id=".intval($supplier_goods_id)." ORDER BY goods_id DESC");
        $local_gimages = $this->db->select("SELECT * FROM sdb_gimages WHERE goods_id=".intval($local_goods['goods_id'])." AND supplier_id=".floatval($supplier_id));
        $storager = $this->system->loadModel('system/storager');

        $plat_gimage_ids = array();
        $local_supplier_gimage_ids = array();

        if(!empty($plat_gimages)){
            foreach($plat_gimages as $plat_gimage){
                $plat_gimage_ids[] = $plat_gimage['gimage_id'];
            }
        }
        if(!empty($local_gimages)){
            foreach($local_gimages as $local_gimage){
                $local_supplier_gimage_ids[] = $local_gimage['supplier_gimage_id'];
            }
        }


        if(!empty($plat_gimages)){

            if(empty($local_gimages)){  //本地没有图片，那么所用同步的图片都要新增到本地来
                foreach($plat_gimages as $gimage){
                    $this->addGimage($command_id,$supplier_id,$local_goods['goods_id'],$gimage);
                }
            }else{  //比对平台的gimage和本地的gimage，有新增就新增，有删除就删除。注意：不存在图片更新的问题，图片除了新增就是删除，所谓的更新就是先删后新增，此时gimage_id肯定变了
                foreach($local_gimages as $l_gimage){
                    if(!in_array($l_gimage['supplier_gimage_id'],$plat_gimage_ids)){
                        $this->db->exec("DELETE FROM sdb_gimages WHERE gimage_id=".intval($l_gimage['gimage_id']));
                        if($l_gimage['is_remote'] == 'false'){
                            $storager->remove($l_gimage['small']);
                            $storager->remove($l_gimage['big']);
                            $storager->remove($l_gimage['thumbnail']);
                            unlink(HOME_DIR . "/upload/" . $l_gimage['source']);
                        }
                    }
                }

                foreach($plat_gimages as $p_gimage){
                    if(!in_array($p_gimage['gimage_id'],$local_supplier_gimage_ids)){
                        $this->addGimage($command_id,$supplier_id,$local_goods['goods_id'],$p_gimage);
                    }
                }
            }
        }else{
            if(!empty($local_gimages)){
                foreach($local_gimages as $l_gimage){
                    $this->db->exec("DELETE FROM sdb_gimages WHERE gimage_id=".intval($l_gimage['gimage_id']));
                    if($l_gimage['is_remote'] == 'false'){
                        $storager->remove($l_gimage['small']);
                        $storager->remove($l_gimage['big']);
                        $storager->remove($l_gimage['thumbnail']);
                        unlink(HOME_DIR . "/upload/" . $l_gimage['source']);
                    }
                }
            }
        }

        //更新图片的默认图，取第一张图片作为默认图片
        $gimage_info = $this->db->selectrow("SELECT gimage_id FROM sdb_gimages WHERE goods_id=".$local_goods['goods_id']);

        //如果本地图片被完全删除了，那么清空sdb_goods表的图片字段
        if(!$gimage_info){
            $update_info['thumbnail_pic'] = NULL;
            $update_info['small_pic'] = NULL;
            $update_info['big_pic'] = NULL;
            $update_info['image_default'] = NULL;
        }else{
            $update_info['thumbnail_pic'] = $gimage_info['thumbnail'];
            $update_info['small_pic'] = $gimage_info['small'];
            $update_info['big_pic'] = $gimage_info['big'];
            $update_info['image_default'] = $gimage_info['gimage_id'];
        }

        $rs = $this->db->query("SELECT * FROM sdb_goods WHERE goods_id=".intval($local_goods['goods_id']));

        $sql = $this->db->GetUpdateSQL($rs,$update_info);

        $this->db->exec($sql);
    }

    /**
     * 同步供应商的货品库存
     *
     * @param int $supplier_id，供应商id
     * @param int $product_id，上游货品id
     */
    function syncProductStore($supplier_id,$product_id){
        $plat_product_info = $this->api->getApiData('getProductByID',API_VERSION,array('supplier_id'=>$supplier_id,'id'=>$product_id),true,true);
        $plat_product_bn = $plat_product_info['bn'];

        if($plat_product_info['store'] === "" || is_null($plat_product_info['store'])){
            $plat_product_store = NULL;
        }else{
            $plat_product_store = $plat_product_info['store'] - intval($plat_product_info['freez']);    //需要同步的是最大可下单库存
        }

        $store = array('store'=>$plat_product_store);

        $local_product_info = $this->db->selectrow("SELECT * FROM sdb_products AS p,sdb_supplier_pdtbn AS s WHERE s.source_bn='".$plat_product_bn."' AND s.local_bn=p.bn AND s.supplier_id=".floatval($supplier_id));
        $local_product_id = $local_product_info['product_id'];
        $local_goods_id = $local_product_info['goods_id'];
        $local_product_store = $local_product_info['store'];

        $rs = $this->db->query("SELECT * FROM sdb_products WHERE product_id=".intval($local_product_id));
        $sql = $this->db->GetUpdateSQL($rs,$store);
        $this->db->exec($sql);

        //更新商品的store
        if(is_null($plat_product_store)){
            $goods_store = NULL;
        }else{
            if($this->db->selectrow("SELECT product_id FROM sdb_products WHERE goods_id=".intval($local_goods_id)." AND store IS NULL")){
                $goods_store = NULL;
            }else{
                $all_store = $this->db->selectrow("SELECT sum(store) as counts FROM sdb_products WHERE goods_id=".intval($local_goods_id));
                $goods_store = $all_store['counts'];
            }
        }

        $rs = $this->db->query("SELECT store FROM sdb_goods WHERE goods_id=".intval($local_goods_id));
        $sql = $this->db->GetUpdateSQL($rs,array('store'=>$goods_store));
        $this->db->exec($sql);

    }

    /**
     * 获取平台的商品信息的完整信息，包括所有图片路径、规格、类型等
     *
     * @param int $supplier_id
     * @param int $supplier_goods_id
     * @return array
     */
    function getSupplierGoodsInfo($supplier_id,$supplier_goods_id){
        $goods_info = $this->api->getApiData('getGoodsByID',API_VERSION,array('supplier_id'=>$supplier_id,'id'=>$supplier_goods_id),true,true);

        if(!empty($goods_info)){
            /*暂时分类名不显示
            $supplier_cat_id = $goods_info['cat_id'];
            $supplier_cat_info = $this->api->getApiData('getCategoryByID',API_VERSION,array('supplier_id'=>$supplier_id,'id'=>$supplier_cat_id),true,true);
            $goods_info['cat_name'] = $supplier_cat_info['cat_name'];
            */
            $supplier_type_id = $goods_info['type_id'];
            $supplier_type_info = $this->api->getApiData('getTypeByID',API_VERSION,array('supplier_id'=>$supplier_id,'id'=>$supplier_type_id),true,true);
            $goods_info['type_name'] = $supplier_type_info['name'];
            $goods_info['type_props'] = $supplier_type_info['props'];
            /*暂时货品相关信息不显示
            $goods_info['products'] = $this->api->getApiData('getProductsByGoodsID',API_VERSION,array('supplier_id'=>$supplier_id,'id'=>$supplier_goods_id),true,true);

            $spec_info = $this->api->getApiData('getSpecificationByGoodsID',API_VERSION,array('supplier_id'=>$supplier_id,'id'=>$supplier_goods_id),true,true);
            $goods_spec = array();
            if(!empty($spec_info)){
                foreach($spec_info as $spec){
                    $goods_spec[$spec['spec_id']] = $spec;
                    unset($goods_spec[$spec['spec_id']]['struct']);
                    if(!empty($spec['struct'])){
                        foreach($spec['struct'] as $spec_value){
                            $goods_spec[$spec['spec_id']]['struct'][$spec_value['spec_value_id']] = $spec_value;
                            if($spec['spec_type'] == 'image'){
                                $goods_spec[$spec['spec_id']]['struct'][$spec_value['spec_value_id']]['spec_image'] = "http://".IMAGESERVER_HOST.IMAGESERVER_PATH."?method=getPicById&api_version=".API_VERSION."&supplier_id=".$supplier_id."&id=".$spec_value['spec_value_id']."&type=specpic";
                            }
                        }
                    }
                }
            }
            $goods_info['spec'] = $goods_spec;*/

            /*暂时图片也不显示
            $supplier_gimage_info = $this->api->getApiData('getImagesByGoodsId',API_VERSION,array('supplier_id'=>$supplier_id,'id'=>$supplier_goods_id),true,true);
            if(!empty($supplier_gimage_info)){
                foreach($supplier_gimage_info as $key=>$gimage){
                    $gimage_id = $gimage['gimage_id'];
                    $goods_info['gimage'][$key]['thumbnail'] = "http://".IMAGESERVER_HOST.IMAGESERVER_PATH."?method=getPicById&api_version=".API_VERSION."&supplier_id=".$supplier_id."&id=".$gimage_id."&type=thumbnail";
                    $goods_info['gimage'][$key]['smallpic'] = "http://".IMAGESERVER_HOST.IMAGESERVER_PATH."?method=getPicById&api_version=".API_VERSION."&supplier_id=".$supplier_id."&id=".$gimage_id."&type=smallpic";
                }
            }else{
                $goods_info['gimage'] = array();
            }
            */
            return $goods_info;
        }else{
            return false;
        }
    }


    /**
     * 分配将上游的品牌、类型、规格等数据存入本地临时表
     *
     * @param int $supplier_id
     * @param array $pline
     */
    function addSyncTmpData($supplier_id,$pline){
        $oSupplier = $this->system->loadModel('distribution/supplier');


        $pline_id = array_keys($pline);
        $supplier_info = $this->db->selectrow("SELECT * FROM sdb_supplier WHERE supplier_id=".floatval($supplier_id));
        $down_params = array(
            'supplier_id' => $supplier_id,
            'last_sync_time' => $supplier_info['sync_time_for_plat'],
            'cmd_action' => 6,
            'pline' => json_encode($pline_id)
        );
        $goods_count = $this->api->getApiData('getUpdateListCount',API_VERSION,$down_params,true,true);

        //判断有新增商品需要下载再下载品牌、类型、规格等临时数据
        if(!empty($goods_count) && intval($goods_count['row_count']) > 0){
            $oSupplier->clearTmpData($supplier_id);

            $sync_job = $this->system->loadModel("distribution/syncjob");
            $params = array('id'=>$supplier_id);
            $api_version = API_VERSION;
            $action = 'distribution/datasync|addSyncTmpData';

            $sync_job->addApiListJob($supplier_id,'getBrands',$params,$api_version,$action,50);
            $sync_job->addApiListJob($supplier_id,'getTypes',$params,$api_version,$action,50);
            $sync_job->addApiListJob($supplier_id,'getSpecifications',$params,$api_version,$action,50);
            $sync_job->addApiListJob($supplier_id,'getCategories',$params,$api_version,$action,50);
        }
    }

    /**
     * 将上游的品牌、类型、规格等数据存入本地临时表
     *
     * @param int $supplier_id
     * @param string $api_name
     */
    function doSyncTmpData($supplier_id,$api_name){
        switch ($api_name){
            case 'getBrands' :
                $s_type = 'brand';
                break;
            case 'getTypes' :
                $s_type = 'goods_type';
                break;
            case 'getSpecifications' :
                $s_type = 'spec';
                break;
            case 'getCategories' :
                $s_type = 'goods_cat';
                break;
            default:
                $s_type = '';
                break;
        }

        $this->_fillTmpData($supplier_id,$api_name,$s_type);
    }

    function _fillTmpData($supplier_id,$api_name,$s_type){
        switch ($s_type){
            case 'brand' :
                $ob_id = 'brand_id';
                break;
            case 'goods_type' :
                $ob_id = 'type_id';
                break;
            case 'spec' :
                $ob_id = 'spec_id';
                break;
            case 'goods_cat' :
                $ob_id = 'cat_id';
                break;
            default :
                $ob_id = '';
                break;
        }

        $sync_job = $this->system->loadModel("distribution/syncjob");
        $api_version = API_VERSION;
        $api_action = 'distribution/datasync|addSyncTmpData';

        $datas = $sync_job->doApiListJob($supplier_id,$api_name,$api_version,$api_action);
        if(!empty($datas)){
            foreach($datas as $data){
                unset($data['row_count']);
                $insert = array(
                    's_type' => $s_type,
                    'ob_id' => $data[$ob_id],
                    'supplier_id' => $supplier_id,
                    's_data' => serialize($data)
                );
                $rs = $this->db->query("SELECT * FROM sdb_sync_tmp WHERE 0=1");
                $sql = $this->db->GetInsertSQL($rs,$insert);
                if($sql){
                    $this->db->exec($sql);
                }
            }
        }
    }

    /**
     * 检查是否有商品下载任务
     *
     * @param int $supplier_id
     * @return boolean
     */
    function ifDownloading($supplier_id){
        if($this->db->selectrow("SELECT job_id FROM sdb_job_data_sync WHERE supplier_id=".floatval($supplier_id))
        || $this->db->selectrow("SELECT job_id FROM sdb_job_goods_download WHERE supplier_id=".floatval($supplier_id))){
            return true;
        }else{
            return false;
        }
    }
}
?>
