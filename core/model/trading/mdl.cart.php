<?php
/**
 * Original Web Interface to phpDocumentor
 *
 * PHP versions 4 and 5
 *
 * @category   ToolsAndUtilities
 * @package    Shopex B2C
 * @author     Ever <ever@shopex>
 * @license    http://www.shopex.cn/ LGPL
 * @link       http://www.shopex.cn/
 * @filesource 
 * @todo       CS cleanup - change package to PhpDocumentor
 * @deprecated redirects automatically to docbuilder 
 *             (see {@link docbuilder/index.html})
 */

class mdl_cart extends modelFactory
{
    var $cookiesName = 'CART';
    var $memberLogin = false;
    function checkMember($aMember)
    {
        $this->memInfo = $aMember;
        if($aMember['member_id'] && $aMember['uname']){
            $this->memberLogin = true;
        }
    }
    
    /**
     * 加入购物车
     * @param string $objType 购物车中哪个组：g/商品；p/捆绑商品；f/赠品；c/优惠券
     * @param array $aParams 购物车参数项数组
     * @param number $quantity 加入购物车数组项数量
     * @return bool
     */
    function addToCart($objType='g', &$aParams, $quantity=1)
    {
        switch($objType){
            case 'g':
                if($aParams['gid'] > 0 && $aParams['pid'] > 0 && $quantity > 0){
                    $cartKey = $aParams['gid'].'-'.$aParams['pid'].'-'.$aParams['adj'];
                    $aCart = $this->getCart('g');
                    if (isset($aCart['cart'][$cartKey])){
                        $aCart['cart'][$cartKey] += $quantity;
                        $buyStatus = 1;
                    }else{
                        $aCart['cart'][$cartKey] = $quantity;
                        $buyStatus = 0;
                    }
                    if($aParams['pmtid'] > 0) $aCart['pmt'][$aParams['gid']] = $aParams['pmtid'];
                    
                    $objGoods = $this->system->loadModel('trading/goods');
                    $aGoods = $objGoods->getMarketableById($aParams['gid']);
                    if($aGoods['marketable'] == "false"){
                        $this->setError(10001);
                        trigger_error(__('该货品已经下架'),E_USER_NOTICE);
                        return false;
                    }

                    if(!$this->_checkStore($aParams['pid'], $aCart['cart'][$cartKey])){
                        if($buyStatus == 0){
                            $this->setError(10001);
                            trigger_error(__('库存不足'),E_USER_NOTICE);
                            return false;
                        }else{
                            return 'notify';
                        }
                        exit;
                    }
        
                    if($stradj != 'na'){
                        $aAdj = explode('|', $aParams['adj']);
                        foreach($aAdj as $val){
                            $adjItem = explode('_', $val);
                            if($adjItem[0]>0 && $adjItem[2]>0){
                                if(!$this->_checkStore($adjItem[0], $adjItem[2]*$aCart['cart'][$cartKey])){
                                    $this->setError(10001);
                                    trigger_error(__('配件库存不足'),E_USER_NOTICE);
                                    return false;
                                }
                            }
                        }
                    }
                    return $this->save('g', $aCart);
                }else{
                    $this->setError(10001);
                    trigger_error(__('参数错误!'),E_USER_NOTICE);
                    return false;
                }
            break;
            case 'p':
                if($aParams['pkgid']){
                    $aCart = $this->getCart('p');
                    $aCart[$aParams['pkgid']]['num'] += $quantity;
                    if (!$this->_checkGoodsStore($aParams['pkgid'], $aCart[$aParams['pkgid']]['num'])) {
                        $this->setError('10000');
                        trigger_error(__('捆绑商品数量不足'),E_USER_ERROR);
                        return false;
                    }
                    return $this->save('p', $aCart);
                }else{
                    $this->setError(10001);
                    trigger_error(__('参数错误!'),E_USER_NOTICE);
                    return false;
                }
            break;
            case 'f':
                if ($aParams['gift_id']) {
                    $aCart = $this->getCart('f');
                    $aCart[$aParams['gift_id']]['num'] += $quantity;
                    return $this->save('f', $aCart);
                }else{
                    $this->setError(10001);
                    trigger_error(__('参数错误!'),E_USER_NOTICE);
                    return false;
                }
            break;
            case 'c':
                //todo判断coupon 的有效性
                //暂时强行规定一个购物车，有且只使用一张优惠券
                if (is_array($aParams)&&count($aParams==1)) {
                    foreach ($aParams as $k=>$c) {
                        $cart_c[$k] = array('type' => $c['type'],
                                            'pmt_id' => $c['pmt_id']);
                    }
                    return $this->save('c', $cart_c);
                }else{
                    $this->setError(10001);
                    trigger_error(__('参数错误!'),E_USER_NOTICE);
                    return false;
                }
            break;
        }
    }

    /**
     * 更新购物车中商品
     * @param string $objType 购物车中哪个组：g/商品；p/捆绑商品；f/赠品；c/优惠券
     * @param string $cartKey 购物车数组项
     * @param number $quantity 购物车数组项更新成指定数量
     * @param array $aMsg 处理过程中的消息回馈
     * @return bool
     */
    function updateCart($objType='g', $cartKey, $quantity, &$aMsg)
    {
        $quantity = intval($quantity);
        if($quantity < 1){
            $aMsg[] = __('输入更新数量不合法');
//            trigger_error(__('输入更新数量不合法'),E_USER_NOTICE);
            return false;
        }
       
        switch($objType){
            case 'g':
                list($goodsid,$productid,$stradj) = explode('-', $cartKey);
                $o_goods=$this->system->loadModel('goods/finderPdt');
                $goods_info=$o_goods->instance($productid);
                if($goodsid > 0 && $productid > 0 && $quantity > 0){
                    $aCart = $this->getCart($objType);
                    $aCart['cart'][$cartKey] = $quantity;
                    if(!$this->_checkStore($productid, $aCart['cart'][$cartKey])){
                        $aMsg[] = __($goods_info['name'].'<br>商品库存不足');
                        return false;
                    }
        
                    if($stradj != 'na'){
                        $aAdj = explode('|', $stradj);
                        foreach($aAdj as $val){
                            $adjItem = explode('_', $val);
                            if($adjItem[0]>0 && $adjItem[2]>0){
                                if(!$this->_checkStore($adjItem[0], $adjItem[2]*$aCart['cart'][$cartKey])){
                                    $aMsg[] = __($goods_info['name'].'<br>配件库存不足');
                                    return false;
                                }
                            }
                        }
                    }
                    return $this->save($objType, $aCart);
                }else{
                    $aMsg[] = __('参数错误');
                    return false;
                }
                break;
            case 'p':
                if($quantity > 0){
                    $aCart = $this->getCart('p');
                    $aCart[$cartKey]['num'] = $quantity;
                    if (!$this->_checkGoodsStore($cartKey, $aCart[$cartKey]['num'])) {
                        $aMsg[] = __('捆绑商品库存不足');
                        return false;
                    }
                    return $this->save('p', $aCart);
                }else{
                    $aMsg[] = __('参数错误');
                    return false;
                }
            break;
            case 'f':
                $oGift = &$this->system->loadModel('trading/gift');
                $aGiftInfo = $oGift->getGiftById($cartKey);
                
                if (intval($cartKey)>0) {
                    if ($oGift->isOnSale($aGiftInfo, $this->memInfo['member_lv_id'], $quantity)) {
                        if ($this->memInfo['point']>=$aGiftInfo['point']*$quantity){//判断积分是否足够
                            $aCart = $this->getCart($objType);
                            $aCart[$cartKey]['num'] = $quantity;
                            return $this->save($objType, $aCart);
                        }else{
                            $aMsg[] = __('用户积分不足');
//                            trigger_error(__('用户积分不足'),E_USER_ERROR);
                            return false;
                        }
                    }else{
                        $aMsg[] = __('库存不足/购买数量超过限定数量/过期/超过最大购买限额');
//                        trigger_error(__('库存不足/购买数量超过限定数量/过期/超过最大购买限额'),E_USER_ERROR);
                        return false;
                    }
                }
            break;
        }
    }

    /**
     * 删除购物车中商品
     * @param string $objType 购物车中哪个组：g/商品；p/捆绑商品；f/赠品；c/优惠券
     * @param array $aGoods 购物车数组项
     * @return bool
     */
    function removeCart($objType='all', $aGoods=array())
    {
        switch($objType){
            case 'g':
                if (is_array($aGoods) && !empty($aGoods)) {
                    $aCart = $this->getCart($objType);
                    foreach ($aCart['cart'] as $strKey => $v){
                        if(!$aGoods[$strKey]) unset($aCart['cart'][$strKey]);
                    }
                }else{
                    $aCart=array();
                }
                return $this->save('g', $aCart);
            break;
            case 'p':
                if (is_array($aGoods) && !empty($aGoods)) {
                    $aCart = $this->getCart('p');
                    foreach ($aCart as $goodsId => $v) {
                        if(!$aGoods[$goodsId]) unset($aCart[$goodsId]);
                    }
                }else{
                    $aCart=array();
                }
                return $this->save('p', $aCart);
            break;
            case 'f':
                //todo 需要bryant调整
                if (is_array($aGoods) && !empty($aGoods)) {
                    $aCart = $this->getCart('f');
                    foreach ($aCart as $giftId => $v) {
                        if(!$aGoods[$giftId]) unset($aCart[$giftId]);
                    }
                }else{
                    $aCart=array();
                }
                return $this->save('f', $aCart);
            break;
            case 'c':
                $aCart = $this->getCart();
                unset($aCart['c']);
                $this->save('all', $aCart);
            break;
            case 'all':
                return $this->save('all', array());
            break;
        }
    }

    /**
     * 读取购物车中积分换赠品的兑换积分数
     * @return integer
     */
    function getCartCPoint()
    {
        $aCart = $this->getCart('f');
        $giftIds = array_keys($aCart);
        $nums = array_item($aCart, 'num');
        $count = 0;
        if ($giftIds) {
            $oGift = &$this->system->loadModel('trading/gift');
            $aGift = $oGift->getGiftByIds($giftIds);
            foreach($aGift as $k => $item) {
                $count += $item['point'] * $nums[$k];
            }
        }
        return $count;
    }
    
    /**
     * 将购物车中几种/几个商品数量写入 $_COOKIE['CART_COUNT'] $_COOKIE['CART_NUMBER']
     * @global string document the fact that this function uses $_myvar
     * @staticvar integer $staticvar this is actually what is returned
     * @param string $param1 name to declare
     * @param array $aCart 购物车数组
     * @return bool
     */
    function setCartNum(&$aCart)
    {
        $sale = &$this->system->loadModel('trading/sale');
        $trading = $sale->getCartObject($aCart,$GLOBALS['runtime']['member_lv'],true);
        $count = count($trading['products'])+count($trading['gift_e'])+count($trading['package']);
        if($trading['products'])
            foreach($trading['products'] as $rows){
                $number += $rows['nums'];
            }
        if($trading['gift_e'])
            foreach($trading['gift_e'] as $rows){
                $number += $rows['nums'];
            }
        if($trading['package'])
            foreach($trading['package'] as $rows){
                $number += $rows['nums'];
            }
        if($count!=$_COOKIE['CART_COUNT']){
            $this->system->setCookie('CART_COUNT',$count);
        }
        if($number!=$_COOKIE['CART_NUMBER']){
            $this->system->setCookie('CART_NUMBER',$number);
        }
    }
    
    /**
     * 读取购物车中数据，以数组的形式返回
     * Array
     *  [goods][cart][gid-pid-adj]=num
     *  [goods][pmt][gid] = pmt
     *  @g.gid-pid-adj-num,gid-pid-adj-num;gid-pmtid,gid-pmtid@f.@p.@c.
     *  //说明:@g.(购物车内容)gid-pid-adj-num,gid-pid-adj-num;(PMT内容)gid-pmtid,gid-pmtid
     *  //adj = 配件id_配件组_配件数量|
     *  ['gift']
     *  g.gift_id-na-num_pmtid
     *  ['c'] pmt_id type:o订单/g商品
     * @param string $objType 指定购物车中某项数据
     * @param string $sCookie 购物车指定Cookie值
     * @return array
     */
    function getCart($objType='all', $sCookie='')
    {
        $aCart = $this->_getCart($sCookie);
        if ($objType == 'all') {
            return $aCart;
        }else {
            if ($this->_verifyObjType($objType)) {
                if (is_array($aCart[$objType])) {
                    return $aCart[$objType];
                }
            }
        }
    }

    function _getCart($sCookie='')
    {
        $aCart = array();
        if(!$sCookie){
            if($this->memberLogin){
                $oMember = &$this->system->loadModel('member/member');
                $sCookie = $oMember->getCart($this->memInfo['member_id']);
            }else{
                $sCookie = $_COOKIE[$this->cookiesName];
            }
        }
        $aType = explode("@", $sCookie);
        unset($aType[0]);
        foreach($aType as $sType){
            if (!empty($sType)){
                $aItems = explode(".", $sType);
                $sCurObj = $aItems[0];
                $sItem = $aItems[1];
                switch ($sCurObj) {
                    case 'g'://商品
                        $aTmp = null;
                        $aSplit = explode(";", $sItem);    //拆分购物车和PMT
                        $sCart = $aSplit[0];
                        $sPmt = $aSplit[1];
                        if(!empty($sCart)){
                            $aRow = explode(",", $sCart);
                            foreach($aRow as $sRow){
                                $aTmp = explode("-", $sRow);
                                $aCart['g']['cart'][$aTmp[0].'-'.$aTmp[1].'-'.$aTmp[2]] = $aTmp[3];
                            }
                            $aRow = explode(",", $sPmt);
                            foreach($aRow as $sRow){
                                $aTmp = explode("-", $sRow);
                                if($aTmp[0]) $aCart['g']['pmt'][$aTmp[0]] = $aTmp[1];
                            }
                        }else{
                            $aCart['g']['cart'] = array();
                        }
                        break;
                    case 'p'://捆绑
                        $aTmp = null;                        
                        $aRow = explode(",",$sItem);
                        foreach ($aRow as $sRow) {
                            $aTmp = explode('-', $sRow);
                            $aCart['p'][$aTmp[0]]['num'] = $aTmp[1];
                        }
                        break;
                    case 'f'://赠品
                        $aTmp = null;                        
                        $aRow = explode(",",$sItem);
                        foreach ($aRow as $sRow) {
                            $aTmp = explode('-', $sRow);
                            $aCart['f'][$aTmp[0]]['num'] = $aTmp[1];
                        }
                        break;
                    case 'c'://优惠券
                        /*
                        $aTmp = null;
                        $aRow = explode("-",$sItem);
                        $aTmp[$aRow[0]]['pmt_id'] = $aRow[1];                        
                        switch ($aRow[2]) {
                            case 'o':
                                $aRow[2] = 'order';
                                break;
                            case 'g':
                                $aRow[2] = 'goods';
                                break;
                        }
                        $aTmp[$aRow[0]]['type'] = $aRow[2];
                        $aCart['c'] = $aTmp;*/
                        $aTmp = null;
                        $aRow = explode(',', $sItem);
                        foreach($aRow as $sRow) {
                            $aTmp = explode("-",$sItem);
                            $aCart['c'][$aTmp[0]]['pmt_id'] = $aTmp[1];                        
                            switch ($aTmp[2]) {
                                case 'o':
                                    $aTmp[2] = 'order';
                                    break;
                                case 'g':
                                    $aTmp[2] = 'goods';                            
                                    break;
                            }
                            $aCart['c'][$aTmp[0]]['type'] = $aTmp[2];
                        }
                        break;
                }
            }
        }
        return $aCart;
    }
    
    //cookies
    function save($objType, $aPara)
    {
        if ($objType == 'all') {
            $aRet = $aPara;
        }else {
            if ($this->_verifyObjType($objType)) {
                $aRet = $this->getCart();
                $aRet[$objType] = $aPara;
            }else{
                return false;
            }
        }
        $this->setCartNum($aRet);
        if($aRet){
            $sRet = $this->_save($aRet);
        }else{
            $sRet = '';
        }
        if($this->memberLogin){
            $oMember = &$this->system->loadModel('member/member');
            $oMember->saveCart($this->memInfo['member_id'], $sRet);    //插入用户数据库addon['cart']
        }else{
            $this->system->setcookie($this->cookiesName, $sRet);
        }
        return true;
    }

    function _save(&$aRet)
    {
        $sRet = '';
        foreach($aRet as $sObj => $aObj) {
            if (is_array($aObj)&&!empty($aObj)) {
                $sRet .= '@';
                switch ($sObj){
                    case 'g':
                        $sRet .= $sObj.'.';
                        //@g.gid-pid-adj-num,gid-pid-adj-num;pmt:gid-pmtid,gid-pmtid
                        $iLoop = 0;
                        foreach($aObj['cart'] as $item => $num){
                            if($item && $num){
                                if($iLoop > 0) $sRet .= ',';
                                $sRet .= $item.'-'.$num;
                                $iLoop++;
                            }
                        }
                        
                        $iLoop = 0;
                        $sRet .= ';';
                        foreach($aObj['pmt'] as $gid => $pmtid){
                            if($gid && $pmtid){
                                if($iLoop > 0) $sRet .= ',';
                                $sRet .= $gid.'-'.$pmtid;
                                $iLoop++;
                            }
                        }
                        break;
                    case 'p':
                        $sRet .= $sObj.'.';
                        $aComponents  = array();
                        foreach($aObj as $gid => $aProduct){
                            $aComponents[]  = $gid.'-'.$aProduct['num'];
                        }
                        $sRet .= implode(',', $aComponents );
                        break;
                    case 'f':
                        $sRet .= $sObj.'.';
                        $aComponents  = array();
                        foreach($aObj as $gid => $aProduct){
                            $aComponents[]  = $gid.'-'.$aProduct['num'];
                        }
                        $sRet .= implode(',', $aComponents );
                        break;
                    case 'c':
                        $sRet .= $sObj.'.';
                        $aComponents  = array();
                        foreach($aObj as $code => $aCoupon){
                            switch ($aCoupon['type']) {
                                case 'order':
                                    $aCoupon['type'] = 'o';
                                    break;
                                case 'goods':
                                    $aCoupon['type'] = 'g';
                                    break;
                            }
                            //$sRet .= '.'.$code.'-'.$aCoupon['pmt_id'].'-'.$aCoupon['type'];
                            $aComponents[] = $code.'-'.$aCoupon['pmt_id'].'-'.$aCoupon['type'];
                        }
                        $sRet .= implode(',', $aComponents);
                        break;
                }
            }
        }
        return $sRet;
    }

    function _checkStore($pid, $num=1, $orderid="")
    {
        $objProduct = &$this->system->loadModel('goods/products');
        $aStore = $objProduct->getFieldById($pid);
        $aOrderItem = $this->db->selectrow('SELECT nums FROM sdb_order_items WHERE product_id=\''.$pid.'\' AND order_id=\''.$orderid.'\'');
        if(!is_null($aStore['store'])){
            $gStore = intval($aStore['store']) - intval($aStore['freez']) + intval($aOrderItem['nums']);
            if($gStore < $num){
                return false;
                exit;
            }
        }
        return true;
    }
    
    function _checkGoodsStore($pid, $num=1)
    {
        $objGoods = &$this->system->loadModel('trading/goods');
        $aStore = $objGoods->getFieldById($pid);
        if(!is_null($aStore['store'])){
            $gStore = intval($aStore['store']) - intval($aStore['freez']);
            if($gStore < $num){
                return false;
                exit;
            }
        }
        return true;
    }
    
    function _verifyObjType($objType) {
        $_allObjType = array('g', 'p', 'f', 'c');
        return in_array($objType, $_allObjType);
    }

    function getCheckout(&$aCart, $aMember, $currency){
        $trading = $this->checkoutInfo($aCart, $aMember);
        $gtype = &$this->system->loadModel('goods/gtype');
        foreach($trading['products'] as $p){
            if($p['goods_id'] > 0) $aP[] = $p['goods_id'];
        }
        if(!empty($aP) || $trading['package'] || $trading['gift_e']){    //todo 需要处理捆绑商品的配送问题
            if(!empty($aP)){
                $deliverInfo = $gtype->deliveryInfo($aP);
                $aOut['has_physical'] = $deliverInfo['physical'];
            }else{
                $aOut['has_physical'] = 1;
            }
            foreach($trading['products'] as $product){
                if($deliverInfo['custom'][$product['type_id']]){
                    $aOut['minfo'][$product['product_id']] = array(
                            'goods_id' => $product['goods_id'],
                            'nums' => $product['nums'],
                            'name' => $product['name'],
                            'minfo' => &$deliverInfo['custom'][$product['type_id']]);
                }
            }
            $oDly = &$this->system->loadModel('trading/delivery');
            if($area = $oDly->getDlAreaList()){
                foreach($area as $a){
                    $aOut['areas'][$a['area_id']]=$a['name']; 
                }
            }
        }
        
        $payment = &$this->system->loadModel('trading/payment');
        $oCur = &$this->system->loadModel('system/cur');
        $currency = $oCur->getcur($currency, true);

        $oMem = &$this->system->loadModel('member/member');
        $trading['receiver'] = $oMem->getDefaultAddr($aMember['member_id']);
        $trading['receiver']['email'] = $aMember['email'];
        $trading['receiver']['point'] = $aMember['point'];
        $aOut['currencys'] = $oCur->curAll();
        $aOut['currency'] = $currency['cur_code'];
        $aOut['payments'] = $payment->getByCur($currency['cur_code']);
        $aOut['trading'] = $trading;
        return $aOut;
    }

    function checkoutInfo(&$aCart, &$aMember, $aParam=null) {
        $sale = &$this->system->loadModel('trading/sale');
        $trading = $sale->getCartObject($aCart,$aMember['member_lv_id'],true);
        $trading['total_amount'] = $trading['totalPrice'];
        if($aParam['shipping_id']){
            $shipping = &$this->system->loadModel('trading/delivery');
            $aShip = $shipping->getDlTypeByArea($aParam['area'], 0, $aParam['shipping_id']);
            if($trading['exemptFreight'] == 1){
                $trading['cost_freight'] = 0;
            }else{
                $trading['cost_freight'] = cal_fee($aShip[0]['expressions'],$trading['weight'],$trading['pmt_b']['totalPrice'],$aShip[0]['price']);
            }
            $trading['shipping_id'] = $aParam['shipping_id'];
            if($aParam['is_protect'] == 'true' && $aShip[0]['protect']){
                $trading['is_protect'] = 1;
                $trading['cost_protect'] = max($aShip[0]['protect_rate']*$trading['totalPrice'], $aShip[0]['minprice']);
            }
            $trading['total_amount'] += $trading['cost_freight']+$trading['cost_protect'];
        }
        if($this->system->getConf('site.trigger_tax')){
            $trading['is_tax'] = 1;
            if(isset($aParam['is_tax']) && $aParam['is_tax'] == 'true'){
                $trading['tax_checked'] = 'checked';
                $trading['cost_tax'] = $trading['totalPrice'] * $this->system->getConf('site.tax_ratio');
                $trading['total_amount'] += $trading['cost_tax'];
            }
            $trading['tax_rate'] = $this->system->getConf('site.tax_ratio');
        }
        $shipping_del = &$this->system->loadModel('trading/delivery');
        $aShip_del = $shipping_del->getHasCod($aParam['shipping_id']);
        
        if($aShip_del['has_cod']!=1){
          if($aParam['payment']){
            $payment = &$this->system->loadModel('trading/payment');
            $aPay = $payment->getPaymentById($aParam['payment']);
            $config=unserialize($aPay['config']);
            if ($config['method']<>2)
                $trading['cost_payment'] = $aPay['fee'] * $trading['total_amount'];
            else
                $trading['cost_payment'] = $config['fee'];
            $trading['total_amount'] += $trading['cost_payment'];
          }
        }
        $oMem = &$this->system->loadModel('member/member');
        $trading['receiver'] = $oMem->getDefaultAddr($aMember['member_id']);
        //$trading['receiver']['point'] = $aMember['point'];
        $trading['history_GainScore'] = $trading['totalScore']+$trading['receiver']['point'];

        $trading['score_g'] = $trading['pmt_b']['totalGainScore'];
        $trading['pmt_amount'] = $trading['pmt_b']['totalPrice'] - $trading['totalPrice'];
        $trading['member_id'] = $aMember['member_id'];
        
        $order = &$this->system->loadModel('trading/order');
        $newNum = $order->getOrderDecimal($trading['total_amount']);
        $trading['discount'] = $trading['total_amount'] - $newNum;
        $trading['total_amount'] = $newNum;
        $oCur = &$this->system->loadModel('system/cur');
        $currency = $oCur->getcur($aParam['cur']);
        if($currency['cur_code']){
            $trading['cur_rate'] = $currency['cur_rate'];
        }else{
            $trading['cur_rate'] = 1;
        }
        $trading['final_amount'] = $newNum * $trading['cur_rate'];
        $trading['cur_sign'] = $currency['cur_sign'];
        $trading['cur_display'] = $this->system->request['cur'];
        $trading['cur_code'] = $currency['cur_code'];
        return $trading;
    }
    
    function setFastBuy($objType='g', $aParams){
        //todo 需要再考虑捆绑商品
        if(!$this->_checkStore($aParams['pid'], 1)){
            $this->setError(10001);
            trigger_error(__('库存不足'),E_USER_NOTICE);
            return false;
        }
        
        $aAdj = explode('|', $aParams['adj']);
        foreach($aAdj as $val){
            $adjItem = explode('_', $val);
            if($adjItem[0]>0 && $adjItem[2]>0){
                if(!$this->_checkStore($adjItem[0], $adjItem[2])){
                    $this->setError(10001);
                    trigger_error(__('配件库存不足'),E_USER_NOTICE);
                    return false;
                }
            }
        }
        
        if($objType == 'g'){
            $cartKey = $aParams['gid'].'-'.$aParams['pid'].'-'.$aParams['adj'];
            $aCart['g']['cart'][$cartKey] = $aParams['num'];
            if($aParams['pmtid'] > 0) $aCart['pmt'][$aParams['gid']] = $aParams['pmtid'];
        }
        return $aCart;
    }
    
    function getParams($aIn, &$gid, &$pid, $stradj='', $pmtid=0){
        if(is_array($aIn)){
            $gid = intval($aIn['goods_id']);
            $pid = intval($aIn['product_id']);
            $gnum = intval($aIn['num']);
            foreach((array)$aIn['adjunct'] as $key => $aAdj){
                foreach((array)$aAdj as $adjid => $num){
                    $stradj .= $adjid.'_'.$key.'_'.$num.'|';    //配件id、配件组、配件数量|
                }
                $pmtid = $aIn['pmt_id'];
            }
        }
        if (intval($pmtid) == 0) {
            $oPromotion = &$this->system->loadModel('trading/promotion');
            $mlvid = intval($GLOBALS['runtime']['member_lv']);//会员id号
            $pmtid = $oPromotion->getGoodsPromotionId($gid, $mlvid);
        }
        if(intval($pid) == 0){
            $objGoods = &$this->system->loadModel('trading/goods');
            $aP = $objGoods->getProducts($gid);
            $pid = $aP[0]['product_id'];
        }
        if($stradj === '' || $stradj === 0) $stradj = 'na';
        $aParams['gid'] = $gid;
        $aParams['pid'] = $pid;
        $aParams['adj'] = $stradj;
        $aParams['pmtid'] = $pmtid;
        $aParams['num'] = $gnum;
        return $aParams;
    }
    
    function mergeCart($cartCookie, $cartDb){
        $aCart = array();
        if($cartCookie['g']['cart']){
            $aCart['g']['cart'] = $cartCookie['g']['cart'];
            if($cartDb['g']['cart']){
                foreach($cartDb['g']['cart'] as $k => $num){
                    $aCart['g']['cart'][$k] = intval($num) + intval($aCart['g']['cart'][$k]);
                }
            }
        }else{
            if($cartDb['g']['cart']){
                $aCart['g']['cart'] = $cartDb['g']['cart'];
            }
        }
        if($cartCookie['p']){
            $aCart['p'] = $cartCookie['p'];
            if($cartDb['p']){
                foreach($cartDb['p'] as $k => $item){
                    $aCart['p'][$k]['num'] = intval($item['num']) + intval($aCart['p'][$k]['num']);
                }
            }
        }else{
            if($cartDb['p']){
                $aCart['p'] = $cartDb['p'];
            }
        }
        if($cartCookie['f']){
            $aCart['f'] = $cartCookie['f'];
            if($cartDb['f']){
                foreach($cartDb['f'] as $k => $item){
                    $aCart['f'][$k]['num'] = intval($item['num']) + intval($aCart['f'][$k]['num']);
                }
            }
        }else{
            if($cartDb['f']){
                $aCart['f'] = $cartDb['f'];
            }
        }
        return $aCart;
    }
}
?>