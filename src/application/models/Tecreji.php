<?php

/**
 * Class Tecreji
 *
 * @author pqbao
 * @copyright Kobe Digital Labo, Inc
 * @since 2015/03/09
 */
require_once('Kdl/Ipadso/Csv/RecordSet.php');
require_once('Kdl/Ipadso/Csv/RecordSetEx.php');
require_once('Kdl/Ipadso/Csv/Reader.php');
require_once('Kdl/Ipadso/Bin/BinaryReader.php');

class Application_Model_Tecreji {

    protected $_charset = 'SJIS';
    protected $col_repair_pro = array('no' => '行番号',
        'menuCode' => 'ﾒﾆｭｰｺｰﾄﾞ',
        'image' => '',
        'itemName' => '名称2(漢1)',
        'price' => '単価',
        'subprice' => 'ｻﾌﾞ単価',
        'category1_code' => '', 'category1_name' => '',
        'category2_code' => 'ﾘﾝｸDPｺｰﾄﾞ', 'category2_name' => '',
        'itemToppingGroupId' => '',
        'suggest1' => '', 'suggest2' => '', 'suggest3' => '',
        'adLink' => '',
        'desc' => '',
        'other1' => '', 'other2' => '',
        'isComment' => 'ｺﾒﾝﾄ宣言',
        'isSub' => 'ｻﾌﾞ宣言',
        'isSet' => 'ｾｯﾄ宣言',
        'SCP1' => 'SCP(1)', 'SCP2' => 'SCP(2)', 'SCP3' => 'SCP(3)', 'SCP4' => 'SCP(4)', 'SCP5' => 'SCP(5)', 'SCP6' => 'SCP(6)',
        'SCP7' => 'SCP(7)', 'SCP8' => 'SCP(8)', 'SCP9' => 'SCP(9)', 'SCP10' => 'SCP(10)', 'SCP11' => 'SCP(11)', 'SCP12' => 'SCP(12)',
        'startTime' => '', 'endTime' => '',
        'PrinterIP' => '', 'PrinterPort' => ''
    );
    protected $col_repair_cate1 = array('kind' => 'kind',
        'code' => 'gp_code',
        'name' => 'gp',
        'image' => ''
    );
    protected $col_repair_cate2 = array('kind' => 'kind',
        'code' => 'dp_code',
        'name' => 'kanji',
        'image' => ''
    );

    public function __construct() {
        $this->_path = Globals::getTmpUploadFolder();
        $this->_data_index = null;
        $this->_data_cate1 = null;
        $this->_data_cate2 = null;
        $this->_data_subcm = null;

        $this->_fileConfig = Globals::getApplicationConfig('upload');
        $this->_allowTypesCsv = explode(',', $this->_fileConfig->allowTypesCsv);
        $this->msgConfig = Zend_Registry::get('MsgConfig');

        //Check upload folder and create
        if (!is_dir($this->_path)) {
            try {
                mkdir($this->_path, 0777);
                chown($this->_path, 775);
            } catch (Exception $e) {
                Globals::log($e);
            }
        }
    }

    /**
     * @action: checkFileUpload
     * @return bool
     * @author pqbao
     * @since 2015/03/10
     * @param: $file_name array (files name)
     */
    public function checkFileUpload($Arrfile) {
        $Error = array();

        try {
            //Check GP.dat: Category 1
            if (isset($Arrfile['gp_dat']['name']) && $Arrfile['gp_dat']['name'] != '') {
                //Check and get data;
                $rs = $this->checkFileGPDAT($Arrfile['gp_dat']['name']);
                if ($rs != '') {
                    $Error['gp_dat'] = $rs;
                }
            } else {
                $Error['gp_dat'] = $this->msgConfig->E300_RequireFileUpload;
            }
        } catch (Exception $e) {
            $Error['gp_dat'] = $e->getMessage();
        }
        try {
            //Check FUDP: Category 2
            if (isset($Arrfile['fudp_dat']['name']) && $Arrfile['fudp_dat']['name'] != '') {
                //Check and get data;
                $rs = $this->checkFileFUDP($Arrfile['fudp_dat']['name']);
                if ($rs != '') {
                    $Error['fudp_dat'] = $rs;
                }
            } else {
                $Error['fudp_dat'] = $this->msgConfig->E300_RequireFileUpload;
            }
        } catch (Exception $e) {
            $Error['fudp_dat'] = $e->getMessage();
        }
        try {
            //Check FSCP: Subcomment
            if (isset($Arrfile['fscp_dat']['name']) && $Arrfile['fscp_dat']['name'] != '') {
                //Check and get data;
                $rs = $this->checkFileFSCP($Arrfile['fscp_dat']['name']);
                if ($rs != '') {
                    $Error['fscp_dat'] = $rs;
                }
            } else {
                $Error['fscp_dat'] = $this->msgConfig->E300_RequireFileUpload;
            }
        } catch (Exception $e) {
            $Error['fscp_dat'] = $e->getMessage();
        }

        try {
            //Check Fumenu.csv: Index
            if (isset($Arrfile['fumenu_csv']['name']) && $Arrfile['fumenu_csv']['name'] != '') {
                //Check and get data;
                $rs = $this->checkFileFumenu($Arrfile['fumenu_csv']['name']);
                if ($rs != '') {
                    $Error['fumenu_csv'] = $rs;
                }
            } else {
                $Error['fumenu_csv'] = $this->msgConfig->E300_RequireFileUpload;
            }
        } catch (Exception $e) {
            $Error['fumenu_csv'] = $e->getMessage();
        }
        
        return $Error;
    }

    /**
     * Check file fumenu
     * @param type $filename
     * @return array
     */
    public function processsaveData() {
        $message = array();

//        $this->updateProduct();
        $message[] = sprintf('商品データを%s件取り込みました。', $this->updateProduct());

//        $this->updateCategory();
        $message[] = sprintf('カテゴリーデータを%s件取り込みました。', $this->updateCategory());

//        $this->updateSubcomment();
        $message[] = sprintf('カスタムオーダーデータを%s件取り込みました。', $this->updateSubcomment());

        return $message;
    }

    /**
     * Check file fumenu
     * @param type $filename
     * @return array
     */
    public function checkFileFumenu($filename) {

        $error = '';
        $upload = new Zend_File_Transfer_Adapter_Http();
        if ($upload->isUploaded($filename)) {

            // Add rules to validate file
            $upload->addValidator('Size', false, $this->_fileConfig->maxSize)
                    ->addValidator('Extension', false, $this->_allowTypesCsv);

            $upload->setDestination($this->_path);
            $upload->receive($filename);
            $messages = $upload->getMessages();

            if (count($messages) > 0) {
                //Not validate file
                $error = $this->msgConfig->E300_FileUploadInvalid;
            }

            $file_upload = $this->_path . $filename;
            //---check charset;
            if (file_exists($file_upload)) {
                $fileup = file_get_contents($file_upload);
                if (!( mb_check_encoding($fileup, $this->_charset) )) {
                    //Error chatset
                    $error = $this->msgConfig->E300_RequireImportCharset;
                }
            }
            if ($error == '') {

                //Read data
                $db = new Kdl_Ipadso_Csv_RecordSet($file_upload, 'ﾒﾆｭｰｺｰﾄﾞ', $this->_charset);
                $this->_data_index = $db->getData();
                $data_up = array();
                foreach ($this->_data_index as $key => $value) {

                    foreach ($this->col_repair_pro as $k_col => $val_col) {
                        
                        //$data_up[$key][$k_col] = isset($value[$val_col]) ? trim(preg_replace('/^[\p{Z}\s]+|[\p{Z}\s]+$/u','',$value[$val_col])) : '';
                        $data_up[$key][$k_col] = isset($value[$val_col]) ? $value[$val_col] : '';
                    }

                    $data_up[$key]['price'] = (int) $data_up[$key]['price'];
                    
                    //check exit category1_code in 
                    //get info cate 2
                    $cate2code = $data_up[$key]['category2_code'];
                    $cate2name = isset($this->_data_cate2[Application_Model_Category::CATE_TYPE_2 . '-' . $cate2code]['kanji']) ? $this->_data_cate2[Application_Model_Category::CATE_TYPE_2 . '-' . $cate2code]['kanji'] : '';
                    $data_up[$key]['category2_name'] = $cate2name;
                    
                    //get info cate 1 => form cate2 by cate code 2
                    $cate1code = isset($this->_data_cate2[Application_Model_Category::CATE_TYPE_2 . '-' . $cate2code]['link_dp_code']) ? $this->_data_cate2[Application_Model_Category::CATE_TYPE_2 . '-' . $cate2code]['link_dp_code'] : '';
                    
                    //exit category1_code in $this->_data_cate1;
                    if( isset($this->_data_cate1[Application_Model_Category::CATE_TYPE_1 . '-' . $cate1code]['gp']) ){
                        $cate1name = $this->_data_cate1[Application_Model_Category::CATE_TYPE_1 . '-' . $cate1code]['gp'];
                        $data_up[$key]['category1_code'] = $cate1code;
                        $data_up[$key]['category1_name'] = $cate1name;
                    }
                    
                }
                $this->_data_index = $data_up;
                
                /* Not use
                $errorField = null;
                if ($this->_checkValidImportDataFumenu($data_up, $errorField)) {
                    $this->_data_index = $data_up;
                } else {
                    $error = sprintf($this->msgConfig->E300_FileUploadDataInvalid . ' (' . $errorField['column'] . ')', $errorField['code']);
                }                 
                 */
            }
        }
        return $error;
    }

    /**
     * Check file GP.DAT
     * @param type $filename
     * @return array
     */
    public function checkFileGPDAT($filename) {
        $error = '';
        $upload = new Zend_File_Transfer_Adapter_Http();
        if ($upload->isUploaded($filename)) {

            // Add rules to validate file
            $upload->addValidator('Size', false, $this->_fileConfig->maxSize);
            $upload->setDestination($this->_path);
            $upload->receive($filename);
            $messages = $upload->getMessages();

            if (count($messages) > 0) {
                //Not validate file
                $error = $this->msgConfig->E300_FileUploadInvalid;
            }

            $file_upload = $this->_path . $filename;
            if ($error == '') {
                $binReader = new BinaryReader();
                $this->_data_cate1 = $binReader->readBinFile($file_upload, BinaryReader::CATE_1_TYPE);

                //repair data: kind 1
                $data_tmp = array();
                foreach ($this->_data_cate1 as $key => $value) {
                    $code = $value['gp_code'];
                    $data_tmp[Application_Model_Category::CATE_TYPE_1 . '-' . $code] = $value;
                    $data_tmp[Application_Model_Category::CATE_TYPE_1 . '-' . $code]['kind'] = 1;
                }
                $this->_data_cate1 = $data_tmp;
            }
        }

        return $error;
    }

    /**
     * Check file FUDP
     * @param type $filename
     * @return array
     */
    public function checkFileFUDP($filename) {
        $error = '';
        $upload = new Zend_File_Transfer_Adapter_Http();
        if ($upload->isUploaded($filename)) {

            // Add rules to validate file
            $upload->addValidator('Size', false, $this->_fileConfig->maxSize);
            $upload->setDestination($this->_path);
            $upload->receive($filename);
            $messages = $upload->getMessages();

            if (count($messages) > 0) {
                //Not validate file
                $error = $this->msgConfig->E300_FileUploadInvalid;
            }

            $file_upload = $this->_path . $filename;
            if ($error == '') {
                $binReader = new BinaryReader();
                $this->_data_cate2 = $binReader->readBinFile($file_upload, BinaryReader::CATE_2_TYPE);
                //repair data: kind 2
                $data_tmp = array();
                foreach ($this->_data_cate2 as $key => $value) {
                    $code = $value['dp_code'];
                    $data_tmp[Application_Model_Category::CATE_TYPE_2 . '-' . $code] = $value;
                    $data_tmp[Application_Model_Category::CATE_TYPE_2 . '-' . $code]['kind'] = 2;
                }
                $this->_data_cate2 = $data_tmp;
            }
        }
        return $error;
    }

    /**
     * Check file FSCP
     * @param type $filename
     * @return array
     */
    public function checkFileFSCP($filename) {
        $error = '';
        $upload = new Zend_File_Transfer_Adapter_Http();
        if ($upload->isUploaded($filename)) {

            // Add rules to validate file
            $upload->addValidator('Size', false, $this->_fileConfig->maxSize);
            $upload->setDestination($this->_path);
            $upload->receive($filename);
            $messages = $upload->getMessages();

            if (count($messages) > 0) {
                //Not validate file
                $error = $this->msgConfig->E300_FileUploadInvalid;
            }

            $file_upload = $this->_path . $filename;
            if ($error == '') {
                $binReader = new BinaryReader();
                $this->_data_subcm = $binReader->readBinFile($file_upload, BinaryReader::SUBCOMMENT_TYPE);

                //repair data
                $data_tmp = array();
                foreach ($this->_data_subcm as $key => $value) {
                    $data_tmp[$value['no'] * 1] = $value;
                }
                $this->_data_subcm = $data_tmp;
            }
        }

        return $error;
    }

    /**
     * To do update Product from file upload
     * return void
     */
    public function updateProduct() {
        $col_skip_updata = array('no' => 'no',
            'image' => 'image',
            'suggest1' => 'suggest1', 'suggest2' => 'suggest2', 'suggest3' => 'suggest3',
            'adLink' => 'adLink',
            'desc' => 'desc',
            'other1' => 'other1',
            'startTime' => 'startTime', 'endTime' => 'endTime',
            'PrinterIP' => 'PrinterIP', 'PrinterPort' => 'PrinterPort'
        );

        if (!is_null($this->_data_index)) {
            $data_edit = array();
            $data_add = array();

            //get data product;
            $csvIndex = new Application_Model_Index();
            $dataIndex = $csvIndex->getData();
            $maxNo = $csvIndex->getMaxNo();

            //repair data upload
            foreach ($this->_data_index as $key => $value) {


                if (array_key_exists($key, $dataIndex)) {
                    //Update
                    $data_edit[$key] = $value;
                    foreach ($col_skip_updata as $column) {
                        if (isset($dataIndex[$key][$column])) {
                            // Get $dataIndex data
                            $data_edit[$key][$column] = $dataIndex[$key][$column];
                        }
                    }
                } else {
                    //Insert
                    $maxNo++;
                    $data_add[$key] = $value;
                    $data_add[$key]['no'] = $maxNo;
                }
            }
            if (count($data_edit) > 0) {
                $csvIndex->update($data_edit);
            }
            if (count($data_add) > 0) {
                $csvIndex->insert($data_add);
            }
        }

        return count($this->_data_index);
    }

    public function removeUTF8BOM($text) {
        $bom = pack('H*', 'EFBBBF');
        $text = preg_replace("/^$bom/", '', $text);
        return $text;
    }

    /**
     * To do update Category from file upload
     * return void
     */
    public function updateCategory() {
        // Define some columns with do not update data
        $col_skip_updata = array('image' => 'image');

        $data_edit = array();
        $data_add = array();
        $csvCategory = new Application_Model_Category();

        //get data old
        $dataCate = $csvCategory->getData();

        //Category 1
        if (!is_null($this->_data_cate1)) {
            $data_tmp = array();
            //repair data upload
            foreach ($this->_data_cate1 as $key => $value) {

                foreach ($this->col_repair_cate1 as $k_col => $val_col) {
                    $data_tmp[$key][$k_col] = isset($value[$val_col]) ? $value[$val_col] : '';
                }

                if (array_key_exists($key, $dataCate)) {
                    //Update
                    $data_edit[$key] = $data_tmp[$key];
                    foreach ($col_skip_updata as $column) {
                        if (isset($dataCate[$key][$column])) {
                            // Get $dataIndex data
                            $data_edit[$key][$column] = $dataCate[$key][$column];
                        }
                    }
                } else {
                    //Insert
                    $data_add[$key] = $data_tmp[$key];
                }
            }
        }

        //Category 2
        if (!is_null($this->_data_cate2)) {

            $data_tmp = array();
            //repair data upload
            foreach ($this->_data_cate2 as $key => $value) {

                foreach ($this->col_repair_cate2 as $k_col => $val_col) {                    
                    $data_tmp[$key][$k_col] = isset($value[$val_col]) ? $value[$val_col] : '';
                }

                if (array_key_exists($key, $dataCate)) {
                    //Update
                    $data_edit[$key] = $data_tmp[$key];
                    foreach ($col_skip_updata as $column) {
                        if (isset($dataCate[$key][$column])) {
                            // Get $dataIndex data
                            $data_edit[$key][$column] = $dataCate[$key][$column];
                        }
                    }
                } else {
                    //Insert
                    $data_add[$key] = $data_tmp[$key];
                }
            }
        }

        if (count($data_edit) > 0) {
            $csvCategory->update($data_edit);
        }
        if (count($data_add) > 0) {
            $csvCategory->insert($data_add);
        }

        return (count($this->_data_cate1) + count($this->_data_cate2));
    }

    /**
     * To do update Subcomment from file upload
     * return void
     */
    public function updateSubcomment() {
        // Define some columns with do not update data
        $col_skip_updata = array();

        $data_edit = array();
        $data_add = array();

        $csvSubcomment = new Application_Model_Subcomment();

        $dataSub = $csvSubcomment->getData();

        //repair data upload
        foreach ($this->_data_subcm as $key => $value) {

            if (array_key_exists($key, $dataSub)) {
                //Update
                $data_edit[$key] = $value;
                foreach ($col_skip_updata as $column) {
                    if (isset($dataSub[$key][$column])) {
                        // Get $dataIndex data
                        $data_edit[$key][$column] = $dataSub[$key][$column];
                    }
                }
            } else {
                //Insert
                $data_add[$key] = $value;
            }
        }

        if (count($data_edit) > 0) {
            $csvSubcomment->update($data_edit);
        }
        if (count($data_add) > 0) {
            $csvSubcomment->insert($data_add);
        }
        return count($this->_data_subcm);
    }

    /**
     * Check valid import data
     *
     * @access private
     * @param  array import data
     * @param  string $errorField
     * @return boolean
     * @since 2015/03/11
     */
    private function _checkValidImportDataFumenu($importData, &$errorField) {
        $check = new Application_Model_ValidateRules();

        $rtn = true;
        $errorData = null;
        $errorColumn = null;
        foreach ($importData as $data) {
            if (is_array($data)) {
                foreach ($data as $key => $val) {
                    switch ($key) {

                        case "menuCode":
                        case "ﾒﾆｭｰｺｰﾄﾞ":
                            if (!Zend_Validate::is($val, 'NotEmpty') || !Zend_Validate::is($val, 'Alnum') || mb_strlen($val, 'UTF-8') > 32) {
                                $errorData = $data;
                                $errorColumn = $this->col_repair_pro[$key];
                                $rtn = false;
                                break;
                            }
                            break;
                        case "itemName":
                        case "名称2(漢1)":
                            if (!Zend_Validate::is($val, 'NotEmpty') || (!$check->checkSpecCharForName($val) || mb_strlen($val, 'UTF-8') > 85)) {
                                $errorData = $data;
                                $errorColumn = $this->col_repair_pro[$key];
                                $rtn = false;
                                break;
                            }
                            break;
                        case "category1_code":
                            if (!Zend_Validate::is($val, 'NotEmpty') ) {
                                $errorData = $data;
                                $errorColumn = $this->col_repair_pro['category2_code'];
                                $rtn = false;
                                break;
                            }
                            break;
                        case "category1_name":                            
                        case "category2_name":
                            //No check
                            break;
                        case "price":
                        case "単価":
                            if (Zend_Validate::is($val, 'NotEmpty') && (!Zend_Validate::is($val, 'Digits') || $val > PHP_INT_MAX)) {
                                $errorData = $data;
                                $errorColumn = $this->col_repair_pro[$key];
                                $rtn = false;
                                break;
                            }
                            break;
                        default:
                            if (Zend_Validate::is($val, 'NotEmpty') && !$check->checkSpecCharForName($val)) {
                                $errorData = $data;
                                $errorColumn = $this->col_repair_pro[$key];
                                $rtn = false;
                                break;
                            }
                            break;
                    }
                }
            }
        }

        if (!$rtn) {
            if (array_key_exists('menuCode', $errorData)) {
                $errorField['code'] = "商品ID=" . $errorData['menuCode'];
            } else {
                $errorField['code'] = "商品ID=" . $errorData['ﾒﾆｭｰｺｰﾄﾞ'];
            }
            $errorField['column'] = $errorColumn;
        }

        return $rtn;
    }

}
