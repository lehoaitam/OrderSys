<?php
/**
 * Action SumarejiController
 * PHP version 5.3.9
 * @author Nguyen Huu Tam
 * @copyright Kobe Digital Labo, Inc
 * @since 2012/09/05
 */

class SumarejiController extends Zend_Controller_Action
{

    /**
     * Init action
     * 
     * @author Nguyen Huu Tam
     * @since 2012/09/05
     */
    public function init()
    {
        $this->msgConfig = Zend_Registry::get('MsgConfig');
        $this->session = Globals::getSession();
    }

    
    /**
     * Index action
     * 
     * @author Nguyen Huu Tam
     * @since 2012/09/05
     */
    public function indexAction()
    {
        // Init Csrf
        $this->view->csrf = $this->_helper->csrf->initInput();

        // Get messages
        $this->view->success = $this->session->success;
        $flashMsg = $this->_helper->getHelper('flashMessenger');
        if ($flashMsg->hasMessages()) {
            $this->view->message = $flashMsg->getMessages();
        }
        
        $formData = $this->_helper->getHelper('formData');
        if ($formData->hasData()) {
            $this->view->data = $formData->getData();
        } else {
            $api = new Application_Model_Api();
            $apiSetting = $api->getSetting()->toArray();
            
            if (empty($apiSetting)) {
                $api->saveSetting();
                $apiSetting = $api->getSetting()->toArray();
            }
            
            $this->view->data = $apiSetting;
        }
        
        $this->view->msgConfirmImport = $this->msgConfig->E501_Confirm_Import;
    }
    
    
    /**
     * Import API data action
     * 
     * @author Nguyen Huu Tam
     * @since 2012/09/05
     */
    public function importDataAction()
    {
        // Check Csrf
        $this->_helper->csrf->checkCsrf($this->getRequest());
        
        $postData = $this->getRequest()->getParams();
        $this->_helper->formData->addData($postData);
        
        // Validate input data
        $errors = $this->_validate($postData);
        if (count($errors)) {
            $this->session->success = 0;
            foreach ($errors as $key =>$error) {
                $this->_helper->flashMessenger->addMessage(array($key=>$error));
            }
            $this->_redirect('/sumareji');
        }
        
        // Init API model
        $api = new Application_Model_Api();
        
        // Save API setting
        $api->saveSetting($postData);
        
        // Sync API data
        $errors = $api->syncApi();
        if (count($errors) > 0 && !isset($errors['warning'])) {
            $this->session->success = 0;
            foreach ($errors as $error) {
                $this->_helper->flashMessenger->addMessage($error);
            }
        } else {
            $this->session->success = 1;
            
			if (isset($errors['warning'])) {
				unset($errors['warning']);
				foreach ($errors as $error) {
					$this->_helper->flashMessenger->addMessage($error);
				}
			} else {
				$this->_helper->flashMessenger->addMessage(
					$this->msgConfig->N501_Success_SyncData
				);
			}
        }
        
        if ($api->hasErrorMessage()) {
            $apiError = $api->getErrorMessages();
            foreach ($apiError as $error) {
                $this->_helper->flashMessenger->addMessage($error);
            }
        }
        
        // Pack data
        DataPacker::packDataInBatch($this->session->company_link);
        
        $this->_redirect('/sumareji');
    }
    
   

    /**
     * Validate post data
     * 
     * @return: array
     * @author Nguyen Huu Tam
     * @since 2012/09/05
     */
    protected function _validate($data)
    {
        $errors =  array();

        // 契約ID
        if (!Zend_Validate::is($data['contractId'], 'NotEmpty')){            
            $errors['contractId'] = $this->msgConfig->E501_Require_ContractId;
        }

        // 識別番号
        if (!Zend_Validate::is($data['at'], 'NotEmpty')){            
            $errors['at'] = $this->msgConfig->E501_Require_At;
        }
        
        return $errors;
    }
    
}