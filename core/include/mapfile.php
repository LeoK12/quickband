<?php
define('DF_BEGIN',0);
define('DF_END',1);
define('DF_SIZE',2);
define('DF_MTIME',3);
define('DF_INLINE_DATA',4);

class mapfile{

    var $hs = null; //headerSize
    var $bs = 512; //blockSize
    var $is = 128; //introSize
    var $seq = 0;
    var $maxsize = 15728640; //max 15m
    var $sparse = true; //稀疏文件
    var $auto_remove_eldest_entry = true;

    function mapfile(){
        $this->infoKey = array('hs'=>1,'is'=>1,'bs'=>1,'seq'=>1,'maxsize'=>1);
        $this->pid = mt_rand(0,32768);
        $this->now = time();
    }

    function set($key,$data){
        $data = serialize($data);
        if($this->mainFile){
            $slen = strlen($data);
            $bs = intval(ceil($slen/$this->bs));

            $this->header['used'][$key][DF_MTIME] = $this->now;
            $this->header['used'][$key][DF_SIZE] = $slen;

            if(isset($this->header['used'][$key])){
                $space = $this->header['used'][$key][DF_END] - $this->header['used'][$key][DF_BEGIN];
                if($space >= $bs){
                    fseek($this->handle,$this->hs + $this->is + $this->header['used'][$key][DF_BEGIN]*$this->bs);
                    fputs($this->handle,$data);
                    if($space>$bs){
                        $oldEnd = $this->header['used'][$key][DF_END];
                        $this->header['used'][$key][DF_END] = $this->header['used'][$key][DF_BEGIN] + $bs;
                        $this->_freespace($this->header['used'][$key][DF_END],$oldEnd);
                    }
                    $this->log(__('数据在原地修改@').$this->header['used'][$key][DF_BEGIN].'-'.$this->header['used'][$key][DF_END]);
                    return true;
                }else{
                    $this->_freespace($this->header['used'][$key][DF_BEGIN],$this->header['used'][$key][DF_END]);
                }
            }

            if($this->header['free'] && !$this->_freeSets){
                foreach($this->header['free'] as $k=>$v){
                    $this->_freeSets[$v-$k][$k] = $k;
                }
                ksort($this->_freeSets);
            }
            if(is_array($this->_freeSets))
                foreach($this->_freeSets as $size=>$freeList){
                    if($size>=$bs){
                        if(count($this->_freeSets[$size])>0){
                            $pos = current($this->_freeSets[$size]);
                            unset($this->_freeSets[$size][$pos]);

                            fseek($this->handle,$this->hs + $this->is + $pos*$this->bs);
                            fputs($this->handle,$data);

                            $this->header['used'][$key][DF_BEGIN] = $pos;
                            $this->header['used'][$key][DF_END] = $pos+$bs;
                            $this->log(__('利用空闲@').$pos.'-'.$this->header['free'][$pos]);
                            $this->_alloc($pos,$bs);
                            return true;
                        }else{
                            unset($this->_freeSets[$size]);
                        }
                    }
                }

            $begin = $this->seq;
            if((($begin + $bs)*$this->bs + $this->hs + $this->is) > $this->maxsize){
                //todo: lru
                if(false && $this->auto_remove_eldest_entry){
                    if($free = $this->remove_eldest_entry($bs)){
                        $begin = $free[DF_BEGIN];
                        $this->_freespace($free[DF_BEGIN]+$bs,$free[DF_END]);
                    }else{
                        unset($this->header['used'][$key]);
                        $this->error('No enough dfile-space @'.$this->file.' when alloc '.$bs*$this->hs,E_USER_WARNING);
                        return false;
                    }
                }else{
                    unset($this->header['used'][$key]);
                    $this->error('No enough dfile-space @'.$this->file.' when alloc '.$bs*$this->hs,E_USER_WARNING);
                    return false;
                }
            }else{
                $this->seq+=$bs;
            }

            fseek($this->handle,$this->hs + $this->is + $begin*$this->bs);
            fputs($this->handle,$data);

            $this->log(__('增加@').$begin);
            $this->header['used'][$key][DF_BEGIN] = $begin;
            $this->header['used'][$key][DF_END] = $begin+$bs;
            return true;
        }else{
            return true;
        }
    }

    function remove_eldest_entry($requireSize){
        $s = $requireSize*$this->bs;
        foreach($this->header['used'] as $k=>$v){
            if($v[DF_SIZE]>=$s){
                $this->delete($k);
                return $v;
            }
        }
        return false;
    }

    function log($message){
        if($this->log_func){
            call_user_func_array($this->log_func,array($message));
        }
    }

    function delete($key){
        if($this->mainFile){
            $this->log(__('删除').$key);
            if($this->_freespace($this->header['used'][$key][DF_BEGIN],$this->header['used'][$key][DF_END])){
                unset($this->header['used'][$key]);
                return true;
            }else{
                return false;
            }
        }else{
            $this->tmpHeader['del'][$key] = $key;
            $this->log(__('副进程delete'));
        }
    }

    function _alloc($pos,$blocks){
        $this->log(__('填充@').$pos.'-'.($pos+$blocks));
        if(isset($this->header['free'][$pos])){
            if($this->header['free'][$pos]==$pos+$blocks){
                $oldEnd = $this->header['free'][$pos];
                unset($this->header['free'][$pos]);
                unset($this->freeSpace[$oldEnd]);
            }elseif($this->header['free'][$pos]>$pos+$blocks){
                $oldEnd = $this->header['free'][$pos];
                unset($this->header['free'][$pos]);
                $this->header['free'][$pos+$blocks] = $oldEnd;
                $this->freeSpace[$oldEnd] = $pos+$blocks;
                $this->log(__('重新划分空闲@').($pos + $blocks).'-'.($oldEnd));
                $this->_freeSets[$oldEnd - $pos - $blocks][$pos+$blocks] = $pos+$blocks;
                return true;
            }else{
                return false;
            }
        }else{
            return false;
        }
    }

    /**
     * _freespace 
     * 释放空间，合并碎片
     * 
     * @param mixed $begin 
     * @param mixed $end 
     * @access public
     * @return void
     */
    function _freespace($begin,$end){
        if($end>$begin){
            $this->log(__('释放空间').$begin.'-'.$end);

            if(isset($this->freeSpace[$begin])){
                $early_begin = $this->freeSpace[$begin];

                $this->log(__('合并').$this->freeSpace[$begin].'-'.$begin.','.$begin.'-'.$end);
                if($this->_freeSets){
                    $this->log(__('清空_freeSets[').($begin-$this->freeSpace[$begin]).']['.$this->freeSpace[$begin].']');
                    if($this->_freeSets[$begin-$this->freeSpace[$begin]][$this->freeSpace[$begin]]){
                        $this->log(__('成功'));
                        unset($this->_freeSets[$begin-$this->freeSpace[$begin]][$this->freeSpace[$begin]]);
                    }else{
                        $this->log(__('失败'));
                    }
                }

                unset($this->header['free'][$this->freeSpace[$begin]]);
                unset($this->freeSpace[$begin]);
                $begin = $early_begin;
            }

            if(isset($this->header['free'][$end])){
                $final_end = $this->header['free'][$end];
                $this->log(__('合并').$begin.'-'.$end.','.$end.'-'.$this->header['free'][$end]);
                if($this->_freeSets){
                    $this->log(__('清空_freeSets[').($final_end-$end).']['.$end.']');
                    if($this->_freeSets[$final_end-$end][$end]){
                        $this->log(__('成功'));
                        unset($this->_freeSets[$final_end-$end][$end]);
                    }else{
                        $this->log(__('失败'));
                    }
                }

                unset($this->freeSpace[$final_end]);
                unset($this->header['free'][$end]);
                $end = $final_end;
            }
            $this->header['free'][$begin] = $end;
            $this->freeSpace[$end] = $begin;
            return true;
        }
    }

    function error($msg,$error_code){
        if($this->error_handle){
            call_user_func_array($this->error_handle,func_get_args());
        }else{
            trigger_error($msg,$error_code);
        }
    }

    function &get($key){

        if(isset($this->header['used'][$key])){

            $tmp = $this->header['used'][$key];
            unset($this->header['used'][$key]);
            $this->header['used'][$key] = $tmp;
            unset($tmp);

            fseek($this->handle,$this->hs + $this->is + $this->header['used'][$key][DF_BEGIN]*$this->bs);
            $orgData = fread($this->handle, $this->header['used'][$key][DF_SIZE]);
            if(!($data = unserialize($orgData))){
                fseek($this->handle,$this->hs + $this->is + $this->header['used'][$key][DF_BEGIN]*$this->bs);
                $orgData = fread($this->handle, $this->header['used'][$key][DF_SIZE]+20);
                $this->error('data can\'t unserialize('.$orgData.')',E_USER_WARNING);
                $this->clear(); //自动清空缓存，当无法反解时
                return false;
            }else{

                return $data;
            }
        }else{
            return false;
        }
    }

    function workat($mapfile){
        register_shutdown_function(array(&$this,'close'));
        if(file_exists($mapfile)){
            $this->file = realpath($mapfile);
            if(!file_exists($mapfile.'.lock') || ($this->now-filemtime($mapfile)>60)){
                $this->mainFile = true;
                touch($mapfile.'.lock');
                $this->handle = fopen($mapfile,'r+');
                $this->log(__('主进程 open'));
            }else{
                $this->handle = fopen($mapfile,'r');
                $this->mainFile = false;
            }
            $data = fread($this->handle,$this->is);

            if($info = unserialize(trim($data))){
                foreach($info as $k=>$v){
                    if($this->infoKey[$k]) $this->$k = $v;
                }
            }
            if(fseek($this->handle,$this->is)!=-1){
                if(!($this->header = unserialize(trim(fread($this->handle,$this->hs))))){
                    $this->clear();
                }
                if(is_array($this->header['free']))
                    $this->freeSpace = array_flip($this->header['free']);
            }
            return true;
        }elseif($this->createMapFile($mapfile)){
            touch($mapfile.'.lock');
            $this->file = realpath($mapfile);
            $this->mainFile = true;
            $this->log(__('主进程 创建文件'));
            return true;
        }else{
            $this->log(__('主进程无法创建文件'));
            return false;
        }
    }

    function clear(){
        $this->doNothing = true;
        if($this->mainFile){
            if($this->handle){
                fclose($this->handle);
            }
        }else{
            unlink($this->file.'.lock');
        }
        return $this->createMapFile($this->file);
    }

    function createMapFile($mapfile){

        if(!$this->hs){
            $this->hs = intval($this->maxsize/10);
        }

        $info = array();
        foreach(get_object_vars($this) as $k=>$v){
            if(isset($this->infoKey[$k])){
                $info[$k] = $v;
            }
        }
        if(file_put_contents($mapfile,serialize($info))){
            $this->handle = fopen($mapfile,'r+');
            if($this->sparse){
                fseek($this->handle,$this->maxsize-1);
                fputs($this->handle,"\0");
            }
            return true;
        }else{
            return false;
        }
    }

    function close(){
        if($this->doNothing)return;
        if($this->mainFile && file_exists($this->file.'.lock')){

            $info = array();
            foreach($this->infoKey as $k=>$v){
                $info[$k] = $this->$k;
            }
            $head = serialize($this->header);
            if(($headLength=strlen($head))>$this->hs){
                $this->clear(); //自动清空缓存，当无剩余inode时
                $this->error('No enough dfile-header @'.$this->file.' ,want/free:'.$headLength.'/'.$this->hs,E_USER_WARNING);
            }else{
                fseek($this->handle,$this->is);
                fputs($this->handle,$head);

                fseek($this->handle,0);
                fputs($this->handle,serialize($info));
            }
            fclose($this->handle);
            unlink($this->file.'.lock');
            $this->log(__('主进程 close'));
        }
    }

    function picture(){
        $pic = array();
        if(is_array($this->header['free']))
            foreach($this->header['free'] as $begin=>$end){
                $pic[$begin] = $this->_pic_block($end-$begin,'free',$begin.'-'.$end);
            }
        if(is_array($this->header['used']))
            foreach($this->header['used'] as $key=>$data){
                $pic[$data[DF_BEGIN]] = $this->_pic_block($data[DF_END]-$data[DF_BEGIN],'full',$key.'@'.$data[DF_BEGIN].'-'.$data[DF_END]);
            }
        ksort($pic);
        return '<style>.full{background-color:red;float:left;border-right:1px solid #fff}.free{background-color:green;float:left;border-right:1px solid #fff}</style><div style="width:500px">'.implode("\n",$pic).'</div>';
    }

    function _pic_block($width,$cls,$msg){
        return '<div onclick="alert(\''.htmlspecialchars($msg).'\')" style="width:'.($width*5).'px" class="'.$cls.'">&nbsp</div>';
    }

}
?>
