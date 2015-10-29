<?php
/**
 * Class PrinterGroup
 *
 * @author nqtrung
 * @copyright Kobe Digital Labo, Inc
 * @since 2014/12/16
 */
require_once('Kdl/Ipadso/Json.php');

class Application_Model_PrinterGroup
{
    const STATUS_FILE = 'printergroup.json';

    private $dir_path;
    
    public function __construct($dir_path = null) {
        if (is_null($dir_path)) {
            $session = Globals::getSession();
            $dir_path = $session->company_link;
        }
        $this->dir_path = $dir_path;
    }

    /**
     * Init printer group json data
     * 
     * @author nqtrung
     * @since 2014/12/17
     */
    public function initPrinterGroupJsonData() {
        $initData = array();
        $this->savePrinterGroupJsonData($initData);

        return $initData;
    }
    
    public static function getMaxID($data) {
        $max = 0;
        foreach ($data as $value) {
            if ($value['id'] > $max) {
                $max = $value['id'];
            }
        }
        return $max;
    }

    public function getPrinterGroupFile() {
        return $this->dir_path . DIRECTORY_SEPARATOR . self::STATUS_FILE;
    }

    /**
     * Init printer group json data
     * 
     * @author nqtrung
     * @since 2014/12/17
     */
    public function getPrinterGroupJsonData($id = null) {
        $dataObj = new Kdl_Ipadso_Json($this->getPrinterGroupFile());
        $configObj = $dataObj->getJsonConfig(false);
        $data = $configObj ? $configObj->toArray() : null;
        if (!is_array($data)) {
            return array();
        }
        $data = isset($data['printergroups']) ? $data['printergroups'] : array();;
        if (is_null($id)) {
            return $data;
        } else {
            foreach ($data as $item) {
                if ($item['id'] == $id) {
                    return $item;
                }
            }
        }
        return array();
    }
    
    /**
     * Edit  printer group json data
     * 
     * @param array $data
     * @author nqtrung
     * @since 2014/12/17
     */
    public function editPrinterGroupJsonData($item) {
        $data = $this->getPrinterGroupJsonData();
        $index = 0;
        $f = false;
        foreach ($data as $index => $value) {
            if ($value['id'] == $item['id']) {
                $f = true;
                break;
            }
        }
        if ($f) {
            $data[$index] = $item;
            $data = array('printergroups' => $data);
            $this->savePrinterGroupJsonData($data);
            return true;
        }
        return false;
    }
    
    /**
     * Delete  printer group json data
     * 
     * @param array $data
     * @author nqtrung
     * @since 2014/12/17
     */
    public function deletePrinterGroupJsonData($id) {
        $data = $this->getPrinterGroupJsonData();
        $index = 0;
        $f = false;
        foreach ($data as $index => $value) {
            if ($value['id'] == $id) {
                $f = true;
                break;
            }
        }
        if ($f) {
            unset($data[$index]);
            $data = array('printergroups' => $data);
            $this->savePrinterGroupJsonData($data);
            return true;
        }
        return false;
    }

    /**
     * Save  printer group json data
     * 
     * @param array $data
     * @author nqtrung
     * @since 2014/12/17
     */
    public function savePrinterGroupJsonData($data) {
        $dataObj = new Kdl_Ipadso_Json($this->getPrinterGroupFile());
        $dataObj->save($data);
    }

    /**
     * get all printer group from json
     * 
     * @author nqtrung
     * @since 2014/12/17
     */
    public function getPrinterGroup($postData, $search = null, $page = null, $limit = null) {
        $rows = array();
        $csvData = array();

        //set printer group status
        $printerGroupData = $this->getPrinterGroupJsonData();
        $data = array();
        foreach ($printerGroupData as $value) {
            $data[] = array('id' => $value['id'], 'printerGroupName' => $value['printerGroupName']);
        }

        $count = count($data);

        // Sort data
        Application_Model_Entity::natksort($data);

        $keySort = array('id', 'printerGroupName');
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
            $rows['total'] = $count;

            foreach ($data as $row) {
                $csvData[] = $row;
            }

            if (($page == null) && ($limit == null )) {
                $rows['rows'] = $csvData;
            } else {
                if ($count > 0) {
                    if ($limit > 0) {
                        $total_pages = ceil($count / $limit);
                    } else {
                        $total_pages = 1;
                    }
                } else {
                    $total_pages = 0;
                }

                if ($page > $total_pages) {
                    $page = $total_pages;
                }
                $start = $limit * $page - $limit;

                $rows['rows'] = (array_slice($csvData, $start, $limit));
            }
        }

        return $rows;
    }
}
