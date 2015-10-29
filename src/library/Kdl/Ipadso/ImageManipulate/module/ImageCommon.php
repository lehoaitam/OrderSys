<?php
/**
 *
 * @abstract
 * @author nishi
 * @package Common
 * @subpackage ImageManipulate
 * @modified Nguyen Huu Tam
 * @since 2012/08/17
 */

abstract class ImageCommon 
{
    /**
     * イメージリソース
     *
     * @var  resource
     * @access protected
     * @author nishi
     * @since   2006/06/19
     */
    protected $imageResource;

    /**
     * @param string $name ファイル名
     * @return void なし
     * @access public
     * @author nishi
     * @modified Nguyen Huu Tam
     * @since 2012/08/17
     */
    public function __construct(&$name)
    {
        echo "$name<br>";
        $this->setResource($name);
    }
    
    /**
     * Resize image
     * 
     * @param string $destination
     * @param int $tn_w
     * @param int $tn_h
     * @param int $quality
     * @return boolean 
     * @author Nguyen Huu Tam
     * @since 2012/08/17
     */
    function resize($destination, $tn_w, $tn_h, $quality = 100)
    {
        #Figure out the dimensions of the image and the dimensions of the desired thumbnail
        $src_w = imagesx($this->imageResource);
        $src_h = imagesy($this->imageResource);

        #Do some math to figure out which way we'll need to crop the image
        #to get it proportional to the new size, then crop or adjust as needed

        $x_ratio = $tn_w / $src_w;
        $y_ratio = $tn_h / $src_h;
        
        $new_w = $tn_w;
        $new_h = ceil($x_ratio * $src_h);
        
        // Portrait
        if ($src_w < $src_h) {
            $tn_h = $new_h;
        }         
        
        $newpic = imagecreatetruecolor(round($new_w), round($new_h));
        imagecopyresampled(
            $newpic, 
            $this->imageResource, 
            0, 
            0, 
            0, 
            0, 
            $new_w, 
            $new_h, 
            $src_w, 
            $src_h
        );
        
        $final = imagecreatetruecolor($tn_w, $tn_h);
        $backgroundColor = imagecolorallocate($final, 255, 255, 255);
        imagefill($final, 0, 0, $backgroundColor);
        imagecopy(
            $final, 
            $newpic, 
            (($tn_w - $new_w)/ 2), 
            (($tn_h - $new_h) / 2), 
            0, 
            0, 
            $new_w, 
            $new_h
        );
        
        $result = false;
        if (imagejpeg($final, $destination, $quality)) {            
            $result = true;
        }
        
        imagedestroy($this->imageResource);
        imagedestroy($final);
        imagedestroy($newpic);        
        return $result;
    }
}
?>