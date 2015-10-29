<?php
/**
 * Class Index
 *
 * @author Nguyen Huu Tam
 * @copyright Kobe Digital Labo, Inc
 * @since 2012/07/13
 */
require_once('Kdl/Ipadso/Csv/RecordSet.php');
require_once('SubComment.php');
require_once('Category.php');

class Application_Model_Index extends Application_Model_Entity
{
    const MAIN_FILE     = 'index.csv';
    const API_LIST_METHOD = 'getItemList';
	const SUGGEST_MAX_LENGTH = 15;

    static public $idCode = 'menuCode';

    protected $_charset = 'UTF-8';
    protected $_data = null;
    private static $_recordSet = null;

    protected $_columns = array(
        'no',
        'menuCode',
        'image',
        'itemName',
        'price',
        'subprice',
        'category1_code',
        'category1_name',
        'category2_code',
        'category2_name',
        'itemToppingGroupId',
        'suggest1',
        'suggest2',
        'suggest3',
        'adLink',
        'desc',
        'other1',
        'other2',
        'isComment',
        'isSub',
        'isSet',
        'SCP1',
        'SCP2',
        'SCP3',
        'SCP4',
        'SCP5',
        'SCP6',
        'SCP7',
        'SCP8',
        'SCP9',
        'SCP10',
        'SCP11',
        'SCP12',
        'startTime',
        'endTime',
        'PrinterIP',
        'PrinterPort'
    );


    protected function _getCsvRecordSet($filePath = null)
    {
        if (!is_null(self::$_recordSet)) {
            return self::$_recordSet;
        }
        $indexFile = is_null($filePath) ? Globals::getDataFilePath(self::MAIN_FILE) : $filePath;
        self::$_recordSet = new Kdl_Ipadso_Csv_RecordSet(
            $indexFile,
            self::$idCode,
            $this->_charset,
            true
        );

        $this->_data = self::$_recordSet->getData();

        return self::$_recordSet;
    }

    public function fetchAll($filePath = null, $setObjectData = false)
    {
        if (!is_null($this->_data)) {
            return;
        }

        $resultSet = $this->_getCsvRecordSet($filePath)->getData();
        return $this->_setObjectData($resultSet, $setObjectData);
    }


    public function find($arg)
    {
        $resultSet = $this->_getCsvRecordSet()->findRowSet($arg);
        return $this->_setObjectData($resultSet);
    }


    public function findById($id)
    {
        $resultSet = $this->_getCsvRecordSet()->findRow($id);
        return $this->_setObjectData($resultSet);
    }


    public function findNameByValue($name, $value)
    {
        return $this->_getCsvRecordSet()->findRowSetByColumn($name, $value);

    }


    public function findRowByKey($key)
    {
        return $this->_getCsvRecordSet()->findRow($key);
    }


    public function deleteByKey($key)
    {
        $this->_getCsvRecordSet()->deleteByKey($key);
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
    }

	public function updateFromFile($rowData, $fileName = null)
    {
        $this->_getCsvRecordSet($fileName)->update($rowData);
    }

    public function updateCategoryName($kind, $code, $name)
    {
        $data = $this->findNameByValue('category'.$kind.'_code', $code);
        if ($data) {
            foreach ($data as $k => $v) {
                $v['category'.$kind.'_name'] = $name;
                $data[$k] = $v;
            }
            $this->_getCsvRecordSet()->update($data);
            $this->_setObjectData($this->_getCsvRecordSet()->getData());
        }
    }


    public function insert($rowData)
    {
        $this->_getCsvRecordSet()->insert($rowData);
        $this->_setObjectData($this->getData());
    }


    protected function _setObjectData($data, $setObjectData = false)
    {
        $entries   = array();
        if ($setObjectData && !empty($data) && (is_array($data))) {
            foreach ($data as $row) {
                $entry = new Application_Model_Index();
                $entry->setData($row);
                $entries[$row[self::$idCode]] = $entry;
            }
        }
        $this->_data = is_array($data) ? $data : array();
        return $entries;
    }


    public function getData()
    {
        if (is_null($this->_data)) {
            $this->fetchAll();
        }
        return $this->_data;
    }

	public function getDataFromFile($filePath)
    {
        if (is_null($this->_data)) {
            $this->fetchAll($filePath);
        }
        return $this->_data;
    }

    public function getHeader()
    {
        return $this->_getCsvRecordSet()->getHeader();
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


    //get data the same json format
    public function getDataJson()
    {
        $csvData = $this->getProduct4Subcomment(true);
        $count = count($csvData);
        $rows = array();

        if ($count) {
            $rows['total'] = count($csvData);
            $rows['rows'] = $csvData;
        }

        return $rows;
    }


    /**
     *
     * @return array
     * @author Nguyen Huu Tam
     * @since 2012/08/22
     */
    public function getProduct4Subcomment($json = false)
    {
        $image      = new Application_Model_Image();
        $dataImage  = $image->getArrayImage();

        $this->fetchAll();
        $data  = $this->getData();
        $count = count($data);

		ksort($data);

        $csvData = array();
        $csvData['json'] = false;
        $csvData['data'] = false;
        if ($count) {
            foreach ($data as $row) {
                if (!array_keys($dataImage, $row['image'])) {
                    $row['image'] = '';
                }
                // Thumbnail
                $row['thumb'] = Application_Model_Image::getThumbnail($row['image']);
//                if (((!empty($row['isComment']) && ($row['isComment'] == 0))
//                        && (!empty($row['isSet']) && ($row['isSet'] == 0)))
//                    || ($row['isSub'] == 1)
//                ) {
                    $csvData['json'][] = $row;
                    $csvData['data'][$row['menuCode']] = $row;
//                }
            }
        }

        if ($json) {
            return $csvData['json'];
        }

        return $csvData['data'];
    }


    // Getting product list based on category1_code or category2_code
    public function getProductBaseOnCategory($postData, $page, $limit)
    {
        $image      = new Application_Model_Image();
        $dataImage  = $image->getArrayImage();

        $session = Globals::getSession();
        $data = $this->findNameByValue('category'.$session->noList.'_code', $session->codeList);

        // Sort data
        asort($data);

        if (array_keys($postData, 'menuCode')
            && ($postData['order'] == 'asc')
        ) {
            krsort($data);
        }

        if (array_keys($postData, 'no')
            && ($postData['order'] == 'asc')
        ) {
            arsort($data);
        }

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

            if (($page == null) && ($limit == null)) {
                $rows['rows'] = $csvData;
            } else {
                if ($count > 0) {
                    $total_pages = ceil($count/$limit);
                } else {
                    $total_pages = 0;
                }

                if ($page > $total_pages) {
                    $page = $total_pages;
                }
                $start = $limit*$page - $limit;

                $rows['rows'] = (array_slice($csvData, $start, $limit));
            }
        }

        $rows['rows'] = $csvData;
        return $rows;
    }

    //get data view on the list
    public function getDataViewList($postData, $search = null, $page = null, $limit = null)
    {
        $subComment = new Application_Model_SubComment();
        $dataScm    = $subComment->fetchAll();

        $csvTopping = new Application_Model_Topping();
        $data_Topping = $csvTopping->getData();

		$category = new Application_Model_Category();
        $dataCategory    = $category->fetchAll();

        $image      = new Application_Model_Image();
        $dataImage  = $image->getArrayImage();

        foreach ($dataScm as $value) {
            // Modifield by pvduy 2013/08/28
            // display subcomment as format: id + ':' + name
            //$arrScm[$value->getNo()] = sprintf('%s:%s', $value->getNo(), $value->getGuidance());
            // End 2013/08/28
			//edit by nqtrung 2014/05/20
            $arrScm[$value->getNo()] = $value->getGuidance();
			//end
        }

        if (is_null($search)) {
            $data = $this->getData();
        } else {
            $this->find($search);
            $data = $this->getData();
        }

		$rows = array();
        $csvData = array();

        $count = count($data);
		//add category name
		if ($count) {
			foreach ($data as &$row) {
				$key = Application_Model_Category::CATE_TYPE_1 . '-' . $row['category1_code'];
				if (isset($dataCategory[$key])) {
					$row['category1_name'] = $dataCategory[$key]->getName();
				} else {
					$row['category1_name'] = '';
				}
			}
		}

        // Sort data
        Application_Model_Entity::natksort($data);

		$keySort = array('menuCode', 'category1_name', 'itemName', 'price', 'SCP5','itemToppingGroupId');
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

        if ($count) {
            $rows['total']= $count;

            foreach ($data as $id => $row) {
                foreach ($row as $key => $value) {
                    if (preg_match('~^(SCP)(\d+)$~', $key, $matches)) {
                        if (isset($arrScm[$value])) {
                            $row[$key] = $arrScm[$value];
                        } else {
							$row[$key] = '';
						}
                    }
                    //---add topping
                    if($key == 'itemToppingGroupId' && isset($data_Topping[$value]['itemToppingGroupName']) ){
                        //$row[$key] = sprintf('%s:%s',$value, $data_Topping[$value]['itemToppingGroupName']);
                        $row[$key] = $data_Topping[$value]['itemToppingGroupName'];
                    }
                }
                if (!array_keys($dataImage, $row['image'])) {
                    $row['image'] = '';
                }

                // Thumbnail
//                $row['thumb'] = Application_Model_Image::getThumbnail($row['image']);
                $row['thumb'] ='/product/image/name/' . $row['image']; //---get image size full;

                if ($row['adLink'] != '') {
                    $row['adLink'] =  "<a href = '".$row['adLink']."' target='_blank'>" . $row['adLink'] ."</a>";
                }
                $row['menuCode']=(string)$row['menuCode'];
                if(isset($row['SCP5']) && $row['SCP5'] == '0'){
                    $row['SCP5'] = '';
                }

				//関連商品
				$row['suggest'] = '';
				if (strlen($row['suggest1']) > 0 && isset($data[$row['suggest1']])) {
					$name = $data[$row['suggest1']]['itemName'];
					if (mb_strlen($name, 'UTF-8') > self::SUGGEST_MAX_LENGTH) {
						$name = mb_substr($name, 0, self::SUGGEST_MAX_LENGTH, 'UTF-8') . '・・・';
					}
					$row['suggest'] .= "<a href = '/product/edit/id_edit/" . htmlspecialchars($data[$row['suggest1']]['menuCode']) . "'>" . htmlspecialchars($name) ."</a>";
				}
				if (strlen($row['suggest2']) > 0 && isset($data[$row['suggest2']])) {
					if ($row['suggest2'] !== $row['suggest1']) {
						$name = $data[$row['suggest2']]['itemName'];
						if (mb_strlen($name, 'UTF-8') > self::SUGGEST_MAX_LENGTH) {
							$name = mb_substr($name, 0, self::SUGGEST_MAX_LENGTH, 'UTF-8') . '・・・';
						}
						if (strlen($row['suggest']) > 0) {
							$row['suggest'] .= "<br/>";
						}
						$row['suggest'] .= "<a href = '/product/edit/id_edit/" . htmlspecialchars($data[$row['suggest2']]['menuCode']) . "'>" . htmlspecialchars($name) ."</a>";
					}
				}
				if (strlen($row['suggest3']) > 0 && isset($data[$row['suggest3']])) {
					if ($row['suggest3'] !== $row['suggest1'] && $row['suggest3'] !== $row['suggest2']) {
						$name = $data[$row['suggest3']]['itemName'];
						if (mb_strlen($name, 'UTF-8') > self::SUGGEST_MAX_LENGTH) {
							$name = mb_substr($name, 0, self::SUGGEST_MAX_LENGTH, 'UTF-8') . '・・・';
						}
						if (strlen($row['suggest']) > 0) {
							$row['suggest'] .= "<br/>";
						}
						$row['suggest'] .= "<a href = '/product/edit/id_edit/" . htmlspecialchars($data[$row['suggest3']]['menuCode']) . "'>" . htmlspecialchars($name) ."</a>";
					}
				}

                $csvData[] = $row;
            }

            if (($page == null) && ($limit == null )) {
                $rows['rows'] = $csvData;
            } else {
                if ($count > 0) {
                    if( $limit > 0){
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
        }

        return $rows;
    }

   //check forgein key from index.csv file
    public function checkForgeinKey($value)
    {
        $csvSubComment = new Application_Model_SubComment();
        $csvSubComment->fetchAll();
        $dataSubcomment = $csvSubComment->getData();
        $execute = 1;
        foreach ($dataSubcomment as $k => $v) {
            foreach ($v as $key => $val) {
                 if (preg_match('~^(menu)(\d+)$~', $key, $matches)) {
                      if($val == $value) {
                           $execute = 0;
                      }
                 }
            }
        }

		if ($execute === 1) {
			$toppingItem = new Application_Model_ToppingGroupItem();
			$result = $toppingItem->find(array('itemId' => array('eq' => $value)));
			if (count($result) > 0) {
				$execute = 0;
			}
		}

        return $execute;
    }

    /**
     * @function: get data from index to fill suggest field
     * @return: array
     */
    public function getDataIndexFillCombobox()
    {
        $dataCombobox = Array();
        $data = $this->fetchAll();

		ksort($data);

        $i = 0;
        foreach ($data as $value) {
            $dataCombobox[$i]['code'] = $value->getMenuCode();
            $dataCombobox[$i]['name'] = $value->getItemName();
            $i++;
         }
         return $dataCombobox;
    }

    /**
     * change data_Form to update and insert
     * @author Nguyen Thi Tho
     * @since  2012/07/16
     */
    public function changeFormData($data)
    {
        $session  = Globals::getSession();
        if(!isset($session->data_eidt_product) ){
            $session->data_eidt_product = array();
        }
        $data = $this->_fillDataFromSession($data,$session->data_eidt_product);
        $csvCategory = new Application_Model_Category();


        $cateData = $csvCategory->getData();
        $kinds = $csvCategory->getCategoryKind();
        foreach ($kinds as $kind) {
            $code = $data["category{$kind}_code"];
            $data["category{$kind}_name"] = $cateData["{$kind}-{$code}"]['name'];
        }

        if (isset($data['startTime']) && !empty($data['startTime'])) {
            $data['startTime'] = $this->changeFormatTime($data['startTime']);
        }

        if (isset($data['endTime']) && !empty($data['endTime'])) {
            $data['endTime'] = $this->changeFormatTime($data['endTime']);
        }

        return $data;
    }


    /**
     *
     * @param string $time
     * @return string
     * @author Nguyen Huu Tam
     * @since 2013/02/27
     */
    public function changeFormatTime($time)
    {
        return str_replace(':', '', $time);
    }


    /**
     * get max no in index.csv file
     * @author Nguyen Thi Tho
     * @since  2012/07/30
     */
    public function getMaxNo()
    {
        $arrayNo = 0;
        $entries = $this->fetchAll();
        asort($entries);
        foreach ($entries as $value) {
            $arrayNo = $value->getNo();
        }
        return $arrayNo;
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
    public function prepareData($data)
    {
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
            $row['image']           = $currData[$id]['image'];
            $row['subprice']        = $currData[$id]['subprice'];
            $row['category2_name']  = $currData[$id]['category2_name'];
            $row['suggest1']        = $currData[$id]['suggest1'];
            $row['suggest2']        = $currData[$id]['suggest2'];
            $row['suggest3']        = $currData[$id]['suggest3'];
            $row['adLink']          = $currData[$id]['adLink'];
            $row['other1']          = $currData[$id]['other1'];
            $row['other2']          = $currData[$id]['other2'];
            $row['startTime']       = $currData[$id]['startTime'];
            $row['endTime']         = $currData[$id]['endTime'];
        }

        return $updateData;
    }


    /**
     * Sort product list by group and menuCode
     *
     * @param array $data
     * @return array
     * @author Nguyen Huu Tam
     * @since 2012/10/04
     */
    static public function sortMenuProduct($data)
    {
        // Sort data by group and menuCode
        if (!empty($data)) {
            uasort(
                $data,
                create_function('$a,$b', '
                    if ($a["group"] == $b["group"]) {
                        return ($a["' . self::$idCode . '"] < $b["' . self::$idCode . '"]) ? -1 : 1;
                    }
                    return ($a["group"] < $b["group"]) ? -1 : 1;'
                )
            );
            $group = '';
            foreach ($data as $key => &$value) {
                // If the same group
                if ($group == $value['group']) {
                    $value['main_item'] = false;
                    // Set common target
                    $value = array_merge($value, $target);
                    continue;
                };

                // Get some value in first item in the group
                // And set for another items in same group
                $target = Application_Model_Html::getTarget($value);
                $group  = $value['group'];
                $value['main_item'] = true;
            }
        }

        return $data;
    }


    /**
     *
     * @param array $data
     * @return array
     * @author Nguyen Huu Tam
     * @since 2012/10/28
     */
    protected function _fillData($data)
    {
        $newData = array();

        foreach ($this->_columns as $column) {
            if (!isset($data[$column])) {
                $newData[$column] = '';
            } else {
                $newData[$column] = $data[$column];
            }
        }

        return $newData;
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

    public function createOptionProd($data,$option,$val_select ) {
        $html = '';
        $val = $option['value'];
        $title = $option['title'];
		if (is_array($data)) {
			foreach ($data as $key => $value) {
				if($val_select == $value[$val]){
                    // 2015/02/19 Nishiyama Add エスケープ追加
					$html .= '<option value="'.$value[$val].'" selected="selected" >'.htmlspecialchars($value[$title]).'</option>';
				}else{
                    // 2015/02/19 Nishiyama Add エスケープ追加
					$html .= '<option value="'.$value[$val].'" >'.htmlspecialchars($value[$title]).'</option>';
				}
			}
		}
        return $html;
    }
}
