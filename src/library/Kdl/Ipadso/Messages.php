<?php
/**
 * Load error message in specified configuration file
 *
 * @author Lam Thanh Huy
 * @copyright Kobe Digital Labo, Inc
 * @since 2012/06/27 
 */
class Kdl_Ipadso_Messages extends Zend_Config_Ini{
    public function  __construct(){
        parent::__construct(APPLICATION_PATH . '/configs/messages.ini');
    }
}