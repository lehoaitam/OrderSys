<?php
require_once 'helpers/FormData.php';
require_once 'helpers/Csrf.php';
require_once 'Kdl/Ipadso/Messages.php';
require_once 'models/Master.php';
require_once 'Kdl/Ipadso/Menu.php';
require_once 'Kdl/Ipadso/Globals.php';
require_once 'plugins/Auth.php';
require_once 'plugins/DeviceLayout.php';
require_once 'plugins/CsrfProtect.php';
require_once 'Zend/Controller/Front.php';
require_once 'Kdl/Ipadso/DataPacker.php';

class Bootstrap extends Zend_Application_Bootstrap_Bootstrap
{
    public function __construct($application)
    {
        parent::__construct($application);

        // add helpers
        Zend_Controller_Action_HelperBroker::addHelper(new Helpers_FormData());
        Zend_Controller_Action_HelperBroker::addHelper(new Helpers_Csrf());

        // create object to output debug to FireBug www.firephp.org
        $debugger = new Zend_Log();
        $writer = new Zend_Log_Writer_Firebug();
        $debugger->addWriter($writer);

        Zend_Registry::set('debugger', $debugger);

        $msg = new Kdl_Ipadso_Messages();
        Zend_Registry::set('MsgConfig', $msg);
    }
    
    protected function _initAutoload()
    {
        $autoloader = new Zend_Application_Module_Autoloader(
            array(
                'namespace' => '',
                'basePath' => dirname(__FILE__),
            )
        );
        return $autoloader;
    }
     
    protected function _initDoctype()
    {
        $this->bootstrap('view');
        $view = $this->getResource('view');
        $view->doctype('XHTML1_STRICT');
    }
    
    protected function _initRouter()
    {
        $front = Zend_Controller_Front::getInstance();
        $router = $front->getRouter();
        $router->addRoute(
            'logout',
            new Zend_Controller_Router_Route(
                'logout',
                array('controller' => 'login', 'action' => 'logout')
            )
        );
    }
    
    // Init plugins
    protected function _initPlugins()
    {
        $front = Zend_Controller_Front::getInstance();
        
        // Init Auth Plugin
        $front->registerPlugin(new Plugins_Auth(Zend_Auth::getInstance()));
        $front->registerPlugin(new App_Controller_Plugin_DeviceLayout(App_Controller_Plugin_DeviceLayout::getInstance()));
        
        $csrfConfig = Globals::getApplicationConfig('csrf');
        // Init CsrfProtect Plugin
        $protect = new Plugins_CsrfProtect(
            array(
                'expiryTime'    => 365 * 24 * 3600, //will make the CSRF key expire in 1 year
                'formId'        => $csrfConfig->formKey //will make the CSRF form element be called "safetycheck". Defaults to "csrf"
            )
        );
        $front->registerPlugin($protect);
    }

    // Init var folder
    protected function _initVarFolder()
    {
        //create var folder
        $folder = Globals::getVarFolder();
        
        if (!is_dir($folder)) {
            mkdir($folder, 0777);
        }
    }
}
