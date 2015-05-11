<?php

###IP 要用ip2long转化后存储，反之取出时要用long2ip还原###

require_once('shopObject.php');

class mdl_paymentcfg extends shopObject{
    //var $__setting;

    var $adminCtl = 'order/payment';
    var $idColumn='id';
    var $textColumn = 'custom_name';
    var $defaultCols = 'custom_name,pay_type,orderlist';
    var $defaultOrder = array('orderlist','desc',',','id','ASC');
    var $tableName = 'sdb_payment_cfg';
/*
    function getColumns(){
        $ret = array('_cmd'=>array('label'=>__('操作'),'width'=>60,'html'=>'payment/finder_command.html'));
        $ret = array_merge($ret, parent::getColumns());
        return $ret;
    }*/
}
?>
