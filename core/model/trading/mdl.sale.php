<?php
/*
g 商品
f 赠品
p 捆绑商品
c 优惠券

gift_e 兑换赠品
gift_p 赠送赠品
coupon_p 赠送优惠券
coupon_u 使用的优惠券

totalGainScore  总得积分
totalconsumescore 总消费积分

*/
class mdl_sale extends modelFactory {
    var $_mlvid   = NULL;
    var $_isCheck = false;

    function _load_c(&$trading, $cart_c) {
        //todo 需要加验证

        $oCoupon = &$this->system->loadModel('trading/coupon');
        if (count($cart_c)>0) {
            $oCoupon = &$this->system->loadModel('trading/coupon');
            $trading['coupon_u'] = array();
            foreach($cart_c as $couponCode => $aCoupon) {
                if ($aCoupon['type'] == 'goods') {
                    foreach($trading['products'] as $p) {
                        $goods_ids[] = $p['goods_id'];
                        $brand_ids[] = $p['brand_id'];
                        $cat_ids[] = $p['cat_id'];
                    }
                    if ($couponPmt = $oCoupon->useMemberCoupon($couponCode, $this->_mlvid, $goods_ids, $brand_ids, $cat_ids)) {
//                        list(, $couponPmt) = each($couponPmt);
                        list(, $firstCouponPmt) = each($couponPmt);

                        foreach ($trading['products'] as $k => $p) {
                            if (in_array($trading['products'][$k]['goods_id'], $firstCouponPmt['goods_ids'])) {
                                $trading['products'][$k]['pmt_id'] = $aCoupon['pmt_id'];
                            }
                        }
                        $trading['coupon_u'] = array_merge($trading['coupon_u'], $couponPmt);
//                        $trading['coupon_u'][] = $couponPmt;
                    }else {
                        unset($trading['coupon_u'][$couponCode]);
                        return false;
                    }
                }else if ($aCoupon['type'] == 'order') {
                    $trading['coupon_u'] = array_merge($trading['coupon_u'], $cart_c);
                }
            }
        }
    }

    function _check_g(&$cart_g) {
        //todo 转到cart中进行验证
        //todo 验证传入的pmt_id是否正确有效(目前的解决方案是重新计算商品的pmt_id)
        $oPromotion = &$this->system->loadModel('trading/promotion');
        foreach($cart_g['cart'] as $key => $num){
            $aTmp = explode('-',$key);
            $goodsId = $aTmp[0];
            if(!$aGid[$goodsId]){
                $pmtid = $oPromotion->getGoodsPromotionId($goodsId, $this->_mlvid);
                $cart_g['pmt'][$goodsId] = $pmtid;
            }
            $aGid[$goodsId] = 1;
        }
    }

    //读取商品
    function _load_g(&$trading, $cart_g) {
        $oMath = &$this->system->loadModel('system/math');
        $product_ids = array();
        foreach($cart_g['cart'] as $strKey => $num) {
            $aRow = explode('-', $strKey);
            if($aRow[1] != '') $product_ids[] = $aRow[1];
        }
        reset($cart_g);
        if(count($product_ids)>0){
            $aProduct = $this->db->select('SELECT p.*,t.setting,g.score,g.brand_id,g.cat_id,g.type_id,g.image_default,g.thumbnail_pic
                    FROM sdb_products AS p
                    LEFT JOIN sdb_goods AS g ON p.goods_id=g.goods_id
                    LEFT JOIN sdb_goods_type AS t ON g.type_id=t.type_id
                    WHERE p.product_id IN ('.implode(',',$product_ids).')');
        }else{
            $aProduct = array();
        }

        foreach($aProduct as $k=>$p){
            if($this->_mlvid){
                $aMprice = $this->db->selectrow('SELECT price, dis_count FROM sdb_member_lv
                        LEFT JOIN sdb_goods_lv_price ON level_id = member_lv_id AND product_id='.intval($p['product_id']).'
                        WHERE member_lv_id='.intval($this->_mlvid));
                if(floatval($aMprice['dis_count']) <= 0){
                    $aMprice['dis_count'] = 1;
                }
            }else{
                $aMprice['dis_count'] = 1;
            }
            $items_g[$p['product_id']]['bn'] = $p['bn']?$p['bn']:($this->system->getConf('system.product.autobn.prefix').str_pad($p['product_id']+$this->system->getConf('system.product.autobn.beginnum',100),$this->system->getConf('system.product.autobn.length'),0,STR_PAD_LEFT));
            $items_g[$p['product_id']]['name'] = $p['name'].($p['pdt_desc']?' ('.stripslashes($p['pdt_desc']).')':'');
            $items_g[$p['product_id']]['sale_price'] = $p['price'];
            $items_g[$p['product_id']]['price'] = ($aMprice['price'] ? $aMprice['price'] : $oMath->getOperationNumber( $oMath->getOperationNumber($p['price'])*$aMprice['dis_count'] ));
            $items_g[$p['product_id']]['type_id'] = $p['type_id'];
            $items_g[$p['product_id']]['weight'] = $p['weight'];
            $items_g[$p['product_id']]['store'] = $p['store'];
            $items_g[$p['product_id']]['cost'] = $p['cost'];
            $items_g[$p['product_id']]['addon'] = array_merge(unserialize($p['props']),unserialize($p['setting']));
            $items_g[$p['product_id']]['pdt_desc'] = stripslashes($p['pdt_desc']);
            $items_g[$p['product_id']]['goods_id'] = $p['goods_id'];
            $items_g[$p['product_id']]['product_id'] = $p['product_id'];
            $items_g[$p['product_id']]['image_default'] = $p['image_default'];
            $items_g[$p['product_id']]['thumbnail_pic'] = $p['thumbnail_pic'];
            $items_g[$p['product_id']]['score'] = $p['score'];
        }
        unset($aProduct);

        $oCur = &$this->system->loadModel('system/cur');
        $aItems = array();
        //循环处理购物车各商品
        foreach($cart_g['cart'] as $strKey => $num){
            $aRow = explode('-', $strKey);
            //如果存在商品id
            if($aRow[0] != '' && $items_g[$aRow[1]]){
                $strName = '';    //初始化显示名称
                $adjPrice = 0;    //初始化配件价格
                $adjCost = 0;     //初始化配件成本价格
                $adjWeight = 0;
                //如果存在配件
                $adjList = array();    //初始化配件购买量数组 array(<配件id>=><购买量>)
                if($aRow[2] != 'na'){
                    $aAdj = explode('|', $aRow[2]);    //取配件配置列表,配件格式：配件id_配件组_配件数量|
                    $tmpAdjList = array();
                    $tmpAdjGrp = array();
                    $tmpAdjId = array();    //初始化配件id数组(<product_id>=>array(<id1>,<id2>...))
                    $strAdj = '';
                    foreach($aAdj as $val){
                        $adjItem = explode('_', $val);
                        if($adjItem[0]>0 && $adjItem[2]>0){
                            $tmpAdjList[] = $adjItem[2];    //设置配件购买量
                            $tmpAdjGrp[] = $adjItem[1];        //配件栏位
                            $tmpAdjId['product_id'][] = $adjItem[0];    //配件id数组
                        }
                    }
                    if(count($tmpAdjId)){
                        $objGoods = &$this->system->loadModel('trading/goods');
                        $strAdjunct = $objGoods->getGoodsMemo($aRow[0], 'adjunct');    //取本商品配件栏位定义
                        $aAdj = unserialize($strAdjunct);
                        $objProduct = &$this->system->loadModel('goods/finderPdt');
                        $adjName = $objProduct->getList('product_id, name, price, cost, weight', $tmpAdjId, 0, -1);
                        if($adjName){
                            foreach($adjName as $val){  //处理各配件
                                $aAdjuncts[$val['product_id']] = $val;
                            }
                            foreach($tmpAdjId['product_id'] as $key => $pid){
                                if($aAdj[$tmpAdjGrp[$key]] && $aAdjuncts[$pid]){    //如果存在对应栏位定义 和 商品库中存在这个配件
                                    //描述文字 = +配件名称(购买量)
                                    $strName .= '+'.$aAdjuncts[$pid]['name'].'('.$tmpAdjList[$key].')';
                                    //如果本栏位设定了配件价格的优惠金额
                                    if($aAdj[$tmpAdjGrp[$key]]['set_price'] == 'minus'){
                                        //配件总价 += (配件销售价+设定的配件调整额)*配件购买量
                                        $adjPrice += $oMath->minus( array(
                                                    $aAdjuncts[$pid]['price'],
                                                    $aAdj[$tmpAdjGrp[$key]]['price'] )
                                                ) *
                                                $tmpAdjList[$key] ;
                                    }else{
                                        //否则即为折扣方式
                                        //配件总价 += 配件销售价*购买量*设定的优惠倍率
                                        $adjDiscount = abs($aAdj[$tmpAdjGrp[$key]]['price']) ? abs($aAdj[$tmpAdjGrp[$key]]['price']) : 1;
                                        $adjPrice += $oMath->getOperationNumber( $oMath->getOperationNumber( $aAdjuncts[$pid]['price'] ) * $tmpAdjList[$key] * $adjDiscount );
                                    }
                                    $adjList[$pid] += $tmpAdjList[$key];
                                    $strAdj .= $pid.'_'.$tmpAdjGrp[$key].'_'.$tmpAdjList[$key].'|';
                                    $adjCost += ($oMath->getOperationNumber( $aAdjuncts[$pid]['cost'] ) * $tmpAdjList[$key]);
                                    $adjWeight += ($aAdjuncts[$pid]['weight'] * $tmpAdjList[$key]);
                                }
                            }
                        }else{
                            $strAdj = 'na';
                        }
                    }else{
                        $strAdj = 'na';
                    }
                }else{
                    $strAdj = 'na';
                }

                $strKey = $aRow[0].'-'.$aRow[1].'-'.$strAdj;
                $linkKey = $aRow[0].'@'.$aRow[1].'@'.$strAdj;
                //重组货品字符串
                $aGoods = $items_g[$aRow[1]];
                $aGoods['addon']['adjinfo'] = $strAdj;    //= 配件id_配件组序号_配件数量|
                $aGoods['addon']['adjname'] = $strName;    //用于描述文字 +配件名称(购买量)
                $aGoods['adjList'] = $adjList;    //array(<配件id>=><购买量>)
                $aGoods['price'] = $oMath->plus( array( $aGoods['price'] , $adjPrice ) );    //+配件总价
                $aGoods['cost'] = $oMath->plus( array( $aGoods['cost'] , $adjCost ) );    //+配件总成本价
                $aGoods['amount'] = $oMath->getOperationNumber($aGoods['price']) * $num;
                $aGoods['price'] = $oCur->formatNumber($aGoods['price'], false);
                $aGoods['key'] = $strKey;        //存在购物车数组的key
                $aGoods['link_key'] = $linkKey;        //存在购物车数组的key
                $aGoods['enkey'] =  base64_encode($strKey);
                $aGoods['nums'] = $num;
                $aGoods['pmt_id'] = intval($cart_g['pmt'][$aRow[0]]);
                $aGoods['weight'] += $adjWeight;
                switch ($this->system->getConf('point.get_policy')) {
                    case 0:
                        $aGoods['score'] = 0;
                        break;
                    case 1:
                        $aGoods['score'] = $aGoods['price'] * $this->system->getConf('point.get_rate');
                        break;
                    default:
                        break;
                }
                $aItems[] = $aGoods;

                $trading['totalPrice'] = $oMath->plus( array( $trading['totalPrice'],  $aGoods['amount'] ) );
                $trading['totalGainScore'] += $aGoods['score'] * $num;
                $trading['weight'] += $aGoods['weight'] * $num;
            }
        }

        unset($items_g);
        $trading['products'] = &$aItems;

    }

    //捆绑商品
    function _load_p(&$trading, $cart_p) {
        $oMath = &$this->system->loadModel('system/math');
        foreach($cart_p as $pid => $aItems) {
            $pkg_ids[] = $pid;
            $pkg_num[] = $aItems['num'];
        }
        $oCur = &$this->system->loadModel('system/cur');
        $totalPrice = 0;
        $oPackage = &$this->system->loadModel('trading/package');
        $items_p = $oPackage->getPackageByIds($pkg_ids);
        $oPackage->getPackageItems($pkg_ids, $items_p);
        foreach($items_p as $k => $v) {
            $adjList = array();    //初始化配件购买量数组 array(<配件id>=><购买量>)
            $strAdj = '';
            $strName = '';
            $adjCost = 0;
            if(is_array($v['items']) && count($v['items'])){
                foreach($v['items'] as $val){
                    $strAdj .= $val['pkgid'].'_0_'.$val['pkgnum'].'|';
                    $strName .= '+'.$val['name'].'('.$val['pkgnum'].')';
                    $adjList[$val['pkgid']] = $val['pkgnum'];
                    $adjCost += ($oMath->getOperationNumber($val['cost']) * $val['pkgnum']);
                }
            }
            $items_p[$k]['bn'] = $v['bn']?$v['bn']:($this->system->getConf('system.product.autobn.prefix').str_pad($v['goods_id']+$this->system->getConf('system.product.autobn.beginnum',100),$this->system->getConf('system.product.autobn.length'),0,STR_PAD_LEFT));
            $items_p[$k]['name'] = $v['name'];
            $items_p[$k]['price'] = $v['price'];    //暂时没有会员价
            $items_p[$k]['price'] = $oCur->formatNumber($items_p[$k]['price'], false);
            $items_p[$k]['cost'] = $adjCost;
            $items_p[$k]['type_id'] = $v['type_id'];
            $items_p[$k]['weight'] = $v['weight'];
            $items_p[$k]['store'] = $v['store'];
            $items_p[$k]['pdt_desc'] = $v['pdt_desc'];
            $items_p[$k]['goods_id'] = $v['goods_id'];
            $items_p[$k]['product_id'] = 0;
            $items_p[$k]['nums'] = $pkg_num[$k];
            $items_p[$k]['amount'] = $oMath->multiple( array( $items_p[$k]['nums'] , $v['price'] ) );

            $items_p[$k]['addon']['adjinfo'] = $strAdj;    //= 配件id_配件组序号_配件数量|
            $items_p[$k]['addon']['adjname'] = $strName;    //用于描述文字 +配件名称(购买量)
            $items_p[$k]['adjList'] = $adjList;    //array(<配件id>=><购买量>)

            switch ($this->system->getConf('point.get_policy')) {
                case 0:
                    $items_p[$k]['score'] = 0;
                    break;
                case 1:
                    $items_p[$k]['score'] = $v['price'] * $this->system->getConf('point.get_rate');
                    break;
                case 2:
                     $items_p[$k]['score'] = $v['score'];
                    //todo:
                    break;
                default:
                    break;
            }

            $trading['totalPkgScore'] += $oMath->multiple( array($items_p[$k]['score'] , $items_p[$k]['nums'] ) );
            $totalPrice += $oMath->getOperationNumber( $items_p[$k]['amount'] );
            $trading['weight'] += $items_p[$k]['weight'] * $items_p[$k]['nums'];
        }
        $trading['totalPkgPrice'] += $totalPrice;
        $trading['package'] = &$items_p;
    }

    //积分兑换赠品
    function _load_f(&$trading, $cart_f) {
        $totalConsumeScore = 0;
        $oGift = &$this->system->loadModel('trading/gift');
        $items_f = $oGift->getGiftByIds(array_keys($cart_f));
        foreach($items_f as $k => $v) {
            $items_f[$k]['nums'] = $cart_f[$v['gift_id']]['num'];
            $items_f[$k]['amount'] = $items_f[$k]['nums'] * $items_f[$k]['point'];
            $items_f[$k]['weight'] = $v['weight'];
            $totalConsumeScore += $items_f[$k]['amount'];
            $trading['weight'] += $items_f[$k]['weight'] * $items_f[$k]['nums'];
        }
        $trading['totalConsumeScore'] += $totalConsumeScore;
        $trading['gift_e'] = &$items_f;
    }

    //校验购物车
    function checkAll(&$aCart) {
        $bReturn = true;
        $bReturn = $bReturn && $this->_check_g($aCart['g']);
        //todo  检查coupon promotion列表中是否有排coupon的promotion存在
        return $bReturn;
    }

    function loadAll(&$trading, $cart) {
        if (is_array($cart) && count($cart)>0) {
            $trading = array('weight'=>0,'totalPrice'=>0,'totalGainScore'=>0);
            $w_count = 0;
            //c 必须在 g後执行.换言之,优惠券挂载必须在商品挂载之后.解决方法,当购物车除c 以外的单元被删除后,c 需要被删除
            //todo 把coupon拉出来 放到最后执行
            if ($this->_isCheck) $this->checkAll($cart);

            krsort($cart);
            foreach($cart as $code => $c) {
                $s_count = count($c);
                $w_count += $s_count;
                if ($s_count > 0) {
                    call_user_func_array(array(&$this,'_load_'.$code),array(&$trading,$c));
                }
            }
            return $w_count;
        }else{
            return false;
        }
    }

    function getCartObject($aCart,$mlvid,$showPromotion=false,$isCheck=true) {
        $this->_isCheck = $isCheck;
        $this->_mlvid = intval($mlvid);
        $trading = null;

        $w_count = $this->loadAll($trading, $aCart);
        $trading['ifCoupon'] = 1;
        if ($w_count>0){
            $oMath = &$this->system->loadModel('system/math');

            $trading['totalPrice'] = $oMath->plus( array( $trading['totalPrice'], $trading['totalPkgPrice'] ) );
            $trading['pmt_b'] = array(
                                    'totalPrice' => $trading['totalPrice'],
                                    'totalGainScore' => $oMath->plus( array( $trading['totalGainScore'] , $trading['totalPkgScore'] ) ) );
            $oPromotion = &$this->system->loadModel('trading/promotion');
            if ($showPromotion) {
                $oPromotion->apply_pdt_pmt($trading, $mlvid);        //商品促销
                $oPromotion->apply_order_pmt($trading, $mlvid);        //订单促销
            }

            $trading['totalGainScore'] = intval($trading['totalGainScore']);
            $trading['totalConsumeScore'] = intval($trading['totalConsumeScore']);
            $trading['totalScore'] = $trading['totalGainScore'] - $trading['totalConsumeScore'];
            $this->mount_gift_p($trading);
            $this->mount_coupon($trading);
            $this->mount_pmt_o($trading);
            return $trading;
        }else{
            return false;
        }
    }

    function mount_gift_p(&$trading) {
        $aTmp = array();
        $gift= &$this->system->loadModel('trading/gift');
        foreach($trading['gift_p'] as  $k => $v) {
            $aTmp[$k] = $this->db->selectRow('select gift_id,name,weight,point,storage from sdb_gift where gift_id='.$k);
            $aTmp[$k]['nums'] = $v;
        }
        $trading['gift_p'] = $aTmp;
    }

    function mount_coupon(&$trading) {
        $aTmp = array();
        foreach($trading['coupon_p'] as $k => $v) {
            $aTmp[$k] = $this->db->selectRow('select cpns_name,cpns_id from sdb_coupons where cpns_id='.$k);
            $aTmp[$k]['nums'] = $v;
        }
        $trading['coupon_p'] = $aTmp;
    }

    function mount_pmt_o(&$trading) {
        if (!empty($trading['pmt_o']['pmt_ids'])) {
            $oPromotion = &$this->system->loadModel('trading/promotion');
            $trading['pmt_o']['list'] = $oPromotion->getPromotionByIds($trading['pmt_o']['pmt_ids']);
        }
    }
}
?>