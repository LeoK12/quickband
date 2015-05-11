<?php
$menu['goods'] = array('label'=>__('商&nbsp;&nbsp;&nbsp;品'),'link'=>'index.php?ctl=goods/product&act=index',
    'items'=>array(
            array('type'=>'html','code'=>__('<div class="section">功能菜单</div><div id="g_menu_sec_1" class="g-menu-sec">')),
                array('type'=>'group','label'=>__('商品管理'),'items'=>array(
                array('type'=>'menu','label'=>__('商品列表'),'link'=>'index.php?ctl=goods/product&act=index'),
                array('type'=>'menu','label'=>__('添加商品'),'link'=>'index.php?ctl=goods/product&act=addNew','target'=>'_blank'),
                array('type'=>'menu','label'=>__('到货通知'),'link'=>'index.php?ctl=goods/gnotify&act=index'),
            )),
            array('type'=>'group','label'=>__('商品分类管理'),'items'=>array(
                array('type'=>'menu','label'=>__('分类列表'),'link'=>'index.php?ctl=goods/category&act=index'),
                array('type'=>'menu','label'=>__('添加分类'),'link'=>'index.php?ctl=goods/category&act=addNew'),
                //array('type'=>'menu','label'=>'导入分类','link'=>'index.php?ctl=goods/gtype&act=index'),
            )),
            array('type'=>'group','label'=>__('前台虚拟分类'),'items'=>array(
                array('type'=>'menu','label'=>__('虚拟分类列表'),'link'=>'index.php?ctl=goods/virtualcat&act=index'),
                array('type'=>'menu','label'=>__('添加虚拟分类'),'link'=>'index.php?ctl=goods/virtualcat&act=addNew'),
                array('type'=>'menu','label'=>__('导入商品分类'),'link'=>'index.php?ctl=goods/virtualcat&act=import'),
                //array('type'=>'menu','label'=>'导入分类','link'=>'index.php    ?ctl=goods/gtype&act=index'),
            )),
            array('type'=>'group','label'=>__('商品类型管理'),'items'=>array(
                array('type'=>'menu','label'=>__('类型列表'),'link'=>'index.php?ctl=goods/gtype&act=index'),
                array('type'=>'menu','label'=>__('添加类型'),'link'=>'index.php?ctl=goods/gtype&act=newType'),
                //array('type'=>'menu','label'=>'导入类型','link'=>'index.php?ctl=goods/comment&act=setting'),
            )),
            array('type'=>'group','label'=>__('规格管理'),'items'=>array(
                array('type'=>'menu','label'=>__('规格列表'),'link'=>'index.php?ctl=goods/specification&act=index'),
                //array('type'=>'menu','label'=>'添加规格','link'=>'index.php?ctl=goods/specification&act=addSpec')
            )),
            array('type'=>'group','label'=>__('品牌管理'),'items'=>array(
                array('type'=>'menu','label'=>__('品牌列表'),'link'=>'index.php?ctl=goods/brand&act=index'),
                array('type'=>'menu','label'=>__('添加品牌'),'link'=>'index.php?ctl=goods/brand&act=addNew'),
            )),
            array('type'=>'group','label'=>__('商品批量处理'),'items'=>array(
                array('type'=>'menu','label'=>__('批量上传'),'link'=>'index.php?ctl=goods/product&act=import'),
            )),
            array('type'=>'html','code'=>'</div>'),
            array('type'=>'html','code'=>__('<div class="section">商品分类目录</div><div id="g_menu_sec_2" class="g-menu-sec x-tree-list" initctree="true">')),
           /* array('section'=>'section','type'=>'tree','model'=>'goods/productCat','label'=>__('商品管理'),'link'=>'index.php?ctl=goods/product&act=index'),*/
            array('type'=>'html','code'=>'</div>
            <script>
            try{
            window.menuAccordion = new ItemAgg($ES(".section","submenu_goods"),$ES(".g-menu-sec","submenu_goods"),{
              show:0,
              onActive:function(s,m){
                s.setStyle("font-weight","bold");
                if(m.get("initctree")){
                var buildTree= function(){
                        new GoodsCatNavTreeList({
                            remoteURL:"index.php?ctl=goods/virtualcat&act=getGoodsCatById&{param}={value}",
                            remoteParamKey:"p[0]",
                            container:"g_menu_sec_2",
                            showStep:1,
                            navURL:"index.php?ctl=goods/product&act=index&filter=a:1:{s:6:\"cat_id\";a:2:{s:1:\"t\";s:{cnamelth}:\"{cname}\";s:1:\"v\";i:{cid};}}"
                        });
                    };
                    m.erase("initctree");
                    m.retrieve("update",buildTree)();
                }
              },
              onBackground:function(s,m){s.setStyle("font-weight","normal")}
             });
             }catch(e){}
            </script>'),
        ));
$menu['order'] = array('label'=>__('订&nbsp;&nbsp;&nbsp;单'),'link'=>'index.php?ctl=order/order&act=index',
    'items'=>array(
            array(
                'type'=>'group',
                'label'=>__('订单管理'),
                'items'=>array(
                    array(
                    'type'=>'menu',
                    'label'=>__('订单列表'),
                    'link'=>'index.php?ctl=order/order&act=index'
                    )
                )
            ),
            array(
                'type'=>'group',
                'label'=>__('单据管理'),
                'items'=>array(
                    array(
                        'type'=>'menu',
                        'label'=>__('收款单'),
                        'link'=>'index.php?ctl=order/payment'
                    ),
                    array(
                        'type'=>'menu',
                        'label'=>__('退款单'),
                        'link'=>'index.php?ctl=order/refund'
                    ),
                    array(
                        'type'=>'menu',
                        'label'=>__('发货单'),
                        'link'=>'index.php?ctl=order/shipping'
                    ),
                    array(
                        'type'=>'menu',
                        'label'=>__('退货单'),
                        'link'=>'index.php?ctl=order/reship'
                    )
                )
            ),
            array(
                'type'=>'group',
                'label'=>__('售后服务管理'),
                'items'=>array(
                     array('type'=>'menu',
                         'label'=>__('功能配置'),
                         'link'=>'index.php?ctl=order/return_product&act=string'
                    ),array(
                        'type'=>'menu',
                        'label'=>__('售后申请列表'),
                        'link'=>'index.php?ctl=order/return_product'
                    )

                )
            ),
            array(
                'type'=>'group',
                'label'=>__('快递单管理'),
                'items'=>array(
                    array(
                        'type'=>'menu',
                        'label'=>__('快递单模板'),
                        'link'=>'index.php?ctl=order/delivery_printer'
                    ),
                    array(
                        'type'=>'menu',
                        'label'=>__('发货信息管理'),
                        'link'=>'index.php?ctl=order/delivery_centers'
                    ),
                )
            )
    ));

$menu['member'] = array('label'=>__('会&nbsp;&nbsp;&nbsp;员'),'link'=>'index.php?ctl=member/member&act=index','keywords'=>array('hy','member'),
    'items'=>array(
            array(
                'type'=>'group',
                'label'=>__('会员管理'),
                'items'=>array(
                    array('type'=>'menu',
                        'label'=>__('会员列表'),
                        'link'=>'index.php?ctl=member/member&act=index'
                    ),
                    array('type'=>'menu',
                        'label'=>__('会员等级'),
                        'link'=>'index.php?ctl=member/level&act=index'
                    ),
     array('type'=>'menu',
                        'label'=>__('会员注册项'),
                        'link'=>'index.php?ctl=member/memberattr&act=index'
                    ),
                    array('type'=>'menu',
                           'label'=>__('邮件短信设置'),
                           'link'=>'index.php?ctl=member/messenger&act=index'
                     )
                )
            ),
            array(
                'type'=>'group',
                'label'=>__('购买咨询'),
                'items'=>array(
                    array('type'=>'menu',
                        'label'=>__('咨询列表'),
                        'link'=>'index.php?ctl=member/gask&act=index'
                    ),
                    array('type'=>'menu',
                        'label'=>__('咨询设置'),
                        'link'=>'index.php?ctl=member/gask&act=setting'
                    )
                )
            ),
            array(
                'type'=>'group',
                'label'=>__('商品评论'),
                'items'=>array(
                    array('type'=>'menu',
                        'label'=>__('评论列表'),
                        'link'=>'index.php?ctl=goods/discuss&act=index'
                    ),
                    array('type'=>'menu',
                        'label'=>__('评论设置'),
                        'link'=>'index.php?ctl=goods/discuss&act=setting'
                    )
                )
            ),
            array(
                'type'=>'group',
                'label'=>__('商店留言'),
                'items'=>array(
                    array('type'=>'menu',
                        'label'=>__('留言列表'),
                        'link'=>'index.php?ctl=member/shopbbs&act=index'
                    ),
                     array('type'=>'menu',
                        'label'=>__('留言设置'),
                        'link'=>'index.php?ctl=member/shopbbs&act=setting'
                    )
                )
            ),
              array(
                'type'=>'group',
                'label'=>__('消息管理'),
                'items'=>array(
                     array('type'=>'menu',
                           'label'=>__('站内消息'),
                           'link'=>'index.php?ctl=member/msgbox&act=index'
                     )
                )
            ),

    ));

$menu['sale'] = array('label'=>__('营销推广'),'link'=>'index.php?ctl=sale/promotion&act=welcome',
    'items'=>array(
            array(
                'type'=>'group',
                'label'=>__('促销活动'),
                'items'=>array(
                    array('type'=>'menu',
                        'label'=>__('活动列表'),
                        'link'=>'index.php?ctl=sale/activity&act=index'
                    )
                )
            ),
            array('type'=>'group',
                'label'=>__('优惠券'),
                'items'=>array(
                    array('type'=>'menu',
                        'label'=>__('优惠券列表'),
                        'link'=>'index.php?ctl=sale/coupon&act=index'
                    ),
                    array('type'=>'menu',
                        'label'=>__('积分兑换优惠券'),
                        'link'=>'index.php?ctl=sale/exchangeCoupon&act=index'
                    )
                )
            ),
            array('type'=>'group',
                'label'=>__('赠品兑换'),
                'items'=>array(
                    array('type'=>'menu',
                        'label'=>__('赠品分类'),
                        'link'=>'index.php?ctl=sale/giftcat&act=index'
                    ),
                    array('type'=>'menu',
                        'label'=>__('赠品列表'),
                        'link'=>'index.php?ctl=sale/gift&act=index'
                    )
                )
            ),
            array('type'=>'group',
                'label'=>__('捆绑销售'),
                'items'=>array(
                    array('type'=>'menu',
                        'label'=>__('捆绑商品列表'),
                        'link'=>'index.php?ctl=goods/package&act=index'
                    )
                )
            ),
            array('type'=>'group',
                'label'=>__('购物积分'),
                'items'=>array(
                    array('type'=>'menu',
                        'label'=>__('积分设置'),
                        'link'=>'index.php?ctl=sale/point&act=pointSetting'
                    )
                )
            ),
            array('type'=>'group',
                'label'=>__('搜索引擎优化'),
                'items'=>array(
                    array('type'=>'menu',
                        'label'=>__('SEO设置'),
                        'link'=>'index.php?ctl=sale/tools&act=seo'
                    ),
                    array('type'=>'menu',
                        'label'=>'Sitemaps',
                        'link'=>'index.php?ctl=sale/tools&act=sitemaps'
                    )
                )
            ),
            array('type'=>'group',
                'label'=>__('站外推广链接'),
                'items'=>array(
                    array('type'=>'menu',
                        'label'=>__('创建链接'),
                        'link'=>'index.php?ctl=sale/tools&act=createLink'
                    )
                )
            ),
            array('type'=>'group',
                'label'=>__('网罗天下免费版'),
                'items'=>array(
                    array('type'=>'menu',
                        'label'=>__('网罗天下免费版'),
                        'link'=>'index.php?ctl=sale/tools&act=wltx_exp_apply'
                    ),
                    array('type'=>'menu',
                        'label'=>__('挑选投放商品'),
                        'link'=>'index.php?ctl=sale/tools&act=wltx_exp'
                    )
                )
            ),
            array('type'=>'group',
                'label'=>__('网罗天下标准版'),
                'items'=>array(
                    array('type'=>'menu',
                        'label'=>__('网罗天下标准版'),
                        'link'=>'index.php?ctl=sale/tools&act=wltx'
                    )
                )
            )
    ));


$menu['site'] = array('label'=>__('页面管理'),'link'=>'index.php?ctl=content/sitemaps&act=welcome',
    'items'=>array(
            array('type'=>'group',
                'label'=>__('网站内容管理'),
                'items'=>array(
                    array('type'=>'menu',
                        'label'=>__('站点栏目'),
                        'link'=>'index.php?ctl=content/sitemaps&act=index'
                    ),
                    array('type'=>'menu',
                        'label'=>__('文章管理'),
                        'link'=>'index.php?ctl=content/articles&act=index'
                    ),                    array('type'=>'menu',
                        'label'=>__('友情链接'),
                        'link'=>'index.php?ctl=content/frendlink&act=index'
                    ),

                    array('type'=>'menu',
                        'label'=>__('网页底部信息'),
                        'link'=>'index.php?ctl=content/content&act=footEdit'
                    ),
                    array('type'=>'menu',
                        'label'=>__('提示信息管理'),
                        'link'=>'index.php?ctl=content/custommessage&act=index'
                    )
                )
            ),
            array('type'=>'group',
                'label'=>__('模板管理'),
                'items'=>array(
                    array('type'=>'menu',
                        'label'=>__('模板列表'),
                        'link'=>'index.php?ctl=system/template&act=index'
                    )
                )
            )
    ));

$menu['analytics'] = array('label'=>__('统计报表'),'link'=>'index.php?ctl=service/wss&act=welcome',
    'items'=>array(
            array(
                'type'=>'group',
                'label'=>__('访问统计'),
                'items'=>array(
                    array('type'=>'menu',
                        'label'=>__('统计功能配置'),
                        'link'=>'index.php?ctl=service/wss&act=show'
                    ),
                    array('type'=>'menu',
                        'label'=>__('查看统计结果'),
                        'link'=>'index.php?ctl=service/wss&act=logininx'
                    )
                ),

            ),
            array(
                'type'=>'group',
                'label'=>__('预付款统计'),
                'items'=>array(
                    array('type'=>'menu',
                        'label'=>__('全站预付款'),
                        'link'=>'index.php?ctl=member/advance&act=index'
                    )
                ),

            ),
            array('type'=>'group',
                'label'=>__('销售统计'),
                'items'=>array(
                    array('type'=>'menu',
                        'label'=>__('销售额总览'),
                        'link'=>'index.php?ctl=sale/salescount&act=index'
                    ),
                    array('type'=>'menu',
                        'label'=>__('销售量(额)排名'),
                        'link'=>'index.php?ctl=sale/salescount&act=countall'
                    ),
                    array('type'=>'menu',
                        'label'=>__('会员购物量(额)排名'),
                        'link'=>'index.php?ctl=sale/salescount&act=membercount'
                    ),
                    array('type'=>'menu',
                        'label'=>__('商品访问购买次数'),
                        'link'=>'index.php?ctl=sale/salescount&act=visitsalecompare'
                    ),
                    array('type'=>'menu',
                        'label'=>__('销售指标分析'),
                        'link'=>'index.php?ctl=sale/salescount&act=salesguide'
                    )
                )
            ),
    ));

if($system->getConf('certificate.distribute')){
$menu['distribution'] = array('label'=>'采购中心','link'=>'index.php?ctl=distribution/supplier&act=generalize',
    'items'=>array(
            array(
                'type'=>'group',
                'label'=>'供应商管理',
                'items'=>array(
                    array('type'=>'menu',
                          'label'=>'供应商列表',
                          'link'=>'index.php?ctl=distribution/supplier&act=index'
                        ),
                    array(
                        'type'=>'menu',
                        'label'=>'采购单列表',
                        'link'=>'index.php?ctl=order/po&act=index'
                        )                    
                ),     
            ),
            array(
                'type'=>'group',
                'label'=>'同步更新配置',
                'items'=>array(
                    array('type'=>'menu',
                        'label'=>'添加配置',
                        'link'=>'index.php?ctl=distribution/autosync&act=addNew'
                    ),
                    array('type'=>'menu',
                        'label'=>'配置列表',
                        'link'=>'index.php?ctl=distribution/autosync&act=index'
                    )
                ),     
            ),
    ));
}

$menu['setting'] = array('label'=>__('商店配置'),'hidden'=>true,'link'=>'index.php?ctl=system/setting&act=welcome',
    'items'=>array(
            'global'=>array('type'=>'group',
                'label'=>__('全站设置'),
                'items'=>array(
                    array('type'=>'menu',
                        'label'=>__('基本设置'),
                        'link'=>'index.php?ctl=system/setting&act=siteBasic'
                    ),
                    array('type'=>'menu',
                        'label'=>__('购物显示设置'),
                        'link'=>'index.php?ctl=system/setting&act=shoppingbasic'
                    ),
                    array('type'=>'menu',
                        'label'=>__('商品图片设置'),
                        'link'=>'index.php?ctl=system/setting&act=watermark'
                    ),
                    'cer_sho'=>array('type'=>'menu',
                        'label'=>__('授权证书'),
                        'link'=>'index.php?ctl=service/certificate&act=showIndex'
                    ),
                 
                    array('type'=>'menu',
                        'label'=>__('全局变量'),
                        'link'=>'index.php?ctl=system/magicvars&act=index'
                    ),
                    array('type'=>'menu',
                        'label'=>__('在线客服设置'),
                        'link'=>'index.php?ctl=system/setting&act=imsetting'
                    ),
                    array('type'=>'menu',
                        'label'=>__('绿卡专享服务'),
                        'link'=>'index.php?ctl=system/setting&act=greencard'
                    ),
                )
            ),
            array('type'=>'group',
                'label'=>__('支付管理'),
                'items'=>array(
                    array('type'=>'menu',
                        'label'=>__('支付方式'),
                        'link'=>'index.php?ctl=trading/payment&act=index'
                    )
                )
            ),
            array('type'=>'group',
                'label'=>__('配送管理'),
                'items'=>array(
                    array('type'=>'menu',
                        'label'=>__('配送方式'),
                        'link'=>'index.php?ctl=trading/delivery&act=index'
                    ),
                    array('type'=>'menu',
                        'label'=>__('地区管理'),
                        'link'=>'index.php?ctl=trading/deliveryarea&act=index'
                    ),
                    array('type'=>'menu',
                        'label'=>__('物流公司'),
                        'link'=>'index.php?ctl=trading/deliverycorp&act=index'
                    )
                )
            ),
            array('type'=>'group',
                'label'=>__('管理员'),
                'super_only'=>1,
                'items'=>array(
                    array('type'=>'menu',
                        'label'=>__('管理员列表'),
                        'link'=>'index.php?ctl=admin/operator&act=index'
                    ),
                    array('type'=>'menu',
                        'label'=>__('角色管理'),
                        'link'=>'index.php?ctl=admin/roles&act=index'
                    ),
                    /*array('type'=>'menu',
                        'label'=>'日志管理',
                        'link'=>'index.php?ctl=admin/logs&act=index'
                    )*/
                )
            ),
            'cur'=>array('type'=>'group',
                'label'=>__('货币管理'),
                'items'=>array(
                    array('type'=>'menu',
                        'label'=>__('货币管理'),
                        'link'=>'index.php?ctl=system/cur&act=index'
                    )
                )
            ),
            array('type'=>'group',
                'label'=>__('第三方整合'),
                'items'=>array(
                    array('type'=>'menu',
                        'label'=>__('第三方整合'),
                        'link'=>'index.php?ctl=system/passport&act=getPassportList'
                    )
                )
            ),
            'local'=>array('type'=>'group',
                'label'=>__('本地化管理'),
                'items'=>array(
                    array('type'=>'menu',
                        'label'=>__('地区设置'),
                        'link'=>'index.php?ctl=system/location&act=index'
                    ),
//                    array('type'=>'menu',
//                        'label'=>__('地区管理'),
//                        'link'=>'index.php?ctl=system/location&act=action'
//                    ),
                )
            ),
    ));

$menu['tools'] = array('label'=>__('工具'),'hidden'=>true,'link'=>'index.php?ctl=system/tools&act=welcome',
    'items'=>array(
            'data'=>array('type'=>'group',
                'label'=>__('数据管理类'),
                'items'=>array(
                    'bac_ind'=>array('type'=>'menu',
                        'label'=>__('数据备份'),
                        'link'=>'index.php?ctl=system/backup&act=index'
                    ),
                    'com_ind'=>array('type'=>'menu',
                        'label'=>__('数据恢复'),
                        'link'=>'index.php?ctl=system/comeback&act=index'
                    ),

                    array('type'=>'menu',
                        'label'=>__('数据库校验'),
                        'link'=>'index.php?ctl=system/debug&act=check_database'
                    ),
                    'deb_cle'=>array('type'=>'menu',
                        'label'=>__('体验数据清除'),
                        'link'=>'index.php?ctl=system/debug&act=clear'
                    )
                )
            ),
            array('type'=>'group',
                'label'=>__('商店运营类'),
                'items'=>array(
                    array('type'=>'menu',
                        'label'=>__('网店暂停营业'),
                        'link'=>'index.php?ctl=system/debug&act=index'
                    )
                )
            ),
            array('type'=>'group',
                'label'=>__('页面显示类'),
                'items'=>array(
                    array('type'=>'menu',
                        'label'=>__('无法找到页内容'),
                        'link'=>'index.php?ctl=system/tools&act=errorpage&404',
                        'target'=>"{data:'p[0]=404'}"
                    ),
                    array('type'=>'menu',
                        'label'=>__('系统错误页内容'),
                        'link'=>'index.php?ctl=system/tools&act=errorpage&500',
                        'target'=>"{data:'p[0]=500'}"
                    ),
                    array('type'=>'menu',
                        'label'=>__('搜索为空时显示内容'),
                        'link'=>'index.php?ctl=system/tools&act=errorpage&searchempty',
                        'target'=>"{data:'p[0]=searchempty'}"
                    )
                )
            ),
        'extend'=>array('type'=>'group',
                'label'=>__('网店扩展'),
                'items'=>array(
                    array('type'=>'menu',
                        'label'=>__('应用中心'),
                        'link'=>'index.php?ctl=system/appmgr&act=index'
                    ),
                    array('type'=>'menu',
                        'label'=>__('插件与扩展'),
                        'link'=>'index.php?ctl=system/addon&act=index'
                    ),
/*
                    (defined('RSC_PORTAL')&& ($certificate = $system->getConf('certificate.id')))?array('type'=>'menu',
                        'label'=>__('第三方应用平台'),
                        'link'=>RSC_PORTAL.'?certi_id='.$certificate,
                        'target'=>"{fm:'main',update:$('main')}"
                    ):null,*/
                )
            ),
        'trigger'=>array('type'=>'group',
                'label'=>__('自动化处理'),
                'items'=>array(
                    array('type'=>'menu',
                        'label'=>__('网店机器人'),
                        'link'=>'index.php?ctl=system/trigger&act=index'
                    ),
                )
            ),
));

if(defined('SAAS_MODE')&&SAAS_MODE){
    unset($menu['setting']['items']['global']['items']['cer_sho']);    //证书
    unset($menu['setting']['items']['local']);    //本地化管理
    unset($menu['setting']['items']['cur']);    //货币管理
    unset($menu['tools']['items']['data']);    //数据管理
    unset($menu['tools']['items']['extend']);    //网店扩展
    unset($menu['setting']['items'][1]['items'][2]); //物流公司
    $menu['setting']['items']['global']['items']['dom_ind'] = array('type'=>'menu',
                        'label'=>__('独立域名绑定'),
                        'link'=>'index.php?ctl=service/domainbind&act=index'
                    );
}



if(isset($_GET['__debugger']))$menu['setting']['items'][] = array('type'=>'menu','label'=>'参数表','link'=>'index.php?ctl=system/setting&act=config');
if(defined('CUSTOM_CORE_DIR')&&file_exists(CUSTOM_CORE_DIR.'/include/customSchema.php')){
    //include('customSchema.php');
    include(CUSTOM_CORE_DIR.'/include/customSchema.php');
    if (is_array($cusmenu)){
        foreach($cusmenu as $key => $val){
            if (!array_key_exists($key,$menu)){
                $menu[$key]=$val;
            }
            else{
                if (is_array($val['items'])){
                    foreach($val['items'] as $skey => $sval){
                        $groupexists=0;
                        if ($sval['type']=="group"){
                            if (is_array($menu[$key]['items'])){
                                foreach($menu[$key]['items'] as $pkey => $pval){
                                    if ($sval['label']==$pval['label']){
                                        $groupexists=1;
                                        break;
                                    }
                                }
                                if ($groupexists){
                                    if (is_array($sval['items'])){
                                        foreach($sval['items'] as $sskey => $ssval){
                                            if ($ssval['position']=='begin'){
                                                array_unshift($menu[$key]['items'][$pkey]['items'],$ssval);
                                            }
                                            elseif ($ssval['position']=='end'){
                                                //$menu[$key]['items'][$pkey]['items'][]=$ssval;
                                                array_push($menu[$key]['items'][$pkey]['items'],$ssval);
                                            }
                                            elseif ($ssval['position']=='before'){
                                                foreach($menu[$key]['items'][$pkey]['items'] as $ppkey => $ppval){
                                                    if ($ppval['label']==$ssval['reference']){
                                                        array_push($menu[$key]['items'][$pkey]['items'],'');
                                                        for($i=count($menu[$key]['items'][$pkey]['items'])-1;$i>$ppkey;$i--){
                                                            $menu[$key]['items'][$pkey]['items'][$i]=$menu[$key]['items'][$pkey]['items'][$i-1];
                                                        }
                                                        $menu[$key]['items'][$pkey]['items'][$ppkey]=$ssval;
                                                        break;
                                                    }
                                                }
                                            }
                                            elseif ($ssval['position']=='after'){
                                                 foreach($menu[$key]['items'][$pkey]['items'] as $ppkey => $ppval){
                                                    if ($ppval['label']==$ssval['reference']){
                                                        array_push($menu[$key]['items'][$pkey]['items'],'');
                                                        for($i=count($menu[$key]['items'][$pkey]['items'])-1;$i>$ppkey+1;$i--){
                                                            $menu[$key]['items'][$pkey]['items'][$i]=$menu[$key]['items'][$pkey]['items'][$i-1];
                                                        }
                                                        $menu[$key]['items'][$pkey]['items'][$ppkey + 1]=$ssval;
                                                        break;
                                                    }
                                                }
                                            }
                                            else{
                                                array_push($menu[$key]['items'][$pkey]['items'],$ssval);
                                            }
                                        }
                                    }
                                }
                                else{
                                    if ($sval['position']=='begin')
                                        array_unshift($menu[$key]['items'],$sval);
                                    elseif ($sval['position']=='end')
                                        array_push($menu[$key]['items'],$sval);
                                    elseif ($sval['position']=='before'){
                                        foreach($menu[$key]['items'] as $pkey => $pval){
                                            if ($pval['label']==$sval['reference']){
                                                array_push($menu[$key]['items'],'');
                                                for($i=count($menu[$key]['items'])-1;$i>$pkey;$i--){
                                                    $menu[$key]['items'][$i]=$menu[$key]['items'][$i-1];
                                                }
                                                $menu[$key]['items'][$pkey]=$sval;
                                                break;
                                            }
                                        }
                                    }
                                    elseif ($sval['position']=='after'){
                                        foreach($menu[$key]['items'] as $pkey => $pval){
                                            if ($pval['label']==$sval['reference']){
                                                array_push($menu[$key]['items'],'');
                                                for($i=count($menu[$key]['items'])-1;$i>$pkey+1;$i--){
                                                    $menu[$key]['items'][$i]=$menu[$key]['items'][$i-1];
                                                }
                                                $menu[$key]['items'][$pkey + 1]=$sval;
                                                break;
                                            }
                                        }
                                    }
                                    else{
                                        array_push($menu[$key]['items'],$sval);
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    }
}

?>
