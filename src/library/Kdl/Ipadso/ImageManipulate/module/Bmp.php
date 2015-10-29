<?php
// +----------------------------------------------------------------------+
// | PHP version 5.1.2                                                    |
// +----------------------------------------------------------------------+
// | Copyright (c) Kobe Digital Labo Inc.                                 |
// +----------------------------------------------------------------------+
// | Project Name : KSMSKISS                                              |
// | File_Name    : Bmp.php                                               |
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
class Bmp extends ImageCommon {
    protected function setResource(&$name){
        $this->imageResource = imagecreatefromgd2($name);
	}
    protected function saveImage($resource, $name){
    	imagebmp($resource, $name . ".bmp");
        return basename($name . ".bmp");
    }
}

?>