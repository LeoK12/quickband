var dataSyncLock = false;
      var dataSyncStep = 1;
      var dataSyncHalt = false;
      var doDataSync=function(unSyncGoods){
        unSyncGoods = unSyncGoods?true:false;
        dataSyncLock = true;
         new Request({
            data:'step='+dataSyncStep,
            onSuccess:function(nl){
                var keepDownGoods = true;
                dataSyncHalt = false;
               	try{nl = $H(JSON.decode(nl));}catch(e){return;}
                if(nl.get('status')=='continue'){
                    dataSyncStep++;
                    if( $('supplier-list') ){
                        if(nl.has('joblist')){
                            nl.get('joblist').each(function(jobspid){
                                    var loadimg = new Element('img',{'src':'images/sync_loading.gif','title':'正在同步','class':'data-sync-loading'});
                                    $E('#supplier-list tr[supplierid='+jobspid+'] .supplier-sync-status').empty().adopt(loadimg);
                            });
                        }
                        /*
                        else{
                            $$('#supplier-list .data-sync-loading').each(function(loading){
                                var succImg = new Element('img',{'src':'images/success.gif','title':'同步完成'});
                                loading.getParent('td').empty().adopt(succImg);
                            });
                        }*/
                    }
                    doDataSync();
                }else{
                    if(nl.get('status')=='lock'){
                        alert('更新列表下载锁定中，请30秒后刷新页面重试！');
                        keepDownGoods = false;
                    }
                    dataSyncStep=1;
                    dataSyncLock =false;
                    /*
                    if( $('supplier-list') ){
                        $$('#supplier-list .data-sync-loading').each(function(loading){
                            var succImg = new Element('img',{'src':'images/success.gif','title':'同步完成'});
                            loading.getParent('td').empty().adopt(succImg);
                            /*loading.set('styles',{'display':'none'}).getParent('td').getElement('.supplier-data-dis-success').set('styles',{'display':''});
                        });
                    }*/
                    this.cancel(); 
                }
                if( !unSyncGoods && !goodsSyncLock && keepDownGoods)
                    doGoodsSync();
            },
            onFailure:function(){
                if(this.xhr.status == 501 && this.getHeader('notify_msg')){
                	    alert(decodeURIComponent(this.getHeader('notify_msg')));
                        dataSyncHalt = true;
                }else{
                    dataSyncStep ++;
                    setTimeout("doDataSync()",5000);
                }
            }
            }).post('index.php?ctl=distribution/supplier&act=doDataSync');
      }
      
      var autoSyncStep = new Array();
      var autoSyncHalt = false;
      var doAutoSync   = function(supplier_id) {
              if(!dataSyncLock && !goodsSyncLock && !imagesSyncLock && !supplierApiListJobLock ) {
                  _doAutoSync(supplier_id,0);
              } else {
                  setTimeout("doAutoSync("+supplier_id+")",5000);
              }
      }
      
      var _doAutoSync   = function(supplier_id,step) {
         autoSyncStep[supplier_id] = step;
         new Request({
                data:'supplier_id='+supplier_id+'&step='+autoSyncStep[supplier_id],
                onSuccess:function(nl){
                    try{nl = $H(JSON.decode(nl));}catch(e){return;}
                    /* 如果是新增商品或更新图片处理将抛出同步图片的处理 */
                    if(nl.op_id == 6 || nl.op_id == 8) {
                        doImagesSync(nl.command_id);
                    }
                    
                    var td = $E('#supplier-list tr[supplierid='+supplier_id+'] .supplier-sync-status');
                    if(td != null) {
                         img = $E('img',td)
                         if(img.id != 'sync_img_'+supplier_id) {
                              var syncImg = new Element('img',{'src':'images/sync_loading.gif','title':'正在同步','id':'sync_img_'+supplier_id});
                              td.empty().adopt(syncImg);
                         }
                    } 
                    
                    if(nl.status == 'done') {
                        autoSyncStep[supplier_id] = 0;
                        
                        var td = $E('#supplier-list tr[supplierid='+supplier_id+'] .supplier-sync-status');
                        if(td != null) {
                            var succImg = new Element('img',{'src':'images/success.gif','title':'同步完成'});
                            td.empty().adopt(succImg);
                        }
                        
                        this.cancel();
                    } else {
                        _doAutoSync(supplier_id,nl.count);
                    }
                },
                onFailure:function(){
                     if(this.xhr.status == 501 && this.getHeader('notify_msg')){
                	        alert(decodeURIComponent(this.getHeader('notify_msg')));
                     }else{
                        setTimeout("_doAutoSync("+supplier_id+","+autoSyncStep[supplier_id]+")",5000);
                     }
                }
            }).post('index.php?ctl=distribution/supplier&act=doAutoSync');
      }
      
      var autoSyncJobHalt = false;
      var doAutoSyncJob = function(){
          new Request({
              data:'',
              onSuccess:function(nl){
        	     autoSyncJobHalt = false;
                 try{nl = JSON.decode(nl);}catch(e){return;}
                 nl.each(function(nle){
                    _doAutoSync(nle.supplier_id,0);
                 });
              },
              onFailure:function(){
            	  if(this.xhr.status == 501 && this.getHeader('notify_msg')){
            		      alert(decodeURIComponent(this.getHeader('notify_msg')));
                          autoSyncJobHalt = true;
                  }else{
                	      setTimeout("doAutoSyncJob()",5000);
                  }
              }
          }).post('index.php?ctl=distribution/supplier&act=autoSyncJob');
      };
      
      var costSyncStep = new Array();
      var doCostSync = function(supplier_id,step) {
          costSyncStep[supplier_id] = step;
          new Request({
                data:'supplier_id='+supplier_id+'&step='+costSyncStep[supplier_id],
                onSuccess:function(nl){
                    try{nl = $H(JSON.decode(nl));}catch(e){return;}
                    if(nl.status == 'done') {
                        costSyncStep[supplier_id] = 0;
                        
                        var td = $E('#supplier-list tr[supplierid='+supplier_id+'] .supplier-cost-sync-status');
                        if(td != null) {
                            var succImg = new Element('img',{'src':'images/success.gif','title':'同步完成'});
                            td.empty().adopt(succImg);
                            if(!nl.count){
                                alert('此次更新无采购价变更');
                            }
                        }
                        
                        this.cancel();
                    } else {
                        doCostSync(supplier_id,nl.count);
                    }
                },
                onFailure:function(){
                     if(this.xhr.status == 501 && this.getHeader('notify_msg')){
                	        alert(decodeURIComponent(this.getHeader('notify_msg')));
                     }else{
                        setTimeout("doCostSync("+supplier_id+","+costSyncStep[supplier_id]+")",5000);
                     }
                }
            }).post('index.php?ctl=distribution/supplier&act=doCostSync');
      }
      
      var costSyncJobHalt = false;
      var doCostSyncJob = function(){
          new Request({
              data:'',
              onSuccess:function(nl){
        	     costSyncJobHalt = false;
                 try{nl = JSON.decode(nl);}catch(e){return;}
                 nl.each(function(nle){
                     doCostSync(nle.supplier_id,nle.num);
                 });
              },
              onFailure:function(){
            	  if(this.xhr.status == 501 && this.getHeader('notify_msg')){
            		      alert(decodeURIComponent(this.getHeader('notify_msg')));
                          costSyncJobHalt = true;
                  }else{
                	      setTimeout("doCostSyncJob()",5000);
                  }
              }
          }).post('index.php?ctl=distribution/supplier&act=costSyncJob');
      };
      
      var goodsSyncLock = false;
      var goodsSyncStep = 1;
      var goodsSyncHalt = false;
      var doGoodsSync=function(){
      goodsSyncLock =true;
        new Request({
            data:'step='+goodsSyncStep,
            onSuccess:function(nl){
                var keepDownImage = true;
                goodsSyncHalt = false;
                try{nl = $H(JSON.decode(nl));}catch(e){return;}
                if(nl.get('status')=='continue'){
                    goodsSyncStep++;
                    $('head_sync_download_tip_msg').set('styles',{'display':''});
                    $('downmsg').set('text',nl.get('msg'));
                    doGoodsSync();
                }else{
                    if(nl.get('status')=='lock'){
                        alert('商品下载锁定中，请30秒后刷新页面重试！');
                        keepDownImage = false;
                    }
                    goodsSyncStep=1;
                    goodsSyncLock = false;
                    this.cancel(); 
                }
                if( nl.has('cat_id') && nl.get('cat_id') ){
                    new Dialog('index.php?ctl=distribution/supplier&act=syncComplete',
                        {title:'下载完成',
                        width:300,
                        height:200,
                        ajaxoptions:{data:'cat_id='+nl.get('cat_id'), method:'post',ajaksable:false}
                    });
                    $('head_sync_download_tip_msg').set('styles',{'display':'none'});
                }
                if( !imagesSyncLock && keepDownImage)
                    doImagesSync(null);
            },
            onFailure:function(){
            	if(this.xhr.status == 501 && this.getHeader('notify_msg')){
            		    alert(decodeURIComponent(this.getHeader('notify_msg')));
                        goodsSyncHalt = true;
                }else{
                    goodsSyncStep ++;
                    setTimeout("doGoodsSync();",5000);
                }
            }
        }).post('index.php?ctl=distribution/supplier&act=doGoodsSync');
      };
      
      var imagesSyncLock =false;
      var imagesSyncStep = 1;
      var imagesSyncHalt = false;
      var doImagesSync=function(commandid){
        imagesSyncLock = true;
        if(commandid){
            var p_data = 'step='+imagesSyncStep+'&command_id='+commandid;
        }else{
            var p_data = 'step='+imagesSyncStep;
        }
        new Request({
            data:p_data,
            onSuccess:function(nl){
        	    imagesSyncHalt = false;
                try{nl = $H(JSON.decode(nl));}catch(e){return;}
                if(nl.get('status')=='continue'){
                    imagesSyncStep++;
                    doImagesSync(commandid);
                }else{
                    imagesSyncStep=1;
                    imagesSyncLock = false;
                    this.cancel(); 
                }
            },
            onFailure:function(){
            	if(this.xhr.status == 501 && this.getHeader('notify_msg')){
            		    alert(decodeURIComponent(this.getHeader('notify_msg')));
                        imagesSyncHalt = true;
                }else{
                       imagesSyncStep ++;
                	   setTimeout("doImagesSync(null);",5000);
                }
            }
            }).post('index.php?ctl=distribution/supplier&act=doImagesSync');
      };

      var supplierApiListJobLock = false;
      var supplierApiListJobHalt = false;
      var doSupplierApiListJob=function(supplierId,api_name,api_action){
    	supplierApiListJobLock = true;
        new Request({
            data:'',
            onSuccess:function(nl){
        	    supplierApiListJobHalt = false;
                try{nl = $H(JSON.decode(nl));}catch(e){return;}
                if(nl.status=='continue'){
                    doSupplierApiListJob(supplierId,api_name,api_action);
                }else{
                    this.cancel();
                    supplierApiListJobLock = false;
                }
            },
            onFailure:function(){
            	if(this.xhr.status == 501 && this.getHeader('notify_msg')){
                        alert(decodeURIComponent(this.getHeader('notify_msg')));
                        supplierApiListJobHalt = true;
                }else{
                	   setTimeout("doSupplierApiListJob("+supplierId+",'"+api_name+"','"+api_action+"')",5000);
                }
            }
        }).post('index.php?ctl=distribution/supplier&act=doSupplierApiListJob&p[0]='+supplierId+'&p[1]='+api_name+'&p[2]='+api_action);
      };

      var supplierApiListHalt = false;
      var doSupplierApiList=function(){
        new Request({
            data:'',
            onSuccess:function(nl){
        	    supplierApiListHalt = false;
                try{nl = JSON.decode(nl);}catch(e){return;}
                nl.each(function(nle){
                    doSupplierApiListJob(nle.supplier_id,'','');
                });
            },
            onFailure:function(){
            	if(this.xhr.status == 501 && this.getHeader('notify_msg')){
            		    alert(decodeURIComponent(this.getHeader('notify_msg')));
                        supplierApiListHalt = true;
                }else{
                	   setTimeout("doSupplierApiList()",5000);
                }
            }
        }).post('index.php?ctl=distribution/supplier&act=suppilerApiList');
      };

      var notice_first = true;
      var first = true;
      function api_notice(){
          new Request({onSuccess:function(re){
              if(re){
                  $('head_sync_download_tip_msg').set('styles',{'display':''});
                  $('downmsg').set('text',re);
                  if(notice_first){
                	  alert(re); 
                	  notice_first = false;
                  }
              }else{
                  if(!first){
                      if(supplierApiListHalt){
                    	  doSupplierApiList();
                      }
                      if(dataSyncHalt){
                    	  doDataSync();
                      }
                      if(goodsSyncHalt){
                    	  doGoodsSync();
                      }
                      if(imagesSyncHalt){
                    	  doImagesSync(null);
                      }
                      if(autoSyncJobHalt){
                    	  doAutoSyncJob();
                      }
                      if(costSyncJobHalt){
                    	  doCostSyncJob();
                      }

                      if(!goodsSyncLock){
                    	  $('head_sync_download_tip_msg').set('styles',{'display':'none'});
                          $('downmsg').set('text','');
                      }
                  }else{
                      doSupplierApiList();
                      doDataSync();
                      doGoodsSync();
                      doImagesSync(null);
                      doAutoSyncJob();
                      doCostSyncJob();
                      first = false;
                  }
              }
          }}).get("index.php?ctl=default&act=check_api_maintenance",'');
          arguments.callee.delay(600000);/*10分钟重新查询api是否维护.*/
      }

      addEvent('load',function(){
      	api_notice();
      });