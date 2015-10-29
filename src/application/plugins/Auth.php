<?php
/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */
 class Plugins_Auth extends Zend_Controller_Plugin_Abstract
 {
     /*
       * @Var Zend_Auth
     */
     protected $_auth;

     public function  __construct(Zend_Auth $auth)
     {
          $this ->_auth = $auth;
     }

     public function  dispatchLoopStartup (Zend_Controller_Request_Abstract $request)
     {
        //The user is logged in
        //Check if the authenticated user tries to access the /login/index
        if ('login'  == $request->getControllerName()
            && 'index' == $request->getActionName()
        ) {
              return $this ->_redirect($request, 'login', 'index') ;
        } else {
            $session = Globals::getSession();
            //check login
            if ('login' != $request->getActionName()) {
                //check api
                if ($this->_checkAPIModules($request->getControllerName())) {
                    return;
                }
                if (!isset($session->company_code)) {
                    $session->controllerAcc = $request->getControllerName();
                    $session->actionAcc = $request->getActionName();
                    return $this ->_redirect($request, 'login', 'index') ;
                }

                // If normal admin
                if ($session->fullPermission === false) {
                    // Check module permission
                    if ($this->_checkModules($request->getControllerName())) {
                        return $this ->_redirect($request, 'product') ;
                    }
                }
            }
        }
    }

    protected function _redirect($request , $controller , $action = null)
    {
        if ($request->getControllerName() == $controller
            && $request->getActionName() == $action
        ) {
            return true;
        }

        $url = Zend_Controller_Front::getInstance()->getBaseUrl();
        $url .= '/' . $controller
            . '/' . $action;
        return $this ->_response->setRedirect($url) ;
    }
     
    /**
     * Check module permission
     * 
     * @param string $controllerName
     * @return boolean 
     * @author Nguyen Huu Tam
     * @since 2012/10/18
     */
    protected function _checkModules($controllerName)
    {
        $adminConfig = Globals::getApplicationConfig('admin');
        $exceptModules = explode(',', $adminConfig->except->modules);
            
        if (in_array($controllerName, $exceptModules)) {
            return true;
        }    

        return false;
     }
     
     /**
     * Check API module permission
     * 
     * @param string $controllerName
     * @return boolean 
     * @author nqtrung
     * @since 2015/05/19
     */
    protected function _checkAPIModules($controllerName)
    {
        $adminConfig = Globals::getApplicationConfig('admin');
        $exceptModules = explode(',', $adminConfig->api->modules);
            
        if (in_array($controllerName, $exceptModules)) {
            return true;
        }    

        return false;
    }
 }

?>
