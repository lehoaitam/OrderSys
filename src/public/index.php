<?php
// Define path to application directory
$protocol = (!empty($_SERVER['HTTPS'])) ? "https://" : "http://";
defined('APPLICATION_PATH')
    || define('APPLICATION_PATH', realpath(dirname(__FILE__) . '/../application'));

//Define site path
define('HOST_NAME', $protocol . $_SERVER['HTTP_HOST']);

//Define the site path
$site_path = realpath(dirname(__FILE__));
define('SITE_PATH', $site_path);

// Define application environment
defined('APPLICATION_ENV')
    || define('APPLICATION_ENV', (getenv('APPLICATION_ENV') ? getenv('APPLICATION_ENV') : 'production'));

// Ensure library/ is on include_path
set_include_path(implode(PATH_SEPARATOR, array(
    realpath(APPLICATION_PATH . '/../library'),
    get_include_path(),
)));

// Define the batch path
define('BATCH_DIR', APPLICATION_PATH . '/../batch');

/** Zend_Application */
require_once 'Zend/Application.php';

// Create application, bootstrap, and run
$application = new Zend_Application(
    APPLICATION_ENV,
    APPLICATION_PATH . '/configs/application.ini'
);
$application->bootstrap()
            ->run();
