<?php
/**
 * mdl_syncjob
 *
 * @uses modelFactory
 * @package
 * @version $Id: mdl.syncjob.php 2009-04-30 05:31:30Z hujianxin $
 * @copyright 2003-2009 ShopEx
 * @author hujianxin <hjx@shopex.cn>
 * @license Commercial
 */


class mdl_syncjob extends modelFactory{
    //新增任务表，在分页同步更新列表时，在第一次就将job表分配好，比如取得同步列表，发现有1000条记录，然后我每次只取100条记录，那么产生10个job记录，每个job完成插入data_sync表
    //同样，商品下载也加入job
    //访问时都读job表

    function mdl_syncjob(){
        parent::modelFactory();
        $token = $this->system->getConf('certificate.token');
        $this->api = $this->system->api_call(PLATFORM,PLATFORM_HOST,PLATFORM_PATH,PLATFORM_PORT,$token);
    }

    /**
     * 分配数据同步datasync列表的任务
     *
     * @param int $supplier_id，供应商id
     * @param array $supplier_pline，产品线信息，array(
     *                                              $supplier_pline_id => array(
     *                                                                      'cat_id' => xxx,xxx,xxx //所有符合该产品线的cat_id
     *                                                                      'brand_id' => xxx
     *                                                                  ),
     *                                          )
     * @param boolean $auto_download，当执行更新datasync任务时，是否自动分配下载商品的任务
     * @param int $limit，每个任务更新datasync的数量
     * @param int $to_cat_id，如果需要自动分配下载商品任务的话，$to_cat_id为需要下载到本地的分类id，如果是其他任务，那么就为NULL
     */
    function addDataSyncJob($supplier_id,$supplier_pline=array(),$auto_download=false,$limit=20,$to_cat_id=NULL){
        $supplier_info = $this->db->selectrow("SELECT * FROM sdb_supplier WHERE supplier_id=".floatval($supplier_id));
        $last_sync_time = $supplier_info['sync_time_for_plat'];

        if($auto_download){
            //翻取已经在本地的新增记录加入下载商品任务列表
            $count = 0;
            $local_limit = 100;
            $this->getCommandByPline($supplier_id,$supplier_pline,$count,0,1);  //TODO:是否可优化？这里只需要取总数
            if($count > 0){
                $pages = ceil($count/$local_limit);
                //增加特殊记录，起始结束时间都为0，代表是翻取本地记录
                for($i=0;$i<$pages;$i++){
                    $this->_addDataSyncJob($last_sync_time,0,$i+1,$local_limit,$supplier_id,true,$supplier_pline,$to_cat_id);
                }
            }
        }
        
        $sync_list = $this->api->getApiData('getUpdateList',API_VERSION,array('pages'=>1,'counts'=>1,'supplier_id'=>$supplier_id,'last_sync_time'=>$last_sync_time),true,true);

        if(!empty($sync_list)){
            $sync_list_count = $sync_list[0]['row_count'];

            if($sync_list_count > 0){
                $pages = ceil($sync_list_count/$limit);

                for($i=0;$i<$pages;$i++){
                    $this->_addDataSyncJob($last_sync_time,$sync_list[0]['end_time'],$i+1,$limit,$supplier_id,$auto_download,$supplier_pline,$to_cat_id);
                }
                
                $rs = $this->db->query("SELECT * FROM sdb_supplier WHERE supplier_id=".floatval($supplier_id));
                $sql = $this->db->GetUpdateSQL($rs,array('sync_time_for_plat'=>$sync_list[0]['end_time']));
                $this->db->exec($sql);
                
            }
        }
    }
    
    function _addDataSyncJob($from_time,$to_time,$page,$limit,$supplier_id,$auto_download,$supplier_pline,$to_cat_id){
                    $job_info = array(
                        'from_time' => $from_time,
                        'to_time' => $to_time,
                        'page' => $page,
                        'limit' => $limit,
                        'supplier_id' => $supplier_id,
                        'auto_download' => $auto_download?'true':'false',
                        'supplier_pline' => empty($supplier_pline)?"":serialize($supplier_pline),
                        'to_cat_id' => $to_cat_id
                    );

                    $rs = $this->db->query("SELECT * FROM sdb_job_data_sync WHERE 0=1");
                    $sql = $this->db->GetInsertSQL($rs,$job_info);
                    $this->db->exec($sql);
                }

    /**
     * 增加下载商品的任务
     *
     * @param int $command_id
     * @param int $supplier_id
     * @param int $supplier_goods_id
     * @param int $goods_count，该供应商需要下载的商品总数
     * @param int $to_cat_id，需要下载到本地的分类id
     */
    function addGoodsDownloadJob($command_id,$supplier_id,$supplier_goods_id,$goods_count,$to_cat_id=NULL){
        $rs = $this->db->query("SELECT * FROM sdb_job_goods_download WHERE 0=1");
        $insert_info = array(
            'command_id' => $command_id,
            'supplier_id' => $supplier_id,
            'supplier_goods_id' => $supplier_goods_id,
            'supplier_goods_count' => $goods_count,
            'to_cat_id' => $to_cat_id
        );
        $sql = $this->db->GetInsertSQL($rs,$insert_info);
        $this->db->exec($sql);
    }


    /**
     * 执行更新列表任务
     *
     * @return int, 0:没有任务需要执行了，1:执行成功，继续执行
     */
    function doDataSyncJob(){
        $time = time();
        $job = $this->db->selectrow("SELECT * FROM sdb_job_data_sync ORDER BY job_id ASC");
        if(!empty($job)){
            $this->_updateLock('data_sync');

            if($job['auto_download'] == 'true'){
                $count = $this->getDownloadCount($job);
            }else{
                $count = 0;
            }
            
            if($job['to_time'] == 0 && $job['auto_download'] == 'true'){  //特殊情况：如果截止时间为0，那么代表不从远端取api数据，数据已经被下载在本地需要被翻出来执行
                $offset = ($job['page'] - 1) * $job['limit'];
                $command_ids = $this->getCommandByPline($job['supplier_id'],unserialize($job['supplier_pline']),$t_count,$offset,$job['limit']);
                foreach($command_ids as $v){
                    $this->addGoodsDownloadJob($v['command_id'],$job['supplier_id'],$v['object_id'],$count,$job['to_cat_id']);
                }
            }else{
            $auto_download = $job['auto_download'];
            $supplier_id = $job['supplier_id'];

            $params = array(
                'supplier_id' => $supplier_id,
                'last_sync_time' => $job['from_time'],
                'last_sync_time_end' => $job['to_time'],
                'pages' => $job['page'],
                'counts' => $job['limit']
            );

            $sync_list = $this->api->getApiData('getUpdateList',API_VERSION,$params,true,true);

            if(!empty($sync_list)){
                foreach($sync_list as $v){
                    $store = "";

                    $command_id = $v['command_id'];
                    $type = $v['cmd_action']==2?'product':'goods';
                    $object_id = $v['ob_id'];
                    $status = 'unoperated';
                    $command = $v['cmd_action'];
                    $command_info = array(
                        'thumbnail_pic'=>$v['thumbnail_pic'],
                        'name'=>$v['name'].($type=='product'?(empty($v['spec_value'])?'':'('.$v['spec_value'].')'):'')
                    );

                    $goods_id = $v['goods_id'];
                    $brand_id = $v['brand_id'];
                    $brand_name = addslashes($v['brand_name']); //TODO:放在底层做转义
                    $cat_id = $v['cat_id'];
                    $cat_name = addslashes($v['cat_name']); //TODO:放在底层做转义

                    $marketable = $v['marketable'];
                    $store = $v['store'];
                    $bn = $v['bn'];
                    $command_info[$type."_info"] = array(
                        'cat_id' => $cat_id,
                        'type_id' => $v['type_id'],
                        'brand_id' => $brand_id,
                        'brand' => $brand_name,
                        'bn' => $bn,
                        'marketable' => $marketable,
                        'store' => $store,
                    );
                    
                    $name = $v['name'] . ($type=='product'?(empty($v['spec_value'])?'':'('.$v['spec_value'].')'):'');
                    $name = addslashes($name);  //TODO:放在底层做转义
                    $last_modify = $v['cmd_lasttime'];
                    $command_type = $auto_download=='true'?'download':'sync';
                    $store = $store===""||is_null($store)?'NULL':$store;

                    $table_name = "sdb_data_sync_" . $job['supplier_id'];
                    $create_table = <<<EOF
                        CREATE TABLE IF NOT EXISTS `{$table_name}` (
                          `command_id` int(10) unsigned NOT NULL,
                          `type` enum('goods','product') NOT NULL,
                          `supplier_id` int(10) unsigned NOT NULL,
                          `object_id` mediumint(8) unsigned NOT NULL,
                          `status` enum('unoperated','unmodified','done') NOT NULL default 'unoperated',
                          `command` tinyint(3) unsigned NOT NULL,
                          `command_info` text,
                          `last_modify` int(10) unsigned NOT NULL,
                          `command_type` enum('download','sync') NOT NULL default 'download',
                          `img_down_failed` enum('true','false') NOT NULL default 'false',
                          `if_show` enum('true','false') NOT NULL default 'true',
                          `cat_id` int(11) NOT NULL,
                          `brand_id` int(11) NOT NULL,
                          `goods_id` mediumint(8) unsigned NOT NULL,
                          `brand_name` varchar(100),
                          `cat_name` varchar(100),
                          `name` varchar(255),
                          `bn` varchar(200),
                          `marketable` varchar(10) NULL,
                          `store` mediumint(8) unsigned NULL,
                          PRIMARY KEY  (`command_id`),
                          KEY `object_id` (`object_id`),
                          KEY `last_modify` (`last_modify`),
                          KEY `brand_name` (`brand_name`),
                          KEY `cat_name` (`cat_name`),
                          KEY `bn` (`bn`),
                          KEY `store` (`store`)
                        ) ENGINE=MyISAM DEFAULT CHARSET=utf8;
EOF;
                    $this->db->exec($create_table);


                    $flag = $this->_checkInPline($brand_id,$cat_id,$supplier_id);

                    if($flag){
                        //如果不在所选的产品线中，那么就不显示了，如果在的，还需要判断是否是新增的、本地是否已经有该商品，然后来控制显示以及状态
                        if($command == 6){
                            //check if the goods exists
                            $tmp_goods = $this->db->selectrow("SELECT * FROM sdb_goods WHERE supplier_id=".floatval($supplier_id)." AND supplier_goods_id=".intval($goods_id));
                            if(empty($tmp_goods)){
                                $show = 'true';
                            }else{
                                if($tmp_goods['disabled'] == 'true' || $tmp_goods['marketable'] == 'false'){
                                    //本地商品是回收站内的或者下架的，则提示新增，新增时自动出回收站和上架
                                    $show = 'true';
                                }else{
                                    $show = 'true';
                                    $tmp = $this->db->selectrow("SELECT * FROM ".$table_name." WHERE command_id=".$command_id);
                                    $status = $tmp['status'];
                                }
                            }
                        }else{
                            //如果不是新增，那么需要检查该商品在本地是否存在，如果不存在，更新列表的记录不被显示
                            switch ($type){
                                case 'goods':
                                    if(!$this->db->selectrow("SELECT goods_id FROM sdb_goods WHERE supplier_id=".floatval($supplier_id)." AND supplier_goods_id=".intval($goods_id))){
                                        $show = 'false';
                                    }else{
                                        $show = 'true';
                                    }

                                    if($command == 4){
                                        if($show == 'true'){
                                            //更新其他该goods的动作是否显示，防止原本不属于有权限产品线的商品变更到有权限产品线中去的情况下无法更新原有的记录显示状态
                                            $rs = $this->db->query("SELECT * FROM ".$table_name." WHERE if_show='false' AND goods_id=".intval($goods_id));
                                            $sql = $this->db->GetUpdateSQL($rs,array('last_modify'=>$last_modify,'if_show'=>'true'));
                                            if($sql){
                                                $this->db->exec($sql);
                                            }
                                        }else{  //如果本身这个动作不需要显示，那么至少把新增记录翻转状态显示出来
                                            $rs = $this->db->query("SELECT * FROM ".$table_name." WHERE if_show='false' AND command=6 AND goods_id=".intval($goods_id));
                                            $sql = $this->db->GetUpdateSQL($rs,array('last_modify'=>$last_modify,'if_show'=>'true'));
                                            if($sql){
                                                $this->db->exec($sql);
                                            }
                                        }
                                    }

                                    break;
                                case 'product':
                                    if(!$this->db->selectrow("SELECT goods_id FROM sdb_goods WHERE supplier_id=".floatval($supplier_id)." AND supplier_goods_id=".intval($goods_id))){
                                        $show = 'false';
                                    }else{
                                        $show = 'true';
                                    }
                                    break;
                                default:
                                    $show = 'false';
                                    break;
                            }
                        }
                    }else{
                        $show = 'false';

                        //如果变更过分类和品牌了，导致该商品从有权限的产品线切到了没权限的，那么要把其他相应的action动作记录都不显示
                        if($command == 4){
                            $rs = $this->db->query("SELECT * FROM ".$table_name." WHERE if_show='true' AND goods_id=".intval($goods_id));
                            $sql = $this->db->GetUpdateSQL($rs,array('if_show'=>'false'));
                            if($sql){
                                $this->db->exec($sql);
                            }
                        }
                    }

                    if($command == 4){
                        //如果变更过分类和品牌了(将本商品切换到另一产品线的情况)，那么更新其他action动作记录的cat_id和brand_id
                        if(!$this->db->selectrow("SELECT command_id FROM ".$table_name." WHERE command_id=".$command_id." AND cat_id=".$cat_id." AND brand_id=".$brand_id)){
                            $rs = $this->db->query("SELECT * FROM ".$table_name." WHERE goods_id=".intval($goods_id));
                            $sql = $this->db->GetUpdateSQL($rs,array('cat_id'=>$cat_id,'brand_id'=>$brand_id,'cat_name'=>$cat_name,'brand_name'=>$brand_name));
                            if($sql){
                                $this->db->exec($sql);
                            }
                        }
                    }
                    //TODO:放在底层做转义
                    $sql = "REPLACE INTO $table_name(`command_id`,`type`,`supplier_id`,`object_id`,`status`,`command`,`command_info`,`last_modify`,`command_type`,`if_show`,`cat_id`,`brand_id`,`goods_id`,`brand_name`,`cat_name`,`name`,`bn`,`marketable`,`store`) VALUES($command_id,'".$type."',$supplier_id,$object_id,'".$status."',$command,'".addslashes(serialize($command_info))."','".$last_modify."','".$command_type."','".$show."',".intval($cat_id).",".intval($brand_id).",".intval($goods_id).",'".$brand_name."','".$cat_name."','".$name."','".$bn."','".$marketable."',".$store.")";

                    $this->db->exec($sql);

                        if($auto_download != 'true' && $show == 'true'){
					// 加入到自动同步任务列表当中 $ 2009-09-04 18:51:36 wubin
                    $oAutoSync = $this->system->loadModel('distribution/autosync');
                    $oAutoSync->addAutoSyncTask($supplier_id,$command_id);
                        }

                    if($auto_download == 'true' && $type=='goods' && $command == 6){
                        $supplier_pline = unserialize($job['supplier_pline']);

                        $flag = $this->_checkInPline($brand_id,$cat_id,$supplier_id,$supplier_pline);

                        if($flag){
                                $this->addGoodsDownloadJob($command_id,$job['supplier_id'],$object_id,$count,$job['to_cat_id']);
                            }
                        }
                    }
                }
                
            }

            
            $this->db->exec("DELETE FROM sdb_job_data_sync WHERE job_id=".intval($job['job_id']));

            return 1;
        }else{
            $this->_updateLock('data_sync',false);
            return 0;
        }
    }

    /**
     * 做下载商品的任务
     *
     * @return mixed，如果正在下载时，返回json，包含supplier_id的current和count和cat_id
     *                无需下载了，那么返回0
     */
    function doGoodsDownloadJob(){
        $job = $this->db->selectrow("SELECT * FROM sdb_job_goods_download WHERE failed='false' ORDER BY job_id ASC");
        $log_file = HOME_DIR . "/logs/goodsdown.log";
        if(!empty($job)){
            $this->_updateLock('download_goods');
            $return = "";
            $supplier_id = $job['supplier_id'];
            $log_info = json_decode(file_get_contents($log_file),true);

            $datasync = $this->system->loadModel('distribution/datasync');
            $datasync->downloadGoods($job['command_id'],$supplier_id,$job['supplier_goods_id'],$job['to_cat_id']);

            //todo:判断下载成功与否

            if(!isset($log_info[$supplier_id])){
                $log_info[$supplier_id] = array(
                    'current' => 1,
                    'count' => $job['supplier_goods_count'],
                    'cat_id' => $job['to_cat_id']
                );
                file_put_contents($log_file,json_encode($log_info),LOCK_EX);
                $return = array(
                    'supplier_id' => $supplier_id,
                    'current' => 1,
                    'count' => $job['supplier_goods_count'],
                    'cat_id' => $job['to_cat_id']
                );
            }else{
                if($log_info[$supplier_id]['current'] + 1 < $log_info[$supplier_id]['count']){
                    $log_info[$supplier_id]['current'] += 1;
                    file_put_contents($log_file,json_encode($log_info),LOCK_EX);
                }else if(($log_info[$supplier_id]['current'] + 1) == $log_info[$supplier_id]['count']){
                    $log_info[$supplier_id]['current'] += 1;
                    unset($log_info[$supplier_id]);
                    file_put_contents($log_file,json_encode($log_info),LOCK_EX);
                }else{
                    unset($log_info[$supplier_id]);
                    file_put_contents($log_file,json_encode($log_info),LOCK_EX);
                }
                $return = array(
                    'supplier_id' => $supplier_id,
                    'current' => $log_info[$supplier_id]['current'],
                    'count' => $log_info[$supplier_id]['count'],
                    'cat_id' => $job['to_cat_id']
                );
            }

            $this->db->exec("DELETE FROM sdb_job_goods_download WHERE job_id=".intval($job['job_id']));

            $table = "sdb_data_sync_" . $supplier_id;
            $rs = $this->db->query("SELECT * FROM " . $table . " WHERE command_id=".intval($job['command_id']));
            $sql = $this->db->GetUpdateSQL($rs,array('status'=>'unmodified'));
            $this->db->exec($sql);

            return $return;
        }else{
            $this->_updateLock('download_goods',false);
            return 0;
        }
    }

    /**
     * 加入到图片下载任务列表
     *
     * @param string $type，类型 gimage|spec_value|udfimg|brand_logo
     * @param int $supplier_id，供应商id
     * @param int $object_id，gimage_id|spec_value_id|goods_id|brand_id
     * @param int $time，如果type是gimage，那么$time就是last_modify，默认是NULL
     *
     */
    function insertImageSyncList($command_id,$type,$supplier_id,$object_id,$time=NULL){
        $aData = array(
            'type' => $type,
            'supplier_id' => $supplier_id,
            'supplier_object_id' => $object_id,
            'add_time' => is_null($time)?time():$time,
            'command_id' => $command_id
        );
        $rs = $this->db->query("SELECT * FROM sdb_image_sync WHERE 0=1");
        $sql = $this->db->GetInsertSQL($rs,$aData);
        return $this->db->exec($sql);
    }

    /**
     * 下载图片
     * $retry=false && $command_id=1，表示重试下载该command_id所有需要重试下载的图片。注意点：如果反复调用带参数的方法，并且始终下载失败，那么会死锁，需要前台加以控制
     * PS：也可以由前台控制，首先把失败的标记改成成功，重新打开下载队列，这样就不需要传入参数了
     *
     * @param boolean $retry，是否重新下载失败的图片
     * @param int $command_id，同步列表的id
     * @return int -1：下载出错，1：下载成功，0：无需下载
     */
    function downloadImage($retry=false,$command_id=NULL){
        $image_type = array(
            '1' => 'gif',
            '2' => 'jpg',
            '3' => 'png',
            '6' => 'bmp'
        );
        $sql  = "SELECT * FROM sdb_image_sync WHERE 1=1 ";
        if($retry){
            $sql .= " AND failed='true'";
        }else{
            $sql .= " AND failed='false'";
        }
        if(!is_null($command_id)){
            $sql .= " AND command_id=".intval($command_id);
        }
        $sql .= " ORDER BY add_time ASC,img_sync_id ASC";
        $image = $this->db->selectrow($sql);
        if(!empty($image)){
            $this->_updateLock('download_image');

            $filename = "";
            $type = $image['type'];
            $supplier_id = $image['supplier_id'];
            $object_id = $image['supplier_object_id'];

            switch($type){
                case 'gimage':
                    $dir = HOME_DIR . "/upload/gpic";
                    if(!is_dir($dir)){
                        mkdir($dir,0777);
                    }
                    $dir = HOME_DIR . "/upload/gpic/" . date("Ymd");
                    if(!is_dir($dir)){
                        mkdir($dir,0777);
                    }
                    $filename = $dir . "/" . md5($supplier_id.$object_id);
                    $p_type = 'gimage';
                    break;
                case 'spec_value':
                    $filename = MEDIA_DIR . "/default/" . "spec-" . md5($supplier_id.$object_id);
                    $p_type = 'spec';
                    break;
                case 'udfimg':
                    $dir = MEDIA_DIR . "/goods/" . date("Ymd");
                    if(!is_dir($dir)){
                        mkdir($dir,0777);
                    }
                    $filename = $dir . "/" . md5($supplier_id.$object_id);
                    $p_type = 'udfimg';
                    break;
                case 'brand_logo':
                    $dir = MEDIA_DIR . "/brand";
                    if(!is_dir($dir)){
                        mkdir($dir,0777);
                    }
                    $dir .= "/" . date("Ymd");
                    if(!is_dir($dir)){
                        mkdir($dir,0777);
                    }
                    $filename = $dir . "/" .md5($supplier_id.$object_id);
                    $p_type = 'brand';
                    break;
            }

            $send_params = array(
                'supplier_id' => $supplier_id,
                'type' => $p_type,
                'id' => $object_id,
                'return_data' => 'raw'
            );

            $token = $this->system->getConf('certificate.token');
            $img_api = $this->system->api_call(IMAGESERVER,IMAGESERVER_HOST,IMAGESERVER_PATH,IMAGESERVER_PORT,$token);
            $file = $img_api->getApiData('getPicById',API_VERSION,$send_params);
            if($file === false){
                if(!is_null($image['command_id'])){
                    $table = 'sdb_data_sync_' . $supplier_id;
                    $rs = $this->db->query("SELECT * FROM sdb_image_sync WHERE img_sync_id=".intval($image['img_sync_id']));
                    $sql = $this->db->GetUpdateSQL($rs,array('failed'=>'true'));
                    $this->db->exec($sql);

                    $rs = $this->db->query("SELECT * FROM ".$table." WHERE command_id=".intval($image['command_id']));
                    $sql = $this->db->GetUpdateSQL($rs,array('img_down_failed'=>'true'));
                    $this->db->exec($sql);
                }
                return -1;
            }else{
                file_put_contents($filename,$file);
                list($img_width, $img_height, $img_type, $img_attr) = getimagesize($filename);
                $postfix = isset($image_type[$img_type])?$image_type[$img_type]:"jpg";
                rename($filename,$filename.".".$postfix);

                $sql = "DELETE FROM sdb_image_sync WHERE img_sync_id=".$image['img_sync_id'];
                $this->db->exec($sql);

                //下载完更新对应的gimage，spec_values的记录
                switch($type){
                    case 'gimage':
                        $image_path = "gpic/" . date("Ymd") . "/" . md5($supplier_id.$object_id) . "." . $postfix;

                        $supplier_gimage_id = $object_id;
                        $gimage_info = array(
                            'source' => $image_path,
                            'sync_time' => $image['add_time']
                        );
                        $rs = $this->db->query("SELECT * FROM sdb_gimages WHERE supplier_id=".floatval($supplier_id)." AND supplier_gimage_id=".intval($supplier_gimage_id));
                        $sql = $this->db->GetUpdateSQL($rs,addslashes_array($gimage_info));
                        $this->db->exec($sql);

                        //获取本地对应的goods信息
                        $goods = $this->db->selectrow("SELECT goods_id FROM sdb_gimages WHERE supplier_id=".floatval($supplier_id)." AND supplier_gimage_id=".intval($supplier_gimage_id)." ORDER BY goods_id DESC");
                        $local_goods_id = $goods['goods_id'];
                        $goods_info = $this->db->selectrow("SELECT goods_id,image_default,udfimg,spec_desc FROM sdb_goods WHERE goods_id=".intval($local_goods_id));

                        //如果该商品的所有图片都下载完了，则生成所有尺寸的图片
                        if($this->_checkGenAllImage($supplier_id,$local_goods_id)){
                            $gimage = &$this->system->loadModel('goods/gimage');
                            //$gimage->gen_all_size_by_goods_id($goods_info['goods_id'],$goods_info['image_default'],$goods_info['udfimg']=='true');
                            $gimage->gen_all_size_by_goods_id($goods_info['goods_id'],$goods_info['image_default'],false);

                            //更新商品的spec_desc中的spec_goods_images信息（规格关联图册的信息）
                            $goods_spec_desc = unserialize($goods_info['spec_desc']);

                            if(!empty($goods_spec_desc)){
                                foreach($goods_spec_desc as $k1=>$v1){
                                    if(!empty($v1)){
                                        foreach($v1 as $k2=>$v2){
                                            if(isset($v2['spec_goods_images']) && !empty($v2['spec_goods_images'])){
                                                $spec_goods_images = explode(",",$v2['spec_goods_images']);
                                                $tmp_spec_goods_images = array();
                                                foreach($spec_goods_images as $plat_gimage_id){
                                                    $tmp_gimage = $this->db->selectrow("SELECT gimage_id FROM sdb_gimages WHERE supplier_id=".floatval($supplier_id)." AND supplier_gimage_id=".intval($plat_gimage_id));
                                                    $tmp_spec_goods_images[] = $tmp_gimage['gimage_id'];
                                                }
                                                $goods_spec_desc[$k1][$k2]['spec_goods_images'] = implode(",",$tmp_spec_goods_images);
                                            }
                                        }
                                    }
                                }
                            }
                            $rs = $this->db->query("SELECT * FROM sdb_goods WHERE goods_id=".intval($local_goods_id));
                            $sql = $this->db->GetUpdateSQL($rs,array('spec_desc'=>serialize($goods_spec_desc)));
                            $this->db->exec($sql);

                            //将下载失败的那些图片重新置空
                            $goods_gimage_info = $this->db->select("SELECT * FROM sdb_gimages WHERE goods_id=".$local_goods_id);
                            foreach($goods_gimage_info as $goods_gimage){
                                if($this->db->selectrow("SELECT img_sync_id FROM sdb_image_sync WHERE type='gimage' AND supplier_id=".floatval($supplier_id)." AND supplier_object_id=".$goods_gimage['supplier_gimage_id']." AND failed='true'")){
                                    $rs = $this->db->query("SELECT * FROM sdb_gimages WHERE gimage_id=".$goods_gimage['gimage_id']);
                                    $sql = $this->db->GetUpdateSQL($rs,array('small'=>'','big'=>'','thumbnail'=>''));
                                    $this->db->exec($sql);

                                    if($goods_info['image_default'] == $goods_gimage['gimage_id']){
                                        $rs = $this->db->query("SELECT * FROM sdb_goods WHERE goods_id=".intval($local_goods_id));
                                        $sql = $this->db->GetUpdateSQL($rs,array('thumbnail_pic'=>'','small_pic'=>'','big_pic'=>''));
                                        $this->db->exec($sql);
                                    }
                                }
                            }
                        }
                        break;
                    case 'spec_value':
                        $image_path = "images/default/spec-" . md5($supplier_id.$object_id) . "." . $postfix;
                        $image_path = $image_path . "|" . "default/spec-" . md5($supplier_id.$object_id) . "." . $postfix . "|fs_storager";

                        $supplier_spec_value_id = $object_id;
                        $spec_value_info = array(
                            'spec_image' => $image_path
                        );

                        $rs = $this->db->query("SELECT * FROM sdb_spec_values WHERE supplier_id=".floatval($supplier_id)." AND supplier_spec_value_id=".intval($supplier_spec_value_id));
                        $sql = $this->db->GetUpdateSQL($rs,addslashes_array($spec_value_info));
                        $this->db->exec($sql);
                        break;
                    case 'udfimg':  //暂时无下载自定义商品图片的需求
                        $image_path = "images/goods/" . date("Ymd") . "/" . md5($supplier_id.$object_id) . "." . $postfix;
                        $image_path = $image_path . "|" . "/goods/" . date("Ymd") . "/" . md5($supplier_id.$object_id) . "." . $postfix . "|fs_storager";
                        $goods_thumbnail_pic = array('thumbnail_pic'=>$image_path);

                        $rs = $this->db->query("SELECT * FROM sdb_goods WHERE supplier_id=".floatval($supplier_id)." AND supplier_goods_id=".intval($object_id));
                        $sql = $this->db->GetUpdateSQL($rs,addslashes_array($goods_thumbnail_pic));
                        $this->db->exec($sql);
                        break;
                    case 'brand_logo':
                        $image_path = "images/brand/" . date("Ymd") . "/" . md5($supplier_id.$object_id) . "." . $postfix;
                        $image_path = $image_path . "|" . "/brand/" . date("Ymd") . "/" . md5($supplier_id.$object_id) . "." . $postfix . "|fs_storager";

                        $brand_logo_info = array('brand_logo'=>$image_path);
                        $rs = $this->db->query("SELECT * FROM sdb_brand WHERE supplier_id=".floatval($supplier_id)." AND supplier_brand_id=".intval($object_id));
                        $sql = $this->db->GetUpdateSQL($rs,addslashes_array($brand_logo_info));
                        $this->db->exec($sql);
                        break;
                }

                return 1;
            }
        }else{
            $this->_updateLock('download_image',false);
            return 0;
        }
    }

    /**
     * 由于某些api取数据返回结果过多，所以需要分任务分批取
     *
     * @param int $supplier_id
     * @param string $api_name  ，调用的api名
     * @param array $api_params ，调用该api所需要的参数，不包括pages和counts
     * @param string $api_version   ，api的版本
     * @param string $api_action    ，在那个model的方法中调用了添加api任务
     * @param int $limit    ，每次取数据的限制数
     */
    function addApiListJob($supplier_id,$api_name,$api_params,$api_version,$api_action,$limit=100){
        $data = $this->api->getApiData($api_name,$api_version,array_merge($api_params,array('pages'=>1,'counts'=>1)));
        if(!empty($data)){
            $count = $data[0]['row_count'];

            $pages = ceil($count/$limit);

            for($i=0;$i<$pages;$i++){
                $data = array(
                    'supplier_id' => $supplier_id,
                    'api_name' => $api_name,
                    'api_params' => serialize($api_params),
                    'api_version' => $api_version,
                    'api_action' => $api_action,
                    'page' => $i+1,
                    'limit' => $limit
                );

                $rs = $this->db->query("SELECT * FROM sdb_job_apilist WHERE 0=1");
                $sql = $this->db->GetInsertSQL($rs,$data);
                if($sql){
                    $this->db->exec($sql);
                }
            }
        }
    }

    /**
     * 执行apilist任务列表，取得api数据
     *
     * @param int $supplier_id
     * @param string $api_name
     * @param string $api_version
     * @param string $api_action
     *
     * @return array
     */
    function doApiListJob($supplier_id,$api_name,$api_version,$api_action){
        $api_info = $this->db->selectrow("SELECT * FROM sdb_job_apilist WHERE supplier_id=".floatval($supplier_id)." AND api_name='".$api_name."' AND api_version='".$api_version."' AND api_action='".$api_action."'");

        if(!empty($api_info)){
            $params = unserialize($api_info['api_params']);
            $params['pages'] = $api_info['page'];
            $params['counts'] = $api_info['limit'];
            $api_return = $this->api->getApiData($api_info['api_name'],$api_info['api_version'],$params);

            $this->db->exec("DELETE FROM sdb_job_apilist WHERE job_id=".$api_info['job_id']);

            return $api_return;
        }else{
            return array();
        }
    }

    /**
     * 检查是否要重新生成新的图片
     *
     * @param int $supplier_id
     * @param int $local_goods_id，本地的商品id
     * @return boolean
     */
    function _checkGenAllImage($supplier_id,$local_goods_id){
        $gimages = $this->db->select("SELECT supplier_gimage_id FROM sdb_gimages WHERE goods_id=".intval($local_goods_id)." AND is_remote='false' AND supplier_id=".floatval($supplier_id));

        if(!empty($gimages)){
            $supplier_gimage_ids = array();
            foreach($gimages as $v){
                $supplier_gimage_ids[] = $v['supplier_gimage_id'];
            }

            if(!$this->db->select("SELECT img_sync_id FROM sdb_image_sync WHERE type='gimage' AND failed='false' AND supplier_id=".floatval($supplier_id)." AND supplier_object_id IN (".implode(",",$supplier_gimage_ids).")")){
                //$supplier_gimage_ids是该供应商的某商品所对应的所有gimage_id列表，如果这些gimage_id在sdb_image_sync中不存在了，则说明已经把该商品的图片都下载到本地了，要开始生成所有尺寸的图片了
                return true;
            }else{
                return false;
            }
        }else{
            return false;
        }
    }

    /**
     * 更新任务锁
     *
     * @param string $job_name，要锁的队列名
     * @param boolean $lock，是加锁还是解锁
     */
    function _updateLock($job_name,$lock=true){
        $session_id = $this->system->session->sess_id;
        if(!is_dir(HOME_DIR . "/lock")){
            mkdir(HOME_DIR . "/lock",0777);
        }
        $lock_file = HOME_DIR . "/lock/job.lock";
        $time = time();

        if(!file_exists($lock_file)){
            touch($lock_file);
        }

        $tmp_lock = trim(file_get_contents($lock_file));
        if(!empty($tmp_lock)){
            $lock_info = unserialize($tmp_lock);
        }else{
            $lock_info = array();
        }

        $lock_info[$job_name] = array(
            'session_id' => $session_id,
            'lock_time' => $time,
            'if_lock' => $lock?"true":"false"
        );

        file_put_contents($lock_file,serialize($lock_info));
    }

    /**
     * 检查任务是否正被锁住
     * 如果锁时间过期了30秒，那么表示自动解锁
     *
     * @param string $job_name，检查是否被锁的队列名
     * @return boolean，true:被锁中，false:未上锁
     */
    function checkLock($job_name){
        if(!is_dir(HOME_DIR . "/lock")){
            mkdir(HOME_DIR . "/lock",0777);
        }
        $lock_file = HOME_DIR . "/lock/job.lock";
        $time = time();

        if(!file_exists($lock_file)){
            touch($lock_file);
        }

        $tmp_lock = trim(file_get_contents($lock_file));
        if(!empty($tmp_lock)){
            $lock_info = unserialize($tmp_lock);
            $lock_time = $lock_info[$job_name]['lock_time'];

            if($lock_info[$job_name]['if_lock'] == "true"){
                if($time >= $lock_time + 30){    //超30秒自动解锁
                    return false;
                }else{
                    return true;
                }
            }else{
                return false;
            }
        }else{
            return false;
        }
    }

    /**
     * 检查某分类和品牌的组合是否属于该供应商的产品线
     * 关于supplier_pline和supplier_id的说明：当supplier_pline为NULL时，代表需要查询该supplier_id的供应商所有有权限的产品线列表，然后和brand_id、cat_id的组合去匹配
     *                                       当supplier_pline有值时，用当前的brand_id、cat_id组合匹配
     *
     *
     * @param int $brand_id
     * @param int $cat_id
     * @param int $supplier_id
     * @param array $supplier_pline, array(
     *                                  $supplier_id => array(
     *                                      'brand_id' => xxx,
     *                                      'cat_id' => xxx,xxx,xxx
     *                                  ),
     *                                  $supplier_id => array(
     *                                      'brand_id' => xxx,
     *                                      'cat_id' => xxx,xxx,xxx
     *                                  ),
     *                              )
     * @return boolean
     */
    function _checkInPline($brand_id,$cat_id,$supplier_id,$supplier_pline=NULL){
        $flag = false;
        $pline_info = array();

        if(empty($supplier_pline)){
            if($tmp_data = $this->db->selectrow("SELECT supplier_pline FROM sdb_supplier WHERE supplier_id=".floatval($supplier_id))){
                $supplier_pline = unserialize($tmp_data['supplier_pline']);
            }else{
                $supplier_pline = $this->api->getApiData('getProductLineList',API_VERSION,array('id'=>$supplier_id),true,true);
                if(!empty($supplier_pline)){
                    foreach($supplier_pline as $k=>$v){
                        $supplier_pline[$k]['cat_id'] .= $v['child_cat_path']==""?"":",".$v['child_cat_path'];
                    }
                }
            }
        }

        if(!empty($supplier_pline) && is_array($supplier_pline)){
            foreach($supplier_pline as $k=>$pline){
                if($pline['cat_id'] == '-1' && $pline['brand_id'] == '-1'){
                    $flag = true;
                    break;
                }else if($pline['cat_id'] == '-1'){
                    if($pline['brand_id'] == $brand_id){
                        $flag = true;
                        break;
                    }else{
                        $flag = false;
                    }
                }else if($pline['brand_id'] == '-1'){
                    if(in_array($cat_id,explode(",",$pline['cat_id']))){
                        $flag = true;
                        break;
                    }else{
                        $flag = false;
                    }
                }else{
                    if($pline['brand_id'] == $brand_id && in_array($cat_id,explode(",",$pline['cat_id']))){
                        $flag = true;
                        break;
                    }else{
                        $flag = false;
                    }
                }
            }
        }else{
            $flag = false;
        }

        return $flag;
    }
    
    /**
     * 获取supplier_pline在更新列表中是否有command=6,status=unoperated,command_type=download的记录，并返回command_id
     * 
     * @param int $supplier_id
     * @param array $supplier_pline
     * @param int $count 总数
     * 
     * @return array array(
     *                  array('command_id'=>xxx,'goods_id'=>xxx),
     *                  ..................
     *              )
     *
     */
    function getCommandByPline($supplier_id,$supplier_pline,&$count,$offset=0,$limit=100){
        if(!empty($supplier_pline) && is_array($supplier_pline)){
            $sql = "SELECT command_id,object_id FROM sdb_data_sync_".$supplier_id." WHERE command=6 AND status='unoperated' AND command_type='download' ";
            $p_where = array();
            foreach($supplier_pline as $pline){
                if($pline['cat_id'] == "-1" && $pline['brand_id'] == "-1"){
                    //不需要任何条件
                }else if($pline['cat_id'] == "-1"){
                    $p_where[] = " brand_id=".intval($pline['brand_id']);
                }else if($pline['brand_id'] == "-1"){
                    $p_where[] = " cat_id IN (".$pline['cat_id'].")";
                }else{
                    $p_where[] = " cat_id IN(".$pline['cat_id'].") AND brand_id=".intval($pline['brand_id']);
                }
            }
            if($p_where){
                $sql .= " AND (".implode(" OR ",$p_where) . ")";
            }
            $count = $this->db->count($sql);
            $sql .= " ORDER BY command_id ";
            $sql .= " LIMIT ".$limit." OFFSET ".$offset;error_log($sql."\n",3,"e:/log.txt");
            return $this->db->select($sql);
        }else{
            return array();
        }
    }
    
    /**
     * 获取需要下载的总数
     *
     * @param array $data array(
     *                      'supplier_id' => xxx,
     *                      'from_time' => xxx,
     *                      'to_time' => xxx,
     *                      'page' => xxx,
     *                      'limit' => xxx,
     *                  );
     */
    function getDownloadCount($data){
        if(!isset($this->tmp_count)){
            $supplier_pline = unserialize($data['supplier_pline']);
            $count = 0;
            
            $this->getCommandByPline($data['supplier_id'],$supplier_pline,$count,0,1);  //更新了count
            
            foreach($supplier_pline as $k=>$pline){
                $pline_id[] = $k;
            }
            
            $supplier_id = $data['supplier_id'];
            
            if($data['to_time'] == 0){
                $supplier_info = $this->db->selectrow("SELECT * FROM sdb_supplier WHERE supplier_id=".floatval($supplier_id));
                $last_sync_time = $supplier_info['sync_time_for_plat'];
                
                $sync_list = $this->api->getApiData('getUpdateList',API_VERSION,array('pages'=>1,'counts'=>1,'supplier_id'=>$supplier_id,'last_sync_time'=>$data['from_time']),true,true);
                
                if(!empty($sync_list)){
                    $from_time = $data['from_time'];
                    $to_time = $last_sync_time; //时间在获取总数前已经被更新成最新了，所以这里作为结束时间
                }else{
                    $this->tmp_count = $count;
                    return $this->tmp_count;
                }
            }else{
                $from_time = $data['from_time'];
                $to_time = $data['to_time'];
            }
            
            $down_params = array(
                'supplier_id' => $supplier_id,
                'last_sync_time' => $from_time,
                'last_sync_time_end' => $to_time,
                'cmd_action' => 6,
                'pline' => $pline_id
            );
            $goods_count = $this->api->getApiData('getUpdateListCount',API_VERSION,$down_params,true,true);
            $count += $goods_count['row_count'];
    
            $this->tmp_count = $count;
        }
        
        return $this->tmp_count;
    }
}
?>
