<?php
/**
 * mdl_widgets
 *
 * @uses modelFactory
 * @package
 * @version $Id$
 * @copyright 2003-2007 ShopEx
 * @author Wanglei <flaboy@shopex.cn>
 * @license Commercial
 */
class mdl_widgets extends modelFactory{

    function mdl_widgets(){
        parent::modelFactory();
        $system = &$GLOBALS['system'];
        $this->smarty = &$system->loadModel('system/frontend');
        $this->theme = &$this->smarty->theme;
    }

    function getWidgetsInfo($widgets){
        include(PLUGIN_DIR.'/widgets/'.$widgets.'/widgets.php');
        $setting['type'] = $widgets;
        return $setting;
    }

    function saveSlots($widgetsSet,$files){

        $i=0;
        $slots = array();
        $return = array();

        foreach((array)$widgetsSet as $widgets_id=>$widgets){
            $widgets['modified'] = time();
            $widgets['widgets_order'] = $i++;
            $sql = '';
            if(is_numeric($widgets_id)){
                $slots[$widgets['base_file']][]=$widgets_id;
                $rs = $this->db->query('select * from sdb_widgets_set where widgets_id='.$widgets_id);
                $sql = $this->db->getUpdateSQL($rs,$widgets);

                if($sql && !$this->db->exec($sql)){
                    return false;
                }
            }elseif(preg_match('/^mce_([0-9]+)$/i',$widgets_id,$match)){
                $rs = $this->db->query('select * from sdb_widgets_set where 0=1');

                $sql = $this->db->getInsertSQL($rs,array_merge($widgets,array('widgets_type'=>'html')));
                if(!$this->db->exec($sql)){
                    return false;
                }else{
                    $widgets_id = $this->db->lastInsertId();
                    $return['mce_'.$match[1]] = $widgets_id;
                    unset($_SESSION['_tmp_wg'][$match[1]]);
                    $slots[$widgets['base_file']][]=$widgets_id;
                }

            }elseif(preg_match('/^tmp_([0-9]+)$/i',$widgets_id,$match)){
                $rs = $this->db->query('select * from sdb_widgets_set where 0=1');
                $wg = $_SESSION['_tmp_wg'][$match[1]];
                $setting=$this->getWidgetsInfo($wg['widgets_type']);

                $sql = $this->db->getInsertSQL($rs,array_merge($widgets,$wg,array('vary'=>$setting['vary'],'scope'=> is_array($setting['scope'])?(','.implode($setting['scope'],',').','):$setting['scope'])));
                if(!$this->db->exec($sql)){
                    return false;
                }else{
                    $widgets_id = $this->db->lastInsertId();
                    $return[$_SESSION['_tmp_wg'][$match[1]]['_domid']] = $widgets_id;
                    unset($_SESSION['_tmp_wg'][$match[1]]);
                    $slots[$widgets['base_file']][]=$widgets_id;
                }
            }
        }
        if(is_array($files)){
            foreach($files as $file){
                if(is_array($slots[$file])&&count($slots[$file])>0)
                    $this->db->exec('delete from sdb_widgets_set where widgets_id not in('.implode(',',$slots[$file]).') and base_file="'.$file.'"');
                else
                    $this->db->exec('delete from sdb_widgets_set where base_file="'.$file.'"');
            }
        }
        $this->system->cache->clear();
        return $return;
    }
    function getThisWidgetsInfo($file){
        $info=$this->getWidgetsInfo($file);
        $widgetsLib=array('description'=>$info['description'],'catalog'=>$info['catalog'],'name'=>$file,'label'=>$info['name']);
        return $widgetsLib;
    }
    function getLibs($type=''){

        $widgets_dir = PLUGIN_DIR.'/widgets/';
        $widgetsLib = array();
        $order=array();
        if($type==null){
            if ($handle = opendir($widgets_dir)) {
                while (false !== ($file = readdir($handle))) {
                    if(substr($file,0,1)!='.' && is_dir($widgets_dir.$file)){
                        $info=$this->getWidgetsInfo($file);
                        if($info['catalog']){
                            if(!$widgetsLib['list'][$info['catalog']]){
                                $widgetsLib['list'][$info['catalog']]=$info['catalog'];
                            }
                        }
                        if($info['usual']=='1'){
                            $widgetsLib['usual'][]=array('sort'=>$info['order'],'description'=>$info['description'],'name'=>$file,'label'=>$info['name']);
                        }
                    }
                }
            }

        }else{
            if ($handle = opendir($widgets_dir)) {
                while (false !== ($file = readdir($handle))) {
                    if(substr($file,0,1)!='.' && is_dir($widgets_dir.$file)){
                        $info=$this->getWidgetsInfo($file);
                        if($info['catalog']==$type){
                            $order[]=$info['order']?$info['order']:0;
                            $widgetsLib['list'][] = array('sort'=>$info['order'],'description'=>$info['description'],'name'=>$file,'label'=>$info['name']);
                        }
                        /*
                        if($info['usual']=='1'){
                            $widgetsLib['usual'][]=array('sort'=>$info['order'],'description'=>$info['description'],'name'=>$file,'label'=>$info['name']);
                        }
                         */
                    }
                }
            }
            array_multisort($order, SORT_DESC, $widgetsLib['list']);
        }

        closedir($handle);
        //asort($widgetsLib);
        return $widgetsLib;

    }

    function getLibName($sKey,$type=1){

        $aLibTop=array(
            0=>array('goods'=>__('商品')),
        );
        $aLib=array(
            array('goodscat'=>__('商品分类')),
            array('cart'=>__('购物车')),
            array('article'=>__('文章列表')),
            array('treelist'=>__('站点目录树')),
            array('rankinglist'=>__('商品排行榜')),
            array('hst'=>__('浏览过的商品')),
            array('logo'=>__('商店Logo')),
            array('menu_lv1'=>__('商店导航菜单')),
            array('member'=>__('会员注册/登录')),
            array('topbar'=>__('购物栏/货币选择框')),
            array('search'=>__('商品搜索')),
            array('cur'=>__('货币选择框')),
            array('gift'=>__('推荐赠品列表')),
            array('gifttree'=>__('所有赠品列表')),
            array('brand'=>__('品牌列表')),
            array('links'=>__('友情链接')),
            array('im'=>__('即时通讯')),
            array('kf'=>__('53kf(客服)')),
            array('5107'=>__('ShopEx客服通')),
            array('orderlist'=>__('最新订单列表')),
            array('transport'=>__('最新发货列表')),
            array('nav'=>__('商品分类路径显示')),
            array('circle'=>__('商品旋转展示')),
            array('coverflow'=>__('商品翻转展示')),
            array('floweffect'=>__('商品滑动展示')),
            array('ad'=>__('广告')),
            array('flashview'=>__('Flash滚动广告')),
            array('video'=>__('视频广告')),
            array('exchangeeffect'=>__('商品交叉切换展示')),
            array('include'=>__('站外引用')),
            array('menutree'=>__('商店结构树型菜单')),
            array('usercustom'=>__('自定义内容版块'))

        );

        $aLib=array_merge($aLibTop, $aLib);

        if($type==1){
            foreach($aLib as $v=>$k){
                if(!empty($k[$sKey])){
                    return $k[$sKey];
                }
            }
            return $sKey;
        }

        if($type==2){
            foreach($aLib as $v=>$k){
                if(!empty($k[$sKey])){
                    return $v;
                }
            }
            return count($aLib)+1;

        }

    }

    function load($file,$solt,$id=null){
        if(!isset($this->_cache[$file])){
            $c = array();
            $all = $this->db->select("select * from sdb_widgets_set where base_file = '".$file."' order by widgets_order");
            foreach($all as $i=>$r){
                if($r['base_id']){
                    $c['id'][$r['base_id']][] = &$all[$i];
                }else{
                    $c['slot'][$r['base_slot']][] = &$all[$i];
                }
            }
            $this->_cache[$file] = &$c;
        }

        $rows = array();
        if(!$id){
            if(isset($this->_cache[$file]['slot'][$solt]))$rows = $this->_cache[$file]['slot'][$solt];
        }else{
            $rows = $this->_cache[$file]['id'][$id];
        }

        $smarty = &$this->smarty;
        $data = $smarty->_vars;
        $now = time();


        foreach($rows as $widgets){

            if($widgets['widgets_type']=='html'){
                $widgets['params'] = unserialize($widgets['params']);
                echo $widgets['params']['html'];
            }else{

                $env=$this->getWidgetsInfo($widgets['widgets_type']);
                $widgets['env'] = $this->_vary_value($GLOBALS['runtime'],explode(',',$env['vary']));
                $ident = 'widget_'.$widgets['widgets_id'].implode(',',$widgets['env'],true);
                if($env['vary']=='*' || !$this->system->cache->get($ident,$output)){

                    $smarty->pagedata=array('env'=>&$data['env']);
                    $this->system->co_start();
                    $this->system->checkExpries('WIDGET_'.$widgets['widgets_id']);
                    $ctl = $this->system->reqeust['action']['controller'];
                    $act = $this->system->reqeust['action']['method'];
                    if($widgets['scope']=='' || $widgets['scope']==',,' || strpos(','.$ctl.',',$widgets['scope']) || strpos(','.$ctl.':'.$act.',',$widgets['scope'])){
                        $widgets['params'] = unserialize($widgets['params']);
                        $output = $this->fetch($widgets,false,$widgets_id = $widgets['domid']?$widgets['domid']:'widgets_'.$widgets['widgets_id']);
                        if($widgets['border'] && $widgets['border']!='__none__'){
                            $smarty->pagedata['body'] = &$output;
                            $smarty->pagedata['title'] = &$widgets['title'];
                            $smarty->pagedata['widgets_id'] = &$widgets_id;
                            $smarty->pagedata['widgets_classname'] = &$widgets['classname'];
                            $output = $smarty->fetch('border:'.$widgets['border'].'#'.$widgets_id);
                        }
                    }
                    if($widgets['vary']!='*')
                        $this->system->cache->set($ident,$output,$this->system->co_end());
                }
                echo $output;
            }
        }
        $smarty->_vars = $data;
    }

    function saveEntry($widgets_id,$set){
        $rs = $this->db->query('select * from sdb_widgets_set where widgets_id='.intval($widgets_id));
        $sql = $this->db->getUpdateSQL($rs,$set,true);
        return (!$sql ||( $this->db->exec($sql) && $this->system->cache->setModified('WIDGET_'.$widgets_id) && $this->system->cache->clear()));
    }

    function adminWgBorder($widgets,$theme,$type=false){

        //if($widgets['widgets_type'] =='html') $widgets['widgets_type']='usercustom';
        if($type){
            $o = &$this->system->loadModel('system/template');
            $wights_border=$o->getBorderFromThemes($theme);
            /*
          $wights_border=Array();
          $path=THEME_DIR.'/'.$theme.'/borders/';
          $handle = opendir($path);
          while (false !== ($file = readdir($handle))) {
              if(substr($file,0,1)!='.'){
                  $wights_border['borders/'.$file]=file_get_contents($path.$file);
              }
          }
        closedir($handle);
             */
            $content="{$widgets['html']}";
            $wReplace=Array('<{$body}>','<{$title}>','<{$widgets_classname}>','"<{$widgets_id}>"');
            $title=$widgets['title']?$widgets['title']:$widgets['widgets_type'];
            $wArt=Array($content,$widgets['title'],
                $widgets['classname']
                ,($widgets['domid']?$widgets['domid']:'widgets_'.$widgets['widgets_id']).' widgets_id="'.$widgets['widgets_id'].'"  title="'.$title.'"'.' widgets_theme="' . $theme . '"');
            if($widgets['border']!='__none__'){
                $content=preg_replace("/(class\s*=\s*\")|(class\s*=\s*\')/","$0shopWidgets_box ",$wights_border[$widgets['border']],1);
                $tpl=str_replace($wReplace,$wArt, $content);
            }else{
                $tpl='<div class="shopWidgets_box" widgets_id="'.$widgets['widgets_id'].'" title="'.$title.'" widgets_theme="'.$theme.'">'.$content.'</div>';
            }
        }else{
            $tpl="{$widgets['html']}";
        }

        return trim(preg_replace('!\s+!', ' ', $tpl));
    }

    function editor($widgets,$theme,$values=false){

        $return = array();

        $widgets_dir = PLUGIN_DIR.'/widgets/';

        $setting=$this->getWidgetsInfo($widgets);
        if(!empty($setting['template'])){
            $return['tpls'][$file]=$setting['template'];////////
        }else{
            if($widgets=='html'){
                $widgets='usercustom';
                if(!$values['usercustom']) $values['usercustom']= $values['html'];
            }
            if ($handle = opendir($widgets_dir.$widgets)) {
                while (false !== ($file = readdir($handle))) {
                    if(substr($file,0,1)!='_' && strtolower(substr($file,-5))=='.html' && file_exists($widgets_dir.$widgets.'/'.$file)){
                        $return['tpls'][$file]=$file;
                    }
                }
                closedir($handle);
            }else{
                return false;
            }
        }

        $return['borders']=$this->getThemeBorders($theme);
        $return['borders']['__none__']=__('无边框');

        if(file_exists($widgets_dir.$widgets.'/_config.html')){
            $smarty = &$this->system->loadModel('system/frontend');
            $smarty->plugins_dir[] = CORE_DIR.'/admin/smartyplugin/widgetsEditor';

            $func_file = PLUGIN_DIR.'/widgets/'.$widgets['widgets_type'].'/widget_cfg_'.$widgets['widgets_type'].'.php';
            if(file_exists($func_file)){
                include_once($func_file);
                if(function_exists($func = 'widget_cfg_'.$widgets['widgets_type'])){
                    $menus = array();
                    $smarty->pagedata['data'] = $func($this->system);
                }
            }
            $sFunc='widget_cfg_'.$widgets;
            $sFuncFile=PLUGIN_DIR.'/widgets/'.$widgets.'/'.$sFunc.'.php';
            if(file_exists($sFuncFile)){
                include_once($sFuncFile);
                if(function_exists($sFunc)){
                    $smarty->pagedata['data']=$sFunc($this->system);
                }
            }
    
            $smarty->pagedata['setting'] = &$values;

            $return['html']= $smarty->fetch('file:'.$widgets_dir.$widgets.'/_config.html');
    
        }
        return $return;
    }
    function getThemeBorders($theme){
        $aConfig=$this->db->selectrow('select config from sdb_themes where theme="'.$theme.'"');
        $aConfig=unserialize($aConfig['config']);
        for($i=0;$i<count($aConfig['borders']);$i++){
            $aData[$aConfig['borders'][$i]['tpl']]=$aConfig['borders'][$i]['key'];
        }
        return $aData;

    }

    function getWidget($widgets_id){
        if(false !==($widget = $this->db->selectrow('select * from sdb_widgets_set where widgets_id='.intval($widgets_id)))){
            $widget['params'] = unserialize($widget['params']);
            return $widget;
        }else return false;
    }

    function _errorHandler($errno, $errstr, $errfile, $errline){
        restore_error_handler();
        if($errno == ($errno & E_ERROR | E_USER_ERROR)){
            $this->_run_failed = true;
            $this->system->log($errstr,$errno,1000+log($errno,2));
            $this->_errMsg.='<div><b>'.$errfile.':'.$errline.'</b> '.$errstr.'</div>';
            return true;
        }else{
            $this->system->log($errstr,$errno,1000+log($errno,2));
            return true;
        }
    }

    function _vary_value($values,$vary){

        if($vary[0]=='*'){
            return $values;
        }
        foreach($vary as $v){
            $ret[$v] = $values[$v];
        }
        $ret[] = $values['member_lv'];
        return $ret;
    }

    function fetch($widgets,$is_admin=false,$widgets_id){

        if(!file_exists(PLUGIN_DIR.'/widgets/'.$widgets['widgets_type'])){
            return __('版块').$widgets['widgets_type'].__('不存在.');
        }
        
        $func_file = PLUGIN_DIR.'/widgets/'.$widgets['widgets_type'].'/widget_'.$widgets['widgets_type'].'.php';
        
        if(file_exists($func_file)){
            $this->_errMsg = null;
            $this->_run_failed = false;
            set_error_handler(array(&$this,'_errorHandler'));
            include_once($func_file);
            if(function_exists('widget_'.$widgets['widgets_type'])){
                
                $menus = array();
                $func = 'widget_'.$widgets['widgets_type'];

                $this->smarty->pagedata['data'] = $func($widgets['params'],$this->system,$widgets['env'],$menus);
                $this->smarty->pagedata['menus'] = &$menus;
            }
            restore_error_handler();
            if($this->_run_failed)
                return $this->_errMsg;
        }

        if(!$is_admin){
            $this->smarty->pagedata['dom'] = array('domid'=>$widgets['domid'],'classname'=>$widgets['classname']);
        }

        $this->smarty->pagedata['setting'] = $widgets['params'];
        $this->smarty->pagedata['widgets_id'] = $widgets_id;
        
            
        
        if($is_admin && file_exists(PLUGIN_DIR.'/widgets/'.$widgets['widgets_type'].'/_preview.html')){
            return $this->smarty->fetch('widgets:'.$widgets['widgets_type'].'/_preview.html');
        }else{
            if($this->fastmode){
                return '<div class="widgets-preview">'.$widgets['widgets_type'].'</div>';
            }
            return $this->smarty->fetch('widgets:'.$widgets['widgets_type'].'/'.$widgets['tpl']);
        }
    }

    function adminLoad($file,$solt,$id=null,$edit_mode=false){
        //print_r(func_get_args());
        if(!$this->fastmode && $edit_mode){
            $this->fastmode=true;
        }
        if(!$id){
            $rows = $this->db->select("select * from sdb_widgets_set where base_file = '".$file."' and base_slot=".intval($solt)." order by widgets_order");
        }else{
            $rows = $this->db->select("select * from sdb_widgets_set where base_file = '".$file."' and base_id='".$id."' order by widgets_order");
        }
        //print_r($rows);
        $smarty = &$this->smarty;
        $files = $smarty->_files;
        $_wgbar = $smarty->_wgbar;

        $smarty->template_dir = CORE_DIR.'/admin/view/';
        $o = &$this->system->loadModel('system/template');
        if(substr($file,0,5)=='user:'){
            $theme=substr($file,5,strpos($file,'/')-5);
        }else{
            $theme= $this->system->getConf('system.ui.current_theme');
        }
        $wights_border=$o->getBorderFromThemes($theme);
        foreach($rows as $widgets){
            $widgets['params'] = unserialize($widgets['params']);
                if($widgets['widgets_type']=='html')$widgets['widgets_type']='usercustom';
                $widgets['html'] = $this->fetch($widgets,true);
                $title=$widgets['title']?$widgets['title']:$widgets['widgets_type'];
                $wReplace=Array('<{$body}>','<{$title}>','<{$widgets_classname}>','"<{$widgets_id}>"');
                $wArt=Array($this->adminWgBorder($widgets,$theme),$widgets['title'],
                    $widgets['classname']
                    ,($widgets['domid']?$widgets['domid']:'widgets_'.$widgets['widgets_id']).' widgets_id="'.$widgets['widgets_id'].'"  title="'.$title.'"'.' widgets_theme="' . $theme . '"');

                if($widgets['border']!='__none__'){
                    $content=preg_replace("/(class\s*=\s*\")|(class\s*=\s*\')/","$0shopWidgets_box ",$wights_border[$widgets['border']],1);
                    $widgets_box=str_replace($wReplace,$wArt, $content);
                }else{
                    $widgets_box= '<div class="shopWidgets_box" widgets_id="'.$widgets['widgets_id'].'" title="'.$title.'" widgets_theme="'.$theme.'">'.$this->adminWgBorder($widgets,substr($file,5,strpos($file,'/')-5)).'</div>';
                }
                $widgets_box=preg_replace("/<object[^>]*>([\s\S]*?)<\/object>/i","<div class='sWidgets_flash' title='Flash'>&nbsp;</div>",$widgets_box);
                $replacement=array("'onmouse'i","'onkey'i","'onmousemove'i","'onload'i","'onclick'i","'onselect'i","'unload'i");
                $widgets_box=preg_replace($replacement,array_fill(0,count($replacement),'xshopex'),$widgets_box);
                
                echo preg_replace("/<script[^>]*>([\s\S]*?)<\/script>/i","",$widgets_box);
            
        }
        $smarty->_files = $files;
        $smarty->_wgbar = $_wgbar;
    }
}
