<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of VideoController
 *
 * @author Sammy Guergachi <sguergachi at gmail.com>
 */
class VideoController extends Zend_Controller_Action
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

        $videoObj = new Application_Model_Video();
        
        //---get data video on list page
        $data     = $videoObj->getDataViewList($postData, $page, $limit);

        //---get data form config file
        $videoConfig = Globals::getApplicationConfig('video');
        $this->view->maxUpload = $videoConfig->upload->maxUpload;
        
        $this->view->dataMovie = $data;

        $this->msgConfig            = Zend_Registry::get('MsgConfig');
        
        $this->view->notice = vsprintf(
            $this->msgConfig->N700_ProductVideoFile_Notice 
            . '<br />' . $this->msgConfig->N700_MaxUploadVideoFiles, 
            array(
                Application_Model_Video::getFileSize($videoConfig->upload->maxSize),
                $videoConfig->upload->maxUpload
            )
        );
    }
    
    /**
     * @action Add a new category
     *
     * @return void
     * @author Phan Quoc Bao
     * @since 2014/04/24
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

        $this->formData = $this->_helper->getHelper('formData');
        $this->_helper->formData->addData($this->formData->getData());
        if ($this->formData->hasData()) {
            $this->view->data = $this->formData->getData();
        }
        $this->view->success    = $session->success;   
        
        $videoObj = new Application_Model_Video();
        $this->view->listNameExit = $videoObj->getListNameData();
        
        $videoConfig = Globals::getApplicationConfig('video');
        
        $this->view->maxSize = $videoConfig->upload->maxSize;
        $this->view->allowExtensions = $videoConfig->upload->allowExtensions;
        
        $this->view->msgE701_FileSizeMax = $this->msgConfig->E701_FileSizeTooBig;
        $this->view->msgE701_FileExtensionFalse = $this->msgConfig->E701_FileExtensionFalse;
        $this->view->msgE300_RequireFileUpload = $this->msgConfig->E300_RequireFileUpload;
        $this->view->msgE701_Existed = $this->msgConfig->E701_FileExisted;
        $this->view->alertSelect    = $this->msgConfig->E700_NotSelectData;
    }
    
    public function addexecuteAction()
    {
        // Check Csrf
        $this->_helper->csrf->checkCsrf($this->getRequest());
        
        //---get data session
        $session = Globals::getSession();
        
        // Init Messages
    	$this->msgConfig    =  Zend_Registry::get('MsgConfig');
        $flash              = $this->_helper->getHelper('flashMessenger');
        if ($flash->hasMessages()) {
            $this->view->message = $flash->getMessages();
        }
        
        //---get data post
        $namefile = isset($_FILES['videos']['name'][0])?$_FILES['videos']['name'][0]:'';
        
        //---upload file
        $videoObj = new Application_Model_Video();
        $result = $videoObj->upload($_FILES);
        
        if (is_array($result) && count($result)) {
            foreach ($result as $message) {
                $this->_helper->flashMessenger->addMessage(array('videos[]'=>$message));
            }
            $session->success = 0;
        } else {
            $this->_helper->flashMessenger->addMessage($this->msgConfig->I709_UploadSuccessful);
            $session->success = 1;

            // Pack data
            DataPacker::packDataInBatch($session->company_link);
            $this->_redirect('/video/edit/name/'.$namefile);
        }
        
        $this->_redirect('/video/add');
    }
    
    
    /**
     * @action Add a new category
     *
     * @return void
     * @author Phan Quoc Bao
     * @since 2014/04/24
     */
    public function editAction()
    {
        // Init Csrf
        $this->view->csrf = $this->_helper->csrf->initInput();
        
        //---get data session
        $session = Globals::getSession();      

        //---get data post
        $postData = $this->getRequest()->getParams();
        $session->action = $this->getRequest()->getActionName();
        
        // Init Messages
        $this->msgConfig    =  Zend_Registry::get('MsgConfig');
        $flash              = $this->_helper->getHelper('flashMessenger');
        if ($flash->hasMessages()) {
            $this->view->message = $flash->getMessages();
        }

        $this->formData = $this->_helper->getHelper('formData');
        $this->_helper->formData->addData($this->formData->getData());
        if(count($this->formData->getData())== 0) {
            $this->view->data = $postData;
        } else {
            $this->view->data =  $this->formData->getData();
        }
        $this->view->confirmDel    = $this->msgConfig->E700_ConfirmDelete;
        $this->view->success    = $session->success;    
        
        
        $videoObj = new Application_Model_Video();
        $this->view->listNameExit = $videoObj->getListNameData();
        
        $videoConfig = Globals::getApplicationConfig('video');
        
        $this->view->maxSize = $videoConfig->upload->maxSize;
        $this->view->allowExtensions = $videoConfig->upload->allowExtensions;
        
        $this->view->msgE701_FileSizeMax = $this->msgConfig->E701_FileSizeTooBig;
        $this->view->msgE701_FileExtensionFalse = $this->msgConfig->E701_FileExtensionFalse;
        $this->view->msgE300_RequireFileUpload = $this->msgConfig->E300_RequireFileUpload;
        $this->view->msgE701_Existed = $this->msgConfig->E701_FileExisted;
        $this->view->alertSelect    = $this->msgConfig->E700_NotSelectData;
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
        
        $videoObj = new Application_Model_Video();
        
        //---upload file video
        $result = $videoObj->upload($_FILES);
        $this->_helper->formData->addData($postData);
        
        if (is_array($result) && count($result)) {
            foreach ($result as $message) {
                $this->_helper->flashMessenger->addMessage(array('videos[]'=>$message));
            }
            $session->success = 0;
        } else {
            $this->_helper->flashMessenger->addMessage($this->msgConfig->I709_UploadSuccessful);
            $session->success = 1;
            $postData['name'] = $_FILES['videos']['name'][0];
            $this->_helper->formData->addData($postData);
            
            // Pack data
            DataPacker::packDataInBatch($session->company_link);
        }
        
        unset($session->nameVideo);
        $this->_redirect('/video/edit');
    }
    
    /**
     * delete video
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
        
        $video = new Application_Model_Video();
                
        //---get data post
        $postData = $this->getRequest()->getParams();
        $dataDel = isset($postData['name']) ? ($postData['name']) : '';

        if($dataDel == '') {
            $this->_helper->flashMessenger->addMessage($this->msgConfig->I709_NotDataDelVideo);
            $this->_redirect('/video/');
        }       

        //---delete 1 video by name video
        try {
            $video->deleteByKey($dataDel);
            Globals::log('Delete the Video('.$dataDel.')', null, $this->company_code.'.log');
            $this->_helper->flashMessenger->addMessage($this->msgConfig->I000_DeleteSuccessful);
            $session->success = 1;
        }
        catch (Exception $e) {
            Globals::log('Delete the Video unsuccessful ('.$dataDel.')', null, $this->company_code.'.log');
            Globals::log($e);
            $this->_helper->flashMessenger->addMessage($this->msgConfig->E000_CanNotDelete);
            
        }
        
        // Pack data
        DataPacker::packDataInBatch($session->company_link);
        
        $this->_redirect('/video/');
    }
    
}
