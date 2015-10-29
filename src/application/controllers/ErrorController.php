<?php

class ErrorController extends Zend_Controller_Action
{

    public function errorAction()
    {
        $session = Globals::getSession();
        if ($session->company_code == '') {
            $this->_redirect('/login');
        }
        
        $errors = $this->_getParam('error_handler');
        
        if (!$errors || !$errors instanceof ArrayObject) {
            $this->view->message = 'エラーページ';
            return;
        }
       
        switch ($errors->type) {
            case Zend_Controller_Plugin_ErrorHandler::EXCEPTION_NO_ROUTE:
            case Zend_Controller_Plugin_ErrorHandler::EXCEPTION_NO_CONTROLLER:
            case Zend_Controller_Plugin_ErrorHandler::EXCEPTION_NO_ACTION:
                // 404 error -- controller or action not found
                $this->getResponse()->setHttpResponseCode(404);
                $priority = Zend_Log::NOTICE;
                $this->view->message = 'ページが見つかりません。';
                break;
            default:
                // application error
                $this->getResponse()->setHttpResponseCode(500);
                $priority = Zend_Log::CRIT;
                $this->view->message = 'システムエラーが発生しました。';
                
                $exception = $errors->exception;
                switch(get_class($exception)) {
                    case 'Kdl_Ipadso_Csv_Exception':
                    case 'Application_Model_Exception':
                    case 'Zend_Config_Exception':
                        $this->view->message = $exception->getMessage();
                        break;
                    
                    case 'RuntimeException':
                        $this->view->message = $exception->getMessage();
                        Zend_Session::destroy();
                        $this->_redirect('/login');
                        break;
                }
                break;
        }
        
        Globals::logException($errors->exception);
        // Log exception, if logger available
        /*if ($log = $this->getLog()) {
            $log->log($this->view->message, $priority, $errors->exception);
            $log->log('Request Parameters', $priority, $errors->request->getParams());
        }
        
        // conditionally display exceptions
        if ($this->getInvokeArg('displayExceptions') == true) {
            $this->view->exception = $errors->exception;
        }
        
        $this->view->request   = $errors->request;
         */
         
    }

    public function getLog()
    {
        $bootstrap = $this->getInvokeArg('bootstrap');
        if (!$bootstrap->hasResource('Log')) {
            return false;
        }
        $log = $bootstrap->getResource('Log');
        return $log;
    }


}

