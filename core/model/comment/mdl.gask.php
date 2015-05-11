<?php
include_once(dirname(__FILE__).'/mdl.comment.php');
class mdl_gask extends mdl_comment{
    var $idColumn = 'comment_id';
    var $textColumn = 'comment_id';
    var $appendCols = 'adm_read_status';
    var $adminCtl = 'member/gask';
    var $defaultCols = 'p_index,title,goods_id,author,time,lastreply,adm_read_status,reply_name,display';
    var $defaultOrder = array('comment_id','DESC');
    var $tableName = 'sdb_comments';

    function _filter($filter){
        $filter['object_type'] = 'ask';
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



    function searchOptions(){
        $arr = parent::searchOptions();
        return array_merge($arr,array(
                'goods_name'=>__('商品名称'),
        ));
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

    function orderBy(){
        return array(
            array('label'=>__('默认排序'),'sql'=>'adm_read_status DESC'),
            array('label'=>__('按最后回复时间'),'sql'=>'lastreply DESC'),
            array('label'=>__('按发表评论时间'),'sql'=>'time DESC'),
            array('label'=>__('按商品首页优先'),'sql'=>'p_index DESC'),
        );
    }
}
?>
