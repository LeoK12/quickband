<?php
/**
 * shopObject
 * 网店对象基类
 *
 * @uses modelFactory
 * @package
 * @version $Id$
 * @copyright 2003-2007 ShopEx
 * @author Wanglei <flaboy@zovatech.com>
 * @license Commercial
 */
class shopObject extends modelFactory{

    var $disabledMark = 'normal';
    var $typeName = null;
    var $use_recycle = true;

    function shopObject(){
        parent::modelFactory();
        if(!$this->typeName) $this->typeName = substr(strstr(get_class($this),'_'),1);
    }

    function events(){}

    function getFilter($params){

        $errorlevels = array(
            2048 => 'Notice',
            1024 => 'Notice',
            512 => 'Warning',
            256 => 'Error',
            128 => 'Warning',
            64 => 'Error',
            32 => 'Warning',
            16 => 'Error',
            8 => 'Notice',
            4 => 'Error',
            2 => 'Warning',
            1 => 'Error');

        $this->_err[] = array('code'=>$errno, 'string'=>$errstr, 'file'=>$errfile, 'line'=>$errline,'codeinfo'=>$errorlevels[$errno]);

        if(isset($this->system->ErrorSet['errorno']) && isset($this->_errAction[$this->system->ErrorSet['errorno']])){
            $this->splash('failed',$this->_errAction[$this->system->ErrorSet['errorno']],$errstr);

        }else{
            switch($errno){
            case $errno & ( E_NOTICE | E_USER_NOTICE | E_WARNING):
                break;

            case $errno & ( $this->_shutHandle ):
                restore_error_handler();
                $this->splash('failed',$this->_action_url,'&nbsp;'.$errstr,$this->_err);

            /*default:
                restore_error_handler();
                $this->splash('failed',$this->_action_url,$errstr,$this->_err);*/
            }
        }
        return true;
    }
    function &_columns(){
        $schema = &$this->system->loadModel('utility/schemas');
        $table = substr($this->tableName,4);
        if(file_exists(CORE_DIR.'/schemas/'.$table.'.php')){
            $define = require(CORE_DIR.'/schemas/'.$table.'.php');
            $this->__table_define = &$db[$table]['columns'];
        }
        return $this->__table_define;
    }

    function getColumns(){
        $columns = array();

        foreach($this->_columns() as $k=>$v){
            if(isset($v['label'])){
                $columns[$k] = $v;
            }
        }
        return $columns;
    }

    function searchOptions(){
        $columns = array();
        foreach($this->_columns() as $k=>$v){
            if(isset($v['searchtype']) && $v['searchtype']){
                $columns[$k] = $v['label'];
            }
        }
        return $columns;
    }

    function columnValue($column,$value){
        if(!function_exists('object_column_value')) require(CORE_INCLUDE_DIR.'/core/object.column_value.php');
        return object_column_value($column,$value,$this);
    }

    function finderResult($data,$start=0,$limit=null){
        if($data['filter']){
            parse_str($data['filter'],$data);
            $finder = $data['_finder'];
            unset($data['_finder']);
            $return=array();
            foreach($this->getList($this->idColumn,$data,$start,$limit) as $row){
                $return[] = $row[$this->idColumn];
            }
            return $return;
        }else{
            return $data['items'];
        }
    }

    /**
     * fireEvent 触发事件
     *
     * @param mixed $event
     * @access public
     * @return void
     */
    function fireEvent($action , &$object, $member_id=0){
         $trigger = &$this->system->loadModel('system/trigger');
         return $trigger->object_fire_event($action,$object, $member_id,$this);
    }

    /**
     * addTag
     *
     * @param mixed $mix 支持三种参数 id,id的集合,filter
     * @param mixed $tagName
     * @access public
     * @return void
     */
    function addTag($mix,$tag_id){
        $type = '';
        $modTag = &$this->system->loadModel('system/tag');
        if(is_array($mix)){
            if($mix['items']){
                $modTag->begin();
                foreach($mix['items'] as $id){
                    $modTag->addTag($tag_id,$id,$type);
                }
                $modTag->end();
            }elseif($mix['filter']){
                parse_str($mix['filter'],$filter);
            }
        }else{
            $modTag->addTag($tag_id,$mix,$type);
        }
    }

    function newTag($tagName){
        $modTag = &$this->system->loadModel('system/tag');
        return $modTag->newTag($tagName,$this->typeName);
    }

    function setTag($data,$tags){
        $set_tags=array();
        foreach($tags as $key=>$value){
            $type=substr($value,0,6);
            if($type==='_S_ALL'){
                $set_tags[]=substr($value,6);
            }else if($type==='_S_PAR'){
                $part[]=substr($value,6);
            }
        }
        $a = array();
        if(!empty($data)){
            foreach($this->db->select("select {$this->tableName}.{$this->idColumn} as rel_id from {$this->tableName} where ".$this->_filter($data,$this->tableName)) as $r){
                $a[] = $r['rel_id'];
            }
        }
        $tag_id=array();
        if(!empty($a)){
            foreach($this->db->select("SELECT DISTINCT(r.tag_id) FROM sdb_tag_rel r LEFT JOIN sdb_tags t ON r.tag_id = t.tag_id
                where tag_type='".$this->typeName."' AND t.tag_name NOT IN ('".implode('\',\'',$part)."') AND rel_id IN(".implode(',',$a).")") as $rows){
                    $tag_id[] = $rows['tag_id'];
            }
        }

        if(count($tag_id) > 0){
            $this->db->exec('delete from sdb_tag_rel where tag_id in('.implode(',',$tag_id).') and rel_id in('.implode(',',$a).')');
        }else{
            if(!$part){
                $this->db->exec('delete from sdb_tag_rel where rel_id in('.implode(',',$a).')');
            }
        }

        $modTag = &$this->system->loadModel('system/tag');
        foreach($set_tags as $tag){
            $tagId = $modTag->tagId($tag,$this->typeName);
            $tag_id[] =  $tagId;
            if(constant('DB_OLDVERSION')){
                foreach($this->db->select("select {$tagId} as tag_id,{$this->tableName}.{$this->idColumn} as rel_id from {$this->tableName} where ".$this->_filter($data,$this->tableName)) as $r){
                    if(!$this->db->exec("insert into sdb_tag_rel (tag_id,rel_id) values({$r['tag_id']},{$r['rel_id']})")){
                        return false;
                    }
                }
            }else{
                $sql = "insert into sdb_tag_rel (tag_id,rel_id) select {$tagId} as tag_id,{$this->tableName}.{$this->idColumn} as rel_id from {$this->tableName} where ".$this->_filter($data,$this->tableName);
                if(!$this->db->exec($sql)){
                    return false;
                }
            }
        }

        $modTag->recount(array_unique($tag_id));
        return true;
    }

    function getTagList($tag){
        $modTag = &$this->system->loadModel('system/tag');
        if(($result=$modTag->getTagList($this->typeName,$tag))){
            return $result;
        }
        return false;
    }

    function &tagList($count=false,$data){
        $modTag = &$this->system->loadModel('system/tag');
        return $modTag->tagList($this->typeName,$count,$this->tableName,$this->idColumn,$data);
    }

    //回收站
    function recycle($filter){
        $sql = 'update '.$this->tableName.' set disabled=\'true\' where '.$this->_filter($filter);
        return $this->db->exec($sql);
    }

    //从回收站移回
    function active($filter){
        $this->disabledMark = 'recycle';
        $sql = 'update '.$this->tableName.' set disabled=\'false\' where '.$this->_filter($filter);
        return $this->db->exec($sql);
    }

    /**
     * getList
     *
     * @param mixed $cols 列
     * @param mixed $filter 过滤数组
     * @param  $disabled 删除状态  normal|recycle|all
     * @access public
     * @return void
     *
     */
    function getList($cols,$filter='',$start=0,$limit=20,$orderType=null){
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
            $this->_dbstorage[$ident]=$this->db->selectLimit($sql,$limit,$start);
        }
        return $this->_dbstorage[$ident];
    }

    function count($filter=null){
        $row = $this->db->select('SELECT count(*) as _count FROM '.$this->tableName.' WHERE '.$this->_filter($filter));
        return intval($row[0]['_count']);
    }

   function instance( $id, $cols = "*" )
   {
       if($id!=""){
           $rows = $this->getList($cols, array($this->idColumn => $id), 0, 1 );
           return $rows[0];
       }else{
                return "";
       }
   }

    function wFilter($words,$colum){
        $replace = array(",", "+");
        $return=str_replace($replace,' ',$words);
        $word=explode(" ",$return);

        foreach($word as $k=>$v){
            foreach($colum as $k=>$v){
                $sSql[]=$colum[$k].' LIKE \'%'.$word[$k].'%\'';
            }
            $sql[]='('.implode('or',$sSql).')';
            //$sql[]='($this->textColumn LIKE \'%'.$word[$k].'%\' or bn LIKE \'%'.$word[$k].'%\')';
        }
        return implode('and',$sql);
    }

    function _filter($filter,$tableAlias=null,$baseWhere=null){
        if(!function_exists('object_filter_parser')) require(CORE_INCLUDE_DIR.'/core/object.filter_parser.php');
        return object_filter_parser($filter,$tableAlias,$baseWhere,$this);
    }

    function insert($data){
        if(!function_exists('object_insert')) require(CORE_INCLUDE_DIR.'/core/object.insert.php');
        return object_insert($data,$this);
    }

    function update($data,$filter){
        if(!function_exists('object_update')) require(CORE_INCLUDE_DIR.'/core/object.update.php');
        return object_update($data,$filter,$this);
    }

    function delete($filter){
        if(!function_exists('object_delete')) require(CORE_INCLUDE_DIR.'/core/object.delete.php');
        return object_delete($filter,$this);
    }

    function enable($filter){
        return $this->db->exec('delete from '.$this->tableName.' where '.$this->_filter($filter));
    }

    function disable($filter){
        return $this->db->exec('delete from '.$this->tableName.' where '.$this->_filter($filter));
    }

    function export($list){
        if(!function_exists('object_export')) require(CORE_INCLUDE_DIR.'/core/object.export.php');
        return object_export($list,$this);
    }
}
?>
