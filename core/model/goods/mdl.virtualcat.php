<?php
/**
 * mdl_category
 *
 * @uses modelFactory
 * @package
 * @version $Id: mdl.productCat.php 1994 2008-04-28 09:09:37Z ever $
 * @copyright 2003-2007 ShopEx
 * @author Wanglei <flaboy@zovatech.com>
 * @license Commercial
 */
include_once('shopObject.php');
class mdl_virtualcat extends shopObject{

    var $idColumn = 'virtual_cat_id';
    var $textColumn = 'virtual_cat_name';
    var $adminCtl = 'goods/virtualcat';
    var $defaultCols = 'virtual_cat_id,virtual_cat_name,filter,type_id';
    var $defaultOrder = array('p_order','desc');
    var $tableName = 'sdb_goods_virtual_cat';

    function checkTreeSize(){
        $aCount = $this->db->selectrow('SELECT count(*) AS rowNum FROM sdb_goods_virtual_cat');
        if($aCount['rowNum'] > 100){
            return false;
        }else{
            return true;
        }
    }
    function getCatParentById($id){
            $sqlString = 'SELECT virtual_cat_id as cat_id,filter,virtual_cat_name as cat_name FROM sdb_goods_virtual_cat WHERE parent_id ='.$id.' order by p_order,virtual_cat_id desc';
            $default_view=$this->system->getConf('gallery.default_view')?$this->system->getConf('gallery.default_view'):'index';

            $result=$this->db->select($sqlString);
            $oSearch = &$this->system->loadModel('goods/search');
            foreach($result as $cat_key=>$cat_value){
                $filter=$this->_mkFilter($cat_value['filter']);
                $result[$cat_key]['link']=$this->system->mkUrl('gallery',$default_view,array(implode(',',$filter['cat_id']),$oSearch->encode($filter),'0','','',$cat_value['cat_id']));
            }
            return $result;
    }

    function &_mkFilter($filter){
            parse_str($filter,$filter);
            if($filter['type_id']){

                $filter['type_id']=array($filter['type_id']);
            }
            $filter=$this->getFilter($filter);
            if(!is_array($filter['cat_id'])){
                $filter['cat_id']=array($filter['cat_id']);
            }
            if($filter['props']){
                foreach($filter['props'] as $k=>$v){
                    if($v!='_ANY_'){
                        $filter['p_'.$k]=$v;
                    }
                }
            }
            $filter['price'][0]=$filter['pricefrom']?$filter['pricefrom']:0;
            $filter['price'][1]=$filter['priceto'];
            $filter['name'][0]=$filter['searchname'];
            return $filter;
    }
    function getTreeList($pid=0, $listMark='all'){

        if($listMark == 'all'){
            //$oSearch = &$this->system->loadModel('goods/search');

            $aCat = $this->db->select('SELECT virtual_cat_name,virtual_cat_id, o.parent_id AS pid,o.p_order,o.cat_path,o.type_id,o.filter as type
                    FROM sdb_goods_virtual_cat o ORDER BY o.cat_path,o.p_order,o.virtual_cat_id');
             /*
            foreach($aCat as $k=>$v){
                $filters=$this->_mkFilter($aCat[$k]['filter']);
                $aCat[$k]['url']=$this->system->mkUrl('gallery',$this->system->getConf('gallery.default_view'),array(implode(",",$filters['cat_id']),$oSearch->encode($filters)));
            }*/
        }else{
            $oSearch = &$this->system->loadModel('goods/search');              if($pid === 0){
                  $sqlWhere = '(parent_id IS NULL OR parent_id='.intval($pid).')';
              }else{
                  $sqlWhere = 'parent_id='.intval($pid);
              }
            $aCat = $this->db->select('SELECT virtual_cat_name, virtual_cat_id, o.parent_id AS pid, o.p_order,o.filter, o.cat_path, t.name as type_name FROM sdb_goods_virtual_cat o
            LEFT JOIN sdb_goods_type t ON o.type_id = t.type_id
            WHERE '.$sqlWhere.' ORDER BY o.cat_path,o.p_order,o.virtual_cat_id');
            $default_view=$this->system->getConf('gallery.default_view')?$this->system->getConf('gallery.default_view'):'index';
            foreach($aCat as $k => $row){
                $aCat[$k]['pid'] = intval($aCat[$k]['pid']);
                if($row['cat_path'] == '' || $row['cat_path'] == ','){
                    $aCat[$k]['step'] = 1;
                }else{
                    $aCat[$k]['step'] = substr_count($row['cat_path'], ',') + 1;
                }
                $filters=$this->_mkFilter($aCat[$k]['filter']);
                $aCat[$k]['url']=$this->system->realUrl('gallery',$default_view,array(implode(",",$filters['cat_id']),$oSearch->encode($filters)),null,$this->system->base_url());
                //$aCat[$k]['url']=$this->system->mkUrl('gallery',$this->system->getConf('gallery.default_view'),array(implode(",",$filters['cat_id']),$oSearch->encode($filters)));
            }
        }
        return $aCat;
    }

    function getTree($count=true,$node=null){
        $where[]=' 1 ';
        if($count==false){
           $where[]='vCat.parent_id=0';
        }
        if($node){
           $where[]='(vCat.cat_path like \''.$node.',%\' or vCat.virtual_cat_id='.$node.') ';
        }
        return $this->db->select('SELECT vCat.virtual_cat_id,vCat.virtual_cat_name,vCat.filter,vCat.child_count,vCat.p_order,vCat.cat_path,vCat.parent_id,vCat.child_count,vType.name as type_name from sdb_goods_virtual_cat as vCat LEFT JOIN sdb_goods_type as vType ON vType.type_id=vCat.type_id where '.implode($where,' and ').' ORDER BY vCat.p_order,vCat.virtual_cat_id');

    }
    function getGoodsCatById($cat_id=0){
        $data = $this->db->select('SELECT dCat.cat_id,dCat.cat_path,dCat.parent_id,dCat.cat_name,dCat.child_count as isleaf from sdb_goods_cat as dCat where dCat.parent_id = '.intval($cat_id).' order by dCat.p_order');

        $data[] = array('cat_id' => 0,
                    'cat_path' => '',
                    'parent_id' => 0,
                    'cat_name' => '[未分类商品]',
                    'isleaf' => 0 );

        return $data;
    }
    function getVirtualCatById($cat_id=0){
        //return $this->db->select('SELECT dCat.virtual_cat_id,dCat.filter,dCat.parent_id,dCat.virtual_cat_name,count(cCat.parent_id) as isleaf from sdb_goods_virtual_cat as dCat Left Join sdb_goods_virtual_cat as cCat ON dCat.virtual_cat_id=cCat.parent_id where dCat.parent_id = '.intval($cat_id) .' Group by dCat.virtual_cat_id order by dCat.cat_path,dCat.p_order');
        return $this->db->select('SELECT dCat.virtual_cat_id,dCat.filter,dCat.parent_id,dCat.virtual_cat_name,child_count as isleaf from sdb_goods_virtual_cat as dCat where dCat.parent_id = '.intval($cat_id) .' order by dCat.cat_path,dCat.p_order');
    }
    function doImport($search=Array(),$virtual_cat_id=0,$copymode=false){
        $multi_search=array();
        foreach($search as $k=>$v){
            if($pos=strpos($v,'|close')){
                $search[$k]=substr($v,0,$pos);
                foreach($this->_getSonCatId($search[$k]) as $vK){
                    $multi_search[]=$vK['cat_id'];
                }
            }
        }
        $search=array_merge($search,$multi_search);
        if($virtual_cat_id==0){
            unset($virtual_cat_id);
        }
        $sql='select cat_id,parent_id,cat_path,p_order,cat_name,addon,type_id from sdb_goods_cat where cat_id in ('.implode(',', $search).') order by cat_path,cat_id';
        if($virtual_cat_id){
            $plus_path=$this->db->selectrow('select cat_path from sdb_goods_virtual_cat where virtual_cat_id= ' . intval($virtual_cat_id));
            if($plus_path['cat_path'] ==','){
                unset($plus_path);
            }
        }
        $result=$this->db->select($sql);
        $last_id=$this->db->selectrow('select virtual_cat_id from sdb_goods_virtual_cat order by virtual_cat_id desc');
        $last_id=$last_id['virtual_cat_id']+1;
        $path=array();
        foreach($result as $k=>$v){
            $path[$result[$k]['cat_id']]=$last_id;
            $result[$k]['virtual_cat_id']=$last_id;
            $result[$k]['virtual_cat_name']=$result[$k]['cat_name'];
            if($result[$k]['parent_id']){
                $_t_path=explode(",",$result[$k]['cat_path']);
                foreach($_t_path as $tK=>$vK){
                    if($path[$vK]){
                        $_t_path[$tK]=$path[$vK];
                    }else{
                        unset($_t_path[$tK]);
                    }
                }
                $result[$k]['cat_path']=$plus_path['cat_path'].($virtual_cat_id?($virtual_cat_id.','):'').implode(',',$_t_path).',';
                $result[$k]['parent_id']=end($_t_path)?end($_t_path):$virtual_cat_id;
            }else{

                $result[$k]['parent_id']=$virtual_cat_id;
                $result[$k]['cat_path']=$plus_path['cat_path'].$virtual_cat_id.',';
                //echo '<br>';
            }
            if($copymode){
                $result[$k]['filter']='cat_id='.$result[$k]['cat_id'];
            }
            $rs = $this->db->exec('SELECT * FROM sdb_goods_virtual_cat WHERE 0=1');
            $sql = $this->db->getInsertSQL($rs,$result[$k]);
            $last_id++;
            $this->db->exec($sql);
            $this->updateChildCount($result[$k]['parent_id']);
        }
        $this->virtualcat2json();
        return true;
    }
    function _getSonCatId($cat_id){
        $cat_path=$this->db->selectrow("select cat_path from sdb_goods_cat where cat_id =".intval($cat_id));
        if($cat_path['cat_path']==','){
            $cat_path['cat_path']=$cat_id.',';
        }else{
            $cat_path['cat_path']=$cat_path['cat_path'].$cat_id.',';
        }
        $result=$this->db->select('select cat_id from sdb_goods_cat where cat_path like "'.$cat_path['cat_path'].'%"');
        return $result;
    }
    function toRemove($catid){
        $aCats = $this->db->select('SELECT * FROM sdb_goods_virtual_cat WHERE parent_id = '.intval($catid));
        if(count($aCats) > 0){
            trigger_error(__('删除失败：本分类下面还有子分类'), E_USER_ERROR);
            return false;
        }

        $this->db->exec('DELETE FROM sdb_goods_virtual_cat WHERE virtual_cat_id='.intval($catid));
        $this->virtualcat2json();
        return true;
    }
    function get_virtualcat_depth(){
        $row = $this->db->selectrow('select cat_path from sdb_goods_virtual_cat order by cat_path desc');
        return count(explode(',',$row['cat_path']));
    }
    function updateOrder($p_order){
        foreach($p_order as $k=>$v){
            $this->db->exec('update sdb_goods_virtual_cat set p_order='.intval($v).' where virtual_cat_id='.intval($k));
        }
        $this->virtualcat2json();
        return true;
    }

    function updateChildCount($id){
        if(!$id){
            return false;
        }
        $row = $this->db->selectrow('SELECT count(*) AS num FROM sdb_goods_virtual_cat WHERE parent_id='.intval($id));
        if($row['num']){
            $aData['is_leaf'] = 'false';
        }else{
            $aData['is_leaf'] = 'true';
        }
        $aData['child_count'] = $row['num'];
        $rs = $this->db->exec('SELECT * FROM sdb_goods_virtual_cat WHERE virtual_cat_id='.intval($id));
        $sql = $this->db->getUpdateSQL($rs,$aData);
        if(!$sql || $this->db->exec($sql)){
            return true;
        }else{
            return false;
        }
    }

    function addNew($data){

        $oTemplate = $this->system->loadModel('system/template');
        parse_str($data['filter'],$filter);
        $data['type_id']=$filter['type_id'];
        $data['parent_id'] = intval($data['parent_id']);
        $data['addon']['meta']['title'] = htmlspecialchars($data['title']);
        $data['addon']['meta']['keywords'] = htmlspecialchars($data['keywords']);
        $data['addon']['meta']['description'] = htmlspecialchars($data['description']);
        $parent_id = $data['parent_id'];
        $path=array();

        while($parent_id){
            if($data['virtual_cat_id'] && $data['virtual_cat_id'] == $parent_id){
                return false;
                break;
            }
            array_unshift($path, $parent_id);
            $row = $this->db->selectrow('SELECT parent_id, cat_path, p_order FROM sdb_goods_virtual_cat WHERE virtual_cat_id='.intval($parent_id));
            $parent_id = $row['parent_id'];
        }
        $data['cat_path'] = implode(',',$path).',';
        if($data['virtual_cat_id']){
            if($data['type_id']=='_ANY_')
            $data['type_id'] = null;
            $sDefine=$this->db->selectrow('SELECT parent_id FROM sdb_goods_virtual_cat WHERE virtual_cat_id='.intval($data['virtual_cat_id']));
            $rs = $this->db->exec('SELECT * FROM sdb_goods_virtual_cat WHERE virtual_cat_id='.$data['virtual_cat_id']);
            $sql = $this->db->getUpdateSQL($rs,$data);
            if(!$sql || $this->db->exec($sql)){
                $virtual_cat_id=$data['virtual_cat_id'];
                $oTemplate->update_template('virtualcat',$virtual_cat_id,$data['virtualcat_template'],'gallery');
                if($sDefine['parent_id']!=$data['parent_id']){
                    $this->updatePath($data['virtual_cat_id'],$data['cat_path']);
                    $this->updateChildCount($sDefine['parent_id']);
                    $this->updateChildCount($data['parent_id']);
                }
                $this->virtualcat2json();
                return true;
            }else{
                return false;
            }
        }else{
            $rs = $this->db->exec('SELECT * FROM sdb_goods_virtual_cat WHERE 0=1');
            unset($data['virtual_cat_id']);
            $sql = $this->db->getInsertSQL($rs,$data);
            if(!$sql || $this->db->exec($sql)){
                $virtual_cat_id=$this->db->lastInsertId();
                $oTemplate->update_template('virtualcat',$virtual_cat_id,$data['virtualcat_template'],'gallery');
                $this->updateChildCount($data['parent_id']);
                $this->virtualcat2json();
                return true;
            }else{
                return false;
            }
        }
    }

   function updatePath($cat_id,$cat_path){
          $result = $this->db->select('SELECT virtual_cat_id,cat_path FROM sdb_goods_virtual_cat WHERE cat_path like \''.$cat_id.',%\' or parent_id='.$cat_id);
          foreach($result as $k=>$v){
            $path=$cat_path.substr($v['cat_path'],strpos($v['cat_path'],$cat_id.','),strlen($v['cat_path']));
            $this->db->exec('update sdb_goods_virtual_cat set cat_path="'.$this->db->quote($path).'" where virtual_cat_id='.intval($v['virtual_cat_id']));
          }
    }

    function get_virtualcat_list($show_stable=false){
        $file=MEDIA_DIR.'/goods_virtual_cat.data';
        if(($contents=file_get_contents($file))){
            if(($result=json_decode($contents,true))){
                if($show_stable){
                    foreach($result as $key=>$value){
                        if($result[$key]['step']>1){
                            $result[$key]['cat_name']='└'.$result[$key]['cat_name'];
                        }
                    }
                }

                return $result;
            }else{
                return $this->virtualcat2json(true);
            }
        }else{
            return $this->virtualcat2json(true);
        }
    }
    function virtualcat2json($return=false){
        $file=MEDIA_DIR.'/goods_virtual_cat.data';
        $contents=$this->getMapTree(0,'');
        if($return){
            file_put_contents($file,json_encode($contents));
            return $contents;
        }else{
            return file_put_contents($file,json_encode($contents));
        }
    }


    function getMapTree($ss=0, $str='└',$node=null){
        $retCat = $this->map($this->getTree(true,$node),$ss,$str,$cat,$step);
        //$retCat = $this->map($this->getTree($this->checkTreeSize(),$node),$ss,$str);
        global $step,$cat;
        $step = '';
        $cat = array();
        return $retCat;
    }

    function getPath($cat_id,$method=null){
        $method = $this->system->getConf('gallery.default_view');
        if(!$oSearch)$oSearch = &$this->system->loadModel('goods/search');
        $row = $this->db->selectrow('select cat_id,cat_path,virtual_cat_id,filter,virtual_cat_name as cat_name from sdb_goods_virtual_cat where virtual_cat_id='.intval($cat_id));
        $filters=$this->_mkFilter($row['filter']);
        $filters=$this->getFilter($filters);
        $ret = array(array('type'=>'virtualcat','title'=>$row['cat_name'],'link'=> $this->system->realUrl('gallery',$method,array(implode(",",$filters['cat_id']),$oSearch->encode($filters),0,'','',$row['virtual_cat_id']),null,$this->system->base_url())));

        if($row['cat_path'] != ',' && $row['cat_path']){
            foreach($this->db->select('select virtual_cat_name as cat_name,filter,virtual_cat_id,cat_id from sdb_goods_virtual_cat where virtual_cat_id in('.substr($row['cat_path'],0,-1).') order by cat_path desc') as $row){
                $filters=$this->_mkFilter($row['filter']);
                $filters=$this->getFilter($filters);

                array_unshift($ret,array('type'=>'goodsCat','title'=>$row['cat_name'],'link'=>$this->system->realUrl('gallery',$method,array(implode(",",$filters['cat_id']),$oSearch->encode($filters),0,'','',$row['virtual_cat_id']),null,$this->system->base_url())));
            }
        }
        return $ret;
    }

    function &getFilter($filter){
        $filter = array_merge(array('marketable'=>"true",'disabled'=>"false",'goods_type'=>"normal"),$filter);
        if($GLOBALS['runtime']['member_lv']){
            $filter['mlevel'] = $GLOBALS['runtime']['member_lv'];
        }
        return $filter;
    }
    function map($data,$sID=0,$preStr='',&$cat,&$step){
        $step++;
        $oSearch = &$this->system->loadModel('goods/search');
        if($preStr){
             for($i=1; $i<$step; $i++){
                $stepStr = str_repeat("&nbsp;",$step).$preStr;
              }
        }
        $base_url=$this->system->base_url();
        $default_view=$this->system->getConf('gallery.default_view');
        if($data){
            foreach($data as $i=>$value){
            $id=$data[$i]['virtual_cat_id'];
            $filter=$data[$i]['filter'];
                $cls=$data[$i]['child_count']?'true':'false';
            $filters=$this->_mkFilter($data[$i]['filter']);
            $filters=$this->getFilter($filters);
            if(!is_array($filters['cat_id'])){
                $filters['cat_id']=array($filters['cat_id']);
            }

                $link=$this->system->realUrl('gallery',$default_view,array(implode(",",$filters['cat_id']),$oSearch->encode($filters),0,'','',$id),'html',$base_url);

            /*
            $link=$this->system->mkUrl('gallery',$this->system->getConf('gallery.default_view'),array(implode(",",$filters['cat_id']),$oSearch->encode($filters)));
            */
            if(!$sID){ //第一轮圈套
                    if(empty($data[$i]['parent_id'])){ //原始节点
                    $cat[]=array(
                            'cat_name'=>$data[$i]['virtual_cat_name'],
                        'cat_id'=>$id,
                            'pid'=>$data[$i]['parent_id'],
                        'step'=>$step,
                        'filter'=>$filter,
                            'type_name'=>$data[$i]['type_name'],
                            'p_order'=>$data[$i]['p_order'],
                            'cat_path'=>$data[$i]['cat_path'],
                        'cls'=>$cls,
                        'url'=>$link
                    );
                        unset($data[$i]);
                        $this->map($data,$id,$preStr,$cat,$step);
                }else{ //
                    continue;
                }
            }else{ //子节点
                    if($sID==$data[$i]['parent_id']){
                    $cat[]=array(
                            'cat_name'=>$stepStr.$data[$i]['virtual_cat_name'],
                        'cat_id'=>$id,
                            'pid'=>$data[$i]['parent_id'],
                        'type'=>$type,
                            'type_name'=>$data[$i]['type_name'],
                        'step'=>$step,
                            'p_order'=>$data[$i]['p_order'],
                        'filter'=>$filter,
                            'cat_path'=>$data[$i]['cat_path'],
                        'cls'=>$cls,
                        'url'=>$link
                    );
                        unset($data[$i]);
                        $this->map($data,$id,$preStr,$cat,$step);
                    }
                }
            }
        }
        $step--;
        return $cat;
    }
}
?>