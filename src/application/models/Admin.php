<?php
/**
 * Class Admin
 *
 * @author Nguyen Huu Tam
 * @copyright Kobe Digital Labo, Inc
 * @since 2012/07/13
 */
require_once('Kdl/Ipadso/Csv/RecordSetEx.php');

class Application_Model_Admin extends Application_Model_Entity
{
    protected $_id      = 'companyName-companyCode-companyPass';
    protected $_charset = 'UTF-8';

    protected $dirPath  = '';
    protected $fileName = '';
    protected $_data = null;

    protected $_config;

    public function  __construct()
    {
        $this->_config = Globals::getApplicationConfig('data');
    }
    
    public function fetchAll()
    {
        $resultSet = $this->_getCsvRecordSet()->getData();
        return $this->_setObjectData($resultSet);
    }

    protected function _getCsvRecordSet()
    {
        $csv = new Kdl_Ipadso_Csv_RecordSetEx(
            $this->_config->csvfile->admin,
            $this->_id,
            $this->_charset,
            true
        );

        return $csv;
    }

    protected function _setObjectData($data)
    {
        $entries   = array();
        $this->_data = array();
        if (!empty($data) && (is_array($data))) {
            foreach ($data as $key => $row) {
                
                $entry = new Application_Model_Admin();
                $entry->setData($row);

                $link = $entry->getLink();
                $file = substr($link, strrpos($link, '/') + 1, strlen($link));
                $fileName = str_replace('.zip', '', $file);

                $dirPath = $this->_config->master_data_path
                        . '/' . $entry->getCompanyName()
                        . '/' . $entry->getCompanyCode()
                        . '/' . $fileName;

                $entry->setDirPath($dirPath);
                $entry->setFileName($fileName);
                $row['dirPath'] = $dirPath;
                $row['fileName'] = $fileName;

                $this->_data[$key] = $row;
                $entries[$key] = $entry;
            }
        }
        return $entries;
    }

    public function getData()
    {
        if (is_null($this->_data)) {
            $this->fetchAll();
        }
        return $this->_data;
    }

    public function findRowByKey($key)
    {
        $row = $this->_getCsvRecordSet()->findRow($key);
        if ($row) {
            return $this->_setObjectData($row);
        }
        return false;
    }
}