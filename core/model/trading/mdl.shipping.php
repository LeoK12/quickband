<?php
require_once(dirname(__FILE__).'/mdl.delivery.php');
class mdl_shipping extends mdl_delivery{
    //START
    var $idColumn='delivery_id';
    var $adminCtl='order/shipping';
    var $textColumn = 'delivery_id';
    var $defaultCols = 'delivery_id,order_id,t_begin,member_id,money,is_protect,ship_name,delivery,logi_name,logi_no';
    var $defaultOrder = array('t_begin','DESC');
    var $tableName = 'sdb_delivery';

    function getFilter($p){
        //$return['payment']=array_merge(array(array('id'=>0,'custom_name'=>__('线下支付'))),$this->getMethods());
        //$aDelivery=$this->getPlugins();
        return $return;
    }
    function getColumns(){
        $data = parent::getColumns();
        unset($data['_cmd']);
        return $data;
    }
    //按物理单号查询订单号
    function getOrdersByLogino($logino){
        $logino = addslashes($logino);
        $aRet = $this->db->select("SELECT order_id FROM sdb_delivery WHERE logi_no = '{$logino}'");
        $aOrder = array(0);

        foreach($aRet as $row){
            $aOrder[] = $row['order_id'];
        }
        return $aOrder;
    }

    function searchOptions(){
        $arr = parent::searchOptions();
        return array_merge($arr,array(
                'uname'=>__('会员用户名'),
            ));
    }

    function _filter($filter){
        $filter['type'] = 'delivery';
        $where = array(1);

        if(isset($filter['delivery_id'])){
            if(is_array($filter['delivery_id'])){
                if($filter['delivery_id'][0] != '_ALL_'){
                    if(!isset($filter['delivery_id'][1])){
                        $where[] = 'delivery_id = '.$this->db->quote($filter['delivery_id'][0]).'';
                    }else{
                        $aOrder = array();
                        foreach($filter['delivery_id'] as $delivery_id){
                            $aOrder[] = 'delivery_id='.$this->db->quote($delivery_id).'';
                        }
                        $where[] = '('.implode(' OR ',$aOrder).')';
                        unset($aOrder);
                    }
                }
            }else{
                $where[] = 'delivery_id = '.$this->db->quote($filter['delivery_id']).'';
            }
            unset($filter['delivery_id']);
        }

        if(array_key_exists('uname', $filter)&&trim($filter['uname'])!=''){
            $user_data = $this->db->select("select member_id from sdb_members where uname = '".addslashes($filter['uname'])."'");
            foreach($user_data as $tmp_user){
                $now_user[] = $tmp_user['member_id'];
            }
            $where[] = 'member_id IN (\''.implode("','",$now_user).'\')';
            unset($filter['uname']);
        }else{
            if(isset($filter['uname']))
                unset($filter['uname']);
        }

        return parent::_filter($filter).' and '.implode(' AND ',$where);
    }

    function getPlugins(){
        $dir = PLUGIN_DIR.'/shipping/';
        if ($handle = opendir($dir)) {
                while (false !== ($file = readdir($handle))) {
                    if(is_file($dir.'/'.$file) && substr($file,0,5)=='ship.' ){
                        include_once($dir.'/'.$file);
                        $payName = substr($file,5,-4);
                        $class_name='ship_'.$payName;
                        $o = new $class_name;
                        $return[$payName] = get_object_vars($o);
                    }
                }
                closedir($handle);
        }
        return $return;
    }

    function toRemove($id){
        $sqlString = "DELETE FROM sdb_delivery WHERE delivery_id='".$id."'";
        $this->db->exec($sqlString);

        $sqlString = "DELETE FROM sdb_delivery_item WHERE delivery_id='".$id."'";
        $this->db->exec($sqlString);
    }
}
