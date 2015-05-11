<?php
require_once(CORE_DIR.'/kernel.php');
require_once(CORE_DIR.'/func_ext.php');

class crontab extends kernel{

    function crontab(){
        parent::kernel();
        $this->run();
    }

    function run(){

        $this->logFile = HOME_DIR.'/logs/access.log.php';
        $this->now = time();
        $this->viewStat();
        $messenger = &$this->loadModel('system/messenger');
        $messenger->runQueue();
    }

    function viewStat(){
        if(!file_exists($this->logFile)){
            file_put_contents($this->logFile,"<?php exit()?>\n");
        }

        if(isset($_GET['action'])){
            error_log($this->now."\t".$_GET['action']."\t".$_GET['p']."\n", 3, $this->logFile);
        }

        if(!file_exists($this->logFile.'.time') || filemtime($this->logFile.'.time') < $this->now-300){ //5分钟处理一次log
            touch($this->logFile.'.time');
            $work = dirname($this->logFile).'/tmp.'.$this->now.'.php';
            copy($this->logFile,$work);
            unlink($this->logFile);
            while($lines = $this->parseLog($work)){
                foreach($lines as $line){
                    if($line[1]=='product:index'){
                        $pdtView[$line[2]][day($line[0])]++;
                    }
                }
            }
            unlink($work);
        }

        $today = day(time());

        if($pdtView>0){
            $db = &$this->database();
            foreach($db->select('select view_count,view_w_count,count_stat,goods_id from sdb_goods where goods_id in ('.implode(',',array_keys($pdtView)).')') as $row){

                if(!($stat = unserialize($row['count_stat']))){
                    $stat=array('view'=>array(),'buy'=>array());
                }

                foreach($pdtView[$row['goods_id']] as $day=>$count){
                    $stat['view'][$day]+=$count;
                }

                $w_count = 0;
                foreach($stat['view'] as $day=>$count){
                    if($day<$today-90){//todo:只保留最近90天
                        unset($stat['view'][$day]);
                    }elseif($day>$today-7){
                        $w_count+=$count;
                    }

                }
                $row['view_w_count']=$w_count;
                $row['view_count']+=array_sum($pdtView[$row['goods_id']]); //浏览量增加
                $stat = $db->quote(serialize($stat));

                $db->exec("update sdb_goods set view_w_count=".intval($row['view_w_count']).",count_stat="
                        .$stat.",view_count=".intval($row['view_count'])." where goods_id=".intval($row['goods_id']),true);

            }
        }

    }

    /**
     * parseLog
     * 节省内存的分段式分析log
     *
     * @param mixed $file
     * @access public
     * @return void
     */
    function parseLog($file){
        if(!isset($this->fs[$file])){
            $this->fs[$file] = fopen($file,'r');
            if(!$this->fs[$file])
                return false;
        }
        if(feof($this->fs[$file])){
            fclose($this->fs[$file]);
            $this->fs[$file] = true;
            return false;
        }else{
            $contents = fread($this->fs[$file], 8192);
            if($p = strrpos($contents,"\n")){
                $end = substr($contents,$p+1);
                $contents = $this->fend[$file].substr($contents,0,$p);
                $this->fend[$file] = $end;
                $return = array();
                foreach(explode("\n",$contents) as $line){
                    if($line{0}!='#' && $line){
                        $return[] = explode("\t",$line);
                    }
                }
            }else{
                $return = array($this->fend[$file].$contents);
                $this->fend[$file] = null;
            }
            return $return;
        }
    }
}
?>
