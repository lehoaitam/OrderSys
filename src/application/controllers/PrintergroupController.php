<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of PrintergroupController
 *
 * @author nqtrung
 */
class PrintergroupController extends Zend_Controller_Action
{

    public function indexAction()
    {
        // Init Csrf
        $this->view->csrf = $this->_helper->csrf->initInput();

        //---get data session
        $session = Globals::getSession();
        $this->view->success = $session->success;

        // Init Messages
        $flashMsg = $this->_helper->getHelper('flashMessenger');
        if ($flashMsg->hasMessages()) {
            $this->view->message = $flashMsg->getMessages();
        }

        //---get data post
        $postData = $this->getRequest()->getParams();

        //---get list movie
        $page  = isset($postData['page']) ? intval($postData['page']) : 1;
        $limit = isset($postData['rows']) ? intval($postData['rows']) : Globals::getApplicationConfig('optlist')->list_count;
        if (!isset($postData['rows']) && isset($session->view_count_list)) {
            $limit = $session->view_count_list;
        }
        if ($limit == 0 || $limit == 'all') {
            $limit = null;
        }

        $printerGroupObj = new Application_Model_PrinterGroup();

        //---get data printerGroup on list page
        $data     = $printerGroupObj->getPrinterGroup($postData, null, $page, $limit);
        $this->view->data = $data;
    }

    /**
     * @action Add a new printer group
     *
     * @return void
     * @author nqtrung
     * @since 2014/12/17
     */
    public function addAction()
    {
        // Init Csrf
        $this->view->csrf = $this->_helper->csrf->initInput();

        //---get data session
        $session = Globals::getSession();
        $session->action = $this->getRequest()->getActionName();

        // Init Messages
        $this->msgConfig    =  Zend_Registry::get('MsgConfig');
        $flash              = $this->_helper->getHelper('flashMessenger');
        if ($flash->hasMessages()) {
            $this->view->message = $flash->getMessages();
        }

        $this->view->printerIPs = array();
        $this->formData = $this->_helper->getHelper('formData');
        $this->_helper->formData->addData($this->formData->getData());
        if ($this->formData->hasData()) {
            $data = $this->formData->getData();
            $this->view->data = $data;

            $printerIPs = array();
            if (isset($data['printerIP']) && is_array($data['printerIP'])) {
                foreach ($data['printerIP'] as $index => $value) {
                    $printerIPs[$this->view->data['hPrinterIP'][$index]] = $value;
                }
            }
            $this->view->printerIPs = $printerIPs;
        } else {
            $this->view->data = array();
        }
        $this->view->success    = $session->success;
        unset($session->success);

        $categoryObj = new Application_Model_Category();
        $categoryData = $categoryObj->getData();
        Application_Model_Entity::natksort($categoryData);
        $this->view->categoryData = $categoryData;
    }

    public function addexecuteAction()
    {
        // Check Csrf
        $this->_helper->csrf->checkCsrf($this->getRequest());

        //---get data session
        $session = Globals::getSession();

        //---Init Messenger
        $this->msgConfig = Zend_Registry::get('MsgConfig');
        $flash = $this->_helper->getHelper('flashMessenger');
        if ($flash->hasMessages()) {
            $this->view->message = $flash->getMessages();
        }

        $form_data = $this->getRequest()->getPost();
        $this->_helper->formData->removeCsrfData($form_data);
        $this->_helper->formData->addData($form_data);

        if ($this->_validate($form_data)) {
            $session->success = 1;

            //---Insert data
            $printerGroup = new Application_Model_PrinterGroup();
            $data = $printerGroup->getPrinterGroupJsonData();
            $item = array();
            $item['id'] = (Application_Model_PrinterGroup::getMaxID($data) + 1) . ""; //convert to string
            $item['printerGroupName'] = $form_data['groupName'];
            $item['printerIPAddress'] = $form_data['IPAddress'];
            $categories = array();
            foreach ($form_data['printerIP'] as $index => $value) {
                if (!empty($value)) {
                    $categories[$form_data['hPrinterIP'][$index]] = array('ipaddress' => $value);
                }
            }
            $item['category'] = $categories;

            $data[] = $item;
            $data = array('printergroups' => $data);
            $printerGroup->savePrinterGroupJsonData($data);

            Globals::log('Add printer group ' . $item['id'], null, $this->company_code . '.log');
            Globals::log($data, null, $this->company_code . '.log');
            $this->_helper->flashMessenger->addMessage($this->msgConfig->E900_AddSuccessful);
            
            // Pack data
            DataPacker::packDataInBatch($session->company_link);
                
            $this->_redirect('/printergroup/edit/id/' . $item['id']);
        } else {
            $session->success = 0;
        }

        $this->_redirect('/printergroup/add');
    }


    /**
     * @action Edit a new printer group
     *
     * @return void
     * @author nqtrung
     * @since 2014/12/17
     */
    public function editAction()
    {
        // Init Csrf
        $this->view->csrf = $this->_helper->csrf->initInput();

        $session = Globals::getSession();
        $postData = $this->getRequest()->getParams();

        $idEdit = isset($postData['id']) ? ($postData['id']) : $session->idEdit;
        $session->idEdit = $idEdit;        
       
        $session->pos = $session->paramsCategory;
        
        $this->msgConfig = Zend_Registry::get('MsgConfig');
        $flash = $this->_helper->getHelper('flashMessenger');
        if ($flash->hasMessages()) {
            $this->view->message = $flash->getMessages();
        }

        $this->formData = $this->_helper->getHelper('formData');
        $this->_helper->formData->addData($this->formData->getData());                
        
        if ($idEdit == '') {
            $this->view->success = 0;
            $this->_helper->flashMessenger->addMessage($this->msgConfig->E900_NotGetDataToEdit);
            $this->_redirect('/printergroup');
        }

        if ((!isset($session->success) && count($this->formData->getData()) == 0) || (isset($session->success) && $session->success != 0)) {
            $printerGroup = new Application_Model_PrinterGroup();
            $printerGroupData = $printerGroup->getPrinterGroupJsonData($idEdit);

            if (count($printerGroupData) == 0) {
                $this->view->success = 0;
                $this->_helper->flashMessenger->addMessage(
                        sprintf($this->msgConfig->E900_NotExist_Group, $idEdit)
                );
                $this->_redirect('/printergroup');
            }
            
            $data = array();
            $data['groupId'] = $printerGroupData['id'];
            $data['groupName'] = $printerGroupData['printerGroupName'];
            $data['IPAddress'] = isset($printerGroupData['printerIPAddress']) ? 
                $printerGroupData['printerIPAddress'] : '';
            $printerIPs = array();
            foreach ($printerGroupData['category'] as $key => $value) {
                $printerIPs[$key] = $value['ipaddress'];
            }
            
            $this->view->data = $data;
            $this->view->printerIPs = $printerIPs;
        } else {
            $data = $this->formData->getData();
            $this->view->data = $data;  
            
            $printerIPs = array();
            if (isset($data['printerIP']) && is_array($data['printerIP'])) {
                foreach ($data['printerIP'] as $index => $value) {
                    $printerIPs[$data['hPrinterIP'][$index]] = $value;
                }
            }
            $this->view->printerIPs = $printerIPs;
        }
        $this->view->success = $session->success;
        unset($session->success);
        $this->view->confirmDel = $this->msgConfig->E900_Confirm_Delete;
        
        $categoryObj = new Application_Model_Category();
        $categoryData = $categoryObj->getData();
        Application_Model_Entity::natksort($categoryData);
        $this->view->categoryData = $categoryData;
    }

    public function editexecuteAction()
    {
        // Check Csrf
        $this->_helper->csrf->checkCsrf($this->getRequest());

        //---get data session
        $session = Globals::getSession();
        $postData = $this->getRequest()->getParams();
        $this->_helper->formData->removeCsrfData($postData);

        // Init Messages
    	$this->msgConfig    =  Zend_Registry::get('MsgConfig');
        $flash              = $this->_helper->getHelper('flashMessenger');
        if ($flash->hasMessages()) {
            $this->view->message = $flash->getMessages();
        }

        $form_data = $this->getRequest()->getPost();
        $this->_helper->formData->removeCsrfData($form_data);

        if ($this->_validate($form_data)) {
            $session->success = 1;

            //---Edit data
            $printerGroup = new Application_Model_PrinterGroup();
            
            $item = array();
            $item['id'] = $session->idEdit;
            $item['printerGroupName'] = $form_data['groupName'];
            $item['printerIPAddress'] = $form_data['IPAddress'];
            $categories = array();
            foreach ($form_data['printerIP'] as $index => $value) {
                if (!empty($value)) {
                    $categories[$form_data['hPrinterIP'][$index]] = array('ipaddress' => $value);
                }
            }
            $item['category'] = $categories;

            if (!$printerGroup->editPrinterGroupJsonData($item)) {
                $session->success = 0;
                $this->_redirect('/printergroup/edit');
            }

            Globals::log('Edit printer group ' . $session->idEdit, null, $this->company_code . '.log');
            Globals::log($data, null, $this->company_code . '.log');
            $this->_helper->flashMessenger->addMessage($this->msgConfig->E900_EditSuccessful);
            
            // Pack data
            DataPacker::packDataInBatch($session->company_link);
        } else {
            $this->_helper->formData->addData($form_data);
            $session->success = 0;
        }

        $this->_redirect('/printergroup/edit');
    }

    /**
     * delete printerGroup
     *
     * @return void
     * @author Lam Ngoc Thanh
     * @since 2013/05/14
     */
    public function deleteAction()
    {
        // Check Csrf
        $this->_helper->csrf->checkCsrf($this->getRequest());

        // Init Messages
        $this->msgConfig    =  Zend_Registry::get('MsgConfig');
        $flash              = $this->_helper->getHelper('flashMessenger');
        if ($flash->hasMessages()) {
            $this->view->message = $flash->getMessages();
        }

        //---get data session
        $session = Globals::getSession();

        $printerGroup = new Application_Model_PrinterGroup();

        //---get data post
        $postData = $this->getRequest()->getParams();
        $dataDel = isset($postData['id']) ? ($postData['id']) : '';

        if ($dataDel == '') {
            $this->_helper->flashMessenger->addMessage($this->msgConfig->I900_NotDataDelGroup);
            $this->_redirect('/printergroup/');
        }

        try {
            $printerGroup->deletePrinterGroupJsonData($dataDel);
            Globals::log('Delete the printer group ('.$dataDel.')', null, $this->company_code.'.log');
            $this->_helper->flashMessenger->addMessage($this->msgConfig->I000_DeleteSuccessful);
            $session->success = 1;
            
            // Pack data
            DataPacker::packDataInBatch($session->company_link);
        }
        catch (Exception $e) {
            Globals::log('Delete the Printergroup unsuccessful ('.$dataDel.')', null, $this->company_code.'.log');
            Globals::log($e);
            $this->_helper->flashMessenger->addMessage($this->msgConfig->E000_CanNotDelete);

        }

        // Pack data
        //DataPacker::packDataInBatch($session->company_link);

        $this->_redirect('/printergroup/');
    }

    /**
     * validate the value on the setting form
     *
     * @access private
     * @param  array data on the form
     * @return boole
     * @since  2012/07/06
     */

    private function _validate($form_data)
    {
        $result = true;
        $check  = new Application_Model_ValidateRules();

        $flash  = $this->_helper->getHelper('flashMessenger');
        if ($flash->hasMessages()) {
            $this->view->message = $flash->getMessages();
        }

        // グループ名
        if (!Zend_Validate::is($form_data['groupName'], 'NotEmpty')) {
            $this->_helper->flashMessenger->addMessage(array('groupName' => $this->msgConfig->E900_Require_GroupName));
            $result = false;
        } else if (!$check->checkSpecCharForName($form_data['groupName'])
           ) {
            $this->_helper->flashMessenger->addMessage(array('groupName' => $this->msgConfig->E900_Invalid_GroupName));
            $result = false;
        }
        
        // 会計伝票プリンター
        if (Zend_Validate::is($form_data['IPAddress'], 'NotEmpty')
                && (!$check->checkSpecCharForIP($form_data['IPAddress']))) {
            $this->_helper->flashMessenger->addMessage(array('IPAddress' => $this->msgConfig->E900_Invalid_PrinterIP));
            $result = false;
        }

        // カテゴリ名
        if (isset($form_data['printerIP']) && is_array($form_data['printerIP'])) {
            foreach ($form_data['printerIP'] as $index => $value) {
                if (Zend_Validate::is($value, 'NotEmpty')
                        && (!$check->checkSpecCharForIP($value))) {
                    $this->_helper->flashMessenger->addMessage(array('msg' . $form_data['hPrinterIP'][$index] => $this->msgConfig->E900_Invalid_PrinterIP));
                    $result = false;
                }
            }
        }

        return $result;
    }
}
