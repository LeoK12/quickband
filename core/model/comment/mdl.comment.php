<?php
include_once('shopObject.php');
class mdl_comment extends shopObject{
    function getFieldById($id, $aFeild){
        $sqlString = "SELECT ".implode(',', $aFeild)." FROM sdb_comments WHERE  disabled='false' and comment_id = ".intval($id);
        return $this->db->selectrow($sqlString);
    }

    //依id获取留言列表
    function getCommentById($comment_id){
        $aRet = $this->db->selectrow("SELECT c.*, g.name AS goods_name FROM sdb_comments c
                    LEFT JOIN sdb_goods g ON c.goods_id = g.goods_id
                    WHERE comment_id = ".intval($comment_id));
        return $aRet;
    }

    function getTopComment($limit){
        return $this->db->select('SELECT aGoods.name,aComment.comment_id,aComment.author,aComment.goods_id,aComment.comment,aComment.time FROM sdb_comments as aComment
        left join sdb_goods as aGoods on aGoods.goods_id=aComment.goods_id
        WHERE aComment.for_comment_id is Null and aComment.display="true"  and aComment.object_type = "discuss"  and aComment.disabled = "false" ORDER BY aComment.comment_id desc limit 0,'.intval($limit));
    }
    function getCommentReply($comment_id){
        return $this->db->select('SELECT * FROM sdb_comments WHERE for_comment_id = '.intval($comment_id).' and disabled="false" ORDER BY time');
    }

    function toRemove($comment_id){
        $row = $this->getCommentById($comment_id);
        $this->db->exec('DELETE FROM sdb_comments WHERE comment_id = '.intval($comment_id).' OR for_comment_id = '.intval($comment_id));
        $this->modelName = 'member/account';
        $data['member_id'] = $row['author_id'];
        if($row['object_type'] == "discuss"){
            $this->fireEvent('discuzz_del',$data,$row['author_id']);
        }
        if($row['object_type'] == "ask"){
            $this->fireEvent('advisory_del',$data,$row['author_id']);
        }
        return true;
    }

    function toDisplay($comment_id, $status){
        $this->db->exec('UPDATE sdb_comments SET display = '.$this->db->quote($status).' WHERE comment_id = '.intval($comment_id));
        return true;
    }

    function toReply($data){
        $this->toInsert($data);

        if($this->system->getConf('comment.display.'.$data['object_type']) == 'reply'){
            $aUpdate['display'] = 'true';
        }
        $aUpdate['comment_id'] = $data['for_comment_id'];
        $aUpdate['lastreply'] = $data['time'];
        $aUpdate['reply_name'] = $data['author'];
        $aUpdate['mem_read_status'] = 'false';
        $this->toUpdate($aUpdate);
        $this->db->exec('UPDATE sdb_comments SET adm_read_status=\'false\' WHERE comment_id = '.$data['for_comment_id']);
        $objGoods = &$this->system->loadModel('trading/goods');
        $objGoods->updateRank($data['goods_id'], 'comments_count', 1);    //评论次数统计
        $status = &$this->system->loadModel('system/status');
        $status->count_gdiscuss();
        $status->count_gask();
        $aTemp = $this->db->selectrow('SELECT * FROM sdb_comments WHERE comment_id='.$data['for_comment_id']);
        $data['member_id'] = $aTemp['author_id'];
        if ($data['object_type'] == "discuss")
            $type="discuzz_check";
        else
            $type="advisory_replay";
        $this->modelName="member/account";
        $this->fireEvent($type,$data,$data['member_id']);
        return true;
    }

    function toComment($data, $item, &$message){
        $this->toInsert($data);
        if($this->system->getConf('comment.display.'.$item) == 'soon'){
            $message = $this->system->getConf('comment.submit_display_notice.'.$item);
        }else{
            $message = $this->system->getConf('comment.submit_hidden_notice.'.$item);
        }
        $objGoods = &$this->system->loadModel('trading/goods');
        $objGoods->updateRank($data['goods_id'], 'comments_count', 1);    //评论次数统计

        $status = &$this->system->loadModel('system/status');
        $status->count_gdiscuss();
        $status->count_gask();
        $data['member_id'] = substr($_COOKIE['MEMBER'],0,strpos($_COOKIE['MEMBER'],"-"));
        if ($item=="ask")
            $type='advisory_new';
        elseif ($item=="discuss")
            $type='discuzz_new';
        $this->modelName="member/account";
        $this->fireEvent($type,$data,$data['member_id']);

        return true;
    }

    function toInsert(&$data){
        $data['title'] = $data['title'];
        $data['comment'] = safeHtml($data['comment']);
        $rs = $this->db->query('SELECT * FROM sdb_comments WHERE 0=1');
        $sql = $this->db->GetInsertSQL($rs, $data);
        return $this->db->exec($sql);
    }

    function toUpdate(&$data){
        if(!empty($data['comment'])){
            $data['comment'] = safeHtml($data['comment']);
        }
        $rs = $this->db->query('SELECT * FROM sdb_comments WHERE comment_id='.intval($data['comment_id']));
        $sql = $this->db->getUpdateSQL($rs, $data);
        return (!$sql || $this->db->exec($sql));
    }

    //设置管理员已读
    function setReaded($comment_id){
        if(is_array($comment_id)){
            $rs = $this->db->query('SELECT * FROM sdb_comments WHERE comment_id IN ('.implode(",",$comment_id).')');
        }
        else{
            $rs = $this->db->query('SELECT * FROM sdb_comments WHERE comment_id='.intval($comment_id));
        }
        $aUpdate['adm_read_status'] = 'true';
        $sql = $this->db->getUpdateSQL($rs, $aUpdate);
        if($sql){
            if($this->db->exec($sql)){
                $status = &$this->system->loadModel('system/status');
                $status->count_gdiscuss();
                $status->count_gask();
                return 1;
            }else{
                return false;
            }
        }else{
            return true;
        }
    }

    //设置首页优先排序
    function setIndexOrder($comment_id){
        $aRet = $this->getFieldById($comment_id, array('p_index'));

        if($aRet['p_index'] == 1){
            $aRet['p_index'] = 0;
        }else{
            $aRet['p_index'] = 1;
        }

        $rs = $this->db->query('SELECT * FROM sdb_comments WHERE comment_id='.intval($comment_id));
        $sql = $this->db->getUpdateSQL($rs, $aRet);

        return (!$sql || $this->db->exec($sql));
    }

    //读取商品首页评论列表
    function getGoodsIndexComments($goods_id, $item='ask'){
        $sql = "SELECT * FROM sdb_comments WHERE goods_id = ".intval($goods_id)
                ." AND for_comment_id IS NULL AND object_type = '".$item."' and disabled='false' AND display = 'true'";
        $aRet['total'] = $this->db->count($sql);
        $sql = "SELECT * FROM sdb_comments WHERE goods_id = ".intval($goods_id)
                ." AND for_comment_id IS NULL AND object_type = '".$item."' and disabled='false' AND display = 'true' ORDER BY p_index ASC, time DESC LIMIT ".$this->system->getConf('comment.index.listnum');
        $aRet['data'] = $this->db->select($sql);
        return $aRet;
    }

    //读取商品评论回复列表
    function getCommentsReply($aId, $display=false){
        if($display) $sql = ' AND display = \'true\'';
        return $this->db->select("SELECT * FROM sdb_comments WHERE for_comment_id IN (".implode(",", $aId).")".$sql." and disabled='false' ORDER BY time");
    }

    //读取前台评论列表
    function getGoodsCommentList($goods_id, $item='ask', $page=1){
        if($page < 1) $page = 1;
        $pagenum = $this->system->getConf('comment.list.listnum');
        $sql = "SELECT * FROM sdb_comments
            WHERE goods_id = ".intval($goods_id)." AND for_comment_id IS NULL AND object_type = '".$item
            ."' and disabled='false' AND display = 'true'";
        $aRet['total'] = $this->db->count($sql);

        $maxPage = ceil($aRet['total'] / $pagenum);
        if($page > $maxPage) $page = $maxPage;
        $start = ($page-1) * $pagenum;
        $start = $start<0 ? 0 : $start;
        $sql = "SELECT * FROM sdb_comments
            WHERE goods_id = ".intval($goods_id)." AND for_comment_id IS NULL AND object_type = '".$item
            ."' AND display = 'true' and disabled='false' ORDER BY time DESC LIMIT $start,".$pagenum;
        $aRet['page'] = $maxPage;
        $aRet['data'] = $this->db->select($sql);
        return $aRet;
    }

    //读取某会员评论列表
    function getMemberCommentList($member_id, $page=1){
        if($page < 1) $page = 1;
        $pagenum = $this->system->getConf('comment.list.listnum');
        $sql = "SELECT * FROM sdb_comments
            WHERE author_id = ".intval($member_id)." AND for_comment_id IS NULL AND display = 'true' and disabled='false' ORDER BY time DESC";
        $aRet['total'] = $this->db->count($sql);

        $maxPage = ceil($aRet['total'] / $pagenum);
        if($page > $maxPage) $page = $maxPage;
        $start = ($page-1) * $pagenum;
        $start = $start<0 ? 0 : $start;
        $sql = "SELECT * FROM sdb_comments
            WHERE author_id = ".intval($member_id)." AND for_comment_id IS NULL AND display = 'true' and disabled='false' ORDER BY time DESC LIMIT $start,".$pagenum;
        $aRet['page'] = $maxPage;
        $aRet['data'] = $this->db->select($sql);
        return $aRet;
    }

    function toValidate($item, $gid, $memInfo, &$message){
        if($this->system->getConf('comment.switch.'.$item) != 'on'){
            $this->system->error(404);
            return false;
            exit;
        }

        if($this->system->getConf('comment.power.'.$item) != 'null' && !isset($memInfo['member_id'])){
            $message = __('非会员不能发表!');
            return false;
            exit;
        }

        if($this->system->getConf('comment.power.'.$item) == 'buyer' && $memInfo['member_id']){
            $aRet = $this->db->selectrow('SELECT count(*) AS countRows FROM sdb_order_items i
                        LEFT JOIN sdb_orders o ON i.order_id = o.order_id
                        LEFT JOIN sdb_products p ON i.product_id = p.product_id
                        WHERE o.member_id='.intval($memInfo['member_id']).' AND p.goods_id='.intval($gid).' AND (o.pay_status>"0" OR o.ship_status>"0")');
            if($aRet['countRows'] == 0){
                $message = __('未购买过该商品不能发表!');
                return false;
                exit;
            }
        }
        return true;
    }

    function getSetting($item){
        $aOut['switch'][$item] = $this->system->getConf('comment.switch.'.$item);
        $aOut['display'][$item] = $this->system->getConf('comment.display.'.$item);
        $aOut['power'][$item] = $this->system->getConf('comment.power.'.$item);
        $aOut['null_notice'][$item] = $this->system->getConf('comment.null_notice.'.$item);
        $aOut['submit_display_notice'][$item] = $this->system->getConf('comment.submit_display_notice.'.$item);
        $aOut['submit_hidden_notice'][$item] = $this->system->getConf('comment.submit_hidden_notice.'.$item);

        $aOut['index'] = intval($this->system->getConf('comment.index.listnum'));
        $aOut['list'] = intval($this->system->getConf('comment.list.listnum'));
        $aOut['verifyCode'][$item] = $this->system->getConf('comment.verifyCode.'.$item);
        return $aOut;
    }

    function setSetting($item, $aData){
        $this->system->setConf('comment.switch.'.$item, $aData['switch'][$item]);
        $this->system->setConf('comment.display.'.$item, $aData['display'][$item]);
        $this->system->setConf('comment.power.'.$item, $aData['power'][$item]);
        $this->system->setConf('comment.null_notice.'.$item, $aData['null_notice'][$item]);
        $this->system->setConf('comment.submit_display_notice.'.$item, $aData['submit_display_notice'][$item]);
        $this->system->setConf('comment.submit_hidden_notice.'.$item, $aData['submit_hidden_notice'][$item]);

        $this->system->setConf('comment.index.listnum', $aData['indexnum']);
        $this->system->setConf('comment.list.listnum', $aData['listnum']);
        $this->system->setConf('comment.verifyCode.'.$item, $aData['verifyCode'][$item]);
    }

    //会员所有的留言数
    function getTotalNum($nMId, $item=''){
        if($item) $sql = ' AND object_type=\''.$item.'\'';
        $aRow = $this->db->selectrow('SELECT count(*) AS num FROM sdb_comments WHERE  disabled="false" and author_id='.$nMId.$sql);
        return $aRow['num'];
    }

    //依会员id获取留言列表
    function getCommListByMemId($nMId,$item){
        if($item) $sql = ' AND object_type=\''.$item.'\'';
        return $this->db->select("SELECT comment_id,author,contact,title,comment,time FROM sdb_comments WHERE author_id=".$nMId.$sql." ORDER BY time DESC");
    }

}
?>