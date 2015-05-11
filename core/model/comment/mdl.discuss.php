<?php
include_once(dirname(__FILE__).'/mdl.comment.php');
class mdl_discuss extends mdl_comment{
    var $idColumn = 'comment_id';
    var $textColumn = 'comment_id';
    var $appendCols = 'adm_read_status';
    var $adminCtl = 'goods/discuss';
    var $defaultCols = 'title,author,goods_id,time,adm_read_status,reply_name,lastreply,display,p_index';
    var $defaultOrder = array('comment_id','DESC');
    var $tableName = 'sdb_comments';

    function _filter($filter){
        $filter['object_type'] = 'discuss';
        $where[] = 'for_comment_id IS NULL';
        if(empty($filter['author']))
            unset($filter['author']);
        if(isset($filter['goods_name']) && $filter['goods_name'] !== ''){
            $aId = array(0);
            foreach($this->db->select('SELECT goods_id FROM sdb_goods WHERE name LIKE \'%'.addslashes($filter['goods_name']).'%\'') as $rows){
                $aId[] = $rows['goods_id'];
            }
            $where[] = 'goods_id IN ('.implode(',', $aId).')';
            unset($filter['goods_name']);
        }elseif(empty($filter['goods_name'])){
            unset($filter['goods_name']);
        }
        $where = implode(' AND ', $where);

        return parent::_filter($filter)." AND ".$where;
    }

    /**
     * is_highlight
     * 高亮finder的行
     *
     * @param mixed $row
     * @access public
     * @return void
     */
    function is_highlight($row){
        if($row['adm_read_status'] == 'false') return 1;
        else return 0;
    }

    function searchOptions(){
        $arr = parent::searchOptions();
        $arr['author']=__('评论人');
        return array_merge($arr,array(
                'goods_name'=>__('商品名称'),
        ));
    }

    function getColumns(){
        $now = parent::getColumns();
        $now['author']['label']=__("评论人");
        $now['time']['label']=__("评论时间");
        $now['ip']['label']=__("评论人IP");
        return $now;
    }
}
?>