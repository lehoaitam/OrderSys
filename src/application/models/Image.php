<?php
/**
 * Class Image
 *
 * @author Nguyen Thi Tho
 * @copyright Kobe Digital Labo, Inc
 * @since 2012/07/20
 */

class Application_Model_Image
{
    protected $_id;    
    protected $_data    = null;
    protected $_path    = null;
    protected $_handle  = null;
	
	const UPLOAD_PRODUCT_IMAGE = 1;
	const UPLOAD_CATEGORY_IMAGE = 2;

    public function __construct()
    {
        try {
            $session = Globals::getSession(); 
            $this->_path  = $session->company_link . '/image/';
            $this->_handle = @opendir($this->_path);
            
            $this->_config = Globals::getApplicationConfig('image');
            if(isset($this->_config->allowTypes))
                $this->_pattern = '#.+\.('. str_replace(',', '|', $this->_config->allowTypes) .')$#i';
            else
                $this->_pattern = '#.+\.('. str_replace(',', '|', 'jpg,gif,png') .')$#i';
            
            $this->msgConfig = Zend_Registry::get('MsgConfig');
            
        }  catch (Exception $e) {
            Globals::logException($e);
        }
    }
    
    public function getData()
    {
        if ($this->_handle) {
            $i = 0;
            while (false !== ($this->_file = readdir($this->_handle))) {
                if (preg_match($this->_pattern, $this->_file) == 1) {
                    $this->_data[$i]['id']  = $this->_file;
                    $this->_data[$i]['name'] = $this->_file;
                    $i++;
                }
            }
            closedir($this->_handle);
        }
        // Sort data
        if (is_array($this->_data)) {
            asort($this->_data);
        }
        
        return $this->_data;
    
    }

    public function getArrayImage () {
        return scandir($this->_path);
    }

    //get data view on the list
    public function getDataViewList($postData, $page, $limit)
    {
        $csvIndex    = new Application_Model_Index();
        $csvCategory = new Application_Model_Category();

        //Create product list
        $dataIndex  = $csvIndex->fetchAll(null, true);
        $arrIndex     = array();
        foreach ($dataIndex as $value) {
            if (array_key_exists($value->getImage(),$arrIndex)) {
                $arrIndex[$value->getImage()] =  $arrIndex[$value->getImage()] .'<br>'. $value->getMenuCode() .' - '. $value->getItemName();
            } else {
                $arrIndex[$value->getImage()] = $value->getMenuCode() .' - '. $value->getItemName();
            }
            
        }
        //Create category list
        $dataCategory      = $csvCategory->fetchAll();
        $imageCategory     = array();
        foreach ($dataCategory as $value) {
            if (array_key_exists($value->getImage(),$imageCategory)) {
                $imageCategory[$value->getImage()] =  $imageCategory[$value->getImage()] .'<br>'. $value->getCode() .' - '. $value->getName();
            } else {
                $imageCategory[$value->getImage()] = $value->getCode() .' - '. $value->getName();
            }

        }
        //sort
        if (array_keys($postData, 'image')
            && ($postData['order'] == 'asc')
        ) {
            $this->_path = @scandir($this->_path, 1);
           
        } else {
           $this->_path = @scandir($this->_path);
        }

        $dataJson = array();
        if ($this->_handle) {
            foreach ($this->_path as $i => $file) {
                if (preg_match($this->_pattern, $file) == 1) {
                    $productList  = '';
                    $categoryList = '';
                    if (array_key_exists($file, $arrIndex)) {
                        $productList = $arrIndex[$file];
                    }
                    if (array_key_exists($file, $imageCategory)) {
                        $categoryList = $imageCategory[$file];
                    }
                    $dataJson[$i]= Array('no'=>($i+1)
                                        ,'image'=>$file
                                        ,'name'=>$file
                                        ,'product'=>$productList
                                        ,'category'=>$categoryList
                                        ,'thumb'=> self::getThumbnail($file)
                                    );
                }
            }
            closedir($this->_handle);
        }
        $count = count($dataJson);

        if( $count >0 ) {
            $total_pages = ceil($count/$limit);
        } else {
            $total_pages = 0;
        }
        if ($page > $total_pages) $page=$total_pages;
        $start = $limit*$page - $limit;
      
        $rows['total']= $count;
        $rows['rows'] = (array_slice($dataJson, $start, $limit));
        return $rows;
    }
    
    //Delete image
    public function deleteByKey($image)
    {
        if ($this->_handle) {
            $data = $this->getData();
            try {
                unlink($this->_path . $image);
            } catch (Exception $e) {
                Globals::logException($e);
            }                     
        }
    }

    //upload product image
    public function upload($filename, $sourse, $size)
    {
        $result = true;
        $session = Globals::getSession();
        if (preg_match($this->_pattern, $filename) != 1) {
            $result =  'E309_ImageInvalid';
        }
        elseif ($size > $this->_config->maxSize ) {
             $result = 'E309_ImageTooLarge';
        }
        else {
             $dest = $this->_path.$filename;
            if (file_exists($dest)) {
                unlink($dest);//delete if image exist
            }
            try {
                 copy($sourse, $dest);
                 Globals::log('Add image ('.$filename.')', null, $session->company_code.'.log');
                 $result = 1;
            } catch (Exception $e) {
                Globals::logException($e);
            }           
        }
        return $result;
       
    }

    //check the images that are selected to delete from the index.csv and category file
    public function checkForgeinKey($image)
    {
        $execute = 1;

        $csvIndex    = new Application_Model_Index();
        $csvCategory = new Application_Model_Category();
        $csvIndex->fetchAll();
        $csvCategory->fetchAll();

        $arrIndex    = $csvIndex->getDataHeader('image');
        $arrCategory = $csvCategory->getDataHeader('image');

        if(array_keys($arrIndex,$image) || array_keys($arrCategory,$image)) {
            $execute = 'E309_ImageForeign';
        }

        return $execute;
    }
    

    public function uploadImageMulti($uploadType, $destination, $files = null, $renameFiles = null)
    {
        $upload = new Zend_File_Transfer_Adapter_Http();
        
        $messages = array();
        if ($upload->isUploaded($files)) {
            // Set folder to save uploaded file
            if (!is_dir($destination)) {
                mkdir($destination, 0777);
            }
            $upload->setDestination($destination);
            $fileInfo = $upload->getFileInfo();

            // File size validate
            $sizeValidator = Application_Model_Image::setValidatorMessage(
                'Zend_Validate_File_Size',
                array('max' => $uploadType !== self::UPLOAD_CATEGORY_IMAGE ? $this->_config->productImg->max_size : $this->_config->categoryImg->max_size)
            );
			
            // Extension validate
            $allowImgType = explode(',', $this->_config->allowTypes);
            $extensionValidator = Application_Model_Image::setValidatorMessage(
                'Zend_Validate_File_Extension',
                $allowImgType
            );
            
            foreach ($fileInfo as $file => $info) {
                if ($info['error']) continue;
				if (!is_null($renameFiles)) {
					$upload->addFilter('Rename', array('target' => $destination . $renameFiles[$info['name']], 'overwrite' => true));
				}
                $upload->addValidator($extensionValidator, false)
                    ->addValidator($sizeValidator, false);
                
                if ($upload->isValid($file)) {
                    $upload->receive($file);
                } else {
                    foreach ($upload->getMessages() as $message) {
                        $messages[] = $message;
                    }
                }
            }
            return $messages;

        } else {
            throw new Exception($this->msgConfig->E401_Require_Image);
        }
    }
    
    
    /**
     *
     * @param string $validator
     * @param array $options
     * @return \validator 
     * @author Nguyen Huu Tam
     * @since 2012/11/08
     */
    static public function setValidatorMessage($validator, $options)
    {
        $msgConfig = Zend_Registry::get('MsgConfig');
        switch ($validator) {
            case 'Zend_Validate_File_Size':
                $message = array(
                    $validator::TOO_BIG    => $msgConfig->E401_FileSizeTooBig,
                    $validator::NOT_FOUND  => $msgConfig->E401_FileNotFound
                );
                break;

            case 'Zend_Validate_File_ImageSize':
                $message = array(
                    $validator::WIDTH_TOO_BIG   => $msgConfig->E401_FileImageSizeWidthTooBig,
                    $validator::WIDTH_TOO_SMALL => $msgConfig->E401_FileImageSizeWidthTooSmall,
                    $validator::HEIGHT_TOO_BIG  => $msgConfig->E401_FileImageSizeHeightTooBig,
                    $validator::NOT_DETECTED    => $msgConfig->E401_FileImageSizeNotDetected,
                    $validator::NOT_READABLE    => $msgConfig->E401_FileNotFound
                );
                break;
            
            case 'Zend_Validate_File_Extension':
                $message = array(
                    $validator::FALSE_EXTENSION => $msgConfig->E401_FileExtensionFalse,
                    $validator::NOT_FOUND       => $msgConfig->E401_FileNotFound
                );
                break;
            
            case 'Zend_Validate_File_Count':
                $message = array(
                    $validator::TOO_MANY => $msgConfig->E401_FileCountTooMany
                );
                break;
            
            default:
                 $message = array();
                break;
        }
        
        $validator = new $validator($options);
        $validator->setMessages($message);
        
        return $validator;
    }
    
    
    /**
     * Check maximum image files
     *
     * @param array $files
     * @return boolean 
     * @author Nguyen Huu Tam
     * @since 2012/11/08
     * 
     */
    public function checkMaxData($files)
    {
        /*$images = $this->getData();
        if (isset($files['name'])
            && ((count($images) + count($files['name'])) > $this->_config->productImg->max_total)
        ) {
            return false;
        }*/

        return true;
    }
    
    /**
     * resize image
     * 
     * @param string $imagePath
     * @param string $imageWidth
     * @param string $imageHeight
     * @return boolean
     * @author nqtrung
     * @since 2015/06/04
     */
    public static function resizeImage($image, $imageWidth = 50, $imageHeight = 50)
    {
        $jpegExtensions = array('jpg', 'jpeg', 'jpe');
        // Parse path for the extension
        $info = pathinfo($image);
        $imgExtension = strtolower($info['extension']);
        if (in_array($imgExtension, $jpegExtensions)) {
            $imgExtension = 'jpeg';
        }
        // Create dynamic function
        $callback01 = 'imagecreatefrom' . $imgExtension;
        $callback02 = 'image' . $imgExtension;
        
        // Load image and get image size
        $img = call_user_func($callback01, $image);
        if ($img !== false) {
            #Figure out the dimensions of the image and the dimensions of the desired thumbnail
            $src_w = imagesx($img);
            $src_h = imagesy($img);
            
            if ($src_w <= $imageWidth && $src_h <= $imageHeight) {
                return true;
            }

            $x_ratio = $imageWidth / $src_w;
            $y_ratio = $imageHeight / $src_h;
            
            $new_w = $imageWidth;
            $new_h = ceil($x_ratio * $src_h);
            
            // Portrait
            if ($src_w < $src_h) {
                $new_w = ceil($y_ratio * $src_w);
                $new_h = $imageHeight;
            }

            $newpic = imagecreatetruecolor(round($new_w), round($new_h));

            // Copy and resize old image into new image
            imagecopyresized($newpic, $img, 0, 0, 0, 0, $imageWidth, $imageHeight, $src_w, $src_h);

            // Save
            call_user_func_array($callback02, array($newpic, $image));

            imagedestroy($newpic);
            
            return true;
        }
        return false;
    }
    
    /**
     * Create thumbnail image
     * 
     * @param string $imageName
     * @param string $pathToImage
     * @param string $pathToThumb
     * @param int $thumbWidth
     * @param boolean $isCheck
     * @return boolean
     * @author Nguyen Huu Tam
     * @since 2013/02/20
     */
    public static function createThumbnail($imageName, $pathToImage, $pathToThumb, $thumbWidth = 50, $isCheck = false)
    {
        // If need to check exist file
        $imageThumb = $pathToThumb . $imageName;
        if ($isCheck === true) {
            if (file_exists($imageThumb)) {
                Globals::log("Thumbnail for {$imageName} already exists");
                return true;
            }
        }
        
        $jpegExtensions = array('jpg', 'jpeg', 'jpe');
        // Init image path
        $image = $pathToImage . $imageName;
        // Parse path for the extension
        $info = pathinfo($image);
        $imgExtension = strtolower($info['extension']);
        if (in_array($imgExtension, $jpegExtensions)) {
            $imgExtension = 'jpeg';
        }
        // Create dynamic function
        $callback01 = 'imagecreatefrom' . $imgExtension;
        $callback02 = 'image' . $imgExtension;
        
        // Load image and get image size
        $img = call_user_func($callback01, $image);
        if ($img !== false) {
            $width = imagesx($img);
            $height = imagesy($img);

            // Calculate thumbnail size
            $new_width = $thumbWidth;
            $new_height = floor($height * ($thumbWidth / $width));

            // Create a new temporary image
            $tmp_img = imagecreatetruecolor($new_width, $new_height);

            // Copy and resize old image into new image
            imagecopyresized($tmp_img, $img, 0, 0, 0, 0, $new_width, $new_height, $width, $height);

            // Save thumbnail into a file
            call_user_func_array($callback02, array($tmp_img, "{$pathToThumb}{$imageName}"));

            return true;
        } else {
            // Copy image file
            if (copy($image, $imageThumb)) {
                Globals::log('Failed to create thumbnail, just copy image to thumbnail folder.');
            } else {
                Globals::log('Failed to copy image to thumbnail folder.');
            }
            
            return false;
        }
    }
    
    /**
     * Create all thumbnail images in specify folder
     * 
     * @param type $pathToImages
     * @param type $pathToThumb
     * @param type $thumbWidth
     * @author Nguyen Huu Tam
     * @since 2013/02/20
     */
    public static function createThumbAll($pathToImages, $pathToThumb, $thumbWidth)
    {
        // open the directory
        $dir = opendir($pathToImages);
        if (false !== $dir) {
            // loop through it, looking for any/all JPG files:
            while (false !== ($fname = readdir($dir))) {
                // Create thumbnail
                $result = self::createThumbnail($fname, $pathToImages, $pathToThumb, $thumbWidth, true);
    
                if ($result) {
                    Globals::log("Thumbnail for {$fname} was successfully created");
                } else {
                    Globals::log("Failed to create thumbnail for {$fname}");
                }
            }
            // close the directory
            closedir($dir);
        }
    }
    
    /**
     * Get product thumbnail upload folder
     * 
     * @param string $thumbPath
     * @return string
     * @author Nguyen Huu Tam
     * @since 2013/02/20
     */
    public static function getThumpUploadFolder($thumbPath)
    {
        $session = Globals::getSession();
        $thumbDir = vsprintf(
            $thumbPath,
            self::getThumbnailArguments()
        );
        if (!is_dir($thumbDir)) {
             mkdir($thumbDir, 0777, true);
        }
        
        return $thumbDir;
    }
    
    
    public static function getThumpFolder()
    {
        $session = Globals::getSession();
        $imageConfig = Globals::getApplicationConfig('image');
        $thumbDir = vsprintf(
            $imageConfig->thumb_path,
            array(
                $session->company_name,
                $session->company_code
            )
        );
        if (!is_dir($thumbDir)) {
             mkdir($thumbDir, 0777, true);
        }
        
        return $thumbDir;
    }
    
    
    /**
     * Get thumbnail folder
     * 
     * @param string $thumbSrc
     * @return string
     * @author Nguyen Huu Tam
     * @since 2013/02/21
     */
    public static function getThumpSrc($thumbSrc)
    {
        $session = Globals::getSession();
        $thumbDir = vsprintf(
            $thumbSrc,
            self::getThumbnailArguments()
        );
        
        return $thumbDir;
    }
    
    
    /**
     * Get thumbnail image src
     * 
     * @param string $name
     * @return string
     * @author Nguyen Huu Tam
     * @since 2013/02/22
     */
    public static function getThumbnail($name)
    {
        $thumb = '/product/image/name/' . $name;
        
        return $thumb;
    }
    
    
    /**
     * Get arguments thumbnail
     * 
     * @return array
     * @author Nguyen Huu Tam
     * @since 2013/03/01
     */
    public static function getThumbnailArguments()
    {
        $session = Globals::getSession();
        return array(
            $session->company_name,
            $session->company_code,
            $session->menuset
        );
    }
}
