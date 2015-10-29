<?php
/**
 * Class Menuset
 *
 * @author Nguyen Huu Tam
 * @copyright Kobe Digital Labo, Inc
 * @since 2013/02/28
 */
require_once('Kdl/Ipadso/Json.php');

class Application_Model_Menuset
{
    const FOLDER_PATTERN = '/(?P<name>(html)(?P<menuset>\d*))/';
    const MENUSET_NAME_SUFFIX = '';
    
    protected $_folderPath;
    protected $_logFile = 'menuset.log';


    public function __construct()
    {
        $session = Globals::getSession();
        
        $this->_folderPath = $session->company_link . DIRECTORY_SEPARATOR;
        
        $this->msgConfig = Zend_Registry::get('MsgConfig');
        $this->imgConfig = Globals::getApplicationConfig('image');
    }
    
    
    /**
     * Get stored menuset folder
     * 
     * @return type
     * @author Nguyen Huu Tam
     * @since 2013/02/28
     */
    public function getFolderPath()
    {
        return $this->_folderPath;
    }
    
    
    /**
     * Set stored menuset folder
     * 
     * @return type
     * @author Nguyen Huu Tam
     * @since 2013/03/08
     */
    public function setFolderPath($path)
    {
        $this->_folderPath = $path;
    }

        
    /**
     * Get list of html folders
     * 
     * @param array $sortParams (order: asc|desc)
     * @return array
     * @author Nguyen Huu Tam
     * @since 2013/02/28
     */
    public function getList($sortParams = null, $page = null, $limit = null)
    {
        $data = array();
        // Get all directories and files
        $folders = @scandir($this->getFolderPath());
        if ($folders) {
            // Default sort
            natsort($folders);
            // Sort by param
            if (is_array($sortParams)
                && key_exists('order', $sortParams)
                && (strtolower($sortParams['order']) == 'desc')
            ) {
                $folders = array_reverse($folders);
            }
            
            $list = array();
            $i = 0;

            $pattern = self::FOLDER_PATTERN;
            foreach ($folders as $folder) {
                if (preg_match($pattern, $folder, $matches)) {
                    $curMenuset = $matches['menuset'];
                    // Case with <html> orginal folder
                    if (empty($curMenuset)) {
                        $curMenuset = self::getDefaultMenuset();
                    }
                    
                    $list[$i] = array(
                        'id'        => $curMenuset,
                        'name'      => $this->getMenusetName($curMenuset),
                        'fullname'  => $matches['name']
                    );
                    $i++;
                }
            }
			$count = count($list);
            $data['total'] = $count;
            if (($page == null) && ($limit == null)) {
                $data['rows'] = $list;
            } else {
                if ($count > 0) {
                    $total_pages = is_null($limit) ? 1 : ceil($count / $limit);
                } else {
                    $total_pages = 0;
                }
                
                if ($page > $total_pages) {
                    $page = $total_pages;
                }
                $start = $limit * $page - $limit;

                $data['rows'] = (array_slice($list, $start, $limit));
            }
        }

        return $data;
    }
    
    
    /**
     * Remove html folder
     * 
     * @param string $ids
     * @return array|boolean
     * @author Nguyen Huu Tam
     * @since 2013/02/28
     */
    public function delete($ids)
    {
        $result = array();
        $arrIds = explode(',', $ids);
        
        if (count($arrIds)) {
            // Remove html files
            $fileCount = 0;
            $fileList = array();
            
            foreach ($arrIds as $id) {
				$menusetName = '';
				//remove json
				$jsonData = $this->getJsonData();
				$newList = array();
				$i = 1;
				foreach ($jsonData['list'] as $key => $name) {
					if ($key != $id) {
						$newList[$i] = $name;
						$i++;
					} else {
						$menusetName = $name;
					}
				}
				$jsonData['list'] = $newList;
				$this->saveJsonData($jsonData);

				//TODO: remove old json
				$jsonData = $this->getOldJsonData();
				$newList = array();
				$i = 1;
				foreach ($jsonData['list'] as $key => $name) {
					if ($key != $id) {
						$newList[$i] = $name;
						$i++;
					} else {
						$menusetName = $name;
					}
				}
				$jsonData['list'] = $newList;
				$this->saveOldJsonData($jsonData);
				
				//remove folder
                $htmlFolder = $this->getMenusetPath($id);
                $output = $this->deleteAll($htmlFolder);

                if (!$output) {
                    throw new Application_Model_Exception(
                        sprintf($this->msgConfig->E600_Failed_ExecuteCommand, $removeExec)
                    );
                }
                Globals::log("$htmlFolder folder has been deleted.", null, $this->_logFile);
                
                // Remove thumbnails
                $imageConfig = Globals::getApplicationConfig('image');
                $thumbFolder = Application_Model_Image::getThumpUploadFolder($imageConfig->menu->thumb_path);
                Globals::log("$thumbFolder Image folder has been deleted.", null, $this->_logFile);
                $output = $this->deleteAll($thumbFolder);
                Globals::log($output, null, $this->_logFile);
                
                $fileList['deleted'][] = is_array($menusetName) ? $menusetName['menuname'] : $menusetName;
                $fileCount++;
            }
            
            if ($fileCount) {
                $result['message'][] = sprintf(
                    $this->msgConfig->E600_Delete_Menusets,
                    implode(', ', $fileList['deleted'])
                );
            }
        }
        
        return $result;
    }
    
    
    /**
     * Add new menuset
     * 
     * @return boolean
     * @author Nguyen Huu Tam
     * @since 2013/02/28
     */
    public function add($postData)
    {
        // Get increment menuset
        $nextMenusetNumber = $this->_getNextMenusetNumber();
        // Save json data
        $this->setMenusetName($nextMenusetNumber, $postData['menusetName'], $postData['language']);
		// save old json data
        $this->setOldMenusetName($nextMenusetNumber, $postData['menusetName']);
        
        // Create html folder
        $newMenuset = $this->getMenusetPath($nextMenusetNumber);
        if (!mkdir($newMenuset)) {
            // Can not create diretory
            throw new Application_Model_Exception(
                sprintf($this->msgConfig->E600_CanNotCreate_Folder, $newMenuset)
            );
        }

        // Create images folder
        $newImgFolder = $newMenuset
            . DIRECTORY_SEPARATOR
            . Application_Model_Html::IMG_FOLDER;
        if (!mkdir($newImgFolder)) {
            // Can not create diretory
            throw new Application_Model_Exception(
                sprintf($this->msgConfig->E600_CanNotCreate_Folder, $newImgFolder)
            );
        }

        // Copy folder(s) and file(s)
        $menusetConfig = self::getMenusetConfig();
        $dataPath = $menusetConfig->tempData;
        self::copyFolderRecursive($dataPath, $newMenuset);
		
		$pattern = self::FOLDER_PATTERN;
		if (preg_match($pattern, $newMenuset, $matches)) {
			return $matches['menuset'];
		}
		
        return 0;
    }
    
    /**
     * Add new menuset
     * 
     * @return boolean
     * @author Nguyen Huu Tam
     * @since 2013/02/28
     */
    public function copy($postData)
    {
        // Get increment menuset
        $nextMenusetNumber = $this->_getNextMenusetNumber();
        // Save json data
        $this->setMenusetName($nextMenusetNumber, $postData['menusetName'], $this->getMenusetLanguage($postData['menusetList']));
        // Save old json data
        $this->setOldMenusetName($nextMenusetNumber, $postData['menusetName']);
        
        // copy html folder
        $newMenuset = $this->getMenusetPath($nextMenusetNumber);
        if (!mkdir($newMenuset)) {
            // Can not create diretory
            throw new Application_Model_Exception(
                sprintf($this->msgConfig->E600_CanNotCreate_Folder, $newMenuset)
            );
        }
		$oldMenuSet = $this->getMenusetPath($postData['menusetList']);
		self::copyFolderRecursive($oldMenuSet, $newMenuset);
		
        return true;
    }
    
    /**
     * Edit menuset
     * 
     * @return boolean
     * @author Nguyen Huu Tam
     * @since 2013/02/28
     */
    public function edit($postData)
    {
        if (!isset($postData['target'])
            || (empty($postData['target']))
        ) {
            // Empty menuset name
            throw new Application_Model_Exception(
                $this->msgConfig->E602_Require_Id
            );
        }
        
        // Save setting data
        $this->setMenusetName($postData['target'], $postData['menusetName'], $postData['language']);
        // Save old setting data
        $this->setOldMenusetName($postData['target'], $postData['menusetName']);
        
        return true;
    }
    
    
    /**
     * Get next menuset number
     * 
     * @return int
     * @author Nguyen Huu Tam
     * @since 2013/02/28
     */
    protected function _getNextMenusetNumber()
    {
        $menusetNumbers = array(0);
        
        $files = @scandir($this->getFolderPath());
        if ($files) {
            $pattern = array(self::FOLDER_PATTERN);
            $replace = array('$3');
            $menusetNumbers = preg_filter($pattern, $replace, $files);
        }
        if (empty($menusetNumbers)) {
            $menusetNumbers = array(0);
        } elseif (count($menusetNumbers) == 1) {
            $menusetNumbers = array(1);
        }
        
        $nextMenusetNumber = max($menusetNumbers);
        $nextMenusetNumber++;

        return $nextMenusetNumber;
    }
    
    /**
     * Get menuset directory path
     * 
     * @param type $number
     * @return string
     * @author Nguyen Huu Tam
     * @since 2013/02/28
     */
    public function getMenusetPath($number = null)
    {
        $menusetPath = $this->getFolderPath()
            . Application_Model_Html::HTML_FOLDER;
        
        if (!is_null($number) && ($number != self::getDefaultMenuset())) {
            $menusetPath .= $number;
        }
        
        return $menusetPath;
    }
    
    
    /**
     * Get current menuset name
     * 
     * @param int $postData
     * @return int
     * @author Nguyen Huu Tam
     * @since 2013/03/01
     */
    public function getCurrenMenuset($postData = null)
    {
        $session = Globals::getSession();
        $menuset = null;
        $isThrow = false;
        
        if (!empty($postData)) {
            $menuset = $postData;
            $session->menuset = $menuset;
            $isThrow = true;
        } else {
            $menuset = $session->menuset;
        }
     
        if (empty($menuset)) {
            $menuset = self::getDefaultMenuset();
            $session->menuset = $menuset;
        }

        // Check exist menuset
        if (!$this->checkMenuset($menuset, $isThrow)) {
            $session->menuset = self::getDefaultMenuset();
        }
        
        return $session->menuset;
    }
    
    
    /**
     * Check menuset folder exist
     * 
     * @param int $menuset
     * @param boolean $isThrow
     * @return boolean
     * @throws Application_Model_Exception
     * @author Nguyen Huu Tam
     * @since 2013/03/01
     */
    public function checkMenuset($menuset, $isThrow = false)
    {
        if (!is_dir($this->getMenusetPath($menuset))) {
            if ($isThrow) {
                throw new Application_Model_Exception(
                    sprintf($this->msgConfig->E600_NoExist_Menuset, $menuset)
                );
            }
            
            return false;
        }
        
        return true;
    }
    
    
    /**
     * Get list of html folders
     * 
     * @param array $sortParams (order: asc|desc)
     * @return array
     * @author Nguyen Huu Tam
     * @since 2013/02/28
     */
    public function getTreeGridData()
    {
        $list = array();
        
        // Get all menusets
        $menusets = $this->getAllMenusets();
        
        if (count($menusets)) {
            $i = 0;
            foreach ($menusets as $key => $item) {
                // Case with <html> orginal folder
                if (empty($item['menuset'])) {
                    $item['menuset'] = self::getDefaultMenuset();
                }
                
                $curMenuset = $item['menuset'];
                $htmlObj = new Application_Model_Html($curMenuset);
                $data = $htmlObj->getHtmlList(true);
                
                $list[$i] = array(
                    'id'            => $curMenuset,
                    'name'          => $this->getMenusetName($curMenuset),
                    'fullname'      => $item[0],
                    'children'      => $data['rows'],
                    'state'         => 'closed',
                    'can_del'       => $this->canDelete($curMenuset),
                    'is_menuset'    => true
                );
                $i++;
            }

            // Sort list
            array_multisort($list);
        }

        return $list;
    }

    
    /**
     * Get json file path
     * 
     * @return string
     * @author Nguyen Huu Tam
     * @since 2013/03/02
     */
    public static function getJsonFilePath()
    {
        $menusetConfig = self::getMenusetConfig();
        //return Globals::getWorkFolder() . $menusetConfig->setting->fileName;
        return Globals::getDataFilePath($menusetConfig->setting->fileName_detail);
    }
	
	/**
     * Get json old file path
     * 
     * @return string
     * @author Nguyen Huu Tam
     * @since 2013/03/02
     */
    public static function getOldJsonFilePath()
    {
        $menusetConfig = self::getMenusetConfig();
        //return Globals::getWorkFolder() . $menusetConfig->setting->fileName;
        return Globals::getDataFilePath($menusetConfig->setting->fileName);
    }

        
    /**
     * Get json data
     *
     * @return array
     * @author Nguyen Huu Tam
     * @since 2012/09/06
     */
    public function getJsonData()
    {
        $configObj = new Kdl_Ipadso_Json(self::getJsonFilePath());
        
        $setting = $configObj->getJsonConfig()->toArray();
        if (empty($setting)) {			
			//TODO: remove old menuset json after one month
			$menusetConfig = self::getMenusetConfig();
			$configOldObj = new Kdl_Ipadso_Json(self::getOldJsonFilePath());        
			$oldSetting = $configOldObj->getJsonConfig()->toArray();
			if (!empty($oldSetting)) {
				foreach ($oldSetting['list'] as $key => $value) {
					if (is_string($value)) {
						$oldSetting['list'][$key] = array('menuname' => $value, 'language' => $menusetConfig->language->defaultOption);
					}
				}
				$this->saveJsonData($oldSetting);
				return $oldSetting;
			}
			//END
			
            return $this->initJsonData();
        }
        
        return $setting;
    }
	
	/**
     * Get json data
     *
     * @return array
     * @author Nguyen Huu Tam
     * @since 2012/09/06
     */
    public function getOldJsonData()
    {
        $configObj = new Kdl_Ipadso_Json(self::getOldJsonFilePath());
        
        $setting = $configObj->getJsonConfig()->toArray();
        if (empty($setting)) {	
            return $this->initJsonData();
        }
        
        return $setting;
    }


    /**
     * Save json data
     * 
     * @param array $data
     * @author Nguyen Huu Tam
     * @since 2013/03/02
     */
    public function saveJsonData($data)
    {
        $configObj = new Kdl_Ipadso_Json(self::getJsonFilePath());
        
        $configObj->save($data);
    }
	
	/**
     * Save json data
     * 
     * @param array $data
     * @author Nguyen Huu Tam
     * @since 2013/03/02
     */
    public function saveOldJsonData($data)
    {
        $configObj = new Kdl_Ipadso_Json(self::getOldJsonFilePath());
        
        $configObj->save($data);
    }
    
    
    /**
     * Init json data
     * 
     * @author Nguyen Huu Tam
     * @since 2013/03/02
     */
    public function initJsonData()
    {
        $config = self::getMenusetConfig();
        $dataInit = array(
            'default'   => $config->setting->defaultMenuset,
            'list'      => array(
                $config->setting->defaultMenuset => $config->setting->defaultName
            )
        );

        $this->saveJsonData($dataInit);
        
        return $this->getJsonData();
    }
	
	/**
     * Init json data
     * 
     * @author Nguyen Huu Tam
     * @since 2013/03/02
     */
    public function initOldJsonData()
    {
        $config = self::getMenusetConfig();
        $dataInit = array(
            'default'   => $config->setting->defaultMenuset,
            'list'      => array(
                $config->setting->defaultMenuset => $config->setting->defaultName
            )
        );

        $this->saveOldJsonData($dataInit);
        
        return $this->getOldJsonData();
    }
    
    
    /**
     * Get menuset name
     * 
     * @param int $id
     * @return string
     * @author Nguyen Huu Tam
     * @since 2013/03/02
     */
    public function getMenusetName($id = null, $suffix = '')
    {
        if (is_null($id)) {
            $id = $this->getCurrenMenuset();
        }
        
        $data = $this->getJsonData();
        if (isset($data['list'][$id]) && is_array($data['list'][$id])
            && !empty($data['list'][$id]['menuname'])
        ) {
            $menusetName = $data['list'][$id]['menuname'];
        } else {
            $config = self::getMenusetConfig();
            $menusetName = $config->setting->defaultName . $id;
        }
        
        if (!empty($suffix)) {
            $menusetName .= " $suffix";
        }
        
        return $menusetName;
    }
    
	/**
     * Get menuset language
     * 
     * @param int $id
     * @return string
     * @author nqtrung
     * @since 2014/08/05
     */
    public function getMenusetLanguage($id = null)
    {
        if (is_null($id)) {
            $id = $this->getCurrenMenuset();
        }
        
        $data = $this->getJsonData();
        if (isset($data['list'][$id]) && is_array($data['list'][$id])
            && !empty($data['list'][$id]['language'])
        ) {
            return $data['list'][$id]['language'];
        }
        
        $config = self::getMenusetConfig();
        return $config->language->defaultOption;
    }
	
	/**
     * Get menuset no
     * 
     * @param int $id
     * @return string
     * @author nqtrung
     * @since 2014/08/05
     */
    public function getMenusetNo($id = null)
    {
        if (is_null($id)) {
            $id = $this->getCurrenMenuset();
        }
        
        $data = $this->getJsonData();
        if (isset($data['list'][$id]) && is_array($data['list'][$id])
            && !empty($data['list'][$id]['no'])
        ) {
            return $data['list'][$id]['no'];
        } else if (isset($data['list'][$id]) && is_string($data['list'][$id])) {
			return $id;
		}

        return null;
    }
    
    /**
     * Set menuset name
     * 
     * @param int $id
     * @param string $name
     * @param string $language
     * @author Nguyen Huu Tam
     * @since 2013/03/02
     */
    public function setMenusetName($id, $name, $language = null)
    {
        $data = $this->getJsonData();
		
		if (is_null($language) || strlen($language) == 0) {
			$config = self::getMenusetConfig();
			$language = $config->language->defaultOption;
		}
		$item = array('menuname' => trim($name), 'language' => $language);
        $data['list'][$id] = $item;

        $this->saveJsonData($data);
    }
	
	/**
     * Set menuset name
     * 
     * @param int $id
     * @param string $name
     * @param string $language
     * @author Nguyen Huu Tam
     * @since 2013/03/02
     */
    public function setOldMenusetName($id, $name)
    {
        $data = $this->getOldJsonData();		

        $data['list'][$id] = trim($name);

        $this->saveOldJsonData($data);
    }
    
    
    /**
     * Check menuset can delete
     * 
     * @param int $curMenuset
     * @return boolean
     * @author Nguyen Huu Tam
     * @since 2013/03/06
     */
    public function canDelete($curMenuset)
    {
        $canDelete = true;
        if ($curMenuset == self::getDefaultMenuset()) {
            $canDelete = false;
        }
        
        return $canDelete;
    }
    
    
    /**
     * Get menuset config
     * 
     * @return Zend_Config_Ini
     * @author Nguyen Huu Tam
     * @since 2013/03/06
     */
    public static function getMenusetConfig()
    {
        return Globals::getApplicationConfig('menuset');
    }
    

    
    /**
     * Get all menusets
     * 
     * @return array
     * @author Nguyen Huu Tam
     * @since 2013/03/06
     */
    public function getAllMenusets()
    {
        $list = array();
        // Get all directories and files
        $folders = @scandir($this->getFolderPath());
        
        if ($folders) {
            $pattern = self::FOLDER_PATTERN;
            foreach ($folders as $folder) {
                if (preg_match($pattern, $folder, $matches)) {
                    if (empty($matches['menuset'])) {
                        $matches['menuset'] = Application_Model_Menuset::getDefaultMenuset();
                    }
                    $list[$matches['menuset']] = $matches;
                }
            }
        }

        return $list;
    }
    
    
    /**
     * Get default menuset in config
     * 
     * @return int
     * @author Nguyen Huu Tam
     * @since 2013/03/06
     */
    public static function getDefaultMenuset()
    {
        $config = self::getMenusetConfig();
        $defaultMenuset = $config->setting->defaultMenuset;
        
        if (empty($defaultMenuset)) {
            require_once 'Zend/Config/Exception.php';
            throw new Zend_Config_Exception(
                sprintf($this->msgConfig->E200_Invalid_ConfigInfo, 'menuset', 'setting.defaultMenuset')
            );
        }
        
        return $defaultMenuset;
    }    
    
    /**
     * Update order menuset
     *
     * @param array $sortItems
     * @return boolean 
     * @author Nguyen Huu Tam
     * @since 2013/05/09
     */
    public function updateMenuOrder($sortItems)
    {
        // Get default menu list
        $menuset = $this->getList();
        $defaultList = $menuset['rows'];
        $this->_makeTempData($sortItems, $defaultList);
    }    
    
    /**
     *
     * @param array $items
     * @param array $defaultList
     * @return array 
     * @author Nguyen Huu Tam
     * @since 2013/05/09
     */
    protected function _makeTempData($items, $defaultList, $isDelete = false)
    {
        $history    = array();
        $tmpPath    = self::getTempFolder();
        $names      = array();

        if (file_exists($tmpPath)) {
            if (self::destroyFolderRecursive($tmpPath) == false) {
                throw new Application_Model_Exception(
                    sprintf("Can not delete folder: %s", $tmpPath)
                );
            }
        }

        foreach ($items as $index => $menuset) {
            // If there's not any changing
            $mainId = $defaultList[$index]['id'];
            if ($menuset == $mainId) {
                continue;
            }
            
            // Save the changing
            $history[$mainId] = $menuset;
            $names[$mainId] = ($isDelete) ? $this->getMenusetName($mainId) : $this->getMenusetName($menuset);
            $languages[$mainId] = ($isDelete) ? $this->getMenusetLanguage($mainId) : $this->getMenusetLanguage($menuset);
            
            // Thumbnail
            $thumbFolder = Application_Model_Image::getThumpFolder() . "menu";
            $tmpThumb = Globals::getTmpUploadFolder() . "menu_thumb";
            $thumbMenuPath = $thumbFolder . DIRECTORY_SEPARATOR . $menuset;
            
            // Copy thumbnail file to tmp folder
            if (file_exists($thumbMenuPath)) {
                $tmpThumbFolder = $tmpThumb . DIRECTORY_SEPARATOR . $mainId;
                if (!file_exists($tmpThumbFolder)) {
                    mkdir($tmpThumbFolder, 0777, true);
                }

                self::copyFolderRecursive($thumbMenuPath, $tmpThumbFolder, true);
            }
            
            $mainId = ($mainId == 1) ? '' : $mainId;
            $menuset = ($menuset == 1) ? '' : $menuset;
            // Rename & copy html folder to temp folder
            $htmlFolder     = $this->_folderPath . Application_Model_Html::HTML_FOLDER . "$menuset";
            $tmpName        = Application_Model_Html::HTML_FOLDER . $mainId;
            $tmpHtmlFolder  = $tmpPath . $tmpName;
            
            if (!file_exists($tmpHtmlFolder)) {
                mkdir($tmpHtmlFolder, 0777, true);
            }
            // Copy html file to tmp folder
			if (is_dir($htmlFolder)) {
				self::copyFolderRecursive($htmlFolder, $tmpHtmlFolder);
			}
        }

        // If have changing
        if (count($history)) {
            foreach ($history as $oldMenuset => $menuset) {
				if (isset($names[$menuset]) && !empty($names[$menuset])) {
					$this->setMenusetName($menuset, $names[$menuset], $languages[$menuset]);
					$this->setOldMenusetName($menuset, $names[$menuset]);
				}
                
                 // Remove old thumb
                $delThumb = $thumbFolder . DIRECTORY_SEPARATOR . $menuset;
                if (file_exists($delThumb)) {
                    if (self::destroyFolderRecursive($delThumb) == false) {
                        throw new Application_Model_Exception(
                            sprintf("Can not delete folder: %s", $delThumb)
                        );
                    }
                }
                
                $menuset = ($menuset == 1) ? '' : $menuset;
                // Remove old menusets
                $htmlFolder = $this->_folderPath . Application_Model_Html::HTML_FOLDER . $menuset;
                if (file_exists($htmlFolder)) {
                    if (self::destroyFolderRecursive($htmlFolder) == false) {
                        throw new Application_Model_Exception(
                            sprintf("Can not delete folder: %s", $htmlFolder)
                        );
                    }
                }
            }
            
            // Copy new menusets from temp folder to data folder
            self::copyFolderRecursive($tmpPath, $this->_folderPath);
            // Remove temp menusets
            if (self::destroyFolderRecursive($tmpPath) == false) {
                throw new Application_Model_Exception(
                    sprintf("Can not delete folder: %s", $htmlFolder)
                );
            }
            
            // Copy new thumb from temp folder to data folder
            if (file_exists($tmpThumb)) {
                self::copyFolderRecursive($tmpThumb, $thumbFolder);

                // Remove temp thumb
                if (self::destroyFolderRecursive($tmpThumb) == false) {
                    throw new Application_Model_Exception(
                        sprintf("Can not delete folder: %s", $tmpThumb)
                    );
                }
            }

            return true;
        }
    }

    
    /**
     * Delete folders
     * 
     * @param type $dir
     * @return boolean
     * @param string $destination
     * @author Nguyen Huu Tam
     * @since 2013/05/14
     */
    public static function destroyFolderRecursive($dir)
    {
        if (!is_dir($dir) || is_link($dir)) {
            return unlink($dir);
        }
        
        foreach (scandir($dir) as $file) {
            if ($file == '.' || $file == '..') {
                continue;
            }
            
            if (!self::destroyFolderRecursive($dir . DIRECTORY_SEPARATOR . $file)) {
                chmod($dir . DIRECTORY_SEPARATOR . $file, 0777);
                if (!self::destroyFolderRecursive($dir . DIRECTORY_SEPARATOR . $file)) {
                    return false;
                }
            }
        }
        
        return rmdir($dir);
    }
    
    
    /**
     * 
     * @param string $source
     * @param string $destination
     * @author Nguyen Huu Tam
     * @since 2013/05/10
     */
    public static function copyFolderRecursive($source, $destination, $skip = false)
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $path = $destination . $iterator->getSubPathName();
			if (substr($destination, -1) !== DIRECTORY_SEPARATOR) {
				$path = $destination . DIRECTORY_SEPARATOR . $iterator->getSubPathName();
			}
            if ($item->isDir()) {
                if (mkdir($path, 0777, true) === false) {
                    if ($skip) {
                        continue;
                    }
                    throw new Application_Model_Exception(
                        sprintf("Can not create this folder: %s", $path)
                    );
                }
            } else {
                if (copy($item, $path) === false) {
                    if ($skip) {
                        continue;
                    }
                    throw new Application_Model_Exception(
                        sprintf("Can not copy this item: %s to %s", $item, $path)
                    );
                }
            }
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
    public static function getTempFolder($tmpFolder = 'menuset')
    {
        // Create temporary folder
        $tmpPath = Globals::getTmpUploadFolder()
            . $tmpFolder
            . DIRECTORY_SEPARATOR;
        // If not exist temporary folder, then create
        if (!is_dir($tmpPath)) {
            mkdir($tmpPath, 0777, true);
        }
        return $tmpPath;
    }
    
    
    /**
     * Reset menuset order
     *
     * @param array $items
     * @return boolean 
     * @author Nguyen Huu Tam
     * @since 2013/05/14
     */
    public function resetMenuOrder($items)
    {
        $standardList = $this->_createStandardList($items);

        $this->_makeTempData($items, $standardList, true);
    }
    
    
    /**
     * Create standard menu list
     *
     * @param array $items
     * @return array 
     * @author Nguyen Huu Tam
     * @since 2013/05/14
     */
    protected function _createStandardList($items)
    {
        $result = array();
        $i = 1;
        foreach ($items as $item) {
            $result[] = array('id' => $i++);
        }
        
        return $result;
    }
    
    function deleteAll($directory, $empty = false) {
	    Globals::log("Folder has been deleted.".$directory, null, $this->_logFile);
    	if(substr($directory,-1) == "/") {
	        $directory = substr($directory,0,-1);
	    }
	
	    if(!is_dir($directory) || (!is_dir($directory) && !file_exists($directory))) {
	    	return false;
	    } elseif(!is_readable($directory)) {
	    	return false;
	    } else {
	        $directoryHandle = opendir($directory);
	        while ($contents = readdir($directoryHandle)) {
	            if($contents != '.' && $contents != '..') {
	                $path = $directory . "/" . $contents;
	               
	                if(is_dir($path)) {
	                    $this->deleteAll($path);
	                } else {
	                    unlink($path);
	                }
	            }
	        }
	       
	        closedir($directoryHandle);
	
	        if($empty == false) {
	            if(!rmdir($directory)) {
	                return false;
	            }
	        }
	       
	        return true;
	    }
	}    
}
