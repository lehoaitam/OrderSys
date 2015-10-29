<?php
/**
 * Action SumarejiController
 * PHP version 5.3.9
 * @author Nguyen Huu Tam
 * @copyright Kobe Digital Labo, Inc
 * @since 2012/09/05
 */

class TecrejiController extends Zend_Controller_Action
{

    /**
     * Init action
     * 
     * @author Nguyen Huu Tam
     * @since 2013/02/25
     */
    public function init()
    {
        $this->msgConfig = Zend_Registry::get('MsgConfig');
    }
    
    /**
     * Index action
     * 
     * @author Nguyen Huu Tam
     * @since 2013/02/25
     */
    public function indexAction()
    {
        // Init Csrf
        $this->view->csrf = $this->_helper->csrf->initInput();
        
        $session = Globals::getSession();
        $this->view->success = $session->success;
        
        $flashMsg = $this->_helper->getHelper('flashMessenger');
        if ($flashMsg->hasMessages()) {
            $this->view->message = $flashMsg->getMessages();
        }

        $this->view->alert = $this->msgConfig->E300_RequireFileUpload;
        $this->view->confirm = $this->msgConfig->E300_Confirm_ImportPos;
    }
    
    /**
     * @action: import product (.csv)
     *
     * @return void
     * @author Nguyen Thi Tho
     * @since 2012/07/30
     */
    public function uploaddataAction() {
        // Check Csrf
//        $this->_helper->csrf->checkCsrf($this->getRequest());

        //---get data session
        $session = Globals::getSession();
        $session->success = 0;
        
        $TecModel = new Application_Model_Tecreji();
        //check , read files 
        $rs = $TecModel->checkFileUpload($_FILES);

        if( count($rs) == 0 ){
            try{
                
                //data processing, update data
                $success = $TecModel->processsaveData();
                
                //// Pack data
                DataPacker::packDataInBatch($session->company_link);
                $session->success = 1;
                foreach ($success as $succes) {
                        $this->_helper->flashMessenger->addMessage($succes);
                }                
            } catch (Exception $e) {
                   Globals::log($e);
                   $this->_helper->flashMessenger->addMessage($e->getMessage());
                   
            }   
            
        } else {
            foreach ($rs as $key => $value) {
                $this->_helper->flashMessenger->addMessage(array($key=>$value));
            }
        }

        $this->_redirect('/tecreji/index');
    }
}
