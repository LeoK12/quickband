<?php
function goods_insert_link($gid, $aData , &$object){
        $aLinked = $object->db->select("SELECT * FROM sdb_goods_rate WHERE goods_1 = '".intval($gid)."'");
        if(empty($aLinked)){
            if(!empty($aData)){
                foreach($aData as $rows){
                    $aInsert = $rows;
                    $aInsert['goods_1'] = $gid;
                    $aInsert['rate'] = 100;
                    $rs = $object->db->exec('SELECT * FROM sdb_goods_rate WHERE 0=1');
                    $sql = $object->db->getInsertSQL($rs, $aInsert);
                    if($sql)$object->db->exec($sql);
                }
            }
        }else{
            if(empty($aData)){
                foreach($aLinked as $rows){
                    if($rows['goods_1'] == $gid){
                        $object->db->exec('DELETE FROM sdb_goods_rate WHERE goods_1 = '.$gid.' AND goods_2 = '.$rows['goods_2']);
                        if($rows['manual'] == 'both'){
                            $aInsert['goods_1'] = $rows['goods_2'];
                            $aInsert['goods_2'] = $gid;
                            $aInsert['manual'] = 'left';
                            $aInsert['rate'] = 100;
                            $rs = $object->db->exec('SELECT * FROM sdb_goods_rate WHERE 0=1');
                            $sql = $object->db->getInsertSQL($rs, $aInsert);
                            if($sql) $object->db->exec($sql);
                        }
                    }else{
                        if($rows['manual'] == 'both'){
                            $rs = $object->db->exec('SELECT * FROM sdb_goods_rate WHERE goods_1 = '.$rows['goods_1'].' AND goods_2 = '.$rows['goods_2']);
                            $sql = $object->db->getUpdateSQL($rs, array('manual' => 'left'));
                            if($sql) $object->db->exec($sql);
                        }
                    }
                }
            }else{
                $aResult = array();
                foreach($aLinked as $rows){
                    $deleteMark = 1;
                    foreach($aData as $k => $news){
                        if($rows['goods_1'] == $gid){
                            if($rows['goods_2'] == $news['goods_2']){
                                if($rows['manual'] != $news['manual']){
                                    $rows['manual'] = $news['manual'];
                                    $aResult[] = $rows;
                                }else{
                                    $deleteMark = 0;
                                }
                                unset($aData[$k]);
                            }
                        }else{
                            if($rows['goods_1'] == $news['goods_2']){
                                if($rows['manual'] == 'both'){
                                    if($news['manual'] == 'left'){
                                        $rows['manual'] = 'left';
                                        $rows['goods_1'] = $gid;
                                        $rows['goods_2'] = $news['goods_2'];
                                        $aResult[] = $rows;
                                    }else{
                                        $deleteMark = 0;
                                    }
                                }else{
                                    $rows['manual'] = $news['manual'];
                                    $rows['goods_1'] = $gid;
                                    $rows['goods_2'] = $news['goods_2'];
                                    $aResult[] = $rows;
                                }
                                unset($aData[$k]);
                            }
                        }
                    }
                    if($deleteMark){
                        $object->db->exec('DELETE FROM sdb_goods_rate WHERE goods_1 = '.$rows['goods_1'].' AND goods_2 = '.$rows['goods_2']);
                    }
                }
                $aResult = array_merge($aData, $aResult);
                if(count($aResult)){
                    foreach($aResult as $rows){
                        $rs = $object->db->exec('SELECT * FROM sdb_goods_rate WHERE 0=1');
                        $sql = $object->db->getInsertSQL($rs, $rows);
                        if($sql) $object->db->exec($sql);
                    }
                }
            }
        }
}
?>