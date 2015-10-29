<?php

/**
 * Action GetitemstatusController
 * PHP version 5.3.9
 * @author nqtrung
 * @copyright Kobe Digital Labo, Inc
 * @since 2015/05/18
 */
class GetitemstatusController extends Zend_Controller_Action {

    /**
     * Init values
     *
     * @return void
     * @author nqtrung
     * @since 2015/05/18
     */
    public function init() {

    }

    /**
     * @action: Index
     *
     * @return void
     * @author nqtrung
     * @since 2015/05/18
     */
    public function indexAction() {
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender();

        $postData = $this->getRequest()->getParams();
        if (!isset($postData['name']) || !isset($postData['code']) || !isset($postData['pass'])) {
            $this->_helper->json(array());
            return;
        }
        
        $data = array();
        //check login
        if (($row = $this->checkLogin($postData))) {
            $key = $postData['name']
                . '-' . $postData['code']
                . '-' . $postData['pass'];
            $row = $row[$key];
            $company_link = $row->getDirPath();
            
            $soldOut = new Application_Model_SoldOutProducts($row->getDirPath());

            $data['status'] = 'success';
            $data['revision'] = date('YmdHis');
            $data['data'] = $soldOut->getSoldoutProductsJsonData();
            $data['otherdata'] = array('recommendation_title' => $soldOut->getRecommendProductsTitle());
        }

        $this->_helper->json($data);
    } 

    private function checkLogin(&$data) {
        $adminConfig = Globals::getApplicationConfig('admin');

        if (strpos($data['name'], $adminConfig->key) !== false) {
            $data['name'] = str_replace($adminConfig->key, '', $data['name']);
        }

        $key = $data['name']
            . '-' . $data['code']
            . '-' . $data['pass'];

        $admin = new Application_Model_Admin();
        $result = $admin->findRowByKey($key);
        if (!$result) {
            return false;
        } 
        
        return $result;
    }
}
