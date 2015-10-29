<?php
/**
 * Class Html
 *
 * @author Nguyen Huu Tam
 * @copyright Kobe Digital Labo, Inc
 * @since 2012/07/24
 */

class Application_Model_Html
{
    const DOM_VERSION       = '1.0';
    const DOM_ENCODE        = 'UTF-8';
    const IMG_FOLDER        = 'images';
    const HTML_FOLDER       = 'html';
    const HTML_EXTENSION    = '.html';
    
    const MENU_PATTERN          = '/(?P<name>\d+).(?P<extension>html)/';
    const ALL_MENU_PATTERN      = '/(?P<name>\w+).(?P<extension>html)/';
    const TOPVIEW_HREF_PATTERN  = '/(?P<name>\w+)((?P<colon>:)(?P<id>\d+))?/';

    const PAGE_CODE         = 'pagecode';
    const PAGE_NAME         = 'pagename';
    const PAGE_TYPE         = 'pagetype';
    const CATE_TYPE         = 0;
    const MENUSET_TYPE      = 1;
    const MENU_TYPE         = 2;
    const RECOMMEND_TYPE    = 3;
    const PAGE_TITLE        = 'ページ';
    
    const TOPVIEW_NAME      = 'topView';
    const TOPVIEW_NAME_SHOW = '表紙';
    const TOPVIEW_GOMENU    = 'goMenuBook:';
    const TOPVIEW_GOCATE    = 'goCategory';
    const TOPVIEW_GORECOMMEND    = 'goRecommend';
    const TOPVIEW_GOMENUSET = 'goMenuSet:';
    const TOPVIEW_CATENAME  = 'カテゴリ一覧';
    const MENU_HTML_CATENAME  = 'カテゴリページ';
    const MENU_HTML_RECOMMENDNAME  = 'おすすめページ';
    const TOPVIEW_TITLE     = 'トップページ';
    const TOPVIEW_IMG_NAME  = 'TOP5';
    
    const DELETED_ITEM_NAME = '<削除済み>';

    protected $_menuset;
    protected $_htmlFolderPath;
    protected $_imgFolderPath;
    
    protected $_errorMessages = array();

    public function __construct($menuset = '')
    {
        $session = Globals::getSession();
        if (empty($menuset)
            && (!empty($session->menuset))
        ) {
            $menuset = $session->menuset;
        }
        
        $this->_menuset = $menuset;
        
        $this->_htmlFolderPath = self::getHtmlFolderPath($this->_menuset);
        $this->_imgFolderPath = self::getImageFolderPath($this->_menuset);
        
        $this->msgConfig = Zend_Registry::get('MsgConfig');
        $this->imgConfig = Globals::getApplicationConfig('image');
        
        $this->_id = Application_Model_Index::$idCode;
    }

    /**
     * Get default target
     * 
     * @return array
     * @throws Application_Model_Exception 
     * @author Nguyen Huu Tam
     * @since 2012/08/17
     */
    public static function getDefaultTarget()
    {
        $imgConfig = Globals::getApplicationConfig('image');
        
        if ($imgConfig->menu->defaultTarget == null) {
            $msgConfig = Zend_Registry::get('MsgConfig');
            throw new Application_Model_Exception(sprintf($msgConfig->E000_NoConfig, "image: menu.defaultTarget"));
        }
        
        $targets = explode(',', $imgConfig->menu->defaultTarget);
        return array_combine(self::getTargetKeys(), $targets);
    }
    
    /**
     * Get target keys
     * 
     * @return array
     * @author Nguyen Huu Tam
     * @since 2012/08/17
     */
    public static function getTargetKeys()
    {
        return array('x', 'y', 'w', 'h');
    }

    /**
     *
     * @return string
     * @author Nguyen Huu Tam
     * @since 2012/08/03
     */
    public static function getNoImage()
    {
        $config = Globals::getApplicationConfig('image');
        $width = $config->menu->noimage->width;
		$arrWidth = $config->menu->portrait->width->toArray();
		$arrHeight = $config->menu->portrait->height->toArray();
        $height = ceil($width * ($arrHeight[0] / $arrWidth[0]));
        $noImage = '<div class="placeholder"'
            . ' style="width:'.$width.'px; '
            . 'height:'.$height.'px">'
            . '</div>';
        
        return $noImage;
    }

        /**
     * Init DOMDocument object
     *
     * @return \DOMDocument
     * @author Nguyen Huu Tam
     * @since 2012/07/25
     */
    protected function _initDom()
    {
        return new DOMDocument(self::DOM_VERSION, self::DOM_ENCODE);
    }

    /**
     * Create coord information
     * 
     * @param array $data
     * @return string 
     * @author Nguyen Huu Tam
     * @since 2012/07/31
     */
    protected function _makeCoords($data)
    {
        return array(
            'x' => $data['x'],
            'y' => $data['y'],
            'w' => $data['w'],
            'h' => $data['h']
        );
    }

    /**
     *  Check html file exist
     * 
     * @param string $fileName
     * @return array (path, exist)
     *      string path: file path
     *      boolean exist
     * @author Nguyen Huu Tam
     * @since 2012/07/25
     */
    public function checkHtmlFile($fileName, $path = null)
    {
        $isExist = false;
        $file = "$fileName" . self::HTML_EXTENSION;

        if (is_null($path)) {
            $filePath = $this->_htmlFolderPath . $file;
        } else {
            $filePath = $path;
        }

        if (file_exists(realpath($filePath))) {
            $isExist = true;
        }
        
        return array(
            'path' => $filePath,
            'exist' => $isExist
        );
    }

    /**
     *  Read html file content
     * 
     * @param string $fileName
     * @return array (page_number, page_title, image, items)
     * @throws Application_Model_Exception (if product no exist)
     * @author Nguyen Huu Tam
     * @since 2012/07/25
     */
    public function getHtmlFileInfo($fileName)
    {
        $result = $this->checkHtmlFile($fileName);
        if (!$result['exist']) {
            throw new Application_Model_Exception(sprintf($this->msgConfig->E401_NotExist_Page, "{$fileName}" . self::HTML_EXTENSION));
        }

        $data           = array();
        $productData    = array();
        $errorProducts  = array();
        
        $dom = $dom = $this->_initDom();
        @$dom->loadHTMLFile($result['path']);

        $data['page_number'] = $fileName;
       
        // ページタイトル
        $title = $dom->getElementsByTagName('title');
        $data['page_title'] = $title->item(0)->nodeValue;
        
        // 背景画像
        $img = $dom->getElementsByTagName('img');
        $src = $img->item(0)->getAttribute('src');
        $data['image'] = $this->_getImageInfo($src, $this->_menuset);

        // 商品
        $items = array();
        $areas = $dom->getElementsByTagName('area');
        for ($i = 0; $i<$areas->length; $i++) {
            $item = $areas->item($i);
            
            $coords = explode(',', $item->getAttribute('coords'));
            list($x1, $y1, $x2, $y2) = $coords;
            $itemId = $item->getAttribute('href');
            
            $info = array(
                $this->_id  => $itemId,
                'x'         => $x1,
                'y'         => $y1,
                'w'         => $x2-$x1,
                'h'         => $y2-$y1
            );
            
            if ($fileName == self::TOPVIEW_NAME) {
                $items[$itemId] = $info;
            } else {
                $items[] = $info;
            }
        }
        $a = $dom->getElementsByTagName('a');
        for ($i = 0; $i<$a->length; $i++) {
            $item = $a->item($i);
            $styleValues = self::getStyleValue($item->getAttribute('style'));
            $itemId = $item->getAttribute('href');
            
            $info = array(
                $this->_id  => $itemId,
                'x'         => str_replace('px', '', $styleValues['left']),
                'y'         => str_replace('px', '', $styleValues['top']),
                'w'         => str_replace('px', '', $styleValues['width']),
                'h'         => str_replace('px', '', $styleValues['height']),
            );
            
            if ($fileName == self::TOPVIEW_NAME) {
                $items[$itemId] = $info;
            } else {
                $items[] = $info;
            }
        }
        
        @ksort($items, SORT_NATURAL);
        $data['items'] = $items;
        
        // 動画
        $videoItems = array();
        $videos = $dom->getElementsByTagName('video');
        for ($i = 0; $i<$videos->length; $i++) {
            $item = $videos->item($i);
            $styleValues = self::getStyleValue($item->getAttribute('style'));
            
            $src = $item->getAttribute('src');
            $name = substr($src, strrpos($src, '/') + 1);
            $itemName = urldecode($name);
            
            $info = array(
                'name'  => $itemName,
                'x'     => isset($styleValues['left']) ? str_replace('px', '', $styleValues['left']) : '0px',
                'y'     => isset($styleValues['top']) ? str_replace('px', '', $styleValues['top']) : '0px',
                'w'     => $item->getAttribute('width'),
                'h'     => $item->getAttribute('height')
            );
            
            $videoItems[$itemName] = $info;
        }

        $data['videos'] = $videoItems;

        return $data;
    }
    
    
    /**
     * 
     * @param string $string
     * @return array
     * @author Nguyen Huu Tam
     * @since 2013/05/15
     */
    public static function getStyleValue($string)
    {
        $data = array();
        $styles = explode(';', $string);
        
        if (is_array($styles)) {
            foreach ($styles as $style) {
                $value = explode(':', $style);
				if (count($value) > 1) {
					$data[trim($value[0])] = trim($value[1]);
				}
            }
        }
        
        return $data;
    }

    

    /**
     * Make product information
     * 
     * @param array $data
     * @return array(items, errors)
     * @author Nguyen Huu Tam
     * @since 2012/07/31 
     */
    public function getProductInfo($data)
    {
        $indexModel = new Application_Model_Index();
        $csvData = $indexModel->getData();
        
        $items      = null;
        $errorItems = array();
        $itemIds    = array();

        if (is_array($data) && (count($data))) {
            $group = 1;
            $i = 1;
            foreach ($data as $product) {
                $ids = explode(',', $product[$this->_id]);
                // Add group to product data
                $product += array('group' => $group);
                foreach ($ids as $id) {
                    // If no exist product
                    if (!array_key_exists($id, $csvData)) {
                        $info = array(
                            $this->_id  => $id,
                            'itemName'  => self::DELETED_ITEM_NAME,
                            'exist'     => false
                        );
                        $errorItems[] = $id;
                    } else {
                        $info = $csvData[$id];
                    }
                    // For exist product checking
                    $itemIds[$i] = $id;
                    $items[$i] = $info
                        + $product
                        + array('id' => $i); // Add auto increment id
                    $i++;
                }
                $group++;
            }
        }

        return array(
            'items'     => Application_Model_Index::sortMenuProduct($items), // Sort produtc by group and menuCode
            'ids'       => $itemIds,
            'errors'    => $errorItems
        );
    }
    
    /**
     * Make link information
     * 
     * @param array $links
     * @return array
     * @author Nguyen Huu Tam
     * @since 2012/07/31 
     */
    public function getLinkInfo($links)
    {
        $items = array();
        $errors = array();

        if (is_array($links)
            && (count($links))
        ) {
            $pattern = self::TOPVIEW_HREF_PATTERN;
            $info = array();
            foreach ($links as $key => $value) {
                preg_match($pattern, $key, $matches);

                $id = null;
                $href = $matches['name'];
                if (isset($matches['id'])) {
                    $id = $matches['id'];
                    $href .= $matches['colon'];
                }
                
                $result = true;
                switch ($href) {
                    // カテゴリ
                    case self::TOPVIEW_GOCATE:
                        $info = array(
                            self::PAGE_CODE => self::TOPVIEW_GOCATE,
                            self::PAGE_NAME => self::TOPVIEW_CATENAME,
                            self::PAGE_TYPE => self::CATE_TYPE
                        );
                        break;
                     // メニューセット
                    case self::TOPVIEW_GOMENUSET:
                        $menusetObj = new Application_Model_Menuset();
                        $result = $menusetObj->checkMenuset($id);
                        
                        $info = array(
                            self::PAGE_CODE => $id,
                            self::PAGE_NAME => $menusetObj->getMenusetName(
                                $id,
                                Application_Model_Menuset::MENUSET_NAME_SUFFIX
                            ),
                            self::PAGE_TYPE => self::MENUSET_TYPE
                        );

                        break;
                     // メニュー
                    case self::TOPVIEW_GOMENU:
                        $result = $this->checkHtmlFile($id);
                        $result = $result['exist'];
                        
                        $info = array(
                            self::PAGE_CODE => $id,
                            self::PAGE_NAME => $id,
                            self::PAGE_TYPE => self::MENU_TYPE
                        );

                        break;
                }
                
                // No exist
                if (!$result) {
                    $errors[] = $id;
                    $info += array('exist' => false);
                }
                
                $items[$key] = $info + $value;
            }
        }
        
        return array(
            'items' => $items,
            'errors' => $errors
        );
    }

    /**
     *
     * @param string $filePath
     * @return string
     * @throws Application_Model_Exception (if html page no exist)
     * @author Nguyen Huu Tam
     * @since 2012/07/25
     */
    public function toHtml($menuName, $menuset)
    {
        $filePath = self::getHtmlFolderPath($menuset) . $menuName . self::HTML_EXTENSION;
        if (!file_exists(realpath($filePath))) {
            throw new Application_Model_Exception(
                sprintf($this->msgConfig->E401_NotExist_Page, $filePath)
            );
        }

        $dom = $this->_initDom();
        @$dom->loadHTMLFile($filePath);
        
        $img = $dom->getElementsByTagName('img');
        $imgDom = $img->item(0);
        $src = $imgDom->getAttribute('src');
        $imgInfo = $this->_getImageInfo($src, $menuset);
        $imgDom->setAttribute('src', $imgInfo['url']);

        return $dom->saveHTML();
    }
    
    /**
     *
     * @param array $data
     * @return null | DOMDocument Object 
     * @author Nguyen Huu Tam
     * @since 2012/07/31
     */
    protected function _makeHtml($data)
    {
        $html = <<<EOF
<!DOCTYPE HTML>
<html>
<head>
<meta name="viewport" content="width=450">
<meta name="apple-mobile-web-app-capable" content="yes" />
<meta charset="UTF-8" />
<title></title>
<link href="css/style.css" rel="stylesheet" type="text/css" />
</head>
<body>
<p><img src="" alt="" border="0"/></p>
</body>
</html>
EOF;
        $dom = $this->_initDom();
        $dom->loadHTML($html);

        // ページタイトル
        if (isset($data['page_number'])) {
            $title = $dom->getElementsByTagName('title');
            if ($data['page_number'] == self::TOPVIEW_NAME) {
                $title->item(0)->nodeValue = self::TOPVIEW_TITLE;
            } else {
                $title->item(0)->nodeValue = $data['page_number'] . self::PAGE_TITLE;
            }
        }
        
        // 背景画像
        if (isset($data['image'])) {
            $image = $dom->getElementsByTagName('img');
            $src = self::IMG_FOLDER . "/{$data['image']}";
            if (isset($data['is_preview'])) {
                $src = $data['image']['url'];
            }
            $image->item(0)->setAttribute('src', $src);
        }
        
        // Get p html tag
        $pDom = $dom->getElementsByTagName('p');
        
        // 商品
        $curGroup = '';
        $codes = null;
        $coords = '';
        // ページ
        if (isset($data['products'])) {
            foreach ($data['products'] as $product) {
                $code = $product['menuCode'];
                // The first item
                if (empty($curGroup)) {
                    $curGroup = $product['group'];
                    $codes[] = $code;
                    $coords = $this->_makeCoords($product);
                    continue;
                }
                
                // If same group
                if ($curGroup == $product['group']) {
                    $codes[] = $code;
                    continue;
                } else {
                    // Save previous group product data
                    $info = array(
                        'coords'    => $coords,
                        'code'      => $codes
                    );
                    $a = self::addAInfo($dom, $info);
                    $pDom->item(0)->appendChild($a);
                    
                    // Save data for current group
                    $curGroup = $product['group'];
                    unset($codes);
                    $codes[] = $code;
                    $coords = $this->_makeCoords($product);
                }
            }
            
            // Save the last item
            $lastInfo = array(
                'coords'    => $coords,
                'code'      => $codes
            );
            $lastA = self::addAInfo($dom, $lastInfo);
            $pDom->item(0)->appendChild($lastA);
        }
        
        if (isset($data['videos'])) {
            foreach ($data['videos'] as $name => $info) {
                $video = self::addVideoInfo($dom, $info);
                $pDom->item(0)->appendChild($video);
            }
        }

        // TopView
        if (isset($data['links'])) {
            foreach ($data['links'] as $code => $link) {
                $info = array(
                    'coords'    => $this->_makeCoords($link),
                    'code'      => array($code)
                );
                $a = self::addAInfo($dom, $info);
                $pDom->item(0)->appendChild($a);
            }
        }
        
        return $dom;
    }
    
    /**
     *
     * @param DOMDocument $dom
     * @param array $info
     * @return DOMElement
     * @author Nguyen Huu Tam
     * @since 2012/10/05 
     */
    public static function addVideoInfo($dom, $info)
    {
        $video = $dom->createElement('video');

        $video->setAttribute('src', "../video/{$info['name']}");
        $video->setAttribute('controls', null);
        $video->setAttribute('style', "position:absolute;left:{$info['x']}px;top:{$info['y']}px");
        $video->setAttribute('width', $info['w']);
        $video->setAttribute('height', $info['h']);
        
        return $video;
    }
    
    
    /**
     *
     * @param DOMDocument $dom
     * @param array $info
     * @return DOMElement
     * @author Nguyen Huu Tam
     * @since 2013/05/15 
     */
    public static function addAInfo($dom, $info)
    {
        $a = $dom->createElement('a');
        $a->setAttribute('href', implode(',', $info['code']));
        $coords = $info['coords'];
        $a->setAttribute(
            'style',
            "position:absolute;"
            . "left:{$coords['x']}px;"
            . "top:{$coords['y']}px;"
            . "width:{$coords['w']}px;"
            . "height:{$coords['h']}px"
        );
        
        return $a;
    }
    
    
    /**
     *
     * @param DOMDocument $dom
     * @param array $info
     * @return DOMElement
     * @author Nguyen Huu Tam
     * @since 2012/10/05 
     */
    public static function addAreaInfo($dom, $info)
    {
        $area = $dom->createElement('area');
        
        $area->setAttribute('shape', 'rect');
        $area->setAttribute('coords', $info['coords']);
        $area->setAttribute('href', implode(',', $info['code']));
        
        return $area;
    }

        /**
     * Make htmt data
     * 
     * @param aray $data
     * @return null | string
     * @author Nguyen Huu Tam
     * @since 2012/07/31 
     */
    public function makePreviewHtml($data)
    {
        $dom = $this->_makeHtml($data);
        if ($dom) {
            return $dom->saveHTML();
        }
        
        return;
    }
    
    /**
     * Create or update html file
     * 
     * @param array $data
     * @return boolean 
     * @author Nguyen Huu Tam
     * @since 2012/07/25
     */
    public function makeHtmlFile($data)
    {
        if (isset($data['page_number'])) {
            $fileName = $data['page_number'];
            $result = $this->checkHtmlFile($fileName);
            $data = $this->_copyImageFile($data);
            
            $dom = $this->_makeHtml($data);
            if ($dom) {
                return $dom->saveHTMLFile($result['path']);
            }
        }
        return;
    }
    
    /**
     * Update html temp file
     * 
     * @param array $data
     * @return boolean 
     * @author Nguyen Huu Tam
     * @since 2012/08/31
     */
    public function updateHtmlFile($data, $filePath)
    {
        if (isset($data['page_number'])) {
            $fileName = $data['page_number'];
            $result = $this->checkHtmlFile($fileName, $filePath);

            $dom = $this->_updateHtml($filePath, $data);
            if ($dom) {
                return $dom->saveHTMLFile($result['path']);
            }
        }
        return;
    }
    
    /**
     *
     * @param array $data
     * @return null | DOMDocument Object 
     * @author Nguyen Huu Tam
     * @since 2012/07/31
     */
    protected function _updateHtml($file, $data)
    {
        if (file_exists($file)) {
            $dom = $this->_initDom();
            @$dom->loadHTMLFile($file);
            
            // 背景画像
            $image = $dom->getElementsByTagName('img');
            $src = self::IMG_FOLDER . "/{$data['image']}";
            $image->item(0)->setAttribute('src', $src);
            
        } else {
            throw new Application_Model_Exception(
                sprintf($this->msgConfig->C000_FileNotFound, $file)
            );
        }
        
        return $dom;
    }
    
    /**
     *
     * @param array $data
     * @return array
     * @throws Application_Model_Exception 
     * @author Nguyen Huu Tam
     * @since 2012/08/03
     */
    protected function _copyImageFile($data)
    {
        if (isset($data['image'])) {
            $ext = self::getExtension($data['image']);
            
            if ($data['page_number'] == self::TOPVIEW_NAME) {
                $newName = self::TOPVIEW_IMG_NAME . ".$ext";
            } else {
                $newName = "{$data['page_number']}.$ext";
            }
            
            $tempFile = Globals::getTmpUploadFolder() . $data['image'];
            $newFile = $this->_imgFolderPath . $newName;

            if ((isset($data['is_copy']))
                && (!file_exists($tempFile))
            ) {
                $tempFile = $this->_imgFolderPath . $data['image'];
            }

            if (file_exists($tempFile)) {
                // If not exist folder then create
                if (!is_dir($this->_imgFolderPath)) {
                    mkdir($this->_imgFolderPath, 0777);
                }
                // Copy image file from temp folder
                if (!copy($tempFile, $newFile)) {
                    throw new Application_Model_Exception($this->msgConfig->E000_Failed_CopyImage);
                }
                
                // Create thumbnail image
                Application_Model_Image::createThumbnail(
                    $newName,
                    $this->_imgFolderPath,
                    Application_Model_Image::getThumpUploadFolder($this->imgConfig->menu->thumb_path),
                    $this->imgConfig->menu->thumb_width
                );
                
                // Delete old file if exists
                $result = $this->checkHtmlFile($data['page_number']);
                if ($result['exist']) {
                    $htmlData = $this->getHtmlFileInfo($data['page_number']);
                    if ($newName != $htmlData['image']['name']) {
                        unlink($htmlData['image']['path']);
                    }
                }
            } else if (isset($data['is_copy'])) {
				$newName = '';
			}
            $data['image'] = $newName;
            
            return $data;
        }
    }
    
    /**
     * Get file extension
     * @param string $fileName
     * @return null|string
     * @author Nguyen Huu Tam
     * @since 2012/08/03
     */
    public static function getExtension($fileName)
    {
        if (is_string($fileName)) {
            return substr($fileName, strrpos($fileName, '.') + 1);
        }
        return;
    }
    
    /**
     *
     * @param string $destination
     * @return boolean
     * @throws Application_Model_Exception 
     * @author Nguyen Huu Tam
     * @since 2012/07/16
     */
    public function uploadImage()
    {
        $upload = new Zend_File_Transfer_Adapter_Http();
        
        if ($upload->isUploaded()) {
            
            // File size validate
            $sizeValidator = Application_Model_Image::setValidatorMessage(
                'Zend_Validate_File_Size',
                array('max' => $this->imgConfig->maxSize)
            );            
            
            // Image size validate
            /*$imageSizeValidator = Application_Model_Image::setValidatorMessage(
                'Zend_Validate_File_ImageSize',
                array(
                    'minwidth' => $this->imgConfig->menu->min_width,
                    'maxwidth' => $this->imgConfig->menu->width,
                    'maxheight' => $this->imgConfig->menu->height
                )
            );*/
			/*$validSizeList = array();
			$height = $this->imgConfig->menu->portrait->height->toArray();
			foreach ($this->imgConfig->menu->portrait->width->toArray() as $key => $width) {
				$validSizeList[$width . '-' . $height[$key]] = 1;
			}
			$height = $this->imgConfig->menu->landscape->height->toArray();
			foreach ($this->imgConfig->menu->landscape->width->toArray() as $key => $width) {
				$validSizeList[$width . '-' . $height[$key]] = 1;
			}
			$errors = array();
			$files = $upload->getFileInfo();
			foreach ($files as $info) {
				$size = getimagesize($info['tmp_name']);
				if (!isset($validSizeList[$size[0] . '-' . $size[1]])) {
					$errors[] = $this->msgConfig->E401_FileImageSizeNotValid;
				}
			}*/
			
            // Extension validate
            $allowImgType = explode(',', $this->imgConfig->allowTypes);
            $extensionValidator = Application_Model_Image::setValidatorMessage(
                'Zend_Validate_File_Extension',
                $allowImgType
            );
            
            $upload->addValidator($extensionValidator, false);
            $upload->addValidator($extensionValidator, false)
                ->addValidator($sizeValidator, false);
                //->addValidator($imageSizeValidator, false);
            
            // Rename file avoid duplicate name
            /*foreach ($upload->getFileInfo() as $info) {
                $newName = time() . mt_rand() . '.' . self::getExtension($info['name']);
                $upload->addFilter(
                    'Rename',
                    array(
                        'target'    => Globals::getTmpUploadFolder() . $newName,
                        'overwrite' => true
                    )
                );
            }*/
			
			if (count($errors) > 0) {
				$messages = array_merge($errors, $upload->getMessages());
                $this->setErrorMessages($messages);
                Globals::log($messages);

                return false;
			}
            
            // Set folder to save uploaded file
            $upload->setDestination(Globals::getTmpUploadFolder());

            if (!$upload->receive()) {
                $messages = $upload->getMessages();
                $this->setErrorMessages($messages);
                Globals::log($messages);

                return false;
            } else {
                $file = array();
                foreach ($upload->getFileInfo() as $info) {

                    $tmpName = $info['tmp_name'];
                    $name = $info['name'];
                    
                    /* -- Comment resize image from 22/07/2013 -- */
                    //require_once 'Kdl/Ipadso/ImageManipulate/ImageManipulate.php';
                    //$imageManipulate = ImageManipulateFactory::factory($tmpName);
                    
                    //$imageConfig = Globals::getApplicationConfig('image');
                    
                     /*$imageManipulate->resize(
                        $tmpName,
                        $imageConfig->menu->width,
                        $imageConfig->menu->height
                    );*/
                    
                    $url = '/menu/image/temp/true/name/' . $name;
                    $file += $this->_getImageExtraInfo($tmpName, $url);
                    $file['name'] = $name;
                    unset($file['path']);
                }

                return $file;
            }
            
        } else {
            throw new Application_Model_Exception($this->msgConfig->E401_Require_Image);
        }
    }
    
    /**
     * Get image dimension info
     * 
     * @param string $path
     * @return null|string
     * @author Nguyen Huu Tam
     * @since 2012/08/03
     */
    public static function getImageDimension($path)
    {
        if ((is_string($path))
            && (file_exists($path))
        ) {
            return getimagesize($path);
        }
        
        return;
    }
    
    /**
     * Set image info from 'src' attribute of 'img' element in html file
     * 
     * @param string $src
     * @return array (image_name, image_url, image_html) 
     * @author Nguyen Huu Tam
     * @since 2012/08/03
     */
    protected function _getImageInfo($src, $menuset)
    {
        $name = substr($src, strrpos($src, '/') + 1);
        $url = "/menu/image/menuset/{$menuset}/name/" . $name;
        $path = self::getImageFolderPath($menuset) . $name;
        
        // Create thumbnail data
        $file['thumb'] = $url;
        $thumbPath = Application_Model_Image::getThumpSrc($this->imgConfig->menu->thumb_path) . $name;
        if (is_file($thumbPath)) {
            $file['thumb'] = Application_Model_Image::getThumpSrc($this->imgConfig->menu->thumb_src) . $name;
        }
        
        $file['name'] = $name;

        return $file + $this->_getImageExtraInfo($path, $url);
    }

    /**
     * Add some image extra info
     *
     * @param string $path
     * @param string $url
     * @return array 
     * @author Nguyen Huu Tam
     * @since 2012/08/03
     */
    protected function _getImageExtraInfo($path, $url)
    {
        if (is_file($path)) {
            $file['html'] = '<img id="menu_image" src="' . $url . '" class="preview">';
            
            $dimension = self::getImageDimension($path);
            // For max target input validation on product list
            $file['max_dimension'] = 'max:'.max($dimension).',';
            $file += $dimension;
        } else {
            //$file['html'] = '';
            $file['max_dimension'] = '';
        }
        $file['url'] = $url;
        $file['path'] = $path;
        
        return $file;
    }

    /**
     * Get list of html files
     * 
     * @param boolean $isAll 
     *  - true: get all html files
     *  - false: just html files with name is number (ex: 1.html, 12.html, ...)
     * @param array $sortParams (order: asc|desc)
     * @return array
     * @author Nguyen Huu Tam
     * @since 2012/07/31
     */
    public function getHtmlList($isAll = true, $sortParams = null)
    {
        $data = array();
        // Get all directories and files
        $files = @scandir($this->_htmlFolderPath);
        if ($files) {
            // Default sort
            natsort($files);

            // Sort by param
            if (is_array($sortParams)
                && key_exists('order', $sortParams)
                && (strtolower($sortParams['order']) == 'desc')
            ) {
                $files = array_reverse($files);
            }

            $list = array();
            $i = 0;

            $pattern = self::ALL_MENU_PATTERN;
            if (!$isAll) {
                $pattern = self::MENU_PATTERN;
            }
			
			//add topview to the top
			/*$list[0] = array(
                        self::PAGE_NAME     => self::TOPVIEW_NAME,
                        self::PAGE_CODE     => self::TOPVIEW_NAME_SHOW,
                        // For menuset treegrid
                        'id'                => $this->_menuset . '_' . self::TOPVIEW_NAME,
                        'name'              => self::TOPVIEW_NAME
                    );*/

			$foundTopView = false;
            foreach ($files as $file) {
                if (preg_match($pattern, $file, $matches)) {
                    // If page name is not a number or 'topView'
                    if ((!is_numeric($matches['name']))) {
						if ($matches['name'] === self::TOPVIEW_NAME) {
							$foundTopView = true;
						}
                        continue;
                    }
                    $list[$i] = array(
                        self::PAGE_NAME     => $matches['name'],
                        self::PAGE_CODE     => $matches['name'],
                        // For menuset treegrid
                        'id'                => $this->_menuset . '_' . $matches['name'],
                        'name'              => $matches['name']
                    );
                    $i++;
                }
            }
			//check empty folder
			/*if (!$foundTopView && count($list) == 1) {
				$list = array();
			}*/
			
            $data['total'] = count($list);
            $data['rows'] = $list;
        }
        
        return $data;
    }
    
    /**
     * Reset menu order
     *
     * @param array $items
     * @return boolean 
     * @author Nguyen Huu Tam
     * @since 2012/09/21
     */
    public function resetMenuOrder($items)
    {
        $deleteList = array();
        $standardList = $this->_createStandardList($items);
        
        if ($this->_makeTempData($items, $standardList)) {
            // Remove Topview item
            //array_pop($standardList);
            
            // Get menu html files
            $menu = $this->getHtmlList();
			
            $currentList = array();
            // Remove Topview item
			foreach ($menu['rows'] as $value) {
				if ($value['pagename'] !== self::TOPVIEW_NAME) {
					$currentList[] = $value;
				}
			}
            
            $deleteItems = array_diff_assoc($currentList, $standardList);
            if (count($deleteItems)) {
                foreach ($deleteItems as $item) {
                    $deleteList[] = $item['pagename'];
                }
            }
        }
        
        return (count($deleteList) ? implode(',', $deleteList) : false);
    }

    /**
     * Remove menu
     * 
     * @param string $ids
     * @return array|boolean
     * @author Nguyen Huu Tam
     * @since 2012/09/24 
     */
    public function deleteMenu($ids)
    {
        $result = array();
        $arrIds = explode(',', $ids);

        if (count($arrIds)) {
            // Can not remove TOPVIEW
            if (in_array(Application_Model_Html::TOPVIEW_NAME, $arrIds)) {
                $result['error'][] = $this->msgConfig->E400_CannotDelete_TopView;
            }

            // Remove html files
            $fileCount = 0;
            $fileList = array();

            $htmlObj = new Application_Model_Html();
            foreach ($arrIds as $fileName) {
                $htmlPath = $this->_htmlFolderPath . $fileName . self::HTML_EXTENSION;
                if (file_exists(realpath($htmlPath))) {
                    $data = $htmlObj->getHtmlFileInfo($fileName);
                    // Delete html file
                    unlink($htmlPath);
                    // Delete image file
                    unlink($data['image']['path']);
                    // Delete thumbnail file
                    unlink(
                        Application_Model_Image::getThumpUploadFolder(
                            $this->imgConfig->menu->thumb_path . $data['image']['name']
                        )
                    );
                    Globals::log(sprintf($this->msgConfig->N401_FileDeleted, $htmlPath));

                    $fileList['deleted'][] = $fileName;
                    $fileCount++;
                } else {
                    $fileList['no_exist'][] = $fileName;
                    Globals::log(sprintf($this->msgConfig->E401_NotExist_Page, $fileName));
                }
            }

            if ($fileCount) {
                $result['message'][] = sprintf($this->msgConfig->E400_Delete_Pages, implode(', ', $fileList['deleted']));
            }

            if (count($fileList['no_exist'])) {
                $result['message'][] = sprintf($this->msgConfig->E401_NotExist_Page, implode(', ', $fileList['no_exist']));
            }
        }
        
        return $result;
    }

        
    /**
     * Create standard menu list
     *
     * @param array $items
     * @return array 
     * @author Nguyen Huu Tam
     * @since 2012/09/21
     */
    protected function _createStandardList($items)
    {
        $result = array();
        $i = 1;
        foreach ($items as $key => $pageName) {
            $value = ($pageName == self::TOPVIEW_NAME) ? $pageName : $i++;
            $result[] = array('pagename' => $value);
        }
        
        return $result;
    }


    /**
     *
     * @param array $items
     * @param array $defaultList
     * @return array 
     * @author Nguyen Huu Tam
     * @since 2012/09/21
     */
    protected function _makeTempData($items, $defaultList)
    {
        $history    = array();
        $tmpItems   = array();
        $tmpPath    = self::getTempFolder();
        $htmlObj    = new Application_Model_Html();

        foreach ($items as $index => $pageName) {
            // If there's not any changing
            $mainName = $defaultList[$index]['pagename'];
            if ($pageName == $mainName) {
                continue;
            }
            // Save the changing
            $history[$mainName] = $pageName;

            $htmlFile       = $this->_htmlFolderPath . "$pageName" . self::HTML_EXTENSION;
            $tmpName        = $mainName . self::HTML_EXTENSION;
            $tmpHtmlFile    = $tmpPath . $tmpName;

            // Copy html file to tmp folder
            if (!copy($htmlFile, $tmpHtmlFile)) {
                Globals::log($tmpHtmlFile . $this->msgConfig->E000_Failed_CopyFile);
            }

            // Get image info of old file
            $oldFileInfo    = $htmlObj->getHtmlFileInfo($pageName);
            $imgFile        = $oldFileInfo['image']['path'];
            $oldExtension   = self::getExtension($oldFileInfo['image']['name']);
            try {
                // Get image info of temp file
                $newFileInfo    = $htmlObj->getHtmlFileInfo($mainName);
                $tmpImgFile     = $newFileInfo['image']['name'];
                $tmpExtension   = self::getExtension($tmpImgFile);

                $tmpImgFile     = $tmpPath . str_replace($tmpExtension, $oldExtension, $tmpImgFile);
            } catch (Application_Model_Exception $e) {
                $tmpImgFile = "{$tmpPath}{$mainName}.{$oldExtension}";
            }

            // Copy image file to tmp folder
            if (!rename($imgFile, $tmpImgFile)) {
				Globals::log($oldFileInfo['image']['name'] . $this->msgConfig->E000_Failed_CopyImage);
			}
        }

        // If have changing
        if (count($history)) {
            // Change 'src' attribute of <img> tag in html file content
            foreach ($history as $tmpName => $baseName) {

                $baseFileInfo = $htmlObj->getHtmlFileInfo($baseName);
                $baseExtension = self::getExtension($baseFileInfo['image']['name']);

                $data = array(
                    'page_number'   => $tmpName,
                    'image'         => "$tmpName.$baseExtension"
                );

                // Update html temp file
                $tmpHtmlFile = $tmpPath . "$tmpName" . self::HTML_EXTENSION;
                $htmlObj->updateHtmlFile($data, $tmpHtmlFile);

                $tmpItems[$tmpName] = $tmpHtmlFile;
            }
            
            $allTmpFiles = @scandir($tmpPath);
            if ($allTmpFiles) {
                $pattern = self::ALL_MENU_PATTERN;
                foreach ($allTmpFiles as $fileName) {
                    // Html files
                    if (preg_match($pattern, $fileName, $matches)) {
                        $file = $this->_htmlFolderPath . $fileName;
                        $tmpFile = $tmpItems[$matches['name']];
                        
                        if (is_file($tmpFile)) {
                            // Move html temp file to data folder
                            if (!rename($tmpFile, $file)) {
                                Globals::log($tmpFile . $this->msgConfig->E000_Failed_CopyFile);
                            }
                        }
                    } else {
                        // Image files
                        $tmpFile = $tmpPath . $fileName;
                        $file = $this->_imgFolderPath . $fileName;

                        if (is_file($tmpFile)) {
                            // Copy image temp file to data folder
                            if (copy($tmpFile, $file)) {
                                // Create thumbnail
                                Application_Model_Image::createThumbnail(
                                    $fileName,
                                    $this->_imgFolderPath,
                                    Application_Model_Image::getThumpUploadFolder($this->imgConfig->menu->thumb_path),
                                    $this->imgConfig->menu->thumb_width
                                );
                                // Remove temp file
                                unlink($tmpFile);
                            } else {
                                Globals::log($tmpFile . $this->msgConfig->E000_Failed_CopyImage);
                            }
                        }
                    }
                }
            }
            return true;
        } else {
            return false;
        }
    }

    /**
     * Get temporary folder
     *
     * @param string $tmpFolder
     *      Default: menu
     * @return string
     * @author Nguyen Huu Tam
     * @since 2012/09/21 
     */
    public static function getTempFolder($tmpFolder = 'menu')
    {
        // Create temporary folder
        $tmpPath = Globals::getTmpUploadFolder()
            . $tmpFolder
            . DIRECTORY_SEPARATOR;
        // If not exist temporary folder, then create
        if (!is_dir($tmpPath)) {
            mkdir($tmpPath, 0777);
        }
        return $tmpPath;
    }

    /**
     * Update order menu
     *
     * @param array $sortItems
     * @return boolean 
     * @author Nguyen Huu Tam
     * @since 2012/08/31
     */
    public function updateMenuOrder($sortItems)
    {
        // Get default menu list
        $menu = $this->getHtmlList();
        $defaultList = $menu['rows'];
        
        return $this->_makeTempData($sortItems, $defaultList);
    }
    
    /**
     * Menu Flow Data
     *
     * @return array 
     * @author Nguyen Huu Tam
     * @since 2012/08/31
     */
    public function getMenuFlowData()
    {
        $menu = $this->getHtmlList(true);
        $result = array();
        if (count($menu['rows'])) {
            $menuset = '/menuset/' . $this->_menuset;
            $htmlObj = new Application_Model_Html();
            foreach ($menu['rows'] as $key => $item) {
                $data = array();
                $htmlInfo = $htmlObj->getHtmlFileInfo($item['pagename']);
                $data['src']        = $htmlInfo['image']['thumb'];
                $data['title']      = $item['pagename'];
                $data['preview']    = "/menu/preview{$menuset}/name/" . $item['pagename'];

                $result[] = $data;
            }
        }

        return $result;
    }
    
    
    /**
     * Get target (x,y,w,h) in data
     * 
     * @param array $data
     * @return array 
     * @author Nguyen Huu Tam
     * @since 2012/10/04
     */
    public static function getTarget($data)
    {
        $targetKeys = self::getTargetKeys();
        
        return array_intersect_key(
            $data,
            array_flip($targetKeys)
        );
    }

    /**
     * Get target (x,y,w,h) in data by group specified
     * 
     * @param string $group
     * @param array $data
     * @return array|boolean 
     * @author Nguyen Huu Tam
     * @since 2012/10/04
     */
    public static function getTargetByGroup($group, $data)
    {
        $result = self::search($data, 'group', $group);
        
        if (count($result)) {
            $data = array_shift($result);
            
            return self::getTarget($data);
        }
        
        return false;
    }
    
    /**
     * Search
     *
     * @param array $array
     * @param string $key
     * @param string $value
     * @return array
     * @author sunelbe at gmail dot com 21-Sep-2012 05:19
     * @link http://www.php.net/manual/en/function.array-search.php#110120
     */
    public static function search($array, $key, $value)
    {
        $results = array();

        if (is_array($array)) {
            if (isset($array[$key])
                && ($array[$key] == $value)
            ) {
                $results[] = $array;
            }
            
            foreach ($array as $subarray) {
                $results = array_merge($results, self::search($subarray, $key, $value));
            }
        }

        return $results;
    }
    
    
    /**
     *
     * @return array 
     * @author Nguyen Huu Tam
     * @since 2012/11/08
     */
    public function getErrorMessages()
    {
        return $this->_errorMessages;
    }
    
    
    /**
     *
     * @param array $messages
     * @author Nguyen Huu Tam
     * @since 2012/11/08 
     */
    public function setErrorMessages($messages)
    {
        if (!empty($messages) && is_array($messages)) {
            $this->_errorMessages = $messages;
        }
    }
    
    
    /**
     * 
     * @param int $menuset
     * @return string
     * @author Nguyen Huu Tam
     * @since 2013/02/28
     */
    public static function getHtmlFolderPath($menuset = '')
    {
        $session = Globals::getSession();
        $htmlFolder = self::HTML_FOLDER;
        if ($menuset != Application_Model_Menuset::getDefaultMenuset()) {
            $htmlFolder .= $menuset;
        }
        
        $htmlFolderPath = $session->company_link . DIRECTORY_SEPARATOR
            . $htmlFolder . DIRECTORY_SEPARATOR;
        
        return $htmlFolderPath;
    }
    
    
    /**
     * 
     * @param int $menuset
     * @return string
     * @author Nguyen Huu Tam
     * @since 2013/02/28
     */
    public static function getImageFolderPath($menuset = '')
    {
        $imgFolderPath = self::getHtmlFolderPath($menuset)
            . self::IMG_FOLDER . DIRECTORY_SEPARATOR;
        
        return $imgFolderPath;
    }
    
    
    /**
     * 
     * @return string
     * @author Nguyen Huu Tam
     * @since 2013/02/28
     */
    public function getBaseHtmlFolderPath()
    {
        $session = Globals::getSession();
        $folderPath = $session->company_link
            . DIRECTORY_SEPARATOR
            . self::HTML_FOLDER;
        
        return $folderPath;
    }
    

    /**
     * Get all menus
     * 
     * @return array
     * @author Nguyen Huu Tam
     * @since 2013/03/07
     */
    public function getAllMenus()
    {
        $list = array();
        // Get all directories and files
        $files = @scandir($this->_htmlFolderPath);
        
        if ($files) {
            natsort($files);
            $pattern = self::MENU_PATTERN;
            foreach ($files as $file) {
                if (preg_match($pattern, $file, $matches)) {
                    $list[] = $matches;
                }
            }
        }

        return $list;
    }
    
    
    /**
     * Get link list for top-view page
     * 
     * @return array
     * @author Nguyen Huu Tam
     * @since 2013/03/07
     */
    public function getLinkList()
    {
        // カテゴリ
        $data[] = array(
            $this->_id      => self::TOPVIEW_GOCATE,
            self::PAGE_NAME => self::MENU_HTML_CATENAME,
            self::PAGE_CODE => self::TOPVIEW_GOCATE,
            self::PAGE_TYPE => self::CATE_TYPE
        );
        
        // おすすめページ
        $data[] = array(
            $this->_id      => self::TOPVIEW_GORECOMMEND,
            self::PAGE_NAME => self::MENU_HTML_RECOMMENDNAME,
            self::PAGE_CODE => self::TOPVIEW_GORECOMMEND,
            self::PAGE_TYPE => self::RECOMMEND_TYPE
        );
        
        // メニュー
        $menus = $this->getAllMenus();
        foreach ($menus as $item) {
            $data[] = array(
                $this->_id      => self::TOPVIEW_GOMENU . $item['name'],
                self::PAGE_NAME => $item['name'] . 'ページ',
                self::PAGE_CODE => $item['name'],
                self::PAGE_TYPE => self::MENU_TYPE
            );
        }

        return $data;
    }
	
	public function getMenuOthersList() {
		$data = array();
		// メニューセット
        $menusetObj = new Application_Model_Menuset();
        $menusets = array();//$menusetObj->getAllMenusets();
		$menusetData = $menusetObj->getJsonData();
		foreach ($menusetData['list'] as $key => $value) {
			$menusets[$key] = array('menuset' => $key);
		}

        // Remove current menuset
        $curMenuset = $menusetObj->getCurrenMenuset();
        unset($menusets[$curMenuset]);

        foreach ($menusets as $item) {
            if (empty($item['menuset'])) {
                $item['menuset'] = 1;
            }
            $data[] = array(
                'id'      => self::TOPVIEW_GOMENUSET . $item['menuset'],
                'name' => $menusetObj->getMenusetName(
                    $item['menuset'],
                    Application_Model_Menuset::MENUSET_NAME_SUFFIX
                ),
                'code' => $item['menuset'],
                'type' => 'menu_others'
            );
        }
		return $data;
	}
    
	/**
     * Get list of link
     * 
     * @param string $keyword
     * @return array
     * @author Nguyen Quang Trung
     * @since 2014/05/14
     */
	public function getFilteredList($keyword = '') {
		$data = $this->getLinkList();
		
		$links = array();
        foreach ($data as $link) {
			if (strlen($keyword) > 0 && !preg_match('/' . $keyword . '/i', $link[self::PAGE_NAME], $matches)) {
				continue;
			}
            $links[] = array('id' => $link[$this->_id], 'code' => '', 'name' => $link[self::PAGE_NAME], 'type' => 'link');
        }

        return $links;
	}
    
    /**
     * Get next page number
     * 
     * @return array
     * @author Nguyen Huu Tam
     * @since 2013/05/09
     */
    public function getNextPageNumber()
    {
        $pageNumbers = array(0);
        $files = @scandir(self::getHtmlFolderPath($this->_menuset));
        if ($files) {
            $pattern = array('/(?P<name>\d+).(?P<extension>html)/');
            $replace = array('$1');
            $pageNumbers = preg_filter($pattern, $replace, $files);
        }

        if (empty($pageNumbers)) {
            $pageNumbers = array(0);
        }
        $nextPageNumber = max($pageNumbers) + 1;
        
        return $nextPageNumber;
    }
}
