<?php
/**
 * Make all images thumbnail
 *
 * @author Nguyen Huu Tam
 * @copyright Kobe Digital Labo, Inc
 * @since 2013/02/21
 */
require_once('init.php');

// Get config
$dataConfig = Globals::getApplicationConfig('data');
$imgConfig = Globals::getApplicationConfig('image');

// 商品
$productImagePath   = $dataConfig->master_data_path . $imgConfig->product->image_path;
$productThumbPath   = $imgConfig->product->thumb_path;
$productThumbWidth  = $imgConfig->product->thumb_width;
// メニュー
$menuImagePath  = $dataConfig->master_data_path . $imgConfig->menu->image_path;
$menuThumbPath  = $imgConfig->menu->thumb_path;
$menuThumbWidth = $imgConfig->menu->thumb_width;

// Get admin information
$adminModel = new Application_Model_Admin();
$adminList = $adminModel->getData();

if (!is_null($adminList) && count($adminList)) {
    foreach ($adminList as $key => $info) {
        $imgAgrs = array(
            $info['companyName'],
            $info['companyCode'],
            $info['fileName']
        );
        // 商品
        $proImg = getImagePath($productImagePath, $imgAgrs);
        $proThumbAgrs = array(
            $info['companyName'],
            $info['companyCode']
        );
        $proThumb = getThumbPath($productThumbPath, $proThumbAgrs);
         
        Application_Model_Image::createThumbAll(
            $proImg,
            $proThumb,
            $productThumbWidth
        );
        
        // メニュー
        $menusetObj = new Application_Model_Menuset();
        $dataPath = getImagePath($menuImagePath, $imgAgrs);
        $menusetObj->setFolderPath($dataPath);
        $menusets = $menusetObj->getAllMenusets();
        
        foreach ($menusets as $item) {
            if (empty($item['menuset'])) {
                $item['menuset'] = Application_Model_Menuset::getDefaultMenuset();
            }
            
            $menuImg = $dataPath . $item['name'] . '/images/';
            $menuThumbAgrs = array(
                $info['companyName'],
                $info['companyCode'],
                $item['menuset']
            );
            $menuThumb = getThumbPath($menuThumbPath, $menuThumbAgrs);
            
            Application_Model_Image::createThumbAll(
                $menuImg,
                $menuThumb,
                $menuThumbWidth
            );
        }
    }
}

function getThumbPath($thumbPathConfig, $agrs)
{
    $thumbPath = vsprintf($thumbPathConfig, $agrs);
    if (!is_dir($thumbPath)) {
         mkdir($thumbPath, 0777, true);
    }
    return $thumbPath;
}

function getImagePath($imagePathConfig, $agrs)
{
    $imagePath = vsprintf($imagePathConfig, $agrs);
    return $imagePath;
}
