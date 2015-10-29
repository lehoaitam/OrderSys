<?php
/**
 * Class DataPacker
 *
 * @author Nguyen Huu Tam
 * @copyright Kobe Digital Labo, Inc
 * @since 2012/08/08
 */

class DataPacker
{
    const LOG_FILENAME = 'batch.log';

    static protected $_batchClass = 'DataPackerBatch.php';

    /**
     * Pack data
     * 
     * @param string $dirPath 
     * @author Nguyen Huu Tam
     * @since 2012/08/08
     */
    static public function packData($dirPath)
    {
        try {
            $dirPath = self::setCommonPath($dirPath);

            if (is_dir($dirPath)) {
                $zipPath = self::setZipPath($dirPath);

                if (!is_dir($zipPath)) {
                    mkdir($zipPath, 0777, true);
                } 
                
                $zipFile = "$zipPath.zip";
                // Remove old zip file
                if (file_exists($zipFile)) {
                    unlink($zipFile);
                }

                $zip = new ZipArchive;
                $res = $zip->open($zipFile, ZipArchive::CREATE);
                if ($res === true) {
                    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dirPath));
                    $dataConfig = Globals::getApplicationConfig('data');
                    foreach ($iterator as $path => $splFileInfo) {
                        $workPath = $dirPath . DIRECTORY_SEPARATOR . $dataConfig->work_folder;
                        // Don't zip work folder
                        if (strpos($path, $workPath) !== false) {
                            continue;
                        }
                        
                        $path = self::setCommonPath($path);
                        $localName = str_replace($dirPath . '/', '', $path);
                        // If not a file (is a directory) then continue
                        if (!$splFileInfo->isFile()) continue;

                        if (!($zip->addFile(realpath($path), $localName))) {
                            Globals::log("Could not add file: $path", null, self::LOG_FILENAME);
                        }
                    }
                    Globals::log($zip, null, self::LOG_FILENAME);
                    
                    $zip->close();
                } else {
                    Globals::log($res, null, self::LOG_FILENAME);
                }
                
                // Remove zip directory
                rmdir($zipPath);
            }

        } catch (Exception $e) {
            Globals::logException($e);
        }
    }
    
    /**
     * Replace '\' to '/'
     * 
     * @param string $path
     * @return string
     * @author Nguyen Huu Tam
     * @since 2012/08/08
     */
    static function setCommonPath($path)
    {
        if (realpath($path)) {
            return str_replace('\\', '/', realpath($path));
        }
        
        Globals::log("Directory [$path] does not exist.", null, self::LOG_FILENAME);
    }
    
    /**
     * Get zip directory path
     * 
     * @param string $path
     * @return null|string
     * @author Nguyen Huu Tam
     * @since 2012/08/08
     */
    static function setZipPath($path)
    {
        $dataConfig = Globals::getApplicationConfig('data');
            
        $dataPath   = self::setCommonPath($dataConfig->master_data_path);
        $zipPath    = $dataConfig->zip_data_path;
        
        return str_replace($dataPath, $zipPath, $path);
    }
    
    /**
     * Pack all data 
     * 
     * @author Nguyen Huu Tam
     * @since 2012/08/08
     */
    static function packAllData()
    {
        $adminModel = new Application_Model_Admin();
        foreach ($adminModel->fetchAll() as $row) {
            self::packData($row->getDirPath());
        }
    }
    
    /**
     * Pack data bacth
     * 
     * @param string $path 
     * @author Nguyen Huu Tam
     * @since 2012/08/08
     */
    static public function packDataInBatch($path)
    {
        $batchConfig = Globals::getApplicationConfig('batch');
        $execArray = array(
            'php_bin'   => $batchConfig->php_bin,
            'runner'    => BATCH_DIR . DIRECTORY_SEPARATOR . self::$_batchClass,
            'argument'  => $path,
            'output'    => '> /dev/null 2>&1 &'
        );

        $execCommand = implode(' ', $execArray);
        Globals::log("Executed batch command: $execCommand");

        $result = array();
        // Execute command
        // exec($execCommand, $result);
        exec($execCommand);
//        Globals::log($result);
    }
}