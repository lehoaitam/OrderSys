<?php

/**
 * Class Topping
 *
 * @author Nguyen Dinh Bao
 * @copyright Kobe Digital Labo, Inc
 * @since 2014/04/23
 */
require_once('Kdl/Ipadso/Csv/RecordSet.php');
require_once('Index.php');

class Application_Model_Topping extends Application_Model_Entity {

    const MAIN_FILE = 'toppinggroup.csv';
    const API_LIST_METHOD = 'getItemToppingGroupList';

    protected $_id = 'itemToppingGroupId';
    protected $_charset = 'UTF-8';
    protected $_data = null;
    protected $_columns = array(
        'itemToppingGroupId',
        'itemToppingGroupName',
        'min',
        'max'
    );
    protected static $_instance = null;

    protected function _getCsvRecordSet() {
        $file = Globals::getDataFilePath(self::MAIN_FILE);
        $csv = new Kdl_Ipadso_Csv_RecordSet(
                        $file,
                        $this->_id,
                        $this->_charset,
                        true
        );
        return $csv;
    }

    public function fetchAll() {
        $resultSet = $this->_getCsvRecordSet()->getData();
        return $this->_setObjectData($resultSet);
    }

    public function find($arg) {
        $resultSet = $this->_getCsvRecordSet()->findRowSet($arg);
        return $this->_setObjectData($resultSet);
    }

    public function findRowByKey($key) {
        return $this->_getCsvRecordSet()->findRow($key);
    }

    public function deleteByKey($key) {
        $this->_getCsvRecordSet()->deleteByKey($key);
        $this->_setObjectData($this->getData());
    }

    public function deleteRow($row) {
        $this->_getCsvRecordSet()->delete($row);
        $this->_setObjectData($this->getData());
    }

    public function update($rowData) {
        $this->_getCsvRecordSet()->update($rowData);
        $this->_setObjectData($this->getData());
    }

    public function insert($rowData) {
        $this->_getCsvRecordSet()->insert($rowData);
        $this->_setObjectData($this->getData());
    }

    protected function _setObjectData($data) {
        $entries = array();
        $this->_data = array();
        if (!empty($data) && (is_array($data))) {
            foreach ($data as $row) {
                $this->_data[$row[$this->_id]] = $row;
                $entry = new Application_Model_Index();
                $entry->setData($row);
                $entries[$row[$this->_id]] = $entry;
            }
        }
        return $entries;
    }

    public function getData() {
        if (is_null($this->_data)) {
            $this->fetchAll();
        }
        return $this->_data;
    }

    public function getHeader() {
        return $this->_getCsvRecordSet()->getHeader();
    }

    //get count menu
    public function getCountMenu() {
        $header = $this->_getCsvRecordSet()->getHeader();
        $countMenu = 0;
        foreach ($header as $key => $value) {
            if (preg_match('~^(menu)(\d+)$~', $value, $matches)) {
                $countMenu++;
            }
        }
        return $countMenu;
    }

    //get the next no in this file
    public function getMaxItemToppingGroupId() {
        if ($this->fetchAll()) {
            asort($this->_data);
            $lastItem = array_pop($this->_data);

            return $lastItem['itemToppingGroupId'] + 1;
        }

        return false;
    }

    //get data view on the list
    public function getDataViewList($postData, $page, $limit) {
        $csvIndex = new Application_Model_Index();
        $dataIndex = $csvIndex->fetchAll(null, true);
        $arrIndex = array();
        foreach ($dataIndex as $value) {
            $arrIndex[$value->getMenuCode()] = $value->getItemName();
        }
        $data = $this->getData();

        // Sort data
        Application_Model_Entity::natksort($data);
        $keySort = array('itemToppingGroupId', 'itemToppingGroupName');
        foreach ($keySort as $key) {
            if (array_keys($postData, $key)) {
				if ($postData['order'] == 'asc') {
					usort($data, function($a, $b) use ($key) {
						return strnatcmp($a[$key], $b[$key]);
					});
				} else {
					usort($data, function($a, $b) use ($key) {
						return strnatcmp($b[$key], $a[$key]);
					});
				}
				break;
			}
        }

        $count = count($data);
        if ($count > 0 && $limit != '') {
            $total_pages = ceil($count / $limit);
        } else {
            $total_pages = 0;
        }
        if ($page > $total_pages) {
            $page = $total_pages;
        }
        $start = $limit * $page - $limit;

        $rows = array();
        $csvData = array();

        if ($count) {
            $rows['total'] = $count;
            foreach ($data as $id => $row) {
                foreach ($row as $key => $value) {
                    if (preg_match('~^(menu)(\d+)$~', $key, $matches)) {
                        if (isset($arrIndex[$value])) {
                            $row[$key] = $value . '<br>' . $arrIndex[$value];
                        }
                    }
                }
                $csvData[] = $row;
            }

            $rows['rows'] = (array_slice($csvData, $start, $limit));
        }

        return $rows;
    }

    //check forgein key from index.csv file
    public function checkForgeinKey($value) {
        $csvIndex = new Application_Model_Index();
        $csvIndex->fetchAll();
        $arrIndex = $csvIndex->getData();

        $execute = 1;
        foreach ($arrIndex as $v) {
			if ($v['itemToppingGroupId'] == $value) {
				$execute = 'E300_ProductForeign';
				break;
			}
        }
        return $execute;
    }

    /**
     * @function: get data from subcomment to fill SCP
     * @return: data
     */
    public function getDataSubCommentFillCombobox() {
        $dataCombobox = array();
        $data = $this->fetchAll();
        $dataCombox[''] = '';
        foreach ($data as $key => $item) {
            // Modifield by pvduy 2013/08/28
            // display subcomment as format: id + ':' + name
            $dataCombox[$key] = sprintf('%s:%s', $item->getNo(), $item->getGuidance());
            // End 2013/08/28
        }
        return $dataCombox;
    }

    /**
     * Check product exist
     * 
     * @param array $data
     * @return array 
     * @author Nguyen Huu Tam
     * @since 2012/08/22
     */
    static public function checkSubcommentData(&$data) {
        $result = array();

        if (empty($data) || !is_array($result)) {
            return $result;
        }

        $indexModel = new Application_Model_Index();
        $products = $indexModel->getData();
        $proSubs = $indexModel->getProduct4Subcomment();

        $csvSubcomment = self::getInstance();
        $menuNumbers = $csvSubcomment->getCountMenu();
        for ($i = 1; $i <= $menuNumbers; $i++) {
            $index = "menu{$i}";
            if ($data[$index] == 0)
                continue;

            // Check exist product
            if (!array_key_exists($data[$index], $products)) {
                $result['exist'][$i] = $data[$index];
                $data[$index] = '';
            } else {
                // Check subcomment product
                if (!array_key_exists($data[$index], $proSubs)) {
                    $result['sub'][$i] = $data[$index];
                    $data[$index] = '';
                }
            }
        }

        return $result;
    }        

    /**
     * Returns an instance of BinaryReader
     *
     * Singleton pattern implementation
     *
     * @return BinaryReader Provides a fluent interface
     * @author Nguyen Huu Tam
     * @since 2012/09/08
     */
    public static function getInstance() {
        if (null === self::$_instance) {
            self::$_instance = new self();
        }

        return self::$_instance;
    }

    /**
     * Prepare data
     *  Add multi-primarykey to array index
     * 
     * @param array $data
     * @return array 
     * @author Nguyen Huu Tam
     * @since 2012/09/07
     */
    public function prepareData($data) {
        return $this->_getCsvRecordSet()->prepareData($data);
    }

    /**
     * Renew data
     * 
     * @param array $data
     * @return array 
     * @author Nguyen Huu Tam
     * @since 2012/09/07
     */
    public function renewData($file, $data) {
        if (file_exists($file)) {
            unlink($file);
        }
        $this->_getCsvRecordSet()->renewData($file, $data);
    }

    /**
     * Skip no update columns data
     * 
     * @param array $newData
     * @return array
     * @author Nguyen Huu Tam
     * @since 2012/09/07
     */
    public function getUpdateApiData($newData) {
        $currData = $this->getData();
        $updateData = array_intersect_key($newData, $currData);

        return $updateData;
    }

    public function createOptionToppiong($data, $option, $val_select, $header = '') {
        $html = '';
        $val = $option['value'];
        $title = $option['title'];

        $flag_ok = false;
		if (is_array($data)) {
			foreach ($data as $key => $value) {
				if ($val_select == $value[$val]) {
					$html .= '<option value="' . $value[$val] . '" selected="selected" >' . $value[$title] . '</option>';
					$flag_ok = true;
				} else {
					$html .= '<option value="' . $value[$val] . '" >' . $value[$title] . '</option>';
				}
			}
		}
        if (!$flag_ok) {
            $html = '<option value="" selected="selected" >' . $header . '</option>' . $html;
        } else {
            $html = '<option value="">' . $header . '</option>' . $html;
        }
        return $html;
    }    
    
    public function startsWith($haystack, $needle){
        return $needle === "" || strpos($haystack, $needle) === 0;
    }

}