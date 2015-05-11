<?php
/**
 * mdl_advance
 *
 * @uses modelFactory
 * @package
 * @version $Id: mdl.advance.php 1867 2008-04-23 04:00:24Z flaboy $
 * @copyright 2003-2007 ShopEx
 * @author Wanglei <flaboy@zovatech.com>
 * @license Commercial
 */
 include_once('shopObject.php');
class mdl_advance extends shopObject{

    var $idColumn = 'log_id'; //表示id的列
    var $adminCtl = 'member/advance';
    var $textColumn = 'memo';
    var $defaultCols = 'member_id,mtime,memo,import_money,explode_money,member_advance,paymethod,message';
    var $defaultOrder = array('log_id','DESC');
    var $orderAble = false;
    var $tableName = 'sdb_advance_logs';

    function _filter($filter){
        $sdtime = '';
        if($filter['sdtime'])
            $sdtime = explode("/",$filter['sdtime']);
        else
            $sdtime = explode("/",$filter['sdtimecommon']);
        if(count($sdtime) == 1)
            $sdtime = explode('%2F', $sdtime[0]);
        $where = array(1);
        $filter['start_date'] = strtotime($sdtime[0]);
        $filter['end_date'] = strtotime($sdtime[1]);
        if($filter['start_date'])
            $where[] = " mtime >= ".$filter['start_date'];
        if($filter['end_date'])
            $where[] = " mtime <= ".($filter['end_date']+3600*24);
        unset($filter['sdtime'],$filter['sdtimecommon'],$sdtime);
        return parent::_filter($filter).' AND '.implode($where,' AND ');
    }

    /**
     * toLog 增加预存款记录
     *
     * @param mixed $data
     * @access public
     * @return void
     */
    function toLog($data){
        if($rs = $this->db->query('SELECT advance,member_id FROM sdb_members WHERE member_id='.intval($data['member_id']))){
            $sqlString = $this->db->GetUpdateSQL($rs, $data);
            if(!$sqlString || $this->db->exec($sqlString)){
                return true;
            }else{
                return false;
            }
        }else{
            return false;
        }
    }

    function checkAccount($member_id,$money=0,&$errMsg,&$rows){
        if($rs = $this->db->exec('SELECT advance,member_id FROM sdb_members WHERE member_id='.intval($member_id))){
            $rows = $this->db->getRows($rs,1);
            if(count($rows)>0){
                if($money>$rows[0]['advance']){
                    $errMsg .= __('预存款帐户余额不足');
                    return 0;
                }else{
                    return $rows;
                }
            }else{
                $errMsg .= __('预存款帐户不存在');
                return false;
            }
        }else{
            $errMsg .= __('查询预存款帐户失败');
            return false;
        }
    }

    /**
     * add 预存款充值
     *
     * @param mixed $member_id
     * @param mixed $money
     * @param mixed $message
     * @access public
     * @return void
     */
    function add($member_id,$money,$message,&$errMsg, $payment_id='', $order_id='' ,$paymethod='' ,$memo='',$type=0){
        if($rows = $this->checkAccount($member_id,0,$errMsg)){
            $data=array('advance'=>$rows[0]['advance'] + $money);
            if($data['advance'] < 0){
                $errMsg .= __('更新预存款账户失败');
                return false;
            }
            $member_advance = $data['advance'];

            $rs = $this->db->exec('SELECT * FROM sdb_members WHERE member_id='.intval($member_id));
            $sql = $this->db->getUpdateSQL($rs,$data);
            if($this->db->exec($sql)){
                $this->log($member_id,$money,$message, $payment_id, $order_id ,$paymethod ,$memo ,$member_advance);
                if (!$type){
                    $data['member_id']=$member_id;
                    $this->fireEvent('member/account:changeadvance',$data,$member_id);
                }
                return true;
            }else{
                $errMsg .= __('更新预存款帐户失败');
                return false;
            }
        }else{
            return false;
        }
    }

    /**
     * deduct 扣除预存款
     *
     * @param mixed $member_id
     * @param mixed $money
     * @param mixed $message
     * @access public
     * @return void
     */
    function deduct($member_id,$money,$message,&$errMsg, $payment_id='', $order_id='' ,$paymethod='' ,$memo=''){
        if($rows = $this->checkAccount($member_id,$money,$errMsg)){
            $data=array('advance'=>$rows[0]['advance']-$money);
            $member_advance = $data['advance'];
            $rs = $this->db->exec('SELECT * FROM sdb_members WHERE member_id='.intval($member_id));
            $sql = $this->db->getUpdateSQL($rs,$data);
            if(!$sql || $this->db->exec($sql)){
                $this->log($member_id,-$money,$message, $payment_id, $order_id ,$paymethod ,$memo ,$member_advance );
                return true;
            }else{
                $errMsg .= __('更新预存款帐户失败');
                return false;
            }
        }else{
            return false;
        }
    }

    /**
     * log 取得记录
     *
     * @param mixed $member_id
     * @param mixed $start
     * @param mixed $end
     * @access public
     * @return void
     */
    function log($member_id,$money,$message, $payment_id='', $order_id='' ,$paymethod='' ,$memo='' ,$member_advance='' ){
        $shop_advance = $this->getShopAdvance();
        $rs = $this->db->exec('select * from sdb_advance_logs where 0=1');
        $sql = $this->db->getInsertSQL($rs,array(
            'member_id'=>$member_id,
            'money'=>$money,
            'mtime'=>time(),
            'message'=>$message,
            'payment_id'=>$payment_id,
            'order_id'=>$order_id?$order_id:null,
            'paymethod'=>$paymethod,
            'memo'=>$memo,
            'import_money'=>($money>0?$money:0),
            'explode_money'=>($money<0?-$money:0),
            'member_advance'=>$member_advance,
            'shop_advance'=>$shop_advance
            ));
        return $this->db->exec($sql);
    }

    /**
     * getListByMemId 取得现有预存款
     *
     * @param mixed $member_id
     * @access public
     * @return void
     */
    function getListByMemId($member_id){
        return $this->db->select('SELECT * FROM sdb_advance_logs WHERE member_id='.$member_id);
    }

    function getFrontAdvList($memberId,$nPage,$perpage=PERPAGE){
        return $this->db->selectPager('SELECT * FROM sdb_advance_logs WHERE member_id='.$memberId.' ORDER BY mtime DESC',$nPage,$perpage);
    }

    function getShopAdvance(){
        $row = $this->db->selectrow("SELECT SUM(advance) as sum_advance FROM sdb_members");
        return $row['sum_advance'];
    }

    /**
     * get 取得现有预存款
     *
     * @param mixed $member_id
     * @access public
     * @return void
     */
    function get($member_id){
        $row = $this->db->selectrow('SELECT advance FROM sdb_members WHERE member_id='.intval($member_id));
        return $row['advance'];
    }

    function getAdvanceStatistics($sdate=null,$edate=null){
        $sql = 'SELECT COUNT(*) AS count, SUM(import_money) AS import_money, SUM(explode_money) AS explode_money FROM sdb_advance_logs ';
        $where = array();

        if($sdate)
            $where[] = " mtime >= ".strtotime($sdate);
        if($edate)
            $where[] = " mtime <= ".(strtotime($edate)+3600*1000);

        if(!empty($where)){
            $sql .= ' WHERE '.implode(' AND ',$where);
        }
  
        return $this->db->selectrow($sql);
    }

    function getMemberAdvanceStatistics($mId){
        $sql = 'SELECT COUNT(*) AS count, SUM(import_money) AS import_money, SUM(explode_money) AS explode_money FROM sdb_advance_logs WHERE member_id = '.$mId;
        return $this->db->selectrow($sql);
    }

    function getAdvanceLogByLogId($logid){
        return $this->db->selectrow('SELECT * FROM sdb_advance_logs WHERE log_id = '.$logid);
    }
}
?>
