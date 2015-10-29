<?php

/**
 * Action ToppingController
 * PHP version 5.3.9
 * @author Nguyen Dinh Bao
 * @copyright Kobe Digital Labo, Inc
 * @since 2014/04/23
 */
class ToppingController extends Zend_Controller_Action {

    const ITEM_CODE = 'no';

    private $msgConfig;
    private $success;

    /**
     * Init values
     *
     * @return void
     * @author Nguyen Dinh Bao
     * @since 2014/04/23
     */
    public function init() {
        $session = Globals::getSession();
        $this->company_code = $session->company_code;
        $this->_fileConfig = Globals::getApplicationConfig('upload');
        $this->msgConfig = Zend_Registry::get('MsgConfig');
        $flash = $this->_helper->getHelper('flashMessenger');

        if ($flash->hasMessages()) {
            $this->view->message = $flash->getMessages();
        }
    }

    /**
     * Index
     *
     * @return void
     * @author Nguyen Dinh Bao
     * @since 2014/04/23
     */
    public function indexAction() {
        // Init Csrf
        $this->view->csrf = $this->_helper->csrf->initInput();

        $session = Globals::getSession();
        $this->view->success = $session->success;

        $flashMsg = $this->_helper->getHelper('flashMessenger');
        if ($flashMsg->hasMessages()) {
            $this->view->message = $flashMsg->getMessages();
        }

        //get data
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

            $toppingGroup = new Application_Model_Topping();
            $this->view->dataToppingList = $toppingGroup->getDataViewList($postData, $page, $limit);
        } catch (Kdl_Ipadso_Csv_Exception $e) {
            Globals::logException($e);
        }

        $this->view->alertDel = $this->msgConfig->E306_NotDataDelSubcomment;
        $this->view->confirmDel = $this->msgConfig->E306_Confirm_Delete;

        //---get setting data
        $settingModel = new Application_Model_Setting();
        $this->file = $settingModel->getFilePath();

        // If file does not exist then create empty file
        if (!is_file($this->file)) {
            $config = new Zend_Config(array(), true);
            $writer = new Zend_Config_Writer_Json();
            $writer->setConfig($config)
                    ->setFilename($this->file)
                    ->write();
        }
        $this->formData = $this->_helper->getHelper('formData');
        $content = @file_get_contents($this->file);
        $json_setting = json_decode($content, true);
        $formData = $this->_helper->getHelper('formData');

        $data = $formData->getData();
        if (isset($data['stationAddress'])) {
            $data = $formData->getData();
        } else {
            $data = $json_setting;
        }
        $this->view->datasetting = $data;
    }

    /**
     * Add new a topping group
     *
     * @return void
     * @author Nguyen Dinh Bao
     * @since 2014/04/23
     */
    public function addAction() {
        // Init Csrf        
        $this->view->csrf = $this->_helper->csrf->initInput();
		
		//check sumareji
		$settingModel = new Application_Model_Setting();
        $this->file = $settingModel->getFilePath();
        $content = @file_get_contents($this->file);
        $json_setting = json_decode($content,true);
        if ($json_setting['linkSystem'] == 2) {
            $this->_redirect('/topping');
        }

        $this->view->headScript()->offsetSetFile(20, (Globals::isMobile() ? '/sp' : '/pc') . '/js/jquery.dataTables.min.js');

        $session = Globals::getSession();

        $flash = $this->_helper->getHelper('flashMessenger');
        if ($flash->hasMessages()) {
            $this->view->message = $flash->getMessages();
        }

        $this->formData = $this->_helper->getHelper('formData');
        $this->_helper->formData->addData($this->formData->getData());
        if ($this->formData->hasData()) {
            $this->view->data = $this->formData->getData();
        }

        $toppingGroup = new Application_Model_Topping();
        $newNo = $toppingGroup->getMaxItemToppingGroupId();

        $this->view->itemToppingGroupId = $newNo ? $newNo : 1;
        $this->view->success = $session->success;
        $this->view->confirmAdd = $this->msgConfig->E307_Confirm_Add;
        //get url json data to fill into combobox
        //$this->view->dataIndex = '/subcomment/jsonindex';        
    }

    /**
     * Add new a topping group - Execute
     *
     * @return void
     * @author Nguyen Dinh Bao
     * @since 2014/04/23
     */
    public function addexecuteAction() {
        // Check Csrf
        $this->_helper->csrf->checkCsrf($this->getRequest());

        $session = Globals::getSession();
        $this->toppingGroup = new Application_Model_Topping();
        $toppingGroupItem = new Application_Model_ToppingGroupItem();
        
        $req = Zend_Controller_Front::getInstance()->getRequest();
        $this->action = $req->getActionName();

        $this->msgConfig = Zend_Registry::get('MsgConfig');
        $flash = $this->_helper->getHelper('flashMessenger');
        if ($flash->hasMessages()) {
            $this->view->message = $flash->getMessages();
        }

        $form_Data = $this->getRequest()->getPost();
        $this->_helper->formData->removeCsrfData($form_Data);          
                
        $product_sort = array();
        $product_list = array_filter(array_slice($form_Data, 4));    
        $i = 1;
        foreach($product_list as $key => $value){
            $product_sort['menu'.$i] = $value;
            $i++;
        }                           
        $form_Data = array_slice($form_Data, 0, 4) + $product_sort;
        
        if ($this->_validate($form_Data)) {
            try {                
                $data = $this->toppingGroup->changeFormData($form_Data);
                $topping_group = array_slice($form_Data, 0, 4);
                $topping_group_item = array_slice($form_Data, 4);
                $item_list = array("itemToppingId","itemToppingGroupId","itemId");
                
                $maxItemToppingId = $toppingGroupItem->getMaxItemToppingId();
                $itemToppingId = $maxItemToppingId ? $maxItemToppingId : 1;                              
                foreach($topping_group_item as $item){
                    $values = array($itemToppingId, $topping_group['itemToppingGroupId'], $item);
                    $itemToppingRecord = array_combine($item_list, $values);                   
                    $dataItem = Array($itemToppingId => $itemToppingRecord);                    
                    $toppingGroupItem->insert($dataItem);
                    $itemToppingId ++;
                }                
                $data = Array($topping_group['itemToppingGroupId'] => $topping_group);
                
                $session->success = 1;                
                $this->toppingGroup->insert($data);

                $this->view->data = $data[$form_Data['itemToppingGroupId']];

                Globals::log('add topping (' . $form_Data['itemToppingGroupId'] . ')', null, $this->company_code . '.log');
                Globals::log($data, null, $this->company_code . '.log');
                $this->_helper->flashMessenger->addMessage($this->msgConfig->I800_AddToppingGroupSuccessful);

				unset($session->paramsCategory);
                // Pack data
                DataPacker::packDataInBatch($session->company_link);
                if (isset($_SESSION['num_sel_prod2']))
                    unset($_SESSION['num_sel_prod2']);
                $this->_redirect('/topping/edit/id_edit/' . $form_Data['itemToppingGroupId']);
            } catch (Exception $e) {
                $session->success = 0;
                Globals::log('add unsuccessful.', null, $this->company_code . '.log');
                Globals::logException($e);
                $this->_helper->flashMessenger->addMessage($this->msgConfig->E800_CanNotAddTopping);
                $this->_redirect('/topping/add');
            }
        } else {
            $session->success = 0;
            $this->_redirect('/topping/add');
        }
    }

    /**
     * Edit new a topping group - Execute
     *
     * @return void
     * @author Nguyen Dinh Bao
     * @since 2014/04/23
     */
    public function editAction() {
        // Init Csrf
        $this->view->csrf = $this->_helper->csrf->initInput();

        $this->view->headScript()->offsetSetFile(20, (Globals::isMobile() ? '/sp' : '/pc') . '/js/jquery.dataTables.min.js');

        $session = Globals::getSession();
        $postData = $this->getRequest()->getParams();

        $idEdit = isset($postData['id_edit']) ? ($postData['id_edit']) : $session->idEdit;
        $session->idEdit = $idEdit;
        
        $this->view->msg = isset($postData['msg']) ? ($postData['msg']) : '';
        if ($this->view->msg != 1 && isset($session->nameTopping)) {
            unset($session->nameTopping);
        }

        $session->pos = $session->paramsCategory;

        $flash = $this->_helper->getHelper('flashMessenger');
        if ($flash->hasMessages()) {
            $this->view->message = $flash->getMessages();
        }

        $this->formData = $this->_helper->getHelper('formData');
        $this->_helper->formData->addData($this->formData->getData());

        if ($idEdit == '') {
            $this->view->success = 0;
            $this->_helper->flashMessenger->addMessage($this->msgConfig->E301_NotGetDataToEdit);
            $this->_redirect('/topping');
        }
        
        $toppingGroupItem = new Application_Model_ToppingGroupItem();
        $dataItem = $toppingGroupItem->getToppingGroupItemById($idEdit);

        if (count($this->formData->getData()) == 0) {
            $toppingGroup = new Application_Model_Topping();
            $data = $toppingGroup->findRowByKey($idEdit);                        
           
            if ($data == false) {
                $this->view->success = 0;
                $this->_helper->flashMessenger->addMessage(
                        sprintf($this->msgConfig->E307_NotExist_Subcomment, $idEdit)
                );
                $this->_redirect('/topping');
            }

            $msg = array();
            $result = $toppingGroupItem->checkProduct($dataItem);
            
            // No exist product(s)
            if (isset($result['exist']) && count($result['exist'])) {
                foreach ($result['exist'] as $num => $code) {
                    $items[] = "商品{$num}: {$code}";
                }
                $msg[] = sprintf(
                        $this->msgConfig->E307_NotExist_Product, implode(', ', $items)
                );
            }
            // No exist subcomment product(s)
            if (isset($result['sub']) && count($result['sub'])) {
                foreach ($result['sub'] as $num => $code) {
                    $items[] = "商品{$num}: {$code}";
                }
                $msg[] = sprintf(
                        $this->msgConfig->E307_NotExist_SubProduct, implode(', ', $items)
                );
            }

            if (count($msg)) {
                $this->view->message = $msg;
            }
            $this->view->data = $data[$idEdit];
            $this->view->dataItem = $dataItem;
        } else {          
            $this->view->data = $this->formData->getData();          
            $this->view->dataItem = $dataItem;
        }
        $this->view->success = $session->success;
        //$this->view->countMenu = $this->getCountMenu();
        $this->view->confirmEdit = $this->msgConfig->E307_Confirm_Edit;
        $this->view->confirmDel = $this->msgConfig->E800_Confirm_Delete;
				
		if (is_bool($session->removeFirstLineErrorMessage) && $session->removeFirstLineErrorMessage) {
			$this->view->removeFirstLineErrorMessage = true;
		} else {
			$this->view->removeFirstLineErrorMessage = false;
		}
		unset($session->removeFirstLineErrorMessage);
        //$this->view->dataIndex = '/subcomment/jsonindex';
    }

    /**
     * Edit new a topping group - Execute
     *
     * @return void
     * @author Nguyen Dinh Bao
     * @since 2014/04/23
     */
    public function editexecuteAction() {
        // Check Csrf
        $this->_helper->csrf->checkCsrf($this->getRequest());
        $dataCheck = array();

        $req = Zend_Controller_Front::getInstance()->getRequest();
        $this->action = $req->getActionName();

        $this->toppingGroup = new Application_Model_Topping();
        $toppingGroupItem = new Application_Model_ToppingGroupItem();
        $this->check = new Application_Model_ValidateRules();

        $session = Globals::getSession();
        $this->idEdit = $session->idEdit;

        $form_Data = $this->getRequest()->getPost();
        $this->_helper->formData->removeCsrfData($form_Data);

        $this->msgConfig = Zend_Registry::get('MsgConfig');
        $flash = $this->_helper->getHelper('flashMessenger');
        if ($flash->hasMessages()) {
            $this->view->message = $flash->getMessages();
        }  
        
        if ($this->_validate($form_Data)) {
            try {
                $data = $this->toppingGroup->changeFormData($form_Data);                                
                $session->success = 1;
                
                $topping_group = array_slice($form_Data, 0, 4);
                $topping_group_item = array_slice($form_Data, 4);
                $item_list = array("itemToppingId","itemToppingGroupId","itemId");                
                $maxItemToppingId = $toppingGroupItem->getMaxItemToppingId();              
                $itemToppingId = $maxItemToppingId ? $maxItemToppingId : 1;                                
                
                // Put each pair (itemToppingId, itemId) into a array
                $topping_group_item_row = array();
                $array = array();
                for($i = 1; $i <= count($topping_group_item)/2; $i++){
                    $array = ($i==1) ? $topping_group_item : $after;
                    $topping_group_item_row[$i] = array_slice($array, 0, 2);
                    $after = array_slice($array, 2);             
                }
                
                // Insert and update values
                $i = 1;
                $id_list = array();
                foreach($topping_group_item_row as $item) {                    
                    if($item['menu'.$i] == '' && $item['itemToppingId'.$i] != -1){
                        $toppingGroupItem->deleteByKey($item['itemToppingId'.$i]);
                    }
                    else if($item['menu'.$i] != '' && $item['itemToppingId'.$i] == -1){
                        $values = array($itemToppingId, $this->idEdit, $item['menu'.$i]);
                        $itemToppingRecord = array_combine($item_list, $values);
                        $dataItem = Array($itemToppingId => $itemToppingRecord);
                        array_push($id_list, $itemToppingId);
                        $itemToppingId ++;
                        $toppingGroupItem->insert($dataItem);
                    }
                    else if($item['menu'.$i] != '' && $item['itemToppingId'.$i] != -1){
                        $values = array($item['itemToppingId'.$i], $this->idEdit, $item['menu'.$i]);
                        $itemToppingRecord = array_combine($item_list, $values);
                        $dataItem = Array($item['itemToppingId'.$i] => $itemToppingRecord);
                        array_push($id_list, $item['itemToppingId'.$i]);
                        $toppingGroupItem->update($dataItem);
                    }
                    $i++;
                }
               
                $toppingGroupItem->deleteRowToppingItemNotInArray($id_list, $this->idEdit);
                
                $data = array($this->idEdit => $topping_group);               
                $this->toppingGroup->update($data);
                //$this->view->data = $data[$this->idEdit];
                $this->view->data = $toppingGroupItem->getToppingGroupItemById($this->idEdit);               
           
                Globals::log('Edit the topping (' . $form_Data['itemToppingGroupId'] . ')', null, $this->company_code . '.log');
                Globals::log($data, null, $this->company_code . '.log');
                $this->_helper->flashMessenger->addMessage($this->msgConfig->I800_EditSuccessful);

				unset($session->paramsCategory);
                // Pack data
                DataPacker::packDataInBatch($session->company_link);

                $this->_redirect('/topping/edit');
            } catch (Exception $e) {
                $session->success = 0;
                Globals::log('Update the topping group unsuccessful (' . $form_Data['itemToppingGroupId'] . ')', null, $this->company_code . '.log');
                Globals::logException($e);
                $this->_helper->flashMessenger->addMessage($this->msgConfig->E304_CanNotEdit);
                $this->_redirect('/topping/edit');
            }
        } else {
            $session->success = 0;
            $this->_redirect('/topping/edit/msg/1');
        }
    }  

    /**
     * Delete a topping group
     *
     * @return void
     * @author Nguyen Dinh Bao
     * @since 2014/04/23
     */
    public function deleteAction() {
        // Check Csrf
        $this->_helper->csrf->checkCsrf($this->getRequest());

        $session = Globals::getSession();
        $session->success = 0;

        $arrNotDel = Array();
        $postData = $this->getRequest()->getParams();
        $dataDel = isset($postData['id_edit']) ? ($postData['id_edit']) : '';
        
        if ($dataDel == '') {
            $this->_helper->flashMessenger->addMessage($this->msgConfig->E311_NotDataDelSubcomment);
            $this->_redirect('/topping/');
        }
        $dataDel = explode(',', $dataDel);

        $toppingGroup = new Application_Model_Topping();
        $toppingGroupItem = new Application_Model_ToppingGroupItem();
        $toppingGroup->fetchAll();
        $data = $toppingGroup->getData();
        
        foreach ($dataDel as $key => $value) {
            $rowFind = $data[$value];
            $result = $toppingGroup->checkForgeinKey($value);
            if ($result == 1) {
                try {
                    $toppingGroup->deleteByKey($value);
                    $toppingGroupItem->deleteRowByItemToppingGroupId($value);
                    Globals::log('Delete the topping group (' . $value . ')', null, $this->company_code . '.log');
                } catch (Exception $e) {
                    Globals::log('Delete the topping group unsuccessful (' . $value . ')', null, $this->company_code . '.log');
                    $this->_helper->flashMessenger->addMessage($this->msgConfig->E000_CanNotDelete);
                }
            } else {
                $arrNotDel[] = $rowFind;
            }
        }
        if (count($arrNotDel) == 0) {
            $this->_helper->flashMessenger->addMessage($this->msgConfig->I800_DeleteSuccessful);
            $session->success = 1;
        } else {
            $this->_helper->flashMessenger->addMessage($this->msgConfig->E300_ProductForeign);
            $session->success = 0;
			$session->removeFirstLineErrorMessage = true;
			$this->_redirect('/topping/edit');
        }

        // Pack data
        DataPacker::packDataInBatch($session->company_link);

        $this->_redirect('/topping/');
    }

    /**
     * Validate the value on the product form (edit, add)
     *
     * @return data
     * @param  array data on the form
     * @author Nguyen Dinh Bao
     * @since 2014/04/23
     */
    private function _validate($form_data) {
        $result = true;
        $check = new Application_Model_ValidateRules();

        $flash = $this->_helper->getHelper('flashMessenger');
        if ($flash->hasMessages()) {
            $this->view->message = $flash->getMessages();
        }
        
        // 番号
        if (!Zend_Validate::is($form_data['itemToppingGroupId'], 'NotEmpty')) {
            $this->_helper->flashMessenger->addMessage($this->msgConfig->E307_Require_No);
            $result = false;
        } else {
            if (!Zend_Validate::is($form_data['itemToppingGroupId'], 'Digits')) {
                $this->_helper->flashMessenger->addMessage($this->msgConfig->E307_Invalid_No);
                $result = false;
            }
        }
        if (($this->action == 'addexecute')
                && ($this->toppingGroup->findRowByKey($form_data['itemToppingGroupId']) != '')
        ) {
            $this->_helper->flashMessenger->addMessage($this->msgConfig->E307_ExistSubcomment);
            $result = false;
        }
        if (($this->action == 'editexecute')
                && ($this->idEdit != $form_data['itemToppingGroupId'])
                && ($this->toppingGroup->findRowByKey($form_data['itemToppingGroupId']) != '')
        ) {
            $this->_helper->flashMessenger->addMessage($this->msgConfig->E307_ExistSubcomment);
            $result = false;
        }

        // サブコメント
        if (!Zend_Validate::is($form_data['itemToppingGroupName'], 'NotEmpty')) {
            $this->_helper->flashMessenger->addMessage(array('itemToppingGroupName' => $this->msgConfig->E800_Require_name));
            $result = false;
        } else {
            if (!$check->checkSpecCharForName($form_data['itemToppingGroupName'])) {
                $this->_helper->flashMessenger->addMessage(array('itemToppingGroupName' => $this->msgConfig->E800_Invalid_name));
                $result = false;
            }
            if (strlen($form_data['itemToppingGroupName']) > 255) {
                $this->_helper->flashMessenger->addMessage(array('itemToppingGroupName' => $this->msgConfig->E800_Maxlength_name));
                $result = false;
            }
        }        
        
        // トッピング選択条件
        //if($form_data['min']!= '' && $form_data['max']!= ''){
            if ($form_data['min']!= '' && !Zend_Validate::is($form_data['min'], 'Digits')) {
                $this->_helper->flashMessenger->addMessage(array('min' => $this->msgConfig->E800_Invalid_Data));
                $result = false;
            }

            if ($form_data['max']!= '' &&!Zend_Validate::is($form_data['max'], 'Digits')) {
                $this->_helper->flashMessenger->addMessage(array('max' => $this->msgConfig->E800_Invalid_Data));
                $result = false;
            }

            if ($form_data['min']!= '' && strlen($form_data['min']) > 3) {
                $this->_helper->flashMessenger->addMessage(array('min' => $this->msgConfig->E800_Invalid_Data));
                $result = false;
            }

            if ($form_data['max']!= '' && strlen($form_data['max']) > 3) {
                $this->_helper->flashMessenger->addMessage(array('max' => $this->msgConfig->E800_Invalid_Data));
                $result = false;
            }

            /*if ($form_data['min'] >= $form_data['max']) {
                $this->_helper->flashMessenger->addMessage(array('max' => "min  >= max"));
                $result = false;
            }*/
        //}
        /*elseif(($form_data['min']== '' && $form_data['max']!= '') || ($form_data['min']!= '' && $form_data['max']== '')){
            $this->_helper->flashMessenger->addMessage(array('min' => "min or max not null"));
            $result = false;
        }*/

        // Begin 商品 Validate        
        $indexModel = new Application_Model_Index();
        $products = $indexModel->getData();
        $errorCount = 0;
        if ($this->action == 'addexecute') {
            $count = count($form_data) - 4;            
            $_SESSION['num_sel_prod2'] = $count;
            for ($i = 1; $i <= $count; $i++) {
                $menuKey = "menu{$i}";

                // If empty data
                if ($form_data[$menuKey] == "" || $form_data[$menuKey] == Null) {
                    
                }

                // If product not exist
                if ($form_data[$menuKey]!='' && !array_key_exists($form_data[$menuKey], $products)) {
                    $this->_helper->flashMessenger->addMessage(array($menuKey =>
                        sprintf($this->msgConfig->E307_Invalid_Product, $i))
                    );
                    // Set empty data
                    $form_data[$menuKey] = '';
                    $errorCount++;
                }
            }
        }
        if ($this->action == 'editexecute') {
            for ($i = 0; $i < Globals::getApplicationConfig('subcomment')->max_list; $i++) {
                $menuKey = "menu{$i}";
                
                // If empty data
                if (isset($form_data[$menuKey])) {
                    // If product not exist
                    if ($form_data[$menuKey]!='' && !array_key_exists($form_data[$menuKey], $products)) {                        
                        $this->_helper->flashMessenger->addMessage(array($menuKey =>
                            sprintf($this->msgConfig->E307_Invalid_Product, $i))
                        );
                        // Set empty data
                        $form_data[$menuKey] = '';
                        $errorCount++;
                    }
                }
            }
        }
        
        if ($errorCount) {
            $this->_helper->formData->addData($form_data);
            return false;
        }
        // End 商品 Validate      
        $this->_helper->formData->addData($form_data);

        return $result;
    }
}
