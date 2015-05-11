<?php
require_once('shopObject.php');

class mdl_dly_printer extends shopObject{

    var $idColumn = 'prt_tmpl_id'; //表示id的列
    var $textColumn = 'prt_tmpl_title';
    var $defaultCols = 'prt_tmpl_title,shortcut';
    var $adminCtl = 'trading/delivery_printer';
    var $defaultOrder = array('prt_tmpl_id','asc');
    var $tableName = 'sdb_print_tmpl';

    function insert($data){
        if(has_unsafeword($data['prt_tmpl_title'])){
            trigger_error(__('无法保存，标题含有非法字符'),E_USER_ERROR);
        }
        $sql = 'select prt_tmpl_id from sdb_print_tmpl where prt_tmpl_title="'.$this->db->quote($data['prt_tmpl_title']).'"';
        if($this->db->selectrow($sql)){
            trigger_error(__('无法保存，存在同名模板'),E_USER_ERROR);
        }
        return parent::insert($data);
    }


    function getColumns($filter){
        $ret = array('_cmd'=>array('label'=>__('操作'),'width'=>230,'html'=>'order/dly_printer_command.html'));
        return array_merge($ret,parent::getColumns());
    }

    function delete($filter){
        $this->disabledMark=false;
        foreach($this->getList('prt_tmpl_id',$filter) as $r){
            unlink(HOME_DIR.'/upload/dly_bg_'.$r['prt_tmpl_id'].'.jpg');
        }
        parent::delete($filter);
    }

    function update($data,$filter){
        if($data['prt_tmpl_title']){
            if(has_unsafeword($data['prt_tmpl_title'])){
                trigger_error(__('无法保存，标题含有非法字符'),E_USER_ERROR);
                return false;
            }
            if(!$filter['prt_tmpl_id']){
                trigger_error(__('无法保存，模板名称不能重复'),E_USER_ERROR);
                return false;
            }
            $sql = 'select prt_tmpl_id from sdb_print_tmpl where prt_tmpl_id!='.intval($filter['prt_tmpl_id']).' and prt_tmpl_title="'.$this->db->quote($data['prt_tmpl_title']).'"';
            if($r = $this->db->selectrow($sql)){
                trigger_error(__('无法保存，存在同名模板'),E_USER_ERROR);
                return false;
            }
        }
        return parent::update($data,$filter);
    }

    function getElements(){
        $elements = array(
            'ship_name'=>__('收货人-姓名'),

            'ship_area_0'=>__('收货人-地区1级'),
            'ship_area_1'=>__('收货人-地区2级'),
            'ship_area_2'=>__('收货人-地区3级'),

            'ship_addr'=>__('收货人-地址'),
            'ship_tel'=>__('收货人-电话'),
            'ship_mobile'=>__('收货人-手机'),
            'ship_zip'=>__('收货人-邮编'),
            'dly_name'=>__('发货人-姓名'),
            'ship_detail_addr'=>__('收货人-地区+详细地址'),

            'dly_area_0'=>__('发货人-地区1级'),
            'dly_area_1'=>__('发货人-地区2级'),
            'dly_area_2'=>__('发货人-地区3级'),

            'dly_address'=>__('发货人-地址'),
            'dly_tel'=>__('发货人-电话'),
            'dly_mobile'=>__('发货人-手机'),
            'dly_zip'=>__('发货人-邮编'),
            'date_y'=>__('当日日期-年'),
            'date_m'=>__('当日日期-月'),
            'date_d'=>__('当日日期-日'),
            'order_print'=>'订单条码',
            'order_id'=>__('订单-订单号'),
            'order_price'=>__('订单总金额'),
            'order_weight'=>__('订单物品总重量'),
            'order_count'=>__('订单-物品数量'),
            'order_memo'=>__('订单-备注'),
            'ship_time'=>__('订单-送货时间'),
            'shop_name'=>__('网店名称'),
            'tick'=>__('对号 - √'),
            'text'=>__('自定义内容'),
            'member_name'=>__('会员用户名'),

            'order_name'=>__('订单商品名称'),
            'order_name_a'=>__('订单商品名称+数量'),
            'order_name_as'=>__('订单商品名称+规格+数量'),
            'order_name_ab'=>__('订单商品名称+货号+数量'),
            'order_print_id'=>__('订单打印编号'),
            'delivery_print'=>__('订单打印编号-条形码'),
        );
        return $elements;
    }

}
?>
