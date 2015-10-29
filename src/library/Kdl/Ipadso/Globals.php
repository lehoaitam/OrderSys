<?php
/**
 * Global objects of the application
 *
 * @author Lam Thanh Huy
 * @version 0.1 
 */
class Globals
{    
    /**
     * Config
     * 
     * @var Zend_Config_Ini
     */
    private static $_config = null;
    /**
     * Session
     * 
     * @var Zend_Session 
     */
    private static $_mySession= null;
    
    /**
     * Temporary upload folder name
     */
    const TEMP_UPLOAD_FOLDER = 'upload_tmp';
    
    /**
     * getCustomConfig
     * 
     * @return Zend_Config_Ini
     */
    public static function getGlobalConfig($section)
    {
        if (self::$_config != null) {
            return self::$_config;
        }
        
        self::$_config = new Zend_Config_Ini(APPLICATION_PATH . '/configs/global.ini', $section);
        
        return self::$_config;
    }   
    
    /**
     * getCustomConfig
     * 
     * @return Zend_Config_Ini
     */
    public static function getApplicationConfig($section)
    {
//        if (self::$_config != null) {
//            return self::$_config;
//        }
        
        self::$_config = new Zend_Config_Ini(APPLICATION_PATH . '/configs/application.ini', $section);
        
        return self::$_config;
    }  
    
    /**
     * getSession
     * 
     * @return Zend_Session_Namespace
     */
    static public function getSession()
    {        
        if (self::$_mySession != null) {
            return self::$_mySession;
        }
        self::$_mySession = new Zend_Session_Namespace();
        if (!isset(self::$_mySession->initialized)) {
            self::generateSession();
            self::$_mySession->initialized = true;
        }
        return self::$_mySession;        
    }
    
    /**
     * Generate new session id
     */
    static public function generateSession()
    {
        $config = self::getApplicationConfig('session');
        Zend_Session::setOptions($config->toArray());
        Zend_Session::regenerateId();
    }


    static public function isAuthenticated(){
        $company_code = self::getSession()->company_code;
        return $company_code;
    }
    
    static public function isRequiredLogin(){
        if (self::isAuthenticated()){
            return false;
        }
    }
    
    public static function isMobile() {
        $useragent = $_SERVER['HTTP_USER_AGENT'];

        if (preg_match('/(iP(hone|od|ad)|android|bb\d+|meego).+mobile|avantgo|bada\/|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|series(4|6)0|symbian|treo|up\.(browser|link)|vodafone|wap|windows (ce|phone)|xda|xiino/i', $useragent)||
            preg_match('/1207|6310|6590|3gso|4thp|50[1-6]i|770s|802s|a wa|abac|ac(er|oo|s\-)|ai(ko|rn)|al(av|ca|co)|amoi|an(ex|ny|yw)|aptu|ar(ch|go)|as(te|us)|attw|au(di|\-m|r |s )|avan|be(ck|ll|nq)|bi(lb|rd)|bl(ac|az)|br(e|v)w|bumb|bw\-(n|u)|c55\/|capi|ccwa|cdm\-|cell|chtm|cldc|cmd\-|co(mp|nd)|craw|da(it|ll|ng)|dbte|dc\-s|devi|dica|dmob|do(c|p)o|ds(12|\-d)|el(49|ai)|em(l2|ul)|er(ic|k0)|esl8|ez([4-7]0|os|wa|ze)|fetc|fly(\-|_)|g1 u|g560|gene|gf\-5|g\-mo|go(\.w|od)|gr(ad|un)|haie|hcit|hd\-(m|p|t)|hei\-|hi(pt|ta)|hp( i|ip)|hs\-c|ht(c(\-| |_|a|g|p|s|t)|tp)|hu(aw|tc)|i\-(20|go|ma)|i230|iac( |\-|\/)|ibro|idea|ig01|ikom|im1k|inno|ipaq|iris|ja(t|v)a|jbro|jemu|jigs|kddi|keji|kgt( |\/)|klon|kpt |kwc\-|kyo(c|k)|le(no|xi)|lg( g|\/(k|l|u)|50|54|\-[a-w])|libw|lynx|m1\-w|m3ga|m50\/|ma(te|ui|xo)|mc(01|21|ca)|m\-cr|me(rc|ri)|mi(o8|oa|ts)|mmef|mo(01|02|bi|de|do|t(\-| |o|v)|zz)|mt(50|p1|v )|mwbp|mywa|n10[0-2]|n20[2-3]|n30(0|2)|n50(0|2|5)|n7(0(0|1)|10)|ne((c|m)\-|on|tf|wf|wg|wt)|nok(6|i)|nzph|o2im|op(ti|wv)|oran|owg1|p800|pan(a|d|t)|pdxg|pg(13|\-([1-8]|c))|phil|pire|pl(ay|uc)|pn\-2|po(ck|rt|se)|prox|psio|pt\-g|qa\-a|qc(07|12|21|32|60|\-[2-7]|i\-)|qtek|r380|r600|raks|rim9|ro(ve|zo)|s55\/|sa(ge|ma|mm|ms|ny|va)|sc(01|h\-|oo|p\-)|sdk\/|se(c(\-|0|1)|47|mc|nd|ri)|sgh\-|shar|sie(\-|m)|sk\-0|sl(45|id)|sm(al|ar|b3|it|t5)|so(ft|ny)|sp(01|h\-|v\-|v )|sy(01|mb)|t2(18|50)|t6(00|10|18)|ta(gt|lk)|tcl\-|tdg\-|tel(i|m)|tim\-|t\-mo|to(pl|sh)|ts(70|m\-|m3|m5)|tx\-9|up(\.b|g1|si)|utst|v400|v750|veri|vi(rg|te)|vk(40|5[0-3]|\-v)|vm40|voda|vulc|vx(52|53|60|61|70|80|81|83|85|98)|w3c(\-| )|webc|whit|wi(g |nc|nw)|wmlb|wonu|x700|yas\-|your|zeto|zte\-/i', substr($useragent,0,4))) {
            return true;
        }
        return false;
    }
    
    public static function log($message, $level = null, $filename = '')
    {
        $level  = is_null($level) ? Zend_Log::DEBUG : $level;

        $log_folder = self::getVarFolder() . 'log/';
        $filename = empty($filename) ? 'system.log' : $filename;

        static $loggers = array();
        try{
            if (!isset($loggers[$filename])) {
                $logFile = $log_folder . $filename;
                if (!is_dir($log_folder)) {
                    mkdir($log_folder, 0777);
                }

                if (!file_exists($logFile)) {
                    file_put_contents($logFile, '');
                    chmod($logFile, 0777);
                }

                $format = '%timestamp% %priorityName% (%priority%): %message%' . PHP_EOL;
                $formatter = new Zend_Log_Formatter_Simple($format);
                $writer = new Zend_Log_Writer_Stream($logFile);
                $writer->setFormatter($formatter);
                $loggers[$filename] = new Zend_Log($writer);
            }
            if (is_array($message) || is_object($message)) {
                $message = print_r($message, true);
            }

            $loggers[$filename]->log($message, $level);
        }catch(Exception $exp){
            print_r($exp);
        }
    }
    /**
     * Write exception to log
     *
     * @param Exception $e
     */
    public static function logException(Exception $e)
    {
        self::log("\n" . $e->__toString(), Zend_Log::ERR, 'exception.log');
    }
    
    /**
     * dump a message to firebug - FireFox browser add-on
     */
    public static function firebug($message, $stop = false)
    {
        $debugger = new Zend_Log();
        $writer = new Zend_Log_Writer_Firebug();
        $debugger->addWriter($writer);
        $debugger->log($message, Zend_Log::INFO);
        if ($stop == true) {
            exit();
        }
    }
    
    /**
     * Get var folder
     * 
     * @return string
     */
    public static function getVarFolder()
    {
        return  APPLICATION_PATH . '/../var/';
    }
    
    public static function removeTmpUploadFolder()
    {
        $unique_key = date("Ymd") . md5(session_id()) ;
        $tmpUploadFolder = self::getVarFolder() 
            . self::TEMP_UPLOAD_FOLDER
            . DIRECTORY_SEPARATOR
            . $unique_key
            . DIRECTORY_SEPARATOR ;
                
        exec('/bin/rm -rf ' . escapeshellarg($tmpUploadFolder));
    }
    /**
     * Get temporary upload folder
     * 
     * @return string 
     */
    public static function getTmpUploadFolder()
    {
        $unique_key = date("Ymd") . md5(session_id()) ;
        $tmpUploadFolder = self::getVarFolder() 
            . self::TEMP_UPLOAD_FOLDER
            . DIRECTORY_SEPARATOR
            . $unique_key
            . DIRECTORY_SEPARATOR ;
        
        if (!is_dir($tmpUploadFolder)) {
             mkdir($tmpUploadFolder, 0777, true);
        }
        
        return $tmpUploadFolder;
    }
    
    /**
     *
     * @return boolean 
     * @author Nguyen Huu Tam
     * @since 2012/10/18
     */
    static public function isFullPermission()
    {
        $session = self::getSession();
        if ($session->fullPermission === true) {
            return true;
        }
        
        return false;
    }
    
    
    /**
     * Get csv data file path
     * 
     * @param string $fileName
     * @return string
     * @author Nguyen Huu Tam
     * @since 2013/02/25
     */
    public static function getDataFilePath($fileName)
    {
        $session = self::getSession();
        return $session->company_link
            . DIRECTORY_SEPARATOR
            . $fileName;
    }
    
    
    /**
     * Folder for store backup file 
     * 
     * @return string
     * @throws Zend_Config_Exception 
     * @author Nguyen Huu Tam
     * @since 2012/11/07
     */
    static public function getWorkFolder()
    {
        $dataConfig = self::getApplicationConfig('data');
        $msgConfig = Zend_Registry::get('MsgConfig');
        if ((is_null($dataConfig->work_folder))
            || ($dataConfig->work_folder == '')
        ) {
            throw new Zend_Config_Exception(
                sprintf($msgConfig->E200_Invalid_ConfigInfo, 'Section: data', 'work_folder')
            );
        }
        
        $session = Globals::getSession();
        $workFolder = $session->company_link 
                . DIRECTORY_SEPARATOR 
                . $dataConfig->work_folder
                . DIRECTORY_SEPARATOR;
        
        if (!is_dir($workFolder)) {
            mkdir($workFolder);
        }
        
        return $workFolder;
    }
}
