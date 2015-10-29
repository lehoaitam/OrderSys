<?php
/**
 * Class Index
 *
 * @author Nguyen Huu Tam
 * @copyright Kobe Digital Labo, Inc
 * @since 2012/07/13
 */
require_once('Kdl/Ipadso/Csv/RecordSet.php');
require_once('Kdl/Ipadso/Csv/RecordSetEx.php');

class Application_Model_Category extends Application_Model_Entity
{
    const MAIN_FILE     = 'category.csv';
    const CATE_TYPE_1   = 1;
    const CATE_TYPE_2   = 2;
    
    const API_LIST_METHOD = 'getCategoryList';
    
    protected $_id      = 'kind-code';
    protected $_charset = 'UTF-8';
    protected $_data    = null;
	
	protected $_columns = array(
        'kind',
        'code',
        'name',
        'image'
    );

    protected function _getCsvRecordSet()
    {
        $file = Globals::getDataFilePath(self::MAIN_FILE);
        $csv = new Kdl_Ipadso_Csv_RecordSetEx(
            $file,
            $this->_id,
            $this->_charset,
            true
        );
        return $csv;
    }
    protected function _getCsvRecordSetNew()
    {
        $file = Globals::getDataFilePath(self::MAIN_FILE);
        $csv = new Kdl_Ipadso_Csv_RecordSetEx(
            $file,
            'code',
            $this->_charset,
            true
        );
        return $csv;
    }

    public function fetchAll()
    {
        $resultSet = $this->_getCsvRecordSet()->getData();
        return $this->_setObjectData($resultSet);
    }

    public function find($arg)
    {
        $resultSet = $this->_getCsvRecordSet()->findRowSet($arg);
        return $this->_setObjectData($resultSet);
    }

    protected function _setObjectData($data)
    {
        $entries     = array();
        $this->_data = array();
        if (!empty($data) && (is_array($data))) {
            foreach ($data as $key => $row) {
                $this->_data[$key] = $row;
                $entry = new Application_Model_Category();
                $entry->setData($row);
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
    
    public function getDataHeader($header = null)
    {
        $arr = array();
        $data = $this->getData();
        foreach ($data as $key => $value) {
             $arr[] = $value[$header];
        }
        return $arr;
    }

    public function findRowByKey($key)
    {
        return $this->_getCsvRecordSet()->findRow($key);
    }

    public function findNameByValue($name, $value)
    {
        return $this->_getCsvRecordSet()->findRowSetByColumn($name, $value);
    }
    public function deleteByKey($key)
    {
        $this->_getCsvRecordSet()->deleteByKey($key);
        $this->_setObjectData($this->getData());
    }
    
    public function deleteRow($row)
    {
        $this->_getCsvRecordSet()->delete($row);
        $this->_setObjectData($this->getData());
    }
	
	public function deleteAllRow()
    {
        $this->_getCsvRecordSet()->clearAllRow();
        $this->_setObjectData($this->getData());
    }
	
    public function update($rowData)
    {
        $this->_getCsvRecordSet()->update($rowData);
        $this->_setObjectData($this->getData());
    }
    public function updatenew($rowData)
    {
        $this->_getCsvRecordSetNew()->update($rowData);
        $this->_setObjectData($this->getData());
    }

    public function insert($rowData)
    {
        $this->_getCsvRecordSet()->insert($rowData);
        $this->_setObjectData($this->getData());
    }

    public function getHeader()
    {
        return $this->_getCsvRecordSet()->getHeader();
    }

    // get data view on the list
    public function getDataViewList($postData, $page, $limit = NUll)
    {
        $data = $this->getData();
        Application_Model_Entity::natksort($data);
        $keySort = array('code', 'name');
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

        $rows = array();
        $csvData = array();
        
        $count = count($data);
        if ($count) {
            $rows['total']= $count;
            foreach ($data as $id => $row) {
                $row['id'] = $row['kind'].'-'.$row['code'];
                $csvData[] = $row;
            }
        }
        
        if (($page == null) && ($limit == null )) {
            $rows['rows'] = $csvData;
        }else{
                if ($count > 0) {
                    if($limit > 0){
                        $total_pages = ceil($count/$limit);
                    }else{
                        $total_pages = 1;
                    }
                } else {
                    $total_pages = 0;
                }
                if ($page > $total_pages) {
                    $page = $total_pages;
                }
                $start = $limit*$page - $limit;
                
                $rows['rows'] = (array_slice($csvData, $start, $limit));
        }
        
        return $rows;
    }

	//check forgein key from index.csv file
    public function checkForgeinKey($value)
    {        
        $exKey = explode('-', $value);
        $kind    = $exKey[0];
        $code  = $exKey[1];

        $execute = 1;

        $csvIndex = new Application_Model_Index();
        $csvIndex->fetchAll();
        $arrIndex = $csvIndex->getDataHeader('category'.$kind.'_code');
        if(array_keys($arrIndex,$code)) {
            $execute = 'E300_ProductForeign';
        }
        return $execute;
    }

    /**
     * @function: get data from category to fill category1 field
     * @return: data
     */
    public function getDataFillCombobox()
    {
        $dataCombobox = Array();
        $data = $this->fetchAll();
        $i = 0;
        foreach ($data as $value) {
            $dataCombox[$i]['code'] = $value->getCode().','.$value->getName();
            $dataCombox[$i]['name'] = $value->getName();
            $i++;
         }
        return $dataCombox;
    }
    
    /**
     *
     * @param int $type
     * @return array
     * @author Nguyen Huu Tam
     * @since 2012/08/13 
     */
    public function getCategoryDataByType($type)
    {
       return $this->_getDataKindOfCategory($type);
    }

        /**
     * @function: get json category belong to kind 1
     * @return: data
     */
    public function getDataJson1()
    {
        $image      = new Application_Model_Image();
        $dataImage  = $image->getArrayImage();

        $data  = $this->getCategoryDataByType(self::CATE_TYPE_1);
        $count = count($data);

        $rows['total']= $count;
        $csvData = array();

        if ($count) {
            foreach ($data as $row) {
                if (!array_keys($dataImage, $row['image'])) {
                    $row['image'] = '';
                }
                // Thumbnail
                $row['thumb'] = Application_Model_Image::getThumbnail($row['image']);
                $csvData[] = $row;
            }
        }
        $rows['rows'] = $csvData;

       return $rows;
    }

    /**
     * @function: get json category belong to kind 1
     * @return: data
     */
    public function getDataJson2()
    {
        $image      = new Application_Model_Image();
        $dataImage  = $image->getArrayImage();

        $data  = $this->getCategoryDataByType(self::CATE_TYPE_2);
        $count = count($data);

        $rows['total']= $count;
        $csvData = array();

        if ($count) {
            foreach ($data as $row) {
                if (!array_keys($dataImage, $row['image'])) {
                    $row['image'] = '';
                }
                // Thumbnail
                $row['thumb'] = Application_Model_Image::getThumbnail($row['image']);
                $csvData[] = $row;
            }
        }
        $rows['rows'] = $csvData;

       return $rows;
    }

    /**
     * @function: get data category belong to each kind of Category
     * @return: data
     */
    private function _getDataKindOfCategory($element)
    {
        $dataOption  = array();
        
        $arrKind = $this->getCategoryKind();
        $data = $this->getData();
        foreach ($data as $k => $v) {
            foreach ($arrKind as $i) {
                if ($v['kind'] == $i) {
                    $dataOption[$i][$k] = $v;
                }
            }
        }
        return isset($dataOption[$element])?$dataOption[$element]:NULL;
    }
    
    /**
     * Get category kind
     * 
     * @return array type 
     * @author Nguyen Huu Tam
     * @since 2012/08/13
     */
    public function getCategoryKind()
    {
        $kind = array();
        foreach ($this->getData() as $cate) {
            $kind[$cate['kind']] = $cate['kind'];
        }
        
        return $kind;
    }

    /**
     * Get category data by kind
     * 
     * @param int $kind
     * @return array
     * @author Nguyen Huu Tam
     * @since 2012/08/13
     */
    public function getCategoryByKind($kind)
    {
        $category = array();
        foreach ($this->getData() as $cate) {
            if ($cate['kind'] == $kind) {
                $category[$cate['code']] = $cate;
            }
        }
        
        return $category;
    }

    /*
     * Prepare data
     *  Add multi-primarykey to array index
     * 
     * @param array $data
     * @return array 
     * @author Nguyen Huu Tam
     * @since 2012/09/06
     */
    public function prepareData($data)
    {
        return $this->_getCsvRecordSet()->prepareData($data);
    }
    
    /*
     * Renew data
     * 
     * @param array $data
     * @return array 
     * @author Nguyen Huu Tam
     * @since 2012/09/06
     */
    public function renewData($file, $data)
    {
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
    public function getUpdateApiData($newData)
    {
        $currData = $this->getData();
        $updateData = array_intersect_key($newData, $currData);
        foreach ($updateData as $id => &$row) {
            $row['image'] = $currData[$id]['image'];
        }
        
        return $updateData;
    }
    
    public function createOptionCategory($data,$option,$val_select,$header = '' ) {
        $html = '';
        $val = $option['value'];
        $title = $option['title'];
        
        $flag_ok = false;
		if (is_array($data)) {
			foreach ($data as $key => $value) {
				if($val_select == $value[$val]){
                    // 2015/02/19 Nishiyama Add エスケープ追加
					$html .= '<option value="'.$value[$val].'" selected="selected" >'.htmlspecialchars($value[$title]).'</option>';
					$flag_ok = true;
				}else{
                    // 2015/02/19 Nishiyama Add エスケープ追加
					$html .= '<option value="'.$value[$val].'" >'.htmlspecialchars($value[$title]).'</option>';
				}
			}
		}
        if(!$flag_ok){
            $html = '<option value="" selected="selected" >'.$header.'</option>'.$html;
        }else{
            $html = '<option value="">'.$header.'</option>'.$html;
        }
        return $html;
    }
    
    
    // get data view on the list
    public function getNextCategoryId($kind=NULL)
    {
        $next_code = 1;
        $data = $this->getData();
        $keySort = array('code');
        foreach ($keySort as $key) {
                usort($data, function($a, $b) use ($key) {
                        return strnatcmp($b[$key], $a[$key]);
                });
        }
        if(isset($data[0]['code'])){
            $next_code =  $data[0]['code'] + 1;
        }
        return $next_code;
    }

	/**
     *
     * @param array $data
     * @return array
     * @author pqbao
     * @since 2014/05/16
     */
	public function _fillDataFromSession($data, $session) {
		$newData = array();

		foreach ($this->_columns as $column) {
			if (!isset($data[$column])) {
				$newData[$column] = isset($session[$column]) ? $session[$column] : '';
			} else {
				$newData[$column] = $data[$column];
			}
		}

		return $newData;
	}
}
