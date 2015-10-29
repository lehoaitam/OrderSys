<?php
// +----------------------------------------------------------------------+
// | PHP version 5.1.2                                                    |
// +----------------------------------------------------------------------+
// | Copyright (c) Kobe Digital Labo Inc.                                 |
// +----------------------------------------------------------------------+
// | Project Name : KSMSKISS                                              |
// | File_Name    : Gif.php                                               |
// |                                                                      |
// |                                                                      |
// |                                                                      |
// +----------------------------------------------------------------------+
// | Authors: nishi 2006/06/19                                            |
// +----------------------------------------------------------------------+
require_once(dirname(__FILE__) . "/ImageCommon.php");

/**
 * @author    nishi
 * @package   Common
 * @subpackage ImageManipulate
 * @since     2006/06/19
 */
class Gif extends ImageCommon {
    protected function setResource(&$name){
        $this->imageResource = imagecreatefromgif($name);
    }
    
    protected function saveImage($resource, $name){
        imagegif($resource, $name . ".gif");
        return basename($name . ".gif");
    }
}

?>