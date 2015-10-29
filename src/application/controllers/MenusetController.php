<?php
/**
 * Action MenusetController
 * PHP version 5.3.9
 * @author Nguyen Huu Tam
 * @copyright Kobe Digital Labo, Inc
 * @since 2013/02/28
 */

class MenusetController extends Zend_Controller_Action
{
 
	const NO_IMAGE_URL = '/images/no_image_100.png';
	const THUMB_IMAGE_WIDTH = 100;
	const THUMB_IMAGE_HEIGHT_DEFAULT = 105;
	
    /**
     * Init values 
     * 
     * @return void
     * @author Nguyen Huu Tam
     * @since 2012/07/13
     */
    public function init()
    {
        $this->msgConfig = Zend_Registry::get('MsgConfig');
		$this->config = Globals::getApplicationConfig('menuset');
    }
    
    
    /**
     * List action
     * 
     * @return void
     * @author Nguyen Huu Tam
     * @since 2013/02/28
     */
    public function indexAction()
    {
        // Init Csrf
        $this->view->csrf = $this->_helper->csrf->initInput();
		$session = Globals::getSession();
        $this->view->success = $session->success;
	
        // Init message
        $flashMsg = $this->_helper->getHelper('flashMessenger');
        if ($flashMsg->hasMessages()) {
            $this->view->message = $flashMsg->getMessages();
        }
	
		$postData = $this->getRequest()->getParams();
		$page   = isset($postData['page']) ? intval($postData['page']) : 1;
		$limit  = isset($postData['rows']) ? intval($postData['rows']) : Globals::getApplicationConfig('optlist')->list_count;
		if (!isset($postData['rows']) && isset($session->view_count_list)) {
			$limit = $session->view_count_list;
		}
		if ($limit == 0) {
			$limit = null;
		}
		
		//TODO: check menuset json exist
		$newJsonFileExisted = file_exists(Application_Model_Menuset::getJsonFilePath());
        
        $menusetObj = new Application_Model_Menuset();
        $data = $menusetObj->getList($postData, $page, $limit);
        $data_copy = $menusetObj->getList($postData);
		
		//TODO: copy to new menuset json if not exist
		if (!$newJsonFileExisted) {
			// Pack data                
            DataPacker::packDataInBatch($session->company_link);
		}
		
		$found = false;
		$lastOrder = array();
		//save order status
		$order = array();
		foreach ($data_copy['rows'] as $row) {
			$order[] = $row['id'];
			
			if ($row['id'] === $data['rows'][0]['id']) {
				$found = true;
			}
			if (!$found) {
				$lastOrder[] = $row['id'];
			}
		}
		$session->menuset_order_list = $order;
		$this->view->data = $data;
		$this->view->last_data = $lastOrder;
		$this->view->data_copy = $data_copy;
    }
    
    /**
     * Add menuset
     * 
     * @return: void
     * @author Nguyen Huu Tam
     * @since 2013/03/02
     */
    public function doAddAction()
    {
        // Check Csrf
        $this->_helper->csrf->checkCsrf($this->getRequest());
        $session = Globals::getSession();
		$session->success = 0;
        $postData = $this->getRequest()->getParams();
        $this->_helper->formData->addData($postData);
        
        $errors = $this->_validate($postData);
        if (count($errors)) {
            foreach ($errors as $error) {
                $this->_helper->flashMessenger->addMessage($error);
            }	    
            $this->_redirect('/menuset/add');
        }
		
        try {
            $menusetObj = new Application_Model_Menuset();
			$id = $menusetObj->add($postData);
            if ($id > 0) {
                $this->_helper->flashMessenger->addMessage(
                    $this->msgConfig->I600_AddSuccessful_Menuset
                );
                
                // Pack data                
                DataPacker::packDataInBatch($session->company_link);
				$session->success = 1;
            }
        } catch (Application_Model_Exception $e) {
            $this->_helper->flashMessenger->addMessage($e->getMessage());
            Globals::logException($e);
        } catch (ErrorException $e) {
            $this->_helper->flashMessenger->addMessage($e->getMessage());
            Globals::logException($e);
        }
        
        $this->_redirect('/menuset/edit/name/' . $id);
    }
    
    
    /**
     * Add menuset
     * 
     * @return: void
     * @author Nguyen Huu Tam
     * @since 2013/05/09
     */
    public function addAction()
    {
        // Init Csrf
        $this->view->csrf = $this->_helper->csrf->initInput();
		$session = Globals::getSession();
        $this->view->success = $session->success;
	
        $flashMsg = $this->_helper->getHelper('flashMessenger');
        if ($flashMsg->hasMessages()) {
            $this->view->message = $flashMsg->getMessages();
        }        
		
		$this->view->data = array();
		$this->view->languages = $this->config->language->options->toArray();
		
        $formData = $this->_helper->getHelper('formData');		
        if (!is_null($session->success) && $session->success != 1 && $formData->hasData()) {
            $this->view->data = $formData->getData();
        }
		
		$this->view->language = isset($this->view->data['language']) ? $this->view->data['language'] : $this->config->language->defaultOption;
    }
    
    
    /**
     * Edit menuset
     * 
     * @return: void
     * @author Nguyen Huu Tam
     * @since 2013/03/02
     */
    public function editAction()
    {
        try {
            // Init Csrf
            $this->view->csrf = $this->_helper->csrf->initInput();

			$this->view->headScript()->offsetSetFile(20, (Globals::isMobile() ? '/sp' : '/pc') . '/js/jquery.fancybox.js');
			$this->view->headLink()->appendStylesheet((Globals::isMobile() ? '/sp' : '/pc') . '/css/jquery.fancybox.css');
            $flashMsg = $this->_helper->getHelper('flashMessenger');
            if ($flashMsg->hasMessages()) {
                $this->view->message = $flashMsg->getMessages();
            }
			$session = Globals::getSession(); 
			$postData = $this->getRequest()->getParams();
			if (isset($postData['name'])) {
				$id = $postData['name'];
				$session->id = $id;
			} else {
				$id = $session->id;
			}
			if (is_null($id)) {
				$this->_redirect('/menuset');
			}
			$this->formData = $this->_helper->getHelper('formData');
			if (!(count($this->formData->getData()) == 0 || (!is_null($session->success) && $session->success !== 0))) {
				$postData =  $this->formData->getData();
			}

            // Save current menuset in session
            $menusetObj = new Application_Model_Menuset();
            $menuset = $menusetObj->getCurrenMenuset($id);
			$this->view->id = $id;
            $this->view->oldMenusetName = $menusetObj->getMenusetName($menuset);
            $this->view->menusetName = isset($postData['menusetName']) ? $postData['menusetName'] : $menusetObj->getMenusetName($menuset);
			
			$this->view->language = isset($postData['language']) ? $postData['language'] : $menusetObj->getMenusetLanguage($menuset);
			$this->view->languages = $this->config->language->options->toArray();

            $this->view->topViewName = Application_Model_Html::TOPVIEW_NAME;
            $this->view->delTopViewMsg = $this->msgConfig->E400_CannotDelete_TopView;
            $this->view->delMenusetUsed = $this->msgConfig->E601_DelMenuset_Used;
			$this->view->success       = $session->success;
            $this->view->isUsed        = $this->checkUsed($menuset) ? '1' : '0';

			$this->view->showAddButton = true;
			$menuObj = new Application_Model_Html();
            $pageNumber = $menuObj->getNextPageNumber();
            $menuConfig = Globals::getApplicationConfig('menu');
            if ($pageNumber > $menuConfig->maxPage) {
                $this->view->showAddButton = false;
            }			

			$htmlObj = new Application_Model_Html($menuset);
			$menuFlow = $htmlObj->getMenuFlowData();

			$htmlObj = new Application_Model_Html();
			$data = $htmlObj->getHtmlList(true, array());
			$order = array();

			$pageCodeHTML = '<a href="/menu/edit/name/%s">%s</a>';
			$imageHTML = '<a href="/menu/edit/name/%s"><img width="100px" src="%s"> </a> 
				<span class="sort-handle-icon-image" style="margin-top: 35px;">↓</span>			
				<input type="hidden" value="%s" name="list-order[]">
				<input type="hidden" value="%s" name="imageHeight">';
			$listHeight = 0;
			$index = 0;
			foreach ($data['rows'] as $key => $row) {
				if (substr($menuFlow[$key]['src'], -1) == '/') {
					$data['rows'][$key]['image'] = sprintf($imageHTML, $row['pagename'], self::NO_IMAGE_URL, $row['pagename'], self::THUMB_IMAGE_WIDTH);
				} else {
					$size = array();
					if (file_exists(APPLICATION_PATH . '/../public' . $menuFlow[$key]['src'])) {
						$size = getimagesize(APPLICATION_PATH . '/../public' . $menuFlow[$key]['src']);
						$size[1] *= self::THUMB_IMAGE_WIDTH /  $size[0];
					} else {
						$params = array();
						$array = explode('/', $menuFlow[$key]['src']);
						for ($i = 1; $i < count($array) - 1; $i += 2) {
							$params[$array[$i]] = $array[$i + 1];
						}
						
						$filePath = $this->getFilePath(
							$params['name'],
							$params['menuset'],
							Application_Model_Html::IMG_FOLDER
						);
						if (file_exists($filePath)) {
							$size = getimagesize($filePath);
							$size[1] *= self::THUMB_IMAGE_WIDTH /  $size[0];
						} else {
							$size[1] = self::THUMB_IMAGE_HEIGHT_DEFAULT;
						}
					}					
					
					if ($index <= 5) {						
						$listHeight += $size[1] + 11; //11: padding
					}
					$index++;
					$data['rows'][$key]['rownumber'] = $index;
					$data['rows'][$key]['image'] = sprintf($imageHTML, $row['pagename'], $menuFlow[$key]['src'] . '?' . rand(), $row['pagename'], $size[1]);
				}
				$data['rows'][$key]['pagecode'] = sprintf($pageCodeHTML, $row['pagename'], $row['pagename']);

				$order[] = $row['pagename'];
			}
			$session->menu_order_list = $order;
			$session->listData = $data;
			$this->view->listHeight = $listHeight;
			$this->view->urlMenuList = '/menuset/menu-list';
        } catch (Application_Model_Exception $e) {
            $this->_helper->flashMessenger->addMessage($e->getMessage());
            Globals::logException($e);
            
            $this->_redirect('/menuset');
        } catch (ErrorException $e) {
            Globals::logException($e);
        }
    }
    
    /**
     * check if there is any menu is used by another menuset
     * @param type $menusetId
     */
    protected function checkUsed($menusetId) {
        $menusetObj = new Application_Model_Menuset();
        $menusetList = $menusetObj->getList();
        foreach ($menusetList['rows'] as $row) {
            if ($row['id'] != $menusetId) {
                $htmlObj = new Application_Model_Html($row['id']);
                $menuList = $htmlObj->getHtmlList(true, array());
                foreach ($menuList['rows'] as $row1) {
                    $data = $htmlObj->getHtmlFileInfo($row1['pagename']);
                    foreach ($data['items'] as $row2) {
                        //menuCode
                        if ($row2['menuCode'] === Application_Model_Html::TOPVIEW_GOMENUSET . $menusetId) {                                     
                            return true;
                        }
                    }
                }
            }
        }
        
        return false;
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
     * menu list
     * 
     * @return: void
     * @author nqtrung
     * @since 2014/06/12
     */
	public function menuListAction() {
		$session = Globals::getSession();
		$this->_helper->json($session->listData);
	}
    
    
    /**
     * Edit menuset
     * 
     * @return: void
     * @author Nguyen Huu Tam
     * @since 2013/03/02
     */
    public function doEditAction()
    {
        // Check Csrf
        $this->_helper->csrf->checkCsrf($this->getRequest());
        $session = Globals::getSession();
		$session->success = 0;
		
        $postData = $this->getRequest()->getParams();
        $this->_helper->formData->removeCsrfData($postData);
		$this->_helper->formData->addData($postData);

        $errors = $this->_validate($postData);
        if (count($errors)) {
            foreach ($errors as $error) {
                $this->_helper->flashMessenger->addMessage($error);
            }
            $this->_redirect('/menuset/edit');
        }
        
        try {
            $menusetObj = new Application_Model_Menuset();
            if ($menusetObj->edit($postData)) {
                $this->_helper->flashMessenger->addMessage(
                    $this->msgConfig->I602_EditSuccessful_Menuset
                );
                
                // Pack data                
                DataPacker::packDataInBatch($session->company_link);
				$session->success = 1;
            }
        } catch (Application_Model_Exception $e) {
            $this->_helper->flashMessenger->addMessage($e->getMessage());
            Globals::logException($e);
        } catch (ErrorException $e) {
            $this->_helper->flashMessenger->addMessage($e->getMessage());
            Globals::logException($e);
        }        
		
        $this->_redirect('/menuset/edit');
    }
    
    /**
     * copy menuset
     * 
     * @return: void
     * @author Nguyen Quang Trung
     * @since 2014/05/08
     */
    public function doCopyAction()
    {
        // Check Csrf
        $this->_helper->csrf->checkCsrf($this->getRequest());
        $session = Globals::getSession();

        $postData = $this->getRequest()->getParams();
        $this->_helper->formData->addData($postData);
        
        $errors = $this->_validate($postData, true);
        if (count($errors)) {   
            $this->_helper->json(array('error' => $errors, 
				'key' => $this->_helper->csrf->getKeyName(), 
				'formid' => $this->_helper->csrf->getFormId(),
				'token' => $this->_helper->csrf->getToken()));
        }
		
        try {
            $menusetObj = new Application_Model_Menuset();
            if ($menusetObj->copy($postData)) {
                $this->_helper->flashMessenger->addMessage(
                    $this->msgConfig->I600_CopySuccessful_Menuset
                );
                
                // Pack data                
                DataPacker::packDataInBatch($session->company_link);
            }
        } catch (Application_Model_Exception $e) {
            $this->_helper->flashMessenger->addMessage($e->getMessage());
            Globals::logException($e);
			$this->_helper->json(array('error' => $e->getMessage(), 
				'key' => $this->_helper->csrf->getKeyName(), 
				'formid' => $this->_helper->csrf->getFormId(),
				'token' => $this->_helper->csrf->getToken()));
        } catch (ErrorException $e) {
            $this->_helper->flashMessenger->addMessage($e->getMessage());
            Globals::logException($e);
			$this->_helper->json(array('error' => $e->getMessage(), 
				'key' => $this->_helper->csrf->getKeyName(), 
				'formid' => $this->_helper->csrf->getFormId(),
				'token' => $this->_helper->csrf->getToken()));
        }
        
        $this->_helper->json(array('result' => 'true'));
    }
	
	/**
     * Delete menuset(s)
     * 
     * @return: void
     * @author Nguyen Huu Tam
     * @since 2013/02/28
     */
	public function copyCompletedAction() {
		$session = Globals::getSession();
		$session->success = 1;
		$this->_helper->flashMessenger->addMessage(
                    $this->msgConfig->I600_CopySuccessful_Menuset
                );
		$this->_redirect('/menuset');
	}
	
	/**
     * Delete menuset(s)
     * 
     * @return: void
     * @author Nguyen Huu Tam
     * @since 2013/02/28
     */
    public function deleteAction()
    {
        try {
            // Check Csrf
            $this->_helper->csrf->checkCsrf($this->getRequest());
			$session = Globals::getSession();
			
            $id = $this->getRequest()->getParam('id');
            if (!empty($id)) {
                $menusetObj = new Application_Model_Menuset();
                // Remove menu items
                $result = $menusetObj->delete($id);
            }

            // If has error
            if (isset($result['error'])) {
                foreach ($result['error'] as $msg) {
                    $this->_helper->flashMessenger->addMessage($msg);
                }
                $this->_redirect('/menuset');
            }

            if (isset($result['message'])) {
                foreach ($result['message'] as $msg) {
                    $this->_helper->flashMessenger->addMessage($msg);
                }
            }
			
            // Get menu order list
            $items = $session->menuset_order_list;
            // Remove all deleted item(s)
            $items = array_diff($items, array($id));
            // Reset index of array
            $items = array_values($items);
            $menusetObj->resetMenuOrder($items);
            
            // Pack data            
            DataPacker::packDataInBatch($session->company_link);
            
        } catch (Application_Model_Exception $e) {
            $this->_helper->flashMessenger->addMessage($e->getMessage());
            Globals::logException($e);
        } catch (ErrorException $e) {
            Globals::logException($e);
        }
        
        $this->_redirect('/menuset');
    }    
   
    /**
     * Validate post data
     * 
     * @return: array
     * @author Nguyen Huu Tam
     * @since 2013/03/02
     */
    protected function _validate($formData, $isCopyAction = false)
    {
        $errors =  array();
        $check = new Application_Model_ValidateRules();
		
		if ($isCopyAction) {
			if (!isset($formData['menusetList']) || !Zend_Validate::is($formData['menusetList'], 'NotEmpty')) {
				$errors[] = array('menusetList' => $this->msgConfig->E601_Require_MenusetList);
			}
		}
        if (!Zend_Validate::is($formData['menusetName'], 'NotEmpty')) {
            $errors[] = array('menusetName' => $this->msgConfig->E601_Require_MenusetName);
        }
		if (!$check->checkSpecCharForName($formData['menusetName'])) {
			$errors[] = array('menusetName' => $this->msgConfig->E601_Invalid_MenusetName);
		}
        
        return $errors;
    }
    
    
    /**
     * Update order menu
     * 
     * @author Nguyen Huu Tam
     * @since 2012/08/30
     */
    public function updateOrderAction()
    {
        // Check Csrf
        $this->_helper->csrf->checkCsrf($this->getRequest());
        $session = Globals::getSession();
		$session->success = 0;
		
        $sortItems = $this->getRequest()->getParam('list-order');
        if (is_array($sortItems) && count($sortItems) > 1) {
            try {
                $menusetObj = new Application_Model_Menuset();
                $menusetObj->updateMenuOrder($sortItems);
                    
                // Pack data
                DataPacker::packDataInBatch($session->company_link);
                $this->_helper->flashMessenger->addMessage($this->msgConfig->N401_SaveMenuOrder);
                $session->success = 1;
            } catch (Application_Model_Exception $e) {
                $this->_helper->flashMessenger->addMessage($e->getMessage());
            }
            
            $this->_redirect('/menuset/index/' . $this->getRequest()->getParam('lastURL'));
        }
    }
	
	    /**
     * Update order menu
     * 
     * @author Nguyen Huu Tam
     * @since 2012/08/30
     */
    public function updateMenuOrderAction()
    {
        // Check Csrf
        $this->_helper->csrf->checkCsrf($this->getRequest());
        $session = Globals::getSession();
		$session->success = 0;
		
        $sortItems = $this->getRequest()->getParam('list-order');
        if (is_array($sortItems) && count($sortItems) > 1) {
            $htmlObj = new Application_Model_Html();
            if ($htmlObj->updateMenuOrder($sortItems)) {
                // Pack data
                
                DataPacker::packDataInBatch($session->company_link);
				$this->_helper->flashMessenger->addMessage($this->msgConfig->N401_SaveMenuOrder);
				$session->success = 1;
            }
            
            $this->_redirect('/menuset/edit');
        }
    }
}
