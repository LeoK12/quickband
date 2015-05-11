<?php
require_once('shopObject.php');

class mdl_dly_centers extends shopObject{

    var $idColumn = 'dly_center_id'; //表示id的列
    var $textColumn = 'name';
    var $defaultCols = 'name,region,address,area_id,zip,phone,uname';
    var $adminCtl = 'trading/delivery_centers';
    var $defaultOrder = array('dly_center_id','desc');
    var $tableName = 'sdb_dly_center';


    function getColumns($filter){
        $ret = array('_cmd'=>array('label'=>__('操作'),'width'=>70,'html'=>'order/dly_center_command.html'));
        return array_merge($ret,parent::getColumns());
    }

}
?>
