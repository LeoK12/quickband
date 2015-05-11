<?php
class http_base{

    var $timeout = 10;
    var $defaultChunk = 4096;
    var $http_ver = '1.1';

    function action($action,$url,$headers=null,$callback=null,$data=null){
        $url_info = parse_url($url);

        $out = strtoupper($action).' '.(isset($url_info['path'])?$url_info['path']:'/').(isset($url_info['query'])?'?'.$url_info['query']:'')." HTTP/{$this->http_ver}\r\n";
        $host = isset($url_info['port'])?$url_info['host'].':'.$url_info['port']:$url_info['host'];
        $out .= 'Host: '.$host."\r\n";
        $this->responseHeader = &$responseHeader;
        $this->responseBody = &$responseBody;

        /*$gzlib = array();
        if(function_exists('gzdeflate')){
            $gzlib[] = 'deflate';
        }
        if(function_exists('gzuncompress')){
            $gzlib[] = 'gzip';
        }
        if($gzlib){
            $headers['Accept-Encoding'] = implode(',',$gzlib);
        }*/

        if($data){
            if(is_array($data)){
                $data = http_build_query($data);
            }
            $headers['Content-length'] = strlen($data);
            if(!isset($headers['Content-Type'])){
                $headers['Content-Type'] = 'application/x-www-form-urlencoded';
            }
        }

        $headers['Pragma']="no-cache";
        $headers['Cache-Control']="no-cache";
        $headers['Connection']="close";
        $headers['Referer']="http://www.shopex.cn/";

        foreach((array)$headers as $k=>$v){
            $out .= $k.':'.$v."\r\n";
        }
        $out .= "\r\n".$data;
        $data = null;

        $responseHeader = array();
        if($fp = fsockopen($this->proxyHost?$this->proxyHost:$url_info['host'],
            $this->proxyPort?$this->proxyPort:(isset($url_info['port'])?$url_info['port']:80),
            $errno, $errstr, $this->timeout)){

            fwrite($fp, $out);
            $out = null;

            $responseBody = '';
            $status = fgets($fp,512);
            if(preg_match('/\d{3}/',$status,$match)){
                $this->responseCode = $match[0];
            }
            while (!feof($fp)){
                if($raw = trim(fgets($fp,512))){
                    if($p = strpos($raw,':')){
                        $responseHeader[strtolower(trim(substr($raw,0,$p)))] = trim(substr($raw,$p+1));
                    }
                }else{
                    break;
                }
            }
            if(isset($responseHeader['location'])){
                return $this->action($action,$responseHeader['location'],$headers,$callback);
            }

            if(!($chunkmode = (isset($responseHeader['transfer-encoding']) && $responseHeader['transfer-encoding']=='chunked'))){
                $chunklen = $this->defaultChunk;
            }
            while (!feof($fp) &&
                    (!$chunkmode || $chunklen=hexdec(trim($a=fgets($fp,30))))
                ){
                $content = fread($fp, $chunklen);
                $readlen = strlen($content);
                while($chunklen!=$readlen){
                    $buffer = fread($fp, $chunklen-$readlen);
                    if(!strlen($buffer)) break;
                    $readlen += strlen($buffer);
                    $content.=$buffer;
                }
                
                if($callback){
                    if(!call_user_func_array($callback,array(&$this,&$content))){
                        break;
                    }
                }else{
                    $responseBody.=$content;
                }
                if($chunkmode)fread($fp, 2);
            }
            fclose($fp);
            if($callback){
                return $this->responseCode{0}==2;
            }else{
                if($this->responseCode{0}==2){
                    return $responseBody;
                }else{
                    $this->errorInfo = $responseBody;
                    return false;
                }
            }
        }else{
            return false;
        }
    }

}
