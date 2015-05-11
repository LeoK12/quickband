<?php
require_once('plugin.php');
class mdl_page extends plugin{

    var $plugin_name = 'layout';
    var $plugin_type = 'dir';
    var $prefix='layout_';

    function getExists($ident){
        if($this->db->selectrow('select page_id from sdb_pages where page_name="'.$ident.'"')){
            return true;
        }else{
            return false;
        }
    }



    function set_tpl_content($aData){
       if($aData){
           $aData['edittime']=time();
           if(!($rs = $this->db->selectrow('select * from sdb_systmpl where tmpl_name="'.$aData['tmpl_name'].'"'))){
               $rs = $this->db->query('select * from sdb_systmpl where 0=1');
               $sqlString = $this->db->GetInsertSQL($rs, $aData);
               $this->db->exec($sqlString);
           }else{
               $rs = $this->db->query("SELECT * FROM sdb_systmpl WHERE tmpl_name='".$aData['tmpl_name']."'");
               $sql = $this->db->GetUpdateSQL($rs, $aData);
               $this->db->exec($sql);

           }
       }
       return true;
    }
    function get_tpl_content($file_name){
        if(!($rs = $this->db->selectrow('select  content from sdb_systmpl where tmpl_name="'.$file_name.'"'))){
            if(file_exists(CORE_DIR.'/html/pages/'.$ident.'.txt')){
                return file_get_contents(CORE_DIR.'/html/pages/'.$file_name.'.txt');
            }
        }else{
            return $rs['content'];
        }
    }
    function editor($ident,$layout,$theme){

        $rs = $this->db->exec('select page_name,page_content,page_time,page_title from sdb_pages where page_name="'.$ident.'"');
        $rows = $this->db->getRows($rs);
        if(!$rows){
            if(file_exists(CORE_DIR.'/html/pages/'.$ident.'.html')){

                $sql = $this->db->getUpdateSQL($rs,array(
                    'page_name'=>$ident,'page_time'=>time(),'page_title'=>$ident,
                    'page_content'=>'<{widgets}>'),true);
                $this->db->exec($sql);

                $this->db->exec('delete from sdb_widgets_set where base_file="page:'.$ident.'"');

                $rs = $this->db->exec('select * from sdb_widgets_set where 0=1');
                $html_content = file_get_contents(CORE_DIR.'/html/pages/'.$ident.'.html');
                $html_content = str_replace("<{t}>","",$html_content);
                $html_content = str_replace("<{/t}>","",$html_content);
                $sql = $this->db->getInsertSQL($rs,array(
                    'base_file'=>$ident,
                    'base_slot'=>0,
                    'widgets_type'=>'usercustom',
                    'widgets_order'=>0,
                    'border'=>'__none__',
                    'tpl'=>'default.html',
                    'params'=>array('usercustom'=>$html_content),
                    'modified'=>time(),
                    ));
                $this->db->exec($sql);

            }else{
                $sql = $this->db->getUpdateSQL($rs,array(
                    'page_name'=>$ident,'page_time'=>time(),'page_title'=>$ident,
                    'page_content'=>file_get_contents(PLUGIN_DIR.'/layout/1-column/layout.html')),true);
                $this->db->exec($sql);
            }
        }else{
            if($layout){

                if($sql = $this->db->getUpdateSQL($rs,array('page_content'=>file_get_contents(PLUGIN_DIR.'/layout/'.$layout.'/layout.html'),'page_time'=>time()),true)){
                    $this->db->exec($sql);
                    $setting = $this->getParams($layout);
                    $setting['slotsNum'] = intval($setting['slotsNum']);
                    if($setting['slotsNum']>0){
                        $setting['slotsNum']--;
                        $this->db->exec("update sdb_widgets_set set base_slot=".$this->db->quote($setting['slotsNum'])." where base_slot>".intval($setting['slotsNum'])." and base_file='page:".$ident."'");
                    }
                }
            }
            
        }
        return true;
        $smarty = &$this->system->loadModel('system/frontend');
        $this->widgets = &$this->system->loadModel('content/widgets');
        $smarty->pagedata['header'] = $this->_header();
        $smarty->pagedata['include'] = 'page:'.$ident;
        $smarty->pagedata['footer'] = $this->_footer();
        $smarty->_plugins['compiler']['widgets'] = array(&$this,'_widgets_bar');
        $smarty->display('content/page_frame.html');

    }
    function _widgets_bar($tag_args, &$smarty){
        $s = $this->_current_file;
//    if(($pos = strpos($s,':')) && $part = substr($s,0,$pos)){
//      if($part=='test'){
//        $s = 'user:'.substr($s,$pos+1);
//      }
//    }

        $i = intval($smarty->_wgbar[$s]++);

        $args = $tag_args;




        return 'echo \'<div class="shopWidgets_panel" base_file="'.$s.'" base_slot="'.$i.'" base_id="'.substr($args['id'],1,-1).'"  >\';$system = &$GLOBALS[\'system\'];
        if(!$GLOBALS[\'_widgets_mdl\'])$GLOBALS[\'_widgets_mdl\'] = $system->loadModel(\'content/widgets\');
        $widgets = &$GLOBALS[\'_widgets_mdl\'];
        $widgets->adminLoad("'.$s.'",'.($args['id']?($i.','.$args['id']):$i).');echo \'</div>\'';

    }
    function _header($theme){
            $ret='<base href="'.$this->system->base_url().'"/>';
        if( constant('DEBUG_CSS')){
            $ret.= '<link rel="stylesheet" href="statics/framework.css" type="text/css" />';
            $ret.='<link rel="stylesheet" href="statics/shop.css" type="text/css" />';
            $ret.='<link rel="stylesheet" href="statics/widgets.css" type="text/css" />';
            $ret.='<link rel="stylesheet" href="statics/widgets_edit.css" type="text/css" />';
        }elseif( constant('GZIP_CSS')){
            $ret.= '<link rel="stylesheet" href="statics/style.zcss" type="text/css" />';
            $ret.='<link rel="stylesheet" href="statics/widgets_edit.css" type="text/css" />';
        }else{
            $ret.= '<link rel="stylesheet" href="statics/style.css" type="text/css" />';
            $ret.='<link rel="stylesheet" href="statics/widgets_edit.css" type="text/css" />';
        }
        $tmp_path='http://'.$_SERVER['HTTP_HOST'].'/'.dirname($_SERVER['PHP_SELF']);
        if( constant('DEBUG_JS')){
            $ret.= '<script src="'.$tmp_path.'/js_src/moo.js"></script>
                    <script src="'.$tmp_path.'/js_src/moomore.js"></script>
                    <script src="'.$tmp_path.'/js_src/mooadapter.js"></script>
                    <script src="'.$tmp_path.'/js_src/jstools.js"></script>
                    <script src="'.$tmp_path.'/js_src/coms/dragdropplus.js"></script>
                    <script src="'.$tmp_path.'/js_src/coms/shopwidgets.js"></script>';
        }elseif( constant('GZIP_JS')){
            $ret.= '<script src="'.$tmp_path.'/js/package/tools.jgz"></script>
                     <script src="'.$tmp_path.'/js/package/widgetsedit.jgz"></script>';
        }else{
            $ret.= '<script src="'.$tmp_path.'/js/package/tools.js"></script>
                     <script src="'.$tmp_path.'/js/package/widgetsedit.js"></script>';
        }
        return $ret;
    }

      function _footer(){
       return __("<div id='drag_operate_box' class='drag_operate_box' style='visibility:hidden;'>
       <div class='drag_handle_box'>
             <table cellpadding='0' cellspacing='0' width='100%'>
                                           <tr>
                                           <td><span class='dhb_title'>标题</span></td>
                                           <td width='40'><span class='dhb_edit'>编辑</span></td>
                                           <td width='40'><span class='dhb_del'>删除</span></td>
                                           </tr>
              </table>
              </div>
          </div>

          <div id='drag_ghost_box' class='drag_ghost_box' style='visibility:hidden'>

          </div>");
    }

}
?>
