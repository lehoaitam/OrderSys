<?php
/**
 * Action CategorytController
 * PHP version 5.3.9
 * @author Nguyen Thi Tho
 * @copyright Kobe Digital Labo, Inc
 * @since 2012/07/20
 */

class CategoryController extends Zend_Controller_Action
{
    private $msgConfig;    
    private $success;
    
    const ITEM_CODE = 'kind-code';
	
	const LINK_SYSTEM_PRINTER = '0';
	const LINK_SYSTEM_TEC = '1';

    /**
     * Init values
     *
     * @return void
     * @author Nguyen Thi Tho
     * @since 2012/07/20
     */
    public function init()
    {
        //---get data session
        $session = Globals::getSession();
        $this->company_code = $session->company_code;        
        $this->company_link = $session->company_link;   
        
        //---get data config
        $this->_uploadConfig = Globals::getApplicationConfig('upload');
        $this->msgConfig =  Zend_Registry::get('MsgConfig');
        
        $flash = $this->_helper->getHelper('flashMessenger');
        if ($flash->hasMessages()) {
            $this->view->message = $flash->getMessages();
        }
    }

    /**
     * Index
     *
     * @return void
     * @author Nguyen Thi Tho
     * @since 2012/07/20
     */
    public function indexAction()
    {
        //---get data session
        $session = Globals::getSession();        
        $this->view->success = $session->success;
        
        // Init Csrf
        $this->view->csrf = $this->_helper->csrf->initInput();

        $flashMsg = $this->_helper->getHelper('flashMessenger');
        if ($flashMsg->hasMessages()) {
            $this->view->message = $flashMsg->getMessages();
        }
        
        //---get data category
        $data = array();
        try {

            $postData = $this->getRequest()->getParams();       
            $session->paramsCategory = $postData;

            $page  = isset($postData['page']) ? intval($postData['page']) : 1;
            $limit = isset($postData['rows']) ? intval($postData['rows']) : Globals::getApplicationConfig('optlist')->list_count;
            if (!isset($postData['rows']) && isset($session->view_count_list)) {
                $limit = $session->view_count_list;
            }
            if ($limit == 0 || $limit == 'all') {
                $limit = null;
            }    
            
            $category = new Application_Model_Category();
            $data     = $category->getDataViewList($postData, $page, $limit);
            
        } catch (Kdl_Ipadso_Csv_Exception $e) {
            Globals::logException($e);
        }
        
        $this->view->dataCategoryList = $data;
        $this->view->alertDel        = $this->msgConfig->E303_NotDataDelCategory;
        $this->view->confirmDel      = $this->msgConfig->E303_Confirm_Delete;
        
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
        $this->formData  = $this->_helper->getHelper('formData');
        $content = @file_get_contents($this->file);
        $json_setting = json_decode($content,true);
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
     * @action Add a new category
     *
     * @return void
     * @author Nguyen Thi Tho
     * @since 2012/07/23
     */
    public function addAction()
    {
        // Init Csrf
        $this->view->csrf = $this->_helper->csrf->initInput();
		
		//check sumareji
		$settingModel = new Application_Model_Setting();
        $this->file = $settingModel->getFilePath();
        $content = @file_get_contents($this->file);
        $json_setting = json_decode($content,true);
        if ($json_setting['linkSystem'] == 2) {
            $this->_redirect('/category');
        }
        
        //---get data session
        $session = Globals::getSession();      
        if(isset($session->imageDelete)){
            unset($session->imageDelete);
        }
        
        $this->msgConfig    =  Zend_Registry::get('MsgConfig');
        $flash              = $this->_helper->getHelper('flashMessenger');
        if ($flash->hasMessages()) {
            $this->view->message = $flash->getMessages();
        }

        $this->formData = $this->_helper->getHelper('formData');
        $this->_helper->formData->addData($this->formData->getData());
        if ($this->formData->hasData()) {
            $this->view->data = $this->formData->getData();
        }
        $this->view->confirmAdd = $this->msgConfig->E304_Confirm_Add;
        $this->view->success    = $session->success;      
    }

    /**
     * @action Add a new category - Execute
     *
     * @return void
     * @author Nguyen Thi Tho
     * @since 2012/07/23
     */
    public function addexecuteAction()
    {
        // Check Csrf
        $this->_helper->csrf->checkCsrf($this->getRequest());

        //---get data session
        $session    = Globals::getSession();
        $this->category = new Application_Model_Category();

        $req = Zend_Controller_Front::getInstance()->getRequest();
        $this->action = $req->getActionName();

        $this->msgConfig    =  Zend_Registry::get('MsgConfig');
        $flash              = $this->_helper->getHelper('flashMessenger');
        if ($flash->hasMessages()) {
            $this->view->message = $flash->getMessages();
        }
        $postData = $this->getRequest()->getParams();
        
        $form_Data['kind'] = isset($postData['kind'])?$postData['kind']:'';
        $form_Data['code'] = $this->category->getNextCategoryId();
        $form_Data['name'] = isset($postData['name'])?$postData['name']:'';
                
        $this->_helper->formData->removeCsrfData($form_Data);
        $data = array($form_Data['kind'] . '-' . $form_Data['code'] => $form_Data);
        $this->_helper->formData->addData($form_Data);
        
        //---Validate data post
        if ($this->_validate($form_Data)) {
            try {
                $session->success = 1;
                //---upload file
                $image = $this->uploadImage();
                if (!$image) {
                        $this->_redirect('/category/add');
                        return;
                } else if (is_string($image)) {
                        $data[$form_Data['kind'] . '-' . $form_Data['code']]['image'] = $image;
                }

                //---insert data
                $this->category->insert($data);
                $this->view->data = $data[$form_Data['kind'].'-'.$form_Data['code']];
                $this->_helper->formData->addData($data[$form_Data['kind'].'-'.$form_Data['code']]);
                Globals::log('Insert category ('.$form_Data['code'] .')', null, $this->company_code.'.log');
                Globals::log($data, null, $this->company_code.'.log');
                $this->_helper->flashMessenger->addMessage($this->msgConfig->I304_AddCategorySuccessful);
                
                // Pack data
                DataPacker::packDataInBatch($session->company_link);
                
                $this->_redirect('/category/edit/code_edit/'.$form_Data['kind'] . '-' . $form_Data['code']);
            }
            catch (Exception $e) {
                 $session->success = 0;
                 Globals::log('Insert unsuccessful.', null, $this->company_code.'.log');
                 Globals::logException($e);
                 $this->_helper->flashMessenger->addMessage(array('kind'=>$this->msgConfig->E318_CanNotAddCategory));
                 $this->_redirect('/category/add');
            }
        } else {
             $session->success = 0;
             $this->_redirect('/category/add');
        }
       
    }

     /**
     * @action Edit a new category
     *
     * @return void
     * @author Nguyen Thi Tho
     * @since 2012/07/23
     */
    public function editAction()
    {
        // Init Csrf
        $this->view->csrf = $this->_helper->csrf->initInput();
        
        //---include library.
        $this->view->headScript()->offsetSetFile(20, (Globals::isMobile() ? '/sp' : '/pc') . '/js/jquery.fancybox.js');
        $this->view->headLink()->appendStylesheet((Globals::isMobile() ? '/sp' : '/pc') . '/css/jquery.fancybox.css');
        
        //---get data session
        $session    = Globals::getSession();
        
        //---get data post
        $postData = $this->getRequest()->getParams();
        
        $codeEdit  = isset($postData['code_edit']) ? ($postData['code_edit']) : '';
        $codeEdit_tamp = explode('-', $codeEdit);
        $session->kindEdit = isset($codeEdit_tamp[0])?$codeEdit_tamp[0]:'';
        $session->codeEdit = isset($codeEdit_tamp[1])?$codeEdit_tamp[1]:'';
        
        $this->msgConfig    =  Zend_Registry::get('MsgConfig');
        $flash              = $this->_helper->getHelper('flashMessenger');
        if ($flash->hasMessages()) {
            $this->view->message = $flash->getMessages();
        }

        $this->formData = $this->_helper->getHelper('formData');
        $this->_helper->formData->addData($this->formData->getData());

        if (isset($codeEdit_tamp[0]) && $codeEdit_tamp[0] == '') {
            $this->view->success = 0;
            $this->_helper->flashMessenger->addMessage($this->msgConfig->E301_NotGetDataToEdit);
            $this->_redirect('/category');
        }

        //---get data Category by id
        $csvCategory   = new Application_Model_Category();
        $id            = $codeEdit;
        $data          = $csvCategory->findRowByKey($id);
        
        if ($data === false) {
            $this->view->success = 0;
            $this->_helper->flashMessenger->addMessage(
                sprintf($this->msgConfig->E304_NotExist_Category, $id)
            );
            $this->_redirect('/category');
        }
        
        if(count($this->formData->getData())== 0) {
            $this->view->data = $data[$id];
        } else {
            $this->view->data =  $this->formData->getData();
        }
        
        $this->view->confirmEdit     = $this->msgConfig->E304_Confirm_Edit;
        $this->view->confirmDel     = $this->msgConfig->E303_Confirm_Delete;
        $this->view->success        = $session->success;   
		if (is_null($session->hide_first_mesage)) {
			$this->view->hide_first_mesage        = 0;
		} else {
			$this->view->hide_first_mesage        = $session->hide_first_mesage;
			$session->hide_first_mesage = 0;
		}
    }

     /**
     * @action Edit a new category - Execute
     *
     * @return void
     * @author Nguyen Thi Tho
     * @since 2012/07/23
     */
    public function editexecuteAction()
    {
        // Check Csrf
        $this->_helper->csrf->checkCsrf($this->getRequest());
        
        //---get data session
        $session    = Globals::getSession();
        
        //----get data post
        $postData = $this->getRequest()->getParams();
        $noEdit     = isset($postData['kind'])?$postData['kind']:'';
        $codeEdit   = isset($postData['code'])?$postData['code']:'';
        $key_id = $session->kindEdit . '-' . $session->codeEdit;
        
        $req = Zend_Controller_Front::getInstance()->getRequest();
        $this->action = $req->getActionName();
        
        $this->msgConfig    =  Zend_Registry::get('MsgConfig');
        $flash              = $this->_helper->getHelper('flashMessenger');
        if ($flash->hasMessages()) {
            $this->view->message = $flash->getMessages();
        }
        
        $this->category = new Application_Model_Category();
        $index          = new Application_Model_Index();

        //----get data post
        $form_Data  = $this->getRequest()->getPost();
        $this->_helper->formData->removeCsrfData($form_Data);
        
        //---create data Category
        $data = array();
        $data[$noEdit.'-'.$codeEdit]['kind'] = $form_Data['kind'];
        $data[$noEdit.'-'.$codeEdit]['code'] = $form_Data['code'];
        $data[$noEdit.'-'.$codeEdit]['name'] = $form_Data['name'];
        $data[$noEdit.'-'.$codeEdit]['image'] = $form_Data['image_category'];
        
        $this->_helper->formData->addData($data[$noEdit.'-'.$codeEdit]);
        
        //---Validate data post
        if ($this->_validate($form_Data)) {
            try {
                $session->success = 0;
                if($session->kindEdit == $form_Data['kind']){
                    //---Edit: no change kind;
                    $session->success = 1;
                    //---update file img
                    $image = $this->uploadImage();
                    if (!$image) {
                        $this->_redirect('/category/edit/code_edit/'.$key_id);
                    } else if (is_string($image)) { 
                        $data[$noEdit.'-'.$codeEdit]['image'] = $image;
                    }else {
                        $data[$noEdit.'-'.$codeEdit]['image'] = $form_Data['image_category'];
                    }
                    if( $data[$noEdit.'-'.$codeEdit]['image'] != $session->imageDelete && $session->imageDelete != '' ){
                        $filepath = realpath($this->company_link.'/image/'. $session->imageDelete);
                        // Pack data
                        DataPacker::packDataInBatch($session->company_link);
                        //---delete image
                        unlink($filepath);
                        //---delete thumb
                        unlink(Application_Model_Image::getThumpFolder() . 'product/' . $session->imageDelete);
                        
                    }
                    $this->category->update($data);
                    $this->_helper->formData->addData($data[$noEdit.'-'.$codeEdit]);
                    $index->updateCategoryName($form_Data['kind'], $form_Data['code'], $form_Data['name']);
                    unset($session->imageDelete);
                }else{
                    
                    //---Edit: change kind;
                        try {     
                            
                            //---update file img
                            $image = $this->uploadImage();
                            if (!$image) {
                                $this->_redirect('/category/edit/code_edit/'.$key_id);
                            } else if (is_string($image)) { 
                                $data[$noEdit.'-'.$codeEdit]['image'] = $image;
                            }else {
                                $data[$noEdit.'-'.$codeEdit]['image'] = $form_Data['image_category'];
                            }
                            if( $data[$noEdit.'-'.$codeEdit]['image'] != $session->imageDelete && $session->imageDelete != '' ){
                                $filepath = realpath($this->company_link.'/image/'. $session->imageDelete);
                                // Pack data
                                DataPacker::packDataInBatch($session->company_link);
                                //---delete image
                                unlink($filepath);
                                //---delete thumb
                                unlink(Application_Model_Image::getThumpFolder() . 'product/' . $session->imageDelete);
                            }
                            $session->success = 1;
                            //---delete
                            $this->category->deleteByKey($key_id);
                            
                            //---insert
                            $this->category->insert($data);
                            $this->_helper->formData->addData($data[$noEdit.'-'.$codeEdit]);
                            unset($session->imageDelete);
                        }
                        catch (Exception $e) { 
                            $session->success = 0;
                            Globals::log('Delete the category unsuccessful ('.$key_id.')', null, $this->company_code.'.log');
                            Globals::log($e);
                            $this->_helper->flashMessenger->addMessage($this->msgConfig->E000_CanNotEdit);
                        }

                }
                if( $session->success ){
                    Globals::log('Edit the category ('.$form_Data['code'].')', null, $this->company_code.'.log');
                    Globals::log($data, null, $this->company_code.'.log');
                    $this->_helper->flashMessenger->addMessage($this->msgConfig->I000_EditSuccessful);
                    // Pack data
                    DataPacker::packDataInBatch($session->company_link);
                    if(isset($session->nameCategory)){
                        unset($session->nameCategory);
                    }
                    $this->_redirect('/category/edit/code_edit/'.$form_Data['kind'] . '-' . $form_Data['code']);
                }else{   
                    $this->_redirect('/category/edit/code_edit/'.$key_id);
                }
            }
            catch (Exception $e) {
                 $session->success = 0;
                 Globals::log('Update the category unsuccessful ('.$key_id.')', null, $this->company_code.'.log');
                 Globals::logException($e);
                 $this->_helper->flashMessenger->addMessage(array('kind'=>$this->msgConfig->E000_CanNotEdit));
                 
                 $this->_redirect('/category/edit/code_edit/'.$key_id);
            }
        } else {
             $session->success = 0;
             $this->_redirect('/category/edit/code_edit/'.$key_id);
        }
        
    }
    /**
     * @action: import category
     *
     * @return void
     * @author Nguyen Thi Tho
     * @since 2012/08/02
     */
    public function importAction()
    {
        // Init Csrf
        $this->view->csrf = $this->_helper->csrf->initInput();
        
        //---get data session
        $session = Globals::getSession();
        $this->view->success = $session->success;
        $flashMsg = $this->_helper->getHelper('flashMessenger');
        if ($flashMsg->hasMessages()) {
            $this->view->message = $flashMsg->getMessages();
        }
		$this->formData = $this->_helper->getHelper('formData');
        if(count($this->formData->getData()) == 0 || (!is_null($session->success) && $session->success !== 0)) {
            $this->view->data = array('upload_type'=>'update','upload_charset'=>'SJIS');
        } else {
            $this->view->data =  $this->formData->getData();
        }

        $this->view->alertImp       = $this->msgConfig->E300_RequireFileUpload;
        $this->view->alertImportType = $this->msgConfig->E300_RequireImportType;
        $this->view->confiemImpCsv  = $this->msgConfig->E300_Confirm_ImportCsv;
        
        // CSVアップロード処理
        $this->view->importType         = Application_Model_UploadCsv::getImportType('common');
        $this->view->importTypeDefault  = Application_Model_UploadCsv::getImportTypeDefault('common');
    }

    
    /**
     * @action: import product (.csv)
     *
     * @return void
     * @author Nguyen Thi Tho
     * @since 2012/07/30
     */
    public function uploadcsvAction()
    {
        // Check Csrf
        $this->_helper->csrf->checkCsrf($this->getRequest());
        
        //---get data session
        $session = Globals::getSession();
        $session->success = 0;

        $csvCategory = new Application_Model_Category();
        $csvCategory->fetchAll();

        //---get data post
        $file_name    = $_FILES['page_csv']['name'];
        $file_type    = 'page_csv';
        $uploadType = $this->getRequest()->getParam('upload_type');
		$uploadCharset = $this->getRequest()->getParam('upload_charset');
		
		$charsetdeplay = str_replace('SJIS','SHIFT-JIS',$uploadCharset);

        //---check upload file
		$check_error = false;
        if( $uploadType == ''){
            //---Not select type
            $this->_helper->flashMessenger->addMessage(array('upload_type-new'=>$this->msgConfig->E300_RequireImportType));
			$check_error = true;
        }
		if( $uploadCharset == '' && $file_name != ''){
            //---Not select char set
            $this->_helper->flashMessenger->addMessage(array('upload_charset_utf8'=>$this->msgConfig->E300_RequireImportCharset));
            $check_error = true;
        }
        
        $mdelUp       = new Application_Model_UploadCsv(self::ITEM_CODE);
        $checkFile    = $mdelUp->checkFile($file_type, $file_name);
		
		//---check charset...    
        $checkCharset = $mdelUp->checkCharsetFileCsv($file_name,$uploadCharset);
        if( $checkCharset != '' && $file_name != ''){
            //---Not select char set
            $this->_helper->flashMessenger->addMessage(array('page_csv'=>  sprintf($this->msgConfig->E300_Chatset_Allow,  $charsetdeplay ) ) );
            $check_error = true;
        }

        if ($checkFile !== 1) {
            $this->_helper->flashMessenger->addMessage(array('page_csv' => $this->msgConfig->$checkFile));
        } else if( !$check_error ) {
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
            $file_name_up = $mdelUp->copyCategoryFileCsvWithCharSet($file_name, $uploadCharset, $linkSystem == self::LINK_SYSTEM_PRINTER);
            if ($file_name_up !== 0) {
                //check header file upload and file main
                if (($linkSystem === self::LINK_SYSTEM_PRINTER && count(array_diff(explode(',', $this->_uploadConfig->csv->category->printer->download->upload->columns->jp), $upHeader)) != 0) ||
						($linkSystem === self::LINK_SYSTEM_TEC && count(array_diff(explode(',', $this->_uploadConfig->csv->category->TEC->download->upload->columns->en), $upHeader)) != 0)) {
					Globals::log($this->msgConfig->C007_Header_invalid);
                    $this->_helper->flashMessenger->addMessage(array('page_csv'=>$this->msgConfig->E300_FileUploadInvalid));
                } else {
					$data_up = $mdelUp->getDataCategory($file_name_up, self::ITEM_CODE);
                    $uploadType = $this->getRequest()->getParam('upload_type');
                    switch ($uploadType) {
                        // 全てのデータを削除後、データの取り込みを行う
                        case $this->_uploadConfig->csv->new_import:
							//get old data
							$oldData = $csvCategory->getDataViewList(array());
							$oldData = $oldData['rows'];
							
							//replace csv
							if ($linkSystem === self::LINK_SYSTEM_TEC) {
								if (!copy($file_name_up, Globals::getDataFilePath(Application_Model_Category::MAIN_FILE))) {
									throw new Application_Model_Exception(
										sprintf($this->msgConfig->E000_Failed_CopyFile)
									);
								}
							} else {
								$csvCategory->deleteAllRow();								
								foreach ($data_up as $key => $row) {
									$data_up[$key] = $csvCategory->_fillDataFromSession($row, array());									
								}
								$csvCategory->insert($data_up);
							}
							
							//delete all images
							foreach ($oldData as $row) {
								$fileName = $row['image'];
								if (strlen($fileName) > 0) {
									$filepath = realpath($this->company_link . '/image/' . $fileName);
									unlink($filepath);
								}
							}

                            break;
                        // 同一ID以外のデータ取り込みを行う
                        case $this->_uploadConfig->csv->update_import:
                            foreach ($data_up as $key => $row) {
								$data_up[$key] = $csvCategory->_fillDataFromSession($row, array());
							}
							if ($linkSystem === self::LINK_SYSTEM_TEC) {
								$data_del = array_intersect_assoc($data_up, $csvCategory->getData());
							} else {
								$data_del = array_intersect_key($data_up, $csvCategory->getData());
							}
							$mdelUp->uploadCsvCategory($data_up, $data_del);
							
                            break;
                    }
                    
                    $this->_helper->flashMessenger->addMessage($this->msgConfig->E305_ImportCategorySuccessful);
                    $session->success = 1;
                    
                    // Pack data
                    DataPacker::packDataInBatch($session->company_link);
               }
            } else {
                $this->_helper->flashMessenger->addMessage($this->msgConfig->E305_CantImportCategory);
            }
        }
       $this->_redirect('/category/import');
    }
     /**
     * @action delete categorys
     *
     * @return void
     * @author Nguyen Thi Tho
     * @since 2012/07/23
     */
    public function deleteAction()
    {
        // Check Csrf
        $this->_helper->csrf->checkCsrf($this->getRequest());
        
        //---get session
        $session = Globals::getSession();
        $session->success = 0;

        $csvIndex = new Application_Model_Index();
        $csvCategory = new Application_Model_Category();
        
        //---get data post
        $postData = $this->getRequest()->getParams();
        $arrNotDel = Array();
        $noEdit     = isset($postData['kind'])?$postData['kind']:'';
        $codeEdit   = isset($postData['code'])?$postData['code']:'';
        
        $dataDel  = $noEdit.'-'.$codeEdit;
        
        
        if($dataDel == '') {
            $this->_helper->flashMessenger->addMessage($this->msgConfig->E303_NotDataDelCategory);
            $this->_redirect('/category/edit/code_edit/'.$noEdit . '-' . $codeEdit);

        }       
        $dataDel = explode(',', $dataDel);      
       
        $csvIndex->fetchAll();
        $arrIndex   = $csvIndex->getData();
        
        $csvCategory->fetchAll();
        $data       = $csvCategory->getData();

        foreach ($dataDel as $key => $value) {
            $rowFind = $data[$value];
            
            //---check in product use Category
            $result  = $csvCategory->checkForgeinKey($value);
            if ($result == 1) {
                try {
                    $fileName = $postData['image_category_old'];
                    if (strlen($fileName) > 0) {
                        //---delete file image
                        $this->imagedelete($value, $fileName);
                    }
                    //---delete Category
                     $csvCategory->deleteByKey($value);
                     Globals::log('Delete the category ('.$value.')', null, $this->company_code.'.log');
                }
                catch (Exception $e) {
                    Globals::log('Delete the category unsuccessful ('.$value.')', null, $this->company_code.'.log');
                    Globals::log($e);
                    $this->_helper->flashMessenger->addMessage($this->msgConfig->E000_CanNotDelete);
                }
            } else {
                $arrNotDel[] = $value;
            }
        }
        if (count($arrNotDel) == 0) {
            $this->_helper->flashMessenger->addMessage($this->msgConfig->I000_DeleteSuccessful);
            $session->success = 1;
            $this->_redirect('/category/');
        } else {
              $this->_helper->flashMessenger->addMessage($this->msgConfig->E300_ProductForeign);
              $session->success = 0;
			  $session->hide_first_mesage = 1;
        }
        
        // Pack data
        DataPacker::packDataInBatch($session->company_link);
        
        $this->_redirect('/category/edit/code_edit/'.$noEdit . '-' . $codeEdit);

    }  

     /**
     * @function validate the value on the product form (edit, add)
     *
     * @access private
     * @return boole
     * @author Nguyen Thi Tho
     * @since 2012/07/23
     * @param  array data on the form
     */
    private function _validate($form_data)
    {
        $result = true;
        
        //---get session data
        $session = Globals::getSession();
        $check   = new Application_Model_ValidateRules();
        $image   = new Application_Model_Image();
        
        $flash  = $this->_helper->getHelper('flashMessenger');
        if ($flash->hasMessages()) {
            $this->view->message = $flash->getMessages();
        }
        //kind
        if (!Zend_Validate::is($form_data['kind'], 'NotEmpty')) {
            $this->_helper->flashMessenger->addMessage(array('kind'=>$this->msgConfig->E304_Require_NoCategory));
            $result = false;
        }       
        if (Zend_Validate::is($form_data['kind'], 'NotEmpty')
            && !Zend_Validate::is($form_data['kind'], 'Digits')
           ) {
            $this->_helper->flashMessenger->addMessage(array('kind'=>$this->msgConfig->E304_Invalid_NoCategory));
            $result = false;
        }
        //code
        if (!Zend_Validate::is($form_data['code'], 'NotEmpty')) {
            $this->_helper->flashMessenger->addMessage(array('code'=>$this->msgConfig->E304_Require_CodeCategory));
            $result = false;
        }
        if (Zend_Validate::is($form_data['code'], 'NotEmpty')
            && !Zend_Validate::is($form_data['code'], 'Alnum')
           ) {
            $this->_helper->flashMessenger->addMessage(array('code'=>$this->msgConfig->E304_Invalid_CodeCategory));
            $result = false;
        }
        if (Zend_Validate::is($form_data['code'], 'NotEmpty')
             && strlen($form_data['code']) > 32
           ) {
                $this->_helper->flashMessenger->addMessage(array('code'=>$this->msgConfig->E304_MaxLength_CodeCategory));
                $result = false;
        }

        if ($form_data['image_category'] != ''
			&& (!$check->checkSpecCharForFileName($form_data['image_category'])
            || !array_keys($image->getArrayImage(),$form_data['image_category']))){
                $this->_helper->flashMessenger->addMessage(array('image[]'=>$this->msgConfig->E304_Invalid_Image));
                $result = false;
            }
        //name
        if (!Zend_Validate::is($form_data['name'], 'NotEmpty')) {
            $this->_helper->flashMessenger->addMessage(array('name'=>$this->msgConfig->E304_Require_NameCategory));
            $result = false;
        }        
        if (Zend_Validate::is($form_data['name'], 'NotEmpty')
            //&& !preg_match('/^[a-zA-Z0-9()]+$/', $form_data['name'],$matches)
               && !$check->checkSpecCharForName($form_data['name'])
            ) {
            $this->_helper->flashMessenger->addMessage(array('name'=>$this->msgConfig->E304_Invalid_NameCategory));
            $result = false;
        }
        if (Zend_Validate::is($form_data['name'], 'NotEmpty')
             && strlen($form_data['name']) > 85
           ) {
                $this->_helper->flashMessenger->addMessage(array('name'=>$this->msgConfig->E304_MaxLength_NameCategory));
                $result = false;
        }
        //image
        
        //
        if ($this->action == 'addexecute'
             && $this->category->findRowByKey($form_data['kind'].'-'.$form_data['code']) != ''
           ){
            $this->_helper->flashMessenger->addMessage(array('kind'=>$this->msgConfig->E304_ExistCategory));
            $result = false;
        }

        if ($this->action == 'editexecute'
             && $this->category->findRowByKey($form_data['kind'].'-'.$form_data['code']) != ''
             && $form_data['code'] != $session->codeEdit
           ){
            $this->_helper->flashMessenger->addMessage(array('kind'=>$this->msgConfig->E304_ExistCategory));
            $result = false;
        }
        return $result;
    }
	
	/**
     * @action: download option product
     *
     * @return void
     * @author Phan Quoc Bao
     * @since 2014/05/07
     */
    public function downloadoptionAction()
    {
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
        if(count($this->formData->getData()) == 0 || (!is_null($session->success) && $session->success !== 0)) {
            $this->view->data = array('download_type'=>'SJIS');
        } else {
            $this->view->data =  $this->formData->getData();
        }
        
    }
    
    /**
     * Download csv data file
     * 
     * @author Nguyen Huu Tam
     * @since 2013/02/25
     */
    public function downloadCsvAction()
    {
        $downloadFile = Globals::getDataFilePath(Application_Model_Category::MAIN_FILE);
		
		//---get data post
        $downloadType = $this->getRequest()->getParam('download_type');
        $this->_helper->formData->addData($this->getRequest()->getParams());
        if( $downloadType == '' ){
            $session->success = 0;
            //---Not select char set
            $this->_helper->flashMessenger->addMessage(array('download_type_utf8'=>$this->msgConfig->E300_RequireImportCharset));
            $this->_redirect('/category/downloadoption');
        }
		
        // If no exist file
        if (!file_exists($downloadFile)) {
            $session->success = 0;
            $this->_helper->flashMessenger->addMessage(
                sprintf(
                    $this->msgConfig->C000_FileNotFound,
                    Application_Model_Category::MAIN_FILE
                )
            );
            
            $this->_redirect('/category/downloadoption');
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
		$headerEN = explode(',', $this->_uploadConfig->csv->category->printer->download->upload->columns->en);
		$headerJP = explode(',', $this->_uploadConfig->csv->category->printer->download->upload->columns->jp);
		foreach ($headerEN as $key => $value) {
			$newHeader[$value] = $headerJP[$key];
		}

        // Download file
		$csvCategory = new Application_Model_Category();
		$csvCategory->fetchAll();
        Application_Model_UploadBinary::createDownloadCsvFileWithOrder(
			$csvCategory,
            $this,
            $downloadFile,
            Application_Model_Category::MAIN_FILE,
            $downloadType,
			$linkSystem == self::LINK_SYSTEM_PRINTER ? $newHeader : null
        );
    }
    
    private function uploadImage()
    {
        $files_info = $_FILES['image'];
        $this->file = $this->_getAllFilesName($files_info);
        $session = Globals::getSession();
        
        $image = new Application_Model_Image();
        if (!$image->checkMaxData($files_info)) {
            $this->_helper->flashMessenger->addMessage(array('image' =>
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
        $files = array_filter($_FILES['image']['name']);
		
        //check if existed
        foreach ($files as $file) {
                if (file_exists($session->company_link . '/image/' . $file) && $file != $session->imageDelete ) {
                        $this->_helper->flashMessenger->addMessage(array('image[]' => $this->msgConfig->E304_Existed_Image));
                        $session->success = 0;
                        return false;
                }
        }
        if (count($files)) {
            $result = $image->uploadImageMulti(Application_Model_Image::UPLOAD_CATEGORY_IMAGE, $session->company_link . '/image/', $files);	    	    
            if (is_array($result) 
                && count($result)
            ) {
                foreach ($result as $message) {
                    $this->_helper->flashMessenger->addMessage(array('image[]' => $message));
                }
                $session->success = 0;
                return false;
            } else {
                $session->success = 1;
                
                // Pack data
                DataPacker::packDataInBatch($session->company_link);
            }
        } else {
                return true;
        }
        
        return $files[0];
    }
        
	private function _getAllFilesName($files_info) 
    {
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
     * @action: download image from the folder that is defined
     *
     * @return void
     * @author Phan Quoc Bao
     * @since 2014/04/23
     */
    public function imageAction()
    {
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender();
        $fileName = $this->getRequest()->getParam('name');
        // Get image path

        $filepath = realpath($this->company_link.'/image/'. $fileName);

        if(file_exists($filepath)){
            // Response image to browser image
            $image = file_get_contents($filepath);
            $this->getResponse()->clearBody ();
            $this->getResponse()->setHeader('Content-Type', 'image/jpg');
            $this->getResponse()->setBody($image);
        }
    }
	
    
    private function imagedelete($code, $fileName) {echo $code.'--';
            $session = Globals::getSession();        
            // Get image path
            $filepath = realpath($this->company_link.'/image/'. $fileName);

            //check used
            $postData['page'] = 1;
            $search = array();
            $search['image']['eq'] = $fileName;
            $csvCategory = new Application_Model_Category();
            $data = $csvCategory->getDataViewList($postData, $search);

            $category = null;
            foreach ($data['rows'] as $row) {
                    if ($row['id'] == $code) {
                            $category = $row;
                            break;
                    }
            }
            if (is_null($category)) {			
                    return false;
            }
            //update csv
            $category['image'] = '';
            $csvCategory->update(array($code => $category));
            // Pack data
            DataPacker::packDataInBatch($session->company_link);		
            unlink($filepath);
            return true;
	}
    
    /**
     * @action: delete a product image
     *
     * @return void
     * @author Nguyen Thi Tho
     * @since 2012/07/11
     */
    public function imagedeleteAction()
    {
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender();
		
        $code = $this->getRequest()->getParam('code');
        $fileName = $this->getRequest()->getParam('name');
		
        $result = $this->imagedelete($code, $fileName);
        if (!$result) {
                $this->_helper->json(array('result' => 'false'));
                return;
        }
        $this->_helper->json(array('result' => 'true'));
    }
}
