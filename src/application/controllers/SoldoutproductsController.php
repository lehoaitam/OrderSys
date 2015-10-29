<?php

/**
 * Action SoldOutProductsController
 * PHP version 5.3.9
 * @author nqtrung
 * @copyright Kobe Digital Labo, Inc
 * @since 2014/10/08
 */
class SoldoutproductsController extends Zend_Controller_Action {

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
     * @since 2014/10/08
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
     * @since 2014/10/08
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
            $soldoutProducts = new Application_Model_SoldOutProducts($session->company_link);
            $this->view->dataProductList = $soldoutProducts->getSoldoutProducts($postData, $search, $page, $limit);
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
     * @since 2014/10/10
     */
    public function saveAction() {
        // Check Csrf
        $this->_helper->csrf->checkCsrf($this->getRequest());

        //---get session data
        $session = Globals::getSession();
        $this->soldoutProducts = new Application_Model_SoldOutProducts($session->company_link);
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
        
        if (isset($form_Data['setSoldOut'])) {
            $status = $form_Data['setSoldOut'];
            $session->last_uri = str_replace('/status/' . (1 - $status), '/status/' . $status, $session->last_uri);        
        }
        
        if ($this->_validate($form_Data)) {
            try {
                $session->success = 1;

                //---Insert data
                $data = $this->soldoutProducts->getSoldoutProductsJsonData();
                foreach ($form_Data['h_row'] as $v) {
                    if (in_array($v, $form_Data['chk_check_row'])) {
                        $data[$v]['soldout'] = 'true';
                        if (!isset($data[$v]['recommendation'])) {
                            $data[$v]['recommendation'] = 'false';
                        }
                    } else if (isset($data[$v])) {
                        unset($data[$v]['soldout']);
                        if (count($data[$v]) == 0 || $data[$v]['recommendation'] == 'false') {
                            unset($data[$v]);
                        } else {
                            $data[$v]['soldout'] = 'false';
                        }
                    }
                }
                
                $data = $this->soldoutProducts->removeSoldoutProductsFromProductsList($data);
                $data = array('status' => $data);
                $this->soldoutProducts->saveSoldoutProductsJsonData($data);

                Globals::log('Set status soldout products', null, $this->company_code . '.log');
                Globals::log($data, null, $this->company_code . '.log');
                $this->_helper->flashMessenger->addMessage($this->msgConfig->I000_ProductEditSuccessful);

                unset($session->paramsCategory);
                // Pack data
                //DataPacker::packDataInBatch($session->company_link);
                $this->_redirect($session->last_uri);
            } catch (Exception $e) {
                $session->success = 0;
                Globals::log('Set status soldout products unsuccessful.', null, $this->company_code . '.log');
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
     * validate the value on the soldout product form
     *
     * @access private
     * @param  array data on the form
     * @return bool
     * @since 2014/10/10
     */
    private function _validate($form_data) {
        $result = true;

        $this->_helper->formData->addData($form_data);

        return $result;
    }

}
