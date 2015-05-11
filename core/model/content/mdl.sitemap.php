<?php
/**
 * mdl_page
 *
 * @uses modelFactory
 * @package
 * @version $Id$
 * @copyright 2003-2007 ShopEx
 * @author Wanglei <flaboy@zovatech.com>
 * @license Commercial
 */
class mdl_sitemap extends modelFactory{

    function update(){
        $items = $this->db->select('select * from sdb_sitemaps');
        /*
         * 调整为无定义时才会由xml导入
         * 但是以下程序仍然保留了依照xml进行update/delete
         */

        if(count($items)==0){
            $xml = &$this->system->loadModel('utility/xml');
            $map =  $xml->xml2arrayValues(file_get_contents(CORE_DIR.'/site.xml'));

            foreach($items as $k=>$item){
                $list[$item['node_type'].':'.$item['action'].':'.$item['item_id']] = &$items[$k];
            }
            $rows = array();
            $this->_mkRows($list,$map['site']['item'],0,0); // array(array('items')=>'')  必须是items外加数组

            $toRemove = array();
            foreach($list as $item){
                if($item['manual']=='1' || $item['keep']){
                    if($item['move']){
                        $sql = 'update sdb_sitemaps set p_node_id='.intval($item['move']).' where node_id='.intval($item['node_id']);
                        $this->db->exec($sql);
                    }
                }else{
                    $toRemove[$item['node_id']]=$item['p_node_id'];
                }
            }

            foreach($toRemove as $k=>$item){
                while($toRemove[$toRemove[$k]]){
                    $toRemove[$k] = $toRemove[$toRemove[$k]];
                }
            }

            if(count($toRemove)>0){
                $sql = 'delete from sdb_sitemaps where node_id in('.implode(',',array_keys($toRemove)).')';
                $this->db->exec($sql);
                foreach($toRemove as $k=>$v){
                    $this->db->exec('update sdb_sitemaps set p_node_id='.intval($v).' where p_node_id='.intval($k));
                }
            }
        }

        return $this->getList();

    }

    function getLinkNode(){
        $return=array();
        foreach($this->db->select('select * from sdb_sitemaps where node_type=\'page\' or node_type=\'articles\' order by p_order,path') as $row){
            $return[$row['node_type']][] = $row;
        }
        return $return;
    }
    function checkDel($nodid,&$string){
        $nodid=intval($nodid);

        $row=$this->db->selectrow('select node_type from sdb_sitemaps where node_id="'.$nodid.'"');

        if($row['node_type']=='articles'){
             if($this->db->selectrow('select * from sdb_articles where node_id='.$nodid)){
                    $string=__('该文档栏目下面还有文章，不能删除该栏目');
                    return false;
             }else{
                    return true;
             }
        }
        else if($this->db->selectrow('select node_id from sdb_sitemaps where p_node_id="'.$nodid.'"')){

            return false;

        }else{

            return true;

        }

    }
    function getParent($nodid){
        $rows = $this->db->select('select * from sdb_sitemaps where p_node_id='.$nodid);
        return $rows;
    }
    function getTitleByAction($action){
        return $this->db->selectrow('select title from sdb_sitemaps where action="'.$action.'"');
    }
    function getList($nodid=''){
        $rows=$this->getResult($nodid);

        foreach($rows as $k=>$row){
            $this->_apply($rows[$k]);
            if(0==$row['p_node_id']){
                $map[] = &$rows[$k];
            }
            $list[$row['node_id']] = &$rows[$k];
        }

        foreach($rows as $k=>$row){
            $list[$row['p_node_id']]['items'][] = &$rows[$k];
        }
        $this->_p_order($ret,$map);
        return $ret;
    }
    function getResult($nodid=''){
        if($nodid){
            $rows = $this->db->select('select * from sdb_sitemaps where node_id='.$nodid);

        }else{
            $rows = $this->db->select('select * from sdb_sitemaps order by p_order,path');
        }
        return $rows;
    }
    function getNowNod($nodid=''){
        if($nodid){
            $rows = $this->db->select('select * from sdb_sitemaps where node_id='.intval($nodid));
        }

        return $rows;
    }

    function _p_order(&$list,&$map){
        foreach($map as $k=>$item){
            unset($item['items']);
            $list[] = $item;
            if($map[$k]['items']){
                $this->_p_order($list,$map[$k]['items']);
            }
        }
    }

    function _mkRows(&$list,$items,$parent_node_id=null,$depth=null,$path=null){
        $duration=array(0=>100,1=>150);
        foreach($items as $i=>$item){
            $row = array('node_type' => $item['attr']['type'],'title'=>$item['attr']['title'],'hidden'=>($item['attr']['hidden']=='true'?'true':'false'),'p_node_id'=>$parent_node_id,'manual'=>0,'depth'=>$depth,'path'=>$path,'p_order'=>$i);

            if($item['attr']['node_id']){
                $row['node_id'] = $item['attr']['node_id'];
            }

            switch($item['attr']['type']){
            case 'page':
                $row['action'] = 'page:'.$item['attr']['id'];
                break;
            case 'goodsCat':
                $row['action'] = $item['attr']['filter'];
                $row['item_id'] = $item['attr']['id'];
                break;
            case 'articles':
                $row['action'] = 'artlist:index';
                $row['item_id'] = $item['attr']['node_id'];
                break;
            case 'action':
                $row['action'] = $item['attr']['ctl'].':'.$item['attr']['act'];
                break;
            case 'pageurl':
                $row['action'] = $item['attr']['ctl'];
                break;

            }

            $node = &$list[$row['node_type'].':'.$row['action'].':'.$row['item_id']];
            if(!$node){ //自己不在数据库
                $rs = $this->db->exec('select * from sdb_sitemaps where 0=1');
                $sql = $this->db->getInsertSql($rs,$row);
                $this->db->exec($sql);
                $row['node_id']=$this->db->lastInsertID();
                $node = $row;
            }else{
                if($parent_node_id != $node['p_node_id']){
                    $node['move'] = $parent_node_id;
                }
            }
            $node['keep'] = true;

            if($item['item'] && count($item['item'])>0){
                if($item['item']['attr']){
                    $this->_mkRows($list,array($item['item']),$node['node_id'],$node['depth']+1,$node['path'].$node['node_id'].',');
                }else{
                    $this->_mkRows($list,$item['item'],$node['node_id'],$node['depth']+1,$node['path'].$node['node_id'].',');
                }
            }
        }
    }


    function getDefineMap($nowId,$treenum,$treelistnum){

        if(!isset($this->__link_map)){
            $rows = $this->db->select('select * from sdb_sitemaps order by path,p_order');
            foreach($rows as $k=>$row){
                    $this->_apply($rows[$k]);
                    if(0==$row['p_node_id']){
                        $map[$row['node_id']]['label'] = $row['title'];
                        $map[$row['node_id']]['link'] = $rows[$k]['link'];
                        $map[$row['node_id']]['hidden'] = $rows[$k]['hidden'];
                        $map[$row['node_id']]['item_id'] = $rows[$k]['item_id'];
                        $map[$row['node_id']]['depth'] = 0;
                        $link[$row['node_id']]=&$map[$row['node_id']];
                    }else{
                        $link[$row['p_node_id']]['sub'][$row['node_id']]['label']=$row['title'];
                        $link[$row['p_node_id']]['sub'][$row['node_id']]['depth']=$row['depth'];
                        $link[$row['p_node_id']]['sub'][$row['node_id']]['hidden']=$row['hidden'];

                        $link[$row['p_node_id']]['sub'][$row['node_id']]['item_id']=$row['item_id'];

                        $link[$row['p_node_id']]['sub'][$row['node_id']]['link']=$rows[$k]['link'];
                        $link[$row['node_id']]=&$link[$row['p_node_id']]['sub'][$row['node_id']];
                    }
            }
            $this->__link_map = &$link;
        }
        if($treelistnum){
            $return=$this->__link_map[$treelistnum];
        }else{
            $return=$this->__link_map[$nowId[count($nowId)-1]['node_id']];
        }
        return $return;
    }


    function getMap($depth=-1,$root=0,$type=null){
        if(!$depth){
            $depth = -1;
        }
        $where = defined('IN_SHOP')?'hidden!="true" and':'';
        if($depth>0){
            $rows = $this->db->select('select * from sdb_sitemaps where '.$where.' depth<'.$depth.' order by p_order asc');
        }else{
            $rows = $this->db->select('select * from sdb_sitemaps where '.$where.' 1=1 order by path,p_order asc');
        }
        foreach($rows as $k=>$row){
            if($root==$row['p_node_id']){
                $ret[] = &$rows[$k];
            }
            $list[$row['node_id']] = &$rows[$k];
            $this->_apply($rows[$k],$depth-$row['depth']-1);
        }
        foreach($rows as $k=>$row){
            $list[$row['p_node_id']]['items'][] = &$rows[$k];
        }
        return $ret;
    }

    function &_mkFilter($filter){
            parse_str($filter,$filter);
            if($filter['type_id']){
                $filter['type_id']=array($filter['type_id']);
            }
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

    function _apply(&$item,$depth=-1){
        $pos = strpos($item['action'],':');
        switch($item['node_type']){
        case 'action':
            $item['link'] = $this->system->realUrl(substr($item['action'],0,$pos),substr($item['action'],$pos+1),$item['item_id']?array($item['item_id']):null,null,$this->system->base_url());
            break;

        case 'goodsCat':
            $searchtools = &$this->system->loadModel('goods/search');
            $filter=$this->_mkFilter($item['action']);
            $cat_id=implode($filter['cat_id'],',');
            $item['link'] = $this->system->realUrl('gallery',$this->system->getConf('gallery.default_view'),array($cat_id,$searchtools->encode($filter)),null,$this->system->base_url());
            break;

        case 'articles':
            $item['link'] = $this->system->realUrl(substr($item['action'],0,$pos),substr($item['action'],$pos+1),array($item['node_id']),null,$this->system->base_url());
            break;

        case 'page':
            $item['link'] = $this->system->realUrl('page',substr($item['action'],$pos+1),array(),null,$this->system->base_url());
            break;


        case 'pageurl':
            if($item['action']=='?'){
                $item['action'] = $this->system->realUrl('index');
            }
            $item['link'] = $item['action'];
            break;
        case 'custompage':
            $item['link']= $this->system->realUrl('custompage','index',array($item['node_id']));
        }


    }

    function getPathById($node_id,$showtime=true){

        return $this->_getPath(array('node_id'=>$node_id),null,$showtime);
    }

    function getPath($type,$info,$method='index'){
        if($type=='goods'){
            $path = $this->_getPath(array('node_type'=>'goodsCat'));
            $goods = &$this->system->loadModel('goods/products');
            $path = array_merge($path,$goods->getPath($info,$method));
        }elseif($type=='goodsCat'){
            $goods = &$this->system->loadModel('goods/productCat');
            $path = $this->_getPath(array('node_type'=>'goodsCat'),$method);
            if($info!=0){
                $path = array_merge($path,$goods->getPath($info,$method));
            }
        }elseif($type=='virtualcat'){
            $goods = &$this->system->loadModel('goods/virtualcat');
            $path = $this->_getPath(array('node_type'=>'virtualcat'),$method);
            if($info!=0){
                $path = array_merge($path,$goods->getPath($info,$method));
            }
        }
        elseif($type=='articles'){
            $article=&$this->system->loadModel('content/article');
            $result=$article->get($info['node_id']);
            if($result){
                if($result['node_id']){
                    $article_info=$this->getNowNod($result['node_id']);
                    $path[] = array('title'=>$article_info[0]['title'],'link'=>$this->system->mkUrl('artlist','index',array($result['node_id'])));
                }
            }else{
                $path=array();
            }
        }
        else{ //page action

            $path = $this->_getPath($info);

        }

        $return = &$path;
        if(count($return)==1 && $return[0]['link']==$this->system->request['base_url']){
            $return = array();
        }else{
            array_unshift($return,array('title'=>__('首页'),'link'=>$this->system->request['base_url']));
        }
        return $return;
    }

    function _getPath($info,$method=false,$showtime=true){

        foreach($info as $k=>$v){
            if($k=='item_id'){
                $item[] = 'item_id='.intval($v).' or item_id=null';
            }else{
                $item[] = $k.'="'.$v.'"';
            }
        }

        if(count($item)==1 && !is_array($item[0]) && $showtime){
            return array();
        }


        $nav = array();
        $row = $this->db->selectrow("select node_type,title,action,path,node_id,action from sdb_sitemaps where ".implode('and',$item));

        if($row['path']){
            $path = $this->db->select('select node_type,title,action,path,node_id,action from sdb_sitemaps where node_id in('.substr($row['path'],0,-1).') order by depth');
            $path[] = $row;
        }else{
            $path = array($row);
        }

        if($path){
            foreach($path as $k=>$p){
                switch($p['node_type']){

                case 'goodsCat':
                    $p['link'] = $this->system->mkUrl('gallery',$method?$method:$this->system->getConf('gallery.default_view'),array($p['item_id']));
                    break;

                case 'action':
//          $pos = str_
                    $p['link'] = $this->system->mkUrl($p['ctl'],$p['act'],array($p['item_id']));
                    break;

                default:
                    $pos = strpos($p['action'],':');
                    $p['link'] = $this->system->mkUrl(substr($p['action'],0,$pos),substr($p['action'],$pos+1));
//          $p['link'] = $pos;
                    break;
                }
                $nav[] = $p;
            }
        }

        return $nav;

    }
    function updateChildCount($node_id=false){
        if($node_id){
            $row = $this->db->selectrow('SELECT count(*) AS num FROM sdb_sitemaps WHERE p_node_id='.intval($node_id));
            $aData['child_count'] = $row['num'];
            $rs = $this->db->exec('SELECT * FROM sdb_sitemaps WHERE node_id='.intval($node_id));
            $sql = $this->db->getUpdateSQL($rs,$aData);
            if(!$sql || $this->db->exec($sql)){
                return true;
            }else{
                return false;
            }
        }
    }
    function newNode($data){
        $oTemplate = $this->system->loadModel('system/template');
        $data['manual'] = '1';
        if($data['p_node_id']){
            $rs =$this->db->exec('select * from sdb_sitemaps where node_id='.intval($data['p_node_id']));
            $result=$this->db->selectrow('select * from sdb_sitemaps where node_id='.intval($data['p_node_id']));
            $data['depth']=$result['depth']+1;
            $data['path'] = $result['path'].$data['p_node_id'].',';
        }else{
            $data['p_node_id'] = 0;
            $data['depth']=0;
            $rs = $this->db->exec('select * from sdb_sitemaps where 0=1');
        }
        $row = $this->db->selectrow('select max(p_order) as max_p_order from sdb_sitemaps where p_node_id='.intval($data['p_node_id']));
        $data['p_order'] = $row['max_p_order']+1;

        $sql = $this->db->getInsertSql($rs,$data);
        if($this->db->exec($sql)){
            $data['node_id'] = $this->db->lastInsertID();
            if($data['node_type'] == 'articles'){
                $data['node_type'] = 'artlist';
                $oTemplate->set_template($data['node_type'],$data['node_id'],$_POST['artlist_template'],'artlist');
            }
            if($data['node_type']=='page'){
                $oTemplate->set_template($data['node_type'],$data['node_id'],$_POST['singlepage_template'],'page');
            }
            $this->updateChildCount($data['p_node_id']);
            if($data['node_type']=='custompage'){
                $tmpl = &$this->system->loadModel('content/systmpl');
                $tmpl->updateContent(md5($data['node_id']),'[header][footer]');
            }
            return $data;
        }else{
            return false;
        }
    }



    function title2page($title){
        return str_replace('-','_',$title);
//    return substr(md5($title),0,6);
    }

    function save($node_id,$info){

        if($info['p_node_id']){
            $p_node = $this->db->selectrow('select * from sdb_sitemaps where node_id='.intval($info['p_node_id']));
        }else{
            $p_node = array('depth'=>-1);
        }

        if($rs = $this->db->exec('select * from sdb_sitemaps where node_id='.intval($node_id))){
            $row = $this->db->getRows($rs,1);
            $p_path = $p_node['node_id']>0?$p_node['path'].$p_node['node_id'].',':'';
            $aUpdate = array('title'=>$info['title'],'depth'=>$p_node['depth']+1,'path'=>$p_path,'hidden'=>$info['display']?'false':'true','p_node_id'=>$info['p_node_id'],'item_id'=>$info['item_id']?'1':'0');
            if($info['node_type'] == 'page'){
                $page_ident = $this->title2page($info['title']);
                $aUpdate['action'] = $info['node_type'].":".$page_ident;
            }
            $sql = $this->db->getUpdateSQL($rs,$aUpdate);

            if(!$sql){
                return true;
            }

            if(!$this->db->exec($sql)){
                return false;
            }
            if($info['p_node_id']!=$row[0]['p_node_id']){
                $this->updateChildCount($info['p_node_id']);
                $this->updateChildCount($row[0]['p_node_id']);
                $depthDiff = $p_node['depth']-$row[0]['depth']+1;
                $pathLength = strlen($row[0]['path'])+1;

                $depthDiff = $depthDiff+0;
                $sql = 'update sdb_sitemaps set depth=depth'.($depthDiff>=0?('+'.$depthDiff):$depthDiff).'
                    ,path = CONCAT(\''.$this->db->quote($p_path).'\',SUBSTRING(path FROM '.$pathLength.'))
                     where path like \''.$this->db->quote($row[0]['path'].$row[0]['node_id']).',%\'';

                $this->db->exec($sql);
            }
            if($info['node_type'] == 'page'){
                $old_page_ident = $this->title2page($info['old_title']);
                $rs = $this->db->exec('select * from sdb_pages where page_name=\''.$old_page_ident.'\'');
                $sql = $this->db->getUpdateSQL($rs,array('page_title'=>$page_ident,'page_name'=>$page_ident));
                if($sql)
                    $this->db->exec($sql);
                $rs = $this->db->exec('select * from sdb_widgets_set  where base_file=\''.$old_page_ident.'\'');
                $sql = $this->db->getUpdateSQL($rs,array('base_file'=>$page_ident));
                if($sql)
                    $this->db->exec($sql);
            }
            return true;
        }else{
            return false;
        }
    }

    function remove($node_id){
        $node_id=intval($node_id);
        if($row = $this->db->selectrow('select p_node_id,action,node_type,title from sdb_sitemaps where node_id='.intval($node_id))){
            $this->db->exec('update sdb_sitemaps set p_node_id='.intval($row['p_node_id']).' where p_node_id='.intval($node_id));
            if($row['node_type']=='page'){
                $page_ident = $this->title2page($row['title']);
                $this->db->exec('delete from sdb_pages where page_name="'.$page_ident.'"');
                $this->db->exec('delete from sdb_widgets_set where base_file="'.$page_ident.'"');
            }
            if($this->db->exec('delete from sdb_sitemaps where node_id ='.intval($node_id))){
                $this->updateChildCount($row['p_node_id']);
                return true;
            }else{
                return false;
            }
        }else{
            return true;
        }
    }

    function setTitle($node_id,$title){
        if($rs = $this->db->exec('select title from sdb_sitemaps where node_id='.intval($node_id))){
            $sql = $this->db->getUpdateSQL($rs,array('title'=>$title));
            return !$sql || $this->db->exec($sql);
        }else{
            return false;
        }
    }

    function setAction($node_id,$actions){

            if($rs = $this->db->exec('select action,item_id from sdb_sitemaps where node_id='.intval($node_id))){
                if(is_array($actions)){
                    if(empty($actions['item_id'])){
                        $actions['item_id']=0;
                    }

                    $sql = $this->db->getUpdateSQL($rs,array('action'=>$actions['action'],'item_id'=>$actions['item_id']));
                }else{
                    $sql = $this->db->getUpdateSQL($rs,array('action'=>$actions));
                }
                return !$sql || $this->db->exec($sql);
            }else{
                return false;
            }

    }

    function getNode($node_id){
        return $this->db->selectrow('select * from sdb_sitemaps where node_id=\''.intval($node_id).'\'');
    }

    function _walkActions(&$action,$map){
        foreach($map as $k=>$v){
            if($v['attr']['type']=='action'){
                $action[] = $v['attr'];
            }
            if($v['item']){
                $this->_walkActions($action,$v['item']);
            }
        }
    }

    function actions(){
        $xml = &$this->system->loadModel('utility/xml');
        $map =  $xml->xml2arrayValues(file_get_contents(CORE_DIR.'/site.xml'));
        foreach($items as $k=>$item){
            $list[$item['node_type'].':'.$item['action'].':'.$item['item_id']] = &$items[$k];
        }

        $this->_walkActions($actions,$map['site']['item']);
        return $actions;
    }

    /**
     * getActions
     * 用在，content/sitemaps:addNew
     *
     * @access public
     * @return void
     */
    function getActions(){
        foreach($this->db->select('select node_id,action from sdb_sitemaps') as $item){
            $actions[$item['action']] = $item['node_id'];
        }
        $ret = array();
        foreach($this->actions() as $a){
            if(!isset($actions[$a['ctl'].':'.$a['act']])){
                $ret[$a['ctl'].':'.$a['act']]=$a['title'];
            }
        }
        return $ret;
    }
    function geneList($array,$_key){
        $content="";
        $tmpdata = $this->db->selectrow("select max(d_order) as num_g from sdb_goods");
        for($i=0;$i<count($array);$i++){
            $content.="<url>";
            //$content.="<loc>/?product-".$array[$i]['goods_id'].".html</loc>";
            $content.="<loc>".$this->system->mkUrl($_key[1],'index',array($array[$i][$_key[0]]))."</loc>";
            if($_key[4]){
                $content.="<lastmod>".date("Y-m-d",$array[$i]['last_modify'])."</lastmod>";
            }else{
                $content.="<lastmod>".date("Y-m-d")."</lastmod>";
            }
            $content.="<changefreq>".$_key[3]."</changefreq>";
            $num = number_format($array[$i][$_key[2]]/$tmpdata['num_g'],1);
            if($num>1){
                $num = number_format(1,1);
            }
            $content.="<priority>".$num."</priority>";
            $content.="</url>";
        }
        return $content;
    }
    function generateCatalog($count){

        $devide=1000;
        for($i=0;$i<=ceil($count/$devide);$i++){
            $content.="<sitemap>";
            $content.="<loc>".$this->system->mkUrl('sitemaps','index',array($i),'xml')."</loc>";
            //$content.="<loc>/?sitemaps-".$i."</loc>";
            $content.="</sitemap>";
        }
        return $content;

    }
    function map(){


    }
    function updatePorder($pordAry){//更新排序
        if ($pordAry){
            foreach($pordAry as $key => $val){
                 $sqlString="Update sdb_sitemaps set p_order=".intval($val)." where node_id=".intval($key);
                 $this->db->exec($sqlString);
            }
            return true;
        }else{
            return false;
        }
    }

    function setVisibility($node_id,$status){
        $sql = 'update sdb_sitemaps set hidden=\''.($status?'false':'true').'\' where node_id='.intval($node_id);
        return $this->db->exec($sql);
    }
}
?>
