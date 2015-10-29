<?php

/**
 * Action ProductController
 * PHP version 5.3.9
 * @author Nguyen Thi Tho
 * @copyright Kobe Digital Labo, Inc
 * @since 2012/07/10
 */
class ProductController extends Zend_Controller_Action {

    private $msgConfig;
    private $success;
    private $indexModel = null;

    const ITEM_CODE = 'menuCode';
    const IMAGE_NAME = 'image%s';
    const LINK_SYSTEM_PRINTER = '0';
    const LINK_SYSTEM_TEC = '1';

    /**
     * Init values
     *
     * @return void
     * @author Nguyen Thi Tho
     * @since 2012/07/10
     */
    public function init() {
        //---get session data
        $session = Globals::getSession();

        $this->indexModel = new Application_Model_Index();

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
     * @author Nguyen Thi Tho
     * @since 2012/07/10
     */
    public function indexAction() {
        // Init Csrf
        $this->view->csrf = $this->_helper->csrf->initInput();

        //---include library
        $this->view->headScript()->offsetSetFile(20, (Globals::isMobile() ? '/sp' : '/pc') . '/js/jquery.fancybox.js');
        $this->view->headLink()->appendStylesheet((Globals::isMobile() ? '/sp' : '/pc') . '/css/jquery.fancybox.css');

        //---get session data
        $session = Globals::getSession();

        $this->view->success = $session->success;
        $flashMsg = $this->_helper->getHelper('flashMessenger');
        if ($flashMsg->hasMessages()) {
            $this->view->message = $flashMsg->getMessages();
        }

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
            $this->view->dataProductList = $this->indexModel->getDataViewList($postData, $search, $page, $limit);
        } catch (Kdl_Ipadso_Csv_Exception $e) {
            Globals::logException($e);
        }
        $this->view->dataCategory1 = '/product/jsoncategory1';
        $this->view->alertDel = $this->msgConfig->E300_NotDataDelPrtoduct;
        $this->view->confirmDel = $this->msgConfig->E300_Confirm_Delete;
        $this->view->defaultPageSize = Globals::getApplicationConfig('optlist')->list_count;

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
     * @action: download image from the folder that is defined
     *
     * @return void
     * @author Nguyen Thi Tho
     * @since 2012/07/11
     */
    public function imageAction() {
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender();

        $fileName = $this->getRequest()->getParam('name');
        // Get image path
        $filepath = realpath($this->company_link . '/image/' . $fileName);
        if (file_exists($filepath)) {
            // Response image to browser image
            $image = file_get_contents($filepath);
            $this->getResponse()->clearBody();
            $this->getResponse()->setHeader('Content-Type', 'image/jpg');
            $this->getResponse()->setBody($image);
        }
    }

    private function imagedelete($menuCode, $fileName) {
        $session = Globals::getSession();
        // Get image path
        $filepath = realpath($this->company_link . '/image/' . $fileName);

        //check used
        $postData['page'] = 1;
        $search = array();
        $search['image']['eq'] = $fileName;
        $data = $this->indexModel->getDataViewList($postData, $search);

        $product = null;
        foreach ($data['rows'] as $row) {
            if ($row['menuCode'] == $menuCode) {
                $product = $row;
                break;
            }
        }
        if (is_null($product)) {
            return false;
        }
        //update csv
        $product['image'] = '';
        $product['thumb'] = '';
        $this->indexModel->update(array($menuCode => $product));
        // Pack data
        DataPacker::packDataInBatch($session->company_link);

        //---delete file
        unlink($filepath);
        //delete thumb
        unlink(Application_Model_Image::getThumpFolder() . 'product/' . $fileName);
        return true;
    }

    /**
     * @action: delete a product image
     *
     * @return void
     * @author Nguyen Thi Tho
     * @since 2012/07/11
     */
    public function imagedeleteAction() {
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender();

        //---get data post
        $menuCode = $this->getRequest()->getParam('menuCode');
        $fileName = $this->getRequest()->getParam('name');

        $result = $this->imagedelete($menuCode, $fileName);
        if (!$result) {
            $this->_helper->json(array('result' => 'false'));
            return;
        }
        $this->_helper->json(array('result' => 'true'));
    }

    /**
     * @action: add a new product
     *
     * @return void
     * @author Nguyen Thi Tho
     * @since 2012/07/17
     */
    public function addAction() {
        // Init Csrf
        $this->view->csrf = $this->_helper->csrf->initInput();

        //check sumareji
        $settingModel = new Application_Model_Setting();
        $this->file = $settingModel->getFilePath();
        $content = @file_get_contents($this->file);
        $json_setting = json_decode($content, true);
        if ($json_setting['linkSystem'] == 1 || $json_setting['linkSystem'] == 2 ||
                (Globals::isMobile() && $json_setting['linkSystem'] != 0)) {
            $this->_redirect('/product');
        }
 
        $subComment = new Application_Model_SubComment();
        $image = new Application_Model_Image();

        //---get data post
        $req = Zend_Controller_Front::getInstance()->getRequest();
        $this->action = $req->getActionName();

        //---get session data
        $session = Globals::getSession();
        if (isset($session->imageDelete)) {
            unset($session->imageDelete);
        }
        $session->data_eidt_product = array();
        //---Init Messenger
        $this->msgConfig = Zend_Registry::get('MsgConfig');
        $flash = $this->_helper->getHelper('flashMessenger');
        if ($flash->hasMessages()) {
            $this->view->message = $flash->getMessages();
        }

        if (is_null($session->success) || $session->success == 0) {
            $this->formData = $this->_helper->getHelper('formData');
            $this->_helper->formData->addData($this->formData->getData());
            if ($this->formData->hasData()) {
                $this->view->data = $this->formData->getData();
            }
        }

        $entries = $this->indexModel->fetchAll(null, true);
        if ($entries) {
            asort($entries);
            $lastItem = array_pop($entries);
            $newNo = $lastItem->getNo() + 1;
        } else {
            $newNo = 1;
        }

        $this->view->no = $newNo;
        $this->view->dataImage = $image->getData();
        $this->view->dataSubComment = $subComment->getDataSubCommentFillCombobox();
        $this->view->success = $session->success;
        //get url json data to fill into combobox
        $this->view->dataCategory1 = '/product/jsoncategory1';
        $this->view->dataCategory2 = '/product/jsoncategory2';
        $this->view->dataSuggest = '/product/jsonindex';
        $this->view->confirmAdd = $this->msgConfig->E301_Confirm_Add;
        $this->view->indexModel      = $this->indexModel;
        if (Globals::isMobile()) {
            $this->view->showPrinterInput = $json_setting['linkSystem'] == 0;
        }

        $this->view->viewAll = Globals::isFullPermission();
    }

    /**
     * @action: add a new product - execute
     *
     * @return void
     * @author Nguyen Thi Tho
     * @since 2012/07/18
     */
    public function addexecuteAction() {
        // Check Csrf
        $this->_helper->csrf->checkCsrf($this->getRequest());

        //---get session data
        $session = Globals::getSession();
        //$this->index = new Application_Model_Index();
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

        if ($this->_validate($form_Data)) {
            try {
                $data = $this->indexModel->changeFormData($form_Data);
                $data = array($form_Data['menuCode'] => $data);
                $session->success = 1;

                //---upload imge
                $image = $this->uploadImage($form_Data['menuCode']);
                if (!$image) {
                    $this->_redirect('/product/add');
                    return;
                } else if (is_string($image)) {
                    $data[$form_Data['menuCode']]['image'] = $image;
                }
                
                $this->_helper->formData->addData($data[$form_Data['menuCode']]);

                //---Insert data
                $this->indexModel->insert($data);
                $this->view->data = $data[$form_Data['menuCode']];

                Globals::log('add product (' . $form_Data['menuCode'] . ')', null, $this->company_code . '.log');
                Globals::log($data, null, $this->company_code . '.log');
                $this->_helper->flashMessenger->addMessage($this->msgConfig->I301_AddSuccessful);

                unset($session->paramsCategory);
                // Pack data
                DataPacker::packDataInBatch($session->company_link);
                $this->_redirect('/product/edit/id_edit/' . $form_Data['menuCode']);
            } catch (Exception $e) {
                $session->success = 0;
                Globals::log('add unsuccessful.', null, $this->company_code . '.log');
                Globals::logException($e);
                $this->_helper->flashMessenger->addMessage($this->msgConfig->E301_CanNotAddPrtoduct);
                $this->_redirect('/product/add');
            }
        } else {
            $session->success = 0;
            $this->_redirect('/product/add');
        }
    }

    /**
     * @action: edit a new product

     * @return void
     * @author Nguyen Thi Tho
     * @since 2012/07/16
     */
    public function editAction() {
        // Init Csrf
        $this->view->csrf = $this->_helper->csrf->initInput();

        //---include library
        $this->view->headScript()->offsetSetFile(20, (Globals::isMobile() ? '/sp' : '/pc') . '/js/jquery.fancybox.js');
        $this->view->headLink()->appendStylesheet((Globals::isMobile() ? '/sp' : '/pc') . '/css/jquery.fancybox.css');

        $subComment = new Application_Model_SubComment();
        //$index = new Application_Model_Index();
        $image = new Application_Model_Image();
        $arrImage = $image->getArrayImage();

        //----get data session
        $session = Globals::getSession();

        //---get data post
        $postData = $this->getRequest()->getParams();

        if (isset($postData['id_edit'])) {
            $ids = explode(',', $postData['id_edit']);
            $idEdit = array_shift($ids);
        } else {
            $idEdit = $session->idEdit;
        }

        $session->idEdit = $idEdit;
        $session->pos = $session->paramsCategory;

        //---Init Messenger
        $this->msgConfig = Zend_Registry::get('MsgConfig');

        $flash = $this->_helper->getHelper('flashMessenger');
        if ($flash->hasMessages()) {
            $this->view->message = $flash->getMessages();
        }

        $this->formData = $this->_helper->getHelper('formData');
        $this->_helper->formData->addData($this->formData->getData());

        if ($idEdit == '') {
            $this->view->success = 0;
            $this->_helper->flashMessenger->addMessage($this->msgConfig->E301_NotGetDataToEdit);
            $this->_redirect('/product');
        }

        if(count($this->formData->getData()) == 0) {
            //---get data product by code (id)
            $data = $this->indexModel->findRowByKey($idEdit);

            if ($data === false) {
                $this->view->success = 0;
                $this->_helper->flashMessenger->addMessage(
                    sprintf($this->msgConfig->E307_NotExist_Product, $idEdit)
                );
                $this->_redirect('/product');
            }
            $session->data_eidt_product = array();
            foreach ($data[$idEdit] as $key => $value) {
                if ($key == 'image' && (!array_keys($arrImage, $value))) {
                    $data[$idEdit]['image'] = '';
                }
                $session->data_eidt_product[$key] = $value;
            }
            $this->view->data = $data[$idEdit];
        } else {
            $data = $this->formData->getData();
            if (!isset($data['image'])) {
                $data['image'] = '';
            }
            $this->view->data =  $data;
        }

        if (!empty($this->view->data['image'])) {
            $filepath = realpath($this->company_link . '/image/' . $this->view->data['image']);
            if (!file_exists($filepath)) {
                $this->view->data['image'] = '';
            }
        }

        $messages = $this->_checkData($this->view->data);
        if ($messages !== true) {
            $this->view->notice = $messages;
        }

        //get data view combobox
        $this->view->dataImage = $image->getData();
        $this->view->dataSubComment = $subComment->getDataSubCommentFillCombobox();
        //result edit
        $this->view->success = $session->success;
        //get url json data to fill into combobox
        $this->view->dataCategory1 = '/product/jsoncategory1';
        $this->view->dataCategory2 = '/product/jsoncategory2';
        $this->view->dataSuggest = '/product/jsonindex';
        $this->view->confirmEdit = $this->msgConfig->E301_Confirm_Edit;
        $this->view->confirmDel = $this->msgConfig->E300_Confirm_Delete;
        $this->view->indexModel      = $this->indexModel;
        if (Globals::isMobile()) {
            //check sumareji
            $settingModel = new Application_Model_Setting();
            $this->file = $settingModel->getFilePath();
            $content = @file_get_contents($this->file);
            $json_setting = json_decode($content, true);
            
            $this->view->showPrinterInput = $json_setting['linkSystem'] == 0 || $json_setting['linkSystem'] == 2;
        }

        $this->view->viewAll = Globals::isFullPermission();
    }

    /**
     * @action: edit a new product - execute
     *
     * @return void
     * @author Nguyen Thi Tho
     * @since 2012/07/16
     */
    public function editexecuteAction() {
        // Check Csrf
        $this->_helper->csrf->checkCsrf($this->getRequest());


        $req = Zend_Controller_Front::getInstance()->getRequest();
        $this->action = $req->getActionName(); //---get this action name

        //$this->index = new Application_Model_Index();
        $this->check = new Application_Model_ValidateRules();

        //---get data session
        $session = Globals::getSession();
        $this->idEdit = $session->idEdit;

        //---get data post
        $form_Data = $this->getRequest()->getPost();
        $this->_helper->formData->removeCsrfData($form_Data);

        //---change data Time;
        if (isset($form_Data['startTime']) && !empty($form_Data['startTime'])) {
            $form_Data['startTime'] = $this->indexModel->changeFormatTime($form_Data['startTime']);
        }

        if (isset($form_Data['endTime']) && !empty($form_Data['endTime'])) {
            $form_Data['endTime'] = $this->indexModel->changeFormatTime($form_Data['endTime']);
        }
        $form_Data['image'] = isset($form_Data['image_product']) ? $form_Data['image_product'] : '';
        //---end

        //---Validate data post
        if ($this->_validate($form_Data)) {
            try {

                $data = $this->indexModel->changeFormData($form_Data);
                $data = Array($this->idEdit => $data);
                $session->success = 1;

                //---upload file img, upload ok return file name
                $image = $this->uploadImage($form_Data['menuCode']);
                if (!$image) {
                    $this->_redirect('/product/edit');
                    return;
                } else if (is_string($image)) {
                    $data[$this->idEdit]['image'] = $image;
                } else {
                    $data[$this->idEdit]['image'] = $form_Data['image_product'];
                }
                $this->_helper->formData->addData($data[$this->idEdit]);

                //---delete file, if data image null & session image not null
                if ($data[$this->idEdit]['image'] != $session->imageDelete && $session->imageDelete != '') {
                    $filepath = realpath($this->company_link . '/image/' . $session->imageDelete);
                    // Pack data
                    DataPacker::packDataInBatch($session->company_link);
                    //---delete image
                    unlink($filepath);
                    //---delete thumb
                    unlink(Application_Model_Image::getThumpFolder() . 'product/' . $session->imageDelete);
                }
                //---update data product
                $this->indexModel->update($data);
                $this->view->data = $data[$this->idEdit];
                Globals::log('Edit the product (' . $form_Data['menuCode'] . ')', null, $this->company_code . '.log');
                Globals::log($data, null, $this->company_code . '.log');
                $this->_helper->flashMessenger->addMessage($this->msgConfig->I000_ProductEditSuccessful);

                unset($session->paramsCategory);
                // Pack data
                DataPacker::packDataInBatch($session->company_link);
                if (isset($session->nameProduct)) {
                    unset($session->nameProduct);
                }
                unset($session->imageDelete);

                $this->_redirect('/product/edit');
            } catch (Exception $e) {
                $form_Data['image'] = $form_Data['image_product'];
                $this->_helper->formData->addData($form_Data);
                $session->success = 0;
                Globals::log('Update the product unsuccessful (' . $form_Data['menuCode'] . ')', null, $this->company_code . '.log');
                Globals::logException($e);
                $this->_helper->flashMessenger->addMessage($this->msgConfig->E000_CanNotEdit);

                $this->_redirect('/product/edit');
            }
        } else {
            $form_Data['image'] = $form_Data['image_product'];
            $this->_helper->formData->addData($form_Data);
            $session->success = 0;
            $this->_redirect('/product/edit');
        }
    }

    private function uploadImage($menuCode) {
        //---get info file image upload
        $files_info = $_FILES['p_image_file'];
        $this->file = $this->_getAllFilesName($files_info);

        //---get data session
        $session = Globals::getSession();
        $image = new Application_Model_Image();

        //---check file image
        if (!$image->checkMaxData($files_info)) {
            $this->_helper->flashMessenger->addMessage(array('p_image_file[]' =>
                sprintf($this->msgConfig->E309_MaxImageFiles, $this->_imageConfig->productImg->max_total))
            );
            $session->success = 0;
            return false;
        }

        $flash = $this->_helper->getHelper('flashMessenger');
        if ($flash->hasMessages()) {
            $this->view->message = $flash->getMessages();
        }

        // Remove empty files
        $files = array_filter($_FILES['p_image_file']['name']);
        $renameFiles = array();
        if (count($files) > 0) {
            //rename file
            foreach ($files as $name) {
                $renameFiles[$name] = sprintf(self::IMAGE_NAME, $menuCode . '.' . pathinfo($name, PATHINFO_EXTENSION));
            }
        }

        //check if existed (no need anymore because each product has an unique image)
        /* foreach ($renameFiles as $file) {
          if (file_exists($session->company_link . '/image/' . $file) && $file != $session->imageDelete ) {
          $this->_helper->flashMessenger->addMessage(array('p_image_file[]' => $this->msgConfig->E304_Existed_Image));
          $session->success = 0;
          return false;
          }
          } */

        //---upload file, ok
        if (count($files)) {
            $result = $image->uploadImageMulti(Application_Model_Image::UPLOAD_PRODUCT_IMAGE, $session->company_link . '/image/', $files, $renameFiles);
            if (is_array($result) && count($result)
            ) {
                foreach ($result as $message) {
                    $this->_helper->flashMessenger->addMessage(array('p_image_file[]' => $message));
                }
                $session->success = 0;
                return false;
            } else {
                $session->success = 1;
                
                //resize image for mobile
                if (Globals::isMobile()) {
                    $config = Globals::getApplicationConfig('image');
                    foreach ($renameFiles as $path) {
                        Application_Model_Image::resizeImage($session->company_link . '/image/' . $path, 
                            $config->productImg->mobile->width,
                            $config->productImg->mobile->height);
                    }
                }

                // Pack data
                DataPacker::packDataInBatch($session->company_link);
            }
        } else {
            return true;
        }
        return $renameFiles[$files[0]];
    }

    private function _getAllFilesName($files_info) {
        $r = Array();
        foreach ($files_info as $k => $v) {
            foreach ($v as $k1 => $v1) {
                if (($v1 != '') && ($k != 'error')) {
                    $r[$k][] = $v1;
                }
                if (($k == 'error') && ($v1 == 0)) {
                    $r[$k][] = $v1;
                }
            }
        }
        return $r;
    }

    /**
     * @action: import product
     *
     * @return void
     * @author Nguyen Thi Tho
     * @since 2012/07/23
     */
    public function importAction() {
        // Init Csrf
        $this->view->csrf = $this->_helper->csrf->initInput();

        //---get data session
        $session = Globals::getSession();
        $this->view->success = $session->success;

        //---Init Messenger
        $flashMsg = $this->_helper->getHelper('flashMessenger');
        if ($flashMsg->hasMessages()) {
            $this->view->message = $flashMsg->getMessages();
        }
        $this->formData = $this->_helper->getHelper('formData');
        if (count($this->formData->getData()) == 0 || (!is_null($session->success) && $session->success !== 0)) {
            $this->view->data = array('upload_type' => 'update', 'upload_charset' => 'SJIS');
        } else {
            $this->view->data = $this->formData->getData();
        }

        $this->view->alertImp = $this->msgConfig->E300_RequireFileUpload;
        $this->view->alertImportType = $this->msgConfig->E300_RequireImportType;
        $this->view->confiemImpCsv = $this->msgConfig->E300_Confirm_ImportCsv;

        // CSVアップロード処理
        $this->view->importType = Application_Model_UploadCsv::getImportType('index');
        $this->view->importTypeDefault = Application_Model_UploadCsv::getImportTypeDefault('index');
    }

    /**
     * @action: download option product
     *
     * @return void
     * @author Phan Quoc Bao
     * @since 2014/05/07
     */
    public function downloadoptionAction() {
        // Init Csrf
        $this->view->csrf = $this->_helper->csrf->initInput();

        //---get data session
        $session = Globals::getSession();
        $this->view->success = $session->success;

        //---Init Messenger
        $flashMsg = $this->_helper->getHelper('flashMessenger');
        if ($flashMsg->hasMessages()) {
            $this->view->message = $flashMsg->getMessages();
        }
        $this->formData = $this->_helper->getHelper('formData');
        if (count($this->formData->getData()) == 0 || (!is_null($session->success) && $session->success !== 0)) {
            $this->view->data = array('download_type' => 'SJIS');
        } else {
            $this->view->data = $this->formData->getData();
        }
    }

    /**
     * @action: import product (.pos)
     *
     * @return void
     * @author Nguyen Thi Tho
     * @since 2012/08/08
     */
    public function uploadposAction() {
        // Check Csrf
        //$this->_helper->csrf->checkCsrf($this->getRequest());
        //---get dt
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
            $this->_redirect('/tecreji');
        }

        $fileNameDown = $mdelUp->makeIndexFile();

        if ($fileNameDown !== false) {
            $session->success = 1;

            $this->_helper->layout->disableLayout();
            $this->_helper->viewRenderer->setNoRender();

            Application_Model_UploadBinary::createDownloadCsvFile(
                    $this, $fileNameDown, Application_Model_Index::MAIN_FILE
            );
        } else {
            $this->_helper->flashMessenger->addMessage($this->msgConfig->E300_FileUploadInvalid);
            $session->success = 0;
            $this->_redirect('/tecreji');
        }
    }

    /**
     * @action: import product (.csv)
     *
     * @return void
     * @author Nguyen Thi Tho
     * @since 2012/07/30
     */
    public function uploadcsvAction() {
        // Check Csrf
        $this->_helper->csrf->checkCsrf($this->getRequest());

        //---get data session
        $session = Globals::getSession();
        $session->success = 0;

        //$csvIndex = new Application_Model_Index();
        $this->indexModel->fetchAll();

        //---get data post
        $file_name = $_FILES['page_csv']['name'];
        $file_type = 'page_csv';

        $uploadType = $this->getRequest()->getParam('upload_type');
        $uploadCharset = $this->getRequest()->getParam('upload_charset');

        $charsetdeplay = str_replace('SJIS', 'SHIFT-JIS', $uploadCharset);

        //---check data post
        $check_error = false;
        if ($uploadType == '' && $file_name != '') {
            //---Not select type
            $this->_helper->flashMessenger->addMessage(array('upload_type_new' => $this->msgConfig->E300_RequireImportType));
            $check_error = true;
        }
        if ($uploadCharset == '' && $file_name != '') {
            //---Not select char set
            $this->_helper->flashMessenger->addMessage(array('upload_charset_utf8' => $this->msgConfig->E300_RequireImportCharset));
            $check_error = true;
        }

        $mdelUp = new Application_Model_UploadCsv(self::ITEM_CODE);

        //---check & copy file
        $checkFile = $mdelUp->checkFile($file_type, $file_name);

        //---check charset...
        $checkCharset = $mdelUp->checkCharsetFileCsv($file_name, $uploadCharset);
        if ($checkCharset != '' && $file_name != '') {
            //---Not select char set
            $this->_helper->flashMessenger->addMessage(array('page_csv' => sprintf($this->msgConfig->E300_Chatset_Allow, $charsetdeplay)));
            $check_error = true;
        }

        //---check file
        if ($checkFile !== 1) {
            $this->_helper->flashMessenger->addMessage(array('page_csv' => $this->msgConfig->$checkFile));
        } else if (!$check_error) {
            //---get setting data
            $settingModel = new Application_Model_Setting();
            $this->file = $settingModel->getFilePath();
            $content = @file_get_contents($this->file);
            $json_setting = json_decode($content, true);
            $linkSystem = $json_setting['linkSystem'];

            //get header of the upload file
            $upHeader = $mdelUp->getHeader(Globals::getTmpUploadFolder() . $file_name);
            $upHeader = mb_convert_encoding(implode(',', $upHeader), 'UTF-8', $uploadCharset);
            $upHeader = explode(',', $upHeader);

            //import file
            $file_name_up = $mdelUp->copyIndexFileCsvWithCharSet($file_name, $uploadCharset, $linkSystem == self::LINK_SYSTEM_PRINTER);
            if ($file_name_up !== 0) {
                //check header file upload and file main
                if (($linkSystem === self::LINK_SYSTEM_PRINTER && count(array_diff(explode(',', $this->_fileConfig->csv->index->printer->download->upload->columns->jp), $upHeader)) != 0) ||
                        ($linkSystem === self::LINK_SYSTEM_TEC && count(array_diff(explode(',', $this->_fileConfig->csv->index->TEC->download->upload->columns->en), $upHeader)) != 0)) {
                    Globals::log($this->msgConfig->C007_Header_invalid);
                    $this->_helper->flashMessenger->addMessage(array('page_csv' => $this->msgConfig->E300_FileUploadInvalid));
                } else {
                    try {
                        $data_up = $mdelUp->getData($file_name_up, self::ITEM_CODE);
                        
                        $errorField = null;
                        if ($this->_checkValidImportData($data_up, $errorField)) {
                            //add category name
                            $categoryModel = new Application_Model_Category();
                            $catogoryData = $categoryModel->getData();
                            $ok = true;
                            foreach ($data_up as $key => $row) {
                                if (array_key_exists('category1_code', $data_up[$key])) {
                                    $keyCategory = Application_Model_Category::CATE_TYPE_1 . '-' . $data_up[$key]['category1_code'];
                                    if (isset($catogoryData[$keyCategory])) {
                                        $data_up[$key]['category1_name'] = $catogoryData[$keyCategory]['name'];
                                    } else {
                                        $ok = false;
                                        $this->_helper->flashMessenger->addMessage(array('page_csv'=>sprintf($this->msgConfig->E301_Upload_NotExist_Category, $row['menuCode'])));
                                        break;
                                    }                                    
                                } else if (array_key_exists($row['menuCode'], $oldData)) {                            
                                    // index.csvに保存しているデータにあるカテゴリ名称がない場合、更新する
                                    if ($oldData[$row['menuCode']]['category1_name'] == "") {
                                        $keyCategory = Application_Model_Category::CATE_TYPE_1 . '-' . $oldData[$row['menuCode']]['category1_code'];
                                        if (isset($catogoryData[$keyCategory])) {
                                            $oldData[$row['menuCode']]['category1_name'] = $catogoryData[$keyCategory]['name'];
                                        } else {
                                            $ok = false;
                                            $this->_helper->flashMessenger->addMessage(array('page_csv'=>sprintf($this->msgConfig->E301_Upload_NotExist_Category, $row['menuCode'])));
                                            break;
                                        }
                                    }
                                }
                            }
        
                            if ($ok) {
                                switch ($uploadType) {
                                    // 全てのデータを削除後、データの取り込みを行う
                                    case $this->_fileConfig->csv->new_import:
                                        //get old data
                                        $oldData = $this->indexModel->getDataViewList(array());
                                        $oldData = $oldData['rows'];

                                        //replace csv
                                        if ($linkSystem === self::LINK_SYSTEM_TEC) {
                                            if (!copy($file_name_up, Globals::getDataFilePath(Application_Model_Index::MAIN_FILE))) {
                                                throw new Application_Model_Exception(
                                                sprintf($this->msgConfig->E000_Failed_CopyFile)
                                                );
                                            }
                                        } else {
                                            $this->indexModel->deleteAllRow();
                                            $no = 1;
                                            foreach ($data_up as $key => $row) {
                                                $row['no'] = $no;
                                                $data_up[$key] = $this->indexModel->_fillDataFromSession($row, array());
                                                $no++;
                                            }
                                            $this->indexModel->insert($data_up);
                                        }

                                        //delete all images
                                        foreach ($oldData as $row) {
                                            $fileName = $row['image'];
                                            if (strlen($fileName) > 0) {
                                                $filepath = realpath($this->company_link . '/image/' . $fileName);
                                                unlink($filepath);
                                                unlink(Application_Model_Image::getThumpFolder() . 'product/' . $fileName);
                                            }
                                        }

                                        break;
                                    // 同一ID以外のデータ取り込みを行う
                                    case $this->_fileConfig->csv->update_import:
                                        foreach ($data_up as $key => $row) {
                                            $data_up[$key] = $this->indexModel->_fillDataFromSession($row, array());
                                        }
                                        if ($linkSystem === self::LINK_SYSTEM_TEC) {
                                            $data_del = array_intersect_assoc($data_up, $this->indexModel->getData());
                                        } else {
                                            $data_del = array_intersect_key($data_up, $this->indexModel->getData());
                                        }
                                        $mdelUp->uploadCsvIndex($data_up, $data_del);
                                        break;
                                }
                                $this->_helper->flashMessenger->addMessage($this->msgConfig->E302_ImportProductSuccessful);
                                $session->success = 1;

                                //back to page 1
                                unset($session->backUrl);
                                // Pack data
                                DataPacker::packDataInBatch($session->company_link);
                            }
                        } else {
                            $this->_helper->flashMessenger->addMessage(array('page_csv' => sprintf($this->msgConfig->E300_FileUploadDataInvalid, $errorField)));
                        }
                    } catch (Exception $e) {
                        Globals::log($e->getMessage());
                        $this->_helper->flashMessenger->addMessage(array('page_csv' => $e->getMessage()));
                    }
                }
            } else {
                $this->_helper->flashMessenger->addMessage($this->msgConfig->E302_CantImportProdcut);
            }
        }
        $this->_helper->formData->addData($this->getRequest()->getParams());
        $this->_redirect('/product/import');
    }

    /**
     * @action: delete products
     *
     * @return void
     * @author Nguyen Thi Tho
     * @since 2012/07/13
     */
    public function deleteAction() {
        // Check Csrf
        $this->_helper->csrf->checkCsrf($this->getRequest());

        if (Globals::isMobile()) {
            $this->_redirect('/product');
        }
        
        $session = Globals::getSession();
        $session->success = 0;

        $arrNotDel = Array();

        //---get data post
        $postData = $this->getRequest()->getParams();
        $dataDel = isset($postData['id_edit']) ? $postData['id_edit'] : '';
        if ($dataDel == '') {
            $this->_helper->flashMessenger->addMessage($this->msgConfig->E301_NotDataDelPrtoduct);
            $this->_redirect('/product/edit');
        }

        $dataDel = explode(',', $dataDel);
        //$index = new Application_Model_Index();
        $data = $this->indexModel->getData();

        //---delete product
        foreach ($dataDel as $key => $value) {
            $rowFind = $data[$value];

            //---check product use in subcommet and topping group
            if ($this->indexModel->checkForgeinKey($value) == 1) {
                try {
                    $fileName = $postData['image_product_old'];
                    if (strlen($fileName) > 0) {
                        //---delete file image
                        $this->imagedelete($value, $fileName);
                    }

                    //---delele data product
                    $this->indexModel->deleteByKey($value);
                    Globals::log('Delete the product (' . $value . ')', null, $this->company_code . '.log');
                    Globals::log($rowFind, null, $this->company_code . '.log');
                } catch (Exception $e) {
                    Globals::log('Delete the product unsuccessful (' . $value . ')', null, $this->company_code . '.log');
                    $this->_helper->flashMessenger->addMessage($this->msgConfig->E000_CanNotDelete);
                    $arrNotDel[] = $value;
                }
            } else {
                $arrNotDel[] = $rowFind;
            }
        }
        if (count($arrNotDel) == 0) {
            $session->success = 1;
            $this->_helper->flashMessenger->addMessage($this->msgConfig->I000_DeleteSuccessful);
        } else {
            $session->success = 0;
            $this->_helper->flashMessenger->addMessage($this->msgConfig->E300_ProductForeign);
            $this->_redirect('/product/edit');
        }

        // Pack data
        DataPacker::packDataInBatch($session->company_link);

        $this->_redirect($session->backUrl);
    }

    /**
     *  @function: get data from category to fill category1 field
     *
     * @return data
     * @author Nguyen Thi Tho
     * @since 2012/07/16
     */
    public function jsoncategory1Action() {
        $csv = new Application_Model_Category();
        $data = $csv->getDataJson1();
        $this->_helper->json($data);
    }

    /**
     *  @function: get data from category to fill category2 field
     *
     * @return data
     * @author Nguyen Thi Tho
     * @since 2012/07/16
     */
    public function jsoncategory2Action() {
        $csv = new Application_Model_Category();
        $data = $csv->getDataJson2();
        $this->_helper->json($data);
    }

    /**
     *  @function: get data from index.csv to fill suggest combobox
     *
     * @return data
     * @author Nguyen Thi Tho
     * @since 2012/07/16
     */
    public function jsonindexAction() {
        //$csvIndex = new Application_Model_Index();
        $data = $this->indexModel->getDataJson();
        $this->_helper->json($data);
    }

    /**
     * validate the value on the product form (edit, add)
     *
     * @access private
     * @param  array data on the form
     * @return boole
     * @since 2012/07/16
     */
    private function _validate($form_data) {
        $result = true;
        $image = new Application_Model_Image();
        $check = new Application_Model_ValidateRules();

        $flash = $this->_helper->getHelper('flashMessenger');
        if ($flash->hasMessages()) {
            $this->view->message = $flash->getMessages();
        }
        //No
        if (!Zend_Validate::is($form_data['no'], 'NotEmpty')) {
            $this->_helper->flashMessenger->addMessage(array('no' => $this->msgConfig->E301_Require_No));
            $result = false;
        }
        //No
        if (Zend_Validate::is($form_data['no'], 'NotEmpty') && !Zend_Validate::is($form_data['no'], 'Digits')
        ) {
            $this->_helper->flashMessenger->addMessage(array('no' => $this->msgConfig->E301_Invalid_No));
            $result = false;
        }
        //menuCode
        if (!Zend_Validate::is($form_data['menuCode'], 'NotEmpty')) {
            $this->_helper->flashMessenger->addMessage(array('menuCode' => $this->msgConfig->E301_Require_MenuCode));
            $result = false;
        }
        if (Zend_Validate::is($form_data['menuCode'], 'NotEmpty') && !Zend_Validate::is($form_data['menuCode'], 'Alnum')
        ) {
            $this->_helper->flashMessenger->addMessage(array('menuCode' => $this->msgConfig->E301_Invalid_MenuCode));
            $result = false;
        }
        if (Zend_Validate::is($form_data['menuCode'], 'NotEmpty') && strlen($form_data['menuCode']) > 32
        ) {
            $this->_helper->flashMessenger->addMessage(array('menuCode' => $this->msgConfig->E301_MaxLength_CodeProduct));
            $result = false;
        }

        if ($this->action == 'addexecute' && $this->indexModel->findRowByKey($form_data['menuCode']) != ''
        ) {
            $this->_helper->flashMessenger->addMessage(array('menuCode' => $this->msgConfig->E301_ExistProduct));
            $result = false;
        }
        if ($this->action == 'editexecute' && $this->idEdit != $form_data['menuCode'] && $this->indexModel->findRowByKey($form_data['menuCode']) != ''
        ) {
            $this->_helper->flashMessenger->addMessage(array('menuCode' => $this->msgConfig->E301_ExistProduct));
            $result = false;
        }
        //image
        if ($form_data['image_product'] != '' && (!$check->checkSpecCharForFileName($form_data['image_product']) || !array_keys($image->getArrayImage(), $form_data['image_product']))) {
            $this->_helper->flashMessenger->addMessage(array('p_image_file[]' => $this->msgConfig->E304_Invalid_Image));
            $result = false;
        }
        //itemName
        if (!Zend_Validate::is($form_data['itemName'], 'NotEmpty')) {
            $this->_helper->flashMessenger->addMessage(array('itemName' => $this->msgConfig->E301_Require_ItemName));
            $result = false;
        }
        if (Zend_Validate::is($form_data['itemName'], 'NotEmpty') && !$this->check->checkSpecCharForName($form_data['itemName'])
        ) {
            $this->_helper->flashMessenger->addMessage(array('itemName' => $this->msgConfig->E301_Invalid_ItemName));
            $result = false;
        }
        if (Zend_Validate::is($form_data['itemName'], 'NotEmpty') && mb_strlen($form_data['itemName'], 'UTF-8') > 85
        ) {
            $this->_helper->flashMessenger->addMessage(array('itemName' => $this->msgConfig->E301_MaxLength_NameProduct));
            $result = false;
        }
        //price
        if (!Zend_Validate::is($form_data['price'], 'NotEmpty')) {
            $this->_helper->flashMessenger->addMessage(array('price' => $this->msgConfig->E301_Require_Price));
            $result = false;
        }
        if (($form_data['price'] != '') && !Zend_Validate::is($form_data['price'], 'Digits')) {
            $this->_helper->flashMessenger->addMessage(array('price' => $this->msgConfig->E301_Number_Price));
            $result = false;
        }

        if (($form_data['price'] != '') && $form_data['price'] > PHP_INT_MAX
        ) {
            $this->_helper->flashMessenger->addMessage(array('price' => $this->msgConfig->E301_Invalid_Price));
            $result = false;
        }

        // カテゴリ
        $cateModel = new Application_Model_Category();
        // メインカテゴリ
        if ($form_data['category1_code'] != '') {
            $cate1 = $cateModel->getCategoryByKind(Application_Model_Category::CATE_TYPE_1);
            if (!array_key_exists($form_data['category1_code'], $cate1)) {
                $this->_helper->flashMessenger->addMessage(array('category1_code' => $this->msgConfig->E301_Invalid_Category1));
                $form_data['category1_code'] = '';
                $result = false;
            }
        } else {
            $this->_helper->flashMessenger->addMessage(array('category1_code' => $this->msgConfig->E301_Require_Category1));
            $result = false;
        }

        //desc
        if (Zend_Validate::is($form_data['desc'], 'NotEmpty') && !$this->check->checkSpecCharForName($form_data['desc'])
        ) {
            $this->_helper->flashMessenger->addMessage(array('desc' => $this->msgConfig->E301_Invalid_Desc));
            $result = false;
        }
        if (Zend_Validate::is($form_data['desc'], 'NotEmpty') && mb_strlen($form_data['desc'], 'UTF-8') > 1000
        ) {
            $this->_helper->flashMessenger->addMessage(array('desc' => $this->msgConfig->E301_Maxlength_Desc));
            $result = false;
        }

        $timeValidator = new Zend_Validate_Date(array(
            'format' => 'HH:mm',
            'locale' => 'jp'
        ));
        
        $isMobile = Globals::isMobile();
        
        // 取扱い開始時刻
        if (!$isMobile) {
            if (Zend_Validate::is($form_data['startTime'], 'NotEmpty')) {
                if (!$timeValidator->isValid($form_data['startTime'])) {
                    $this->_helper->flashMessenger->addMessage(array('Time' => $this->msgConfig->E301_Invalid_StartTime));
                    $result = false;
                } else if (!Zend_Validate::is(str_replace(':', '', $form_data['startTime']), 'Digits')) {
                    $this->_helper->flashMessenger->addMessage(array('Time' => $this->msgConfig->E301_Invalid_StartTime));
                    $result = false;
                }
            }
            $form_data['startTime'] = str_replace(':', '', $form_data['startTime']);
        }

        //  取扱い終了時刻
        if (!$isMobile) {
            if (Zend_Validate::is($form_data['endTime'], 'NotEmpty')) {
                if (!$timeValidator->isValid($form_data['endTime'])) {
                    $this->_helper->flashMessenger->addMessage(array('Time' => $this->msgConfig->E301_Invalid_EndTime));
                    $result = false;
                } else if (!Zend_Validate::is(str_replace(':', '', $form_data['endTime']), 'Digits')) {
                    $this->_helper->flashMessenger->addMessage(array('Time' => $this->msgConfig->E301_Invalid_EndTime));
                    $result = false;
                }
            }
            $form_data['endTime'] = str_replace(':', '', $form_data['endTime']);
        }

        //printerIp
        if (isset($form_data['PrinterIP'])) {
            if (Zend_Validate::is($form_data['PrinterIP'], 'NotEmpty') && !$this->check->checkSpecCharForIP($form_data['PrinterIP'])
            ) {
                $this->_helper->flashMessenger->addMessage(array('Printer' => $this->msgConfig->E301_Invalid_PrinterIP));
                $result = false;
            }
        }

        //PrinterPort
        if (isset($form_data['PrinterPort'])) {
            if (Zend_Validate::is($form_data['PrinterPort'], 'NotEmpty') && !Zend_Validate::is($form_data['PrinterPort'], 'Digits')
            ) {
                $this->_helper->flashMessenger->addMessage(array('Printer' => $this->msgConfig->E301_Invalid_PrinterPort));
                $result = false;
            }

            if (($form_data['PrinterPort'] != '') && $form_data['PrinterPort'] > PHP_INT_MAX
            ) {
                $this->_helper->flashMessenger->addMessage(array('Printer' => $this->msgConfig->E301_Maxlength_PrinterPort));
                $result = false;
            }
        }

        $this->_helper->formData->addData($form_data);

        return $result;
    }

    /**
     * Data checking
     *
     * @param array $data
     * @return array|true
     * @author Nguyen Huu Tam
     * @since 2012/10/22
     */
    protected function _checkData($data) {
        $msg = array();
        if (is_array($data)) {
            // カテゴリ
            $cateModel = new Application_Model_Category();

            // メインカテゴリ
            if (isset($data['category1_code']) && (!empty($data['category1_code']))
            ) {
                $key = Application_Model_Category::CATE_TYPE_1
                        . '-' . $data['category1_code'];

                if ($cateModel->findRowByKey($key) == false) {
                    $msg[] = sprintf($this->msgConfig->E301_NotExist_Category1, $data['category1_code']);
                }
            }
        }

        return (count($msg) ? $msg : true);
    }
    
    /**
     * Check valid import data
     *
     * @access private
     * @param  array import data
     * @param  string $errorField
     * @return boolean
     * @since 2015/02/19
     */
    private function _checkValidImportData($importData, &$errorField)
    {
        $check   = new Application_Model_ValidateRules();

        $rtn = true; 
        $errorData = null;
        $timeValidator = new Zend_Validate_Date(array(
            'format' => 'HH:mm',
            'locale' => 'jp'
        ));
        foreach ($importData as $data) {
            if (is_array($data)) {
                foreach ($data as $key => $val) {
                    switch ($key) {
                        case "no":
                            if(Zend_Validate::is($val, 'NotEmpty') && !Zend_Validate::is($val, 'Digits') ){                                    
                                $errorData = $data;
                                $rtn = false;
                                break;
                            }
                            break;
                        case "menuCode":
                        case "商品ID":
                            if (!Zend_Validate::is($val, 'NotEmpty')
                                || !Zend_Validate::is($val, 'Alnum')
                                || mb_strlen($val, 'UTF-8') > 32) {
                                    $errorData = $data;
                                    $rtn = false;
                                    break;
                            }
                            break;
                        case "itemName":                            
                        case "商品名":
                            if ( Zend_Validate::is($val, 'NotEmpty')
                                && (!$check->checkSpecCharForName($val) || mb_strlen($val, 'UTF-8') > 85) ) {
                                $errorData = $data;
                                $rtn = false;
                                break;
                            }
                            break;
                        case "price":                            
                        case "商品単価":
                            if ( Zend_Validate::is($val, 'NotEmpty')
                                && ( !Zend_Validate::is($val, 'Digits') || $val > PHP_INT_MAX) ) {
                                $errorData = $data;
                                $rtn = false;
                                break;
                            }
                            break;
                        case "image":                            
                            if ( Zend_Validate::is($val, 'NotEmpty') && !$check->checkSpecCharForFileName($val)) {
                                $errorData = $data;
                                $rtn = false;
                                break;
                            }
                            break;
                        
                        case "desc":                            
                        case "説明":
                            if ( Zend_Validate::is($val, 'NotEmpty') &&
                                (!$check->checkSpecCharForName($val) || mb_strlen($val, 'UTF-8') > 1000) ) {
                                $errorData = $data;
                                $rtn = false;
                                break;
                            }
                            break;
                        case "startTime":                            
                        case "取扱い時間_開始時刻":                            
                            if ( Zend_Validate::is($val, 'NotEmpty') &&
                                (!$timeValidator->isValid($val) || !Zend_Validate::is(str_replace(':', '', $val), 'Digits')) ) {
                                $errorData = $data;
                                $rtn = false;
                                break;
                            }
                            break;
                        case "endTime":                            
                        case "取扱い時間_終了時刻":                            
                            if ( Zend_Validate::is($val, 'NotEmpty') &&
                                (!$timeValidator->isValid($val) || !Zend_Validate::is(str_replace(':', '', $val), 'Digits')) ) {
                                $errorData = $data;
                                $rtn = false;
                                break;
                            }
                            break;
                        case "PrinterIP":                            
                        case "プリンター_IP":                            
                            if ( Zend_Validate::is($val, 'NotEmpty') && !$check->checkSpecCharForIP($val) ) {
                                $errorData = $data;
                                $rtn = false;
                                break;
                            }
                            break;
                        case "PrinterPort":                            
                        case "プリンター_ポート":                            
                            if ( Zend_Validate::is($val, 'NotEmpty') && 
                                (!Zend_Validate::is($val, 'Digits') || $val > PHP_INT_MAX) ) {
                                $errorData = $data;
                                $rtn = false;
                                break;
                            }
                            break;
                        default:
                            if ( Zend_Validate::is($val, 'NotEmpty') && !$check->checkSpecCharForName($val) ) { 
                                $errorData = $data;
                                $rtn = false;
                                break;
                            }
                            break;
                    }
                }
            }
        }
        
        if (!$rtn) {
            if (array_key_exists('menuCode', $errorData)) {
                $errorField = "商品ID=" . $errorData['menuCode'];
            } else {
                $errorField = "商品ID=" . $errorData['商品ID'];
            }            
        }
         
        return $rtn;
    }

    /**
     * Download csv data file
     *
     * @author Nguyen Huu Tam
     * @since 2013/02/25
     */
    public function downloadCsvAction() {
        //---get data session
        $session = Globals::getSession();
        $downloadFile = Globals::getDataFilePath(Application_Model_Index::MAIN_FILE);

        //---get data post
        $downloadType = $this->getRequest()->getParam('download_type');
        $this->_helper->formData->addData($this->getRequest()->getParams());
        if ($downloadType == '') {
            $session->success = 0;
            //---Not select char set
            $this->_helper->flashMessenger->addMessage(array('download_type_utf8' => $this->msgConfig->E300_RequireImportCharset));
            $this->_redirect('/product/downloadoption');
        }

        // If no exist file
        if (!file_exists($downloadFile)) {
            $session->success = 0;
            $this->_helper->flashMessenger->addMessage(
                    sprintf(
                            $this->msgConfig->C000_FileNotFound, Application_Model_Index::MAIN_FILE
                    )
            );

            $this->_redirect('/product/downloadoption');
        }

        //---get setting data
        $settingModel = new Application_Model_Setting();
        $this->file = $settingModel->getFilePath();
        $content = @file_get_contents($this->file);
        $json_setting = json_decode($content, true);
        $linkSystem = $json_setting['linkSystem'];

        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender();

        $newHeader = array();
        $headerEN = explode(',', $this->_fileConfig->csv->index->printer->download->upload->columns->en);
        $headerJP = explode(',', $this->_fileConfig->csv->index->printer->download->upload->columns->jp);
        foreach ($headerEN as $key => $value) {
            $newHeader[$value] = $headerJP[$key];
        }

        // Download file
        Application_Model_UploadBinary::createDownloadCsvFile(
                $this, $downloadFile, Application_Model_Index::MAIN_FILE, $downloadType, $linkSystem == self::LINK_SYSTEM_PRINTER ? $newHeader : null
        );
    }

}
