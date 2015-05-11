<?php
/**
 * 采购单模型
 * @copyright 2003-2009 ShopEx
 * @author gesion
 * @license Commercial
 */

include_once 'shopObject.php';

class mdl_po extends shopObject {

    var $idColumn     = 'order_id';
    var $textColumn   = 'order_id';
    var $defaultCols  = 'dealer_order_id,supplier_id,order_id,good_items,createtime,total_amount,pay_status,ship_status';
    var $appendCols   = 'pay_status,ship_status,status,mark_type';
    var $adminCtl     = 'order/order';
    var $defaultOrder = array('createtime', 'DESC');
    var $tableName    = 'sdb_orders';
    var $hasTag       = false;
    var $api_utility  = null;

    function mdl_po() {
        parent::shopObject();
        $this->_tolken = $this->system->getConf('certificate.token');
        // $this->_tolken = '00002';
        // $this->system->setConf('certificate.id', "20000");
        $this->api_utility = &$this->system->api_call(PLATFORM, PLATFORM_HOST, PLATFORM_PATH, PLATFORM_PORT, $this->_tolken);
    }

    function searchOptions() {
        return array(
            'dealer_order_id'     => '订单号',
            'order_id'            => '采购单号',
            'logi_no'             => '物流单号',
            'bn'                  => '商品货号',
            'supplier_brief_name' => '供应商名称',
            // 'goods_name'          => '商品名称',
            // 'member_name'         => '会员用户名',+
            // 'ship_name'           => '收货人姓名',
            // 'ship_addr'           => '收货人地址',
            // 'ship_tel'            => '收货人电话',
            // 'mark_text'           => '订单备注'
        );
    }
    
    function getColumns() {
        return array(
            '_cmd'=>array('label'=>__('操作'),'width'=>80,'html'=>'po/command.html'),
            'dealer_order_id' => array('label' => '订单号', 'class' => 'span-3', 'primary' => true),
            'supplier_id'     => array('label' => '供应商名称', 'class' => 'span-2', 'type' => 'object:distribution/supplier','editable' => false,'filtertype'=>'yes','filterdefalut'=>true),
            'order_id'        => array('label' => '采购单号(供应商订单号)', 'class' => 'span-4', 'primary' => true ,'searchtype' => 'has','editable' => false,'filterdefalut'=>true,'filtertype'=>'custom','filtercustom'=>array('='=>'等于')),
            'good_items'      => array('label' => '订购商品', 'class' => 'span-3', 'type' => 'good_items', 'noOrder' => true),
            // 'order_tips'      => array('label' => '提示', 'class' => 'span-1', 'sql' => 'order_id', 'type' => 'order_tips'),
            'createtime'      => array('label' => '下采购单日期', 'class' => 'span-3', 'type' => 'time'),
            'acttime'         => array('label' => '最后更新', 'class' => 'span-3', 'type' => 'time:SDATE_STIME'),
            // 'print_status'    => array('label' => '打印', 'class' => 'span-4', 'type' => 'print_status'),
            'total_amount'    => array('label' => '采购单总额', 'class' => 'span-2', 'type' => 'money'),
            'ship_name'       => array('label' => '收货人', 'class' => 'span-2', 'type' => 'ship_name'),
            'pay_status'      => array('label' => '采购单付款状态', 'class' => 'span-3', 'editable' => false,'filtertype'=>'yes','filterdefalut'=>true, 'type' => array (0 => __('未支付'),1 => __('已支付'),2 => __('处理中'),3 => __('部分付款'),4 => __('部分退款'),5 => __('全额退款')) ),
            'ship_status'     => array('label' => '发货状态', 'class' => 'span-2','editable' => false,'filtertype'=>'yes','filterdefalut'=>true,'type' => array ( 0 => __('未发货'),1 => __('已发货'),2 => __('部分发货'),3 => __('部分退货'), 4 => __('已退货'))),
            'shipping'        => array('label' => '配送方式', 'class' => 'span-2'),
            'cost_freight'    => array('label' => '配送费用', 'class' => 'span-2', 'type' => 'money'),
            'payment'         => array('label' => '支付方式', 'class' => 'span-2', 'type' => 'pp'),
            //'member_id'       => array('label' => '会员用户名', 'class' => 'span-2', 'type' => 'object:member'),
            // 'status'          => array('label' => '订单状态', 'class' => 'span-2', 'type' => 'status'),
            // 'confirm'         => array('label' => '确认状态', 'class' => 'span-2', 'type' => 'confirm'),
            'ship_tel'        => array('label' => '收货人电话', 'class' => 'span-2'),
            'ship_area'       => array('label' => '收货地区', 'class' => 'span-5', 'type' => 'region'),
            'ship_addr'       => array('label' => '收货地址', 'class' => 'span-5'),
            // 'refer_id'        => array('label' => '订单来源ID', 'class' => 'span-2', 'type' => 'refer'),
            // 'refer_url'       => array('label' => '订单来源网址', 'class' => 'span-4', 'type' => 'refer'),
            'member_memo'       => array('label' => '订单备注', 'class' => 'span-4', 'type' => 'member_memo'),
            //'mark_text'       => array('label' => '订单备注', 'class' => 'span-4', 'type' => 'mark_text', 'modifier' => 'row'),
            // 'mark_type'       => array('label' => '订单备注图标', 'class' => 'span-4')
        );
    }

    /**
     * 重建过滤数组
     *
     * @param array $filter
     * @return array
     */
    function rebuildFilter($filter, &$orderby) {
        $re = array();
        if (isset($filter['_finder']['orderBy']) && isset($filter['_finder']['orderType'])) {
            $orderby = array(
                $filter['_finder']['orderBy'],
                $filter['_finder']['orderType']
            );
        }
        if (isset($filter['status'])) {
            $re['status'] = $filter['status'];
        }
        if (isset($filter['pay_status'])) {
            $re['pay_status'] = $filter['pay_status'];
        }
        if (isset($filter['ship_status'])) {
            $re['ship_status'] = $filter['ship_status'];
        }
        if (isset($filter['delivery'])) {
            $re['delivery'] = $filter['delivery'];
        }
        if (isset($filter['payment'])) {
            $re['payment'] = $filter['payment'];
        }
        $op = $this->searchOptions();
        foreach ($op as $key => $val) {
            if (isset($filter[$key])) {
                $re[$key] = $filter[$key];
            }
        }
        return $re;
    }

    /**
     * Get PO list
     *
     * @param string $cols
     * @param array $filter
     * @param int $start
     * @param int $limit
     * @param int $count
     * @param string $orderby
     * @return array
     */
    function getList($cols, $filter = '', $start = 0, $limit = 20, &$count, $orderby = null) {
        $ident = md5($cols . print_r($filter, true) . $start . $limit);
        
        if (!$this->_dbstorage[$ident]) {
            if (!$cols) {
                $cols = $this->defaultCols;
            }
            if (!empty($this->appendCols)) {
                $cols .= ',' . $this->appendCols;
            }
            $filter = $this->rebuildFilter($filter, $orderby);
            if (isset($_GET['supplier_id'])) {
                $filter['supplier_id'] = $_GET['supplier_id'];
            }
            if ($filter['supplier_brief_name']) {
                $res = $this->db->select('SELECT supplier_id FROM sdb_supplier WHERE supplier_brief_name = "' . $filter['supplier_brief_name'] . '" LIMIT 1');
                if (count($res)) {
                    $filter['supplier_id'] = array($res[0]['supplier_id']);
                } else {
                    return;
                }
                unset($filter['supplier_brief_name']);
            }
            if (!$orderby) {
                $orderby = $this->defaultOrder;
            }
            $send = array(
                'filter_type' => 'json',
                'filter'      => count($filter) > 0 ? json_encode($filter) : '',
                'orderby'     => implode(' ', $orderby),
                'start'       => $start,
                'limit'       => $limit,
                'return_data' => 'json'
            );
            $result = $this->api_utility->getApiData('getDealerOrderListByDealerId', API_VERSION, $send, true, true);
            if ($result === false) {
                echo $this->api_utility->error;
                return false;
            } else {
                $count = $result['row_count'];
                $POrders = $result['struct'];
                $this->getGoodItems($POrders);
                $this->getSupplierName($POrders);
                // e($POrders);
                $this->_dbstorage[$ident] = $POrders;
            }
        }
        
        return $this->_dbstorage[$ident];
    }
    
    /**
     * Get PO count 
     *
     */
    function count($filter=''){
        $ident = md5(print_r($filter, true));
        
        if (!$this->_dbstorage[$ident]) {
            $filter = $this->rebuildFilter($filter, $orderby);
            if (isset($_GET['supplier_id'])) {
                $filter['supplier_id'] = $_GET['supplier_id'];
            }
            if ($filter['supplier_brief_name']) {
                $res = $this->db->select('SELECT supplier_id FROM sdb_supplier WHERE supplier_brief_name = "' . $filter['supplier_brief_name'] . '" LIMIT 1');
                if (count($res)) {
                    $filter['supplier_id'] = array($res[0]['supplier_id']);
                } else {
                    return 0;
                }
                unset($filter['supplier_brief_name']);
            }
            $send = array(
                'filter_type' => 'json',
                'filter'      => count($filter) > 0 ? json_encode($filter) : '',
                'orderby'     => implode(' ', $orderby),
                'start'       => 0,
                'limit'       => 1,
                'return_data' => 'json'
            );
            $result = $this->api_utility->getApiData('getDealerOrderListByDealerId', API_VERSION, $send, true, true);
            if ($result === false) {
                echo $this->api_utility->error;
                return 0;
            } else {
                $count = $result['row_count'];
                $this->_dbstorage[$ident] = $count;
            }
        }
        
        return $this->_dbstorage[$ident];
    }
    
    /**
     * 根据本地订单号获取采购单列表
     * 
     * @param int $order_id 本地订单号
     * @return array()
     */
    function getPOList($order_id) {
        $re = $this->api_utility->getApiData('getPOrdersBySOrderId', API_VERSION, array(
            'id' => $order_id,
            'return_data' => 'json'
        ));
        if ($re === false) {
            echo $this->api_utility->error;
            return false;
        } else {
            $this->getSupplierName($re);
            foreach ($re as $key => $val) {
                $this->getOrderItems($re[$key]);
//                $oOrderPo = $this->system->loadModel('purchase/order_po');
                $re[$key]['po_status'] = $this->getPoShowStatusByPoStatus($val['status'], $val['pay_status'], $val['ship_status']);
            }
            return is_array($re) ? $re : array();
        }
    }

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
    
    
    /**
     * 获取商品清单
     *
     * @param array $POrders
     */
    function getGoodItems(&$POrders) {
        if (count($POrders) > 0) {
            foreach ($POrders as $key => $val) {
                $goods = '';
                $title = '';
                // 保留支付和配送的数字状态 ,可以由别的函数在getList中实现的 2009-11-24 16:13 wubin
                $POrders[$key]['__pay_status'] = $val['pay_status'];
                $POrders[$key]['__ship_status'] = $val['ship_status'];
                
                foreach ($val['items'] as $item) {
                    if (empty($item['name'])) continue;
                    $title .= $item['name'] . '×(' . $item['nums'] . ') ';
                    $goods .= $item['name'] . '<span class="fontcolorBlue">×(' . $item['nums'] . ')</span> ';
                }
                empty($goods) or $POrders[$key]['good_items'] = '<span title="' . $title . '">' . $goods . '</span>';
            }
        }
    }
    
    function getSupplierList() {
        return $this->db->select('SELECT supplier_id, supplier_brief_name FROM sdb_supplier');
    }

    /**
     * 根据供应商ID获取供应商名称
     *
     * @param array $POrders
     * @return array
     */
    function getSupplierName(&$POrders) {
        // echo '<pre>' . var_export($POrders, true) . '</pre>';
        $return = array();
        if (count($POrders) > 0) {
            $sArr = array();
            foreach ($POrders as $val) {
                $sArr[] = $val['supplier_id'];
            }
            $result = $this->db->select('SELECT * FROM sdb_supplier WHERE supplier_id IN (' . implode(', ', array_unique($sArr)) . ')');
            foreach ($result as $rs) {
                $return[$rs['supplier_id']] = $rs['supplier_brief_name'];
            }
        }
        foreach ($POrders as $key => $val) {
            $POrders[$key]['supplier_id'] = isset($return[$val['supplier_id']]) ? $return[$val['supplier_id']] : '-';
        }
        return $POrders;
    }

    /**
     * 获取采购单信息
     *
     * @param int $order_id 采购单号
     * @return array
     */
    function getOrder($order_id) {
        $re = $this->api_utility->getApiData('getPOrderById', API_VERSION, array(
            'id' => $order_id,
            'return_data' => 'json'
        ));
        $orderInfo = $this->getOrderInfo($re['dealer_order_id']);
        $orderInfo = $orderInfo[0];

        $re['_pay'] = (($re['status'] == 'active' || $re['status']=='pending') and in_array($re['pay_status'], array(0, 2, 3)));
        $re['_refund'] = ($orderInfo['status'] == 'active' and in_array($orderInfo['pay_status'], array(1, 2, 3)));
        $re['_cancel'] = ($re['status'] == 'active' and (int) $re['pay_status'] == 0 and (int) $re['ship_status'] == 0);
        
        $this->getOrderItems($re);
        $this->formatOrder($re);
        $this->formatOrder($orderInfo);
        $re['orderInfo'] = $orderInfo;
        $re['payInfo'] = $this->getPays($order_id);
        $re['refundInfo'] = $this->getRefunds($order_id);
        $re['deliveryInfo'] = $this->getDeliverys($order_id);
        $re['reshipInfo'] = $this->getReship($order_id);
        $re = $this->formatEmptyValue($re);
        $re['dateInfo'] = $this->formatDate($re);
        // e($re);
        return $re;
    }
    
    function formatEmptyValue($arr) {
        if (is_array($arr)) {
            foreach ($arr as $key => $val) {
                if (is_array($val)) {
                    $arr[$key] = $this->formatEmptyValue($val);
                } else {
                    ($key != 'small_pic' and ($val === '' or $val === null)) and $arr[$key] = '-';
                }
            }
        }
        return $arr;
    }
    
    /**
     * 获取本地商品信息
     * 
     * @param array $re
     * @return void
     */
    function getOrderItems(&$order) {
        $sql = 'SELECT
        G.goods_id,
        I.bn,
        I.price,
        G.small_pic
        FROM sdb_goods AS G
        LEFT JOIN sdb_products as P ON P.goods_id = G.goods_id
        LEFT JOIN sdb_order_items as I ON I.product_id = P.product_id
        LEFT JOIN sdb_orders AS O ON O.order_id = I.order_id
        WHERE O.order_id = "' . $order['dealer_order_id'] . '"
        ';
        $res = $this->db->select($sql);
        $local_items = array();
        foreach ($res as $val) {
            $local_items[$val['bn']] = array(
                'goods_id' => $val['goods_id'],
                'price' => $val['price'],
                'small_pic' => $val['small_pic']
            );
        }
        foreach ($order['items'] as $key => $val) {
            $order['items'][$key]['goods_id'] = $local_items[$val['dealer_bn']]['goods_id'];
            $order['items'][$key]['local_price'] = $local_items[$val['dealer_bn']]['price'];
            $order['items'][$key]['small_pic'] = $local_items[$val['dealer_bn']]['small_pic'];
        }
        return $res;
    }
    
    /**
     * 格式化时间
     *
     * @param array $info
     * @param array $keys
     * @return array
     */
    function formatDate(&$info) {
        $re = array();
        if (is_array($info)) {
            // $re[] = $info['acttime'] . ' - 采购单活动';
            $re[] = $info['createtime'] . '<span style="display:none">A</span> - 采购单创建';
            foreach (array('pay', 'refund', 'delivery', 'reship') as $val) {
                $val .= 'Info';
                foreach ($info[$val] as $k => $v) {
                    foreach (array('t_begin', 't_end', 't_ready', 't_send', 't_received') as $key) {
                        if (isset($v[$key]) && $v[$key]>0) {
                            $date = date('Y-m-d H:i', $v[$key]);
                            if (empty($date)) continue;
                            $case = substr($val, 0, -4) . '_' . $key;
                            switch ($case) {
                                case 'pay_t_begin':
                                    $re[] = $date . '<span style="display:none">B</span> - 开始支付，支付单号：' . $info[$val][$k]['payment_id'];
                                    break;
                                case 'pay_t_end':
                                    $re[] = $date . '<span style="display:none">C</span> - 支付结束';
                                    break;
                                case 'refund_t_ready':
                                    $re[] = $date . '<span style="display:none">D</span> - 退款单创建，退款单号：' . $info[$val][$k]['refund_id'];
                                    break;
                                case 'refund_t_send':
                                    $re[] = $date . '<span style="display:none">E</span> - 发款';
                                    break;
                                case 'refund_t_received':
                                    $re[] = $date . '<span style="display:none">F</span> - 用户确认收款';
                                    break;
                                case 'delivery_t_begin':
                                    $re[] = $date . '<span style="display:none">G</span> - 发货信息，物流公司：' . $info[$val][$k]['logi_name'] . '，物流单号：' . ($info[$val][$k]['logi_no'] == '-' ? '无' : $info[$val][$k]['logi_no']);
                                    break;
                                case 'delivery_t_end':
                                    $re[] = $date . '<span style="display:none">H</span> - 发货结束';
                                    break;
                                case 'reship_t_begin':
                                    $re[] = $date . '<span style="display:none">I</span> - 退货信息，物流公司：' . $info[$val][$k]['logi_name'] . '，物流单号：' . ($info[$val][$k]['logi_no'] == '-' ? '无' : $info[$val][$k]['logi_no']);
                                    break;
                                case 'reship_t_end':
                                    $re[] = $date . '<span style="display:none">J</span> - 退货结束';
                                    break;
                                default:
                                    break;
                            }
                            
                            $info[$val][$k][$key] = $date;
                        }
                    }
                }
            }
            if ($info['status'] == 'dead') {
                $re[] = $info['acttime'] . '<span style="display:none">Z</span> - 采购单撤销';
            }
        }
        sort($re);
        return $re;
    }
    
    /**
     * 格式化订单
     *
     * @param array $order
     * @return array
     */
    function formatOrder(&$order) {
        $pay_status = array(
            0 => '未付款',
            1 => '已付款',
            2 => '已付款至担保方',
            3 => '部分付款',
            4 => '部分退款',
            5 => '已退款',
            6 => '支付中'
        );
        $ship_status = array(
            0 => '未发货',
            1 => '已发货',
            2 => '部分发货',
            3 => '部分退货',
            4 => '已退货'
        );
//        $oOrderPo = $this->system->loadModel('purchase/order_po');        
        $order['po_status'] = $this->getPoShowStatusByPoStatus($order['status'], $order['pay_status'], $order['ship_status']);
        $order['__pay_status'] = $order['pay_status'];
        $order['pay_status'] = $pay_status[$order['pay_status']];
        $order['__ship_status'] = $order['ship_status'];
        $order['ship_status'] = $ship_status[$order['ship_status']];
        $order['acttime'] = date('Y-m-d H:i', $order['acttime']);
        $order['createtime'] = date('Y-m-d H:i', $order['createtime']);
        $order['expire_time'] = date('Y-m-d H:i', $order['expire_time']);
        $order['score_g'] = intval($order['score_g']);
        
        return $order;
    }

    /**
     * 获得本地订单信息
     *
     * @param int $order_id 本地订单号
     * @return array
     */
    function getOrderInfo($order_id) {
        $res = $this->db->select('SELECT * FROM sdb_orders WHERE order_id = ' . $order_id . ' LIMIT 1');
        // echo '<pre>' . var_export($res, true) . '</pre>';
        return $res;
    }

    /**
     * 支付单查询
     *
     * @param int $order_id 采购单号
     * @return array
     */
    function getPays($order_id) {
        $re = $this->api_utility->getApiData('getPaysByPOrderId', API_VERSION, array(
            'id' => $order_id,
            'return_data' => 'json'
        ));
        if ($re === false) {
            echo $this->api_utility->error;
            return false;
        } else {
            $status = array(
                'succ'     => '支付成功',
                'failed'   => '支付失败',
                'cancel'   => '未支付',
                'error'    => '参数异常',
                'progress' => '处理中',
                'timeout'  => '超时',
                'ready'    => '准备中'
            );
            foreach ($re as $key => $val) {
                $re[$key]['status'] = $status[$val['status']];
            }
            return is_array($re) ? $re : array();
        }
    }

    /**
     * 退款单查询
     *
     * @param int $order_id 采购单号
     * @return array
     */
    function getRefunds($order_id) {
        $re = $this->api_utility->getApiData('getRefundsByPOrderId', API_VERSION, array(
            'id' => $order_id,
            'return_data' => 'json'
        ));
        if ($re === false) {
            echo $this->api_utility->error;
            return false;
        } else {
            $status = array(
                'received' => '退款成功',
                'progress' => '处理中',
                'sent'     => '已退款',
                'ready'    => '准备中'
            );
            foreach ($re as $key => $val) {
                $re[$key]['status'] = $status[$val['status']];
            }
            return is_array($re) ? $re : array();
        }
    }

    /**
     * 发货单查询
     *
     * @param int $order_id 采购单号
     * @return array
     */
    function getDeliverys($order_id) {
        $re = $this->api_utility->getApiData('getDeliverysByPOrderId', API_VERSION, array(
            'id' => $order_id,
            'return_data' => 'json'
        ));
        if ($re === false) {
            echo $this->api_utility->error;
            return false;
        } else {
            return is_array($re) ? $re : array();
        }
    }

    /**
     * 退货单查询
     *
     * @param int $order_id 采购单号
     * @return array
     */
    function getReship($order_id) {
        $re = $this->api_utility->getApiData('getReshipByPOrderId', API_VERSION, array(
            'id' => $order_id,
            'return_data' => 'json'
        ));
        if ($re === false) {
            echo $this->api_utility->error;
            return false;
        } else {
            return is_array($re) ? $re : array();
        }
    }
    
    /**
     * 暂停发货
     * 
     * @param int $order_id 采购单号
     * @return boolean
     */
    function pausePOrder($order_id) {
        $re = $this->api_utility->getApiData('setOrderPending', API_VERSION, array(
            'id' => $order_id,
            'return_data' => 'json'
        ));
        if ($re === false) {
            echo $this->api_utility->error;
            return false;
        } else {
            return true;
        }
    }
    
    /**
     * 取消暂停发货
     * @param int $order_id 采购单号
     * @return boolean
     */
    function cancelPausePOrder($order_id) {
        $re = $this->api_utility->getApiData('setOrderAwake', API_VERSION, array(
            'id' => $order_id,
            'return_data' => 'json'
        ));
        if ($re === false) {
            echo $this->api_utility->error;
            return false;
        } else {
            return true;
        }
    }
    
    /**
     * 撤销采购单
     * 
     * @param int $order_id 采购单号
     * @return array
     */
    function cancelPOrder($order_id) {
        $re = $this->api_utility->getApiData('setOrderInvalid', API_VERSION, array(
            'id' => $order_id,
            'return_data' => 'json'
        ));
        if ($re === false) {
            echo $this->api_utility->error;
            return false;
        } else {
            return true;
        }
    }

    /**
     * 过滤器，应用于API服务端
     *
     * @param array $filter
     * @return string
     */
    function _filter($filter) {
        $where = array(1);
        // 订单状态
        if (is_array($filter['status'])) {
            $aStatus = array();
            foreach ($filter['status'] as $status) {
                if ($status != '_ANY_') {
                    $aStatus[] = $status;
                }
            }
            if (count($aStatus) > 0) {
                $where[] = 'status IN ("' . implode('", "', $aStatus) . '")';
            }
            unset($filter['status'], $aStatus, $status);
        }
        // 支付状态
        if (is_array($filter['pay_status'])) {
            $aStatus = array();
            foreach ($filter['pay_status'] as $status) {
                if ($status != '_ANY_') {
                    $aStatus[] = intval($status);
                }
            }
            if (count($aStatus) > 0) {
                $where[] = 'pay_status IN ("' . implode('", "', $aStatus) . '")';
            }
            unset($filter['pay_status'], $aStatus, $status);
        }
        // 发货状态
        if (is_array($filter['ship_status'])) {
            $aStatus = array();
            foreach ($filter['ship_status'] as $status) {
                if ($status != '_ANY_') {
                    $aStatus[] = intval($status);
                }
            }
            if (count($aStatus) > 0) {
                $where[] = 'ship_status IN ("' . implode('", "', $aStatus) . '")';
            }
            unset($filter['ship_status'], $aStatus, $status);
        }
        // 地区
        if (is_array($filter['areas'])) {
            $aArea = array();
            foreach ($filter['areas'] as $area) {
                if ($area != '_ANY_') {
                    $aArea[] = 'shipping_area = "' . $area . '"';
                }
            }
            if (count($aArea) > 0) {
                $where[] = '(' . implode(' OR ', $aArea) . ')';
            }
            unset($filter['areas'], $aArea, $area);
        }
        // 发货
        if (is_array($filter['delivery'])) {
            $aDelivery = array();
            foreach ($filter['delivery'] as $delivery) {
                if ($delivery != '_ANY_') {
                    $aDelivery[] = 'shipping_id = ' . intval($delivery);
                }
            }
            if (count($aDelivery) > 0) {
                $where[] = '(' . implode(' OR ', $aDelivery) . ')';
            }
            unset($filter['delivery'], $aDelivery, $delivery);
        }
        // 支付方式
        if (is_array($filter['payment'])) {
            $aPayment = array();
            foreach ($filter['payment'] as $payment) {
                if ($payment != '_ANY_') {
                    $aPayment[] = 'payment = ' . intval($payment);
                }
            }
            if (count($aPayment) > 0) {
                $where[] = '(' . implode(' OR ', $aPayment) . ')';
            }
            unset($filter['payment'], $aPayment, $payment);
        }
        // 递送方式名称
        if (isset($filter['ship_name'])) {
            $where[] = 'ship_name LIKE "%' . $filter['ship_name'] . '%"';
            unset($filter['ship_name']);
        }
        // 创建时间
        if (isset($filter['createtime'])) {
            $where[] = 'createtime > "' . $filter['createtime'] . '"';
            unset($filter['createtime']);
        }
        // 地址
        if (isset($filter['ship_addr'])) {
            $where[] = 'ship_addr LIKE "%' . $filter['ship_addr'] . '%"';
            unset($filter['ship_addr']);
        }
        // 电话
        if (isset($filter['ship_tel'])) {
            $where[] = 'ship_tel LIKE "%' . $filter['ship_tel'] . '%"';
            unset($filter['ship_tel']);
        }
        // 回单
        if (isset($filter['return_order_id']) && $filter['return_order_id'] != '') {
            $where[] = 'order_id LIKE "%' . $filter['return_order_id'] . '%"';
            unset($filter['return_order_id']);
        }
        if (isset($filter['mark_text'])) {
            $where[] = 'mark_text LIKE "%' . $filter['mark_text'] . '%"';
            unset($filter['mark_text']);
        }
        return implode(' AND ', $where);
    }

    /**
     * 通过po单单号取回相应po单
     *
     * @param int $id, po单单号
     * @return
          array(
              'confirm' => ,
              'status' =>,
              ...
              'items' => array( //po单明细
                     ...
                );
              );
    
     */
    function __instance($id, $cols = '*') {
        $send = array(
            'id' => $id,
            'cols' => $cols
        );
        return $this->api_utility->getApiData('getPOrderById', API_VERSION, $send);
    }

    function modifier_pp(&$rows) {
        $status = array(
            0 => '线下支付',
            -1 => '货到付款'
        );
        foreach ($rows as $k => $v) {
            if ($v < 1)
                $rows[$k] = $status[$v];
        }
        foreach ($this->db->select('SELECT id,custom_name FROM sdb_payment_cfg WHERE id IN (' . implode(',', array_keys($rows)) . ')') as $r) {
            $rows[$r['id']] = $r['custom_name'];
        }
    }

    function modifier_ship_name(&$rows) {
        foreach ($rows as $k => $v) {
            $rows[$k] = htmlspecialchars($rows[$k]);
        }
    }

    function modifier_pay_status(&$rows) {
        $status = array(
            0 => '未付款',
            1 => '已付款',
            2 => '已付款至担保方',
            3 => '部分付款',
            4 => '部分退款',
            5 => '已退款',
            6 => '支付中'
        );
        foreach ($rows as $k => $v) {
            $rows[$k] = $status[$v];
        }
    }

    function modifier_ship_status(&$rows) {
        $status = array(
            0 => '未发货',
            1 => '已发货',
            2 => '部分发货',
            3 => '部分退货',
            4 => '已退货'
        );
        foreach ($rows as $k => $v) {
            $rows[$k] = $status[$v];
        }
    }

    function modifier_mark_text($row) {
        if ($row['mark_text'] != '') {
            return "<span  title=\"" . $row['mark_text'] . "\"><img src=\"../statics/remark_icons/" . $row['mark_type'] . ".gif\"></span>";
        }
    }
}
?>