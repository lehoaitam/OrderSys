<?php
/**
 * Action ManageImageController
 * PHP version 5.3.9
 * @author Nguyen Thi Tho
 * @copyright Kobe Digital Labo, Inc
 * @since 2012/07/24
 */

class ManageimageController extends Zend_Controller_Action
{
    private $msgConfig;    
    private $success;

    /**
     * Init values
     *
     * @return void
     * @author Nguyen Thi Tho
     * @since 2012/07/24
     */
    public function init()
    {
        $session = Globals::getSession();
        $this->company_code = $session->company_code;        
        $this->_path        = $session->company_link . '/image/';
        $this->msgConfig    =  Zend_Registry::get('MsgConfig');
        $flash              = $this->_helper->getHelper('flashMessenger');

        if ($flash->hasMessages()) {
            $this->view->message = $flash->getMessages();
        }
        
        $this->_imageConfig = Globals::getApplicationConfig('image');
        
        $this->view->maxUpload = $this->_imageConfig->productImg->max_upload;
        
        $this->view->notice = vsprintf(
            $this->msgConfig->N309_ProductImageFile_Notice 
            . '<br />' . $this->msgConfig->E309_MaxImageFiles, 
            array(
                $this->_imageConfig->productImg->max_size/1024,
                $this->_imageConfig->productImg->max_total
            )
        );
        
        
    }

    /**
     * index action
     *
     * @return void
     * @author Nguyen Thi Tho
     * @since 2012/07/24
     */
    public function indexAction()
    {
        $session = Globals::getSession();
        $this->view->success = $session->success;

        $flashMsg = $this->_helper->getHelper('flashMessenger');
        if ($flashMsg->hasMessages()) {
            $this->view->message = $flashMsg->getMessages();
        }
        // Init Csrf
        $this->view->csrf = $this->_helper->csrf->initInput();

        $this->view->dataImageList = '/manageimage/data';
        $this->view->alertDel      = $this->msgConfig->E309_NotDataDelImage;
        $this->view->confirmDel    = $this->msgConfig->E309_Confirm_Delete;
        $this->view->confirmAdd    = $this->msgConfig->E309_Confirm_Add;
    }

     /**
     * data action
     *
     * @return data has json style to view on the list
     * @author Nguyen Thi Tho
     * @since 2012/07/24
     */
    public function dataAction()
    {       
        $postData = $this->getRequest()->getParams();
      
        $page = isset($postData['page']) ? intval($postData['page']) : 1; 
        $limit = isset($postData['rows']) ? intval($postData['rows']) : 10;

        $image = new Application_Model_Image();
        $data = $image->getDataViewList($postData, $page, $limit);
        $this->_helper->json($data);
    }

    /**
     * upload a new product image
     *
     * @return void
     * @author Nguyen Thi Tho
     * @since 2012/07/24
     */

    public function uploadAction()
    {
        // Check Csrf
        $this->_helper->csrf->checkCsrf($this->getRequest());
        
        $path = Globals::getTmpUploadFolder();
        $files_info = $_FILES['p_image_file'];
        $this->file = $this->_getAllFilesName($files_info);
        $session = Globals::getSession();
        
        $image = new Application_Model_Image();
        if (!$image->checkMaxData($files_info)) {
            $this->_helper->flashMessenger->addMessage(
                sprintf($this->msgConfig->E309_MaxImageFiles, $this->_imageConfig->productImg->max_total)
            );
            $session->success = 0;
            $this->_redirect('/manageimage/');
        }
        
        $flash = $this->_helper->getHelper('flashMessenger');
        if ($flash->hasMessages()) {
            $this->view->message = $flash->getMessages();
        }

        $form_Data = $this->getRequest()->getPost();
        
        // Remove empty files
        $files = array_filter($_FILES['p_image_file']['name']);
        if (count($files)) {
            
            $result = $image->uploadImageMulti($this->_path, $files);
            if (is_array($result) 
                && count($result)
            ) {
                foreach ($result as $message) {
                    $this->_helper->flashMessenger->addMessage($message);
                }
                $session->success = 0;
            } else {
                $this->_helper->flashMessenger->addMessage($this->msgConfig->I309_UploadSuccessful );
                $session->success = 1;
                
                // Pack data
                DataPacker::packDataInBatch($session->company_link);
            }
        } else {
            $this->_helper->flashMessenger->addMessage($this->msgConfig->E309_RequireImage);
            $session->success = 0;
        }
        
        $this->_redirect('/manageimage/');
    }
    
   /**
     * delete product images
     *
     * @return void
     * @author Nguyen Thi Tho
     * @since 2012/07/24
     */
    public function deleteAction()
    {
        // Check Csrf
        $this->_helper->csrf->checkCsrf($this->getRequest());
        
        $session = Globals::getSession();
        $session->success = 0;

        $image = new Application_Model_Image();
        
        $arrNotDel = Array();
        $dataDel = isset($_POST['id_edit']) ? ($_POST['id_edit']) : '';
        $dataDel = explode(',', $dataDel);

        if($dataDel == '') {
            $this->_helper->flashMessenger->addMessage($this->msgConfig->E309_NotDataDelImage);
            $this->_redirect('/manageimage/');
        }       

        foreach ($dataDel as $key => $value) {
            $result = $image->checkForgeinKey($value);
            if ($result == 1) {
                try {
                     $image->deleteByKey($value);
                     Globals::log('Delete the images('.$value.')', null, $this->company_code.'.log');
                }
                catch (Exception $e) {
                    Globals::log('Delete the images unsuccessful ('.$value.')', null, $this->company_code.'.log');
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
        } else {
            $this->_helper->flashMessenger->addMessage($this->msgConfig->E309_ImageForeign);
            $session->success = 0;
        }
        
        // Pack data
        DataPacker::packDataInBatch($session->company_link);
        
        $this->_redirect('/manageimage/');
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
}
?>
