<?php /**  * Remap layouts file names based on UserAgent device.  */ 
class App_Controller_Plugin_DeviceLayout extends Zend_Controller_Plugin_Abstract {     
    public function dispatchLoopStartup(Zend_Controller_Request_Abstract $req) {         
        $layout = Zend_Layout::getMvcInstance();

        $inflector = $layout->getInflector();
        $inflector->setTarget(':device/:script.:suffix');
 
        if (!Globals::isMobile()) {
            $inflector->setStaticRule('device', 'pc');
        } else {
            $inflector->setStaticRule('device', 'sp');
        }
        
        return $req;
    }
}