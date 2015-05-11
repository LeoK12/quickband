<?php
class mdl_barcode{

    function get($data,$code=39){
        $func = 'code_'.$code;
        if(method_exists($this,$func)){
            return $this->$func($data);
        }else{
            return $data;
        }
    }

    function code_39($data){
        $data = $data;
        
        $slen = strlen($data);
        $lib['0'] = '0001101000';
        $lib['1'] = '1001000010';
        $lib['2'] = '0011000010';
        $lib['3'] = '1011000000';
        $lib['4'] = '0001100010';
        $lib['5'] = '1001100000';
        $lib['6'] = '0011100000';
        $lib['7'] = '0001001010';
        $lib['8'] = '1001001000';
        $lib['9'] = '0011001000';
        $lib['*'] = '0100101000';

        $code = $lib['*'];
        $row1 = '<td rowspan="2" valign="top">'.$this->code_39_line(0,1,40).'</td>';
        $cell='';
        for($j=1;$j<10;$j++){
            $cell.=$this->code_39_line($code{$j},$j%2!=1,30);
        }
        $row1 .= '<td>'.$cell.'</td>';
        $row2 ='<td style="text-align:center;font-size:9px">*</td>';

        for($i=0;$i<$slen;$i++){
            if($code = $lib[$data{$i}]){
                $cell='';
                for($j=0;$j<10;$j++){
                    $cell.=$this->code_39_line($code{$j},$j%2!=1,30);
                }
                $row1.='<td>'.$cell.'</td>';
            }else{
                $row1.='';
            }
            $row2.='<td style="text-align:center;font-size:9px">'.$data{$i}.'</td>';
        }

        $row2 .='<td style="text-align:center;font-size:9px">*</td>';
        $code = $lib['*'];
        $cell = '';
        for($j=0;$j<8;$j++){
            $cell.=$this->code_39_line($code{$j},$j%2!=1,30);
        }
        $row1 .= '<td>'.$cell.'</td>';
        $row1 .= '<td rowspan="2" valign="top">'.$this->code_39_line(0,1,40).'</td>';

        return "<table cellspacing=\"0\" cellpadding=\"0\" border=\"0\"><tr>{$row1}</tr><tr>{$row2}</tr></table>";
    }

    function code_39_line($i,$b,$h){
        $file = $b?'black.gif':'transparent.gif';
        return '<img src="images/'.$file.'" class="x-barcode" width="'.($i?3:1).'pt" height="'.$h.'px" />';
    }
}
?>
