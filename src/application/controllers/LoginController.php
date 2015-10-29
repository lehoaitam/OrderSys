<?php
/**
 * Action Logincontroller
 * PHP version 5.3.9
 * @author Nguyen Thi Tho
 * @copyright Kobe Digital Labo, Inc
 * @since 2012/06/27
 */

class LoginController extends Zend_Controller_Action
{
    private $msgConfig;
    private $debugger;

    /**
     * Init values
     *
     * @return void
     * @author Nguyen Thi Tho
     * @since 2012/06/27
     */
    public function init()
    { 
        $this->_helper->layout()->setLayout('public');
        /* Initialize action controller here */
        $this->msgConfig = Zend_Registry::get('MsgConfig');
        $this->debugger = Zend_Registry::get('debugger');
        $this->formData = $this->_helper->getHelper('formData');
        
        $flash = $this->_helper->getHelper('flashMessenger');
        if ($flash->hasMessages()) {
            $this->view->message = $flash->getMessages();
        }
        if ($this->formData->hasData()) {
            $this->view->data = $this->formData->getData();
            $this->_helper->formData->addData($this->formData->getData());
        }
    }

    /**
     * Index
     *
     * @return void
     * @author Nguyen Thi Tho
     * @since 2012/06/27
     */
    public function indexAction()
    {
        $formData = $this->_helper->getHelper('formData');
        if ($formData->hasData()) {
            $this->view->data = $formData->getData();
        }
        
        // Init Csrf
        $this->view->csrf = $this->_helper->csrf->initInput();
    }

     /**
     * Logout
     *
     * @return clear session and redirect login
     * @author Nguyen Thi Tho
     * @since 2012/06/27
     */
    public function logoutAction()
    {        
        Globals::removeTmpUploadFolder();
        Zend_Session::destroy();
        $this->_redirect('/login');
    }

    /**
     * Login
     *
     * @return: success ? redirect to index : redirect login/index
     * @author Nguyen Thi Tho
     * @since 2012/06/27
     *
     */
    public function loginAction()
    {
        // Check Csrf
        $this->_helper->csrf->checkCsrf($this->getRequest());

        $session = Globals::getSession();
        $formData = $this->getRequest()->getPost();
        if ($this->_validate($formData)) {
            $this->_helper->formData->addData($formData);           
            if (($row = $this->_validateFile($formData))) {

                $session->company_name = $formData['txt_name'];
                $session->company_code = $formData['txt_code'];
                
                $key = $formData['txt_name']
                    . '-' . $formData['txt_code']
                    . '-' . $formData['txt_pass'];
                $row = $row[$key];
                $session->company_link = $row->getDirPath();
                Globals::generateSession();
                
                if ($session->controllerAcc != ''
                    && $session->controllerAcc != 'favicon.ico'
                    && $session->controllerAcc != 'index'
                    && $session->actionAcc != ''
                ){
                    $this->_redirect('/'. $session->controllerAcc. '/'. $session->actionAcc);
                } else {
                    $this->_redirect('/product');
                }
              
            } else {
                $this->_redirect('/login');
            }

        } else {
            $this->_helper->formData->addData($formData);
            $this->_redirect('/login');
        }                    
    }   

    /**
     * @function validate the value on the login form
     *
     * @return: bloole
     * @author Nguyen Thi Tho
     * @since 2012/06/28
     *
     */
    private function _validate($form_data)
    {
        $result = true;
        $flash  = $this->_helper->getHelper('flashMessenger');
        if ($flash->hasMessages()) {
            $this->view->message = $flash->getMessages();
        }
        if (!Zend_Validate::is($form_data['txt_name'], 'NotEmpty')) {
            $this->_helper->flashMessenger->addMessage($this->msgConfig->E100_Require_CompanyName);
            $result = false;
        }
        if (!Zend_Validate::is($form_data['txt_code'], 'NotEmpty')) {
            $this->_helper->flashMessenger->addMessage($this->msgConfig->E100_Require_CompanyCode);
            $result = false;
        }
        if (!Zend_Validate::is($form_data['txt_pass'], 'NotEmpty')) {
            $this->_helper->flashMessenger->addMessage($this->msgConfig->E100_Require_CompanyPass);
            $result = false;
        }
        return $result;
    }

     /**
     * @function check login information
     *
     * @return: bloole
     * @author Nguyen Thi Tho
     * @since 2012/06/28
     *
     */
    private function _validateFile(&$data)
    {
        $session = Globals::getSession();
        $adminConfig = Globals::getApplicationConfig('admin');

        if (strpos($data['txt_name'], $adminConfig->key) === false) {
            $session->fullPermission = false;
        } else {
            $session->fullPermission = true;
            $data['txt_name'] = str_replace($adminConfig->key, '', $data['txt_name']);
        }

        $key = $data['txt_name']
            . '-' . $data['txt_code']
            . '-' . $data['txt_pass'];

        $admin = new Application_Model_Admin();
        $result = $admin->findRowByKey($key);
        if (!$result) {
            $this->_helper->flashMessenger->addMessage(
                $this->msgConfig->E100_Require_CompanyAuth
            );
            return false;
        } 
        
        return $result;
    }
}
?>
