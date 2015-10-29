<?php
require_once('Zend/Config/Yaml.php');

class Menu
{
    protected $_container = null;
    protected $_current_view = null;
    
    public function __construct($container, $view)
    {
        $this->_container = $container;
        $this->_current_view = $view;

    }
    /**
     * 
     * get item to display header navigation
     * @author Lam Thanh Huy
     * @since 2012/07/05
     * @modified by Nguyen Huu Tam 2012/10/18
     */
    public function getHeaderNavigation($controller = '')
    {
        $menuPath = APPLICATION_PATH . '/configs/backend.menu.yaml';
        if (Globals::isMobile()) {
            $menuPath = APPLICATION_PATH . '/configs/backend.menu.mobile.yaml';
        }
        $config = new Zend_Config_Yaml($menuPath);
        $menu = $config->toArray();

        // Defend on linkSystem data
        $this->setMenu($menu);
        
        $session = Globals::getSession();
        if ($session->fullPermission === false) {
            $adminConfig = Globals::getApplicationConfig('admin');
            $exceptModules = explode(',', $adminConfig->except->modules);
            
            foreach ($exceptModules as $module) {
                $module = trim($module);
                if (isset($module, $menu)) {
                    unset($menu[$module]);
                }
            }
        }
        
        return $menu;
    }
    
    
    /**
     * Show/hide menu tab defend on linkSystem data
     * 
     * @param type $menu
     * @return type
     * @author Nguyen Huu Tam
     * @since 2013/02/25
     */
    protected function setMenu(&$menu)
    {
        $settingModel = new Application_Model_Setting();
        // Get current linkSystem value
        $curLinkSystem = $settingModel->getDataByKey('linkSystem');
        // Get all tabs
        $linkSystemMenuTabs = $settingModel->getLinkSystemMenuTabs();
        
        foreach ($linkSystemMenuTabs as $key => $tab) {
            // Show the menu tab
            if ($key == $curLinkSystem) {
                continue;
            }
            // Hide menu tab
            unset($menu[$tab]);
        }
        
        return $menu;
    }
}
