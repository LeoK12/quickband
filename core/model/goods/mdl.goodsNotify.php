<?php
include_once('shopObject.php');
class mdl_goodsNotify extends shopObject{

    var $defaultCols = 'goods_id,member_id,email,send_time,creat_time,status,product_id';
    var $idColumn = 'gnotify_id'; //表示id的列
    var $adminCtl = 'goods/gnotify';
    var $textColumn = 'goods_id';
    var $defaultOrder = array('gnotify_id','DESC');
    var $tableName = 'sdb_gnotify';
    var $typeName = 'goods';

    function modifier_product_id(&$rows){
        foreach($rows as $k=>$v){
            $date = $this->db->selectrow("SELECT store FROM sdb_products  WHERE product_id = ".$v."");
            if($date['store'] === '0'){
                $rows[$k] = __('无货');
            }else{
                $rows[$k] = __('有货');
            }

        }
    }

    function searchOptions(){
        return array(
            'gname'=>__('商品名称'),
        );
    }

    //#add by leo
    function getNotifyByGId($nGid){
        return $this->db->select('SELECT gn.*,m.uname,m.name FROM sdb_gnotify gn LEFT JOIN sdb_members m ON gn.member_id=m.member_id WHERE gn.goods_id='.$nGid);
    }

    function _filter($filter){
        if($filter['gname'] || $filter['gbn']){
            if($filter['gname']) $gfilter['name'] = $filter['gname'];
            if($filter['gbn']) $gfilter['bn'] = $filter['gbn'];
            $oGoods = &$this->system->loadModel('goods/products');
            $filter['goods_id'][] = -1;
            foreach($oGoods->getList('goods_id',$gfilter, 0, 1000) as $rows){
                $filter['goods_id'][] = $rows['goods_id'];
            }
        }
        unset($filter['gname']);
        if($filter['notifytime']){
                $where = ' and creat_time > '.$filter['notifytime'];
        }

        return parent::_filter($filter).$where;
    }

    function getFieldById($nId){
        return $this->db->selectrow('SELECT * FROM sdb_gnotify WHERE gnotify_id='.$nId);
    }

    function getInfoById($nId){
        return $this->db->selectrow('SELECT gn.*, g.name AS goods_name, m.uname AS username FROM sdb_gnotify gn
                    LEFT JOIN sdb_members m ON gn.member_id = m.member_id
                    LEFT JOIN sdb_goods g ON g.goods_id = gn.goods_id
                    WHERE gnotify_id='.$nId);
    }

    function createNotify($aData){
        $aData['disabled'] = 'false';
        if($aData['member_id']){
            if($this->db->select("SELECT * FROM sdb_gnotify WHERE goods_id=".$aData['goods_id']." AND product_id=".$aData['product_id']." AND member_id=".$aData['member_id']." AND status='ready'")){
                $aRs = $this->db->exec('SELECT * FROM sdb_gnotify WHERE goods_id='.$aData['goods_id'].' AND product_id='.$aData['product_id'].' AND member_id='.$aData['member_id']." AND status='ready'");
                $sSql = $this->db->GetUpdateSQL($aRs,$aData,true);
            }else{
                $aRs = $this->db->exec('SELECT * FROM sdb_gnotify WHERE goods_id='.$aData['goods_id'].' AND product_id='.$aData['product_id'].' AND member_id='.$aData['member_id']);
                $sSql = $this->db->GetInsertSQL($aRs,$aData);
            }
        }else{
            $aData['member_id'] = NULL;
            $aRs = $this->db->exec('SELECT * FROM sdb_gnotify WHERE 0=1');
            $sSql = $this->db->GetInsertSQL($aRs,$aData);
        }
        if(!$sSql || $this->db->exec($sSql)){
            $this->updateGoodsNum($aData['goods_id']);
            $status = &$this->system->loadModel('system/status');
            $status->count_gnotify();
            return true;
        }else{
            return false;
        }
    }

    function updateGoodsNum($gid){
        $nGNotify = $this->db->selectrow('SELECT COUNT(gnotify_id) as notify_num FROM sdb_gnotify WHERE goods_id='.$gid);
        $num = intval($nGNotify['notify_num']);
        $aRs = $this->db->query('SELECT notify_num FROM sdb_goods WHERE goods_id='.$gid);
        $sSql = $this->db->GetUpdateSql($aRs,array('notify_num'=>$nGNotify['notify_num']));
        return (!$sSql || $this->db->exec($sSql));
    }

    function toNofity($aData){
        foreach($aData as $id){
            $aTmp = $this->getInfoById($id);
            $objMember = &$this->system->loadModel('member/member');
             $trust_uname = $objMember->trust_check($aTmp['username']);
             if($trust_uname){
                 $aTmp['username'] = $trust_uname;
             }
            $aTmp['product_url'] = $this->system->realUrl("product","index",array($aTmp['goods_id']));
            if($this->fireEvent('notify',$aTmp,$aTmp['member_id'])){
                $this->setNotifyStatus($aTmp['gnotify_id']);
                $sNum++;
            }else{
                $fNum++;
            }
        }
        $status = &$this->system->loadModel('system/status');
        $status->count_gnotify();
        return array('success'=>$sNum,'failed'=>$fNum);
    }

    function setNotifyStatus($id){
//        return $this->db->exec("DELETE FROM sdb_gnotify WHERE gnotify_id=".intval($id));
        $this->db->exec("UPDATE sdb_gnotify SET status = 'send', send_time=".time()." WHERE gnotify_id=".intval($id));
        $status = &$this->system->loadModel('system/status');
        $status->count_gnotify();
        return true;
    }

}