<?php
// +----------------------------------------------------------------------+
// | PHP version 5.1.2                                                    |
// +----------------------------------------------------------------------+
// | Copyright (c) Kobe Digital Labo Inc.                                 |
// +----------------------------------------------------------------------+
// | Project Name : KSMSKISS                                              |
// | File_Name    : Png.php                                               |
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
class Png extends ImageCommon {
    protected function setResource(&$name){
        $this->imageResource = imagecreatefrompng($name);
    }
    
    protected function saveImage($resource, $name){
        imagepng($resource, $name . ".png");
        return basename($name . ".png");
    }
}

?>