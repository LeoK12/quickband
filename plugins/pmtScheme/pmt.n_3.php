<?php
class pmt_n_3{
    var $name = '促销活动规则--顾客购买指定的商品，可获得翻倍积分或者x倍积分';
    var $memo = '顾客购买指定的商品，可获得翻倍积分或者x倍积分';
    var $pmts_solution = array(
        'type'=>'goods',
        'condition'=>array(
            array('mLev')
        ),            
        'method'=>array(
            array('moreScore'),
        )
    );
    var $pmts_type = PMT_SCHEME_PROMOTION;
}
?>
