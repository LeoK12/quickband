<?php
class mdl_template extends modelFactory{

    function getList(){

        $handle = opendir(THEME_DIR);
        while(false!==($item=readdir($handle))){
            if(substr($item,0,1)!='.' && is_dir(THEME_DIR.'/'.$item)){
                $t[$item]=1;
            }
        }
        closedir($handle);
        foreach($this->db->select('select * from sdb_themes where theme in("'.implode('","',array_keys($t)).'") order by stime desc') as $theme){
            $themes[$theme['theme']] = $theme;
            unset($t[$theme['theme']]);
        }
        $objWg = $this->system->loadModel('content/widgets');
        foreach($objWg->getLibs() as $w){
            $widgets[$w['name']] = $w;
            $widgets[$w['name']]['installed'] = true;
        }
        //未安装
        foreach(array_keys($t) as $item){
            if(file_exists(THEME_DIR.'/'.$item.'/theme.xml')){
                $themes=array_merge(array($item=>$this->initTheme($item)),$themes);
            }
        }
        foreach($this->db->select('SELECT base_file,widgets_type FROM sdb_widgets_set s') as $widset){
            $tname = substr($widset['base_file'],($p=strpos($widset['base_file'],':')+1),strpos($widset['base_file'],'/')-$p);
            if($themes[$tname]){
                $themes[$tname]['widgets'][$widset['widgets_type']] = $widgets[$widset['widgets_type']]?$widgets[$widset['widgets_type']]:array('label'=>$widset['widgets_type'],'name'=>$widset['widgets_type'],'installed'=>false);
            }
        }

        return $themes;

    }
    function tempalte_rename($old_name,$new_name,$theme){

        return $this->db->query('update sdb_tpl_source set tpl_name="'.trim($new_name).'" where tpl_name="'.trim($old_name).'" and tpl_theme="'.trim($theme).'"');
    }
    function get_template_path($theme,$tpl){
        if(file_exists(THEME_DIR.'/'.$theme.'/'.$tpl)){
            return 'user:'.$theme .'/'.$tpl;
        }else{
            if(($default_template=$this->system->getConf('system.custom_template_default')) && file_exists(THEME_DIR.'/'.$theme.'/'.$default_template)){
                return 'user:'.$theme .'/'.$default_template;
            }
            return 'user:'.$theme .'/default.html';

        }

    }
    function remove_tpl($theme,$tpl){
        $sql='delete from sdb_tpl_source where tpl_theme="'.$theme.'" and tpl_file="'.$tpl.'"';
        return $this->db->exec($sql);
    }
    function insert_tpl($aData){
        if(is_array($aData)){
            if(!$this->db->selectrow('select * from sdb_tpl_source where tpl_file="'.$aData['tpl_file'].'" and tpl_theme="'.$aData['tpl_theme'].'"')){
                $rs=$this->db->query('select * from sdb_tpl_source where 1');
                $sql= $this->db->GetInsertSQL($rs,$aData);
                return $this->db->exec($sql);
            }

        }
    }
    function makeXml($theme,$name){
        //THEME_DIR.'/'.$theme

        if(file_put_contents(THEME_DIR.'/'.$theme.'/'.$name.'.xml',$this->__makeConfigFile($theme))){
            return true;
        }else{
            return false;
        }


    }

    function copy_tpl($copy_from,$copy_to,$file_name,$type){
        if(($result=$this->db->select('select * from sdb_widgets_set where base_file="'.$copy_from['type'].':'.$copy_from['theme'].'/'.$copy_from['tpl'].'"'))){
            foreach($result as $key=>$value){
                $aData=$result[$key];
                unset($aData['widgets_id']);
                $aData['base_file']=$copy_to['type'].':'.$copy_to['theme'].'/'.$copy_to['tpl'];
                $rs=$this->db->query('select * from sdb_widgets_set where 1');
                $sql= $this->db->GetInsertSQL($rs,$aData);
                $this->db->query($sql);
            }
        }
        $template=array(
                    'tpl_name'=>$file_name,
                    'tpl_file'=>$file_name,
                    'tpl_theme'=>$copy_from['theme'],
                    'tpl_type'=>$type
                );
        $this->insert_tpl($template);
        return true;
    }
    function remove($theme){
        return $this->__removeDir(THEME_DIR.'/'.$theme);
    }

    function remove_directory($dir){
        if(substr($dir, -1, 1)=="/"){
            $dir=substr($dir, 0, strlen($dir)-1);
        }
        if ($handle=opendir("$dir")){
            while(false!==($item=readdir($handle))){
                if($item!="." && $item!=".."){
                    if (is_dir("$dir/$item")){
                        $this->remove_directory("$dir/$item");
                    }else{
                        unlink("$dir/$item");
                    }
                }
            }
            closedir($handle);
            rmdir($dir);
        }
    }

    function previewImg($theme,$img='preview.jpg'){
        $this->system->sfile(THEME_DIR.'/'.$theme.'/'.$img,'images/theme_dft.jpg',true);
    }

    function allowUpload(&$message){
        if(!function_exists("gzinflate")){
            $message = 'gzip';
            return $message;
        }
        if(!is_writable(THEME_DIR)){
            $message = 'writeable';
            return $message;
        }
        return true;
    }

    function getThemes($theme){

        $list=array("theme.xml","theme-bak.xml");
        $views = array();
        foreach($list as $v){
            if(is_file(THEME_DIR.'/'.$theme.'/'.$v)){
                $views[]=$v;
            }
        }
        
        return $views;
    }

    function update_template($type,$id,$file,$template_type){

        if(isset($file)){
            $aData=array(
                'source_type'=>$type,
                'source_id'=>$id,
                'template_name'=>$file,
                'template_type'=>$template_type
            );
            $rs=$this->db->query('select * from sdb_template_relation where source_type="'.$type.'" and source_id="'.$id.'" and template_type="'.$template_type.'"');
            $sSQL= $this->db->GetUpdateSQL($rs,$aData,true);
            if(!$sSQL || $this->db->query($sSQL)){
                return true;
            }else{
                return false;
            }

       }
    }
    function get_customer_template($template_type,$id,$source_type=null){
        $where=array();
        if($template_type){
            $where[]=' template_type ="'.$template_type.'"';
        }
        if($source_type){
            $where[]=' source_type ="'.$source_type.'"';
        }
        if($id){

            $result = $this->db->selectrow('select template_name from sdb_template_relation where source_id="'.$id.'" and '.implode(' and ',$where));

            if($template_type=='product'){

                if(!$result){

                    $cat=$this->db->selectrow('select cat_id from sdb_goods where goods_id='.$id);

                    if($cat['parent_id']){
                        $cat_id=substr($cat['cat_path'],0,strpos($cat['cat_path'],','));
                    }else{
                        $cat_id=$cat['cat_id'];
                        $result = $this->db->selectrow('select template_name from sdb_template_relation where source_type ="cat" and source_id="'.$cat_id.'" and template_type ="'.$template_type.'"');
                     
                    }


                }
            }
        }
        $id = 'PAGE:'.$id;
        $sSql = 'select B.template_name from sdb_sitemaps as A LEFT JOIN sdb_template_relation as B  ON A.node_id = B.source_id where A.action ="'.$id.' "';
        if($template_type){
            $sSql .= ' AND B.template_type ="'.$template_type.'"';
        }
        if($source_type){
            $sSql .=' AND B.source_type ="'.$source_type.'"';
        }
        $rs = $this->db->selectrow($sSql);
        if($template_type=='page'&& $id && isset($rs)){
            $result=$rs;
        }
  
        return $result['template_name'];
    }
    function set_template($type,$id,$file,$template_type){
        if($file){
            $aData=array(
                'template_name'=>$file,
                'source_type'=>$type,
                'source_id'=>$id,
                'template_type'=>$template_type
            );
            $rs=$this->db->query('select * from sdb_template_relation where 1');
            $sSQL= $this->db->GetInsertSQL($rs,$aData);
            if(!$sSQL || $this->db->query($sSQL)){
                return true;
            }else{
                return false;
            }
        }
    }
    function getViews($theme){


        if ($handle=opendir(THEME_DIR.'/'.$theme)){
            $views = array();
            while(false!==($file=readdir($handle))){
                if ($file{0}!=='.' && $file{0}!=='_' && is_file(THEME_DIR.'/'.$theme.'/'.$file) && (($t=strtolower(strstr($file,'.')))=='.html' || $t=='.htm')){
                    $views[] = $file;
                }
            }
            closedir($handle);
            return $views;
        }else{
            return false;
        }
    }

    function upload($file,&$msg){
        if(!$this->allowUpload($msg)) return false;
        $tar = $this->system->loadModel('utility/tar');
        $handle = fopen ($file['tmp_name'], "r");
        $contents = file_get_contents($handle);
        preg_match('/\<id\>(.*?)\<\/id\>/',$contents,$tar_name);
        $filename=$tar_name[1]?$tar_name[1]:time();
        if(is_dir(THEME_DIR.'/'.trim($filename))){
           $filename=time();
        }
        $sDir=$this->__buildDir(str_replace('\\','/',THEME_DIR.'/'.trim($filename)));
        if($tar->openTAR($file['tmp_name'])){
            if($tar->containsFile('theme.xml')) {

                foreach($tar->files as $id => $file) {
                    $tar_tmp_file = substr($file['name'],strrpos($file['name'],".")+1);
                    if(check_file_name($tar_tmp_file)){
                        $fpath = $sDir.$file['name'];
                        if(!is_dir(dirname($fpath))){
                            if(mkdir_p(dirname($fpath))){
                                file_put_contents($fpath,$tar->getContents($file));
                            }else{
                                $msg = __('权限不允许');
                                return false;
                            }
                        }else{
                            file_put_contents($fpath,$tar->getContents($file));
                        }
                    }
                }
                $tar->closeTAR();
                if(!$config=$this->initTheme(basename($sDir),'','upload')){
                    $this->__removeDir($sDir);
                    $msg=__('shopEx模板包创建失败');
                    return false;
                }
                return $config;
            }else{
                $msg = __('不是标准的shopEx模板包');
                return false;
            }
        }else{
            $msg = __('模板包已损坏，不是标准的shopEx模板包').$file['tmp_name'];
            return false;
        }
    }

    function getBorderFromThemes($file){
        /*
        $wights_border=Array();
        $path=THEME_DIR.'/'.substr($file,5,strpos($file,'/')-5).'/borders/';
        $handle = opendir($path);
        while (false !== ($file = readdir($handle))) {
            if(substr($file,0,1)!='.'){
                $wights_border['borders/'.$file]=file_get_contents($path.$file);
            }
        }
        */
        //closedir($handle);
        $wights_border=Array();
        $path=THEME_DIR.'/'.$file;
        $workdir = getcwd();
         chdir($path);
         $xml = $this->system->loadModel('utility/xml');
         $content=file_get_contents('info.xml');
         $config = $xml->xml2arrayValues($content);

         foreach($config['theme']['borders']['set'] as $k=>$v){
           $wights_border[$v['attr']['tpl']]=file_get_contents($v['attr']['tpl']);
         }
         chdir($workdir);

        return $wights_border;
    }
    function resetTheme($theme){

        $path=THEME_DIR.'/'.$theme;
        $workdir = getcwd();
        chdir($path);
        if(is_file('info.xml')){
            $xml = $this->system->loadModel('utility/xml');
            $config = $xml->xml2arrayValues(file_get_contents('info.xml'));
            $aTheme=array(
                'theme'=>$theme,
                'name'=>$config['theme']['name']['value'],
                'id'=>$config['theme']['id']['value'],
                'version'=>$config['theme']['version']['value'],
                'info'=>$config['theme']['info']['value'],
                'author'=>$config['theme']['author']['value'],
                'site'=>$config['theme']['site']['value'],
                'update_url'=>$config['theme']['update_url']['value'],
                'config'=>array(
                    'config'=>$this->__changeXMLArray($config['theme']['config']['set']),
                    'borders'=>$this->__changeXMLArray($config['theme']['borders']['set']),
                    'views'=>$this->__changeXMLArray($config['theme']['views']['view'])
                )
            );
            /*
                $update=array(
                        'config'=>$this->__changeXMLArray($config['theme']['config']['set']),
                        'borders'=>$this->__changeXMLArray($config['theme']['borders']['set']),
                        'views'=>$this->__changeXMLArray($config['theme']['views']['view'])
                    );

            */
            chdir($workdir);
            if(!$this->updateThemes($aTheme)){
                return false;
            }else{
                return true;
            }
       }
    }
    function initTheme($theme,$replaceWg=false,$upload='',$loadxml=''){
        if(empty($loadxml)){
            $loadxml='theme.xml';
        }
        $configxml='info.xml';
        $this->separatXml($theme);
        $sDir=THEME_DIR.'/'.$theme.'/';
        $xml = $this->system->loadModel('utility/xml');
        $wightes_info = $xml->xml2arrayValues(file_get_contents($sDir.$loadxml));
        if(is_file($sDir.$configxml)){
           $config = $xml->xml2arrayValues(file_get_contents($sDir.$configxml));
        }else{
           $config=$wightes_info;
        }
        if($upload=="upload" && $config['theme']['id']['value']){
            $config['theme']['id']['value']=preg_replace('@[^a-zA-Z0-9]@','_',$config['theme']['id']['value']);
            if(file_rename(THEME_DIR.'/'.$theme,THEME_DIR.'/'.$config['theme']['id']['value'])){
                $sDir=THEME_DIR.'/'.$config['theme']['id']['value'];
                $theme=$config['theme']['id']['value'];
                $replaceWg=false;
            }
        }
        $aTheme=array(
            'name'=>$config['theme']['name']['value'],
            'id'=>$config['theme']['id']['value'],
            'version'=>$config['theme']['version']['value'],
            'info'=>$config['theme']['info']['value'],
            'author'=>$config['theme']['author']['value'],
            'site'=>$config['theme']['site']['value'],
            'update_url'=>$config['theme']['update_url']['value'],
            'config'=>array(
                'config'=>$this->__changeXMLArray($config['theme']['config']['set']),
                'borders'=>$this->__changeXMLArray($config['theme']['borders']['set']),
                'views'=>$this->__changeXMLArray($config['theme']['views']['view'])
            )
        );

        $aWidgets=$wightes_info['theme']['widgets']['widget'];
        $aTheme['theme']=$theme;
        $aTheme['stime']=time();
        if(is_array($aTheme['config']['views']) && count($aTheme['config']['views'])>0){
            foreach($aTheme['config']['views'] as $v){
                $tmp[]=$v['tpl'];
            }
            $aTheme['template']=implode(',',$tmp);
        }else{
            $aTheme['template']='';
        }

        for($i=0;$i<count($aWidgets);$i++){
            $aTmp[$i]['base_file']='user:'.$aTheme['theme'].'/'.$aWidgets[$i]['attr']['file'];
            $aTmp[$i]['base_slot']=$aWidgets[$i]['attr']['slot'];
            $aTmp[$i]['widgets_type']=$aWidgets[$i]['attr']['type'];
            $aTmp[$i]['widgets_order']=$aWidgets[$i]['attr']['order'];
            $aTmp[$i]['title']=$aWidgets[$i]['attr']['title'];
            $aTmp[$i]['domid']=$aWidgets[$i]['attr']['domid'];
            $aTmp[$i]['border']=$aWidgets[$i]['attr']['border'];
            $aTmp[$i]['classname']=$aWidgets[$i]['attr']['classname'];
            $aTmp[$i]['tpl']=$aWidgets[$i]['attr']['tpl'];
            $aTmp[$i]['base_id']=$aWidgets[$i]['attr']['baseid'];

            /*$set=$aWidgets[$i]['set'];
            $aSet = array();
            for($y=0;$y<count($set);$y++){
                $aSet[$set[$y]['attr']['key']]=$set[$y]['attr']['value'];
            }*/
            $aTmp[$i]['params']=strtr($aWidgets[$i]['value'], array_flip(get_html_translation_table(HTML_SPECIALCHARS)));
        }
        $aWidgets=$aTmp;

        $aNumber=$this->countWidgetsByTheme($theme);
        $nNumber=intval($aNumber['num']);
        $insertWidgets=false;
        if($replaceWg){

            if($nNumber){
                $this->deleteWidgetsByTheme($theme);
            }
            $insertWidgets=true;
        }else{
            if($nNumber==0){
                $insertWidgets=true;
            }
        }
        if($insertWidgets && count($aWidgets)>0){

            foreach($aWidgets as $k=>$wg){
                $this->insertWidgets($wg);
            }
        }
        if(!$this->updateThemes($aTheme)){
            return false;
        }else{
            return $aTheme;
        }
    }
    function countWidgetsByTheme($sTheme){
        return $this->db->selectrow('select count("widgets_id") as num from sdb_widgets_set where  base_file like "user:'.$sTheme.'%"');
    }
    function deleteWidgetsByTheme($sTheme){
        return $this->db->query('delete from sdb_widgets_set where  base_file like "user:'.$sTheme.'%"');
    }
    function insertWidgets($aData){
        $rs=$this->db->query('select * from sdb_widgets_set where 1');
        $aData['params']=str_replace('\'','\\\'',$aData['params']);
        $sSQL= $this->db->GetInsertSQL($rs,$aData);
        if(!$sSQL || $this->db->query($sSQL)){
            return true;
        }else{
            return false;
        }
    }
    function __buildDir($sDir){
        if(file_exists($sDir)){
            $aTmp=explode('/',$sDir);
            $sTmp=end($aTmp);
            if(strpos($sTmp,'(')){
                $i=substr($sTmp,strpos($sTmp,'(')+1,-1);
                $i++;
                $sDir=str_replace('('.($i-1).')','('.$i.')',$sDir);
            }else{
                $sDir.='(1)';
            }
            return $this->__buildDir($sDir);
        }else{
            return $sDir.'/';
        }
    }
    function __removeDir($sDir) {
        if($rHandle=opendir($sDir)){
            while(false!==($sItem=readdir($rHandle))){
                if ($sItem!='.' && $sItem!='..'){
                    if(is_dir($sDir.'/'.$sItem)){
                        $this->__removeDir($sDir.'/'.$sItem);
                    }else{
                        if(!unlink($sDir.'/'.$sItem)){
                            trigger_error(__('因权限原因，模板文件').$sDir.'/'.$sItem.__('无法删除'),E_USER_NOTICE);
                        }
                    }
                }
            }
            closedir($rHandle);

            rmdir($sDir);
            return true;
        }else{
            return false;
        }
    }

    function updateThemes($aData){//更新 sdb_themes
        $rs=$this->db->query('select * from sdb_themes where theme="'.$aData['theme'].'"');
        $sql= $this->db->GetUpdateSQL($rs,$aData,true);

        return (!$sql || $this->db->exec($sql));
    }

    function setDefault($theme){
        return $this->system->setConf('system.ui.current_theme',$theme);
    }

    function reset($theme,$xml=''){
        return $this->initTheme($theme,true,'',$xml);
    }

    function getDefault(){
        $defaultTheme=$this->system->getConf('system.ui.current_theme');
        $this->separatXml($defaultTheme);
        return $defaultTheme;
    }
    function separatXml($theme){
        $workdir = getcwd();
        chdir(THEME_DIR.'/'.$theme);
        if(!is_file('info.xml')){
            $content=file_get_contents('theme.xml');
            $rContent=substr($content,0,strpos($content,'<widgets>'));
            /*
            $xml = $this->system->loadModel('utility/xml');
            $content=file_get_contents('theme.xml');
            $config = $xml->xml2arrayValues($content);
            $info=array();
            foreach($config['theme'] as $k=>$v){
                if($k!='widgets'){
                    $info[$k]=$v;
                }
            }
            */
            file_put_contents('info.xml',$rContent.'</theme>');
        }
        chdir($workdir);

    }
    function outputPkg($theme){
        $tar = $this->system->loadModel('utility/tar');
        $workdir = getcwd();

        if(chdir(THEME_DIR.'/'.$theme)){
            $this->__getAllFiles('.',$aFile);
            for($i=0;$i<count($aFile);$i++){
                if($f = substr($aFile[$i],2)){
                    if($f!='theme.xml'){
                        $tar->addFile($f);
                    }
                }
            }
            if(is_file('info.xml')){
                $tar->addFile('info.xml',file_get_contents('info.xml'));
            }
            $tar->addFile('theme.xml',$this->__makeConfigFile($theme));
            //$tar->addFile('info.xml',$this->__makeConfigFile($theme));

            $aTheme=$this->getThemeInfo($theme);

            $this->system->__session_close();
            $charset = $this->system->loadModel('utility/charset');
            //$name = $charset->utf2local(preg_replace('/\s/','-',$aTheme['name'].'-'.$aTheme['version']),'zh');
            $name = $charset->utf2local(preg_replace('/\s/','-',$aTheme['name'].'-'.$aTheme['version']),'zh');
            @set_time_limit(0);
            //header("Cache-Control: no-cache, must-revalidate"); // HTTP/1.1
            header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
            header('Content-type: application/octet-stream');
            header('Content-type: application/force-download');
            header('Content-Disposition: attachment; filename="'.$name.'.tgz"');
            $tar->getTar('output');
            chdir($workdir);
        }else{
            chdir($workdir);
            return false;
        }
    }

    function __getAllFiles($sDir,&$aFile,$loop=true){
        
        if($rHandle=opendir($sDir)){
            while(false!==($sItem=readdir($rHandle))){
                if ($sItem!='.' && $sItem!='..' && $sItem!=''){
                    if(is_dir($sDir.'/'.$sItem)){
                        if($loop){
                            $this->__getAllFiles($sDir.'/'.$sItem,$aFile);
                        }
                    }else{
                        $aFile[]=$sDir.'/'.$sItem;
                    }
                }
            }
            closedir($rHandle);
        }
    }

    function __makeConfigFile($sTheme){
        $aTheme=$this->getThemeInfo($sTheme);



        $aWidget['widgets']=$this->getWidgetsInfo($sTheme);
        foreach($aWidget['widgets'] as $i=>$widget){

            $aWidget['widgets'][$i]['params']=$aWidget['widgets'][$i]['params'];
            $aWidget['widgets'][$i]['base_file']=str_replace('user:'.$sTheme.'/','',$aWidget['widgets'][$i]['base_file']);

        }

        $aTheme['config']['config']=$aTheme['config']['config'];

        $aTheme['config']['views']=$aTheme['views'];
        $aTheme['id']=$aTheme['theme'];

        $smarty = &$this->system->loadModel('system/frontend');
        $smarty->template_dir=CORE_DIR.'/admin/view/';
        $aTheme=array_merge($aTheme,$aWidget);
        $sXML=$smarty->_fetch_compile_include('system/template/theme.xml',$aTheme);
        return $sXML;


    }
    function __changeXMLArray($aArray){
        $aData = array();
        foreach($aArray as $i=>$v){
            unset($v['attr']);
            $aData[$i]=array_merge($v,$aArray[$i]['attr']);
        }
        return $aData;
    }

    function getThemeInfo($sTheme){
        $info = $this->db->selectrow('select * from sdb_themes where theme="'.$sTheme.'"');

        if(isset($info['config'])){

            $info['config'] = unserialize($info['config']);


        }

        return $info;
    }

    function getWidgetsInfo($sName){
        return $this->db->select('select * from sdb_widgets_set where base_file like "user:'.$sName.'/%"');
    }

    //todo page不属于模板
    function htmEdit($src){
        return $this->db->selectrow('select * from sdb_pages where page_title="'.$src.'"');
    }

    function editHtml($title,$data){
        $dat=$this->db->selectrow('select * from sdb_pages where page_title="'.$title.'"');
        if($dat['page_title']){
            $rs=$this->db->query('select * from sdb_pages where page_title="'.$title.'"');
            $sql=$this->db->GetUpdateSQL($rs,$data);
        }else{
            $rs=$this->db->query('select * from sdb_pages where 1');
            $sql= $this->db->GetInsertSQL($rs,array_merge($data,array('page_title'=>$title)));
        }
        return (!$sql || $this->db->exec($sql));
    }

    function applyTheme($theme=null){
        if(!$theme){
            $theme = $this->system->getConf('system.ui.current_theme');
            define('TPL_ID',$theme);
        }
        return $this->getThemeInfo($theme);
    }

    function getContent($theme,$file){
        return file_get_contents(THEME_DIR.'/'.$theme.'/'.$file);
    }
    function delFile($theme,$file){
        return unlink(THEME_DIR.'/'.$theme.'/'.$file);
    }
    function del_template_widgets($theme,$file,$type="user"){
        return $this->db->query('delete from sdb_widgets_set where base_file ="'.$type.':'.$theme.'/'.$file.'"');
    }

    function recoverTpl($theme, $bakfile, $file){
        $dir = THEME_DIR.'/'.$theme.'/';
        return copy($dir.$bakfile, $dir.$file);
    }
    function setContent($theme,$file,$content,$isbak=false){
        if($content){
            $this->__getAllFiles(THEME_DIR.'/'.$theme, $aFile, false);
            if(count($aFile) == 0 || $isbak){
                $aTmp = explode('.', $file);
                $arrNum = count($aTmp) - 1;
                $lastStr = $aTmp[$arrNum];
                if(substr($aTmp[$arrNum-1], 0, 4) == 'bak_') $arrNum -= 1;
                for($i=0; $i<$arrNum; $i++){
                    $preStr .= $aTmp[$i].'.';
                }
                $iLoop = 1;
                foreach($aFile as $item){
                    if(strstr($item, $preStr.'bak_')){
                        preg_match_all('/'.$preStr.'bak_(.*)\./i',$item,$match);
                        if($match[1][0] >= $iLoop){
                            $iLoop = $match[1][0] + 1;
                        }
                    }
                }
                if($isbak){
                    $saveFile = $preStr.'bak_'.$iLoop.'.'.$lastStr;
                    file_rename(THEME_DIR.'/'.$theme.'/'.$file, THEME_DIR.'/'.$theme.'/'.$saveFile);
                }
            }
            file_put_contents(THEME_DIR.'/'.$theme.'/'.$file,stripslashes($content));
            return 1;
        }else{
            return false;
        }
    }
    function getListName($name){
        $ctl = array(
            'default.html'=>__('其它页面'),
            'index.html'=>__('首页'),
            'article.html'=>__('文章页'),
            'product.html'=>__('商品详细页'),
            'comment.html'=>__('商品评论/咨询页'),
            'gallery.html'=>__('商品列表页'),
            'cart.html'=>__('购物车页'),
            'gift.html'=>__('赠品页'),
            'member.html'=>__('会员中心页'),
            'page.html'=>__('站点栏目单独页'),
            'passport.html'=>__('注册/登录页'),
            'search.html'=>__('高级搜索页')
        );

        return $ctl[$name];
    }

    function get_template_list($theme){
        $result=$this->db->select('select * from sdb_tpl_source where tpl_theme="'.$theme.'"');
        $list=array();
        foreach($result as $key=>$value){
            $list[$value['tpl_type']][]=array('tpl_name'=>$value['tpl_name'],'tpl_file'=>$value['tpl_file']);
        }
        return $list;
    }
    function geteditlist($theme){
        $list=array();
        $this->__getAllFiles(THEME_DIR.'/'.$theme,$list,false);
        $ctl=$this->getname();
        if(file_exists(THEME_DIR.'/'.$theme.'/cart.html')){

            if(!file_exists(THEME_DIR.'/'.$theme.'/order_detail.html')){
                copy(THEME_DIR.'/'.$theme.'/cart.html',THEME_DIR.'/'.$theme.'/order_detail.html');
            }

            if(!file_exists(THEME_DIR.'/'.$theme.'/order_index.html')){
                copy(THEME_DIR.'/'.$theme.'/cart.html',THEME_DIR.'/'.$theme.'/order_index.html');
            }
        }
        foreach((array)$list as $key=>$value){
            $file_name=basename($value,'.html');
            if(!strpos($file_name,'.')){

                if(($pos=strpos($file_name,'-'))){
                    $type=substr($file_name,0,$pos);
                    $file[$type][$key]['name']=$ctl[substr($file_name,0,$pos)];
                    $file[$type][$key]['file']=$file_name.'.html';
                }else{
                    $type=$file_name;
                    $file[$file_name][$key]['name']=$ctl[$file_name];
                    $file[$file_name][$key]['file']=$file_name.'.html';
                    //$file[$key]['name']=$ctl[$file_name];
                }
                    if($type){
                    if(!$this->system->getConf('system.custom_template_'.$type)){
                        if($file_name!='default-pro'){
                          $this->system->setConf('system.custom_template_'.$type,$file_name.'.html');
                        }
                    }
                }
                $template=array(
                    'tpl_name'=>$file_name.'.html',
                    'tpl_file'=>$file_name.'.html',
                    'tpl_theme'=>$theme,
                    'tpl_type'=>$type?$type:$file_name
                );
                $this->insert_tpl($template);

            }
        }
        return $file;
    }
    function get_bak_file($theme, $file){
        $list=array();
        $this->__getAllFiles(THEME_DIR.'/'.$theme,$list,false);
        $file = basename($file,'.html');
        foreach((array)$list as $key=>$value){
            $file_name=basename($value,'.html');
            if(strstr($file_name,$file.'.') && preg_match('/.*\\.bak_[0-9]+\\.[^\\.]+/',$value)){
                $template[] = array(
                    'tpl_name'=>$file_name.'.html',
                    'tpl_file'=>$value,
                    'tpl_theme'=>$theme,
                    'tpl_type'=>$type?$type:$file_name
                );
            }
        }
        return $template;
    }
    function getname(){
        $ctl = array(
            'index'=>__('首页'),
            'gallery'=>__('商品列表页'),
            'product'=>__('商品详细页'),
            'comment'=>__('商品评论/咨询页'),
            'article'=>__('文章页'),
            'article'=>__('文章列表页'),
            'gift'=>__('赠品页'),
            'package'=>__('捆绑商品页'),
            'brandlist'=>__('品牌专区页'),
            'brand'=>__('品牌商品展示页'),
            'cart'=>__('购物车页'),
            'search'=>__('高级搜索页'),
            'passport'=>__('注册/登录页'),
            'member'=>__('会员中心页'),
            'page'=>__('站点栏目单独页'),
            'order_detail'=>__('订单详细页'),
            'order_index'=>__('订单确认页'),
            'default'=>__('默认页'),
        );
        return $ctl;
    }
    function templateList($theme){//默认模板列表
        include('shopctls.php');
        $ctl=$this->getname();

        foreach($ctl as $c=>$a){
            if($ctl[$c]){
                $item = array('name'=>$ctl[$c],'items'=>array(),'file'=>($file=$c.'.html'),'available'=>file_exists(THEME_DIR.'/'.$theme.'/'.$file));
                foreach($a as $act=>$i){
                    $item['items'][] = array_merge(array(
                            'act'=>$act,
                            'file'=>($file = $c.'-'.$act.'.html'),
                            'available'=>file_exists(THEME_DIR.'/'.$theme.'/'.$file),
                        ),$i);
                }
                $data[]=$item;
            }
        }
        //$data[]=array('name'=>__('其它页面'),'file'=>'default.html','available'=>file_exists(THEME_DIR.'/'.$theme.'/default.html'));
        return $data;
    }

    function getWdigetsPage(){//取单独页面的信息
        return $this->db->select('SELECT * FROM sdb_widgets_set WHERE base_file NOT LIKE "user:%"');
    }

    function updateWidgets($aData){//更新 sdb_widgets_set
        $rs=$this->db->query('SELECT * FROM sdb_widgets_set WHERE widgets_id = '.$aData['widgets_id']);
        $sql= $this->db->GetUpdateSQL($rs,$aData,true);

        return (!$sql || $this->db->exec($sql));
    }
    function getTemplateByType($tpl_type){
        $rs = $this->db->selectrow('SELECT tpl_type  FROM sdb_tpl_source  WHERE tpl_type  = "'.$tpl_type.'"');
        if(isset($rs)){
            return true;
        }else{
            return false;
        }
    }

}


?>
