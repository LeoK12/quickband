<?php
class mdl_backup extends modelFactory{

    function getList($dir=null,$type=null){

        if(!$dir)$dir = HOME_DIR.'/upload';
        $handle=opendir($dir);

        if ($handle = opendir($dir)) {
            while (false !== ($file = readdir($handle))) {
                if(is_file($dir.'/'.$file) && strtolower(strstr($file,'.'))=='.tgz'){
                    $pkgInfo = $this->getInfo($dir.'/'.$file);
                    if($type && $type==$pkgInfo['type']){
                        $return[filemtime($dir.'/'.$file)] = $pkgInfo;
                    }else{
                        $return[filemtime($dir.'/'.$file)] = $pkgInfo;
                    }
                }
            }
            krsort($return);
            closedir($handle);
        }
        return $return;
    }

    function getInfo($pkgName){
        $pkgName = realpath($pkgName);
        if(!$this->_pkgTypes){

            foreach(get_class_methods($this) as $method){
                if(strtolower(substr($method,0,9))=='_pkginfo_'){
                    $this->_pkgTypes[]=strtolower(substr($method,9));
                }
            }
            $this->_xml = &$this->system->loadModel('utility/xml');
            $this->_tar = &$this->system->loadModel('utility/tar');
        }

        $this->_tar->openTAR($pkgName);

        foreach($this->_pkgTypes as $type){
            if($this->_tar->containsFile($type.'.xml')){
                $method = '_pkgInfo_'.$type;
                $file = $this->_tar->getFile($type.'.xml');
                return array_merge(
                    $this->_xml->xml2array($this->_tar->getContents($file)),
                    array(
                        'type'=>$type,
                        'file'=>$pkgName,
                        'size'=>filesize($pkgName),
                        'mtime'=>filemtime($pkgName),
                    ));
            }
        }
        $this->_tar->closeTAR();
    }


    function _pkgInfo_archive($infoArray){
        return $infoArray;
    }

    function startBackup($params,&$nexturl){
        set_time_limit(0);
        header("Content-type:text/html;charset=utf-8");
        $sizelimit = $params['sizelimit'];
        $filename = $params['filename'];
        $fileid = $params['fileid'];
        $tableid = $params['tableid'];
        $startid = $params['startid'];
        include_once(CORE_DIR.'/lib/mysqldumper.class.php');

        $dumper = new Mysqldumper(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);
        $dumper->setDroptables(true);
        $dumper->tableid = $tableid;
        $dumper->startid = $startid;
        $backdir = HOME_DIR.'/backup';
        $finished = $dumper->multiDump($filename,$fileid,$sizelimit,$backdir);

        $fileid++;



        if(!$finished){
            $nexturl = "index.php?ctl=system/backup&act=backup&sizelimit=$sizelimit&filename=$filename&fileid=$fileid&tableid=".$dumper->tableid."&startid=".$dumper->startid;
        }else{
            $dir = HOME_DIR.'/backup';
            $tar = &$this->system->loadModel('utility/tar');
            chdir($dir);
            for($i=1;$i<=$fileid;$i++){
                $tar->addFile('multibak_'.$filename.'_'.$i.'.sql');
            }
            //Add archive xml
            $verInfo = $this->system->version();
            $backupdata['app'] = $verInfo['app'];
            $backupdata['rev'] = $verInfo['rev'];
            $backupdata['vols'] = $fileid;
            $xml = &$this->system->loadModel('utility/xml');
            $xmldata = $xml->array2xml($backupdata,'backup');
            file_put_contents('archive.xml',$xmldata);
            $tar->addFile('archive.xml');
            $tar->filename = 'multibak_'.$filename.'.tgz';
            $tar->saveTar();
            for($i=1;$i<=$fileid;$i++){
                @unlink('multibak_'.$filename.'_'.$i.'.sql');
            }
            @unlink('archive.xml');
        }
        return $finished;
    }
    function recover($sTgz,$vols,$fileid){
        $prefix = substr($sTgz,0,23);
        $sTmpDir=HOME_DIR.'/tmp/'.md5($sTgz).'/';
        if($fileid==1){
            $rTar=&$this->system->loadModel('utility/tar');
            mkdir_p($sTmpDir);
            if($rTar->openTAR(HOME_DIR.'/backup/'.$sTgz)){
                foreach($rTar->files as $id => $aFile) {
                    if(substr($aFile['name'],-4)=='.sql'){
                        $sPath=$sTmpDir.$aFile['name'];
                        file_put_contents($sPath,$rTar->getContents($aFile));
                    }
                }
            }
            $rTar->closeTAR();
            $this->comeback($sTmpDir.$prefix.'_1.sql');
        }else{
            $this->comeback($sTmpDir.$prefix.'_'.$fileid.'.sql');
        }
        if($vols==$fileid){
            //do updatescripts
            $info = $this->getInfo(HOME_DIR.'/backup/'.$sTgz);
            $pkgRev = $info['backup']['rev'];
            $ver = $this->system->version();
            $appRev = $ver['rev'];
            $sDir = realpath(CORE_DIR.'/updatescripts');

            if($pkgRev<$appRev){
                $upgrade = &$this->system->loadModel('system/upgrade');
                echo '<pre>';
                $scripts = $upgrade->scripts($pkgRev,$appRev);

                foreach($scripts as $sqlFile){
                    if(false !== ($sql = file_get_contents(CORE_DIR.'/updatescripts/'.$sqlFile[0]))){
                        foreach($this->db->splitSql($sql) as $line){
                            $this->db->exec($line);
                        }
                    }
                }

                $this->db->exec("drop table if exists sdb_dbver");
                $this->db->exec("create table sdb_dbver(`{$appRev}` varchar(255)) type = MYISAM");

            }

            $this->__finish($sTmpDir);
        }
    }

    function comeback($sFile){
        $rFile=fopen($sFile,'r');
        if(mysql_get_server_info() > '5.0.1') {
            $this->db->query("SET sql_mode=''",true);
        }
        while($sTmp=$this->fgetline($rFile)){
            $sTmp = trim($sTmp);
            if($sTmp!='' && substr($sTmp,0,1)!='#' && substr($sTmp,0,2)!='/*'){
                if(strpos($sTmp,'cachemgr') && strpos($sTmp,'INSERT INTO')===0){
                    continue;
                }
                $sTmp = str_replace('{shopexdump_table_prefix}',DB_PREFIX,$sTmp);
                if(!constant("DB_OLDVERSION"))
                    $sTmp = str_replace('{shopexdump_create_specification}',' DEFAULT CHARACTER SET utf8',$sTmp);
                else
                    $sTmp = str_replace('{shopexdump_create_specification}','',$sTmp);

                if($this->db->query($sTmp,true)){
                    continue;
                }else{
                    echo 'Error:'.$sTmp.'<br>';
                }
            }
        }
        fclose($rFile);
    }


    function fgetline($handle){
        $buffer = fgets($handle, 4096);
        if (!$buffer){
            return false;
        }
        if(( 4095 > strlen($buffer)) || ( 4095 == strlen($buffer) && "\n" == $buffer{4094} )){
            $line = $buffer;
        }else{
            $line = $buffer;
            while( 4095 == strlen($buffer) && "\n" != $buffer{4094} ){
                $buffer = fgets($handle,4096);
                $line.=$buffer;
            }
        }
        return $line;
    }

    function removeTgz($aTgz){
        foreach($aTgz as $sFile){
            @unlink(HOME_DIR.'/backup/'.$sFile);
        }
        return '';
    }
    function __finish($sDir){
        $this->__removeDir($sDir);
        return $sDir;
    }
    function __removeDir($sDir){
        if($rHandle=opendir($sDir)){
            while(false!==($sItem=readdir($rHandle))){
                if ($sItem!='.' && $sItem!='..'){
                    if(is_dir($sDir.'/'.$sItem)){
                        $this->__removeDir($sDir.'/'.$sItem);
                    }else{
                        unlink($sDir.'/'.$sItem);
                    }
                }
            }
            closedir($rHandle);
            rmdir($sDir);
        }
    }
}
?>
