<?php

/**
 * Class Recommend products
 *
 * @author nqtrung
 * @copyright Kobe Digital Labo, Inc
 * @since 2015/05/18
 */
require_once('Kdl/Ipadso/Json.php');
require_once('Index.php');

class Application_Model_RecommendProducts {

    const STATUS_FILE = 'itemstatus.json';
    
    private $dir_path;
    
    public function __construct($dir_path) {
        $this->dir_path = $dir_path;
    }

    /**
     * Init recommend products json data
     * 
     * @author nqtrung
     * @since 2015/05/18
     */
    public function initRecommendProductsJsonData() {
        $initData = array();
        $this->saveRecommendProductsJsonData($initData);

        return $initData;
    }

    /**
     * Init recommend products json data
     * 
     * @author nqtrung
     * @since 2015/05/18
     */
    public function getRecommendProductsJsonData() {
        $dataObj = new Kdl_Ipadso_Json($this->dir_path . DIRECTORY_SEPARATOR . self::STATUS_FILE);
        $data = $dataObj->getJsonConfig()->toArray();
        return isset($data['status']) ? $data['status'] : array();
    }

    /**
     * Save  recommend products json data
     * 
     * @param array $data
     * @author nqtrung
     * @since 2015/05/18
     */
    public function saveRecommendProductsJsonData($data) {
        $dataObj = new Kdl_Ipadso_Json($this->dir_path . DIRECTORY_SEPARATOR . self::STATUS_FILE);
        $dataObj->save($data);
    }

    /**
     * get all recommend products from json
     * 
     * @author nqtrung
     * @since 2015/05/18
     */
    public function getRecommendProducts($postData, $search = null, $page = null, $limit = null) {
        $index = new Application_Model_Index();
        $category = new Application_Model_Category();
        $dataCategory = $category->fetchAll();
        
        $image      = new Application_Model_Image();
        $dataImage  = $image->getArrayImage();

        if (is_null($search) || count($search) == 0) {
            $data = $index->getData();
        } else {
            $index->find($search);
            $data = $index->getData();
        }

        $rows = array();
        $csvData = array();

        //set recommend status
        $recommendData = $this->getRecommendProductsJsonData();

        if (isset($postData['recommend'])) {
            $tmp = array();
            foreach ($data as $k => $v) {
                if (array_key_exists($k, $recommendData) && array_key_exists('recommendation', $recommendData[$k]) && $recommendData[$k]['recommendation'] == 'true') {
                    $v['recommend'] = 1;
                    $v['recommendation_order'] = isset($recommendData[$k]['recommendation_order']) ? $recommendData[$k]['recommendation_order'] : '';
                    $tmp[$k] = $v;
                }
            }
            $data = $tmp;
        } else {
            foreach ($data as $k => $v) {
                if (array_key_exists($k, $recommendData) && array_key_exists('recommendation', $recommendData[$k]) && $recommendData[$k]['recommendation'] == 'true') {
                    $data[$k]['recommend'] = 1;
                    $data[$k]['recommendation_order'] = isset($recommendData[$k]['recommendation_order']) ? $recommendData[$k]['recommendation_order'] : '';
                } else {
                    $data[$k]['recommend'] = 0;
                    $data[$k]['recommendation_order'] = '';
                }
            }
        }

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
                
                if (!array_keys($dataImage, $row['image'])) {
                    $row['image'] = '';
                }

                // Thumbnail
                $row['thumb'] ='/product/image/name/' . $row['image'];
            }
        }

        // Sort data
        Application_Model_Entity::natksort($data);

        $keySort = array('menuCode', 'category1_name', 'itemName');
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
    
    /**
     * get recommendation title
     * 
     * @author nqtrung
     * @since 2015/06/17
     */
    public function getRecommendProductsTitle() {
        $dataObj = new Kdl_Ipadso_Json($this->dir_path . DIRECTORY_SEPARATOR . self::STATUS_FILE);
        $data = $dataObj->getJsonConfig()->toArray();
        if (isset($data['recommendation_title'])) {
            return $data['recommendation_title'];
        }
        return '';
    }

    /**
     * remove all recommend products that not exist in the product list
     * 
     * @author nqtrung
     * @since 2015/05/18
     */
    public function removeRecommendProductsFromProductsList($data) {
        $index = new Application_Model_Index();
        $products = $index->getData();
        $r = array();
        foreach ($data as $k => $v) {
            if (isset($products[$k])) {
                $r[$k] = $v;
            }
        }
        return $r;
    }

}
