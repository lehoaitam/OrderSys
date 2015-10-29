<?php
/**
 * Action SubCommentController
 * PHP version 5.3.9
 * @author Nguyen Dinh Bao
 * @copyright Kobe Digital Labo, Inc
 * @since 2014/04/18
 */
class SubcommentController extends Zend_Controller_Action {

    const ITEM_CODE = 'no';

    private $msgConfig;
    private $success;

    /**
     * Init values
     *
     * @return void
     * @author Nguyen Dinh Bao
     * @since 2014/04/18
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
     * Get count menu
     * 
     * @return int
     * @author Nguyen Dinh Bao
     * @since 2014/04/18
     */
    public function getCountMenu() {
        $csvSubcomment = new Application_Model_SubComment();
        return $csvSubcomment->getCountMenu();
    }

    /**
     * Index
     *
     * @return void
     * @author Nguyen Dinh Bao
     * @since 2014/04/18
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

            $subComment = new Application_Model_SubComment();                                    
            $this->view->dataCustomerOrderList = $subComment->getDataViewList($postData, $page, $limit);
        } catch (Kdl_Ipadso_Csv_Exception $e) {
            Globals::logException($e);
        }

        $this->view->alertDel = $this->msgConfig->E306_NotDataDelSubcomment;
        $this->view->confirmDel = $this->msgConfig->E306_Confirm_Delete;
        $this->view->dataSubCommentList = '/subcomment/data';

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
     * get data to view on the list
     *
     * @return data has json style
     * @author Nguyen Dinh Bao
     * @since 2014/04/18
     */
    public function dataAction() {
        $data = array();
        try {
            $session = Globals::getSession();
            if ($session->pos) {
                $postData = $session->pos;
                unset($session->pos);
            } else {
                $postData = $this->getRequest()->getParams();
            }
            $session->paramsCategory = $postData;

            $page = isset($postData['page']) ? intval($postData['page']) : 1;
            $limit = isset($postData['rows']) ? intval($postData['rows']) : 10;

            $subComment = new Application_Model_SubComment();            
            $data = $subComment->getDataViewList($postData, $page, $limit);
        } catch (Kdl_Ipadso_Csv_Exception $e) {
            Globals::logException($e);
        }

        $this->_helper->json($data);
    }

    /**
     * Add new a subcomment
     *
     * @return void
     * @author Nguyen Dinh Bao
     * @since 2014/04/18
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
            $this->_redirect('/subcomment');
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

        $csvSubcomment = new Application_Model_SubComment();
        $newNo = $csvSubcomment->getMaxNo();
       
        $this->view->no = $newNo ? $newNo : 1;
        $this->view->success = $session->success;
        $this->view->countMenu = $this->getCountMenu();
        $this->view->confirmAdd = $this->msgConfig->E307_Confirm_Add;
        //get url json data to fill into combobox
        $this->view->dataIndex = '/subcomment/jsonindex';
    }

    /**
     * Add new a subcomment - Execute
     *
     * @return void
     * @author Nguyen Dinh Bao
     * @since 2014/04/18
     */
    public function addexecuteAction() {
        // Check Csrf
        $this->_helper->csrf->checkCsrf($this->getRequest());

        $session = Globals::getSession();
        $this->csvSubcomment = new Application_Model_SubComment();

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
        $product_list = array_filter(array_slice($form_Data, 2));    
        $i = 1;
        foreach($product_list as $key => $value){
            $product_sort['menu'.$i] = $value;
            $i++;
        }                           
        $form_Data = array_slice($form_Data, 0, 2) + $product_sort;
        
        if ($this->_validate($form_Data)) {
            try {                
                $data = $this->csvSubcomment->changeFormData($form_Data);
                $data = Array($form_Data['no'] => $form_Data);
                
                $session->success = 1;                
                $this->csvSubcomment->insert($data);
                
                $this->view->data = $data[$form_Data['no']];
                
                Globals::log('add subcomment (' . $form_Data['no'] . ')', null, $this->company_code . '.log');
                Globals::log($data, null, $this->company_code . '.log');
                $this->_helper->flashMessenger->addMessage($this->msgConfig->I307_AddSubCommentSuccessful);

				unset($session->paramsCategory);
                // Pack data
                DataPacker::packDataInBatch($session->company_link);
                if (isset($_SESSION['num_sel_prod']))
                    unset($_SESSION['num_sel_prod']);
                $this->_redirect('/subcomment/edit/id_edit/'.$form_Data['no']);
            } catch (Exception $e) {
                $session->success = 0;
                Globals::log('add unsuccessful.', null, $this->company_code . '.log');
                Globals::logException($e);
                $this->_helper->flashMessenger->addMessage($this->msgConfig->E307_CanNotAddSubcomment);
                $this->_redirect('/subcomment/add');
            }
        } else {
            $session->success = 0;
            $this->_redirect('/subcomment/add');
        }
    }

    /**
     * Edit new a subcomment - Execute
     *
     * @return void
     * @author Nguyen Dinh Bao
     * @since 2014/04/18
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
        if($this->view->msg=='' && isset($session->nameCustomerOrder)){ 
            unset($session->nameCustomerOrder);
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
            $this->_redirect('/subcomment');
        }

        if (count($this->formData->getData()) == 0) {
            $csvSubcomment = new Application_Model_SubComment();
            $data = $csvSubcomment->findRowByKey($idEdit);

            if ($data === false) {
                $this->view->success = 0;
                $this->_helper->flashMessenger->addMessage(
                        sprintf($this->msgConfig->E307_NotExist_Subcomment, $idEdit)
                );
                $this->_redirect('/subcomment');
            }

            $msg = array();
            $result = $csvSubcomment->checkSubcommentData($data[$idEdit]);
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
        } else {            
            $this->view->data = $this->formData->getData();
        }
        $this->view->success = $session->success;
        $this->view->countMenu = $this->getCountMenu();
        $this->view->confirmEdit = $this->msgConfig->E307_Confirm_Edit;
        $this->view->confirmDel = $this->msgConfig->E306_Confirm_Delete;
        $this->view->dataIndex = '/subcomment/jsonindex';
    }

    /**
     * Edit new a subcomment - Execute
     *
     * @return void
     * @author Nguyen Dinh Bao
     * @since 2014/04/18
     */
    public function editexecuteAction() {
        // Check Csrf
        $this->_helper->csrf->checkCsrf($this->getRequest());
        $dataCheck = array();
        
        $req = Zend_Controller_Front::getInstance()->getRequest();
        $this->action = $req->getActionName();
        
        $this->csvSubcomment = new Application_Model_SubComment();
        $this->check    = new Application_Model_ValidateRules();
        
        $session = Globals::getSession();
        $this->idEdit = $session->idEdit;
        
        $form_Data = $this->getRequest()->getPost();
        $this->_helper->formData->removeCsrfData($form_Data);

        $this->msgConfig = Zend_Registry::get('MsgConfig');
        $flash = $this->_helper->getHelper('flashMessenger');
        if ($flash->hasMessages()) {
            $this->view->message = $flash->getMessages();
        }
        
        $product_sort = array();
        $product_list = array_filter(array_slice($form_Data, 2));    
        $i = 1;
        foreach($product_list as $key => $value){
            $product_sort['menu'.$i] = $value;
            $i++;
        }                           
        $form_Data = array_slice($form_Data, 0, 2) + $product_sort;
        
        if ($this->_validate($form_Data)) {            
            try {
                $data = $this->csvSubcomment->changeFormData($form_Data);
                $data = array($this->idEdit => $form_Data);
                
                $session->success = 1;

                $this->csvSubcomment->update($data);                
                $this->view->data = $data[$this->idEdit];
                
                Globals::log('Edit the subcoment (' . $form_Data['no'] . ')', null, $this->company_code . '.log');
                Globals::log($data, null, $this->company_code . '.log');
                $this->_helper->flashMessenger->addMessage($this->msgConfig->I307_EditSuccessful);

				unset($session->paramsCategory);
                // Pack data
                DataPacker::packDataInBatch($session->company_link);
                
                $this->_redirect('/subcomment/edit');
            } catch (Exception $e) {
                $session->success = 0;
                Globals::log('Update the subcoment unsuccessful (' . $form_Data['no'] . ')', null, $this->company_code . '.log');
                Globals::logException($e);
                $this->_helper->flashMessenger->addMessage($this->msgConfig->E304_CanNotEdit);
                $this->_redirect('/subcomment/edit');
            }
        } else {
            $session->success = 0;           
            $this->_redirect('/subcomment/edit/msg/1');
        }
    }

    /**
     * @action: import category
     *
     * @return void
     * @author Nguyen Dinh Bao
     * @since 2012/08/02
     */
    public function importAction() {
        // Init Csrf
        $this->view->csrf = $this->_helper->csrf->initInput();

        $session = Globals::getSession();
        $this->view->success = $session->success;
        $flashMsg = $this->_helper->getHelper('flashMessenger');
        if ($flashMsg->hasMessages()) {
            $this->view->message = $flashMsg->getMessages();
        }

        $this->view->alertImp = $this->msgConfig->E300_RequireFileUpload;
        $this->view->alertImportType = $this->msgConfig->E300_RequireImportType;
        $this->view->confiemImpCsv = $this->msgConfig->E300_Confirm_ImportCsv;

        // CSVアップロード処理
        $this->view->importType = Application_Model_UploadCsv::getImportType('common');
        $this->view->importTypeDefault = Application_Model_UploadCsv::getImportTypeDefault('common');
    }

    /**
     * @action: import subcomment (.pos)
     *
     * @return void
     * @author Nguyen Dinh Bao
     * @since 2012/08/11
     */
    public function uploadposAction() {
        // Check Csrf
        //$this->_helper->csrf->checkCsrf($this->getRequest());

        $session = Globals::getSession();
        $session->success = 0;
        $file_name = $_FILES['page_pos']['name'];
        $mdelUp = new Application_Model_UploadBinary($file_name);

        $result = $mdelUp->checkFileUpload();
        if (is_array($result) && count($result)) {
            if (isset($result['no_file'])) {
                $this->_helper->flashMessenger->addMessage($result['no_file']);
            }
            if (isset($result['invalid'])) {
                foreach ($result['invalid'] as $errorMsg) {
                    $this->_helper->flashMessenger->addMessage($errorMsg);
                }
            }
            $session->success = 0;
            $this->_redirect('/product/import');
        }

        $fileNameDown = $mdelUp->makeSubcommentFile();
        if ($fileNameDown !== false) {
            $session->success = 1;
            $this->_helper->layout->disableLayout();
            $this->_helper->viewRenderer->setNoRender();

            Application_Model_UploadBinary::createDownloadCsvFile(
                    $this, $fileNameDown, Application_Model_SubComment::MAIN_FILE
            );
        } else {
            $this->_helper->flashMessenger->addMessage($this->msgConfig->E300_FileUploadInvalid);
            $session->success = 0;
            $this->_redirect('/subcomment/import');
        }
    }

    /**
     * @action: import subcomment (.csv)
     *
     * @return void
     * @author Nguyen Dinh Bao
     * @since 2012/08/02
     */
    public function uploadcsvAction() {
        // Check Csrf
        $this->_helper->csrf->checkCsrf($this->getRequest());

        $session = Globals::getSession();
        $session->success = 0;

        $csvSubcomment = new Application_Model_SubComment();
        $csvSubcomment->fetchAll();

        $session = Globals::getSession();
        $file_name = $_FILES['page_csv']['name'];
        $file_type = 'page_csv';
        $mdelUp = new Application_Model_UploadCsv(self::ITEM_CODE);
        $checkFile = $mdelUp->checkFile($file_type, $file_name);

        if ($checkFile !== 1) {
            $this->_helper->flashMessenger->addMessage($this->msgConfig->$checkFile);
        } else {

            //import file
            $file_name_up = $mdelUp->copyFileCsv($file_name);
            if ($file_name_up !== 0) {

                //check header file upload and file main
                if (count(array_diff($csvSubcomment->getHeader(), $mdelUp->getHeader($file_name_up))) != 0) {
                    $this->_helper->flashMessenger->addMessage($this->msgConfig->E300_FileUploadInvalid);
                } else {
                    $uploadType = $this->getRequest()->getParam('upload_type');
                    switch ($uploadType) {
                        // 全てのデータを削除後、データの取り込みを行う
                        case $this->_fileConfig->csv->new_import:
                            if (!copy($file_name_up, Globals::getDataFilePath(Application_Model_SubComment::MAIN_FILE))) {
                                throw new Application_Model_Exception(
                                        sprintf($this->msgConfig->E000_Failed_CopyFile)
                                );
                            }
                            $rs = 1;
                            break;
                        // 同一ID以外のデータ取り込みを行う
                        case $this->_fileConfig->csv->update_import:
                            $data_imp = $mdelUp->getData($file_name_up, self::ITEM_CODE);
                            $data_del = array_intersect_assoc($data_imp, $csvSubcomment->getData());

                            $rs = $mdelUp->uploadCsvSubcomment($data_imp, $data_del);
                            break;
                    }

                    if ($rs == 1) {
                        $this->_helper->flashMessenger->addMessage($this->msgConfig->E308_ImportSubcommentSuccessful);
                        $session->success = 1;

                        // Pack data
                        DataPacker::packDataInBatch($session->company_link);
                    } else {
                        $this->_helper->flashMessenger->addMessage($this->msgConfig->$rs);
                        $session->success = 0;
                    }
                }
            } else {
                $this->_helper->flashMessenger->addMessage($this->msgConfig->E308_CantImportSubcomment);
            }
        }
        $this->_redirect('/subcomment/import');
    }

    /**
     * Delete new subcomments
     *
     * @return void
     * @author Nguyen Dinh Bao
     * @since 2014/04/18
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
            $this->_redirect('/subcomment/');
        }
        $dataDel = explode(',', $dataDel);

        $scvSubcomment = new Application_Model_SubComment();
        $scvSubcomment->fetchAll();
        $data = $scvSubcomment->getData();

        foreach ($dataDel as $key => $value) {
            $rowFind = $data[$value];
            //$result = $scvSubcomment->checkForgeinKey($value);
             $result = 1;
            if ($result == 1) {
                try {
                    $scvSubcomment->deleteByKey($value);
                    Globals::log('Delete the subcomment (' . $value . ')', null, $this->company_code . '.log');
                } catch (Exception $e) {
                    Globals::log('Delete the subcomment unsuccessful (' . $value . ')', null, $this->company_code . '.log');
                    $this->_helper->flashMessenger->addMessage($this->msgConfig->E000_CanNotDelete);
                }
            } else {
                $arrNotDel[] = $rowFind;
            }
        }
        if (count($arrNotDel) == 0) {
            $this->_helper->flashMessenger->addMessage($this->msgConfig->I307_DeleteSuccessful);
            $session->success = 1;
        } else {
            $this->_helper->flashMessenger->addMessage($this->msgConfig->E303_ProductForeign);
            $session->success = 0;
        }

        // Pack data
        DataPacker::packDataInBatch($session->company_link);

        $this->_redirect('/subcomment/');
    }

    /**
     * Get data from index.csv to fill menu combobox
     *
     * @return data
     * @author Nguyen Dinh Bao
     * @since 2014/04/18
     */
    public function jsonindexAction() {
        $csvIndex = new Application_Model_Index();
        $data = $csvIndex->getDataJson();
        $this->_helper->json($data);
    }

    /**
     * Validate the value on the product form (edit, add)
     *
     * @return data
     * @param  array data on the form
     * @author Nguyen Dinh Bao
     * @since 2014/04/18
     */
    private function _validate($form_data) {
        $result = true;
        $check = new Application_Model_ValidateRules();

        $flash = $this->_helper->getHelper('flashMessenger');
        if ($flash->hasMessages()) {
            $this->view->message = $flash->getMessages();
        }

        // 番号
        if (!Zend_Validate::is($form_data['no'], 'NotEmpty')) {
            $this->_helper->flashMessenger->addMessage($this->msgConfig->E307_Require_No);
            $result = false;
        } else {
            if (!Zend_Validate::is($form_data['no'], 'Digits')) {
                $this->_helper->flashMessenger->addMessage($this->msgConfig->E307_Invalid_No);
                $result = false;
            }
        }
        if (($this->action == 'addexecute')
                && ($this->csvSubcomment->findRowByKey($form_data['no']) != '')
        ) {
            $this->_helper->flashMessenger->addMessage($this->msgConfig->E307_ExistSubcomment);
            $result = false;
        }
        if (($this->action == 'editexecute')
                && ($this->idEdit != $form_data['no'])
                && ($this->csvSubcomment->findRowByKey($form_data['no']) != '')
        ) {
            $this->_helper->flashMessenger->addMessage($this->msgConfig->E307_ExistSubcomment);
            $result = false;
        }

        // サブコメント
        if (!Zend_Validate::is($form_data['guidance'], 'NotEmpty')) {
            $this->_helper->flashMessenger->addMessage(array('guidance' => $this->msgConfig->E307_Require_Guidance));            
            $result = false;
        } else {
            if (!$check->checkSpecCharForName($form_data['guidance'])) {
                $this->_helper->flashMessenger->addMessage(array('guidance' => $this->msgConfig->E307_Invalid_Guidance));
                $result = false;
            }
            if (strlen($form_data['guidance']) > 255) {
                $this->_helper->flashMessenger->addMessage(array('guidance' => $this->msgConfig->E307_Maxlength_Guidance));
                $result = false;
            }
        }

        // Begin 商品 Validate        
        $indexModel = new Application_Model_Index();
        $products = $indexModel->getData();
        $errorCount = 0;
        if($this->action == 'addexecute'){
            $count = count($form_data)-2;   
            $_SESSION['num_sel_prod'] = $count;
            for ($i = 1; $i <= $count; $i++) {
                $menuKey = "menu{$i}";

                // If empty data
                if ($form_data[$menuKey]=="" || $form_data[$menuKey]==Null){                
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
        if($this->action == 'editexecute'){
            for ($i = 1; $i <= Globals::getApplicationConfig('subcomment')->max_list; $i++) {
                $menuKey = "menu{$i}";           
                // If empty data
                if (isset($form_data[$menuKey])){                                                
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

    /**
     * Download csv data file
     * 
     * @author Nguyen Huu Tam
     * @since 2013/02/25
     */
    public function downloadCsvAction() {
        $downloadFile = Globals::getDataFilePath(Application_Model_SubComment::MAIN_FILE);

        // If no exist file
        if (!file_exists($downloadFile)) {
            $session->success = 0;
            $this->_helper->flashMessenger->addMessage(
                    sprintf(
                            $this->msgConfig->C000_FileNotFound, Application_Model_SubComment::MAIN_FILE
                    )
            );

            $this->_redirect('/subcomment/import');
        }

        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender();
        // Download file
        Application_Model_UploadBinary::createDownloadCsvFile(
                $this, $downloadFile, Application_Model_SubComment::MAIN_FILE
        );
    }

}
