<?php

/**
 * Action RecommendProductsController
 * PHP version 5.3.9
 * @author nqtrung
 * @copyright Kobe Digital Labo, Inc
 * @since 2015/05/18
 */
class RecommendproductsController extends Zend_Controller_Action {

    private $msgConfig;
    private $success;

    const ITEM_CODE = 'menuCode';
    const IMAGE_NAME = 'image%s';
    const LINK_SYSTEM_PRINTER = '0';
    const LINK_SYSTEM_TEC = '1';

    /**
     * Init values
     *
     * @return void
     * @author nqtrung
     * @since 2015/05/18
     */
    public function init() {
        //---get session data
        $session = Globals::getSession();

        $this->company_code = $session->company_code;
        $this->company_link = $session->company_link;

        $this->_fileConfig = Globals::getApplicationConfig('upload');
        $this->msgConfig = Zend_Registry::get('MsgConfig');
        $flash = $this->_helper->getHelper('flashMessenger');

        if ($flash->hasMessages()) {
            $this->view->message = $flash->getMessages();
        }
    }

    /**
     * @action: Index
     *
     * @return void
     * @author nqtrung
     * @since 2015/05/18
     */
    public function indexAction() {
        // Init Csrf
        $this->view->csrf = $this->_helper->csrf->initInput();

        //---get session data
        $session = Globals::getSession();

        $session->last_uri = $_SERVER['REQUEST_URI']; 

        $this->view->success = $session->success;        

        //get data list
        try {
            $postData = $this->getRequest()->getParams();

            $session->paramsCategory = $postData;

            $page = isset($postData['page']) ? intval($postData['page']) : 1;
            $limit = isset($postData['rows']) ? intval($postData['rows']) : Globals::getApplicationConfig('optlist')->list_count;
            if (!isset($postData['rows']) && isset($session->view_count_list)) {
                $limit = $session->view_count_list;
            }
            if ($limit == 0 || $limit == 'all') {
                $limit = null;
            }
            $itemName = isset($postData['item']) ? trim($postData['item']) : '';
            $search = NULL;
            if (!empty($itemName)) {
                $search = array(
                    'itemName' => array('like' => $itemName)
                );
            }
            $pricelt = isset($postData['pricelt']) ? trim($postData['pricelt']) : '';
            if (!empty($pricelt)) {
                $search['price']['lte'] = $pricelt;
            }
            $pricegt = isset($postData['pricegt']) ? trim($postData['pricegt']) : '';
            if (!empty($pricegt)) {
                $search['price']['gte'] = $pricegt;
            }
            $category = isset($postData['category']) ? trim($postData['category']) : '';
            if (strlen($category) > 0) {
                $search['category1_code']['='] = $category;
            }

            //---get data list product
            $recommendProducts = new Application_Model_RecommendProducts($session->company_link);
            $this->view->dataProductList = $recommendProducts->getRecommendProducts($postData, $search, $page, $limit);
                        
            $formData = $this->_helper->getHelper('formData');		
            if (!is_null($session->success) && $session->success != 1 && $formData->hasData()) {
                $this->view->data = $formData->getData();
            } else {
                $this->view->data = array('recommendationTitle' => $recommendProducts->getRecommendProductsTitle());
            }
        } catch (Kdl_Ipadso_Csv_Exception $e) {
            Globals::logException($e);
        }
        
        $flashMsg = $this->_helper->getHelper('flashMessenger');
        if ($flashMsg->hasMessages()) {
            $this->view->message = $flashMsg->getMessages();
        }

        $this->view->alertEnterSearch = $this->msgConfig->E301_Enter_Search;
        $this->view->alertSelectRow = $this->msgConfig->E301_Select_Row;
        $this->view->defaultPageSize = Globals::getApplicationConfig('optlist')->list_count;
    }

    /**
     * @action: set status of product - execute
     *
     * @return void
     * @author nqtrung
     * @since 2015/05/18
     */
    public function saveAction() {
        // Check Csrf
        $this->_helper->csrf->checkCsrf($this->getRequest());

        //---get session data
        $session = Globals::getSession();
        $this->recommendProducts = new Application_Model_RecommendProducts($session->company_link);
        $this->check = new Application_Model_ValidateRules();

        //---get data post
        $req = Zend_Controller_Front::getInstance()->getRequest();
        $this->action = $req->getActionName();

        //---Init Messenger
        $this->msgConfig = Zend_Registry::get('MsgConfig');
        $flash = $this->_helper->getHelper('flashMessenger');
        if ($flash->hasMessages()) {
            $this->view->message = $flash->getMessages();
        }

        $form_Data = $this->getRequest()->getPost();
        $this->_helper->formData->removeCsrfData($form_Data);
        
        if (isset($form_Data['setRecommend'])) {
            $status = $form_Data['setRecommend'];
            $session->last_uri = str_replace('/status/' . (1 - $status), '/status/' . $status, $session->last_uri);        
        }
        
        if ($this->_validate($form_Data)) {
            try {
                $session->success = 1;

                //---Insert data
                $data = $this->recommendProducts->getRecommendProductsJsonData();
                foreach ($form_Data['h_row'] as $k => $v) {
                    if (in_array($v, $form_Data['chk_check_row'])) {
                        $data[$v]['recommendation'] = 'true';
                        if (!isset($data[$v]['soldout'])) {
                            $data[$v]['soldout'] = 'false';
                        }
                        if (trim($form_Data['recommendation_order'][$k]) != '') {
                            $data[$v]['recommendation_order'] = trim($form_Data['recommendation_order'][$k]);
                        } else {
                            unset($data[$v]['recommendation_order']);
                        }
                    } else if (isset($data[$v])) {
                        unset($data[$v]['recommendation']);
                        unset($data[$v]['recommendation_order']);
                        if (count($data[$v]) == 0 || $data[$v]['soldout'] == 'false') {
                            unset($data[$v]);
                        } else {
                            $data[$v]['recommendation'] = 'false';
                        }
                    }
                }
                
                $data = $this->recommendProducts->removeRecommendProductsFromProductsList($data);
                $data = array('status' => $data, 'recommendation_title' => $form_Data['recommendationTitle']);               

                
                $this->recommendProducts->saveRecommendProductsJsonData($data);

                Globals::log('Set status recommend products', null, $this->company_code . '.log');
                Globals::log($data, null, $this->company_code . '.log');
                $this->_helper->flashMessenger->addMessage($this->msgConfig->I000_ProductEditSuccessful);

                unset($session->paramsCategory);
                // Pack data
                //DataPacker::packDataInBatch($session->company_link);
                $this->_redirect($session->last_uri);
            } catch (Exception $e) {
                $session->success = 0;
                Globals::log('Set status recommend products unsuccessful.', null, $this->company_code . '.log');
                Globals::logException($e);
                $this->_helper->flashMessenger->addMessage($this->msgConfig->E000_CanNotEdit);
                $this->_redirect($session->last_uri);
            }
        } else {
            $session->success = 0;
            $this->_redirect($session->last_uri);
        }
    }

    /**
     * validate the value on the recommend product form
     *
     * @access private
     * @param  array data on the form
     * @return bool
     * @since 2015/05/18
     */
    private function _validate($form_data) {
        $result = true;

        $check = new Application_Model_ValidateRules();
        $this->_helper->formData->addData($form_data);
        
		if (!$check->checkSpecCharForName($form_data['recommendationTitle'])) {
			$this->_helper->flashMessenger->addMessage(array('recommendationTitle' => $this->msgConfig->E601_Invalid_RecommendationTitle));
            $result = false;
        }
        
        if (isset($form_data['recommendation_order'])) {
            foreach ($form_data['recommendation_order'] as $index => $value) {
                $menuCode = $form_data['h_row'][$index];
                if (!in_array($menuCode, $form_data['chk_check_row'])) {
                    continue;
                }
                
                if ($value != '') {
                    if (!Zend_Validate::is($value, 'Digits')) {
                        $this->_helper->flashMessenger->addMessage($this->msgConfig->E311_Invalid_Recommendation_Order);
                        $result = false;
                        break;
                    }
                }
            }
        }

        return $result;
    }

}
