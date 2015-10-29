<?php
/**
 * Class ApiImport
 *
 * @author Nguyen Huu Tam
 * @copyright Kobe Digital Labo, Inc
 * @since 2012/09/05
 */
require_once('Kdl/Ipadso/Csv/RecordSetEx.php');

class Application_Model_Api
{
    const API_SETTING_FILE = 'api_setting.json';
    
    protected $_interfaceList = array();
    protected $_errorMessages = array();

    public $companyName = '';
    public $companyLink = '';
    public $companyCode = '';
    
    public $companyUser = '';
    public $companyPass = '';
    public $companyUrl = '';


    public function __construct() 
    {
        $this->msgConfig = Zend_Registry::get('MsgConfig');
        
        $this->_interfaceList = array(
			'KdlItem'        => Application_Model_Index::API_LIST_METHOD,
            'KdlCategory'    => Application_Model_Category::API_LIST_METHOD,            
            'KdlSubcomment'  => Application_Model_SubComment::API_LIST_METHOD,
            'KdlItem_ToppingGroup'  => Application_Model_Topping::API_LIST_METHOD,
            'KdlItem_ToppingListItem'  => Application_Model_ToppingGroupItem::API_LIST_METHOD
        );
        
        $session = Globals::getSession();
        $this->companyName = $session->company_name;
        $this->companyLink = $session->company_link;
        $this->companyCode = $session->company_code;
        
        $ApiConfig = Globals::getApplicationConfig('sumareji');
        
        $this->companyUser = $ApiConfig->smaregi_user;
        $this->companyPass = $ApiConfig->smaregi_password;
        $this->companyUrl = $ApiConfig->smaregi_url;

    }
    
    
    /**
     * Get setting file path
     *
     * @return string
     * @author Nguyen Huu Tam
     * @since 2012/09/06
     */
    protected function _getSettingFile()
    {
        $settingFile = Globals::getDataFilePath(self::API_SETTING_FILE);
        
        // If file no exist
        if (!file_exists($settingFile)) {
            $config = new Zend_Config(array(), true);
            $this->_write2Json($config, $settingFile);
        }
        
        return $settingFile;
    }

    
    /**
     * Get setting information
     *
     * @return \Zend_Config_Json 
     * @author Nguyen Huu Tam
     * @since 2012/09/05
     */
    public function getSetting()
    {
        $config = new Zend_Config_Json(
            $this->_getSettingFile(),
            null,
            array('allowModifications' => true)
        );
        
        return $config;
    }

    
    /**
     * Save data setting
     *
     * @param array $data 
     * @author Nguyen Huu Tam
     * @since 2012/09/05
     */
    public function saveSetting($data = array())
    {
        $config = $this->getSetting();

        $config->companyName = $this->companyName;
        $config->companyCode = $this->companyCode;

        $config->contractId = isset($data['contractId']) ? $data['contractId'] : '';
        $config->at         = isset($data['at']) ? $data['at'] : '';
        
        $this->_write2Json($config, $this->_getSettingFile());
    }
    
    
    /**
     *
     * @param Zend_Config_Json $config 
     * @param string $file 
     * @author Nguyen Huu Tam
     * @since 2012/09/06
     */
    protected function _write2Json($config, $file)
    {
        $writer = new Zend_Config_Writer_Json(
            array(
                'config'    => $config,
                'filename'  => $file
            )
        );

        $writer->write();
    }
    
    /**
     * API request
     * 
     * @param string $service
     * @param string $method
     * @return json
     * @author Nguyen Huu Tam
     * @since 2012/09/06
     */
    protected function _getApiData($service, $method)
    {
        $setting = $this->getSetting();        
        
        // Authorization
        $auth = sprintf(
            "Authorization: Basic %s\r\n", 
            base64_encode($this->companyUser.':'.$this->companyPass )
        );
        
        $subParams = array(
            'at'            => $setting->at,
            'contractId'    => $setting->contractId
        );
        
        $queryParams = array(
            'service'   => $service . 'Service',
            'method'    => $method,
            'params'    => json_encode($subParams)
        );
        
        $queryString = http_build_query($queryParams);
       
        $options = array( 
            'http' => array(
                'method' => 'POST',
                'header'  => $auth
                    . "Content-type: application/x-www-form-urlencoded\r\n"
                    . "Content-Length: " . strlen($queryString) . "\r\n",
                'content' => $queryString 
            ) 
        );
        
        $ctx = stream_context_create($options);
        
        $requestResult = file_get_contents($this->companyUrl, false, $ctx);

        return $requestResult;
    }
    
    
    /**
     * API data synchronization
     * 
     * @return array 
     * @author Nguyen Huu Tam
     * @since 2012/09/07
     */
    public function syncApi()
    {
        $result = array();
        foreach ($this->_interfaceList as $service => $method) {
            $services = explode('_', $service);
            
            //---Call API
            $jsonData = $this->_getApiData($services[0], $method);
            
            //---get data;
            if ($msg = $this->importApi($jsonData, $method)) {
				if (is_array($msg)) {
					$result['warning'] = $msg['warning'];
					$result[$msg['msg']] = $msg['msg'];
				} else {
					$result[$msg] = $msg;
				}
            }
        }
        return $result;
    }
    
    
    /**
     * Import API data
     * 
     * @param json $jsonData
     * @return string|void 
     * @author Nguyen Huu Tam
     * @since 2012/09/07
     */
    public function importApi($jsonData, $method)
    {
        $apiData = json_decode($jsonData, true);
        
        if (isset($apiData['status']) && strtolower($apiData['status']) === 'fail') {
            $message = array('msg' => $apiData['message']);
			Globals::log($message['msg']);
            Globals::log($jsonData);
            return $message;
        }

        $message = '';
        $data = !is_null($apiData) ? $apiData['data'] : array();
        switch ($method) {
            case Application_Model_Category::API_LIST_METHOD:
                $object = new Application_Model_Category();
				$message = 'カテゴリーデータを%s件取り込みました。';
                break;
            case Application_Model_Index::API_LIST_METHOD:
                $object = new Application_Model_Index();
				$message = '商品データを%s件取り込みました。';
                break;
            case Application_Model_SubComment::API_LIST_METHOD:
                $object = new Application_Model_SubComment();
                $message = 'カスタムオーダーデータを%s件取り込みました。';
                break;
            
            case Application_Model_Topping::API_LIST_METHOD:
                $object = new Application_Model_Topping();
                $message = 'トッピンググループデータを%s件取り込みました。';
                break;
            case Application_Model_ToppingGroupItem::API_LIST_METHOD:
                $object = new Application_Model_ToppingGroupItem();
                
                break;
            default:
                return sprintf(
                    $this->msgConfig->E501_Unsupport_ApiMethod, 
                    $apiData['method']
                );
                
                break;
        }

        //---update data
        if (!is_null($data) && count($data) > 0 && !$this->_import($object, $data)) {
            $message = sprintf($this->msgConfig->E501_Fail_ApiImport, $apiData['method']);
        } else {
			$message = array('msg' => sprintf($message, count($data)), 'warning' => true);
			Globals::log($message['msg']);
            Globals::log(!is_null($apiData) ? $jsonData : 'json is null.');
		}
        
        return $message;
    }
    
    
    /**
     * Import data
     *
     * @param Object $object
     * @param array $data 
     * @author Nguyen Huu Tam
     * @since 2012/09/07
     */
    protected function _import($object, $data)
    {
        try {
            // New data
            $newData = $object->prepareData($data);
            
            $className = get_class($object);
            if (isset($newData['error'])) {
                $this->setErrorMessage($className::API_LIST_METHOD, $newData['error']);
            }
            
            // Add data
            $addData = array_diff_key($newData['data'], $object->getData());
            // Update data
            $updateData = $object->getUpdateApiData($newData['data']);

            $finalData = $updateData + $addData;

            $fileName = $className::MAIN_FILE;
            $tmpFile = self::getTmpApiFilePath($fileName);

            $object->renewData($tmpFile, $finalData);

            $currFile = $this->companyLink . DIRECTORY_SEPARATOR . $fileName;
            // Copy temp file to main file
            if (copy($tmpFile, $currFile)) {
                // Rename temp file for log
                $logFileName = array(
                    $this->companyName . $this->companyCode, 
                    date('YmdHis'), 
                    $fileName
                );
                rename($tmpFile, self::getTmpApiFilePath(implode('_', $logFileName)));
                return true;
            } else {
                Globals::log($this->msgConfig->E000_Failed_CopyFile);
                return false;
            }
        } catch (Kdl_Ipadso_Csv_Exception $e) {
            Globals::logException($e);
        }
        
        return false;
    }
    
    
    /**
     * Get API temporary folder path
     *
     * @param string $filename
     * @return string 
     * @author Nguyen Huu Tam
     * @since 2012/09/07
     */
    static public function getTmpApiFilePath($filename)
    {
        $tmpApiFolder = Globals::getTmpUploadFolder() . 'api/';
        // If no exist folder then create it
        if (!is_dir($tmpApiFolder)) {
            mkdir($tmpApiFolder, 0777);
        }
        $tmpFile = $tmpApiFolder . $filename;
        
        return $tmpFile;
    }

    
    /**
     *
     * @param array $messages 
     * @author Nguyen Huu Tam
     * @since 2012/10/22
     */
    public function setErrorMessage($prefix, $messages)
    {
        $currMessage = $this->getErrorMessages();
        foreach ($messages as $message) {
            $currMessage[] = "{$prefix}: {$message}";
        }
        
        $this->_errorMessages = $currMessage;
    }

        
    /**
     *
     * @return array 
     * @author Nguyen Huu Tam
     * @since 2012/10/22
     */
    public function getErrorMessages()
    {
        return $this->_errorMessages;
    }
    
    
    /**
     *
     * @return boolean 
     * @author Nguyen Huu Tam
     * @since 2012/10/22
     */
    public function hasErrorMessage()
    {
        if (count($this->getErrorMessages())) {
            return true;
        }
        
        return false;
    }
    
}
