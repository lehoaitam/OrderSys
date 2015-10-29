<?php
/**
 * Action SettingController
 * PHP version 5.3.9
 * @author Nguyen Thi Tho
 * @copyright Kobe Digital Labo, Inc
 * @since 2012/07/03
 */

class SettingController extends Zend_Controller_Action
{
    /**
     * Init values
     *
     * @return void
     * @author Nguyen Thi Tho
     * @since 2012/07/03
     */
    public function init()
    {
        //---get session data
        $session = Globals::getSession();
        $this->company_code = $session->company_code;
        $this->company_name = $session->company_name;
        $this->company_link = $session->company_link;
        
        $settingModel = new Application_Model_Setting();
        $this->file = $settingModel->getFilePath();

        // If file does not exist then create empty file
        if (!is_file($this->file)) {
            $config = new Zend_Config(array(), true);
            $writer = new Zend_Config_Writer_Json();
            $writer->setConfig($config)
                ->setFilename($this->file)
                ->write();
        }
        
        $this->msgConfig =  Zend_Registry::get('MsgConfig');
        $this->debugger  = Zend_Registry::get('debugger');
        $this->formData  = $this->_helper->getHelper('formData');

        $flash = $this->_helper->getHelper('flashMessenger');
        if ($flash->hasMessages()) {
            $this->view->message = $flash->getMessages();
        } 
        
        $this->config = Globals::getApplicationConfig('setting');
    }

    /**
     * Index
     *
     * @return void
     * @author Nguyen Thi Tho
     * @since 2012/07/06
     */
    public function indexAction()
    {
        // Init Csrf
        $this->view->csrf = $this->_helper->csrf->initInput();

        //---get session data
        $session = Globals::getSession();
        
        //---get data from file setting.json
        $content = @file_get_contents($this->file);
        $json_setting = json_decode($content,true);
        $formData = $this->_helper->getHelper('formData');
        $this->view->success = $session->success;
        
        $flashMsg = $this->_helper->getHelper('flashMessenger');
        if ($flashMsg->hasMessages()) {
            $this->view->message = $flashMsg->getMessages();
        }

        $data = $formData->getData();
        if (isset($data['stationAddress']) && $session->success != 1) {
            $data = $formData->getData();
        } else {
            $data = $json_setting;
        }

        /*$settingModel = new Application_Model_Setting();
        $columns = $settingModel->getColumns();
        foreach ($columns as $column) {
            if (!isset($data[$column])) {
                $data[$column] = '';
            }
        }*/

        // 連携システム選択
        $this->_setDefault($data, 'linkSystem', $this->config->linkSystem->defaultOption);		
        // 画面の方向
        $this->_setDefault($data, 'screenOrientation', $this->config->screenOrientation->defaultOption);
        // 店員呼出ボタンの表示
        $this->_setDefault($data, 'useCallStaff', $this->config->useCallStaff->defaultOption);
        // おすすめメニューボタン表示
        $this->_setDefault($data, 'recommendationVisible', $this->config->recommendationVisible->defaultOption);
        // データ自動更新 
        $this->_setDefault($data, 'automaticUpdate', $this->config->automaticUpdate->defaultOption);
        // トップページに席番号の表示
        $this->_setDefault($data, 'dispTableName', $this->config->dispTableName->defaultOption);
        // 注文ボタンの表示
        $this->_setDefault($data, 'orderStationFlag', $this->config->orderStationFlag->defaultOption);
        // 割り勘表示
        $this->_setDefault($data, 'useWarikan', $this->config->useWarikan->defaultOption);
        // 汎用リンクボタンの表示
        $this->_setDefault($data, 'generallinkFlag', $this->config->generallinkFlag->defaultOption);
        // 汎用リンクボタンの表示
        $this->_setDefault($data, 'generallinkFlag', $this->config->generallinkFlag->defaultOption);		
		// プリンター機種選択
        $this->_setDefault($data, 'printerType', $this->config->printerType->defaultOption);		
		// 会計伝票プリンターポート
        $this->_setDefault($data, 'printerPortNo', $this->config->printerPortNo->defaultOption);
        // 注文時に印刷を行う
        $this->_setDefault($data, 'doPrintFlag', $this->config->doPrintFlag->defaultOption);
        // 会計伝票の印刷枚数
        $this->_setDefault($data, 'doPrintCount', $this->config->doPrintCount->defaultOption);
        // 会計伝票の印刷枚数
        $this->_setDefault($data, 'doPrintCount', $this->config->doPrintCount->defaultOption);        
		// 注文伝票の印刷を行う
        $this->_setDefault($data, 'doOrderPrintFlag', $this->config->doOrderPrintFlag->defaultOption);		
        // 印刷時に音声を鳴らす
        $this->_setDefault($data, 'doPrintSoundFlag', $this->config->doPrintSoundFlag->defaultOption);
        // 会計伝票改ページ有無
        $this->_setDefault($data, 'bigSlipPageBreak', $this->config->bigSlipPageBreak->defaultOption);
        // 会計伝票改ページ商品数
        $this->_setDefault($data, 'printItemsPerPage', $this->config->printItemsPerPage->defaultOption);
        // 印字文字サイズ拡大
        $this->_setDefault($data, 'charPrintSizeExpansion', $this->config->charPrintSizeExpansion->defaultOption);
        // TCP/IP受信用ポート番号
        $this->_setDefault($data, 'receivePort', $this->config->receivePort->defaultOption);
        // TCP/IP送信用ポート番号
        $this->_setDefault($data, 'sendPort', $this->config->sendPort->defaultOption);
        // ソケット通信リトライ回数
        $this->_setDefault($data, 'socket_retry', $this->config->socket_retry->defaultOption);
        // ソケット通信待ち時間
        $this->_setDefault($data, 'socket_waitSec', $this->config->socket_waitSec->defaultOption);
        // スタッフ呼び出し用メニューコード
        $this->_setDefault($data, 'staffCallCode', $this->config->staffCallCode->defaultOption);
        // 連携URL
        $this->_setDefault($data, 'smaregi_url', $this->config->smaregi_url->defaultOption);

        $this->view->data = $data;
        $this->view->confirmEdit = $this->msgConfig->E200_Confirm_Update;
        
        $this->view->dataOrderStationFlag       = $this->getFlag('注文ボタンの表示');
        $this->view->useWarikan                 = $this->getUseWarikanFlag('割り勘表示');
        $this->view->dataLinkSystem             = $this->getLinkSystem();
        $this->view->screenOrientation          = $this->getScreenOrientation();
        $this->view->useCallStaff               = $this->getUseCallStaff();
        $this->view->recommendationVisible      = $this->getRecommendationVisible();
        $this->view->automaticUpdate            = $this->getAutomaticUpdate();
        $this->view->dispTableName              = $this->getDispTableName();
        $this->view->printerType				= $this->getPrinterType();
        $this->view->doOrderPrintFlag           = $this->getDoOrderPrintFlag();
        $this->view->dataDoPrintFlag            = $this->getDoPrintFlag();
        $this->view->dataDoPrintSoundFlag       = $this->getDoPrintSoundFlag();
    }
    
    
    /**
     *
     * @param array $data
     * @param string $key
     * @param string $value
     * @author Nguyen Huu Tam
     * @since 2012/11/06 
     */
    protected function _setDefault(&$data, $key, $value)
    {
        if (!isset($data[$key])) {
            $data[$key] = $value;
        }
    }

        
    /**
     * 注文ボタンの表示
     * 汎用リンクボタンの表示
     * 
     * @return array
     * @author Nguyen Huu Tam
     * @since 2012/10/05
     */
    public function getFlag($name = '')
    {
        $flag = $this->config->flag->options;
        if (!is_null($flag) 
            && is_object($flag)
        ) {
            $optionData[''] = '-- 選択してください --';
            $optionData += $flag->toArray();
            
            return $optionData;
        } else {
            require_once 'Zend/Config/Exception.php';
            throw new Zend_Config_Exception(
                sprintf($this->msgConfig->E200_Invalid_ConfigInfo, $name, 'flag')
            );
        }
    }
    
    /**
     * 
     * @return array
     * @author nqtrung
     * @since 2015/03/24
     */
    public function getUseWarikanFlag($name = '')
    {
        $flag = $this->config->useWarikan->options;
        if (!is_null($flag) 
            && is_object($flag)
        ) {
            $optionData = $flag->toArray();
            
            return $optionData;
        } else {
            require_once 'Zend/Config/Exception.php';
            throw new Zend_Config_Exception(
                sprintf($this->msgConfig->E200_Invalid_ConfigInfo, $name, 'flag')
            );
        }
    }
    
    /**
     * 連携システム選択
     * 
     * @return array
     * @author Nguyen Huu Tam
     * @since 2012/11/06
     */
    public function getLinkSystem()
    {
        $linkSystem = $this->config->linkSystem->options;
        
        if (!is_null($linkSystem) 
            && is_object($linkSystem)
        ) {
            return $linkSystem->toArray();
        } else {
            require_once 'Zend/Config/Exception.php';
            throw new Zend_Config_Exception(
                sprintf($this->msgConfig->E200_Invalid_ConfigInfo, '連携システム選択', 'linkSystem')
            );
        }
    }
    
	/**
     * 画面の方向
     * 
     * @return array
     * @author nqtrung
     * @since 2014/07/04
     */
    public function getScreenOrientation()
    {
        $screenOrientation = $this->config->screenOrientation->options;
        
        if (!is_null($screenOrientation) 
            && is_object($screenOrientation)
        ) {
            return $screenOrientation->toArray();
        } else {
            require_once 'Zend/Config/Exception.php';
            throw new Zend_Config_Exception(
                sprintf($this->msgConfig->E200_Invalid_ConfigInfo, '画面の方向', 'screenOrientation')
            );
        }
    }
	
	/**
     * 店員呼出ボタンの表示
     * 
     * @return array
     * @author nqtrung
     * @since 2014/07/04
     */
    public function getUseCallStaff()
    {
        $useCallStaff = $this->config->useCallStaff->options;
        
        if (!is_null($useCallStaff) 
            && is_object($useCallStaff)
        ) {
            return $useCallStaff->toArray();
        } else {
            require_once 'Zend/Config/Exception.php';
            throw new Zend_Config_Exception(
                sprintf($this->msgConfig->E200_Invalid_ConfigInfo, '店員呼出ボタンの表示', 'useCallStaff')
            );
        }
    }
    
    /**
     * 店員呼出ボタンの表示
     * 
     * @return array
     * @author nqtrung
     * @since 2014/07/04
     */
    public function getRecommendationVisible()
    {
        $recommendationVisible = $this->config->recommendationVisible->options;
        
        if (!is_null($recommendationVisible) 
            && is_object($recommendationVisible)
        ) {
            return $recommendationVisible->toArray();
        } else {
            require_once 'Zend/Config/Exception.php';
            throw new Zend_Config_Exception(
                sprintf($this->msgConfig->E200_Invalid_ConfigInfo, 'おすすめメニューボタン表示', 'recommendationVisible')
            );
        }
    }
	
    /**
     * データ自動更新 
     * 
     * @return array
     * @author ththo
     * @since 2015/09/04
     */
    public function getAutomaticUpdate()
    {
        $automaticUpdate = $this->config->automaticUpdate->options;
        
        if (!is_null($automaticUpdate) 
            && is_object($automaticUpdate)
        ) {
            return $automaticUpdate->toArray();
        } else {
            require_once 'Zend/Config/Exception.php';
            throw new Zend_Config_Exception(
                sprintf($this->msgConfig->E200_Invalid_ConfigInfo, 'データ自動更新 ', '$automaticUpdate')
            );
        }
    }
	/**
     * トップページに席番号の表示
     * 
     * @return array
     * @author nqtrung
     * @since 2014/07/04
     */
    public function getDispTableName()
    {
        $dispTableName = $this->config->dispTableName->options;
        
        if (!is_null($dispTableName) 
            && is_object($dispTableName)
        ) {
            return $dispTableName->toArray();
        } else {
            require_once 'Zend/Config/Exception.php';
            throw new Zend_Config_Exception(
                sprintf($this->msgConfig->E200_Invalid_ConfigInfo, 'トップページに席番号の表示', 'dispTableName')
            );
        }
    }
	
	/**
     * プリンター機種選択
     * 
     * @return array
     * @author nqtrung
     * @since 2014/07/04
     */
    public function getPrinterType()
    {
        $printerType = $this->config->printerType->options;
        
        if (!is_null($printerType) 
            && is_object($printerType)
        ) {
            return $printerType->toArray();
        } else {
            require_once 'Zend/Config/Exception.php';
            throw new Zend_Config_Exception(
                sprintf($this->msgConfig->E200_Invalid_ConfigInfo, 'プリンター機種選択', 'printerType')
            );
        }
    }
	
	/**
     * 注文伝票の印刷を行う
     * 
     * @return array
     * @author nqtrung
     * @since 2014/07/04
     */
    public function getDoOrderPrintFlag()
    {
        $doOrderPrintFlag = $this->config->doOrderPrintFlag->options;
        
        if (!is_null($doOrderPrintFlag) 
            && is_object($doOrderPrintFlag)
        ) {
            return $doOrderPrintFlag->toArray();
        } else {
            require_once 'Zend/Config/Exception.php';
            throw new Zend_Config_Exception(
                sprintf($this->msgConfig->E200_Invalid_ConfigInfo, '注文伝票の印刷を行う', 'doOrderPrintFlag')
            );
        }
    }
    
    /**
     * 注文時に印刷を行う
     * 
     * @return array
     * @author Nguyen Huu Tam
     * @since 2012/11/06
     */
    public function getDoPrintFlag()
    {
        $doPrintFlag = $this->config->doPrintFlag->options;
        
        if (!is_null($doPrintFlag) 
            && is_object($doPrintFlag)
        ) {
            return $doPrintFlag->toArray();
        } else {
            require_once 'Zend/Config/Exception.php';
            throw new Zend_Config_Exception(
                sprintf($this->msgConfig->E200_Invalid_ConfigInfo, '注文時に印刷を行う', 'doPrintFlag')
            );
        }
    }
    
    
    /**
     * 印刷時に音声を鳴らす
     * 
     * @return array
     * @author Nguyen Huu Tam
     * @since 2012/11/06
     */
    public function getDoPrintSoundFlag()
    {
        $doPrintSoundFlag = $this->config->doPrintSoundFlag->options;
        
        if (!is_null($doPrintSoundFlag) 
            && is_object($doPrintSoundFlag)
        ) {
            return $doPrintSoundFlag->toArray();
        } else {
            require_once 'Zend/Config/Exception.php';
            throw new Zend_Config_Exception(
                sprintf($this->msgConfig->E200_Invalid_ConfigInfo, '印刷時に音声を鳴らす', 'doPrintSoundFlag')
            );
        }
    }
    
    /**
     * Setting
     *
     * @return void
     * @author Nguyen Thi Tho
     * @since 2012/07/10
     */
    public function settingAction()
    {
        // Check Csrf
        $this->_helper->csrf->checkCsrf($this->getRequest());

        //---get data session
        $session    = Globals::getSession();
        
        //---get data post
        $json_Data  = $this->getRequest()->getPost();
        $this->_helper->formData->removeCsrfData($json_Data);

        //---Validate data post
        if ($this->_validate($json_Data)) {
            $session->success = 1;
            $this->_helper->formData->addData($json_Data);
            
            //---update data.
			$content = @file_get_contents($this->file);
			$json_setting = array_merge(json_decode($content, true), $json_Data);
			//set default values
			foreach ($json_setting as $key => $value) {
				if (!isset($json_Data[$key]) && !is_null($this->config->$key->defaultOptions)) {
					$json_setting[$key] = $this->config->$key->defaultOptions;
				}
			}
                        
            $this->_update($this->file, $json_setting);
           
            // Pack data
            DataPacker::packDataInBatch($session->company_link);
        } else {
            $this->_helper->formData->addData($json_Data);
            $session->success = 0;
        }
        
        $this->_redirect('/setting');
    }
    /**
     * validate the value on the setting form
     *
     * @access private
     * @param  array data on the form
     * @return boole
     * @since  2012/07/06
     */

    private function _validate($form_data)
    {
        $result = true;
        $check  = new Application_Model_ValidateRules();

        $flash  = $this->_helper->getHelper('flashMessenger');
        if ($flash->hasMessages()) {
            $this->view->message = $flash->getMessages();
        }
        
        // 連携システムの選択
        if (!Zend_Validate::is($form_data['linkSystem'], 'NotEmpty')) {
            $this->_helper->flashMessenger->addMessage(array('linkSystem' => $this->msgConfig->E200_Require_LinkSystem));
            $result = false;
        } else {
            if (!array_key_exists($form_data['linkSystem'], $this->getLinkSystem())) {
                $this->_helper->flashMessenger->addMessage(array('linkSystem' => $this->msgConfig->E200_Invalid_LinkSystem));
                $result = false;
            }
        }
		
		// 画面の方向
        if (!Zend_Validate::is($form_data['screenOrientation'], 'NotEmpty')) {
            $this->_helper->flashMessenger->addMessage(array('screenOrientation' => $this->msgConfig->E200_Require_ScreenOrientation));
            $result = false;
        } else {
            if (!array_key_exists($form_data['screenOrientation'], $this->getScreenOrientation())) {
                $this->_helper->flashMessenger->addMessage(array('screenOrientation' => $this->msgConfig->E200_Invalid_ScreenOrientation));
                $result = false;
            }
        }
		
		// 店員呼出ボタンの表示
        if (!Zend_Validate::is($form_data['useCallStaff'], 'NotEmpty')) {
            $this->_helper->flashMessenger->addMessage(array('useCallStaff' => $this->msgConfig->E200_Require_UseCallStaff));
            $result = false;
        } else {
            if (!array_key_exists($form_data['useCallStaff'], $this->getUseCallStaff())) {
                $this->_helper->flashMessenger->addMessage(array('useCallStaff' => $this->msgConfig->E200_Invalid_UseCallStaff));
                $result = false;
            }
        }
        
        // おすすめメニューボタン表示
        if (!Zend_Validate::is($form_data['recommendationVisible'], 'NotEmpty')) {
            $this->_helper->flashMessenger->addMessage(array('recommendationVisible' => $this->msgConfig->E200_Require_RecommendationVisible));
            $result = false;
        } else {
            if (!array_key_exists($form_data['recommendationVisible'], $this->getUseCallStaff())) {
                $this->_helper->flashMessenger->addMessage(array('recommendationVisible' => $this->msgConfig->E200_Invalid_RecommendationVisible));
                $result = false;
            }
        }
	
        // データ自動更新
        if (!Zend_Validate::is($form_data['automaticUpdate'], 'NotEmpty')) {
            $this->_helper->flashMessenger->addMessage(array('automaticUpdate' => $this->msgConfig->E200_Require_AutomaticUpdate));
            $result = false;
        } else {
            if (!array_key_exists($form_data['automaticUpdate'], $this->getUseCallStaff())) {
                $this->_helper->flashMessenger->addMessage(array('automaticUpdate' => $this->msgConfig->E200_Invalid_AutomaticUpdate));
                $result = false;
            }
        }
        
		// トップページに席番号の表示
        if (!Zend_Validate::is($form_data['dispTableName'], 'NotEmpty')) {
            $this->_helper->flashMessenger->addMessage(array('dispTableName' => $this->msgConfig->E200_Require_DispTableName));
            $result = false;
        } else {
            if (!array_key_exists($form_data['dispTableName'], $this->getDispTableName())) {
                $this->_helper->flashMessenger->addMessage(array('dispTableName' => $this->msgConfig->E200_Invalid_DispTableName));
                $result = false;
            }
        }
        
        // 管理画面パスワード
        if (!Zend_Validate::is($form_data['pass'], 'NotEmpty')) {
            $this->_helper->flashMessenger->addMessage(array('pass' => $this->msgConfig->E200_Require_Pass));
            $result = false;
        } else {
            if (!Zend_Validate::is($form_data['pass'], 'Alnum')) {
                $this->_helper->flashMessenger->addMessage(array('pass' => $this->msgConfig->E200_Invalid_Pass));
                $result = false;
            }
            if (strlen($form_data['pass']) > 32) {
                $this->_helper->flashMessenger->addMessage(array('pass' => $this->msgConfig->E200_Lengh_Pass));
                $result = false;
            }
            
        }
        
        // 注文ボタンの表示
        if (Zend_Validate::is($form_data['orderStationFlag'], 'NotEmpty')
            && (!array_key_exists($form_data['orderStationFlag'], $this->getFlag()))
        ) {
             $this->_helper->flashMessenger->addMessage(array('orderStationFlag' => $this->msgConfig->E200_Invalid_OrderStationFlag));
            $result = false;
        }
        
        // 割り勘表示
        if (Zend_Validate::is($form_data['useWarikan'], 'NotEmpty')
            && (!array_key_exists($form_data['useWarikan'], $this->getUseWarikanFlag()))
        ) {
             $this->_helper->flashMessenger->addMessage(array('useWarikan' => $this->msgConfig->E200_Invalid_UseWarikanFlag));
            $result = false;
        }
        
        // 注文リスト警告時間（分）
        if (Zend_Validate::is($form_data['cartcleartime'], 'NotEmpty')
            && !Zend_Validate::is($form_data['cartcleartime'], 'Digits')
        ) {
            $this->_helper->flashMessenger->addMessage(array('cartcleartime' => $this->msgConfig->E200_Number_CartClearTime));
            $result = false;
        }
        
        // 品切れ更新時間（秒）
        if (Zend_Validate::is($form_data['ipadStatusUpdateInterval'], 'NotEmpty')
            && (!Zend_Validate::is($form_data['ipadStatusUpdateInterval'], 'Digits') || $form_data['ipadStatusUpdateInterval'] <= 0)
        ) {
            $this->_helper->flashMessenger->addMessage(array('ipadStatusUpdateInterval' => $this->msgConfig->E200_Number_IpadStatusUpdateInterval));
            $result = false;
        }
		
		// プリンター機種選択
        if (!Zend_Validate::is($form_data['printerType'], 'NotEmpty')) {
            $this->_helper->flashMessenger->addMessage(array('printerType' => $this->msgConfig->E200_Require_PrinterType));
            $result = false;
        } else {
            if (!array_key_exists($form_data['printerType'], $this->getDispTableName())) {
                $this->_helper->flashMessenger->addMessage(array('printerType' => $this->msgConfig->E200_Invalid_PrinterType));
                $result = false;
            }
        }
        
        // 会計伝票プリンターアドレス
        if (Zend_Validate::is($form_data['printerIPAddress'], 'NotEmpty')
             && (!$check->checkSpecCharForIP($form_data['printerIPAddress']))
        ) {
            $this->_helper->flashMessenger->addMessage(array('printerIPAddress' => $this->msgConfig->E200_Invalid_PrinterIPAddress));
            $result = false;
        }
        
        // 会計伝票プリンターポート
        if (Zend_Validate::is($form_data['printerPortNo'], 'NotEmpty')
            && !Zend_Validate::is($form_data['printerPortNo'], 'Digits')
        ) {
            $this->_helper->flashMessenger->addMessage(array('printerPortNo' => $this->msgConfig->E200_Number_PrinterPortNo));
            $result = false;
        }
        
        // 注文時に印刷を行う
        if (Zend_Validate::is($form_data['doPrintFlag'], 'NotEmpty')
            && (!array_key_exists($form_data['doPrintFlag'], $this->getDoPrintFlag()))
        ) {
            $this->_helper->flashMessenger->addMessage(array('doPrintFlag' => $this->msgConfig->E200_Invalid_DoPrintFlag));
            $result = false;
        }
		
		// 会計伝票の印刷枚数
        if (Zend_Validate::is($form_data['doPrintCount'], 'NotEmpty')
            && !Zend_Validate::is($form_data['doPrintCount'], 'Digits')) {
            $this->_helper->flashMessenger->addMessage(array('doPrintCount' => $this->msgConfig->E200_Number_DoPrintCount));
            $result = false;
        }
		
		// 注文伝票の印刷を行う
        if (Zend_Validate::is($form_data['doOrderPrintFlag'], 'NotEmpty')
            && (!array_key_exists($form_data['doOrderPrintFlag'], $this->getDoOrderPrintFlag()))
        ) {
            $this->_helper->flashMessenger->addMessage(array('doOrderPrintFlag' => $this->msgConfig->E200_Invalid_DoOrderPrintFlag));
            $result = false;
        }
        
        // 印刷時に音声を鳴らす
        if (Zend_Validate::is($form_data['doPrintSoundFlag'], 'NotEmpty')
            && (!array_key_exists($form_data['doPrintSoundFlag'], $this->getDoPrintSoundFlag()))
        ) {
            $this->_helper->flashMessenger->addMessage(array('doPrintSoundFlag' => $this->msgConfig->E200_Invalid_DoPrintSoundFlag));
            $result = false;
        }

        // ステーションのアドレス
        if (Zend_Validate::is($form_data['stationAddress'], 'NotEmpty')
            && (!$check->checkSpecCharForIP($form_data['stationAddress']))
        ) {
            $this->_helper->flashMessenger->addMessage(array('stationAddress' => $this->msgConfig->E200_Invalid_StationAddress));
            $result = false;
        }

        // TCP/IP受信用ポート番号
        if (Zend_Validate::is($form_data['receivePort'], 'NotEmpty')
            && (!Zend_Validate::is($form_data['receivePort'], 'Digits'))
        ) {
            $this->_helper->flashMessenger->addMessage(array('receivePort' => $this->msgConfig->E200_Number_ReceivePort));
            $result = false;
        }

        // TCP/IP送信用ポート番号
        if (Zend_Validate::is($form_data['sendPort'], 'NotEmpty')
            && (!Zend_Validate::is($form_data['sendPort'], 'Digits'))
        ) {
            $this->_helper->flashMessenger->addMessage(array('sendPort' => $this->msgConfig->E200_Number_SendPort));
            $result = false;
        }

        // ソケット通信リトライ回数
        if (Zend_Validate::is($form_data['socket_retry'], 'NotEmpty')
            && (!Zend_Validate::is($form_data['socket_retry'], 'Digits'))
        ) {
            $this->_helper->flashMessenger->addMessage(array('socket_retry' => $this->msgConfig->E200_Number_SocketRetry));
            $result = false;
        }
        
        // ソケット通信待ち時間
        if (Zend_Validate::is($form_data['socket_waitSec'], 'NotEmpty')
            && (!Zend_Validate::is($form_data['socket_waitSec'], 'Digits'))
        ) {
            $this->_helper->flashMessenger->addMessage(array('socket_waitSec' => $this->msgConfig->E200_Number_SocketWaitSec));
            $result = false;
        }
                
        // スタッフ呼出用メニューコード
        if (Zend_Validate::is($form_data['staffCallCode'], 'NotEmpty')
            && (!Zend_Validate::is($form_data['staffCallCode'], 'Digits'))
        ) {
            $this->_helper->flashMessenger->addMessage(array('staffCallCode' => $this->msgConfig->E200_Number_StaffCallCode));
            $result = false;
        }
        
        // URL
        if (Zend_Validate::is($form_data['smaregi_url'], 'NotEmpty') 
            && (!Zend_Uri::check($form_data['smaregi_url']))
        ) {
            $this->_helper->flashMessenger->addMessage(array('smaregi_url'=>$this->msgConfig->E200_Invalid_Url));
            $result = false;
        }
        
        // パスワード
        if (Zend_Validate::is($form_data['smaregi_password'], 'NotEmpty')) {
            if (!Zend_Validate::is($form_data['smaregi_password'], 'Alnum')) {
                $this->_helper->flashMessenger->addMessage(array('smaregi_password'=>$this->msgConfig->E200_Invalid_Pass));
                $result = false;
            }
            if (strlen($form_data['smaregi_password']) > 32) {
                $this->_helper->flashMessenger->addMessage(array('smaregi_password'=>$this->msgConfig->E200_Lengh_Pass) );
                $result = false;
            }
        }
        
        return $result;
    }

    /**update the value in the setting.json file
     *
     * @access private
     * @param  array data on the form
     * @return boole
     * @author Nguyen Thi Tho
     * @since  2012/07/06
     */
    private function _update($path_file,$json_data)
    {
        $fp = fopen($this->file, 'w');
        try{
            fwrite($fp, json_encode($json_data));
            $this->_helper->flashMessenger->addMessage($this->msgConfig->I000_EditSuccessful);
            Globals::log(json_encode($json_data), null, $this->company_code.'.log');
        }
        catch (Exception $e) {
             Globals::log('update unsuccessful.', null, $this->company_code.'.log');
             $this->_helper->flashMessenger->addMessage($this->msgConfig->E200_CanNotUpdate_AppConf);
        }
        fclose($fp);
    }
    
}
?>
