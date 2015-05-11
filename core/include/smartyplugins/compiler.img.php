<?php
/*
 * Smarty plugin
 * -------------------------------------------------------------
 * File:     compiler.tplheader.php
 * Type:     compiler
 * Name:     tplheader
 * Purpose:  Output header containing the source file name and
 *           the time it was compiled.
 * -------------------------------------------------------------
 */
function tpl_compiler_img($attrs, &$smarty) {
    $imgLib = array(
/*- begin -*/

'statics/icons/arrow_1.gif'=>'width:11px;height:11px;background-image:url(statics/icons.gif);background-repeat:no-repeat;background-position:0 -0px;',
'statics/icons/arrow_10.gif'=>'width:9px;height:9px;background-image:url(statics/icons.gif);background-repeat:no-repeat;background-position:0 -11px;',
'statics/icons/arrow_11.gif'=>'width:12px;height:8px;background-image:url(statics/icons.gif);background-repeat:no-repeat;background-position:0 -20px;',
'statics/icons/arrow_2.gif'=>'width:7px;height:7px;background-image:url(statics/icons.gif);background-repeat:no-repeat;background-position:0 -28px;',
'statics/icons/arrow_3.gif'=>'width:7px;height:7px;background-image:url(statics/icons.gif);background-repeat:no-repeat;background-position:0 -35px;',
'statics/icons/arrow_4.gif'=>'width:9px;height:9px;background-image:url(statics/icons.gif);background-repeat:no-repeat;background-position:0 -42px;',
'statics/icons/arrow_5.gif'=>'width:11px;height:11px;background-image:url(statics/icons.gif);background-repeat:no-repeat;background-position:0 -51px;',
'statics/icons/arrow_6.gif'=>'width:17px;height:7px;background-image:url(statics/icons.gif);background-repeat:no-repeat;background-position:0 -62px;',
'statics/icons/arrow_7.gif'=>'width:5px;height:5px;background-image:url(statics/icons.gif);background-repeat:no-repeat;background-position:0 -69px;',
'statics/icons/arrow_8.gif'=>'width:18px;height:14px;background-image:url(statics/icons.gif);background-repeat:no-repeat;background-position:0 -74px;',
'statics/icons/arrow_9.gif'=>'width:11px;height:11px;background-image:url(statics/icons.gif);background-repeat:no-repeat;background-position:0 -88px;',
'statics/icons/btn_adj_buy.gif'=>'width:61px;height:22px;background-image:url(statics/icons.gif);background-repeat:no-repeat;background-position:0 -99px;',
'statics/icons/btn_goods_gallery.gif'=>'width:101px;height:26px;background-image:url(statics/icons.gif);background-repeat:no-repeat;background-position:0 -121px;',
'statics/icons/btn_pkg_buy.gif'=>'width:61px;height:22px;background-image:url(statics/icons.gif);background-repeat:no-repeat;background-position:0 -147px;',
'statics/icons/cart.gif'=>'width:16px;height:15px;background-image:url(statics/icons.gif);background-repeat:no-repeat;background-position:0 -169px;',
'statics/icons/icon_asc.gif'=>'width:13px;height:12px;background-image:url(statics/icons.gif);background-repeat:no-repeat;background-position:0 -184px;',
'statics/icons/icon_asc_gray.gif'=>'width:13px;height:12px;background-image:url(statics/icons.gif);background-repeat:no-repeat;background-position:0 -196px;',
'statics/icons/icon_delete.gif'=>'width:13px;height:12px;background-image:url(statics/icons.gif);background-repeat:no-repeat;background-position:0 -208px;',
'statics/icons/icon_desc.gif'=>'width:13px;height:12px;background-image:url(statics/icons.gif);background-repeat:no-repeat;background-position:0 -220px;',
'statics/icons/icon_desc_gray.gif'=>'width:13px;height:12px;background-image:url(statics/icons.gif);background-repeat:no-repeat;background-position:0 -232px;',
'statics/icons/member0.gif'=>'width:16px;height:16px;background-image:url(statics/icons.gif);background-repeat:no-repeat;background-position:0 -244px;',
'statics/icons/member0_grey.gif'=>'width:16px;height:16px;background-image:url(statics/icons.gif);background-repeat:no-repeat;background-position:0 -260px;',
'statics/icons/member1.gif'=>'width:16px;height:16px;background-image:url(statics/icons.gif);background-repeat:no-repeat;background-position:0 -276px;',
'statics/icons/member1_grey.gif'=>'width:16px;height:16px;background-image:url(statics/icons.gif);background-repeat:no-repeat;background-position:0 -292px;',
'statics/icons/member2.gif'=>'width:16px;height:16px;background-image:url(statics/icons.gif);background-repeat:no-repeat;background-position:0 -308px;',
'statics/icons/member2_grey.gif'=>'width:16px;height:16px;background-image:url(statics/icons.gif);background-repeat:no-repeat;background-position:0 -324px;',
'statics/icons/member3.gif'=>'width:16px;height:16px;background-image:url(statics/icons.gif);background-repeat:no-repeat;background-position:0 -340px;',
'statics/icons/member3_grey.gif'=>'width:16px;height:16px;background-image:url(statics/icons.gif);background-repeat:no-repeat;background-position:0 -356px;',
'statics/icons/member4.gif'=>'width:16px;height:16px;background-image:url(statics/icons.gif);background-repeat:no-repeat;background-position:0 -372px;',
'statics/icons/member4_grey.gif'=>'width:16px;height:16px;background-image:url(statics/icons.gif);background-repeat:no-repeat;background-position:0 -388px;',
'statics/icons/member5.gif'=>'width:16px;height:16px;background-image:url(statics/icons.gif);background-repeat:no-repeat;background-position:0 -404px;',
'statics/icons/member5_grey.gif'=>'width:16px;height:16px;background-image:url(statics/icons.gif);background-repeat:no-repeat;background-position:0 -420px;',
'statics/icons/member6.gif'=>'width:16px;height:16px;background-image:url(statics/icons.gif);background-repeat:no-repeat;background-position:0 -436px;',
'statics/icons/member6_grey.gif'=>'width:16px;height:16px;background-image:url(statics/icons.gif);background-repeat:no-repeat;background-position:0 -452px;',
'statics/icons/member7.gif'=>'width:16px;height:16px;background-image:url(statics/icons.gif);background-repeat:no-repeat;background-position:0 -468px;',
'statics/icons/member7_grey.gif'=>'width:16px;height:16px;background-image:url(statics/icons.gif);background-repeat:no-repeat;background-position:0 -484px;',
'statics/icons/pic6.gif'=>'width:30px;height:30px;background-image:url(statics/icons.gif);background-repeat:no-repeat;background-position:0 -500px;',
'statics/icons/thridparty0.gif'=>'width:30px;height:30px;background-image:url(statics/icons.gif);background-repeat:no-repeat;background-position:0 -530px;',
'statics/icons/thridparty1.gif'=>'width:16px;height:14px;background-image:url(statics/icons.gif);background-repeat:no-repeat;background-position:0 -560px;',

'statics/bundle/arrow-down.gif'=>'width:11px;height:11px;background-image:url(statics/bundle.gif);background-repeat:no-repeat;background-position:0 -0px;',
'statics/bundle/cart.gif'=>'width:16px;height:16px;background-image:url(statics/bundle.gif);background-repeat:no-repeat;background-position:0 -11px;',
'statics/bundle/del.gif'=>'width:13px;height:13px;background-image:url(statics/bundle.gif);background-repeat:no-repeat;background-position:0 -27px;',
'statics/bundle/ico_comment_1.gif'=>'width:10px;height:10px;background-image:url(statics/bundle.gif);background-repeat:no-repeat;background-position:0 -40px;',
'statics/bundle/ico_comment_2.gif'=>'width:10px;height:10px;background-image:url(statics/bundle.gif);background-repeat:no-repeat;background-position:0 -50px;',
'statics/bundle/select_row.gif'=>'width:16px;height:14px;background-image:url(statics/bundle.gif);background-repeat:no-repeat;background-position:0 -60px;',
'statics/bundle/spacer.gif'=>'width:2px;height:2px;background-image:url(statics/bundle.gif);background-repeat:no-repeat;background-position:0 -74px;',
'statics/bundle/success.gif'=>'width:16px;height:16px;background-image:url(statics/bundle.gif);background-repeat:no-repeat;background-position:0 -76px;',
'statics/bundle/wheel.gif'=>'width:24px;height:27px;background-image:url(statics/bundle.gif);background-repeat:no-repeat;background-position:0 -92px;',

/*- end -*/
    );

    if(isset($attrs['tag'])){
        if($attrs['tag']{0}=='\'' || $attrs['src']{0}=='"'){
            $tag = substr($attrs['tag'],1,-1);
        }else{
            $tag = $attrs['tag'];
        }
    }else{
        $tag = 'img';
    }

    $html = '?><'.$tag.' ';
    if($attrs['src']{0}=='\'' || $attrs['src']{0}=='"'){
        $src = substr($attrs['src'],1,-1);
    }
    if(isset($imgLib[$src])){
        $attrs['src'] =  '"statics/transparent.gif"';
        $attrs['style'] = $imgLib[$src].$attrs['style'];
    }
    foreach($attrs as $k=>$v){
        if($v{0} != '\'' && $v{0} != '"' && $v{0} != '$'){
            $v = '"'.$v.'"';
        }
        $html.=$k.'='.(($v{0}=='$')?'"<?php echo '.$v.';?>"':$v).' ';
    }
    return $html.' /><?php ';
}
?>