<?php
include_once('shopObject.php');
include(API_DIR.'/include/api_utility.php');
class mdl_order_po extends shopObject{

    function mdl_order_po(){
        parent::shopObject();
        $this->_token = $this->system->getConf('certificate.token');
    }

    /**
     * 错误处理
     * 对于系统级错误和应用级错误error级别直接 trigger_error E_USER_ERROR 暂时无用
     *
     * @author bryant
     * @date 2009-05-31
     * @param int $obj_api_utility, api 实例化对象
     * @param int $str_app_error_no, 应用级错误编号
     * @param enum(true/false)int, 是否需要程序来捕获错误,还是通过系统来处理
     */    
    function _catch_app_error($obj_api_utility, $str_app_error_no, $is_catch=false){
        if($obj_api_utility->error_no=='0x003'){
            $_array_error = $obj_api_utility->application_error[$str_app_error_no];
            if(isset($_array_error)){
                if($is_catch===false){
                    switch($_array_error['level']){
                        case 'notice':
                            $_error_level = E_USER_NOTICE;
                            break;
                        case 'warning':
                            $_error_level = E_USER_WARNING;
                            break;
                        case 'error':
                            $_error_level = E_USER_ERROR;
                            break;
                    }
                    trigger_error($_array_error['desc'], $_error_level);
                }                
                return $application_error[$str_app_error_no];                
            }else{
                return false;
            }
        }
        return false;
    }

    /**
     * 通过订单id取回相应po单
     *
     * @author bryant
     * @date 2009-05-31
     * @param int $orderid, 订单id
     * @return array 
     *    array(
     *        'local' => ,
     *        'supplier' => array(
     *              @supplier_id@ => array(
     *                    ....
     *                ),
     *           ),
     *         );    
     */
    function getOrderItemsList($orderid){
        $sql = 'SELECT i.*,g.thumbnail_pic,g.goods_id,g.weight,p.store,i.supplier_id,i.bn dealer_bn,sp.source_bn supplier_bn
            FROM sdb_order_items i
            LEFT JOIN sdb_products p ON i.product_id = p.product_id
            LEFT JOIN sdb_goods g ON g.goods_id = p.goods_id
            LEFT JOIN sdb_supplier_pdtbn sp ON i.bn = sp.local_bn AND sp.default = \'true\'
            LEFT JOIN sdb_supplier s ON sp.sp_id = s.sp_id
            WHERE order_id = \''.$orderid.'\' AND i.is_type = \'goods\' ';
        $aGoods = $this->db->select($sql);
        $aPo = array(
            'local' => array(),
            'supplier' => array());
        foreach($aGoods as $aGoodsItem){
            if ($aGoodsItem['supplier_id']===null) {
                $aPo['local'][$aGoodsItem['bn']] = $aGoodsItem;
            }else{
                $aPo['supplier'][$aGoodsItem['supplier_id']][$aGoodsItem['bn']] = $aGoodsItem;
            }
        }
        
        $sql = 'SELECT i.*,g.thumbnail_pic,g.goods_id,g.weight,g.store,i.bn dealer_bn
            FROM sdb_order_items i
            LEFT JOIN sdb_goods g ON i.product_id = g.goods_id
            WHERE order_id = \''.$orderid.'\' AND i.is_type = \'pkg\' ';
        $aGoods = $this->db->select($sql);
        foreach((array)$aGoods as $aGoodsItem){
            $aPo['local'][$aGoodsItem['bn']] = $aGoodsItem;
        }
        
        $aGoods = $this->db->select('SELECT i.*
                    FROM sdb_gift_items i LEFT JOIN sdb_gift f ON i.gift_id = f.gift_id 
                    WHERE order_id = \''.$orderid.'\'');
        foreach((array)$aGoods as $aGoodsItem){
            $aPo['gift'][$aGoodsItem['gift_id']] = $aGoodsItem;
        }
        return $aPo;
    }

    /**
     * 通过订单id取回相应po单
     *
     * @author bryant
     * @date 2009-05-31
     * @param int $orderid, 订单id
     * @return
     array(
     'local' => ,
     'supplier' => array(
     @supplier_id@ => array(
     ....
     ),
     ),
     );
    */
    function getPoShowStatusByPoStatus($status, $pay_status, $ship_status){
        $aPayStatus = array(0=>__('未付款'),
                            1=>__('已全部付款'),
                            2=>__('已付款至担保方'),
                            3=>__('部分付款'),
                            4=>__('部分退款'),
                            5=>__('已全部退款'),
                            6=>__('支付中'));
        $aShipStatus = array(0=>__('未发货'),
                                       1=>__('已全部发货'),
                                       2=>__('部分发货'),
                                       3=>__('部分退货'),
                                       4=>__('已全部退货') );


        if ($status == 'dead'){
            $_po_status = __('采购单被取消');
        }else if ($status == 'finish'){
            $_po_status = __('已完成');
        }else if ($pay_status == 6){
            $_po_status = __('支付中');                    
        }else if ($status=='pending'){
            $_po_status = __('暂停');
        }else if ($pay_status==0 && $ship_status==0){
            $_po_status = __('未付款');
        }else if ($pay_status==1 && $ship_status==0){
            $_po_status = __('等待发货中');
        }else if ($pay_status==5 && $ship_status==0){
            $_po_status = __('已全额退款,等待发货');
        }else if ($pay_status==4 && $ship_status==0){
            $_po_status = __('部分已退款,等待发货');
        }else if ($pay_status==1 && $ship_status==1){
            $_po_status = __('订单已完成');
        }else if ($pay_status==5 && $ship_status==1){
            $_po_status = __('已全额退款,等待退货');
        }else if ($pay_status==4 && $ship_status==1){
            $_po_status = __('已部分退款，已全部发货');
        }else if ($pay_status==1 && $ship_status==4){
            $_po_status = __('已全部退货，暂未退款');
        }else{
            $_po_status = $aPayStatus[$pay_status].','.$aShipStatus[$ship_status];
        }
        return $_po_status;
    }

    function getPoActionByPoStatus($status, $pay_status, $ship_status){
        $_action_status = array();
        
        if ($status=='dead'||$status=='finish'){
            $_action_status = array(
                'inquiry' => false,
                'create' => false,
                'reconciliation' => false,
                'pending' => 'disabled',
                'pay' => false,
                'modify' => false);
        }else{
            $_action_status['inquiry'] = false;
            $_action_status['create'] = false;
            

            if ($pay_status==6||$pay_status==2){//支付中或已付款至担保方
                $_action_status['reconciliation'] = true;
            }else{
                $_action_status['reconciliation'] = false;
            }
        
            if ($status == 'pending'){
                $_action_status['pending'] = 'cancelpending';
            }else if ($pay_status==1 && $ship_status ==0){//已付款未发货
                $_action_status['pending'] = 'pending';
            }else{
                $_action_status['pending'] = 'disabled';
            }

            if (in_array($pay_status, array(0, 2, 3))){//未全额支付
                $_action_status['pay'] = true;
            }else{
                $_action_status['pay'] = false;
            }

            if ($ship_status == 0){//未发货
                $_action_status['modify'] = true;
            }else{
                $_action_status['modify'] = false;
            }
        }
        return $_action_status;
    }

    /**
     * 获取简要或详细PO单列表信息
     *
     * @author bryant
     * @date 2009-05-31
     * @param int $orderid，b2c订单order_id
     * @param enum('all', 'active')$show_status 指定需要列出哪些状态的po单  
     *     'all':  所有的po单
     *     'valid':  有效的po单      pending','active','finish'
     * @return array，po单列表信息
     *                      array(
     *                          'local' => array(
     *                               #货品bn号# => array(//b2c bn号
     *                                     ....
     *                                   ),
     *                               ....    
     *                          ),
     *                          'local_ship_status' => enum('0/1/2') //纯本地商品发货状态 0 未发货 1已发货 2部分发货
     *                          'supplier' => array(
     *                              #供应商id# => array(
     *                                  'name' => xxx, //供应商名称
     *                                  'po' => array(
     *                                      #po单单号# => array(
     *                                          'order_id' => xxx //po单单号
     *                                          'status' => xxx,
     *                                          'pay_status' => xxx,
     *                                          'ship_status' => xxx,
     *                                          'shipping_id' => xxx,
     *                                          'ship_area' => xxx,
     *                                          'total_amount' => xxx,     //po单价格总价
     *                                          'createtime'  => xxx,
     *                                          'items' => array(
     *                                              #b2c号# => array( 
     *                                                  'supplier_bn' => xxxx, //b2b bn号
     *                                                  'dealer_bn' => xxxx, //b2c bn号
     *                                                  'name' => xxxx,         
     *                                                  'price' => xxxx,//b2c价格
     *                                                  'po_price' => xxxx, //采购单实际价格     
     *                                                  'nums' => xxxx,
     *                                                  'amount' => xxxx,
     *                                                  ....
     *                                                 ),
     *                                             ),
     *                                          '_action_status' => array(
     *                                              'inquiry' => true/false,
     *                                              'create' => true/false,
     *                                              'reconciliation' => false,
     *                                              'create' => true/false,
     *                                              'pending' => disabled/pending/cancelpending,
     *                                              'create' => true/false,
     *                                              'create' => true/false,
     *                                              'create' => true/false,
     *                                             ),
     *                                          '_po_status' => xxxx //po单状态描述
     *                                         ),
     *                                  'local' => array(
     *                                      #b2c货号# => array(
     *                                          'dealer_bn' => xxxx, //b2c bn号
     *                                          'supplier_bn' => xxxx, //b2b bn号
     *                                          'name' => xxxx,
     *                                         ),
     *                                     ),
     *                                ),
     *                           )         
     */
    function getPoListByOrderId($orderid, $show_status='all'){
        if(!($pdt_items = $this->getOrderItemsList($orderid))){
            return false;
        }
        $result = array();        
        $result['local'] = &$pdt_items['local'];
        //+判断纯本地商品库存状态
        //0 未发货 1已发货 2部分发货
        $tmp_nums = 0;
        $tmp_sendnum = 0;
        if($pdt_items['local']){
            foreach($pdt_items['local'] as $_item){
                $tmp_nums += $_item['nums'];
                $tmp_sendnum += $_item['sendnum'];
            }
        }
        if($tmp_sendnum == 0){
            $result['local_ship_status'] = 0;
        }elseif($tmp_sendnum == $tmp_nums){
            $result['local_ship_status'] = 1;
        }else{
            $result['local_ship_status'] = 2;
        }
        //-判断纯本地商品库存状态
        $s_pdt_items = &$pdt_items['supplier'];
        
        $api_utility = &$this->system->api_call(PLATFORM,PLATFORM_HOST,PLATFORM_PATH,PLATFORM_PORT,$this->_token);

        $send = array(
            'columns' => 'order_id|status|pay_status|ship_status|createtime|supplier_id|items|final_amount|cost_item|cost_freight|cost_protect|cost_tax|payed|shipping_id|ship_area|total_amount',
            'id' => $orderid,
            );

        //指定列出哪些状态的po单
        if ($show_status == 'all'){
        }else if($show_status == 'valid'){
            $send['status'] = array('pending','active','finish');
        }

        if (count($pdt_items['supplier']) == 0){
            return $result;
        }
//        var_dump($pdt_items);

        $aPoList = $api_utility->getApiData('getPOrdersBySOrderId', API_VERSION, $send);//todo 加上status  active
        $api_utility->trigger_all_errors();
        if(!is_array($aPoList)){
            $aPoList = array();
        }

        foreach ($aPoList as $poItem){
            $poItem['_action_status'] = $this->getPoActionByPoStatus($poItem['status'], $poItem['pay_status'], $poItem['ship_status']);
            $poItem['_po_status'] = $this->getPoShowStatusByPoStatus($poItem['status'], $poItem['pay_status'], $poItem['ship_status']);
            $poItem['ship_area'] = substr($poItem['ship_area'], strrpos($poItem['ship_area'], ':')+1);
//            $poItem['total_amount'] = $poItem['final_amount'];
            unset($poItem['final_amount']);
            $_s2l_cr = $this->get_s2lBns(array_item($poItem['items'], 'bn'), true);
            foreach($poItem['items'] as $_k => $poProductItem){
                $poItem['items'][$_k]['supplier_bn'] = $poProductItem['bn'];
                $poItem['items'][$_k]['dealer_bn'] = $_dealer_bn = $_s2l_cr[$poProductItem['bn']];
                $poItem['items'][$_k]['po_price'] = $poProductItem['price'];//采购单价格                    
                $poItem['items'][$_k]['price'] = $s_pdt_items[$poItem['supplier_id']][$_dealer_bn]['price'];//b2c零售价格
                $poItem['items'][$_k]['product_id'] = $s_pdt_items[$poItem['supplier_id']][$_dealer_bn]['product_id'];//采购单价格                    
                $poItem['items'][$_k]['goods_id'] = $s_pdt_items[$poItem['supplier_id']][$_dealer_bn]['goods_id'];//b2c零售价格

                if($poItem['status'] != 'dead'){
                    $s_pdt_items[$poItem['supplier_id']][$_dealer_bn]['nums'] -= $poProductItem['nums'];
                }
                unset($poItem['items'][$_k]['bn']);
                unset($poItem['items'][$_k]['type_id']);
                unset($poItem['items'][$_k]['product_id']);                    
            }
            //将po单的items的key换为dealer_bn
            $poItem['items'] = array_change_key($poItem['items'],'dealer_bn');
            $result['supplier'][$poItem['supplier_id']]['po'][$poItem['order_id']] = $poItem;
            
        }

        foreach($s_pdt_items as $_supplier_id => $_s_pdt_item){
            foreach($_s_pdt_item as $_bn => $_item){
                if (intval($_s_pdt_item[$_bn]['nums']) <= 0){
                    unset($_s_pdt_item[$_bn]);
                }
            }
            if(!empty($_s_pdt_item)){
                $result['supplier'][$_supplier_id]['local'] = $_s_pdt_item;
            }

            $_sql = sprintf('select supplier_brief_name from sdb_supplier where supplier_id=%d', $_supplier_id);
            $_supplier = $this->db->selectrow($_sql);
            $result['supplier'][$_supplier_id]['name'] = $_supplier['supplier_brief_name'];
        }
        return $result;
    }

    function _verify_api_createOrder_data(&$array_data) {
        $str_poinfo_rule = <<<EOT
shipping    varchar(100)    N
dealer_order_id    integer    Y
shipping_area    varchar(50)    N
ship_name    varchar(50)    N
ship_area    varchar(255)    N
ship_addr    varchar(100)    N
ship_zip    varchar(20)    N
ship_tel    varchar(30)    N
ship_email    varchar(150)    N
ship_time    varchar(50)    N
ship_mobile    varchar(50)    N
shipping_id    integer    Y
is_tax    enum('false','true')    Y
tax_company    varchar(255)    N
is_protect    enum('false','true')    Y
currency    varchar(8)    N
member_memo    longtext    N
sender_info    longtext    Y
items    array     Y
EOT;
        $obj_verify_data = $this->system->loadModel('utility/data_verify');
        return $obj_verify_data->checkParams($str_poinfo_rule, $array_data['struct']);
    }

    /**
     * 生成采购单
     *
     * @author bryant
     * @date 2009-05-31
     * @param int $supplierId, 供应商id
     * @param int $orderid, b2c订单id     
     * @param array $poInfo, po单基础信息
     *                          array(
     *                              'shipping' => xxx,
     *                              'shipping_area' => xxx,
     *                              'ship_name' => xxx,
     *                              'ship_area' => xxx,
     *                              'ship_addr' => xxx,
     *                              'ship_zip' => xxx,
     *                              'ship_tel' => xxx,
     *                              'ship_email' => xxx,
     *                              'ship_time' => xxx,
     *                              'ship_mobile' => xxx,
     *                              'is_tax' => true/false,
     *                              'tax_company' => xxx,
     *                              'is_protect' => true/false,
     *                              'currency' => xxx,
     *                              'member_memo' => xxx,
     *                              'sender_info' => array,//发货人信息
     *                         )
     * @param array $poItems, po单货品明细列表
     *                          array( 
     *                              0 => array( 
     *                                   'dealer_bn' => xxx,//供应商bn
     *                                   'supplier_bn' => xxx,//供应商bn     
     *                                   'price' => xxx, //下采购单时的价格
     *                                   'nums' => xxx),
     *                               ...
     *                          )
     * @return int 新生成的采购单单号
     */

    
    
    function createPo($supplierId, $orderid, $poInfo, $poItems){
        //+ 检查下单量是否大于已下单量
        //todo getPoListByOrderId 调出经销商order_id 下的所有 order_items +上要下单的商品后 和本地进行对比,如果超过则报错
        $all_items = $this->getPoListByOrderId($orderid, 'valid');//经销商order_id下的所有po单
        if($all_items===false){ 
            trigger_error(__('无法获取PO单'), E_USER_ERROR); 
        }else{ 
            $_array_po_items = $all_items['supplier'][$supplierId]['local']; 
            $_array_po_items = array_change_key($_array_po_items, 'dealer_bn'); 
        }
        foreach($poItems as $_item){ 
            $_array_po_item = $_array_po_items[$_item['dealer_bn']]; 
            if (empty($_array_po_item) || ($_array_po_item['nums']-$_item['nums']<0)){ 
                trigger_error(__('向供应商下单的商品超出B2C订单内商品,请刷新重试'), E_USER_ERROR); 
            }             
        }

        //-检查下单量是否大于已下单量
        $poInfo['dealer_order_id'] = $orderid;
        $poInfo['sender_info'] = '';        
        $poInfo['is_tax'] = ($poInfo['is_tax'])?'true':'false';
        $poInfo['is_protect'] = ($poInfo['is_protect'])?'true':'false';
        $poItems = array_change_key($poItems, 'dealer_bn');
        $_dealer_bns = array_item($poItems, 'dealer_bn');
        $_l2s_cr = $this->get_l2sBns($_dealer_bns, true);

        $_inquiry_data = array();
        foreach($_l2s_cr as $_dealer_bn => $_supplier_bn){
            $_inquiry_data[$_supplier_bn] = $poItems[$_dealer_bn]['nums'];
        }

        $_inquiry_result = $this->inquiry($supplierId, $orderid, $_inquiry_data);
        if(!$_inquiry_result){
            trigger_error(__('下单商品有错误,不能下单'), E_USER_ERROR);
        }
        $_inquiry_result = $_inquiry_result['items'];
        $_err_msg = '';
        //库存不足的情况继续下单
        foreach($_inquiry_result as $_item){
            if($_item['po_price'] != $poItems[$_item['dealer_bn']]['price']){
                trigger_error(sprintf(__('货品: %s 价格与之前询单价格不同,请重新询单'), $_item['dealer_bn']), E_USER_ERROR);
            }
            if($_item['status'] == 'shelves' || $_item['status'] == 'deleted' || $_item['stock'] === 0){
                unset($poItems[$_item['dealer_bn']]);
            }
            
        }

        //生成提交所用的数据
        $send['id'] = $supplierId;
        $send['struct'] = &$poInfo;
        $send['struct']['items'] = $poItems;
        if(!$poItems) {            
            trigger_error(__('采购单中的商品没有库存，或者无商品'), E_USER_ERROR);
            return;
        }
        if(($str_error_field = $this->_verify_api_createOrder_data($send))!==true) {            
            trigger_error(__('下采购单数据有误').$str_error_field, E_USER_ERROR);
        }
        $api_utility = &$this->system->api_call(PLATFORM,PLATFORM_HOST,PLATFORM_PATH,PLATFORM_PORT,$this->_token);

        $result = $api_utility->getApiData('createOrder', API_VERSION, $send);
        $api_utility->trigger_all_errors();        

        //创建采购单返回po单id
        $poOrderId = $result;
        
        //将sdb_order_items supplier_id置为对应supplier_id，标识订单明细货品为哪个供应商供货
        foreach($_dealer_bns as $_bn){
            $_sql = sprintf('select * from sdb_order_items where bn=\'%s\'', $_bn);
            $rs = $this->db->query($_sql);
            $_data = array('supplier_id'=>$supplierId);
            $_sql = $this->db->getUpdateSql($rs, $_data);
            $this->db->exec($sql);
        }
        return $poOrderId;
    }


    /**
     * 采购单修改
     *
     * @author bryant
     * @date 2009-05-31
     * @param int $orderid, 订单id(b2c端order_id)
     * @param array $modifyItems, 订单修改货品细列表
     *                          array( 
     *                              0 => array( 
     *                                   'dealer_bn' => xxx, //b2c端bn号
     *                                   'supplier_bn' => xxx, //b2c端bn号     
     *                                   'price' => xxx ,//b2c端价格
     *                                   'po_price' => xxx ,//采购单价格
     *                                   'nums' => xxx
     *                                   'product_id' => xxx),
     *                               ...
     *                          )
     * @param array $supplierId, 供应商id
     * @param array $poId, 采购单id
     * @return boolean
     */

    //todo  price po_price 搞一搞
    function modifyOrder($orderid, $modifyItems, $supplierId=0, $poId=0, &$return){
        //如果供应商id为0,则为纯本地商品|如果有供应商id,po单号为0,则为供应商本地未下单商品|如果有供应商id,并且有po单号,则为采购商品
        $all_items = $this->getPoListByOrderId($orderid, 'valid');
        //flag 0:纯本地货品,1:供应商供货单还未下单货品,2:已下采购单货品
        //todo 删除商品逻辑,如果不在就删除
        $_flag = 0;
        $modifyItems = array_change_key($modifyItems, 'dealer_bn');
        $_delete_items = array();
        if($supplierId==0){
            $_flag = 0;
            $_old_order_items = $all_items['local'];
            
        }else if($poId===0){
            $_flag = 1;
            $_old_order_items = $all_items['supplier'][$supplierId]['local'];
        }else{
            $_flag = 2;
            $_old_order_items = $all_items['supplier'][$supplierId]['po'][$poId]['items'];
        }
        $_old_order_items = array_change_key($_old_order_items, 'dealer_bn');


        //判断购物车是否被删空
        if(empty($modifyItems)){
            $_sql = sprintf('select count(bn) as count from sdb_order_items where order_id=%s', $orderid);
            $_array_tmp_count = $this->db->selectrow($_sql);
            if($_array_tmp_count['count'] - count((array)$_old_order_items)<=0){
                trigger_error(__('不能将购物车商品删空'), E_USER_ERROR);
            }
        }else{
            $_is_all_delete = true;
            foreach($modifyItems as $_bn => $_modifyItem){
                if ($_modifyItem['nums'] > 0){
                    $_is_all_delete = false;
                    break;
                }
            }
            if ($_is_all_delete){
                trigger_error(__('不能将购物车商品删空'), E_USER_ERROR);
            }
        }
        
        

        //+询价通用处理
        if($_flag===1||$_flag===2){
            //询价
            $_dealer_bns = array_item($modifyItems, 'dealer_bn');
            $_l2s_cr = $this->get_l2sBns($_dealer_bns, true);
            $_inquiry_data = array();
            foreach($_l2s_cr as $_dealer_bn => $_supplier_bn){
                $_inquiry_data[$_supplier_bn] = $modifyItems[$_dealer_bn]['nums'];
            }

            $_inquiry_result = $this->inquiry($supplierId, $orderid, $_inquiry_data);
/*
            if(!$_inquiry_result){
                trigger_error('修改的商品有错误,不能修改', E_USER_ERROR);
            }
*/          
            $_inquiry_result = $_inquiry_result['items'];
            $_err_msg = '';
            foreach($_inquiry_result as $_item){
                if ($_item['stock'] === 0 && $_old_order_items[$_item['dealer_bn']]['nums']===0){
                    trigger_error(sprintf(__('货品: %s 供应商库存为0,请将货品数量改为0否则无法修改保存'), $_item['dealer_bn']), E_USER_ERROR);
                }
                if ($_item['status'] == 'shelves' || $_item['status'] == 'deleted'){
                    trigger_error(sprintf(__('货品: %s 为已下架商品或删除商品,请先删除再下单'), $_item['dealer_bn']), E_USER_ERROR);
                }
            }
            //看价格是否与询单价格相同,如果不同则报错
            if($_flag===2){
                foreach($_inquiry_result as $_item){
                    if($_item['po_price'] != $modifyItems[$_item['dealer_bn']]['po_price']){
                        trigger_error(sprintf(__('货品: %s 价格与之前询单价格不同,请重新询单, 之前价格为%s,之后价格为%s'), $_item['dealer_bn'], $_item['po_price'], $modifyItems[$_item['dealer_bn']]['po_price']), E_USER_ERROR);
                    }
                }
            }
        }
        
        //-询价通用处理
        if($_flag===0){
            //修改本地库存,订单明细及订单状态
            $_tmp_old_bns = array_keys($_old_order_items);
            $this->_modifyLocalItems($orderid, $modifyItems, $_tmp_old_bns);
        }else if($_flag===1){
            //修改本地库存,订单明细及订单状态
            $_tmp_old_bns = array_keys($_old_order_items);
            $this->_modifySupplierItems($orderid, $modifyItems, $_old_order_items);
        }else if($_flag===2){
            if ($all_items['supplier'][$supplierId]['po'][$poId]['pay_status']==1&&
                $all_items['supplier'][$supplierId]['po'][$poId]['status']=='active'){
                $this->pendingPo($poId);                            
            }

            //生成提交所用的数据
            $_edit_data = $modifyItems;
            foreach($_edit_data as $_k => $_item){
                $_edit_data[$_k]['price'] = $_item['po_price'];
                unset($_edit_data[$_k]['po_price']);
                unset($_edit_data[$_k]['product_id']);
            }
            //调用平台订单修改api
            $send = array(
                'id' => $supplierId,
                'order_id' => $poId,
                'items' => $_edit_data);
            $api_utility = &$this->system->api_call(PLATFORM,PLATFORM_HOST,PLATFORM_PATH,PLATFORM_PORT,$this->_token);

            $result = $api_utility->getApiData('editOrder', API_VERSION, $send);
            $api_utility->trigger_all_errors();
            //更新order_items 数据和库存冻结量
            $_tmp_old_bns = array_keys($_old_order_items);

           $this->_modifySupplierItems($orderid, $modifyItems, $_old_order_items);
        }
        $this->_accountOrders($orderid);
        return true;
    }

    function _modifySupplierItems($orderid, $modifyItems, $old_order_items){
        $oProduct = $this->system->loadModel('goods/products');    //生成订单前检查库存        
        foreach($old_order_items as $_bn => $_old_order_item){
            if (!isset($modifyItems[$_bn])){
                $_sql = sprintf('update sdb_products set freez=freez - %d where bn=\'%s\' and order_id=%s',
                                $_old_order_item['nums'], $_old_order_item['dealer_bn'], $orderid);
                $this->db->exec($_sql);
                $_sql = sprintf('update sdb_order_items set nums=nums - %d where bn=\'%s\' and order_id=%s',
                                $_old_order_item['nums'], $_old_order_item['dealer_bn'], $orderid);
                $this->db->exec($_sql);

                
            }else if($_old_order_item['nums'] != $modifyItems[$_bn]['nums']){
                $_sql = sprintf('update sdb_products set freez=freez + %d where bn=\'%s\' and order_id=%s',
                                $modifyItems[$_bn]['nums']-$_old_order_item['nums'], $_old_order_item['dealer_bn'], $orderid);
                $this->db->exec($_sql);
                $_sql = sprintf('update sdb_order_items set nums=nums + %d where bn=\'%s\' and order_id=%s',
                                $modifyItems[$_bn]['nums']-$_old_order_item['nums'], $_old_order_item['dealer_bn'], $orderid);
                $this->db->exec($_sql);

            }else{
                continue;
            }

        }
        $_sql = sprintf('delete from sdb_order_items where nums=0 and order_id=%s', $orderid);
        $this->db->exec($_sql);
                
    }
    
    /**
     * 本地订单明细修改
     *
     * @author bryant
     * @date 2009-05-31
     * @param int $orderid, 订单id(b2c端order_id)
     * @param array $modifyItems, 订单修改货品细列表
     *                          array( 
     *                              #b2c端货号# => array( 
     *                                   'dealer_bn' => xxx, //b2c端bn号
     *                                   'price' => xxx ,//b2c端价格
     *                                   'po_price' => xxx ,//采购单价格
     *                                   'nums' => xxx
     *                                   'product_id' => xxx),
     *                               ...
     *                          )
     * @param array $old_bns, 原有订单里的所有货品的货品号数组
     *         array('bn001','bn002')
     * @param array $is_local, 是否为纯粹的本地商品
     * @return boolean
     */
    function _modifyLocalItems($orderid, $modifyItems, $old_bns){
        $oOrder = $this->system->loadModel('trading/order');
        $_orderInfo = $oOrder->getFieldById($orderid, array('status', 'pay_status', 'ship_status'));
        if($_orderInfo['status'] == 'active' || $_orderInfo['pay_status'] == 0 || $_orderInfo['ship_status'] == 0){
            
            //更新所有order_items
            $add_Store = array();
            $oProduct = $this->system->loadModel('goods/products');    //生成订单前检查库存

            $_tmp_new_bns = array_keys($modifyItems);
            $_tmp_old_bns = &$old_bns;

            $new_pdts = array_intersect((array)$_tmp_old_bns, (array)$_tmp_new_bns);
            $del_pdts = array_diff((array)$_tmp_old_bns, (array)$new_pdts);

            
            foreach($new_pdts as $_bn){
                $_product_id = $modifyItems[$_bn]['product_id'];
                $_pdts_store = $oProduct->getFieldById($_product_id, array('store', 'freez'));
                if($_pdts_store['store'] !== null && $_pdts_store['store'] !== ''){
                    $sql = sprintf('SELECT nums, name  FROM sdb_order_items WHERE order_id=%s AND product_id = %d', $orderid, $_product_id);
                    $_result = $this->db->selectrow($sql);
                    $_store = intval($_pdts_store['store']) - intval($_pdts_store['freez']) + intval($_result['nums']);
                    if($_store < $modifyItems[$_bn]['nums']){
                        trigger_error($_result.__(':库存不足'), E_USER_ERROR);
                    }
                    $add_store[$_product_id] = intval($modifyItems[$_bn]['nums']) - intval($_result['nums']);
                }
            }
            foreach($modifyItems as $_k => $_item){
                $_data  = array(
                    'nums' => $_item['nums'],
                    'price' => $_item['price'],
                    'amount' => $_item['nums'] * $_item['price'],
                    );

                $rs = $this->db->query(sprintf('select * from sdb_order_items where bn=\'%s\' and order_id=%s', $_item['dealer_bn'], $orderid));
                $sql = $this->db->getUpdateSql($rs,$_data);
                $this->db->exec($sql);

                if(isset($add_store[$_item['product_id']])){
                    if($add_store[$productId]>=0){
                    $this->db->exec("UPDATE sdb_products SET freez = freez + ".$add_store[$productId]." WHERE product_id = ".$productId);
                    }
                }                                        
            }
            //删除去掉的商品
            foreach($del_pdts as $_bn){
                $_sql = sprintf('delete from sdb_order_items where bn=\'%s\' and order_id=%s', $_bn, $orderid);
                $this->db->exec($_sql);
            }

        }else{
            //错误信息
            trigger_error('此商品非活动订单或已发货或已付款', E_USER_ERROR);
        }        
    }

    function _accountOrders($orderid){
        //更新到order
        $_sql = sprintf('update sdb_order_items set amount=price*nums where order_id=%s', $orderid);
        $this->db->exec($_sql);

        $sql = sprintf('select sum(i.price) sum_price,sum(i.nums) sum_nums,sum(i.amount) sum_amount,sum(p.weight*i.nums) weight
                           from sdb_order_items i
                           left join sdb_products p on i.product_id=p.product_id
                           where order_id=%s', $orderid);
        $_order_sum = $this->db->selectrow($sql);
        $oOrder = $this->system->loadModel('trading/order');
        $order_info = $oOrder->getFieldById($orderid);
        $_data['cost_item'] = $_order_sum['sum_amount'];
        $_data['total_amount'] = $_order_sum['sum_amount'] + $order_info['cost_freight'] + $order_info['cost_protect'] + $order_info['cost_payment'] + $order_info['cost_tax'] - $order_info['discount'] - $order_info['pmt_amount'];
        $_rate = $oOrder->getFieldById($orderid, array('cur_rate'));

        $_data['final_amount'] = $_data['total_amount'] * $_rate['cur_rate'];
        if($oOrder->toEdit($orderid, $_data)){
            $oOrder->addLog(__('订单编辑'), $this->op_id?$this->op_id:null, $this->op_name?$this->op_name:null , __('编辑') );
            return true;
        }else{
            return false;
        }
    }

    /**
     * 通过一组本地货号获取本地货号对应供应商货号的对应表  //todo 日后会被取消
     *
     * @author bryant
     * @date 2009-05-31
     * @param array $bns, 供应商bn数组
     *       array('bn001','bn002',...)
     * @param boolean $is_ref, 返回的是bn对应表还是供应商bn数组
     * @return array 
     *       if $is_ref=false
     *          array('xx001','xx002',...) //返回供应商货号数组
     *       else
     *          array(
     *              #经销商货号# => #供应商货号#,
     *              ...
     *            )
     */ 
    function get_l2sBns($bns, $is_ref=false){
        foreach($bns as $_bn){
            $_where_bns[] = sprintf('\'%s\'',addslashes($_bn));
        }

        $_sql = sprintf('select local_bn, source_bn
                             from sdb_supplier_pdtbn 
                             where local_bn in(%s) and `default`=\'true\'', implode(',', $_where_bns));
        $_supplier_pdtbn = $this->db->select($_sql);
        $result = array();
        if ($is_ref){
            foreach($_supplier_pdtbn as $_k => $_item){
                $result[$_item['local_bn']] = $_item['source_bn'];
            }
        }else{
            $result = array_item($_supplier_pdtbn, 'source_bn');
        }
        return $result;
    }

    /**
     * 通过一组供应商货号获取供应商货号对应本地货号的对应表       
     *
     * @param array $bns, 供应商bn数组
     *       array('bn001','bn002',...)
     * @param boolean $is_ref, 返回的是bn对应表还是经销商bn数组
     * @return array 
     *       if $is_ref=false
     *          array('xx001','xx002',...) //返回经销商(b2c)bn数组
     *       else
     *          array(
     *              #供应商货号# => #经销商货号#,
     *              ...
     *            )
     */
    function get_s2lBns($bns, $is_ref='false'){
        //todo 进参加上supplier_id
        foreach($bns as $_bn){
            $_where_bns[] = sprintf('\'%s\'',addslashes($_bn));
        }
        /* sdb_supplier_pdtbn 现在记录了以前的关联关系 2010-01-26 19:07 wubin
        $_sql = sprintf('select local_bn, source_bn
                             from sdb_supplier_pdtbn 
                             where source_bn in(%s)', implode(',', $_where_bns));
        */
        $_sql = sprintf('SELECT s.local_bn, s.source_bn FROM sdb_supplier_pdtbn AS s
                         RIGHT JOIN sdb_products AS p ON p.bn = s. local_bn
                         WHERE  s.source_bn IN(%s)', implode(',', $_where_bns));
        $_supplier_pdtbn = $this->db->select($_sql);
        $result = array();
        if ($is_ref){
            foreach($_supplier_pdtbn as $_k => $_item){
                $result[$_item['source_bn']] = $_item['local_bn'];
            }
        }else{
            $result = array_item($_supplier_pdtbn, 'source_bn');
        }
        return $result;
    }
    
    /*** 通过一组货品bn进行询盘  更新库存
     *
     * @author bryant
     * @date 2009-05-31
     * @param int $supplierId, 供应商Id
     * @param int $orderid, b2c订单id
     * @param array $bns, 供应商bn数组
     *       array('bn001'=> 购买量,...)
     * @return array 返回询盘后的价格,库存,货品状态,及库存状态
     *                          array(
     *                              'total_amount' => xxx 总价
     *                              'items' =>
     *                                  array(
     *                                      #经销商bn号 => array(  
     *                                          'dealer_bn' => xxxx,
     *                                          'supplier_bn' => xxxx,
     *                                          'price' => xxxx,
     *                                          'po_price' => xxxx,  //po端价格   
     *                                          'stock' => xxxx, //库存量 如果库存为无限库存 则值为-1
     *                                          'stock_status' => enum(0,1,2,3),  //0充足,1紧张,2不足,3无货
     *                                          'status' => enum('shelves','deleted','normal') //shelves下架货品,deleted删除货品,正常货品
     *                                         ),
     *                                      ...
     *                                     );
     */
    function inquiry($supplierId, $orderid, $bns){
        if(empty($bns)||!is_array($bns)){
            return false;

        }
        $api_utility = &$this->system->api_call(PLATFORM,PLATFORM_HOST,PLATFORM_PATH,PLATFORM_PORT,$this->_token);
        $result_items = array();
        $send = array(
            'id' => $supplierId,
            'bns' => array_keys($bns),
            );

        $inquiry_result = $api_utility->getApiData('inquiry', API_VERSION, $send);
        $api_utility->trigger_all_errors();
        $store_mark = 0;    //询价单是否有货
        foreach($inquiry_result as $k => $item){
            $result_items[$k] = array(
                'supplier_bn' => $item['bn'],
                'po_price' => $item['price'],
                'stock' => $item['stock'],
                );
                
            if($item['status'] == 'normal'){
                if($item['stock'] === null || $item['store'] === ''){
                    $result_items[$k]['stock_status'] = 0;//库存充足
                    $store_mark = 1;
                }else if($item['stock'] > ($bns[$item['bn']] + $item['threshold'])){//库存充足
                    $result_items[$k]['stock_status'] = 0;
                    $store_mark = 1;
                }else if ($item['stock'] > $bns[$item['bn']]){//库存紧张
                    $result_items[$k]['stock_status'] = 1;
                    $store_mark = 1;
                }else if($item['stock'] > 0){
                    $result_items[$k]['stock_status'] = 2;
                    $store_mark = 1;
                }else{
                    $result_items[$k]['stock_status'] = 3;
                }
            }
            $result_items[$k]['status'] = $item['status'];
        }
        
        $_s2l_cr = $this->get_s2lBns(array_item($result_items, 'supplier_bn'), true);
        $_where_bns = array();
        
        // 成本价同步
        $oCostSync = $this->system->loadModel('distribution/costsync');
        
        foreach($result_items as $_k => $_item){
            $result_items[$_k]['dealer_bn'] = $_s2l_cr[$_item['supplier_bn']];
            $result_items[$_k]['nums'] = $bns[$_item['supplier_bn']];

            $_where_bns[] = sprintf('\'%s\'',addslashes($result_items[$_k]['dealer_bn']));
            
            // 获取到货品的goods_id 因为有人来商品链接要用到 2009-12-16 11:12 wubin
            $sSql = 'SELECT i.name,i.price, i.product_id,p.goods_id FROM sdb_order_items AS i
                    LEFT JOIN sdb_products AS p ON p.product_id = i.product_id WHERE i.bn=\''.$result_items[$_k]['dealer_bn'].'\' and i.order_id='.$orderid;
            $aProduct = $this->db->selectrow($sSql);
            // 如果产品被删除除的话 产品信息从sdb_order_items上取 2010-01-26 18:56 wubin
            if(empty($aProduct)) {
                $sSql = 'SELECT i.name,i.price, i.product_id,i.bn 
                         FROM sdb_order_items AS i 
                         LEFT JOIN sdb_supplier_pdtbn AS s ON s.local_bn = i.bn 
                         WHERE s.source_bn=\''.$_item['supplier_bn'].'\' and i.order_id='.$orderid;
                $aProduct = $this->db->selectrow($sSql);
                
                // 更新sdb_order_items 2010-01-28 14:46 wubin 将原订单的货BN号更新成最新的BN
                $sSql = "SELECT product_id FROM sdb_products WHERE bn ='".$result_items[$_k]['dealer_bn']."'";
                $aTemp = $this->db->selectrow($sSql);
                // 如果能在数据库中找到对应的货品数据,将订单的bn,product_id 更新
                if($aTemp) {
                    $aTemp['bn'] = $result_items[$_k]['dealer_bn'];
                    $rs = $this->db->exec("SELECT * FROM sdb_order_items WHERE order_id='".$orderid."' AND bn='".$aProduct['bn']."'");
                    $sSql = $this->db->getUpdateSql($rs, $aTemp);
                    $this->db->exec($sSql);
                }
                unset($aProduct['bn']);
            }
            $result_items[$_k] = array_merge((array)$aProduct, $result_items[$_k]);
            // 更新货品成本价 wubin 2009-09-15 14:29:12
            $oCostSync->updateAloneProductCost($aProduct['product_id'],$_item['po_price']);
        }

        $_sql = sprintf('select bn as dealer_bn,nums,supplier_id from sdb_order_items where order_id=%s and bn in(%s)', $orderid, implode(',', $_where_bns));
        $_order_items = $this->db->select($_sql);

        $_order_items = array_change_key($_order_items, 'dealer_bn');
        
        $result_total_amount = 0;//总价
        foreach($result_items as $_k => $_item){
            //如果货品已经指定为某个供应商货品,
            if($_order_items[$_item['dealer_bn']]['supplier_id'] !== null){
                $result_items[$_k]['store'] += $_order_items[$_item['dealer_bn']]['nums'];
            }
            //通过更新的库存计算amount
            if($_item['stock']<=0){
                $result_items[$_k]['stock'] = 0;
            }
            $result_items[$_k]['amount'] = $bns[$_item['supplier_bn']] * $_item['po_price'];
            $result_total_amount += $result_items[$_k]['amount'];
            
            //如果库存为''/null 则将库存状态置为-1
            if($_item['stock']==null || $_item['stock']=='') {
                $result_items[$_k]['stock'] = -1;
            }
            
            $_data = array('store' => ($result_items[$_k]['stock']!=-1)?$result_items[$_k]['stock']:null);
            $_sql = sprintf('SELECT * FROM sdb_products WHERE bn=\'%s\'', addslashes($_item['dealer_bn']));
            $rs = $this->db->exec($_sql);
            $_sql = $this->db->getUpdateSql($rs, $_data);
            $this->db->exec($_sql);
        }

        $result_items = array_change_key($result_items, 'dealer_bn');
        $result = array(
            'items' => $result_items,
            'store_status' => $store_mark,
            'total_amount' => $result_total_amount);

        return $result;
    }
    
    /**
     * 暂停po单
     *
     * @author bryant
     * @date 2009-05-31
     * @param int $orderid, po单单号
     * @return boolean
     */
    function pendingPo($orderid){
        $api_utility = &$this->system->api_call(PLATFORM,PLATFORM_HOST,PLATFORM_PATH,PLATFORM_PORT,$this->_token);
        $send = array(
            'id' => $orderid
            );

        $api_utility->getApiData('setOrderPending', API_VERSION, $send);
        $api_utility->trigger_all_errors();            
        return true;
    }

    /**
     * 取消暂停po单
     *
     * @author bryant
     * @date 2009-05-31
     * @param int $orderid, po单单号
     * @return boolean
     */
    function cancelPendingPo($orderid){
        $api_utility = &$this->system->api_call(PLATFORM,PLATFORM_HOST,PLATFORM_PATH,PLATFORM_PORT,$this->_token);
        $send = array(
            'id' => $orderid
            );

        $api_utility->getApiData('setOrderAwake', API_VERSION, $send);
        $api_utility->trigger_all_errors();            

        return true;
    }

    /**
     * po单对账  
     * 如果po单状态为支付中状态需要进行的对账操作
     *
     * @author bryant
     * @date 2009-05-31
     * @param int $orderid, po单单号
     * @return boolean
     */
    function reconciliation($orderid){
        $api_utility = &$this->system->api_call(PLATFORM,PLATFORM_HOST,PLATFORM_PATH,PLATFORM_PORT,$this->_token);
        $send = array(
            'id' => $orderid
            );

        $api_utility->getApiData('reconciliation', API_VERSION, $send);
        $api_utility->trigger_all_errors();                        
        return true;
    }

    /**
     * 通过po单单号用与存款支付
     *
     * @author bryant
     * @date 2009-05-31
     * @param int $orderid, po单单号
     * @return boolean
     */
    function payByDeposits($orderid, $payId){
        $api_utility = &$this->system->api_call(PLATFORM,PLATFORM_HOST,PLATFORM_PATH,PLATFORM_PORT,$this->_token);
        $send = array(
            'pay_id' => $payId,
            'id' => $orderid
            );

        $api_utility->getApiData('PayByDeposits', API_VERSION, $send);
        $api_utility->trigger_all_errors();
        return true;        
        
        //支付是否需要钱 //todo sy
    }


    /**
     * 通过供应商id取供应商Doamin
     *
     * @author bryant
     * @date 2009-05-31
     * @param int $supplierId, 供应商id号
     * @param enum(true,false) $is_real_url, 是否为完整的url(带https/http)
     * @return boolean
     */
    function getSupplierDomain($supplierId, $is_real_url=false){
        $api_utility = &$this->system->api_call(PLATFORM,PLATFORM_HOST,PLATFORM_PATH,PLATFORM_PORT,$this->_token);
        $send = array(
            'id' => $supplierId
            );

        $domain = $api_utility->getApiData('getDomain', API_VERSION, $send);
        $api_utility->trigger_all_errors();
        if(!$is_real_url) {
            $_pattern = "/^(((http|https):\/\/)?)([-A-Z0-9+&@#\/%?=~_|!:,.;]*[-A-Z0-9+&@#%=~_|])\/*$/i";
            $_replacement = "$4";
            $domain['domain'] = preg_replace($_pattern, $_replacement, $domain['domain']);
        }
        return $domain['domain'];
    }

    /**
     * 获取配送地区列表
     *
     * @author bryant
     * @date 2009-05-31
     * @param int $supplierId, 供应商ID
     * @return array 
     *                array(
     *                    0 => array(),
     *                    ...
     *                 )
     */
    function getDlyArea($supplierId){
        $_api_domain = $this->getSupplierDomain($supplierId);
        $api_utility = &$this->system->api_call('b2b-'.strval($supplierId),$_api_domain,'/api.php',80,$this->_token);
        $_result = $api_utility->getApiData('search_dly_area', API_VERSION);
        $api_utility->trigger_all_errors();                                    
        return $_result;                
    }
    
    
    /**
     * 获取配送物流公司列表
     *
     * @author bryant
     * @date 2009-05-31
     * @param int $supplierId, 供应商ID
     * @return array
     *                array(
     *                    0 => array(),
     *                    ...
     *                 )
     */
    function getDlyCorp($supplierId){
        $_api_domain = $this->getSupplierDomain($supplierId);        
        $api_utility = &$this->system->api_call('b2b-'.strval($supplierId),$_api_domain,'/api.php',80,$this->_token);
        $_result = $api_utility->getApiData('search_dly_corp', API_VERSION);
        $api_utility->trigger_all_errors();                                                
        return $_result;                
    }
    

    /**
     * 获取配送物流公司列表
     *
     * @param int $supplierId, 供应商ID     
     * @return array
     *                array(
     *                    0 => array(),
     *                    ...
     *                 )
     */
    function getDlyType($supplierId){
        $_api_domain = $this->getSupplierDomain($supplierId);        
        $api_utility = &$this->system->api_call('b2b-'.strval($supplierId),$_api_domain,'/api.php',80,$this->_token);
        $_result = $api_utility->getApiData('search_dly_type', API_VERSION);
        $api_utility->trigger_all_errors();

        return $_result;                
    }

    /**
     * 获取配送物流公司列表
     *
     * @author bryant
     * @date 2009-05-31
     * @param int $supplierId, 供应商ID     
     * @return array 
     *                array(
     *                    0 => array(),
     *                    ...
     *                 )
     */
    function getDlyHarea($supplierId){
        $_api_domain = $this->getSupplierDomain($supplierId);        
        $api_utility = &$this->system->api_call('b2b-'.strval($supplierId),$_api_domain,'/api.php',80,$this->_token);        
        $_result = $api_utility->getApiData('search_dly_h_area', API_VERSION);
        $api_utility->trigger_all_errors();

        return $_result;                
    }

    /**
     * 获取货币列表
     *
     * @author bryant
     * @date 2009-05-31
     * @param int $supplierId, 供应商ID     
     * @return array 
     *                array(
     *                    0 => array(),
     *                    ...
     *                 )
     */
    
    function getCurList($supplierId){
        $_api_domain = $this->getSupplierDomain($supplierId);
        $api_utility = &$this->system->api_call('b2b-'.strval($supplierId),$_api_domain,'/api.php',80,$this->_token);        
        $_result = $api_utility->getApiData('search_cur_list', API_VERSION);
        $api_utility->trigger_all_errors();
        return $_result;                
        
    }    
    /**
     * 获得支付方式
     *
     * @author bryant
     * @date 2009-05-31
     * @param int $supplierId, 供应商ID     
     * @return array 
     *                array(
     *                        0 => array(
     *                           'custom_name' => xxx, //自定义支付网关名称
     *                           'pay_type' => xxx, //支付网关代号(英文缩写)
     *                           'fee' => xxx, //手续费
     *                           'des' => xxx, //描述信息
     *                           'orderlist' => xxx, //排序
     *                           'disabled' => xxx, //无效
     *                          ),
     *                        ...
     *                      )
     *                   )
     */
    function getPaymentCfg($supplierId){
        $api_utility = &$this->system->api_call(PLATFORM,PLATFORM_HOST,PLATFORM_PATH,PLATFORM_PORT,$this->_token); 
        $send = array('id'=>$supplierId); 
        $_result = $api_utility->getApiData('getPaymentCfg', API_VERSION, $send); 
        $api_utility->trigger_all_errors();
        return $_result;                
    }

    function getPOrderById($poId){
        $api_utility = &$this->system->api_call(PLATFORM,PLATFORM_HOST,PLATFORM_PATH,PLATFORM_PORT,$this->_token);
        $send = array('id'=>$poId);
        $_result = $api_utility->getApiData('getPOrderById', API_VERSION, $send);
        $api_utility->trigger_all_errors();
        return $_result;        
    }

    /**
     * 获取地区子节点地区
     *
     * @author bryant
     * @date 2009-06-20
     * @param int $supplierId, 供应商ID
     * @param int $regionId, 地区Id
     * @return array 
     *                array(
     *                    0 => array(
     *                        'region_id' => xxxx, //地区ID
     *                        'local_name' => xxxx, //地区名称
     *                        'is_node' => xxxx, //是否存在子节点(1：有 0：无)
     *                      ),
     *                    ...
     *                 )
     */
    function getSubRegions($supplierId, $regionId=0){
        $_api_domain = $this->getSupplierDomain($supplierId);        
        $api_utility = &$this->system->api_call('b2b-'.strval($supplierId),$_api_domain,'/api.php',80,$this->_token);
        $send = array('p_region_id'=>intval($regionId));
        $_result = $api_utility->getApiData('search_sub_regions', API_VERSION, $send);
        $api_utility->trigger_all_errors();
        return $_result;                
    }

    /**
     * 获取地区的配送方式
     *
     * @author bryant
     * @date 2009-06-20
     * @param int $supplierId, 供应商ID
     * @param int $regionId, 地区Id
     * @return array 
     *                array(
     *                    0 => array(
     *                        'dt_id' => xxx, //设置成功
     *                        'dt_name' =>xxx, //配送名称
     *                        'pad' =>xxx, //是否支持货到付款
     *                        'protect' =>xxx, //是否保价
     *                        'dt_config' =>xxx, //配置
     *                        'expressions' =>xxx, //配送公式
     *                        'detail' =>xxx, //配送说明
     *                        'minprice' =>xxx, //配送最小金额
     *                      ),
     *                    ...
     *                 )
     */
    function getDlyTypeByArea($supplierId, $areaId=0){
        $_api_domain = $this->getSupplierDomain($supplierId);        
        $api_utility = &$this->system->api_call('b2b-'.strval($supplierId),$_api_domain,'/api.php',80,$this->_token);
        $send = array('area_id'=>intval($areaId));
        $_result = $api_utility->getApiData('search_dltype_byarea', API_VERSION, $send);
        $api_utility->trigger_all_errors();
        return $_result;                
    }
    
    /**
     * 通过配送方式id获取配送方式明细
     *
     * @param int $supplierId, 供应商ID
     * @param int $deliveryId, 配送方式ID          
     * @return array
     *                array(
     *                    0 => array(),
     *                    ...
     *                 )
     */
    function getDlyIsPay($supplierId, $deliveryId, $areaId=0){
        //todo 取配送方式详细内容array(pad=>1);
        $_api_domain = $this->getSupplierDomain($supplierId);        
        $api_utility = &$this->system->api_call('b2b-'.strval($supplierId),$_api_domain,'/api.php',80,$this->_token);
        $send = array('delivery_id'=>intval($deliveryId),
                    'area_id'=>intval($areaId));
        $_result = $api_utility->getApiData('search_dly_type_byid', API_VERSION, $send);
        $api_utility->trigger_all_errors();
        return $_result;                
        
    }
    
    /**
     * 获取上游的订单相关配置
     *
     * @param int $supplier_id
     * @return array
     *              array(
                        'cur_sign' => xxx, //货币符号
                        'decimals' => xxx, //前台商品价格精确到几位
                        'carryset' => xxx, //价格进位方式
                        'dec_point' => xxx, //小数符号
                        'thousands_sep' => xxx, //千位符号
     *                  'decimal_digit' => xxx, //订单金额取整位数, 0:整数取整,1:取整到1位小数,2:取整到2位小数,取整到3位小数....
     *                  'decimal_type' => xxx, //订单金额取整方式, 0:四舍五入,1:向上取整,2:向下取整
     *                  'trigger_tax' => xxx, //是否设置含税价格,0/1
     *                  'tax_ratio' => xxx //税率(去除%后的数字)
     *              )
     */
    function getOrderSetting($supplier_id){
        $api_utility = &$this->system->api_call(PLATFORM,PLATFORM_HOST,PLATFORM_PATH,PLATFORM_PORT,$this->_token);
        $setting = $api_utility->getApiData('getOrderSetting',API_VERSION,array('id'=>$supplier_id));
        if($setting){
            return $setting;
        }else{
            $api_utility->trigger_all_errors();
            return array();
        }
    }
}
