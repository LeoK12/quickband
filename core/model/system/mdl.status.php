<?php
class mdl_status extends modelFactory {

    function getList(){
        $return = array();
        foreach($this->db->select('select * from sdb_status where date_affect="0000-00-00"')  as $row){
            $return[$row['status_key']] = $row['status_value'];
        }
        return $return;
    }

    function add($key,$value=1,$skip_history=false){
        $key = strtoupper($key);
        if(!$skip_history){
            $this->_add_value($key,date('Y-m-d'),$value);
        }
        $this->_add_value($key,'0000-00-00',$value);
        return true;
    }

    function _add_value($key,$date,$value){
        if(false!==$this->get($key,$date)){
            $this->db->exec('update sdb_status set status_value=status_value+'.floatval($value).' where status_key='.$this->db->quote($key).' and date_affect='.$this->db->quote($date));
        }else{
            $this->set($key,$this->get($key,$date)+$value,$date);
        }
    }

    function set($key,$value,$date='0000-00-00'){
        $key = strtoupper($key);
        $rs = $this->db->exec('select * from sdb_status where status_key=\''.$key.'\' and date_affect="'.$date.'"');
        $sql = $this->db->getUpdateSQL($rs,array('status_key'=>$key,'status_value'=>$value,'last_update'=>time(),'date_affect'=>$date),true);
        return $this->db->exec($sql);
    }

    function get($key,$date='0000-00-00'){
        $key = strtoupper($key);
        if($row = $this->db->selectrow('select status_value from sdb_status where status_key=\''.$key.'\' and date_affect="'.$date.'"')){
            return $row['status_value'];
        }else{
            return false;
        }
    }

    function update($force_count=false){
        $in_lib = $this->getList();
        foreach(get_class_methods($this) as $func){
            if(substr($func,0,6)=='count_'){
                if($force_count || !isset($in_lib[strtoupper(substr($func,6))])){
                    $this->$func();
                }
            }
        }
    }

    function _update_count($func,$count){
        return $this->set(substr($func,6),$count);
    }

    //未处理缺货登记
    function count_gnotify(){
        $oNotify = &$this->system->loadModel('goods/goodsNotify');
        $filter['status'] = 'ready';
        $filter['disabled'] = 'false';
        return $this->_update_count(__FUNCTION__,$oNotify->count($filter));
    }

    //商品库存报警
    function count_galert(){
        $r = $this->db->selectrow('SELECT count(distinct(goods_id)) as c FROM sdb_products where store<='.intval($this->system->getConf('system.product.alert.num')));
        return $this->_update_count(__FUNCTION__,$r['c']);
    }

    //未处理商品评论
    function count_gdiscuss(){
        $oDiscuss = &$this->system->loadModel('comment/discuss');
        return $this->_update_count(__FUNCTION__,$oDiscuss->count(array('adm_read_status'=>'false')));
    }

    //未处理购买咨询
    function count_gask(){
        $oGask = &$this->system->loadModel('comment/gask');
        return $this->_update_count(__FUNCTION__,$oGask->count(array('adm_read_status'=>'false','disabled'=>'false')));
    }

    //未处理商店留言
    function count_messages(){
        $oBBS = &$this->system->loadModel('resources/shopbbs');
        return $this->_update_count(__FUNCTION__,$oBBS->count(array('unread'=>0)));
    }

    //未处理订单
    function count_order_new(){
        $oOrder = &$this->system->loadModel('trading/order');
        $filter['status'] = 'active';
        $filter['pay_status'] = array('0');
        $filter['ship_status'] = array('0');
        $filter['disabled'] = 'false';
        $filter['confirm'] = 'N';
        return $this->_update_count(__FUNCTION__,$oOrder->count($filter));
    }

    //待付款订单
    function count_order_to_pay(){
        $sales = &$this->system->loadModel('utility/salescount');
        $count=$sales->orderWithoutPay();
        return $this->_update_count(__FUNCTION__,$count);
    }

    //已付款待发货订单
    function count_order_to_dly(){
        $sales = &$this->system->loadModel('utility/salescount');
        $count=$sales->playWithoutDeliever();
        return $this->_update_count(__FUNCTION__,$count);
    }

    //上架商品
    function count_goods_online(){
        $oGoods = &$this->system->loadModel('goods/products');
        $count=$oGoods->getMarketGoods('true');
        return $this->_update_count(__FUNCTION__,$count);
    }

    //已下架商品
    function count_goods_hidden(){
        $oGoods = &$this->system->loadModel('goods/products');
        $count=$oGoods->getMarketGoods('false');
        return $this->_update_count(__FUNCTION__,$count);
    }

}
?>