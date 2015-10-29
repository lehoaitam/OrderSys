<?php
/**
 * Action MenuController
 * PHP version 5.3.9
 * @author Nguyen Thi Tho
 * @copyright Kobe Digital Labo, Inc
 * @since 2012/07/06
 */

class MenuController extends Zend_Controller_Action
{
    const ADD_ACTION    = 'add';
    const EDIT_ACTION   = 'edit';
    const COPY_ACTION   = 'copy';
	const JCropImagePortraitWidth = 460;
	const JCropImageLandscapeWidth = 613;
    const DATA_SEPERATOR = ';@;';
    
    protected $_htmlFolder  = '';
    protected $_imgFolder   = '';
    protected $_noImage     = '';
    protected $_defaultTarget;
    protected $_defaultGroup = 1;

    protected $_sessionNamespace;
    protected $_isAdd;
    protected $_isEdit;
    protected $_isCopy;
    protected $_data;
    
    /**
     * Redirector - defined for code completion
     *
     * @var Zend_Controller_Action_Helper_Redirector
     */
    protected $_redirector = null;

    /**
     * Init values 
     * 
     * @return void
     * @author Nguyen Huu Tam
     * @since 2012/07/13
     */
    public function init()
    {
        $this->msgConfig    = Zend_Registry::get('MsgConfig');
        
        $this->_htmlFolder = Application_Model_Html::getHtmlFolderPath();
        $this->_imgFolder = Application_Model_Html::getImageFolderPath();
        
        $this->_tmpUploadFolder = Globals::getTmpUploadFolder();
        $this->_noImage         = Application_Model_Html::getNoImage();
        $this->_defaultTarget   = Application_Model_Html::getDefaultTarget();
        
        $flashMsg = $this->_helper->getHelper('flashMessenger');
        if ($flashMsg->hasMessages()) {
            $this->view->message = $flashMsg->getMessages();
        }
        
        // 商品一覧
        $this->view->listWidth = 420;        
        
        $this->view->notice             = $this->_getNoticeMessages();
		$this->view->urlAllList			= '/menu/all-list';
        $this->view->urlProductList		= '/menu/product-list';
        $this->view->urlLinkList		= '/menu/link-list';
        $this->view->urlVideoList		= '/menu/video-list';
        $this->view->urlMenuOthersList  = '/menu/menu-others-list';
        $this->view->urlUpdateProduct   = '/menu/update-product';
        $this->view->urlCheckHtmlFile   = '/menu/check-htmlfile';
        
        $this->loadSession();
        
        $this->_redirector = $this->_helper->getHelper('Redirector');
    }
    
    /**
     * Load session
     * 
     * @return void
     * @author Nguyen Huu Tam
     * @since 2012/07/26
     */
    public function loadSession()
    {
        if (isset($this->getSessionNs()->isAdd)) {
            $this->_isAdd = $this->getSessionNs()->isAdd;
        } else {
            $this->_isAdd = false;
        }
        
        if (isset($this->getSessionNs()->isEdit)) {
            $this->_isEdit = $this->getSessionNs()->isEdit;
        } else {
            $this->_isEdit = false;
        }
        
        if (isset($this->getSessionNs()->isCopy)) {
            $this->_isCopy = $this->getSessionNs()->isCopy;
        } else {
            $this->_isCopy = false;
        }
        
        if (isset($this->getSessionNs()->data)) {
            $this->_data = $this->getSessionNs()->data;
        }
        
        $this->persist();
    }
    
    /**
     * Save session values
     * 
     * @return void
     * @author Nguyen Huu Tam
     * @since 2012/07/26
     */
    public function persist()
    {
        $this->getSessionNs()->isAdd    = $this->_isAdd;
        $this->getSessionNs()->isEdit   = $this->_isEdit;
        $this->getSessionNs()->isCopy   = $this->_isCopy;
        $this->getSessionNs()->data     = $this->_data;
    }
    
    /**
     * Get session namespace
     * 
     * @return void
     * @author Nguyen Huu Tam
     * @since 2012/07/26
     */
    public function getSessionNs()
    {
        if (null === $this->_sessionNamespace) {
            $this->setSessionNs(new Zend_Session_Namespace(__CLASS__));
        }
        return $this->_sessionNamespace;
    }

        
    /**
     * Set session namespace
     * 
     * @return void
     * @author Nguyen Huu Tam
     * @since 2012/07/26
     */
    public function setSessionNs(Zend_Session_Namespace $ns)
    {
        $this->_sessionNamespace = $ns;
    }

    /**
     * Destroy all session 
     */
    public function destroySession()
    {
        $session = $this->getSessionNs();
        $session->unsetAll();
    }
    
    /**
     * List of html files with name is number
     * 
     * @return json data
     * @author Nguyen Huu Tam
     * @since 2012/07/31
     */
    public function linkVideoListAction()
    {
		$postData = $this->getRequest()->getParams();
		
        $htmlObj = new Application_Model_Html();
        $links = array();
		$videoObj = new Application_Model_Video();
        $videos = array();
        if (isset($postData['q']) && (!empty($postData['q']))) {
            $links = $htmlObj->getFilteredList($postData['q']);
			$videos = $videoObj->getFilteredList($postData['q']);
        } else {
            $links = $htmlObj->getFilteredList();
			$videos = $videoObj->getFilteredList();
        }
		
		// Sort links
        asort($links);
		$keySort = array('id', 'name');
		if (!isset($postData['sort'])) {
			$postData['sort'] = 'name';
			$postData['order'] = 'asc';
		}
		foreach ($keySort as $key) {
			if (array_keys($postData, $key)) {
				if ($postData['order'] == 'asc') {
					usort($links, function($a, $b) use ($key) {
						return strnatcmp($a[$key], $b[$key]);
					});
				} else {
					usort($links, function($a, $b) use ($key) {
						return strnatcmp($b[$key], $a[$key]);
					});
				}
				break;
			}
		}

        $csvData = array_merge($links, $videos);
	
        $rows['total']= count($csvData);
        $rows['rows'] = $csvData;

        $this->_helper->json($rows);
    }
	
    /**
     * List of html files with name is number
     * 
     * @return json data
     * @author Nguyen Huu Tam
     * @since 2012/07/31
     */
    public function linkListAction()
    {
		$postData = $this->getRequest()->getParams();
		
        $htmlObj = new Application_Model_Html();
        $links = array();
        if (isset($postData['q']) && (!empty($postData['q']))) {
            $links = $htmlObj->getFilteredList($postData['q']);
        } else {
            $links = $htmlObj->getFilteredList();
        }
	
        $rows['total']= count($links);
        $rows['rows'] = $links;

        $this->_helper->json($rows);
    }
	
    /**
     * List of html files with name is number
     * 
     * @return json data
     * @author Nguyen Huu Tam
     * @since 2012/07/31
     */
    public function videoListAction()
    {
		$postData = $this->getRequest()->getParams();

		$videoObj = new Application_Model_Video();
        $videos = array();
        if (isset($postData['q']) && (!empty($postData['q']))) {
			$videos = $videoObj->getFilteredList($postData['q']);
        } else {
			$videos = $videoObj->getFilteredList();
        }
		
		// Sort links
        asort($videos);
		$keySort = array('name');
		if (!isset($postData['sort'])) {
			$postData['sort'] = 'name';
			$postData['order'] = 'asc';
		}
		foreach ($keySort as $key) {
			if (array_keys($postData, $key)) {
				if ($postData['order'] == 'asc') {
					usort($videos, function($a, $b) use ($key) {
						return strnatcmp($a[$key], $b[$key]);
					});
				} else {
					usort($videos, function($a, $b) use ($key) {
						return strnatcmp($b[$key], $a[$key]);
					});
				}
				break;
			}
		}

        $rows['total']= count($videos);
        $rows['rows'] = $videos;

        $this->_helper->json($rows);
    }
	
	/**
     * List of html files with name is number
     * 
     * @return json data
     * @author nqtrung
     * @since 2014/08/04
     */
    public function menuOthersListAction()
    {
        $htmlObj = new Application_Model_Html();
        $links = $htmlObj->getMenuOthersList();		
			
        $rows['total']= count($links);
        $rows['rows'] = $links;

        $this->_helper->json($rows);
    }
    
    /**
     * List action
     * 
     * @return void
     * @author Nguyen Huu Tam
     * @since 2012/07/13
     */
    public function indexAction()
    {
        $this->_redirect('/menuset');
    }
    
    /**
     * Init session
     * 
     * @return void
     * @author Nguyen Huu Tam
     * @since 2012/07/26
     */
    protected function _initSession($function)
    {
        switch ($function) {
            case self::ADD_ACTION:
                $this->_isAdd   = true;
                $this->_isEdit  = false;
                $this->_isCopy  = false;
                break;
            case self::EDIT_ACTION:
                $this->_isAdd   = false;
                $this->_isEdit  = true;
                $this->_isCopy  = false;
                break;
            case self::COPY_ACTION:
                $this->_isAdd   = false;
                $this->_isEdit  = false;
                $this->_isCopy  = true;
                break;
        }
        
        $this->_data = null;
        
        $this->persist();
    }

    /**
     * Preview html file
     * 
     * @return: void
     * @author Nguyen Huu Tam
     * @since 2012/07/13
     */
    public function previewAction()
    {
        try {
            // Attach js file
            $this->view->headScript()->offsetSetFile(10, (Globals::isMobile() ? '/sp' : '/pc') . '/js/jquery-1.7.2.min.js');
            $this->_helper->layout->disableLayout();
            
            $htmlModel = new Application_Model_Html();
            $getData = $this->getRequest()->getParams();

            if (isset($getData['name'])) {
                $this->view->menuset = (isset($getData['menuset'])) ? $getData['menuset'] : null;
                $this->view->html = $htmlModel->toHtml($getData['name'], $this->view->menuset);
            } else {
                $data = $this->getSessionNs()->data;
                $data['is_preview'] = true;
                $session = Globals::getSession();
                $this->view->menuset = $session->menuset;
                $return = $htmlModel->makePreviewHtml($data);
              
                if ($return) {
                    $this->view->html = $return;
                } else {
                    $this->_helper->flashMessenger->addMessage($this->msgConfig->N404_Empty_Data);
                    $this->_redirect('/menu');
                }
            }
            
        } catch (Exception $e) {
            Globals::logException($e);
            $this->_helper->flashMessenger->addMessage($e->getMessage());
            
            $this->_redirect('/menu');
        }
    }
    
    /**
     * Download image from the folder that is defined
     * 
     * @return: void
     * @author Nguyen Huu Tam
     * @since 2012/07/16
     */
    public function imageAction()
    {
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender();
        
        $getData = $this->getRequest()->getParams();
        
        $filePath = '';
        if (isset($getData['name'])) {
            // Get image path
            if ($this->getRequest()->getParam('temp')) {
                $filePath = Globals::getTmpUploadFolder() . $getData['name'];
            } else {
                $menuset = '';
                if (isset($getData['menuset'])) {
                    $menuset = $getData['menuset'];
                }
                $filePath = $this->getFilePath(
                    $getData['name'],
                    $menuset,
                    Application_Model_Html::IMG_FOLDER
                );
            }
        }

        if (file_exists(realpath($filePath))) {
            // Response image to browser image
            $image = file_get_contents($filePath);
            $dimension = Application_Model_Html::getImageDimension($filePath);
            $this->getResponse()->clearBody();
            $this->getResponse()->setHeader('Content-Type', $dimension['mime'], true);
            $this->getResponse()->setBody($image);
        } else {
            Globals::log(sprintf($this->msgConfig->E401_FileNotFound, $filePath));
            $this->_helper->flashMessenger->addMessage(
                sprintf($this->msgConfig->E401_FileNotFound, $filePath)
            );
        }
    }
    
    /**
     * Delete html files
     * 
     * @return: void
     * @author Nguyen Huu Tam
     * @since 2012/07/16
     */
    public function deleteAction()
    {
        // Check Csrf
        $this->_helper->csrf->checkCsrf($this->getRequest());
        $session = Globals::getSession();
		
        $id = $this->getRequest()->getParam('id');
		if (strtolower($id) == strtolower(Application_Model_Html::TOPVIEW_NAME)) {
			$this->_redirect('/menuset');
		}
			
        if (!empty($id)) {
			$session->success = 0;
            $htmlObj = new Application_Model_Html();
            // Remove menu items
            $result = $htmlObj->deleteMenu($id);
            
            // If has error
            if (isset($result['error'])) {
                foreach ($result['error'] as $msg) {
                    $this->_helper->flashMessenger->addMessage($msg);
                }
                $this->_redirect('/menu/edit');
            }
            
            if (isset($result['message'])) {
                foreach ($result['message'] as $msg) {
                    $this->_helper->flashMessenger->addMessage($msg);
                }
            }
			
			// Get menu order list
            $items = $session->menu_order_list;
            // Remove all deleted item(s)
            $items = array_diff($items, array($id));
            // Reset index of array
            $items = array_values($items);
            
            $deleteIds = $htmlObj->resetMenuOrder($items);
            if ($deleteIds !== false) {
                $htmlObj->deleteMenu($deleteIds);
            }
            
            // Pack data            
            DataPacker::packDataInBatch($session->company_link);
			$session->success = 1;
        }
        
        $this->_redirect('/menuset/edit');
    }    

    /**
     * Get file path
     * 
     * @return: void
     * @author Nguyen Huu Tam
     * @since 2012/07/16
     */
    protected function getFilePath($fileName, $menuset = '', $directory = 'html')
    {
        $filePath = '';
        switch ($directory) {
            case Application_Model_Html::HTML_FOLDER:
                $filePath = Application_Model_Html::getHtmlFolderPath($menuset) . "{$fileName}.html";
                break;
            case Application_Model_Html::IMG_FOLDER:
                $filePath = Application_Model_Html::getImageFolderPath($menuset) . $fileName;
                break;
        }

        return $filePath;
    }
	
    /**
     * @action: get data to view on the list
     * @return: data has json style
     */
    public function allListAction()
    {
        $indexModel = new Application_Model_Index();
		$htmlObj = new Application_Model_Html();
		$videoObj = new Application_Model_Video();

        $indexModel->fetchAll();
		$links = $htmlObj->getFilteredList();
		$videos = $videoObj->getFilteredList();
		$menuOthers = $htmlObj->getMenuOthersList();
        
        $data = $indexModel->getData();		
        $count = count($data);

        $rows['total']= $count;
        $csvData = array();
       
        if ($count) {
            foreach ($data as $row) {
                if ($row['isComment'] != 1
                    && $row['isSub'] != 1
                    && $row['isSet'] != 1
                   ) {
                    $csvData[] = array('id' => $row['menuCode'], 'code' => $row['menuCode'], 'name' => $row['itemName'], 'type' => 'product');
                }
            }
        }		

        $csvData = array_merge($csvData, $links, $videos, $menuOthers);
		
        $rows['total']= count($csvData);
        $rows['rows'] = $csvData;

        $this->_helper->json($rows);
    } 	
    
    /**
     * @action: get data to view on the list
     * @return: data has json style
     */
    public function productListAction()
    {
        $postData = $this->getRequest()->getParams();
        $indexModel = new Application_Model_Index();

        if (isset($postData['q']) && (!empty($postData['q']))) {
            $condition = array('itemName' => array('like' => $postData['q']));
            $indexModel->find($condition);
        } else {
            $indexModel->fetchAll();
        }
        
        $data = $indexModel->getData();
		// Sort product
        Application_Model_Entity::natksort($data);
		$keySort = array('code' => 'menuCode', 'name' => 'itemName');
		foreach ($keySort as $key => $value) {
			if (array_keys($postData, $key)) {
				if ($postData['order'] == 'asc') {
					usort($data, function($a, $b) use ($value) {
						return strnatcmp($a[$value], $b[$value]);
					});
				} else {
					usort($data, function($a, $b) use ($value) {
						return strnatcmp($b[$value], $a[$value]);
					});
				}
				break;
			}
		}
		
        $count = count($data);

        $rows['total']= $count;
        $csvData = array();
       
        if ($count) {
            foreach ($data as $row) {
                if ($row['isComment'] != 1
                    && $row['isSub'] != 1
                    && $row['isSet'] != 1
                   ) {
                    // 2015/02/19 Nishiyama Add エスケープ追加
                    $csvData[] = array('id' => $row['menuCode'], 'code' => $row['menuCode'], 'name' => htmlspecialchars($row['itemName']), 'type' => 'product');

                }
            }
        }		
		
        $rows['total']= count($csvData);
        $rows['rows'] = $csvData;

        $this->_helper->json($rows);
    } 	

    /**
     * Upload image
     * 
     * @return: void
     * @author Nguyen Huu Tam
     * @since 2012/07/16
     */
    protected function _uploadImage()
    {
        try {
            // Upload new image
            $htmlModel = new Application_Model_Html();
            $image = $htmlModel->uploadImage();

            if ($image) {
                $this->_data['image'] = $image;
                $this->persist();
            } else {
                $errorMessages = $htmlModel->getErrorMessages();
                
                if (!empty($errorMessages)) {
                    foreach ($errorMessages as $msg) {
                        $this->_helper->flashMessenger->addMessage(array('image_file' => $msg));
                    }
                }
            }
                    
        } catch (Exception $e) {
            $this->_helper->flashMessenger->addMessage(array('image_file' => $e->getMessage()));
            Globals::logException($e);
        }
    }

        
    /**
     * Add new menu action
     * 
     * @return: void
     * @author Nguyen Huu Tam
     * @since 2012/07/16
     */
    public function addAction()
    {
        try {
            // Init Csrf
            $this->view->csrf = $this->_helper->csrf->initInput();
			$session = Globals::getSession();

            // Init session for add action if not exist
            if (!isset($this->getSessionNs()->isAdd)
                || ($this->getSessionNs()->isAdd == false)
            ) {
                $this->_initSession(self::ADD_ACTION);
            }

            $menuObj = new Application_Model_Html();
            $this->view->pageNumber = $menuObj->getNextPageNumber();
            $menuConfig = Globals::getApplicationConfig('menu');
            if ($this->view->pageNumber > $menuConfig->maxPage) {
                $this->_helper->flashMessenger->addMessage(
                    sprintf($this->msgConfig->N401_MaxPage_Notice, $menuConfig->maxPage)
                );
				
                $this->_redirect('/menuset/edit');
            }

            // Attach js file
            $this->view->headScript()->offsetSetFile(12, (Globals::isMobile() ? '/sp' : '/pc') . '/js/menu-init.js');

            // Get messages
            $flashMsg = $this->_helper->getHelper('flashMessenger');
            if ($flashMsg->hasMessages()) {
                $this->view->message = $flashMsg->getMessages();
            }

            $formData = $this->_helper->getHelper('formData');
            if ($formData->hasData()) {
                $this->view->data = $formData->getData();
            }

            $dataSession = $this->getSessionNs()->data;
            if (isset($dataSession['image'])
                && ($dataSession['image'] != null)
            ) {
                $this->view->imageHtml      = $dataSession['image']['html'];
                $this->view->imageName      = $dataSession['image']['name'];
                $this->view->imageUrl       = $dataSession['image']['url'];
                $this->view->imageMaxTarget = $dataSession['image']['max_dimension'];				
				
            } else {
                $this->view->imageHtml  = $this->_noImage;
                $this->view->imageName  = '';
                $this->view->imageUrl   = '';
                $this->view->imageMaxTarget = '';

                $this->_data['image'] = null;
                $this->persist();
            }

			$this->view->JCropWidth = $this->getJCropWidthFromUrl($this->view->imageUrl);

			if (isset($dataSession['products'])
                && ($dataSession['products'] != null)
            ) {
				$this->view->lastSelectedArea = $dataSession['products'];
			} else {
				if (isset($dataSession['saveSelectedAreas']) && isset($dataSession['selectedAreas'])) {
					$this->view->lastSelectedArea = $dataSession['selectedAreas'];
					
					unset($dataSession['saveSelectedAreas']);
					$this->_data = $dataSession;
					$this->persist();
				} else {
					$this->view->lastSelectedArea = array();
				}
			}

            $menusetObj = new Application_Model_Menuset();
            $this->view->menuset = $menusetObj->getMenusetName();
            $this->view->success = $session->success;
        } catch (Application_Model_Exception $e) {
            $this->_helper->flashMessenger->addMessage($e->getMessage());
            Globals::logException($e);
            
            $this->_redirect('/menuset');
        } catch (ErrorException $e) {
            Globals::logException($e);
        }
    }	
	
	/**
     * get jcrop width from an url
     * 
     * @return: boolean
     * @author Nguyen Quang Trung
     * @since 2014/05/15
     */
	protected function getJCropWidthFromUrl($url) {
		$this->view->listHeight = 605;
		
		$array = explode('/', $url);
		$params = array();
		for ($i = 1; $i < count($array) - 1; $i += 2) {
			$params[$array[$i]] = $array[$i + 1];
		}
		
		if (!isset($params['name']) || empty($params['name'])) {
			return self::JCropImagePortraitWidth;
		}
		$path = '';
		if (isset($params['temp'])) {
			$path = Globals::getTmpUploadFolder() . $params['name'];
		} else {
			$path = $this->getFilePath(
                    $params['name'],
                    $params['menuset'],
                    Application_Model_Html::IMG_FOLDER
                );
		}
		if (!file_exists($path)) {
			return self::JCropImagePortraitWidth;
		}
		$size = getimagesize($path);
		if ($size[0] <= $size[1]) {
			return self::JCropImagePortraitWidth;
		} else {
			$this->view->listHeight = 453;
			return self::JCropImageLandscapeWidth;
		}
	}
	
	/**
     * check image exist from an url
     * 
     * @return: boolen
     * @author Nguyen Quang Trung
     * @since 2014/05/20
     */
	function imageExists($url)
	{
		$array = explode('/', $url);
		$params = array();
		for ($i = 1; $i < count($array) - 1; $i += 2) {
			$params[$array[$i]] = $array[$i + 1];
		}
		
		$path = '';
		if (isset($params['temp'])) {
			$path = Globals::getTmpUploadFolder() . $params['name'];
		} else {
			$path = $this->getFilePath(
                    $params['name'],
                    $params['menuset'],
                    Application_Model_Html::IMG_FOLDER
                );
		}
		if (!file_exists($path)) {
			return false;
		}
		return true;
	}

	/**
     * Init notice message for js script
     * 
     * @return: array
     * @author Nguyen Huu Tam
     * @since 2012/07/16
     */
    protected function _getNoticeMessages()
    {
        $messages = array();

        $messages['add_menu']       = $this->msgConfig->N401_AddMenu;
        $messages['edit_menu']      = $this->msgConfig->N402_EditMenu;
        $messages['delete_menu']    = $this->msgConfig->N402_DelMenu;
        $messages['copy_menu']      = $this->msgConfig->N403_CopyMenu;
        $messages['remove_product'] = $this->msgConfig->N401_RemoveProduct;
        $messages['empty_menu']     = $this->msgConfig->E400_RequireSelect_Product;
        $messages['empty_video']    = $this->msgConfig->E400_RequireSelect_Video;
        
        $messages['empty_link']     = $this->msgConfig->E404_RequireSelect_Link;
        $messages['edit_topview']   = $this->msgConfig->N404_EditTopView;
        $messages['remove_link']    = $this->msgConfig->E404_Remove_Link;
        $messages['require_link']   = $this->msgConfig->E404_RequireSelect_Link;
        $messages['del_category']   = $this->msgConfig->E404_Delete_Category;
        
        $menuConfig = Globals::getApplicationConfig('menu');
        $messages['max_page'] = sprintf($this->msgConfig->N401_MaxPage_Notice, $menuConfig->maxPage);
        
        $imageConfig = Globals::getApplicationConfig('image');
        $messages['image_upload'] = vsprintf(
            $this->msgConfig->N401_ImageUpload_Notice,
            array(
                $imageConfig->menu->min_width,
                $imageConfig->menu->width,
                $imageConfig->menu->height                
            )
        );
        
        return $messages;
    }

    /**
     * Validate post data
     * 
     * @return: array
     * @author Nguyen Huu Tam
     * @since 2012/07/16
     */
    protected function _validate($data)
    {
        $errors =  array();
        $sessionData = $this->getSessionNs()->data;
        // ページ番号
        if (!Zend_Validate::is($data['page_number'], 'NotEmpty')) {
            $errors[] = $this->msgConfig->E401_Require_PageNumber;
        } else {
            if (!Zend_Validate::is($data['page_number'], 'Digits')) {
                $errors[] = $this->msgConfig->E401_NumberOnly_PageNumber;
            }
        }

        // 背景画像
        if (empty($sessionData['image'])
            || ($sessionData['image']['html'] == $this->_noImage)
        ) {
            $errors[] = array('image_file' => $this->msgConfig->E401_Require_Image);
        }
        
        // 商品
        //if (empty($sessionData['products'])) {
            //$errors[] = $this->msgConfig->E401_Require_Product;
        //}
        
        return $errors;
    }
    
    /**
     * Validate top view post data
     * 
     * @return: array
     * @author Nguyen Huu Tam
     * @since 2012/07/31
     */
    protected function _validateTopView()
    {
        $errors =  array();
        $sessionData = $this->getSessionNs()->data;

        // 背景画像
        if (empty($sessionData['image']['name'])
            || ($sessionData['image']['html'] == $this->_noImage)
        ) {
            $errors[] = array('image_file' => $this->msgConfig->E401_Require_Image);
        }
        
        // リンク
        //if (empty($sessionData['links'])) {
            //$errors[] = $this->msgConfig->E401_Require_Link;
        //}
        
        return $errors;
    }
    
    public function doAddAction()
    {
        // Check Csrf
        $this->_helper->csrf->checkCsrf($this->getRequest());
        $session = Globals::getSession();
		$session->success = 0;
        $postData = $this->getRequest()->getParams();
        $this->_helper->formData->addData($postData);

        // Upload new image
        if (isset($postData['is_upload'])
            && (!empty($postData['is_upload']))
        ) {
            $this->_uploadImage();
			
			$this->_data['saveSelectedAreas'] = 1;
            $this->persist();
			
            $this->_redirect('/menu/add');
        }
        
        $errors = $this->_validate($postData);
        if (count($errors)) {
            foreach ($errors as $error) {
                $this->_helper->flashMessenger->addMessage($error);
            }
            $this->_redirect('/menu/add');
        }
        
        $dataSession = $this->getSessionNs()->data;
        $postData['image']      = $dataSession['image']['name'];
        
		$postData = array_merge($postData, $this->convertToOldDataFormat($dataSession['selectedAreas']));

        $htmlModel = new Application_Model_Html();
        if ($htmlModel->makeHtmlFile($postData)) {
            $this->_helper->flashMessenger->addMessage($this->msgConfig->N401_SaveSuccess_Menu);
            // Pack data
            DataPacker::packDataInBatch($session->company_link);
			$this->destroySession();
			$session->success = 1;
        } else {
            $this->_helper->flashMessenger->addMessage($this->msgConfig->E401_SaveError_Menu);
			$this->_redirect('/menu/add');
        }
        
        $this->_redirect('/menu/edit/name/' . $postData['page_number']);
    }
	
	/**
	 * convert new session data to old format
     * 
     * @return array
     * @author Nguyen Quang Trung
     * @since 2014/05/14
	 */
	private function convertToOldDataFormat($newData, $isTopView = false) {
		$data['videos'] = array();
		
		$links = array();
		$products = array();
		$videos = array();
		foreach ($newData as $group => $value) {
			$target = explode(',', $value['target']);
			if (isset($value['linkIds']) && strlen($value['linkIds']) > 0) {
				$linkIds = explode(self::DATA_SEPERATOR, $value['linkIds']);				
				foreach ($linkIds as $linkId) {
					$links[$linkId] = array('menuCode' => $linkId, 'group' => $group, 'x' => $target[0], 'y' => $target[1], 'w' => $target[2], 'h' => $target[3]);
				}
			} else if (isset($value['productIds']) && strlen($value['productIds']) > 0) {
				$productIds = explode(self::DATA_SEPERATOR, $value['productIds']);				
				foreach ($productIds as $menuCode) {
					$products[] = array('menuCode' => $menuCode, 'group' => $group, 'x' => $target[0], 'y' => $target[1], 'w' => $target[2], 'h' => $target[3]);
				}
			} else if (isset($value['videoIds']) && strlen($value['videoIds']) > 0) {
				$videoIds = explode(self::DATA_SEPERATOR, $value['videoIds']);
				foreach ($videoIds as $name) {
					$videos[] = array('name' => $name, 'x' => $target[0], 'y' => $target[1], 'w' => $target[2], 'h' => $target[3]);
				}
			}			
		}
		
		if ($isTopView) {
			$data['links'] = $links;
		}
		if (!$isTopView) {
			$data['products'] = $products;
		}
		$data['videos'] = $videos;
		return $data;
	}
	
	/**
	 * convert old session data format to new format
     * 
     * @return array
     * @author Nguyen Quang Trung
     * @since 2014/05/14
	 */
	private function convertToNewDataFormat($oldData, $isTopView = false) {
		$group = 1;
		$data = array();		
		
		$products = array();
		if (!$isTopView) {
			$indexModel = new Application_Model_Index();
			$indexModel->fetchAll();
			$products = $indexModel->getData();
		}
		$link = array();
		$htmlObj = new Application_Model_Html();
		$links = $htmlObj->getFilteredList();
		$menuOthers = $htmlObj->getMenuOthersList();

		foreach ($oldData['items'] as $item) {
			if (strlen($item['menuCode']) == 0) {
				continue;
			}
			$tmp = explode(',', $item['menuCode']);
			$ids = array();
			$names = array();
			$types = array();

			foreach ($tmp as $id) {
				$ids[] = $id;
				$found = false;
				foreach ($products as $product) {
					if ($id === $product['menuCode']) {
						$found = true;
						$names[] = $product['itemName'];
						$types[] = 'product';
						break;
					}
				}
				if  (!$found) {
					foreach ($links as $link) {
						if ($id === $link['id']) {
							$found = true;
							$names[] = $link['name'];
							$types[] = 'link';
							break;
						}
					}
				}
				if  (!$found) {
					foreach ($menuOthers as $menu) {
						if ($id === $menu['id']) {
							$found = true;
							$names[] = $menu['name'];
							$types[] = 'menu_others';
							break;
						}
					}
				}
				if (!$found) {
					$names[] = '';
				}
			}

			if ($isTopView) {
				$data[$group] = array('linkIds' => implode(self::DATA_SEPERATOR, $ids), 'linkNames' => implode(self::DATA_SEPERATOR, $names), 'productTypes' => implode(self::DATA_SEPERATOR, $types),
					'videoIds' => '', 'target' => $item['x'] . ',' . $item['y'] . ',' . $item['w'] . ',' . $item['h']);
			} else {
				$data[$group] = array('productIds' => implode(self::DATA_SEPERATOR, $ids), 'productNames' => implode(self::DATA_SEPERATOR, $names), 'productTypes' => implode(self::DATA_SEPERATOR, $types),
					'videoIds' => '', 'target' => $item['x'] . ',' . $item['y'] . ',' . $item['w'] . ',' . $item['h']);
			}
			$group++;
		}
		foreach ($oldData['videos'] as $video) {
			if ($isTopView) {
				$data[$group] = array('linkIds' => '', 'linkNames' => '', 'productTypes' => 'video',
					'videoIds' => $video['name'], 'target' => $video['x'] . ',' . $video['y'] . ',' . $video['w'] . ',' . $video['h']);
			} else {
				$data[$group] = array('productIds' => '', 'productNames' => '', 'productTypes' => 'video',
					'videoIds' => $video['name'], 'target' => $video['x'] . ',' . $video['y'] . ',' . $video['w'] . ',' . $video['h']);
			}
			$group++;
		}
		
		if (isset($oldData['selectedAreas']) && is_array($oldData['selectedAreas'])) {
			$data = array_merge($data, $oldData['selectedAreas']);
		}
		
		return $data;
	}    
    
    /**
     * Get next product id
     * 
     * @return int
     * @author Nguyen Huu Tam
     * @since 2013/02/19
     */
    protected function _getNextProductId()
    {
        $id = 0;
        $sessionData = $this->getSessionNs()->data;
        if (isset($sessionData['products'])) {
            $id = max(array_keys($sessionData['products']));
        }
        
        return ($id + 1);
    }
    
    
    /**
     * Update product list
     * 
     * @return: void
     * @author Nguyen Huu Tam
     * @since 2012/07/16
     */
    public function updateProductAction()
    {
        try {
            $this->_helper->layout->disableLayout();
            $this->_helper->viewRenderer->setNoRender();

            $postData = $this->getRequest()->getParams();

            // Get session data
            $sessionData = $this->getSessionNs()->data;
            // Product data
            $productData = array();
            if (isset($sessionData['selectedAreas'])) {
                $productData = $sessionData['selectedAreas'];
            }

            // UPDATE PRODUCT TARGET (x,y,w,h)
            if ($postData['update-group'] === '0' && isset($postData['update-product-ids'])
                && ((!empty($postData['update-product-ids'])) || (!empty($postData['update-video-ids'])))
            ) {				
				$productData[$postData['group']] = array('productIds' => $postData['update-product-ids'], 
													'productNames' => $postData['update-product-names'],
													'productTypes' => $postData['update-product-types'],
													'videoIds' => $postData['update-video-ids'],
													'target' => $postData['target']);
            } else if ($postData['update-group'] !== '0') {
				$productData[$postData['group']]['target'] = $postData['target'];
			}
            
            // REMOVE PRODUCT
            if (!empty($postData['del-product'])) {
                unset($productData[$postData['del-product']]);
            }

            // Save session data
            $this->_data['selectedAreas']    = $productData;
            $this->persist();
            
        } catch (ErrorException $e) {
            Globals::logException($e);
        }
    }    

    /**
     * Check existed html file
     * 
     * return json
     * @author Nguyen Huu Tam
     * @since 2012/07/23 
     */
    public function checkHtmlfileAction()
    {
        $response = array();
        $data = $this->getRequest()->getParams();

        if (isset($data['page_number'])
            && (!empty($data['page_number']))
        ) {
            $htmlModel = new Application_Model_Html();
            $result = $htmlModel->checkHtmlFile($data['page_number']);
            
            if ($result['exist']) {
                $response = array('message' => $this->msgConfig->N401_PageExist);
            }
        }

        $this->_helper->json($response);
    }    
   
    /**
     * 
     * 
     * @param array $data
     * @param boolean $isTopView
     * @return array
     */
    protected function _prepareData($data, $isTopView = FALSE)
    {
        $messages = array();
        $htmlObj = new Application_Model_Html();
        $this->_data = $data;

        $this->_data['pageName'] = $data['page_number'];
        
        if ($isTopView === true) {
            $itemsData = $htmlObj->getLinkInfo($data['items']);
            $this->_data['links'] = $itemsData['items'];
            if (count($itemsData['errors'])) {
                $messages[] = sprintf(
                    $this->msgConfig->E400_NotExist_Page,
                    implode(', ', $itemsData['errors'])
                );
            }
        } else {
            $itemsData = $htmlObj->getProductInfo($data['items']);
            $this->_data['products'] = $itemsData['items'];
            $this->_data['ids'] = $itemsData['ids'];
            if (count($itemsData['errors'])) {
                $messages[] = sprintf(
                    $this->msgConfig->E400_NotExist_Product,
                    "{$data['page_number']}.html",
                    implode(', ', $itemsData['errors'])
                );
            }
        }
    
        $image = array();
        $image['html'] = $this->_noImage;
        
        if (isset($data['image'])) {
            $image['name']          = $data['image']['name'];
            $image['url']           = $data['image']['url'];
            $image['max_dimension'] = $data['image']['max_dimension'];

            if (isset($data['image']['html'])) {
                $image['html'] = $data['image']['html'];
            }
        } else {
            //$messages[] = $this->msgConfig->E000_ImageNotFound;
            $image['name']          = '';
            $image['url']           = '';
            $image['max_dimension'] = '';
        }

        $this->_data['image'] = $image;
        $this->persist();
        
        return $messages;
    }


    public function editAction()
    {
        try {
            // Init Csrf
            $this->view->csrf = $this->_helper->csrf->initInput();
            $session = Globals::getSession();
			
            $fileName = $this->getRequest()->getParam('name');		
		
            if (strtolower($fileName) == strtolower(Application_Model_Html::TOPVIEW_NAME)) {
                $this->_redirect('/menu/edit-top-view');
            }

            // Init session
            if (!isset($this->getSessionNs()->isEdit)
                || ($this->getSessionNs()->isEdit == false)
            ) {
                $this->_initSession(self::EDIT_ACTION);
            }

            // Include js file
            $this->view->headScript()->offsetSetFile(12, (Globals::isMobile() ? '/sp' : '/pc') . '/js/menu-init.js');
            
            if (empty($fileName)) {
                $dataSession = $this->getSessionNs()->data;
                $fileName = $dataSession['page_number'];
            }

            $flashMsg = $this->_helper->getHelper('flashMessenger');
            if ($flashMsg->hasMessages()) {
                $this->view->message = $flashMsg->getMessages();
            }
            
            $dataSession = $this->getSessionNs()->data;
            $messages = array();
            $formData = $this->_helper->getHelper('formData');
            if ($formData->hasData() && isset($session->success) && $session->success != 1) {
				if (isset($dataSession['saveSelectedAreas'])) {
					$this->view->lastSelectedArea = $dataSession['selectedAreas'];
				}
                $this->view->data = $formData->getData();
            } else {
                $htmlObj = new Application_Model_Html();
                $data = $htmlObj->getHtmlFileInfo($fileName);
				$data['selectedAreas'] = $this->convertToNewDataFormat($data);
				$this->view->lastSelectedArea = $data['selectedAreas'];
                $this->view->data = $data;
				$dataSession = $data;
                 
                $messages = $this->_prepareData($data);
            }
            
			if ($this->imageExists($dataSession['image']['url'])) {
				$this->view->imageHtml      = $dataSession['image']['html'];
				$this->view->imageName      = $dataSession['image']['name'];
				$this->view->imageUrl       = $dataSession['image']['url'];
				$this->view->imageMaxTarget = $dataSession['image']['max_dimension'];
			} else {
				$this->view->imageHtml      = '';
				$this->view->imageName      = '';
				$this->view->imageUrl       = '';
				$this->view->imageMaxTarget = '';
			}
			
			$this->view->JCropWidth = $this->getJCropWidthFromUrl($this->view->imageUrl);

            $this->view->msg = $messages;
            
            $menusetObj = new Application_Model_Menuset();
            $this->view->menuset = $menusetObj->getMenusetName();
			$this->view->success = $session->success;
			$this->view->page_number = $fileName;            
			$this->view->backUrl = '/menuset/edit';            
        } catch (Application_Model_Exception $e) {
            $this->_helper->flashMessenger->addMessage($e->getMessage());
            Globals::logException($e);
            
            $this->_redirect('/menuset');
        } catch (Exception $e) {
            $this->_helper->flashMessenger->addMessage($e->getMessage());
            Globals::logException($e);
            
            $this->_redirect('/menu');
        }
    }
    
    public function doEditAction()
    {
        // Check Csrf
        $this->_helper->csrf->checkCsrf($this->getRequest());
        $session = Globals::getSession();
		$session->success = 0;
        $postData = $this->getRequest()->getParams();

        $this->_helper->formData->addData($postData);

        if (isset($postData['is_upload'])
            && (!empty($postData['is_upload']))
        ) {
            $this->_uploadImage();
			
			$this->_data['saveSelectedAreas'] = 1;
            $this->persist();
			
            $this->_redirect('/menu/edit');
        }
        
        $errors = $this->_validate($postData);
        if (count($errors)) {
            foreach ($errors as $error) {
                $this->_helper->flashMessenger->addMessage($error);
            }
            $this->_redirect('/menu/edit');
        }
        
        $dataSession            = $this->getSessionNs()->data;
        $postData['image']      = $dataSession['image']['name'];
        $postData = array_merge($postData, $this->convertToOldDataFormat($dataSession['selectedAreas']));

        $htmlModel = new Application_Model_Html();
        if ($htmlModel->makeHtmlFile($postData)) {
            $this->_helper->flashMessenger->addMessage($this->msgConfig->N401_SaveSuccess_Menu);
            // Pack data            
            DataPacker::packDataInBatch($session->company_link);
			$session->success = 1;
			$this->destroySession();
        } else {
            $this->_helper->flashMessenger->addMessage($this->msgConfig->E401_SaveError_Menu);
        }        
		
        $this->_redirect('/menu/edit/name/' . $postData['page_number']);
    }

    public function copyAction()
    {
        try {
            if (!isset($this->getSessionNs()->isCopy)
                || ($this->getSessionNs()->isCopy == false)
            ) {
                $this->_initSession(self::COPY_ACTION);
            }

            $this->view->headScript()->offsetSetFile(12, (Globals::isMobile() ? '/sp' : '/pc') . '/js/menu-init.js');

            // Init Csrf
            $this->view->csrf = $this->_helper->csrf->initInput();
        
            // Init page number
            $menuObj = new Application_Model_Html();
            $this->view->pageNumber = $menuObj->getNextPageNumber();
            $menuConfig = Globals::getApplicationConfig('menu');
            if ($this->view->pageNumber > $menuConfig->maxPage) {
                $this->_helper->flashMessenger->addMessage(
                    sprintf($this->msgConfig->N401_MaxPage_Notice, $menuConfig->maxPage)
                );

                $this->_redirect('/menu');
            }
            
            $fileName = $this->getRequest()->getParam('name');
            
            if (empty($fileName)) {
                $dataSession = $this->getSessionNs()->data;
                $fileName = $dataSession['pageName'];
            }

            $flashMsg = $this->_helper->getHelper('flashMessenger');
            if ($flashMsg->hasMessages()) {
                $this->view->message = $flashMsg->getMessages();
            }
            
            $messages = array();
            $formData = $this->_helper->getHelper('formData');
            if ($formData->hasData()) {
                $this->view->data = $formData->getData();
            } else {
                $htmlObj = new Application_Model_Html();
                $data = $htmlObj->getHtmlFileInfo($fileName);
                $messages = $this->_prepareData($data);
                $data['page_number'] = '';
                $this->view->data = $data;
            }
            
            $dataSession = $this->getSessionNs()->data;
            $this->view->imageHtml      = $dataSession['image']['html'];
            $this->view->imageName      = $dataSession['image']['name'];
            $this->view->imageUrl       = $dataSession['image']['url'];
            $this->view->imageMaxTarget = $dataSession['image']['max_dimension'];
            
            $this->view->msg = $messages;
            
            $menusetObj = new Application_Model_Menuset();
            $this->view->menuset = $menusetObj->getMenusetName();
            
        } catch (Application_Model_Exception $e) {
            $this->_helper->flashMessenger->addMessage($e->getMessage());
            Globals::logException($e);
            
            $this->_redirect('/menuset');
        } catch (Exception $e) {
            $this->_helper->flashMessenger->addMessage($e->getMessage());
            Globals::logException($e);
            
            $this->_redirect('/menu');
        }
    }
    
    public function doCopyAction()
    {
        // Check Csrf
        $this->_helper->csrf->checkCsrf($this->getRequest());
        
        $postData = $this->getRequest()->getParams();

        $this->_helper->formData->addData($postData);

        if (isset($postData['is_upload'])
            && (!empty($postData['is_upload']))
        ) {
            $this->_uploadImage();
            $this->_redirect('/menu/copy');
        }
        
        $errors = $this->_validate($postData);
        if (count($errors)) {
            foreach ($errors as $error) {
                $this->_helper->flashMessenger->addMessage($error);
            }
            $this->_redirect('/menu/copy');
        }
        
        $dataSession            = $this->getSessionNs()->data;
        $postData['image']      = $dataSession['image']['name'];
        $postData['products']   = $dataSession['products'];
        $postData['videos']     = $dataSession['videos'];
        $postData['is_copy']    = true;

        $htmlModel = new Application_Model_Html();
        if ($htmlModel->makeHtmlFile($postData)) {
            $this->_helper->flashMessenger->addMessage($this->msgConfig->N401_SaveSuccess_Menu);
            // Pack data
            $session = Globals::getSession();
            DataPacker::packDataInBatch($session->company_link);
        } else {
            $this->_helper->flashMessenger->addMessage($this->msgConfig->E401_SaveError_Menu);
        }
        
        $this->destroySession();
        $this->_redirect('/menu');
    }
    
    public function editTopViewAction()
    {
        try {
            if (!isset($this->getSessionNs()->isEdit)
                || ($this->getSessionNs()->isEdit == false)
            ) {
                $this->_initSession(self::EDIT_ACTION);
            }
            
            // Init Csrf
            $this->view->csrf = $this->_helper->csrf->initInput();
            $session = Globals::getSession();
			$fileName = Application_Model_Html::TOPVIEW_NAME;
			
            // Init ajax links
            $this->view->urlLinkVideoList    = '/menu/link-video-list';
            $this->view->urlUpdateLink  = '/menu/update-link';

            $flashMsg = $this->_helper->getHelper('flashMessenger');
            if ($flashMsg->hasMessages()) {
                $this->view->message = $flashMsg->getMessages();
            }
            
            $messages = array();
            $formData = $this->_helper->getHelper('formData');
            
            $dataSession = $this->getSessionNs()->data;
            if ($formData->hasData() && isset($session->success) && $session->success != 1) {
				if (isset($dataSession['saveSelectedAreas'])) {
					$this->view->lastSelectedArea = $dataSession['selectedAreas'];
				}
                $this->view->data = $formData->getData();
            } else {
                $htmlObj = new Application_Model_Html();                
                
                $data = $htmlObj->getHtmlFileInfo($fileName);
				if (isset($dataSession['saveSelectedAreas'])) {
					$data['selectedAreas'] = $dataSession['selectedAreas'];
				} else {
					$data['selectedAreas'] = $this->convertToNewDataFormat($data, true);
				}
				$this->view->lastSelectedArea = $data['selectedAreas'];
                $this->view->data = $data;
				$dataSession = $data;

                $messages = $this->_prepareData($data, true);
            }

			if ($this->imageExists($dataSession['image']['url'])) {
				$this->view->imageName      = $dataSession['image']['name'];
				$this->view->imageUrl       = $dataSession['image']['url'];						
				$this->view->imageMaxTarget = $dataSession['image']['max_dimension'];
			} else {
				$this->view->imageName  = '';
                $this->view->imageUrl   = '';
                $this->view->imageMaxTarget = '';
			}

			$this->view->JCropWidth = $this->getJCropWidthFromUrl($this->view->imageUrl);
            
            $this->view->cateName       = Application_Model_Html::TOPVIEW_GOCATE;
            $this->view->msg            = $messages;
            
            $menusetObj = new Application_Model_Menuset();
            $this->view->menuset = $menusetObj->getMenusetName();
            $this->view->success = $session->success;
			$this->view->page_number = $fileName;            
			$this->view->page_number_show = Application_Model_Html::TOPVIEW_NAME_SHOW;            
			$this->view->backUrl = '/menuset/edit';
        } catch (Exception $e) {
            $this->_helper->flashMessenger->addMessage($e->getMessage());
            Globals::logException($e);
            
            $this->_redirect('/menu');
        }
    }
    
    public function doEditTopViewAction()
    {
        // Check Csrf
        $this->_helper->csrf->checkCsrf($this->getRequest());
        $session = Globals::getSession();
		$session->success = 0;
        $postData = $this->getRequest()->getParams();
        $this->_helper->formData->addData($postData);

        if (isset($postData['is_upload'])
            && (!empty($postData['is_upload']))
        ) {
            $this->_uploadImage();
			
			$this->_data['saveSelectedAreas'] = 1;
            $this->persist();
			
            $this->_redirect('/menu/edit-top-view');
        }
        
        $errors = $this->_validateTopView();
        if (count($errors)) {
            foreach ($errors as $error) {
                $this->_helper->flashMessenger->addMessage($error);
            }
            $this->_redirect('/menu/edit-top-view');
        }
        
        $dataSession = $this->getSessionNs()->data;
        
        $postData['image'] = $dataSession['image']['name'];
        $postData = array_merge($postData, $this->convertToOldDataFormat($dataSession['selectedAreas'], true));

        $htmlModel = new Application_Model_Html();
        if ($htmlModel->makeHtmlFile($postData)) {
            $this->_helper->flashMessenger->addMessage($this->msgConfig->N404_SaveSuccess_TopView);
            // Pack data
            DataPacker::packDataInBatch($session->company_link);
			$session->success = 1;
			$this->destroySession();
        } else {
            $this->_helper->flashMessenger->addMessage($this->msgConfig->E404_SaveError_TopView);
        }        
        
        $this->_redirect('/menu/edit-top-view');
    }
    
    public function updateLinkAction()
    {
        try {
            $this->_helper->layout->disableLayout();
            $this->_helper->viewRenderer->setNoRender();

            $postData = $this->getRequest()->getParams();
            // Get session data
            $sessionData = $this->getSessionNs()->data;
            // Link data
            $linkData = array();
            if (isset($sessionData['selectedAreas'])) {
                $linkData = $sessionData['selectedAreas'];
            }

            // UPDATE LINK TARGET (x,y,w,h)
            if ($postData['update-group'] === '0' && isset($postData['update-link-ids'])
                && ((!empty($postData['update-link-ids'])) || (!empty($postData['update-video-ids'])))
            ) {				
				$linkData[$postData['group']] = array('linkIds' => $postData['update-link-ids'], 
													'linkNames' => $postData['update-link-names'],
													'videoIds' => $postData['update-video-ids'],
													'target' => $postData['target']);
            } else if ($postData['update-group'] !== '0') {
				$linkData[$postData['group']]['target'] = $postData['target'];
			}
            
            // REMOVE LINK
            if (!empty($postData['del-link'])) {
                unset($linkData[$postData['del-link']]);
            }

            // Save session data
            $this->_data['selectedAreas']    = $linkData;
            $this->persist();
        } catch (ErrorException $e) {
            Globals::logException($e);
        }
    }
}
