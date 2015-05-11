<?php
class mdl_sfile extends modelFactory {

    function fetch($ident){
        return file_get_contents(HOME_DIR.'/upload/'.$ident);
    }

    function save($tmpfile,$info){

        if($info['usedby']){

            $info['usedby'] = md5($info['usedby']);

            if($rows = $this->db->select('select file_id from sdb_sfiles where usedby="'.$info['usedby'].'"')){
                foreach($rows as $row){
                    $this->remove($row['file_id']);
                }
            }
        }

            $ident = md5(rand(0,time()).var_export($info,true));
            $file = HOME_DIR.'/upload/'.$ident;
            move_uploaded_file($tmpfile['tmp_name'],$file);
            $info = array_merge($info,array(
                'file_id'=>$ident,
                'file_name'=>$tmpfile['name'],
                'file_type'=>$tmpfile['type'],
                'file_size'=>$tmpfile['size'],
                'cdate'=>time()
            ));

            $rs = $this->db->query('select * from sdb_sfiles where 0=1');
            $sql = $this->db->getInsertSQL($rs,$info);
            if($this->db->exec($sql)){
                return $info;
            }else{
                return false;
            }
    }

    function remove($ident){
            if(unlink(HOME_DIR.'/upload/'.$ident)){
                return $this->db->exec('delete from sdb_sfiles where file_id="'.$ident.'"');
            }else{
                return false;
            }
    }

    function output($ident){
        if($row = $this->db->selectrow('select * from sdb_sfiles where file_id="'.$ident.'"')){
            if(file_exists(HOME_DIR.'/upload/'.$ident)){
                header('Content-type: '.$row['file_type']);
                header('Content-Disposition: attachment; filename="'.$row['file_name'].'"');
                $this->system->sfile(HOME_DIR.'/upload/'.$ident);
            }
        }else{
            echo ':(';
        }
    }

    function outputDB($ident){
            if(file_exists(HOME_DIR.'/backup/'.$ident)){
                header('Content-Disposition: attachment; filename="'.$ident.'"');
                $this->system->sfile(HOME_DIR.'/backup/'.$ident);
            }
    }
    function UploadPaymentFile($tmp_file,$ptype){//上传支付网关所需文件，如公钥文件、私钥文件
        if (!is_dir(HOME_DIR."/upload/".$ptype))
            @mkdir(HOME_DIR."/upload/".$ptype);
        $filename = $tmp_file['name'];
        if($ptype=="chinapay"){
            if(($pos=strpos($filename, "."))){
                $suffix = substr($filename, $pos);
                $max_len = 7-strlen($suffix);
                if(strlen(substr($filename, 0, $pos)) > $max_len){
                    $filename = substr($filename, 0, $max_len).$suffix;
                }
            }
            else{
                if(strlen($filename) > 7){
                    $filename = substr($filename, 0, 7);
                }
            }
        }
        move_uploaded_file($tmp_file['tmp_name'],HOME_DIR."/upload/".$ptype."/".$filename);
    }
}
