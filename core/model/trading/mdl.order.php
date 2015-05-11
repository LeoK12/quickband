<?php
define('OLIST_ACT',0);
define('OLIST_ALL',1);
define('OLIST_STARRED',2);
define('ORDER_PRINT_CART',1); //购物清单
define('ORDER_PRINT_SHEET',2); //配货单
define('ORDER_PRINT_MERGE',4); //联合打印
define('ORDER_PRINT_DLY',8); //快递单打印
include_once('delivercorp.php');
include_once('shopObject.php');
class mdl_order extends shopObject{

    var $idColumn = 'order_id';
    var $textColumn = 'order_id';
    var $defaultCols = 'order_id,order_tips,createtime,total_amount,ship_name,pay_status,ship_status,shipping,print_status,payment,member_id';
    var $appendCols = 'pay_status as pay_stat,ship_status as ship_stat,status as stat,mark_type,mark_text,is_has_remote_pdts';
    var $adminCtl = 'order/order';
    var $defaultOrder = array('createtime','DESC');
    var $tableName='sdb_orders';
    var $hasTag = true;
    var $typeName = 'order';
    var $name='订单';

    function searchOptions(){
        $arr = parent::searchOptions();

        return array_merge($arr,array(
                'bn'=>__('货号'),
                'goods_name'=>__('商品名称'),
                'logi_no'=>__('物流单号'),
                'member_name'=>__('会员用户名'),
            ));
    }

    function getColumns(){
        $ret = parent::getColumns();
        $ret['_cmd'] = array('label'=>__('操作'),'width'=>70,'html'=>'order/finder_command.html');
        $ret['goods_na'] = array('label'=>__('商品名称'),'width'=>70,'filtertype'=>'yes','filterdefalut'=>true,'hidden'=>true);       //把商品名称加入商品搜索
        $ret['order_id']['default'] = '';
        //付款状态
        $ret['pay_status']['default'] = '';
        //发货状态
        $ret['ship_status']['default'] = '';
        //订单总额
        $ret['total_amount']['default'] = '';
        //订单提示
        $ret['order_tips']['type'] = 'order_tips';
        $ret['order_tips']['label'] = '提示';
        $ret['order_tips']['width'] = 50;
        $ret['order_tips']['sql'] = 'order_id';
        //配送方式修改
        $ret['shipping']['type'] = 'object:trading/delivery';
        $ret['shipping']['filtertype'] = 'yes';
        $ret['shipping']['filterdefalut'] = true;

        return $ret;
    }

    function modifier_order_tips(&$rows){
        foreach( $rows as $rk=>$rv) {
            $rows[$rk] = '';
        }

        $msg = $this->db->select('SELECT rel_order AS order_id ,msg_type, count(*) AS c FROM sdb_message WHERE unread = "0" AND rel_order IN ('.implode(',', array_keys($rows)).') GROUP BY rel_order , msg_type ORDER BY msg_type');

        foreach( $msg as $mkey=>$mval) {
            if( $mval['c'] > 0 ){
                if( $mval['msg_type'] == 'payment' )
                    $rows[$mval['order_id']] = $rows[$mval['order_id']].' <img src="./images/icon_payment.gif" width="15" height="15"  onclick="'.$alertjs.'"/> ';
                else if( $mval['msg_type'] == 'default')
                    $rows[$mval['order_id']] = $rows[$mval['order_id']].'<span detail="index.php?ctl=order/order&act=detail&p[0]='.$mval['order_id'].'&p[1]=detail_msg"> <img src="./images/icon_leave.gif" width="15" height="15"/> </span>';

            }
        }

        $remark=$this->db->select('SELECT order_id,mark_text FROM sdb_orders WHERE order_id IN ('.implode(',', array_keys($rows)).') ');
        /*
        foreach($remark as $k=>$v){
            if($remark[$k]['mark_text']){
                $rows[$v['order_id']] = $rows[$v['order_id']].'<span class=\'memberinfo\' title=\''.$remark[$k]['mark_text'].'\'>&nbsp;</span>';
            }
        }*/
        return $rows['order_tips'];

    }

    function events(){
        $ret = array(
            'create'=>array('label'=>__('新建')),//'params'=>array('order_point'=>array('label'=>'积分','type'=>'number'),)),
//            'confirm'=>array('label'=>__('确认')),
            'payed'=>array('label'=>__('付款')),
            'shipping'=>array('label'=>__('发货')),
            'finished'=>array('label'=>__('存档')),
            'cancel'=>array('label'=>__('取消')),
            'returned'=>array('label'=>__('退货')),
            'refund'=>array('label'=>__('退款')),
            'add_message'=>array('label'=>__('用户留言')),
            'reply_message'=>array('label'=>__('留言回复')),
            'addmemo'=>array('label'=>__('备注')),
            'editorder'=>array('label'=>__('修改'))
            );
        $global_params = array(
            'buycount'=>array('label'=>__('购物次数'),'type'=>'number'),
            'buyamount'=>array('label'=>__('购物金额累计'),'type'=>'number'),
            'total_amount'=>array('label'=>__('本次购物金额'),'type'=>'number'),
            'is_birthday'=>array('label'=>__('生日购物'),'type'=>'bool'),
            'is_tax'=>array('label'=>__('是否需要发票'),'type'=>'bool')
            );
        foreach($ret as $k=>$v){
            if($ret[$k]['params']){
                $ret[$k]['params'] = array_merge($ret[$k]['params'],$global_params);
            }else{
                $ret[$k]['params'] = &$global_params;
            }
        }
        return $ret;
    }

    function _get_buycount($order_id){
        $aData = $this->instance($order_id,'member_id');
        $filter['pay_status'] = "1";
        $filter['member_id'] = $aData['member_id'];
        return $this->count($filter);
    }

    function _get_buyamount($order_id){
        $aData = $this->instance($order_id,'member_id');
        $filter['pay_status'] = "1";
        $filter['member_id'] = $aData['member_id'];
        $aOrder = $this->getList('*',$filter, 0, -1);
        $money = 0;
        foreach((array)$aOrder as $row){
            $money += $row['payed'];
        }
        return $money;
    }

    function _get_is_birthday($order_id){
        $a_tmp = $this->instance($order_id,'member_id');
        $oMember = $this->system->loadModel('member/member');
        $a_tmp = $oMember->instance($a_tmp['member_id'], 'b_year, b_month, b_day');
        if($a_tmp['b_month'] == date('n',mktime())
            && $a_tmp['b_day'] == date('j',mktime())){
            return true;
        }else{
            return false;
        }
    }

    /**
     * is_highlight
     * 高亮finder的行
     *
     * @param mixed $row
     * @access public
     * @return void
     */
    function getLastestOrder($rowlimit=10){

        $sql='select order_id,ship_name,createtime,total_amount,sex from sdb_orders od
              Left Join sdb_members me on od.member_id =me.member_id where od.ship_status="1" and od.pay_status ="1" order by od.createtime desc limit 0,'.intval($rowlimit);

        return $this->db->select($sql);
    }
    function is_highlight($row){
        return $row['stat'] == 'active' && $row['pay_status'] == 0 && $row['ship_status'] == 0;
    }

    function modifier_payment(&$rows){
        $status = array(0=>__('线下支付'),
                    -1=>__('货到付款') );
        foreach($rows as $k => $v){
            if($v < 1) $rows[$k] = $status[$v];
        }
        foreach($this->db->select('SELECT id,custom_name FROM sdb_payment_cfg WHERE id IN ('.implode(',', array_keys($rows)).')') as $r){
            $rows[$r['id']] = $r['custom_name'];
        }
    }

    function modifier_print_status(&$rows){
        foreach($rows as $k => $v){
            $rows[$k] = '<img src="images/print-icon.gif" width="16px" height="16px" style="margin:3px 0 0 2px" />';
            $rows[$k] .= '&nbsp;<span class="'.((ORDER_PRINT_CART==(ORDER_PRINT_CART & $v))?'p_prted':'p_prt').'" onclick="orderPrint(this,'.ORDER_PRINT_CART.__(')" title="购物清单打印">购</span>');
            $rows[$k] .= '&nbsp;<span class="'.((ORDER_PRINT_SHEET==(ORDER_PRINT_SHEET & $v))?'p_prted':'p_prt').'" onclick="orderPrint(this,'.ORDER_PRINT_SHEET.__(')" title="配货单打印">配</span>');
            $rows[$k] .= '&nbsp;<span class="'.((ORDER_PRINT_MERGE==(ORDER_PRINT_MERGE & $v))?'p_prted':'p_prt').'" onclick="orderPrint(this,'.ORDER_PRINT_MERGE.__(')" title="联合打印">合</span>');
            $rows[$k] .= '&nbsp;<span class="'.((ORDER_PRINT_DLY==(ORDER_PRINT_DLY & $v))?'p_prted':'p_prt').'" onclick="orderPrint(this,'.ORDER_PRINT_DLY.__(')" title="快递单打印">递</span>');
        }
    }

    function exporter_print_status(&$rows){;}

    function modifier_mark_text($row){
        if($row['mark_text']!=''){
            return "<span  title=\"".$row['mark_text']."\"><img src=\"../statics/remark_icons/".$row['mark_type'].".gif\"></span>";
        }
    }
    function modifier_member_id(&$rows){
        foreach($rows as $k => $v){
            if($v == 0) $rows[$k] = '-';
            else{
                $tmpid[$k] = $v;
            }
        }
        $tmpid = array_keys($tmpid);
        if(isset($tmpid)){
            foreach($this->db->select('SELECT member_id,uname FROM sdb_members WHERE member_id IN ('.implode(',',$tmpid).')') as $r){
                $rows[$r['member_id']] = $r['uname'];
            }
        }
    }
    function getFilter($p){
        $delivery = &$this->system->loadModel('trading/delivery');
        $return['areas'] = $delivery->getDlAreaList();
        $return['delivery'] = $delivery->getDlTypeList();
        $payment = &$this->system->loadModel('trading/payment');
        $return['payment'] = $payment->getMethods();
        return $return;
    }
    function countNum(){
        $conunt= $this->db->selectRow('select count("order_id") as ordernum from sdb_orders where disabled="false"');
        return $conunt['ordernum'];
    }

    function _filter($filter){

        $filter = is_array($filter)?$filter:$_POST;
        $where=array(1);

        if(isset($filter['order_id'])){
            if(is_array($filter['order_id'])){
                if($filter['order_id'][0] != '_ALL_'){
                    if(!isset($filter['order_id'][1])){
                        $where[] = 'order_id LIKE \''.addslashes(trim($filter['order_id'][0])).'%\'';
                    }else{
                        $aOrder = array();
                        foreach($filter['order_id'] as $order_id){
                            $aOrder[] = 'order_id="'.addslashes(trim($order_id)).'"';
                        }
                        $where[] = '('.implode(' OR ',$aOrder).')';
                        unset($aOrder);
                    }
                }
            }else{
                $where[] = 'order_id LIKE \''.addslashes(trim($filter['order_id'])).'%\'';
            }
            unset($filter['order_id']);
        }

        if(array_key_exists('goods_na', $filter)){             //把商品名称加入商品搜索
            if($filter['goods_na'] !== ''){
                $aId = array(0);
                foreach($this->db->select('SELECT order_id FROM sdb_order_items WHERE name LIKE \'%'.addslashes(trim($filter['goods_na'])).'%\'') as $rows){
                    $aId[] = 'order_id = \''.$rows['order_id'].'\'';
                }
                $where[] = '('.implode(' OR ', $aId).')';
            }

            unset($filter['goods_na']);
        }
        
        if(array_key_exists('shipping', $filter)){             //修改配送方式选择项
            if($filter['shipping'] !== ''){
                $aId = array();
                foreach($this->db->select('SELECT dt_name FROM sdb_dly_type  WHERE dt_id = \''.addslashes(trim($filter['shipping'])).'\'') as $rows){
                    $aId[] = 'shipping  = \''.addslashes(trim($rows['dt_name'])).'\'';
                }
                $where[] = '('.implode(' OR ', $aId).')';
            }

            unset($filter['shipping']);
        }

        if(array_key_exists('bn', $filter)){
            if($filter['bn'] !== ''){
                $aId = array(0);
                foreach($this->db->select('SELECT order_id FROM sdb_order_items WHERE bn LIKE \''.addslashes(trim($filter['bn'])).'%\'') as $rows){
                    $aId[] = 'order_id = \''.addslashes($rows['order_id']).'\'';
                }
                $where[] = '('.implode(' OR ', $aId).')';
            }

            unset($filter['bn']);
        }

        if(array_key_exists('goods_name', $filter)){
            if($filter['goods_name'] !== ''){
                $aId = array(0);
                foreach($this->db->select('SELECT order_id FROM sdb_order_items WHERE name LIKE \'%'.addslashes($filter['goods_name']).'%\'') as $rows){
                    $aId[] = 'order_id = \''.$rows['order_id'].'\'';
                }
                $where[] = '('.implode(' OR ', $aId).')';
            }

            unset($filter['goods_name']);
        }

        if(array_key_exists('logi_no', $filter)){
            if($filter['logi_no'] !== ''){
                $objShipping = &$this->system->loadModel('trading/shipping');
                $aOrder = $objShipping->getOrdersByLogino($filter['logi_no']);
                $where[] = 'order_id IN (\''.implode("','",$aOrder).'\')';
            }
            unset($filter['logi_no']);
        }

        if(array_key_exists('member_name', $filter)){
            if($filter['member_name'] !== ''){
                $aId = array(0.1);
                foreach($this->db->select('SELECT member_id FROM sdb_members WHERE uname = \''.addslashes($filter['member_name']).'\'') as $rows){
                    $aId[] = $rows['member_id'];
                }
                $where[] = 'member_id IN ('.implode(',', $aId).')';
            }
            unset($filter['member_name']);
        }

        return parent::_filter($filter).' and '.implode($where,' AND ');
    }

    function load($order_id){
        if($row = $this->db->selectrow('SELECT * from sdb_orders where order_id ='.$order_id)){
            $this->update_last_modify($order_id);
            $this->_info['order_id'] = $row['order_id'];        //会员id
            $this->_info['member_id'] = $row['member_id'];        //会员id
            $this->_info['confirm'] = $row['confirm']=='Y';
            $this->_info['status'] = $row['status'];        //状态：active:活动,  dead:死单, finish:完成
            $this->_info['pay_status'] = $row['pay_status'];        //是否支付(0 未支付 1 已支付 2 处理中 3 部分付款 4 部分退款 5 全额退款)
            $this->_info['ship_status'] = $row['ship_status'];        //发货状态：0未发货，1已发货，2部分发货，3部分退货，4已退货
            $this->_info['user_status'] = $row['user_status'];        //用户交互状态(null:无；payed:已支付；shipped:已收到货)
            $this->_info['is_delivery'] = $row['is_delivery'];        //是否实体配送Y;N
            $this->_info['weight'] = $row['weight'];        //重量
            $this->_info['tostr'] = $row['tostr'];        //文字描述
            $this->_info['acttime'] = $row['acttime'];        //活动时间
            $this->_info['createtime'] = $row['createtime'];        //创建时间
            $this->_info['itemnum'] = $row['itemnum'];        //货物总数量
            $this->_info['ip'] = $row['ip'];        //下单ip地址
            $this->_info['currency'] = $row['currency'];        //货币
            $this->_info['cur_rate'] = $row['cur_rate'];        //货币汇率
            $this->_info['payment'] = $row['payment'];        //支付方式
            $this->_info['memo'] = $row['memo'];        //订单备注
            $this->_info['receiver']['name'] = $row['ship_name'];        //收货人姓名
            $this->_info['receiver']['area'] = $row['ship_area'];        //收货人地qu
            $this->_info['receiver']['addr'] = $row['ship_addr'];        //收货人地址
            $this->_info['receiver']['zip'] = $row['ship_zip'];        //收货人邮编
            $this->_info['receiver']['tel'] = $row['ship_tel'];        //收货人固定电话
            $this->_info['receiver']['email'] = $row['ship_email'];        //收货人Email
            $this->_info['receiver']['mobile'] = $row['ship_mobile'];        //收货人移动电话
            $this->_info['shipping']['id'] = $row['shipping_id'];        //配送方式
            $this->_info['shipping']['time'] = $row['ship_time'];        //要求到货时间
            $this->_info['shipping']['method'] = $row['shipping'];        //配送方式
            $this->_info['shipping']['cost'] = $row['cost_freight'];        //配送价格
            $this->_info['shipping']['is_protect'] = $row['is_protect'];        //保价价格
            $this->_info['shipping']['protect'] = $row['cost_protect'];        //保价价格
            $this->_info['shipping']['area'] = $row['shipping_area'];        //配送地区
            $this->_info['basic']['totalPrice'] = $row['cost_item'];        //商品价格
            $this->_info['is_tax'] = $row['is_tax'];        //税 (仅商品)
            $this->_info['cost_tax'] = $row['cost_tax'];        //税 (仅商品)
            $this->_info['tax_company'] = $row['tax_company'];        //税 (仅商品)
            $this->_info['use_pmt'] = $row['use_pmt'];
            $this->_info['discount'] = $row['discount'];
            $this->_info['use_pmt'] = $row['use_pmt'];
            $this->_info['score_g'] = $row['score_g'];
            $this->_info['score_u'] = $row['score_u'];
            $this->_info['advance'] = $row['advance'];        //返点到预存款
            $this->_info['amount']['total'] = $row['total_amount'];
            $this->_info['amount']['final'] = $row['final_amount'];
            $this->_info['amount']['payed'] = $row['payed'];        //现已支付金额：应等于payments表本order所有money的和
            $this->_info['amount']['cost_payment'] = $row['cost_payment'];
            $this->_info['amount']['pmt_amount'] = $row['pmt_amount'];
            $this->_info['pay_extend'] = $row['extend'];
            $this->_info['last_change_time']=$row['last_change_time'];
            $this->_inDatabase = true;

            switch($row['payment']){
                case 0:
                $this->_info['paymethod'] = __('线下支付');
                $this->_info['paytype'] = 'OFFLINE';
                break;
                case -1:
                $this->_info['paymethod'] = __('货到付款');
                $this->_info['paytype'] = 'PAYAFT';
                break;
                default:
                $payment = &$this->system->loadModel('trading/payment');
                $aPayment = $payment->getPaymentById($row['payment']);
                $this->_info['paymethod'] = $aPayment['custom_name'];
                $this->_info['paytype'] = $aPayment['pay_type'];
                break;
            }

            $oCur = &$this->system->loadModel('system/cur');
            $aCur = $oCur->getSysCur();
            $this->_info['cur_name'] = $aCur[$row['currency']];

            return $this->_info;
        }return false;
    }

    function getFieldById($orderId, $aField=array('*')){
        return $this->db->selectrow("SELECT ".implode(",", $aField)." FROM sdb_orders WHERE order_id='{$orderId}'");
    }

    function sumOrder($member_id){
        return $this->db->selectrow("SELECT SUM(payed) AS sum_pay, COUNT(order_id) AS sum FROM sdb_orders WHERE status = 'finish' AND member_id = ".intval($member_id)." GROUP BY member_id");
    }

    //检测实体商品配送信息的合法性
    function checkOrderDelivery($aGoods, &$aDelivery, $otherPhysical=false, $is_member=false){
        if(count($aGoods) == 0 && !$otherPhysical) return 'N';
        $gtype = &$this->system->loadModel('goods/gtype');
        $deliverInfo = $gtype->deliveryInfo($aGoods);
        if($deliverInfo['physical'] || $otherPhysical){
            if(trim($aDelivery['ship_name']) == ''
                || trim($aDelivery['ship_area']) == ''
                || (!$is_member && !(preg_match('/.+@.+$/',$aDelivery['ship_email'])))
                || (trim($aDelivery['ship_tel']) == '' && trim($aDelivery['ship_mobile']) == '')
                || trim($aDelivery['ship_addr']) == ''){
                return false;
            }else{
                return 'Y';
            }
        }else{
            return 'N';
        }
    }

    function checkPoint($memberid, $data){
        //+积分处理------------------------------------------------------------
        $oMemberPoint = &$this->system->loadModel('trading/memberPoint');
        $oGift = &$this->system->loadModel('trading/gift');
        $data['score_u'] = intval($data['totalConsumeScore']);
        if ($data['score_u']>0) {
            if ($data['score_u'] > $oMemberPoint->getMemberPoint($memberid)) {
                trigger_error(__('用户积分不足'),E_USER_ERROR);
                return false;
            }else{
                //+赠品处理------------------------------------------------------------
                if (is_array($data['gift_e']) && count($data['gift_e'])) {
                    foreach($data['gift_e'] as $giftId => $v) {
                        $aGift = $oGift->getFieldById($v['gift_id'], array('storage','freez'));
                        if ($aGift['storage'] - $aGift['freez'] < $v['nums']) {//兑换赠品缺货
                            trigger_error(__('兑换赠品').$v['name'].__('缺货'),E_USER_ERROR);//中断
                            return false;
                        }
                    }
                }
            }
            return true;
        }else{
            return true;
        }
    }

    function checkGift($gift_p){
        if (is_array($gift_p) && count($gift_p)){
            $oGift = &$this->system->loadModel('trading/gift');
            foreach($gift_p as $v) {
                $giftId = $v['gift_id'];
                if (!$oGift->freezStock($v['gift_id'], $v['nums'])) {//兑换赠品缺货
//                    trigger_error(__('赠送赠品').$v['name'].__('缺货'),E_USER_ERROR);//提示 ,E_USER_ERROR继续
                    return false;
                }
            }
            return true;
        }else{
            return true;
        }
    }

    function _saveAddr($memberid, $aData){
        if($memberid && $aData['is_save']){
            $member = &$this->system->loadModel('member/member');
            $aAddr['member_id'] = $memberid;
            $aAddr['name'] = $aData['ship_name'];
            $aAddr['area'] = $aData['ship_area'];
            $aAddr['addr'] = $aData['ship_addr'];
            $aAddr['zip'] = $aData['ship_zip'];
            $aAddr['tel'] = $aData['ship_tel'];
            $aAddr['mobile'] = $aData['ship_mobile'];
            if($aData['addr_id']){
                $aAddr['addr_id'] = $aData['addr_id'];
                $member->saveRec($aAddr);
            }else{
                $member->insertRec($aAddr, $aAddr['member_id']);
            }
        }
        return true;
    }

    function create(&$aCart,&$aMember,&$aDelivery,&$aPayment,&$minfo,&$postInfo ){

        $oSale = &$this->system->loadModel('trading/sale');
        $trading = $oSale->getCartObject($aCart,$aMember['member_lv_id'],true,true);

        //保存收货人地址
        $this->_saveAddr($aMember['member_id'], $aDelivery);
        $iProduct = 0;
        if (is_array($trading['products']) && count($trading['products'])){
            $objGoods = &$this->system->loadModel('trading/goods');    //生成订单前检查库存
            $objCart = &$this->system->loadModel('trading/cart');
            $arr = array();
            $aLinkId = array();
            foreach($trading['products'] as $k => $p){
                $aStore = $objGoods->getFieldById($p['goods_id'], array('marketable','disabled'));
                if($aStore['marketable'] == 'false' || $aStore['disabled'] == 'true'){
                    /**
                     * trigger Smarty error
                     *
                     * @param string $error_msg
                     * @param integer $error_type
                     */
                    trigger_error($p['name'].__('商品未发布不能下单。'),E_USER_ERROR);
                    return false;
                    exit;
                }

                if($this->freez_time()=='order' || $this->freez_time()=='pay' || $this->freez_time()=='delivery'){
                    if(!$objCart->_checkStore($p['product_id'], $p['nums'])){
                        trigger_error("商品“".$p['name']."”库存不足",E_USER_ERROR);
                        return false;
                        exit;
                    }
                }

                //判断配件库存to检查变量
                if(count($p['adjList'])){
                    foreach($p['adjList'] as $pid => $num){
                        if(!$objCart->_checkStore($pid, $num*$p['nums'])){
                            trigger_error("商品配件库存不足",E_USER_ERROR);
                            return false;
                            exit;
                        }
                    }
                }
                $arr[] = $p['name'].'('.$p['nums'].')';
                $this->itemnum+=$p['nums'];
                $aLinkId[] = $p['goods_id'];
                $trading['products'][$k]['addon']['minfo'] = $minfo[$p['product_id']];    //将商品用户信息存入addon
                $trading['products'][$k]['minfo'] = $minfo[$p['product_id']];    //将商品用户信息存入addon

                if($p['goods_id']) $aP[] = $p['goods_id'];
                $iProduct++;
            }
        }
        if($trading['package'] || $trading['gift_e']) $otherPhysical = true;
        else $otherPhysical = false;
        if(count($aP) || $otherPhysical){
            $return = $this->checkOrderDelivery($aP, $aDelivery, $otherPhysical, $aMember['member_id']);    //检测实体商品配送信息的合法性
            if($return){
                $aDelivery['is_delivery'] = $return;
                if($return == 'Y' && empty($aDelivery['shipping_id'])){
                    trigger_error(__("提交不成功，请选择配送方式"),E_USER_ERROR);
                    return false;
                    exit;
                }
            }else{
                trigger_error(__("对不起，请完整填写配送信息"),E_USER_ERROR);
                return false;
                exit;
            }
        }

        $iPackage = 0;
        if (is_array($trading['package']) && count($trading['package'])){
            $objCart = &$this->system->loadModel('trading/cart');
            foreach ($trading['package'] as $v) {
                if (!$objCart->_checkStore($v['goods_id'], $v['nums'])) {
                    trigger_error(__("捆绑商品库存不足"),E_USER_ERROR);
                    return false;
                    exit;
                }
                $iPackage++;
                $arr[] = $v['name'].'('.$v['nums'].')';
            }
        }
        if(is_array($trading['gift_e']) && count($trading['gift_e'])){
            foreach ($trading['gift_e'] as $v){
                $arr[] = $v['name'].'('.$v['nums'].')';
            }
        }
        if($iProduct + $iPackage + count($trading['gift_p']) + count($trading['gift_e']) == 0){
            trigger_error(__("购物车中无有效商品!"),E_USER_ERROR);
            return false;
        }

//        $objProduct->updateRate($aLinkId);    //更新商品推荐度
        $oCur = &$this->system->loadModel('system/cur');
        $tdelivery = explode( ':' , $aDelivery['ship_area'] );
        $area_id = $tdelivery[count($tdelivery)-1];
        $oDelivery = &$this->system->loadModel('trading/delivery');
        $rows = $oDelivery->getDlTypeByArea($area_id,$trading['weight'],$aDelivery['shipping_id']);
        if($trading['exemptFreight'] == 1){    //[exemptFreight] => 1免运费
            $aDelivery['cost_freight']=0;
        }else{
            $trading['cost_freight'] = $oCur->formatNumber(cal_fee($rows[0]['expressions'],$trading['weight'],$trading['pmt_b']['totalPrice'],$rows[0]['price']), false);
        }
        $trading['cost_freight'] = is_null($trading['cost_freight'])?0:$trading['cost_freight'];
        if($aDelivery['is_protect'][$aDelivery['shipping_id']] && $rows[0]['protect']==1){
            $aDelivery['cost_protect'] = $oCur->formatNumber(max($trading['totalPrice']*$rows[0]['protect_rate'],$rows[0]['minprice']), false);
            $aDelivery['is_protect'] = 'true';
        }else{
            $aDelivery['cost_protect']=0;
            $aDelivery['is_protect'] = 'false';
        }
        if($aPayment['payment'] > 0 || $aPayment['payment'] == -1){
            $oPayment = &$this->system->loadModel('trading/payment');
            $aPay = $oPayment->getPaymentById($aPayment['payment']);
            if($aPay['pay_type'] == 'DEPOSIT' && $aMember['member_id'] == ""){
                trigger_error(__("未登录客户不能选择预存款支付!"),E_USER_ERROR);
                return false;
            }
            $config=unserialize($aPay['config']);
            $aPayment['fee'] = $aPay['fee'];
            if ($config['method']==2){
                $aPayment['fee'] = $config['fee'];
                $aPayment['method'] = $config['method'];
            }
        }else{
            trigger_error(__("提交不成功，未选择支付方式!"),E_USER_ERROR);
            return false;
        }
        $currency = $oCur->getcur($aPayment['currency'], true);
        $aPayment['currency'] = $currency['cur_code'];

        if(!$this->checkPoint($aMember['member_id'], $trading)){
            return false;
        }
        if(!$this->checkGift($trading['gift_p'])){
            unset($trading['gift_p']);  //直接不给
        }
        $orderInfo = $trading;
        $orderInfo['order_id'] = $this->gen_id();
        $orderInfo['cur_rate'] = ($currency['cur_rate']>0 ? $currency['cur_rate']:1);
        $orderInfo['tostr'] = implode(',',$arr);
        $orderInfo['itemnum'] = $this->itemnum;
        getRefer($orderInfo);    //推荐下单
        $aDelivery['ship_time'] = ($aDelivery['day']=='specal' ? $aDelivery['specal_day'] : $aDelivery['day']).' '.$aDelivery['time'];
        $orderInfo = array_merge($orderInfo,$aDelivery, $aPayment);
        if( $aMember ){
            $orderInfo = array_merge($orderInfo,$aMember);
        }

             return $this->save($orderInfo, true,$postInfo);
    }

    function gen_id(){
        $i = rand(0,9999);
        do{
            if(9999==$i){
                $i=0;
            }
            $i++;
            $order_id = mydate('YmdH').str_pad($i,4,'0',STR_PAD_LEFT);
            $row = $this->db->selectrow('SELECT order_id from sdb_orders where order_id ='.$order_id);
        }while($row);
        return $order_id;
    }

    /**
     * save
     * 保存订单，用于新建或者修改
     *
     * @param mixed $doCreate 是否为新建订单
     * @access public
     * @return void todo:目前只适用于添加！！！
     */
    function save(&$trading, $doCreate=false,&$postInfo ){
        $data = $trading;
        $objDelivery = &$this->system->loadModel('trading/reship');
        $oCur = &$this->system->loadModel('system/cur');
        $aShipping = $objDelivery->getDlTypeById($trading['shipping_id']);
//        $aArea = $objDelivery->getDlAreaById($trading['area']);
        $data['shipping'] = $aShipping['dt_name'];
//        $data['shipping_area'] = $aArea['name']; 废弃字段
        $data['acttime'] = time();
        $data['createtime'] = time();
        $data['last_change_time'] = time();
        $data['ip'] = remote_addr();
        $trading['totalPrice'] = $oCur->formatNumber($trading['totalPrice'], false);
        $trading['pmt_b']['totalPrice'] = $oCur->formatNumber($trading['pmt_b']['totalPrice'], false);
        $data['cost_item'] = $trading['totalPrice'];
        $data['total_amount'] = $trading['totalPrice']+$trading['cost_freight']+$trading['cost_protect'];
        $data['pmt_amount'] = $trading['pmt_b']['totalPrice'] - $trading['totalPrice'];
        if($trading['is_tax'] && $this->system->getConf('site.trigger_tax')){
            $data['is_tax'] = 'true';
            $data['cost_tax'] = $trading['totalPrice'] * $this->system->getConf('site.tax_ratio');
            $data['cost_tax'] = $oCur->formatNumber($data['cost_tax'], false);
            $data['total_amount'] += $data['cost_tax'];
        }
        if($trading['payment'] > 0){
            if ($data['method'])
                $data['cost_payment'] = $data['fee'];
            else
                $data['cost_payment'] = $data['fee'] * $data['total_amount'];
            $data['cost_payment'] = $oCur->formatNumber($data['cost_payment'], false);
            $data['total_amount'] += $data['cost_payment'];
        }

        $newNum = $this->getOrderDecimal($data['total_amount']);

        $data['discount'] = floatval($data['total_amount'] - $newNum);
        $data['total_amount'] = $newNum;
        $data['final_amount'] = $data['total_amount'] * $data['cur_rate'];
        $data['final_amount'] = $oCur->formatNumber($data['final_amount'], false);
        $data['score_g'] = intval($data['totalGainScore']);
        $data['score_u'] = intval($data['totalConsumeScore']);
        $data['score_e'] = intval($newNum);
        if ($trading['payment']!="-1"){
            //----检测该支付方式是否还有子选项，如快钱选择银行
            $payment=$this->system->loadModel('trading/payment');
            $payment->recgextend($data,$postInfo,$extendInfo);
            $data['extend']=serialize($extendInfo);
            //------------------------------------------------
        }
        //+判断是否有远端商品
        if(true || $this->system->getConf('certificate.distribute')){ //检测付款前的订单状态,如果是刚付款立即发货
            if (!empty($trading['products']) && is_array($trading['products'])) {
                foreach($trading['products'] as $product){
                    $_where_bns[] = sprintf('\'%s\'',addslashes($product['bn']));
                }
                $_sql = sprintf('select local_bn,supplier_id
                                 from sdb_supplier_pdtbn
                                 where local_bn in(%s) and `default`=\'true\'', implode(',', $_where_bns));
                $_remote_product = $this->db->select($_sql);
                $_remote_product = array_change_key($_remote_product, 'local_bn');
                if($_remote_product){
                    $data['is_has_remote_pdts'] = 'true';
                }
            }
        }

        //----------------
        $rs = $this->db->exec('SELECT * FROM sdb_orders WHERE order_id='.$data['order_id']);
        $sql = $this->db->getUpdateSql($rs,$data,$doCreate);
        $this->_info['order_id'] = $data['order_id'];        //会员id
        if(!$this->db->exec($sql)){
            return false;
        }elseif($doCreate){
            $this->addLog(__('订单创建'), $this->op_id?$this->op_id:null, $this->op_name?$this->op_name:null , __('添加') );
        }
        $status = &$this->system->loadModel('system/status');
        $status->add('ORDER_NEW');
        $status->count_order_to_pay();
        $status->count_order_new();
        //+商品------------------------------------------------------------
        if (!empty($trading['products']) && is_array($trading['products'])) {
            $objGoods = &$this->system->loadModel('trading/goods');
            foreach ($trading['products'] as $product) {
                $product['order_id'] = $data['order_id'];
                $product['bn'] = $product['bn'];
                $product['name'] = $product['name'];
                $product['addon'] = serialize($product['addon']);
                $product['minfo'] = serialize($product['minfo']);
                $product['supplier_id'] = $_remote_product[$product['bn']]['supplier_id'];

                $rs = $this->db->query('SELECT * FROM sdb_order_items WHERE 0=1');
                $sqlString = $this->db->GetInsertSQL($rs, $product);
                if($sqlString) $this->db->exec($sqlString);
                $objGoods->updateRank($product['goods_id'], 'buy_count', $product['nums']);    //购买次数统计
                //冻结库存
                if($this->freez_time()=='order'){
                    if(intval($product['nums'])>=0){
                        $this->db->exec("UPDATE sdb_products SET freez = freez + ".intval($product['nums'])." WHERE product_id = ".intval($product['product_id']));
                    }
                    $this->db->exec("UPDATE sdb_products SET freez = ".intval($product['nums'])." WHERE product_id = ".intval($product['product_id'])." AND freez IS NULL");
                }
            }
        }

        //+捆绑商品------------------------------------------------------------
        if (is_array($trading['package']) && count($trading['package'])) {
            foreach ($trading['package'] as $pkgData) {
                $pkgData['order_id'] = $data['order_id'];
                $pkgData['product_id'] = $pkgData['goods_id'];
                $pkg[] = $pkgData['goods_id'];
                $pkgData['is_type'] = 'pkg';
                $pkgData['addon'] = serialize($pkgData['addon']);
                $rs = $this->db->query('SELECT * FROM sdb_order_items WHERE order_id='.$pkgData['order_id'].' AND is_type = \'pkg\' AND product_id='.intval($pkgData['goods_id']));
                $sqlString = $this->db->GetUpdateSQL($rs, $pkgData,true);
                $this->db->exec($sqlString);
            }
            $this->db->exec('DELETE FROM sdb_order_items WHERE order_id='.$pkgData['order_id'].' AND is_type = \'pkg\' AND product_id NOT IN('.implode(',',$pkg).')');
        }

        //+促销信息------------------------------------------------------------
        if ($trading['pmt_o']['pmt_ids']) {//促销
            $sSql = 'INSERT INTO sdb_order_pmt (pmt_id,pmt_describe,order_id) select pmt_id,pmt_describe,\''
                        .$data['order_id'].'\' FROM sdb_promotion WHERE pmt_id in('
                        .implode(',',$trading['pmt_o']['pmt_ids']).')';
            $this->db->exec($sSql);
            foreach($trading['pmt_o']['pmt_ids'] as $k=>$pmtId) {
                $sSql = 'UPDATE sdb_order_pmt SET pmt_amount='.floatval($trading['pmt_o']['pmt_money'][$k])
                        .' WHERE pmt_id='.intval($pmtId).' AND order_id='.$this->db->quote($data['order_id']);
                $this->db->exec($sSql);
            }
        }
        if ($trading['products']) {
            $pre_pmtOrder = array();
            foreach ($trading['products'] as $v) {
                if ($v['pmt_id']){
                    $pre_pmtOrder[$v['pmt_id']] += $v['price'] - $v['_pmt']['price'];
                }
            }
            $aPmtIds = array_keys($pre_pmtOrder);
            if(!empty($aPmtIds)){
                $sSql = 'SELECT pmt_id,pmt_describe FROM sdb_promotion WHERE pmt_id IN('.implode(',', $aPmtIds).')';
                $aPmtOrder = $this->db->select($sSql);
                foreach($aPmtOrder as $k=>$v) {
                    $v['pmt_amount'] = $pre_pmtOrder[$v['pmt_id']];
                    $v['order_id'] = $data['order_id'];

                    $rs = $this->db->query('select * from sdb_order_pmt where 0=1');
                    $sqlString = $this->db->GetInsertSQL($rs, $v);
                    $this->db->exec($sqlString);
                }
            }
        }

        //+积分处理------------------------------------------------------------
        $oMemberPoint = &$this->system->loadModel('trading/memberPoint');
        $oGift = &$this->system->loadModel('trading/gift');
        $aGiftData = array();
        if ($data['score_u']>=0) {
            if (!$oMemberPoint->payAllConsumePoint($data['member_id'],$data['order_id'])) {
                ;
            }else{
                //+赠品处理------------------------------------------------------------
                if (is_array($trading['gift_e']) && count($trading['gift_e'])) {
                    foreach($trading['gift_e'] as $giftId => $v) {
                        $giftId = $v['gift_id'];
                        $aGiftData[$giftId] = array(
                            'gift_id' => $giftId,
                            'name' => $v['name'],
                            'nums' => $v['nums'],
                            'point' => $v['point']);
                        if($this->freez_time()=='order'){
                            if (!$oGift->freezStock($v['gift_id'], $v['nums'])) {//兑换赠品缺货
                            ;}
                        }
                    }
                }
            }
        }
        if (is_array($trading['gift_p']) && count($trading['gift_p'])){
            foreach($trading['gift_p'] as $v) {
                $giftId = $v['gift_id'];
                if (isset($aGiftData[$giftId])) {
                    $aGiftData[$giftId]['nums'] += $v['nums'];
                }else {
                    $aGiftData[$giftId] = array(
                            'gift_id' => $giftId,
                            'name' => $v['name'],
                            'nums' => $v['nums'],
                            'point' => $v['point']);
                }
            }
        }
        if($aGiftData) {
            foreach($aGiftData as $item) {
                $oGift = &$this->system->loadModel('trading/gift');
                $item['order_id'] = $data['order_id'];
                $rs = $this->db->query('select * from sdb_gift_items where 0=1');
                $sqlString = $this->db->GetInsertSQL($rs, $item);
                $this->db->exec($sqlString);
            }
        }

        //+优惠券------------------------------------------------------------
        if (is_array($trading['coupon_u']) && !empty($trading['coupon_u'])) {
            $oCoupon = &$this->system->loadModel('trading/coupon');
            foreach ($trading['coupon_u'] as $code => $v) {
                $aTmp = $this->db->selectRow('select cpns_name from sdb_coupons where cpns_id='.intval($v['cpns_id']));
                $aData = array(
                    'order_id' => $data['order_id'],
                    'cpns_id' => $v['cpns_id'],
                    'memc_code' => $code,
                    'cpns_name' => $aTmp['cpns_name'],
                    'cpns_type' => $v['cpns_type']);
                $rs = $this->db->query('select * from sdb_coupons_u_items where 0=1');

                $sqlString = $this->db->GetInsertSQL($rs, $aData);
                $this->db->exec($sqlString);
                $oCoupon->applyMemberCoupon($v['cpns_id'], $code, $data['order_id'], $data['member_id']);
            }
        }

        if (is_array($trading['coupon_p']) && !empty($trading['coupon_p'])) {
            foreach ($trading['coupon_p'] as $code => $v) {
                $aData = array(
                    'order_id' => $data['order_id'],
                    'cpns_id' => $v['cpns_id'],
                    'cpns_name' => $v['cpns_name'],
                    'nums' => $v['nums']);
                $rs = $this->db->query('select * from sdb_coupons_p_items where 0=1');
                $sqlString = $this->db->GetInsertSQL($rs, $aData);
                $this->db->exec($sqlString);
            }
        }

        $data['is_tax'] = ($data['is_tax'] ? true : false);


        $this->fireEvent('create',$data,$data['member_id']);        //订单生成成功事件

        if($data['total_amount'] == 0){
            $pdata['order_id'] = $data['order_id'];
            $pdata['member_id'] = $data['member_id'];
            $pdata['money'] = 0;
            $this->payed($pdata);
        }
        return $data['order_id'];
    }

    function checkOrderStatus($act, &$aOrder){
        /**
        *    @params:
        *        @$aOrder['status']        :    订单状态
        *            @values
        *                active    :激活
        *        @$aOrder['ship_status']:订单发货状态
        *            @values:
        *                    0:未发货
        *                    4:已退货
        *        @$aOrder['pay_status']    :
        *            @values
        *                1:已支付
        *                2:处理中(在线支付)
        *                5:全额退款
        */
        switch($act){
            case 'pay':
                if($aOrder['status'] != 'active' || $aOrder['pay_status'] == '1' || $aOrder['pay_status'] == '2' || $aOrder['pay_status'] == '4' || $aOrder['pay_status'] == '5'){
                    return false;
                    exit;
                }
            break;
            case 'refund':
                if($aOrder['status'] != 'active' || $aOrder['pay_status'] == '0' || $aOrder['pay_status'] == '5'){
                    return false;
                    exit;
                }
            break;
            case 'delivery':
                if($aOrder['status'] != 'active' || $aOrder['ship_status'] == '1'){
                    return false;
                    exit;
                }
            break;
            case 'reship':
                if($aOrder['status'] != 'active' || $aOrder['ship_status'] == '0' || $aOrder['ship_status'] == '4'){
                    return false;
                    exit;
                }
            break;
            case 'cancel':
                if($aOrder['status'] != 'active' || $aOrder['pay_status'] > 0 || $aOrder['ship_status'] > 0){
                    return false;
                    exit;
                }
            break;
        }
        return true;
    }
    function update_last_modify($order_id){
        return $this->db->query('update sdb_orders set last_change_time='.time().' where order_id='.intval($order_id));
    }
    /**
     * payed
     * 订单支付，前后台支付都是调用这个方法，进行付款或后续发货动作
     *
     * @param mixed $money 实际支付金额(扣除支付所花费用)
     * @access public
     * @return void
     */
    function toPayed($aData, $createBill=false){
        $aOrder = $this->load($aData['order_id']);
        if(!$aOrder){
            $this->system->error(501);
            return false;
            exit;
        }
        if(!$this->checkOrderStatus('pay', $aOrder)){
            $this->setError(10001);
            trigger_error(__('订单状态锁定'),E_USER_ERROR);
            return false;
            exit;
        }

        /**
        *    @params
        *        @$nonPay    :此次未付费金额
        *        @$aOrder['amount']['total'] 订单总金额
        *        @$aOrder['amount']['payed'] 订单已付金额
        */
        $nonPay = $aOrder['amount']['total'] - $aOrder['amount']['payed'];
        if(isset($aData['money'])){    //从收款单提交进入
            if($aData['money'] > $nonPay || $aData['money'] <= 0){
                $this->setError(10001);
                trigger_error(__('支付总金额不在订单金额范围'),E_USER_ERROR);
                return false;
                exit;
            }
            $paymentId = $aData['payment'];
            $payMethod = $aData['payment'];
            $payMoney = $aData['money'];
        }else{    //未从收款单提交进入
            /**
            *    @branch:未填写付款数额按照全额处理
            */
            $paymentId = $aOrder['payment'];
            switch($aOrder['paytype']){
                case 'DEPOSIT':
                $aData['pay_type'] = 'deposit';
                break;
                case 'OFFLINE':
                $aData['pay_type'] = 'offline';
                break;
                default:
                $aData['pay_type'] = 'online';
                break;
            }
            $payMethod = $aOrder['paymethod'];
            $payMoney = $nonPay;
        }
        $oCur = &$this->system->loadModel('system/cur');
        $payMoney = $oCur->formatNumber($payMoney, false);
        if($aData['pay_type'] == 'deposit'){
            $oAdvance = &$this->system->loadModel("member/advance");
            if(!$oAdvance->checkAccount($aOrder['member_id'], $payMoney, $message)){
                trigger_error(__('支付失败：').$message,E_USER_ERROR);
                return false;
                exit;
            }
        }

        /**
        *    @params:
        *    values:$createBill是否生成单据
        */
        $payment = &$this->system->loadModel('trading/payment');
        if($createBill){
            //后台收款
            $payment->pay_type = $aData['pay_type'];    //订单付款 在线，线下，预付款
            $payment->op_id = intval($aData['opid']);
            $payment->order_id = $aData['order_id'];
            $payment->member_id = $aOrder['member_id'];
            $payment->currency = $aOrder['currency'];
            $payment->money = $payMoney;
            $payment->cur_money = $payMoney;    //后台手工支付的币别金额都取本位币
            $payment->pay_account = $aData['pay_account'];
            $payment->payment = intval($paymentId);
            $payment->paymethod = $payMethod;
            $payment->account = $aData['account'];
            $payment->bank = $aData['bank'];
            $payment->status = 'ready';

            $pay_id = $payment->toCreate();
            if(!$pay_id){
                $this->setError(10001);
                trigger_error(__('支付单不能正常生成'),E_USER_ERROR);
                return false;
                exit;
            }
        }else{
            //前台支付返回不需要生成支付单据.
            $pay_id = $aData['payment_id'];
        }
        /*
        * @function addLog():添加订单LOG
        */
        $o = &$this->system->loadModel('trading/payment');
        $aPay['memo'] = ($aData['memo'] ? $aData['memo'].'#' : '').__('后台').$aData['opname'].__('支付成功！');
        return $o->setPayStatus($pay_id, PAY_SUCCESS, $aPay);
    }

    /**
      *    @params:
      *    values:$data=array(
      *                     order_id
      *                     payment_id  //支付单号
      *                     pay_type    //支付单类型：（deposit预存款）
      *                     money       //支付金额(已折算本位币)
      *                     currency    //支付货币
      *                     member_id   //支付会员
      *                     paymethod   //支付方式名称
      *                     status      //支付前的支付单状态
      *                     pay_assure  //是否担保交易 true/false
      *                     pay_account //发邮件时的付款人
      *                         )
      */
    function payed($data, &$message){
        if(empty($data['order_id'])){
            $message .= '支付单：订单号{'.$info['payment_id'].'}没有对应订单号';
            return false;
        }
        $aOrder = $this->getFieldById($data['order_id'], array('total_amount','final_amount','payed','pay_status','ship_status','status','member_id','is_tax','ship_email','ship_mobile'));
        $aOrder['order_id'] = $data['order_id'];
        if($aOrder['pay_status'] == '0' || $aOrder['pay_status'] == '3' || ($aOrder['pay_status'] == '2' && !$data['pay_assure'])){    //如何是未支付或者部分支付或者支付中
            if($data['pay_type'] =='deposit' && ($aOrder['pay_status'] == '0' || $aOrder['pay_status'] == '3')){  //预存款付款
                $message .= '预存款支付：订单号{'.$data['order_id'].'}';
                $oAdvance = $this->system->loadModel("member/advance");
                if(!$oAdvance->deduct($data['member_id'], $data['money'], $message, $message, $data['payment_id'], $data['order_id'] ,$data['paymethod'] , '预存款支付')){
                    return false;
                }
            }
        }
        $aOrder['ship_email'] = $aOrder['ship_email'];
        $aOrder['ship_mobile'] = $aOrder['ship_mobile'];
        if($aOrder['final_amount'] - $aOrder['payed'] <= $data['money']){
            /**
            *    @branch:全额付款
            */
            $aOrder['pay_status']= ($data['pay_assure'] ? '2':'1');    //如果是担保交易则2，否则已支付1
            $aOrder['payed'] = $aOrder['final_amount'];
        }else{  //部分付款
            $aOrder['pay_status'] = '3';
            $aOrder['payed'] = $aOrder['payed'] + $data['money'];

/*            if($aData['pay_status'] == 1){
                $lastMoney = $nonPay - $payMoney;
                $this->addLog(__('更改订单金额:减少').$lastMoney);
                $aUpdate['pay_status'] = 1;
                $aUpdate['discount'] += $lastMoney;
            }else{
                $aUpdate['pay_status'] = 3;
            }*/
        }
        $aOrder['acttime'] = time();
        $aOrder['last_change_time'] = time();
        if(!$this->toEdit($data['order_id'], $aOrder)){
            $message .= __('更新订单失败');
            return false;
        }

        $this->addLog('订单'.$aOrder['order_id'].'付款'.($data['pay_assure'] ? '（担保交易）':'').$data['money'], $this->op_id?$this->op_id:null, $this->op_name?$this->op_name:null , '付款');
        if($aOrder['status'] != 'active'){  //死单被支付的情况
            return true;
        }

        if($aOrder['pay_status']=='1' || $aOrder['pay_status']=='2'){
            if($this->freez_time()=='pay'){
                $missProduct=array();
                $objCart = &$this->system->loadModel('trading/cart');
                $objGift = &$this->system->loadModel('trading/gift');
                $rs = $this->db->select('SELECT product_id,nums,name  FROM sdb_order_items  WHERE order_id = '.$aOrder['order_id'].' ');
                $rsG= $this->db->select('SELECT gift_id,nums  FROM sdb_gift_items   WHERE order_id = '.$aOrder['order_id'].' ');

                foreach($rs as $k=>$p){
                    if($p['nums']>=0){
                     $this->db->exec("UPDATE sdb_products SET freez = freez + ".intval($p['nums'])." WHERE product_id = ".intval($p['product_id']));
                    }
                     $this->db->exec("UPDATE sdb_products SET freez = ".intval($p['nums'])." WHERE product_id = ".intval($p['product_id'])." AND freez IS NULL");
                }
                 foreach($rsG as $key=>$val){
                     $objGift->freezStock($val['gift_id'],$val['nums']);
                }
            }
            if($this->system->getConf('system.auto_delivery')){ //检测付款前的订单状态,如果是刚付款立即发货
                $this->delivery($aOrder, false);
            }
        }

        if ($aOrder['pay_status'] == '1'){
            $aPara = $aOrder;
            $aOrder['money'] = $data['money'];
            $aOrder['pay_account'] = $data['pay_account'];

            if($data['pay_progress']!='PAY_PROGRESS'){
                 $this->toCoupon($aOrder);  //给优惠券
                 $this->toPoint($aOrder);  //给积分
                 $this->toExperience($aOrder);
            }

            $status = &$this->system->loadModel('system/status');
            if($data['order_id'] && ($aOrder['pay_status'] == '1' || $aOrder['pay_status'] == '2')){
                if($aOrder['ship_status'] == '1'){
                    $status->add('ORDER_SUCC');
                    $status->add('REVENUE', $aOrder['final_amount']);
                }else{
                    $status->count_order_to_dly();
                }
            }
            $status->count_order_to_pay();
            $s = $this->fireEvent('payed', $aOrder,$aOrder['member_id']);
        }

        return $aOrder['pay_status'];
    }

    function toCoupon($PARA) {
            $orderId = $PARA['order_id'];
            $sSql = 'select count(*) as count from sdb_member_coupon where memc_gen_orderid=\''.$orderId.'\'';
            $aData = $this->db->selectRow($sSql);
            //如果没有此订单没生成过优惠券,则生成.否则置之不理
            if ($aData['count']==0) {
                $aOrder = $this->getFieldById($orderId, array('member_id'));
                $memberId = $aOrder['member_id'];
                $oCoupon = $this->system->loadModel('trading/coupon');

                //赠送优惠券按
                $sSql = 'select cpns_id,nums from sdb_coupons_p_items where order_id=\''.$orderId.'\'';
                $c_p_items = $this->db->select($sSql);
                if ($c_p_items) {
                    foreach ($c_p_items as $items) {
                        $oCoupon->generateCoupon($items['cpns_id'], $memberId, $items['nums'], $orderId);
                    }
                }
            }
            //使用优惠券,挪到下单后就进行
        return true;
    }

    function toPoint(&$PARA){
        $orderId = $PARA['order_id'];
        $aMemberId = $this->getFieldById($orderId, array('member_id'));
        $memberId = intval($aMemberId['member_id']);
        if ($memberId) {
                $oMemberPoint = $this->system->loadModel('trading/memberPoint');
                if (!$oMemberPoint->payAllGetPoint($memberId, $orderId)) {
                    return false;
                }
        }
        return true;
    }
    function toExperience(&$PARA){
        $orderId = $PARA['order_id'];
        $aMemberId = $this->getFieldById($orderId, array('member_id'));
        $memberId = intval($aMemberId['member_id']);
        if ($memberId) {
                $oMemberPoint = $this->system->loadModel('trading/memberExperience');
                if (!$oMemberPoint->payAllGetExperience($memberId, $orderId)) {
                    return false;
                }
        }
        return true;
    }

    //退款操作
    function refund($aData){
        $aOrder = $this->load($aData['order_id']);
        if(!$aOrder){
            $this->system->error(501);
            return false;
            exit;
        }
        if(!$this->checkOrderStatus('refund', $aOrder)){
            $this->setError(10001);
            trigger_error(__('退款失败: 订单状态锁定'),E_USER_ERROR);
            return false;
            exit;
        }

        $payMoney = $aOrder['amount']['payed'] - $aOrder['amount']['cost_payment'];
        $aUpdate['pay_status']= '5';    //预设订单状态
        $aUpdate['payed'] = $aOrder['amount']['cost_payment'];    //预设订单支付金额
        if(isset($aData['money'])){    //从退款单提交进入
            if($aData['money'] > $payMoney || $aData['money'] <= 0){
                $this->setError(10001);
                trigger_error(__('退款金额不在订单已支付金额范围'),E_USER_ERROR);
                return false;
            }

            if($payMoney > $aData['money']){
                $aUpdate['pay_status'] = '4';
                $aUpdate['payed'] = $aOrder['amount']['payed'] - $aData['money'];
            }
            $paymentId = $aData['payment'];
            $payMethod = $aData['payment'];
            $payMoney = $aData['money'];
        }else{    //未从退款单提交进入
            $paymentId = $aOrder['payment'];
            $payMethod = __("手工");
            switch($aOrder['paytype']){
                case 'DEPOSIT':
                $aData['pay_type'] = 'deposit';
                break;
                case 'OFFLINE':
                $aData['pay_type'] = 'offline';
                break;
                default:
                $aData['pay_type'] = 'online';
                break;
            }
        }

        if($aData['pay_type'] == 'deposit'){
            $oAdvance = &$this->system->loadModel("member/advance");
            if(!$oAdvance->checkAccount($aOrder['member_id'], 0, $message)){
                trigger_error(__('支付失败：').$message,E_USER_ERROR);
                return false;
                exit;
            }
        }

        $aRefund['money'] = $payMoney;
        $aRefund['order_id'] = $aData['order_id'];
        $aRefund['send_op_id'] = intval($aData['opid']);
        $aRefund['pay_type'] = $aData['pay_type'];
        $aRefund['member_id'] = $aOrder['member_id'];
        $aRefund['account'] = $aData['account'];
        $aRefund['pay_account'] = $aData['pay_account'];
        $aRefund['bank'] = $aData['bank'];
        $aRefund['title'] = 'title';
        $aRefund['currency'] = $aOrder['currency'];
        $aRefund['payment'] = $paymentId;
        $aRefund['paymethod'] = $payMethod;
        $aRefund['status'] = 'sent';
        $aRefund['memo'] = ($aData['memo'] ? $aData['memo'].'#' : '').__('管理员后台退款产生');

        $oRefund = &$this->system->loadModel('trading/refund');
        $refund_id = $oRefund->create($aRefund);
        if(!$refund_id){
            $this->setError(10001);
            trigger_error(__('退款单不能正常生成'),E_USER_ERROR);
            return false;
        }

        $detailRefund = $oRefund->detail($refund_id);
        $aRefund['paymethod'] = $detailRefund['paymethod'];

        $this->addLog(__('订单退款').$payMoney, $this->op_id?$this->op_id:null, $this->op_name?$this->op_name:null , __('退款'));

        $aUpdate['acttime'] = time();
        $aUpdate['last_change_time'] = time();
        /**
        *    @function    toEdit():编辑订单
        */
        if(!$this->toEdit($aData['order_id'], $aUpdate)){
            $this->setError(10001);
            trigger_error(__('更新订单状态失败'),E_USER_ERROR);
            return false;
        }
        $freez_status = $this->freez_time();
        if($freez_status == 'pay' || $freez_status == 'order'){
            if($aUpdate['pay_status']=='5' && $aOrder['ship_status'] == '0'){
                $this->toUnfreez($aData['order_id']);
                $objGift = &$this->system->loadModel('trading/gift');
                $rsG= $this->db->select('SELECT gift_id,nums  FROM sdb_gift_items   WHERE order_id = '.$aData['order_id'].' ');
                foreach($rsG as $key=>$val){
                    $objGift->unFreezStock($val['gift_id'],$val['nums']);
                }
            }
        }

        if($aData['pay_type'] =='deposit'){    //预存款付款
            $message .= __('预存款退款：#O{').$aData['order_id'].'}#';
            if(!$oAdvance->add($aOrder['member_id'], $payMoney, $message, $message, $refund_id, $aData['order_id'] ,$aRefund['paymethod'] ,__('预存款退款'))){
                return false;
            }
        }

        $aPara['pay_status'] = $aUpdate['pay_status'];
        $aPara['order_id'] = $aData['order_id'];
        $aPara['return_score'] = $aData['return_score'];
        $aPara['money'] = $aData['money'];
        $this->toReturnPoint($aPara);

        $eventData['order_id'] = $aData['order_id'];
        $eventData['total_amount'] = $aOrder['amount']['total'];
        $eventData['is_tax'] = $aOrder['is_tax'];
        $eventData['member_id'] = $aOrder['member_id'];
        $this->fireEvent('refund', $eventData,$aOrder['member_id']);
        return $aPara['pay_status'];
    }

    function toReturnPoint($PARA){
        $orderId = $PARA['order_id'];
        $aMemberId = $this->getFieldById($orderId, array('member_id'));
        $memberId = intval($aMemberId['member_id']);
        if ($memberId) {
            //$PARA['pay_status'] 4为部分到款,5为全额退款
            $oMemberPoint = $this->system->loadModel('trading/memberPoint');
            $oMemberExperience = $this->system->loadModel('trading/memberExperience');
            if (isset($PARA['return_score'])){
                $oMemberPoint->refundPartGetPoint($memberId, $orderId, (0-$PARA['return_score']));
                $oMemberExperience->refundPartGetExperience($memberId, $orderId, (0-$PARA['money']));
            }else{
                $oMemberPoint->refundAllGetPoint($memberId, $orderId);
                $oMemberExperience->refundAllGetExperience($memberId, $orderId);
            }
        }
        return true;
    }

    /**
     * 有发货函数的不同类型商品生成不同的发货单
     * 发货不成功的商品生成失败的发货单
     * 订单会转移到非活动订单
     *
     * @access public
     * @return void
     */
    function delivery($aData, $manual=true){
        $aOrder = $this->load($aData['order_id']);
        if(!$aOrder){
            $this->system->error(501);
            return false;
        }
        if(!$this->checkOrderStatus('delivery', $aOrder)){
            $this->setError(10001);
            trigger_error(__('发货失败: 订单状态锁定'),E_USER_ERROR);
            return false;
        }

        //读取订单明细
        $rows = $this->db->select('SELECT i.item_id,i.addon,i.minfo,i.nums,i.sendnum,i.product_id,i.bn,i.name,i.is_type,
                    t.type_id,t.setting,t.schema_id,t.is_physical,t.dly_func FROM sdb_order_items i
                    LEFT JOIN sdb_goods_type t ON t.type_id = i.type_id
                    WHERE i.order_id='.$aData['order_id']);
        /**
        *    @aData['send'][id]:            发货数量
        *    @sdb_order_items.addon    :    序列化数量
        *    @error tito check
        *        @note:检查$aData['send'][]数量
        *        @$send = abs($aData['send']);
        */
        if(isset($aData['send'])){
            /**
            *    @function:    经过发货单发货
            *    @params:    $aData['send']为发货货品数组
            *
            */
            if($aData['logi_id']){

                $oCorp = &$this->system->loadModel('trading/delivery');
                $aCorp = $oCorp->getCorpById($aData['logi_id']);

            }
            if(constant('SAAS_MODE')){
                $date  = getdeliverycorplist();
                $aCorp = $date[$aData['logi_id']-1];
                if($aData['other_name']!=""&&isset($aData['other_name'])){
                    $aCorp['name'] = $aData['other_name'];
                }
            }

            $delivery = array(
                'money' => floatval($aData['money']) + floatval($aData['cost_protect']),
                'is_protect' => $aData['is_protect'],
                'delivery' => $aData['delivery'],
                'logi_id' => $aData['logi_id'],
                'logi_no' => $aData['logi_no'],
                'logi_name' => $aCorp['name'],
                'ship_name' => $aData['ship_name'],
                'ship_area' => $aData['ship_area'],
                'ship_addr' => $aData['ship_addr'],
                'ship_zip' => $aData['ship_zip'],
                'ship_tel' => $aData['ship_tel'],
                'ship_mobile' => $aData['ship_mobile'],
                'ship_email' => $aData['ship_email'],
                'memo' => $aData['memo'],
                'gift_send' => $aData['gift_send']
            );
        }else{
            /** 未经过发货单发货
            *    @params:
            *        @order_id                :订单号
            *        @member_id                :会员号
            *        @shipping['method']        :发货方式
            */
            $aRet = $this->getGiftItemList($aData['order_id']);
            $aGiftItems = array();
            foreach($aRet as $aRows){
                $aGiftItems[$aRows['gift_id']] = $aRows['nums'] - $aRows['sendnum'];
            }
            $delivery = array(
                'money' => $aOrder['cost_freight']+$aOrder['cost_protect'],
                'is_protect' => $aOrder['is_protect'],
                'delivery' => $aOrder['shipping']['method'],
                'logi_id' => '',
                'logi_no' => $aData['logi_no'],
                'logi_name' => $aData['logi_name'],
                'ship_name' => $aOrder['receiver']['name'],
                'ship_area' => $aOrder['receiver']['area'],
                'ship_addr' => $aOrder['receiver']['addr'],
                'ship_zip' => $aOrder['receiver']['zip'],
                'ship_tel' => $aOrder['receiver']['tel'],
                'ship_mobile' => $aOrder['receiver']['mobile'],
                'ship_email' => $aOrder['receiver']['email'],
                'gift_send' => $aGiftItems
            );
        }
        /**
        *    @function:    订单明细赋值,读取订单详细表sdb_order_items的addon字段
        *    @params:
        *        @$dinfo['addon']:        订单序列化字段，存放订单物品等资料
        *        @$delivery['op_name']:    订单操作人员
        *        @$aUpdate['ship_status']:订单发货状态 1为发货状态
        */
        $delivery['order_id'] = $aData['order_id'];
        $delivery['member_id'] = $aOrder['member_id'];
        $delivery['t_begin'] = time();
        $delivery['op_name'] = $aData['opname'];
        $delivery['type'] = 'delivery';
        $delivery['status'] = 'progress';

        //遍历订单明细
        $aBill = array();
        $nonGoods = 0;    //是否完全发货商品标识
        foreach($rows as $dinfo){
            $dinfo['addon'] = unserialize($dinfo['addon']);
            /**
            *    @$aData['send'][$dinfo['product_id']:需要发送的商品数量
            */
            if(!isset($aData['send']) || (isset($aData['send'][$dinfo['item_id']]) && floor($aData['send'][$dinfo['item_id']]) > 0)){
                if($aData['send'][$dinfo['item_id']] > $dinfo['nums'] - $dinfo['sendnum']){
                    $message .= __('商品：').$dinfo['name'].__('发货超出购买量');
                    $this->setError(10001);
                    trigger_error($message, E_USER_ERROR);
                    return false;
                }

                if(!isset($aData['send']) || $aData['send'][$dinfo['item_id']] == $dinfo['nums']-$dinfo['sendnum']){
//                    $aUpdate['items'][$dinfo['product_id']] = $dinfo['nums'];
                    $dinfo['send'] = $dinfo['nums']-$dinfo['sendnum'];    //本次发货数量
                }else{
                    $nonGoods = 1;
//                    $aUpdate['items'][$dinfo['product_id']] = $dinfo['sendnum'] + $aData['send'][$dinfo['product_id']];
                    $dinfo['send'] = floor($aData['send'][$dinfo['item_id']]);
                }

                /**
                    @params:
                        @$dinfo['is_physical']是否为实体商品
                            @value:
                                1:实体商品
                        @$dinfo['dly_func']????
                */
                if($dinfo['dly_func'] == 1){
                    $aBill['func'][$dinfo['schema_id']][] = $dinfo;
                }else{
                    if($dinfo['is_physical']==1 || empty($dinfo['type_id'])){    //如果读取类型id为空，说明当前商品一件被删除（或捆绑商品），默认为实体商品发货
                        $dinfo['is_physical'] = true;
                        $aBill['nofunc'][] = $dinfo;
                    }else{
                        $aBill['error'][] = $dinfo;    //如果虚拟商品没有发货函数，则需要建立失败发货单
                    }
                }
            }else{
                if($dinfo['nums'] > $dinfo['sendnum']) $nonGoods = 1;
            }
        }
        if(count($rows) && count($aBill) == 0) $nonGoods = -1;    //商品没有发货

        $objShipping = &$this->system->loadModel('trading/delivery');
        $schema = &$this->system->loadModel('goods/schema');
        if($aBill['func'])    //有发货函数的实体虚拟商品
            foreach($aBill['func'] as $schema_id => $rows){
                $delivery['delivery_id'] = $objShipping->getNewNumber($delivery['type']);
                $delivery['memo'] = $aData['memo'];
                $iLoop = 0;

                foreach($rows as $dinfo){
                    $setting = unserialize($dinfo['setting']);
                    ob_start();
                    //执行发货函数
                    $minfo = array();
                    if($mData = unserialize($dinfo['minfo'])){
                        foreach($mData as $minfo_key=>$minfo_row){
                            $minfo[$minfo_key] = $minfo_row['value'];
                        }
                    }

                    $setting['idata'] = $dinfo['addon']['idata'];
                    unset($setting['data']);
                    $result = $schema->delivery($dinfo['schema_id'],$minfo,$setting,$dinfo['nums'],$logs);
                    $output = ob_get_clean();

                    $delivery['memo'] .= $logs."\n".$output;
                    if(!$result){    //发货失败
                        $aBill['error'][] = $dinfo;    //todo 是否程序终止有待讨论（现在是继续执行）
                    }else{
                        $item = array(
                                    'order_item_id' => $dinfo['item_id'],
                                    'order_id' => $aData['order_id'],
                                    'delivery_id' => $delivery['delivery_id'],
                                    'item_type' => ($dinfo['is_type']=='pkg' ? $dinfo['is_type'] : 'goods'),
                                    'product_id' => $dinfo['product_id'],
                                    'product_bn' => $dinfo['bn'],
                                    'product_name' => $dinfo['name'].$dinfo['addon']['adjname'],
                                    'adjunct' => $dinfo['addon']['adjinfo'],
                                    'number' => $dinfo['send'] );
                        if(!$objShipping->toInsertItem($item, $dinfo['is_physical'], $delivery['type'], $delivery['status'])){
                            $aBill['error'][] = $dinfo;    //todo 是否程序终止有待讨论（现在是继续执行）
                        }else{
                            $iLoop++;
                        }
                    }
                }
                if($iLoop > 0){
                    if(!$objShipping->toCreate($delivery)){
                        $this->setError(10001);
                        trigger_error(__('配送单据生成失败'), E_USER_ERROR);
                        return false;
                    }
                }
            }

        if($aBill['nofunc']){        //实体商品
            if($manual || (!$manual && $this->system->getConf('system.auto_delivery_physical') != 'no')){
                if(!$manual){
                    $delivery['status'] = ($this->system->getConf('system.auto_delivery_physical')=='yes' ? 'progress' : 'ready');
                }
                $iLoop = 0;
                $delivery['delivery_id'] = $objShipping->getNewNumber($delivery['type']);
                foreach($aBill['nofunc'] as $dinfo){
                    $item = array(
                                'order_item_id' => $dinfo['item_id'],
                                'order_id' => $aData['order_id'],
                                'delivery_id' => $delivery['delivery_id'],
                                'item_type' => ($dinfo['is_type']=='pkg' ? $dinfo['is_type'] : 'goods'),
                                'product_id' => $dinfo['product_id'],
                                'product_bn' => $dinfo['bn'],
                                'product_name' => $dinfo['name'].$dinfo['addon']['adjname'],
                                'adjunct' => $dinfo['addon']['adjinfo'],
                                'number' => $dinfo['send'] );
                    if(!$objShipping->toInsertItem($item, $dinfo['is_physical'], $delivery['type'], $delivery['status'])){
                        $aBill['error'][] = $dinfo;    //todo 是否程序终止有待讨论（现在是继续执行）
                    }else{
                        $iLoop++;
                    }
                }
            }
            if($iLoop > 0){
                if(!$objShipping->toCreate($delivery)){
                    $this->setError(10001);
                    trigger_error(__('配送单据生成失败'), E_USER_ERROR);
                    return false;
                }
                $eventId = $$delivery['delivery_id'];
            }
        }

        if($aBill['error']){
            $nonGoods = 1;
            $iLoop = 0;
            $delivery['delivery_id'] = $objShipping->getNewNumber($delivery['type']);
            $delivery['status'] = 'failed';
            $delivery['money'] = 0;
            foreach($aBill['error'] as $dinfo){
                $item = array(
                            'order_item_id' => $dinfo['item_id'],
                            'order_id' => $aData['order_id'],
                            'delivery_id' => $delivery['delivery_id'],
                            'item_type' => ($dinfo['is_type']=='pkg' ? $dinfo['is_type'] : 'goods'),
                            'product_id' => $dinfo['product_id'],
                            'product_bn' => $dinfo['bn'],
                            'product_name' => $dinfo['name'].$dinfo['addon']['adjname'],
                            'adjunct' => $dinfo['addon']['adjinfo'],
                            'number' => $dinfo['send'] );
                $objShipping->toInsertItem($item, $dinfo['is_physical'], $delivery['type'], $delivery['status']);
                $iLoop++;
            }
            if($iLoop > 0){
                if(!$objShipping->toCreate($delivery)){
                    $this->setError(10001);
                    trigger_error(__('配送单据生成失败'), E_USER_ERROR);
                    return false;
                }
            }
        }

        $aPara['order_id'] = $aData['order_id'];
        $aPara['message'] = array();
        $aPara['ship_status'] = '1';
        $aPara['ship_status_o'] = array();
        $aPara['delivery'] = $delivery;
        $aPara['delivery']['delivery_id'] = $eventId;
        $aPara['ship_billno'] = $aData['logi_no'];
        $aPara['ship_corp'] = $aCorp['logi_name'];
        $this->toDeliveryGift($aPara);

        if($nonGoods == -1 && $aPara['ship_status_o'][0] == -1){
            $this->setError(10001);
            trigger_error(__('没有任何商品发货'), E_USER_ERROR);
            $this->addLog(__('发货失败,没有发送任何商品'), $this->op_id?$this->op_id:null, $this->op_name?$this->op_name:null , __('发货'));
            return false;
        }else{
            //没有完全发货
            if($nonGoods) $aUpdate['ship_status'] = '2';
            else $aUpdate['ship_status'] = '1';
            $aUpdate['order_id'] = $aData['order_id'];
            $aUpdate['ship_status'] = max($aUpdate['ship_status'], $aPara['ship_status_o'][0]);
            $this->setShipStatus($aUpdate);
            if(constant('SAAS_MODE')){
                include_once('delivercorp.php');
                $tmpdate =  getdeliverycorplist();
                $key = delivercorp_index($aCorp['name']);
                $aCorp['website'] = $tmpdate[$key]['query_interface'];
            }

            $status = &$this->system->loadModel('system/status');
            if($aData['order_id'] && $aUpdate['ship_status'] == '1'){
                $aPayStatus = $this->getFieldById($aData['order_id'], array('pay_status'));
                if($aPayStatus['pay_status'] == '1' || $aPayStatus['pay_status'] == '2'){
                    $status->add('ORDER_SUCC');
                    $status->add('REVENUE', $aOrder['amount']['total']);
                    $status->count_order_to_dly();
                }
            }
            $status->count_order_new();

            $aUpdate['total_amount'] = $aOrder['amount']['total'];
            $aUpdate['is_tax'] = $aOrder['is_tax'];
            $aUpdate['member_id'] = $aOrder['member_id'];
            $aUpdate['delivery'] = $delivery;
            $aUpdate['ship_billno'] = $delivery['logi_no'];
            $aUpdate['ship_corp'] = $delivery['logi_name'];
            $this->fireEvent('shipping', $aUpdate, $aOrder['member_id']);

            //取得发货的具体信息，add by hujianxin
            $message_part1 = "";
            $message = "";

            $ship_status = $aUpdate['ship_status'];

            if($ship_status == '1'){   //全部发货
                $message_part1 = "发货完成";
            }else if($ship_status == '2'){    //部分发货
                $message_part1 = "已发货";
            }
            $message = "订单<!--order_id=".$aData['order_id']."&delivery_id=".$delivery['delivery_id']."&ship_status=".$ship_status."-->".$message_part1;

            $cilent = &$this->system->loadModel('service/apiclient');
            $cilent->url = 'http://sds.ecos.shopex.cn/api.php';
            $cilent->key = '371e6dceb2c34cdfb489b8537477ee1c';
            $deilvery_cen_list = $cilent->native_svc("service.get_logistics");
            if($deilvery_cen_list['result']=='succ'){
                $return_cen_html = $this->system->call("get_cen_deli",$deilvery_cen_list['result_msg'],$aCorp['type'],$delivery['logi_no']);
                if(!$return_cen_html){
                    $return_cen_html =__('，物流公司：<a class="lnk" href="').$aCorp['website'].'" target="_blank">'.$aCorp['name'].__('</a>（可点击进入物流公司网站跟踪配送），物流单号：').$delivery['logi_no'];
                }
            }else{
                $return_cen_html =__('，物流公司：<a class="lnk" href="').$aCorp['website'].'" target="_blank">'.$aCorp['name'].__('</a>（可点击进入物流公司网站跟踪配送），物流单号：').$delivery['logi_no'];
            }
            $this->addLog($message.($delivery['logi_no'] ?$return_cen_html: ''), $this->op_id?$this->op_id:null, $this->op_name?$this->op_name:null , __('发货'));
            return true;
        }
    }

    function toDeliveryGift(&$PARA) {
        $orderId = $PARA['order_id'];
        $oGift = $this->system->loadModel('trading/gift');
        $PARA['gift_send'] = &$PARA['delivery']['gift_send'];

        if (is_array($PARA['gift_send'])) {
            $aOrderItems = $oGift->getOrderItemsList($orderId, array_keys($PARA['gift_send']));
            $flowMark = true;
        } else {
            $aOrderItems = $oGift->getOrderItemsList($orderId);
            $flowMark = false;
        }

        $nTotalSendNum = 0;
        $i = 0;
        $sError = '';

        //判断库存是否全
        if ($aOrderItems){
            foreach($aOrderItems as $aItem) {
                $sendNum = ($flowMark ? intval($PARA['gift_send'][$aItem['gift_id']]) : ($aItem['nums'] - $aItem['send_num']));
                if ($sendNum>0) {
                    if (!$oGift->checkStock($aItem['gift_id'], 0)) {
                        $sError = ','.$aItem['name'];
                    }else{
                        $nTotalSendNum += $sendNum;
                        $consignItems[$i] = array(
                                'order_id'=>$orderId,
                                'item_type'=>'gift',
                                'product_id'=>$aItem['gift_id'],
                                'product_bn'=>'',
                                'product_name'=>$aItem['name'],
                                'number'=>$sendNum
                        );
                        $i++;
                    }
                }
            }
        }

        if ($nTotalSendNum > 0) {
            $objShipping = $this->system->loadModel('trading/delivery');
            $oGift = $this->system->loadModel('trading/gift');
            if ($PARA['delivery']['delivery_id']) {
                $deliveryId = $PARA['delivery']['delivery_id'];
            }else{
                $deliveryId = $objShipping->toCreate($PARA['delivery']);
            }

            foreach($consignItems as $aItem) {
                $aItem['delivery_id'] = $deliveryId;   //对应主表ID
                $itemId = $objShipping->toInsertItem($aItem);
                $oGift->toConsign($orderId, $aItem['product_id'], $aItem['number']);
            }
        }
        if (!empty($sError)) {
            array_push($PARA['message'], $sError);
            array_push($PARA['ship_status_o'], '2');//部分发货
        }else{
            if($i > 0) array_push($PARA['ship_status_o'], 1);//全部发货
            else array_push($PARA['ship_status_o'], -1);//没有赠品发货
        }

        return true;
    }

    function setShipStatus($aData){
        $aData['acttime'] = time();
        $aData['last_change_time'] = time();
        $rs = $this->db->query("SELECT * FROM sdb_orders where order_id=".$aData['order_id']);
        $sql = $this->db->getUpdateSql($rs,$aData);
        if($sql) $this->db->exec($sql);
        return true;
    }

    //订单退货
    function toReship($aData,&$message){
        /**
        *    @function:加载订单信息
        *    @params:
        *        @$aData['order_id']:订单编号
        */
        $aOrder = $this->load($aData['order_id']);
        if(!$aOrder){
            $this->system->error(501);
            return false;
            exit;
        }
        if(!$this->checkOrderStatus('reship', $aOrder)){
            $this->setError(10001);
            trigger_error(__('订单状态锁定'),E_USER_ERROR);
            return false;
            exit;
        }

        $rows = $this->db->select('SELECT i.item_id,i.addon,i.minfo,i.nums,i.sendnum,i.product_id,i.bn,i.name,i.is_type,
                    t.type_id,t.setting,t.schema_id,t.is_physical,t.ret_func FROM sdb_order_items i
                    LEFT JOIN sdb_goods_type t ON t.type_id = i.type_id
                    WHERE i.order_id='.$aData['order_id']);
        $schema = &$this->system->loadModel('goods/schema');

        /**
        *    @params
        *    @$aData['send']:退货物品
        */
        if(isset($aData['send'])){
            if($aData['logi_id']){
                $oCorp = &$this->system->loadModel('trading/delivery');
                $aCorp = $oCorp->getCorpById($aData['logi_id']);
            }
            if(constant('SAAS_MODE')){
                $date  = getdeliverycorplist();
                $aCorp = $date[$aData['logi_id']-1];
                if($aData['other_name']!=""&&isset($aData['other_name'])){
                    $aCorp['name'] = $aData['other_name'];
                }
            }
            $delivery = array(
                'money' => $aData['money'],
                'is_protect' => $aData['is_protect'],
                'delivery' => $aData['delivery'],
                'logi_id' => $aData['logi_id'],
                'logi_no' => $aData['logi_no'],
                'logi_name' => $aCorp['name'],
                'ship_name' => $aData['ship_name'],
                'ship_area' => $aData['ship_area'],
                'ship_addr' => $aData['ship_addr'],
                'ship_zip' => $aData['ship_zip'],
                'ship_tel' => $aData['ship_tel'],
                'ship_mobile' => $aData['ship_mobile'],
                'ship_email' => $aData['ship_email'],
                'memo' => $aData['reason'].$aData['memo'],
            );
        }else{
            $delivery = array(
                'money' => 0,
                'is_protect' => 'false',
                'delivery' => $aOrder['shipping']['method'],
                'logi_id' => '',
                'logi_no' => '',
                'ship_name' => $aOrder['receiver']['name'],
                'ship_area' => $aOrder['receiver']['area'],
                'ship_addr' => $aOrder['receiver']['addr'],
                'ship_zip' => $aOrder['receiver']['zip'],
                'ship_tel' => $aOrder['receiver']['tel'],
                'ship_mobile' => $aOrder['receiver']['mobile'],
                'ship_email' => $aOrder['receiver']['email'],
            );
        }
        /**
        *    @params:
        *        @$delivery['type']            :    发送类型
        *        @$delivery['status']        :    发送状态
        *        @$aUpdate['ship_status']    :
        *            @values:
        ×                4:全部退货
        *
        */
        $delivery['order_id'] = $aData['order_id'];
        $delivery['member_id'] = $aOrder['member_id'];
        $delivery['t_begin'] = time();
        $delivery['op_name'] = $aData['opname'];
        $delivery['type'] = 'return';
        $delivery['status'] = 'progress';

        //遍历订单明细
        $aBill = array();
        $nonGoods = 0;    //是否完全退货商品标识
        foreach($rows as $dinfo){    //订单退货明细
            $dinfo['addon'] = unserialize($dinfo['addon']);
            if(!isset($aData['send']) || (isset($aData['send'][$dinfo['item_id']]) && floor($aData['send'][$dinfo['item_id']]) > 0)){
                //退货数量超出发货数量，则记录出错信息
                if($aData['send'][$dinfo['item_id']] > $dinfo['sendnum']){
                    $message .= __('商品：').$dinfo['name'].__('退货量超出已发货量');
                    $this->setError(10001);
                    trigger_error($message, E_USER_ERROR);
                    return false;
                }

                if(!isset($aData['send']) || $aData['send'][$dinfo['item_id']] == $dinfo['sendnum']){
                    $dinfo['send'] = $dinfo['sendnum'];    //本次退货数量
//                    $aUpdate['items'][$dinfo['product_id']] = 0;
                }else{
                    $nonGoods = 1;
                    $dinfo['send'] = floor($aData['send'][$dinfo['item_id']]);
//                    $aUpdate['items'][$dinfo['product_id']] = $dinfo['sendnum'] - $aData['send'][$dinfo['product_id']];
                }

                if($dinfo['ret_func'] == 1){
                    $aBill['func'][$dinfo['schema_id']][] = $dinfo;
                }else{
                    if($dinfo['is_physical']==1 || empty($dinfo['type_id'])){
                        $dinfo['is_physical'] = true;
                        $aBill['nofunc'][] = $dinfo;
                    }else{
                        $aBill['error'][] = $dinfo;    //如果虚拟商品没有退货函数，则需要建立失败退货单
                    }
                }
            }else{
                if($dinfo['sendnum'] > $aData['send'][$dinfo['item_id']]) $nonGoods = 1;
            }
        }

        if(count($rows) && count($aBill) == 0){   //商品没有发货
            $this->setError(10001);
            trigger_error(__('没有任何商品退货'), E_USER_ERROR);
            $this->addLog(__('退货失败,没有发送任何商品'), $this->op_id?$this->op_id:null, $this->op_name?$this->op_name:null , __('退货'));
            return false;
        }

        $objShipping = &$this->system->loadModel('trading/delivery');
        $schema = &$this->system->loadModel('goods/schema');
        if($aBill['func'])    //有发货函数的实体虚拟商品
            foreach($aBill['func'] as $schema_id => $rows){
                $delivery['delivery_id'] = $objShipping->getNewNumber($delivery['type']);
                $delivery['memo'] = $aData['memo'];
                $iLoop = 0;
                foreach($rows as $dinfo){
                    ob_start();
                    $result = $schema->toreturn($dinfo['schema_id'],unserialize($dinfo['minfo']),$dinfo['addon'],$dinfo['nums'],$logs);
                    $output = ob_get_clean();
                    $delivery['memo'] = $logs."\n".$output;
                    if(!$result){
                        $aBill['error'][] = $dinfo;    //todo 是否程序终止有待讨论（现在是继续执行）
                    }else{
                        $item = array(
                                    'order_item_id' => $dinfo['item_id'],
                                    'order_id' => $aData['order_id'],
                                    'delivery_id' => $delivery['delivery_id'],
                                    'item_type' => ($dinfo['is_type']=='pkg' ? $dinfo['is_type'] : 'goods'),
                                    'product_id' => $dinfo['product_id'],
                                    'product_bn' => $dinfo['bn'],
                                    'product_name' => $dinfo['name'].$dinfo['addon']['adjname'],
                                    'adjunct' => $dinfo['addon']['adjinfo'],
                                    'number' => $dinfo['send'] );
                        if(!$objShipping->toInsertItem($item, $dinfo['is_physical'], $delivery['type'], $delivery['status'])){
                            $aBill['error'][] = $dinfo;    //todo 是否程序终止有待讨论（现在是继续执行）
                        }else{
                            $iLoop++;
                        }
                    }
                }
                if($iLoop > 0){
                    if(!$objShipping->toCreate($delivery)){
                        $this->setError(10001);
                        trigger_error(__('配送单据生成失败'), E_USER_ERROR);
                        return false;
                    }
                }
            }

        if($aBill['nofunc']){        //实体商品
            $iLoop = 0;
            $delivery['delivery_id'] = $objShipping->getNewNumber($delivery['type']);
            foreach($aBill['nofunc'] as $dinfo){
                $item = array(
                            'order_item_id' => $dinfo['item_id'],
                            'order_id' => $aData['order_id'],
                            'delivery_id' => $delivery['delivery_id'],
                            'item_type' => ($dinfo['is_type']=='pkg' ? $dinfo['is_type'] : 'goods'),
                            'product_id' => $dinfo['product_id'],
                            'product_bn' => $dinfo['bn'],
                            'product_name' => $dinfo['name'].$dinfo['addon']['adjname'],
                            'adjunct' => $dinfo['addon']['adjinfo'],
                            'number' => $dinfo['send'] );
                if(!$objShipping->toInsertItem($item, $dinfo['is_physical'], $delivery['type'], $delivery['status'])){
                    $aBill['error'][] = $dinfo;    //todo 是否程序终止有待讨论（现在是继续执行）
                }else{
                    $iLoop++;
                }
            }
            if($iLoop > 0){
                if(!$objShipping->toCreate($delivery)){
                    $this->setError(10001);
                    trigger_error(__('配送单据生成失败'), E_USER_ERROR);
                    return false;
                }
            }
        }

        if($aBill['error']){
            $nonGoods = 1;
            $iLoop = 0;
            $delivery['delivery_id'] = $objShipping->getNewNumber($delivery['type']);
            $delivery['status'] = 'failed';
            $delivery['money'] = 0;
            foreach($aBill['error'] as $dinfo){
                $item = array(
                            'order_item_id' => $dinfo['item_id'],
                            'order_id' => $aData['order_id'],
                            'delivery_id' => $delivery['delivery_id'],
                            'item_type' => ($dinfo['is_type']=='pkg' ? $dinfo['is_type'] : 'goods'),
                            'product_id' => $dinfo['product_id'],
                            'product_bn' => $dinfo['bn'],
                            'product_name' => $dinfo['name'].$dinfo['addon']['adjname'],
                            'adjunct' => $dinfo['addon']['adjinfo'],
                            'number' => $dinfo['send'] );
                $objShipping->toInsertItem($item, $dinfo['is_physical'], $delivery['type'], $delivery['status']);
                $iLoop++;
            }
            if($iLoop > 0){
                $objShipping->toCreate($delivery);
            }
        }

        $aPara['order_id'] = $aData['order_id'];
        $aPara['message'] = array();
        $aPara['ship_status'] = '4';

        if($nonGoods) $aUpdate['ship_status'] = '3';
        else $aUpdate['ship_status'] = '4';
        $aUpdate['order_id'] = $aData['order_id'];
        $this->setShipStatus($aUpdate);

        $eventData['order_id'] = $aData['order_id'];
        $eventData['total_amount'] = $aOrder['amount']['total'];
        $eventData['is_tax'] = $aOrder['is_tax'];
        $eventData['member_id'] = $aOrder['member_id'];

        $this->fireEvent('returned', $eventData, $aOrder['member_id']);

        //取得退货的具体信息，add by hujianxin
        $message_part1 = "";
        $message = "";

        $ship_status = $aUpdate['ship_status'];

        if($ship_status == '4'){   //全部退货
            $message_part1 = "退货完成";
        }else if($ship_status == '3'){    //部分退货
            $message_part1 = "已退货";
        }

        $message = "订单<!--order_id=".$aData['order_id']."&delivery_id=".$delivery['delivery_id']."&ship_status=".$ship_status."-->".$message_part1;

        $this->addLog($message, $this->op_id?$this->op_id:null, $this->op_name?$this->op_name:null , __('退货'));

        return true;
    }

    //订单确认
    function toConfirm($orderid,$op_id=null){
        $sqlString = "UPDATE sdb_orders SET confirm = 'Y',last_change_time='".time()."', acttime='".time()."' WHERE order_id = ".$this->db->quote($orderid);
        $this->addLog(__('订单确认'), $this->op_id?$this->op_id:null, $this->op_name?$this->op_name:null , __('确认'));
        if($this->db->exec($sqlString)){
            $this->load($orderid);

            $eventData['order_id'] = $orderid;
            $eventData['total_amount'] = $this->_info['amount']['total'];
            $eventData['is_tax'] = $this->_info['is_tax'];
            $eventData['member_id'] = $this->_info['member_id'];
            $this->fireEvent('confirm', $eventData);
            return $this->_info;
        }
    }

    //订单归档
    function toArchive($orderid){
        $aRet = $this->getFieldById($orderid, array('status', 'member_id', 'pay_status', 'ship_status', 'is_tax', 'total_amount'));
        if ($aRet['status'] == 'active'){
            $sqlString = "UPDATE sdb_orders SET status = 'finish',last_change_time='".time()."' WHERE order_id = ".$this->db->quote($orderid);
            $this->db->exec($sqlString);
            $this->_info['order_id'] = $orderid;
            $this->addLog(__('订单完成'), $this->op_id?$this->op_id:null, $this->op_name?$this->op_name:null , __('完成'));

            $eventData['order_id'] = $orderid;
            $eventData['total_amount'] = $aRet['total_amount'];
            $eventData['is_tax'] = $aRet['is_tax'];
            $eventData['member_id'] = $aRet['member_id'];
            $this->fireEvent('finished', $eventData);
            return true;
        }else{
            $message = __('操作失败: 订单状态锁定');
            return false;
        }
    }

    function toCancel($orderid){
        $aOrder = $this->load($orderid);
        if(!$aOrder){
            $this->system->error(501);
            return false;
            exit;
        }
        if(!$this->checkOrderStatus('cancel', $aOrder)){
            $this->setError(10001);
            trigger_error(__('订单状态锁定'),E_USER_ERROR);
            return false;
            exit;
        }

        $sqlString = "UPDATE sdb_orders SET status = 'dead',last_change_time='".time()."' WHERE order_id=".$this->db->quote($orderid);
        $this->db->query($sqlString);

        if($this->freez_time() == 'order'){
            $this->toUnfreez($orderid);    //冻结库存解冻
        }
        $this->_info['order_id'] = $orderid;
        $this->addLog(__('订单作废'), $this->op_id?$this->op_id:null, $this->op_name?$this->op_name:null , __('作废'));

        $aPara['order_id'] = $orderid;
        $aPara['total_amount'] = $aOrder['amount']['total'];
        $this->toCancelPoint($aPara);
        $aPara['is_tax'] = $aOrder['is_tax'];
        $aPara['member_id'] = $aOrder['member_id'];

        $this->fireEvent('cancel', $aPara,$aOrder['member_id']);
        return true;
    }

    function toCancelPoint($PARA){
        $oMemberPoint = $this->system->loadModel('trading/memberPoint');
        $oMemberExperience = $this->system->loadModel('trading/memberExperience');

        $orderId = $PARA['order_id'];
        $aData = $this->getFieldById($orderId, array('member_id'));
        $memberId = intval($aData['member_id']);

//        if ($PARA['pay_status']==1 || $PARA['pay_status']==2) {
            $oMemberPoint->cancelOrderRefundConsumePoint($aMemberId['member_id'], $orderId);
            $oMemberExperience->cancelOrderRefundConsumeExperience($aMemberId['member_id'], $orderId);
//        }

        $oGift = $this->system->loadModel('trading/gift');
        $aOrderItems = $oGift->getOrderItemsList($PARA['order_id']);
        if($aOrderItems) {
            foreach($aOrderItemds as $aItem) {
                $oGift->toCancel($orderId, $aItem['gift_id']);
            }
        }
        return true;
    }

    //orderid,aItem[product_id]=pid,aItem[freez]=num,,aItem[nums]=allnum,,aItem[sendnum]=sendnum
    function toUnfreez($orderid, $aItem=null){
        if($orderid == '_ALL_'){
            $aItem = $this->db->select('SELECT * FROM sdb_order_items');
        }
        if($aItem == null) $aItem = $this->db->select('SELECT * FROM sdb_order_items WHERE order_id = '.$orderid);
        $pro = $this->system->loadModel('goods/products');
        foreach($aItem as $aProduct){
            $Product = $pro->getFieldById($aProduct['product_id'], array('goods_id,store,freez'));
            $store = $aProduct['nums'] - $aProduct['sendnum'];
            $aTmp = $this->db->selectrow("SELECT count(*) AS num FROM sdb_products WHERE product_id = ".$aProduct['product_id']
                    ." AND freez <= ".$store);
            if(empty($Product['freez'])) $Product['freez']=0;
            if($aTmp['num']){
                $this->db->exec("UPDATE sdb_products SET freez = 0 WHERE product_id = ".intval($aProduct['product_id']));
            }else{
                $this->db->exec("UPDATE sdb_products SET freez = ".($Product['freez']>intval($store) ? "freez - ".intval($store) : 0)." WHERE product_id = ".intval($aProduct['product_id']));
            }
        }
    }

    //删除订单，注意：确认后的订单不得删除！！！同时应删除订单相关明细记录。
    function toRemove($orderid, &$message){
        $aOrder = $this->load($orderid);
        if(!$aOrder){
            $this->system->error(501);
            return false;
            exit;
        }
/*        if($aOrder['pay_status'] > 0 || $aOrder['ship_status'] > 0 || $aOrder['confirm'] == 'Y'){
            $message = __('删除订单'.$orderid.'失败: 状态锁定');
            return false;
            exit;
        }*/
        $orderList = array('order_id'=>$orderid);
        $this->toCancelPoint($orderList);
        $this->db->exec('delete from sdb_gift_items where order_id='.intval($PARA['order_id']));

//        $this->toUnfreez($orderid);    //冻结库存解冻
        $sqlString = "DELETE FROM sdb_message WHERE rel_order='".$orderid."'";
        $this->db->exec($sqlString);

        $sqlString = "DELETE FROM sdb_orders WHERE order_id='".$orderid."'";
        $this->db->exec($sqlString);

        $sqlString = "DELETE FROM sdb_order_items WHERE order_id='".$orderid."'";
        $this->db->exec($sqlString);

        $sqlString = "DELETE FROM sdb_order_log WHERE order_id='".$orderid."'";
        $this->db->exec($sqlString);

        $sqlString = "DELETE FROM sdb_payments WHERE order_id='".$orderid."'";
        $this->db->exec($sqlString);

        $sqlString = "DELETE FROM sdb_refunds WHERE order_id='".$orderid."'";
        $this->db->exec($sqlString);

        $sqlString = "DELETE FROM sdb_order_pmt WHERE order_id='".$orderid."'";
        $this->db->exec($sqlString);

        $delivery_id = array(-1);
        $arr = $this->db->select("select delivery_id from  sdb_delivery WHERE order_id='".$orderid."'");
        foreach($arr as $r){
            $delivery_id[] = $r['delivery_id'];
        }

        $sqlString = "DELETE FROM sdb_delivery WHERE order_id='".$orderid."'";
        $this->db->exec($sqlString);

        $sqlString = "DELETE FROM sdb_delivery_item WHERE delivery_id in (".implode(',',$delivery_id).")";
        $this->db->exec($sqlString);

        $status = &$this->system->loadModel('system/status');
        $status->count_order_to_pay();
        $status->count_order_new();

        return true;
    }

    function getOrderDecimal($number){
        $decimal_digit = $this->system->getConf('site.decimal_digit');
        $decimal_type = $this->system->getConf('site.decimal_type');
        if($decimal_digit < 3){
            $mul = 1;
            $mul = pow(10, $decimal_digit);
            switch($decimal_type){
                case 0:
                $number = number_format($number, $decimal_digit, '.', '');
                break;
                case 1:
                $number = ceil($number*$mul) / $mul;
                break;
                case 2:
                $number = floor($number*$mul) / $mul;
                break;
            }
        }
        return $number;
    }

    function fetchByMember($member_id,$nPage){
        return $this->db->selectPager('select * from sdb_orders where disabled="false" AND member_id='.intval($member_id).' AND order_refer="local" order by acttime desc',$nPage,PERPAGE);
    }//selectPager分页
    
    function addLog($message,$op_id=null, $op_name=null , $behavior = '', $result = 'success'){
        if($message){
            $rs = $this->db->query('select * from sdb_order_log where 0=1');
            $sql = $this->db->getInsertSQL($rs,array(
                'order_id'=>$this->_info['order_id'],
                'op_id'=>$op_id,
                'op_name'=>$op_name,
                'behavior'=>$behavior,
                'result'=>$result,
                'log_text'=>$message,
                'acttime'=>time()
                ));
            return $this->db->exec($sql);
        }else{
            return false;
        }
    }

    function getLogs($order_id){
        return $this->db->select('SELECT * FROM sdb_order_log WHERE order_id = \''.$order_id.'\'');
    }

    function setPrintStatus($order_id,$type){
        $rs = $this->db->exec('select print_status from sdb_orders where order_id = \''.$order_id.'\'');
        $row = $this->db->getRows($rs,1);
        $print_status = $row[0]['print_status'];
        $sql = $this->db->GetUpdateSQL($rs,array('print_status'=>(intval($print_status) | intval($type))));
        return $this->db->exec($sql);
    }

    //读取订单明细/*{{{*/
    function getItemList($orderid, $strId='', $is_only_local=false){
        $sqlWhere = '';
        if($strId != ''){
            $sqlWhere = " AND i.product_id in (".$strId.")";
        }
        if($is_only_local){//b2c-plat bryant 需求,过滤本地商品
            $sqlWhere .= ' and (g.supplier_id is null or g.supplier_id=0)';
        }
        $aGoods = $this->db->select('SELECT i.*,nums-sendnum AS send,sendnum AS resend,g.thumbnail_pic,g.goods_id,g.small_pic,c.cat_name,p.store,p.store_place,g.supplier_id FROM sdb_order_items i
            LEFT JOIN sdb_products p ON i.product_id = p.product_id
            LEFT JOIN sdb_goods g ON g.goods_id = p.goods_id
            LEFT JOIN sdb_goods_cat c ON g.cat_id = c.cat_id
            WHERE order_id = \''.$orderid.'\' AND i.is_type = \'goods\''.$sqlWhere);
        $aPkgs = $this->db->select('SELECT i.*,nums-sendnum AS send,sendnum AS resend,g.thumbnail_pic,g.goods_id,g.small_pic,c.cat_name,g.store,g.store_place FROM sdb_order_items i
            LEFT JOIN sdb_goods g ON i.product_id = g.goods_id
            LEFT JOIN sdb_goods_cat c ON g.cat_id = c.cat_id
            WHERE order_id = \''.$orderid.'\' AND i.is_type = \'pkg\''.$sqlWhere);
        return array_merge($aGoods,$aPkgs);
    }

    function toReply($data){
        $rs = $this->db->query('SELECT * FROM sdb_comments WHERE 0=1');
        $sqlString = $this->db->GetInsertSQL($rs, $data);
        return $this->db->exec($sqlString);
    }

    function getPmtList($orderid){
        return $this->db->select('SELECT * FROM sdb_order_pmt WHERE order_id = \''.$orderid.'\'');
    }

    function getCatByPid($pid){
        $row = $this->db->selectrow('SELECT cat_name FROM sdb_products p
                LEFT JOIN sdb_goods g ON p.goods_id=g.goods_id
                LEFT JOIN sdb_goods_cat c ON g.cat_id=c.cat_id
                WHERE product_id = '.intval($pid));
        return $row['cat_name'];
    }

    //编辑订单
    function toEdit($order_id, &$aData ){
        $rs = $this->db->query('SELECT * FROM sdb_orders WHERE order_id='.$order_id);
        $this->_info['order_id'] = $order_id;
        $aData['last_change_time'] = time();
        $sql = $this->db->GetUpdateSQL($rs, $aData);
        if (!$sql || $this->db->exec($sql)){
            return true;
        }else{
            return false;
        }
    }
    //管理员手工编辑订单的同时更新sdb_orders表中的tostr
    function changeOrder($orderid,$data){
        $result12 = "";
        foreach ($data['aItems'] as $key => $val){
           $objProduct = &$this->system->loadModel('goods/products');
           $pname = $objProduct->getFieldById($val); 
           $result = $pname['name'].(($pname['pdt_desc'])?('('.$pname['pdt_desc'].')'):'').'('.$data['aNum'][$key].'),';
           $result12 .=$result;  
        }
          $this->db->exec('UPDATE sdb_orders SET tostr = \''.$result12.'\' WHERE order_id ='.$orderid);
        
    }
    //读取订单明细
    function getGiftItemList($orderid){
        return $this->db->select('SELECT i.*,thumbnail_pic,image_file FROM sdb_gift_items i LEFT JOIN sdb_gift f ON i.gift_id = f.gift_id WHERE order_id = \''.$orderid.'\'');
    }

    //管理员手工编辑订单
    function editOrder(&$aData, $delMark=true){
        if ($aData['order_id'] == ''){
            $orderid = $this->toInsert($aData);
        }else{
            $orderid = $aData['order_id'];
        }

        $addStore = array();
        foreach($aData['aItems'] as $key => $productId){
            $objProduct = &$this->system->loadModel('goods/products');    //生成订单前检查库存
            $aStore = $objProduct->getFieldById($productId);

            if($aStore['store'] !== null && $aStore['store'] !== ''){
                $sqlString = "SELECT nums FROM sdb_order_items WHERE order_id='".$orderid."' AND product_id = '".$productId."'";
                $aRet = $this->db->selectrow($sqlString);
                $gStore = intval($aStore['store']) - intval($aStore['freez']) + intval($aRet['nums']);
                if($gStore < $aData['aNum'][$key]){
                    return false;
                }
                $addStore[$productId] = intval($aData['aNum'][$key]) - intval($aRet['nums']);
            }
        }
        reset($aData['aItems']);

        $itemsFund = 0;
        foreach($aData['aItems'] as $key => $productId){
            $aItem = array();
            $aItem['order_id'] = $orderid;
            $aItem['product_id'] = $productId;
            $aItem['price'] = $aData['aPrice'][$key];
            $aItem['nums'] = $aData['aNum'][$key];
            $aItem['amount'] = $aItem['price'] * $aItem['nums'];
            $objCart = &$this->system->loadModel('trading/cart');
            if($this->freez_time()=='order'){
                    if(!$objCart->_checkStore($aItem['product_id'], $aItem['nums'], $orderid)){
                        trigger_error("商品“".$aItem['name']."”库存不足",E_USER_ERROR);
                        return false;
                        exit;
                    }
            }

            //todo 库存冻结量,库存是否足够 / 商品配件
            if($this->existItem($orderid, $productId)){
                $aProduct['edit'][] = $productId;
                $this->editItem($aItem);
            }else{
                $objProduct = &$this->system->loadModel('goods/products');
                $aPdtinfo = $objProduct->getFieldById($productId, array('goods_id, bn, name, cost, store, weight,pdt_desc'));
                $aPdtinfo['weight'] *= $aItem['nums'];

                $objGoods = &$this->system->loadModel('trading/goods');
                $aGoodsinfo = $objGoods->getFieldById($aPdtinfo['goods_id'], array('type_id'));
                $aItem = array_merge($aItem, $aPdtinfo, $aGoodsinfo);
                $this->addItem($aItem);
                $aProduct['edit'][] = $productId;
            }

            $itemsFund += $aItem['amount'];
            $itemsnum += intval($aData['aNum'][$key]);
            $freezTime = $this->freez_time();
            if($freezTime == 'order'){
                if(isset($addStore[$productId])){
                    $this->db->exec("UPDATE sdb_products SET freez = freez + ".intval($addStore[$productId])." WHERE product_id = ".intval($productId));
                    $this->db->exec("UPDATE sdb_products SET freez = ".intval($addStore[$productId])." WHERE product_id = ".intval($productId)." AND freez IS NULL");
                }
            }
        }
        if($delMark){
            $this->execDelItems($orderid, $aProduct['edit']);
        }else{
            $itemsFund = $this->getCostItems($orderid);
        }

        if($aData['is_protect'] != 'true') $aData['cost_protect'] = 0;
        if($aData['is_tax'] != 'true') $aData['cost_tax'] = 0;
        $aData['cost_item'] = $itemsFund;
        $aData['total_amount'] = $itemsFund + $aData['cost_freight'] + $aData['cost_protect'] + $aData['cost_payment'] + $aData['cost_tax'] - $aData['discount'] - $aData['pmt_amount'];
        $rate = $this->getFieldById($orderid, array('cur_rate'));
        $aData['final_amount'] = $aData['total_amount'] * $rate['cur_rate'];
        $shipping = &$this->system->loadModel('trading/delivery');
        $aShip = $shipping->getDlTypeById($aData['shipping_id']);
        $aData['shipping'] = $aShip['dt_name'];
        $aData['acttime'] = time();
        $aData['last_change_time'] = time();
        $aData['itemnum'] = $itemsnum;
        if($this->toEdit($orderid, $aData )){
            $this->addLog(__('订单编辑'), $this->op_id?$this->op_id:null, $this->op_name?$this->op_name:null , __('编辑') );
            return true;
        }else{
            return false;
        }
        return $aMsg;
    }

    function existItem($orderid, $productid){
        $sqlString = "SELECT * FROM sdb_order_items WHERE order_id='".$orderid."' AND product_id = '".$productid."'";
        $aRet = $this->db->select($sqlString);
        if(count($aRet)) return true;
        else return false;
    }

    function editItem($aData){
        $rs = $this->db->query("SELECT * FROM sdb_order_items WHERE order_id='".$aData['order_id']."' AND product_id = ".intval($aData['product_id']));
        $sqlString = $this->db->GetUpdateSQL($rs, $aData);
        if(!$sqlString || $this->db->exec($sqlString)){
            return true;
        }else{
            return false;
        }
    }

    function addItem($aData){
        $aData['name']=$aData['name'].(($aData['pdt_desc'])?'('.stripslashes($aData['pdt_desc']).')':'');
        $rs = $this->db->query('SELECT * FROM sdb_order_items WHERE 0=1');
        $sqlString = $this->db->GetInsertSQL($rs, $aData);
        $this->db->exec($sqlString);
        return true;
    }

    function execDelItems($orderid, &$aItems){
        $freezTime = $this->freez_time();
        if($freezTime == 'order'){
            $sqlString = "SELECT product_id, nums FROM sdb_order_items WHERE order_id='".$orderid."' AND product_id NOT IN('".implode("','", $aItems)."')";
            $aRet = $this->db->select($sqlString);
            if(count($aRet)){
                foreach($aRet as $key => $item){
                    $productId = $item['product_id'];
                    $nums = intval($item['nums']);
                    $this->db->exec("UPDATE sdb_products SET freez = freez - ".$nums." WHERE product_id = ".$productId);
                }
            }
        }
        $sqlString = "DELETE FROM sdb_order_items WHERE order_id = '".$orderid."' AND product_id NOT IN('".implode("','", $aItems)."')";
        $this->db->exec($sqlString);
    }

    //根据会员ID返回订单数量
    function getOrderNumbyMemberId($member_id=0){
        $sqlString = "SELECT COUNT(*) AS num FROM sdb_orders WHERE member_id = ".$member_id;
        $data = $this->db->selectrow($sqlString);
        return $data['num'];
    }

    //获取会员的定单列表
    function getOrderListByMemberId($nMId){
        return $this->db->select("SELECT order_id,status,pay_status,ship_status,total_amount,createtime FROM sdb_orders WHERE member_id=".$nMId);
    }

    //前台订单支付更换支付方式
    function chgPayment($orderid, $paymentid, $paymoney,$chgpayment=0){
        if($aOrder = $this->getFieldById($orderid, array('cost_protect','cost_freight','cost_tax','cost_payment','cost_item','pmt_amount','discount','payment','total_amount','payed','currency','cur_rate'))){
            if($aOrder['payment'] != $paymentid){
                $payment = &$this->system->loadModel('trading/payment');
                if($paymentid > 0){
                    $aPayment = $payment->getPaymentById($paymentid);
                    $aPayment['config'] = unserialize($aPayment['config']);
                }else{
                    $aPayment['fee'] = 0;
                }
                if (!$chgpayment)
                    $aData['payment'] = $paymentid;

                $total_fee = 0;
                $total_fee = 0;
                $payedamount = $payment->getSuccOrderBillList($orderid);
                if ($payedamount){
                    foreach($payedamount as $pk => $pv){
                        $totalPayedMoney +=$pv['money'];//已支付总金额
                        $totalPayFee +=$pv['paycost'];//已支付费率
                    }
                }
                $chgMoney = $totalPayedMoney - $totalPayFee;
                $amountExceptPay = $aOrder['cost_protect']+$aOrder['cost_freight']+$aOrder['cost_tax']+$aOrder['cost_item']-$aOrder['discount'];
                if(($amountExceptPay + $aOrder['cost_payment'])."" != $aOrder['total_amount']){
                    $amountExceptPay = $amountExceptPay - $aOrder['pmt_amount'];
                }
                if($aPayment['config']['method']==2){//支付固定费用
                    $amountPayment = $aPayment['config']['fee'];
                }else{
                    $amountPayment = ($amountExceptPay - $chgMoney) * $aPayment['fee'];
                }
                $total_amount = $amountExceptPay + $amountPayment + $totalPayFee;

                $aData['cost_payment'] = $totalPayFee + $amountPayment;
                $aData['total_amount'] = $total_amount;
                $aData['final_amount'] = $total_amount * $aOrder['cur_rate'];
                $_POST['cur_money'] = ($aData['total_amount']-$chgMoney) * $aOrder['cur_rate'];

                if(!$chgpayment){
                    $rs = $this->db->exec('SELECT * FROM sdb_orders WHERE order_id =\''.$orderid.'\'');
                    $sql = $this->db->getUpdateSQL($rs, $aData);
                    $this->db->exec($sql);
                }

                return $aData['total_amount']-$chgMoney;
            }else{
                return $paymoney;
            }
        }else{
            return false;
        }
    }

    //新增订单商品
    function insertOrderItem($orderid, $goodsbn, $num){

        $aOrder = $this->getFieldById($orderid, array('member_id'));
        $aProduct = $this->db->selectrow('SELECT p.bn,p.name,g.score,p.product_id,type_id,p.price,p.pdt_desc FROM sdb_products p
                    LEFT JOIN sdb_goods g ON p.goods_id = g.goods_id
                    WHERE p.bn=\''.$goodsbn.'\' AND g.disabled = \'false\' AND g.marketable = \'true\'');
        if(!$aProduct['product_id']) return 'none';
        if($aOrder['member_id']){
            $oMember = &$this->system->loadModel('member/member');
            $aMember = $oMember->getFieldById($aOrder['member_id'], array('member_lv_id'));
            $mPrice = $this->db->selectrow('SELECT price AS mprice FROM sdb_goods_lv_price
                    WHERE product_id='.intval($aProduct['product_id']).' AND level_id = '.intval($aMember['member_lv_id']));
            if(!$mPrice['mprice']){
                $mPrice['mprice'] = $aProduct['price'];
            }
        }else{
            $oLevel = &$this->system->loadModel('member/level');
            $aLevel = $oLevel->getList('discount', array('default_lv'=>1),0,-1);
            $mPrice['mprice'] = $aProduct['price'] * ($aLevel[0]['discount'] ? $aLevel[0]['discount'] : 1);
        }

        $aData['product_id'] = $aProduct['product_id'];
        $aData['bn'] = $aProduct['bn'];
        $aData['name'] = $aProduct['name'].($aProduct['pdt_desc'] ? '('.$aProduct['pdt_desc'].')' : '');
        $aData['price'] = $mPrice['mprice'];
        $aData['order_id'] = $orderid;
        $aData['amount'] = $aData['price'] * $num;
        $aData['nums'] = $num;
        $aData['score'] = $aProduct['score'];
        $aData['type_id'] = $aProduct['type_id'];
        if($this->db->selectrow('SELECT * FROM sdb_order_items WHERE order_id=\''.$orderid.'\' AND product_id='.$aData['product_id'])){
            return 'exist';
        }
        $rs = $this->db->query('select * from sdb_order_items where 0=1');
        $sqlString = $this->db->GetInsertSQL($rs, $aData);
        return $this->db->exec($sqlString);
    }

    function saveMarkText($orderid, $aData){
        $aTmp['mark_text'] = $aData['mark_text'];
        $aTmp['mark_type'] = $aData['mark_type'];
        $rs = $this->db->exec('SELECT * FROM sdb_orders WHERE order_id='.$orderid);
        $sql = $this->db->getUpdateSql($rs,$aTmp);
        if(!$sql || $this->db->exec($sql)){
            $aOrder = $this->getFieldById($orderid, array('total_amount','is_tax','member_id'));
            $eventData['order_id'] = $orderid;
            $eventData['total_amount'] = $aOrder['total_amount'];
            $eventData['is_tax'] = $aOrder['is_tax'];
            $eventData['member_id'] = $aOrder['member_id'];
            $this->fireEvent('addmemo', $eventData,$eventData['member_id']);
            return true;
        }else{
            return false;
        }
    }
    function fireEvent($action,$data,$memberid){
   
        if($data['member_id']===NULL){
          $data['mobile']=$data['delivery']['ship_mobile']?$data['delivery']['ship_mobile']:$data['ship_mobile'];
          $data['email'] = $data['delivery']['ship_email']?$data['delivery']['ship_email']:$data['ship_email'];
        }

        if($data['mobile']==''){
          $data['mobile']=$data['ship_mobile']?$data['ship_mobile']:$data['delivery']['ship_mobile'];
        }

        if (!$data['email'])
            $data['email'] = $data['ship_email'];
        parent::fireEvent($action,$data,$memberid);
    }

    function getOrderLogList($orderid , $page, $pageLimit){
        return $this->db->selectPager( 'SELECT * FROM sdb_order_log WHERE order_id = '.$orderid , $page , $pageLimit);
    }

    function recycle($filter){
        $rs = parent::recycle($filter);
        if($rs)
            foreach( $filter['order_id'] as $oid ){
                $this->_info['order_id'] = $oid;
                $this->addLog(__('订单删除'), $this->op_id?$this->op_id:null, $this->op_name?$this->op_name:null , __('删除'));
            }
        return $rs;
    }

    function active($filter){
        $rs = parent::active($filter);
        if($rs)
            foreach( $filter['order_id'] as $oid ){
                $this->_info['order_id'] = $oid;
                $this->addLog(__('订单还原'), $this->op_id?$this->op_id:null, $this->op_name?$this->op_name:null , __('还原'));
            }
        return $rs;
    }

    function addOrderMsg($aData){
        $aRs = $this->db->query('SELECT * FROM sdb_message WHERE 0');
        $sSql = $this->db->getInsertSql($aRs,$aData);

        return $this->db->exec($sSql);
    }
    function getCostItems($order_id){
        $aRet = $this->getItemList($order_id);
        $money = 0;
        foreach((array)$aRet as $row){
            $money += $row['amount'];
        }
        return $money;
    }

    //判断订单明细是否还有本地商品：0全部本地，1部分本地，2无本地
    function checkLocalItem(){
        ;
    }

    function alterOrderLog($logs){
        if(!empty($logs)){
            $message_part = "";

            foreach($logs as $k=>$log){
                $prefix = "<!--";
                $postfix = "-->";
                $pattern = $prefix."[\s\S]*?".$postfix;

                $matches = array();
                if(preg_match("/".$pattern."/",$log['log_text'],$matches)){
                    $match_text = $matches[0];
                    $match_text = str_replace($prefix,"",$match_text);
                    $match_text = str_replace($postfix,"",$match_text);

                    parse_str($match_text,$arr);
                    $delivery_id = $arr['delivery_id'];
                    $order_id = $arr['order_id'];
                    $ship_status = $arr['ship_status'];

                    $delivery_item_info = $this->db->select(
                        "SELECT a.number,b.name
                            FROM sdb_delivery_item AS a,sdb_order_items AS b
                            WHERE a.delivery_id=".$delivery_id.
                                " AND b.order_id=".$order_id.
                                " AND a.product_bn=b.bn"
                    );
                    $delivery_item_info = json_encode($delivery_item_info);
                    $delivery_item_info = str_replace("'","&#039;",$delivery_item_info);

                    if($ship_status == "1" || $ship_status == "4"){
                        $message_part = "全部";
                    }else if($ship_status == "2" || $ship_status == "3"){
                        $message_part = "部分";
                    }

                    $log_text = "<a href='javascript:void(0)' onclick='show_delivery_item(this,\"".$delivery_id."\",".($delivery_item_info).")' title='点击查看详细' style='color:#003366; font-weight:bolder; text-decoration:underline;'>".$message_part."商品</a>";

                    $logs[$k]['log_text'] = preg_replace("/".$pattern."/",$log_text,$log['log_text']);
                }
            }

            return $logs;
        }else{
            return array();
        }
    }
    function updateExtend($orderid,$extend){
        $data['extend'] = serialize($extend);
        $rs=$this->db->query('select extend from sdb_orders where order_id=\''.$orderid.'\'');
        $sSql=$this->db->getUpdateSql($rs,$data);
        return (!$sSql || $this->db->exec($sSql));
    }

    function freez_time(){
        $rs = $this->db->select('SELECT s_data  FROM sdb_settings WHERE s_name = \'system\'');
        if(isset($rs[0]['s_data'])){
            $aData=unserialize($rs[0]['s_data']);
            switch($aData['store.time']){
            case '1':
            $method='order';
            break;
            case '2':
            $method='pay';
            break;
            case '3':
            $method='delivery';
            break;
            default:
            $method='order';
            break;
            }
        }
        return $method;
    }

    function _getOrderIdList(){
        return $this->db->select('SELECT order_id FROM sdb_orders');
    }
    function _getShipment($orderId){
        $aTemp = $this->db->selectrow('SELECT delivery_id,money,delivery,type FROM sdb_delivery WHERE order_id=\''.$orderId.'\'');
        return $aTemp;
    }
    function _getShipmentItem($delivery_id){
        return $this->db->select('SELECT * FROM sdb_delivery_item WHERE delivery_id=\''.$delivery_id.'\'');
    }

    function checkPaymentCfg($id){
        return $this->db->select('SELECT pay_type FROM  sdb_payment_cfg  WHERE id=\''.$id.'\'');
    }

    function getProductInfo($orderid, $goodsbn){
        $aOrder = $this->getFieldById($orderid, array('member_id'));
        $aProduct = $this->db->selectrow('SELECT p.bn,p.name,g.score,p.product_id,type_id,p.price,p.pdt_desc, p.store,p.freez FROM sdb_products p
                        LEFT JOIN sdb_goods g ON p.goods_id = g.goods_id
                        WHERE p.bn=\''.$goodsbn.'\' AND g.disabled = \'false\' AND g.marketable = \'true\'');
      
        if(!$aProduct['product_id']) return 'none';

        if(!is_null($aProduct['store'])){
           $avali_store=$aProduct['store']-$aProduct['freez'];

            if(($this->freez_time()=='order' && $avali_store < 1)||($this->freez_time()=='pay' && $avali_store < 1)||($this->freez_time()=='delivery' && $avali_store < 1)){
                return 'understock';
            }
        }

        if($aOrder['member_id'] || $aOrder['member_id']==NULL){
            $oMember = &$this->system->loadModel('member/member');
            $aMember = $oMember->getFieldById($aOrder['member_id'], array('member_lv_id'));
            $mPrice = $this->db->selectrow('SELECT price AS mprice FROM sdb_goods_lv_price
                    WHERE product_id='.intval($aProduct['product_id']).' AND level_id = '.intval($aMember['member_lv_id']));
            if(!$mPrice['mprice']){
                $mPrice['mprice'] = $aProduct['price'];
            }
        }else{
            $oLevel = &$this->system->loadModel('member/level');
            $aLevel = $oLevel->getList('dis_count', array('default_lv'=>1),0,-1);
            $mPrice['mprice'] = $aProduct['price'] * ($aLevel[0]['dis_count'] ? $aLevel[0]['dis_count'] : 1);
        }

        if($this->db->selectrow('SELECT * FROM sdb_order_items WHERE order_id=\''.$orderid.'\' AND product_id='.$aProduct['product_id'])){
            return 'exist';
        }

        return array_merge($aProduct, $mPrice);
    }

    function getPrintId($order_id){
        //检查是否存在print_id
        $rs = $this->db->exec('select print_id from sdb_orders where order_id = \''.$order_id.'\'');
        $row = $this->db->getRows($rs,1);
        $print_id = $row[0]['print_id'];

        if(!$print_id){
            $cur_date = date('Ymd',time());
            $aPrintid = $this->db->selectrow('SELECT MAX(print_id) AS print_id FROM sdb_orders WHERE LEFT(print_id,8)=\''.$cur_date.'\'');
            $cur_max_printid = $aPrintid['print_id'];
            if($cur_max_printid)
                $aUpdate['print_id'] = $cur_date.str_pad(intval(substr($cur_max_printid,8,4))+1, 4, '0' ,STR_PAD_LEFT);
            else
                $aUpdate['print_id'] = $cur_date."0001";
            $sql = $this->db->GetUpdateSQL($rs,$aUpdate);
            if($this->db->exec($sql)){
                return $aUpdate['print_id'];
            }
            else
                return false;
        }

        return $print_id;
    }
}
?>
