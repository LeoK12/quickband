<?php
/**
 * mdl_gdimage
 *
 * @package
 * @version $Id: mdl.gdimage.php 1867 2008-04-23 04:00:24Z flaboy $
 * @copyright 2003-2007 ShopEx
 * @author Alex <alex@zovatech.com>
 * @license Commercial
 */

class mdl_gdimage{

    var $src_image_name = "";                          //输入图片的文件名(必须包含路径名)
    var $jpeg_quality = 90;             //jpeg图片质量
    var $save_file = '';                //输出文件名，如未定义则直接在浏览器中输出
    var $wm_image_name = "";            //水印图片的文件名(必须包含路径名)
    var $gd_loaded = false;             //GD是否加载
    var $wm_image_pos = 1;             //水印图片放置的位置
    // 0 = middle
    // 1 = top left
    // 2 = top right
    // 3 = bottom right
    // 4 = bottom left
    // 5 = top middle
    // 6 = middle right
    // 7 = bottom middle
    // 8 = middle left
    var $wm_image_transition = 80;            //水印图片与原图片的融合度 (1=100)
    var $emboss = false;
    var $wm_text = "";                        //水印文字(支持中英文以及带有\r\n的跨行文字)
    var $wm_text_size = 20;                   //水印文字大小
    var $wm_text_angle = 4;                   //水印文字角度,这个值尽量不要更改
    var $wm_text_font = "";                   //水印文字的字体
    var $wm_text_color = "#FF0000";           //水印字体的颜色值
    var $gif_enable;                          //是否支持写入GIF
    var $wm_angle;

    /*******************
    *  构造函数
    *  包括是否支持输出GIF的检测
    **********************/
    function mdl_gdimage()
    {
        if(function_exists("imagegif")) $this->gif_enable = true;
        else $this->gif_enable = false;
        if(function_exists("imagecreate")) $this->gd_loaded = true;
    }

    function fileCheck(){
        $font_dir = PUBLIC_DIR.'/fonts/';
        if(!is_file($this->wm_image_name)) $this->wm_image_name='';
        if(!is_file($font_dir.$this->wm_text_font)) $this->wm_text='';
        else $this->wm_text_font = $font_dir.$this->wm_text_font;
    }


    function makeThumbWatermark($width=128,$height=128)
    {
        $this->fileCheck();
        $image_info = $this->getInfo($this->src_image_name);
        if (!$image_info) return false;

        $src_image_type = $image_info["type"];
        $img =  $this->createImage($src_image_type,$this->src_image_name);
        if (!$img) return false;

        $width = ($width==0)?$image_info["width"]:$width;
        $height = ($height==0)?$image_info["height"]:$height;

        $width = ($width > $image_info["width"]) ? $image_info["width"] : $width;
        $height = ($height > $image_info["height"]) ? $image_info["height"] : $height;
        $srcW = $image_info["width"];
        $srcH = $image_info["height"];
        if ($srcH * $width > $srcW * $height)
            $width = round($srcW * $height / $srcH);
        else
            $height = round($srcH * $width / $srcW);
        //*

        $src_image = @imagecreatetruecolor($width, $height);
        $white = @imagecolorallocate($src_image, 0xFF, 0xFF, 0xFF);
        @imagecolortransparent($src_image,$white);
        @imagefilltoborder( $src_image, 0, 0, $white , $white );
        if ($src_image) //GD2.0.1
        {
            ImageCopyResampled($src_image, $img, 0, 0, 0, 0, $width, $height, $image_info["width"], $image_info["height"]);
        }
        else
        {
            $src_image = imagecreate($width, $height);
            ImageCopyResized($src_image, $img, 0, 0, 0, 0, $width, $height, $image_info["width"], $image_info["height"]);
        }

        $src_image_w=ImageSX($src_image);
        $src_image_h=ImageSY($src_image);


        if ($this->wm_image_name){
               $wm_image_info = $this->getInfo($this->wm_image_name);
               if (!$wm_image_info) return false;
               $wm_image_type = $wm_image_info["type"];
               $wm_image = $this->createImage($wm_image_type,$this->wm_image_name);
               $wm_image_w=ImageSX($wm_image);
               $wm_image_h=ImageSY($wm_image);
               $temp_wm_image = $this->getPos($src_image_w,$src_image_h,$this->wm_image_pos,$wm_image);
               if($this->emboss && function_exists("imagefilter"))
                {
                    imagefilter ($wm_image, IMG_FILTER_EMBOSS);
                    $bgcolor = imagecolorclosest($wm_image, 0x7F, 0x7F, 0x7F);
                    imagecolortransparent($wm_image,$bgcolor);
                }
                if(function_exists("ImageAlphaBlending")&&IMAGETYPE_PNG==$wm_image_info['type']){
                    ImageAlphaBlending($src_image, true);
                }
               $wm_image_x = $temp_wm_image["dest_x"];
               $wm_image_y = $temp_wm_image["dest_y"];
               if(IMAGETYPE_PNG==$wm_image_info['type']){
                   imageCopy($src_image, $wm_image,$wm_image_x,$wm_image_y,0,0,$wm_image_w,$wm_image_h);
               }else{
                   imageCopyMerge($src_image, $wm_image,$wm_image_x,$wm_image_y,0,0,$wm_image_w,$wm_image_h,$this->wm_image_transition);
               }
        }

        if ($this->wm_text){
               $this->wm_text = $this->wm_text;
               $temp_wm_text = $this->getPos($src_image_w,$src_image_h,$this->wm_image_pos);
               $wm_text_x = $temp_wm_text["dest_x"];
               $wm_text_y = $temp_wm_text["dest_y"];
              if(preg_match("/([a-f0-9][a-f0-9])([a-f0-9][a-f0-9])([a-f0-9][a-f0-9])/i", $this->wm_text_color, $color))
              {
                 $red = hexdec($color[1]);
                 $green = hexdec($color[2]);
                 $blue = hexdec($color[3]);
                 $wm_text_color = imagecolorallocate($src_image, $red,$green,$blue);
              }else{
                 $wm_text_color = imagecolorallocate($src_image, 255,255,255);
              }
               imagettftext($src_image, $this->wm_text_size, $this->wm_angle, $wm_text_x, $wm_text_y, $wm_text_color,$this->wm_text_font,  $this->wm_text);
        }

        if ($this->save_file)
        {
          switch ($src_image_type){
           case 1:
               if($this->gif_enable)
                $src_img=ImageGIF($src_image, $this->save_file);
               else
                $src_img=ImagePNG($src_image, $this->save_file);
                break;
           case 2:$src_img=ImageJPEG($src_image, $this->save_file, $this->jpeg_quality); break;
           case 3:$src_img=ImagePNG($src_image, $this->save_file); break;
           default:$src_img=ImageJPEG($src_image, $this->save_file, $this->jpeg_quality); break;
          }
        }
        else
        {
          switch ($src_image_type){
           case 1:
              if($this->gif_enable)
              {
                    header("Content-type: image/gif");
                    $src_img=ImageGIF($src_image);
              }
              else
              {
                    header("Content-type: image/png");
                    $src_img=ImagePNG($src_image);
              }
              break;
           case 2:
                header("Content-type: image/jpeg");
               $src_img=ImageJPEG($src_image, "", $this->jpeg_quality);break;
           case 3:
                header("Content-type: image/png");
               $src_img=ImagePNG($src_image);break;
           case 6:
                header("Content-type: image/bmp");
               $src_img=imagebmp($src_image);break;
           default:
                header("Content-type: image/jpeg");
               $src_img=ImageJPEG($src_image, "", $this->jpeg_quality);break;
          }
        }
        imagedestroy($src_image);
        imagedestroy($img);
        return true;

    }

    /************************************************
    /*
    createImage     根据文件名和类型创建图片
    内部函数

    $type:                图片的类型，包括gif,jpg,png
    $img_name:  图片文件名，包括路径名，例如 " ./mouse.jpg"
    ********************************************************/
    function createImage($type,$img_name){
              switch ($type){
                      case 1:
                            if (function_exists('imagecreatefromgif'))
                                   $tmp_img=@ImageCreateFromGIF($img_name);
                            else
                                return false;
                            break;
                      case 2:
                            $tmp_img=ImageCreateFromJPEG($img_name);
                            break;
                      case 3:
                            $tmp_img=ImageCreateFromPNG($img_name);
                            break;
                      case 6:
                            $tmp_img=imagecreatefrombmp($img_name);
                            break;
                      default:
                            $tmp_img=ImageCreateFromString($img_name);
                            break;
              }
              return $tmp_img;
    }

    /****************************************************************************************
    getPos               根据源图像的长、宽，位置代码，水印图片id来生成把水印放置到源图像中的位置
    内部函数

    $sourcefile_width:        源图像的宽
    $sourcefile_height: 原图像的高
    $pos:               位置代码
    // 0 = middle
    // 1 = top left
    // 2 = top right
    // 3 = bottom right
    // 4 = bottom left
    // 5 = top middle
    // 6 = middle right
    // 7 = bottom middle
    // 8 = middle left
    $wm_image:           水印图片ID
    *************************************************************************************/
    function getPos($sourcefile_width,$sourcefile_height,$pos,$wm_image=""){
             if  ($wm_image){
                  $insertfile_width = ImageSx($wm_image);
                  $insertfile_height = ImageSy($wm_image);
             }else {
                  $lineCount = explode("\n",$this->wm_text);
                  $fontSize = imagettfbbox($this->wm_text_size,$this->wm_text_angle,$this->wm_text_font,$this->wm_text);
                  $insertfile_width = $fontSize[2] - $fontSize[0];
                  $insertfile_height = count($lineCount)*($fontSize[1] - $fontSize[7]);
             }

             switch ($pos){
                    case 0:
                       $dest_x = ( $sourcefile_width / 2 ) - ( $insertfile_width / 2 );
                       $dest_y = ( $sourcefile_height / 2 ) - ( $insertfile_height / 2 );
                       break;

                    case 1:
                       $dest_x = 0;
                       if ($this->wm_text){
                           $dest_y = $insertfile_height;
                       }else{
                           $dest_y = 0;
                       }
                       break;

                    case 2:
                      $dest_x = $sourcefile_width - $insertfile_width;
                      if ($this->wm_text){
                         $dest_y = $insertfile_height;
                      }else{
                          $dest_y = 0;
                      }
                      break;

                    case 3:
                      $dest_x = $sourcefile_width - $insertfile_width;
                      $dest_y = $sourcefile_height - $insertfile_height;
                      break;

                    case 4:
                      $dest_x = 0;
                      $dest_y = $sourcefile_height - $insertfile_height;
                      break;

                    case 5:
                     $dest_x = ( ( $sourcefile_width - $insertfile_width ) / 2 );
                     if ($this->wm_text){
                        $dest_y = $insertfile_height;
                     }else{
                        $dest_y = 0;
                     }
                     break;

                    case 6:
                     $dest_x = $sourcefile_width - $insertfile_width;
                     $dest_y = ( $sourcefile_height / 2 ) - ( $insertfile_height / 2 );
                     break;

                    case 7:
                     $dest_x = ( ( $sourcefile_width - $insertfile_width ) / 2 );
                     $dest_y = $sourcefile_height - $insertfile_height;
                     break;

                    case 8:
                     $dest_x = 0;
                     $dest_y = ( $sourcefile_height / 2 ) - ( $insertfile_height / 2 );
                     break;

                    default:
                      $dest_x = $sourcefile_width - $insertfile_width;
                      $dest_y = $sourcefile_height - $insertfile_height;
                      break;
             }
            return array("dest_x"=>$dest_x,"dest_y"=>$dest_y);
    }


    /*****************************
    * 函数: getInfo($file)
    * 返回图片信息数组
    * 参数: $file 文件路径
    ********************************/
    function getInfo($file)
    {
        if(!file_exists($file)) return false;
        $data = getimagesize($file);
        $imageInfo["width"] = $data[0];
        $imageInfo["height"]= $data[1];
        $imageInfo["type"] = $data[2];
        $imageInfo["name"] = basename($file);
        return $imageInfo;
    }


}