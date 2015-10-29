<?php
// +----------------------------------------------------------------------+
// | PHP version 5.1.2                                                    |
// +----------------------------------------------------------------------+
// | Copyright (c) Kobe Digital Labo Inc.                                 |
// +----------------------------------------------------------------------+
// | Project Name : KSMSKISS                                              |
// | File_Name    : ImageManipulateClass.php                              |
// |                                                                      |
// |                                                                      |
// |                                                                      |
// +----------------------------------------------------------------------+
// | Authors: nishi 2006/06/19                                            |
// +----------------------------------------------------------------------+

/**
 * @author    nishi
 * @package   Common
 * @subpackage ImageManipulate
 * @since     2006/06/19
 */
class ImageManipulateFactory{

    public static function factory($name, $subdir=NULL){
    	$imgObj = new ImageManipulate();
        return $imgObj->factory($name, $subdir);
    }
}

/**
 * 画像操作Factoryクラス
 *
 * @author    nishi
 * @package   Common
 * @subpackage ImageManipulate
 * @since     2006/06/19
 */
class ImageManipulate {

    /**
     * Factoryメソッド
     */
    public function factory($name, $subdir=NULL){
    	$stat = "";
        $type = exif_imagetype($name);

        switch($type){
        	case 1:
                $stat = "Gif";
                break;
            case 2:
                $stat = "Jpeg";
                break;
            case 3:
                $stat = "Png";
                break;
            case 4:
                $stat = "Swf";
                break;
            case 5:
                $stat = "Psd";
                break;
            case 6:
                $stat = "Bmp";
                break;
            case 7:
                $stat = "Tiff_II";
                break;
            case 8:
                $stat = "Tiff_MM";
                break;
            case 9:
                $stat = "Jpc";
                break;
            case 10:
                $stat = "Jp2";
                break;
            case 11:
                $stat = "Jpx";
                break;
            case 12:
                $stat = "Jb2";
                break;
            case 13:
                $stat = "Swc";
                break;
            case 14:
                $stat = "Iff";
                break;
            case 15:
                $stat = "Wbmp";
                break;
            case 16:
                $stat = "Xbm";
                break;
            default:
                $stat = null;
                break;
        }

        $module = dirname(__FILE__) . "/module/" . $stat . ".php";
        if(file_exists($module)){
        	require_once($module);
            return new $stat($name, $subdir);
        }

        return false;
    }

}
?>