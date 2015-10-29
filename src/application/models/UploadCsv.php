<?php
/**
 * Class Image
 *
 * @author Nguyen Thi Tho
 * @copyright Kobe Digital Labo, Inc
 * @since 2012/07/30
 */
require_once('Kdl/Ipadso/Csv/RecordSet.php');
require_once('Kdl/Ipadso/Csv/RecordSetEx.php');
require_once('Kdl/Ipadso/Csv/Reader.php');

class Application_Model_UploadCsv
{
    protected $_charset = 'UTF-8';

    protected $_data = null;

    public function __construct($id = '')
    {
        $this->session = Globals::getSession();

        $this->_path = Globals::getTmpUploadFolder();
        $this->_id = $id;
        $this->_fileConfig = Globals::getApplicationConfig('upload');
        $this->_allowTypesCsv = explode(',', $this->_fileConfig->allowTypesCsv);
        $this->msgConfig = Zend_Registry::get('MsgConfig');
    }

     /**
     * @function: get data from .csv import file
     *
     * @return data
     * @author Nguyen Thi Tho
     * @since 2012/07/30
     */
    public function getData($file_name_up, $id)
    {
        $db = new Kdl_Ipadso_Csv_RecordSet($file_name_up, $id);
        $this->_data = $db->getData();
        return $this->_data;
    }

      /**
     * @function: get data from category.csv import file
     *
     * @return data
     * @author Nguyen Thi Tho
     * @since 2012/07/30
     */
    public function getDataCategory($file_name_up, $id)
    {
        $db = new Kdl_Ipadso_Csv_RecordSetEx($file_name_up, $id);
        $this->_data = $db->getData();
        return $this->_data;
    }

    /**
     * @function: get header's from .csv import file
     *
     * @return data
     * @author Nguyen Thi Tho
     * @since 2012/07/30
     */
    public function getHeader($file_name_up)
    {       
       $db = new Kdl_Ipadso_Csv_Reader($file_name_up);
       $resultSet = $db->getHeader();
	   
	   if (is_array($resultSet) && count($resultSet) > 0) {
		   $resultSet[0] = $this->removeUTF8BOM($resultSet[0]);
	   }
	   
       return  $resultSet;
    }
	
	public function removeUTF8BOM($text){
		$bom = pack('H*','EFBBBF');
		$text = preg_replace("/^$bom/", '', $text);
		return $text;
	}

    public function checkColumnName($columnName)
    {
        print_r($this->getHeader());
        if (false === array_search($columnName, $this->getHeader())) {
            throw new Exception(
                sprintf($this->msgConfig->C004_ColumnNameNotFound, $columnName)
            );
        }
        return true;
    }

     /**
     * @action: Upload csv
     *
     * @return void
     * @author Nguyen Thi Tho
     * @since 2012/07/30
     *@param: $file_name (upload file's name)
     */
    public function checkFile($file_type, $file_name)
    {
        $response = array();
        $datetime = date("h").date("i").date("s").date("y").date("m").date("d")."_";

        $upload   = new Zend_File_Transfer_Adapter_Http();

        if ($upload->isUploaded($file_name)) {
            // Add rules to validate file
            $upload->addValidator('Size', false, $this->_fileConfig->maxSize)
                   ->addValidator('Extension', false, $this->_allowTypesCsv);
            //Check upload folder and create
            if (!is_dir($this->_path)) {
                try {
                    mkdir($this->_path, 0777);
                    chown($this->_path, 775);
                } catch (Exception $e) {
                    Globals::log($e);
                }
            }
            // Set folder to save uploaded file
            $upload->setDestination($this->_path);
            
             // Returns all known internal file information
            $file        = $upload->getFileInfo();
            $source      = $file[$file_type]['tmp_name'];
            $newname     = $this->_path . $datetime. 'E_' . $file_name;
            $path_up     = $this->_path . $file_name;

            if (!$upload->receive($file_name)) {   
                if (!file_exists($path_up)) {
                    copy($source, $newname);
                } else {
                    rename($path_up, $newname);
                }
                $messages = $upload->getMessages();
                Globals::log($messages);
                return 'E300_FileUploadInvalid';
            } else {
                return 1;
            }
        } else {
            return 'E300_RequireFileUpload';
        }
    }

   /**
     * @action: Copy file and rename upload csv file
     *
     * @return void
     * @author Nguyen Thi Tho
     * @since 2012/07/30
     * @param: $file_name (upload file's name)
     */
    public function copyFileCsv($file_name)
    {
        $datetime = date("h") . date("i") . date("s") . date("y") . date("m") . date("d") . "_";
		$path_up = $this->_path . $file_name;

		if (file_exists($path_up)) {
			//Rename
			$newname = $this->_path . $datetime . $file_name;
			rename($path_up, $newname);
			
			//remove BOM
			$data = $this->removeUTF8BOM(file_get_contents($newname));
			file_put_contents($newname, $data);
			
			return $newname;
		} else {
			return 0;
        }
   }

   /**
     * @action: Create file and rename upload csv file with charset
     *
     * @return void
     * @author Phan Quoc Bao
     * @since 2014/05/08
     * @param: $file_name (upload file's name)
     */
    public function copyIndexFileCsvWithCharSet($file_name, $charsetform = 'SJIS', $changeHeaderToEn = false)
    {
        $datetime = date("h").date("i").date("s").date("y").date("m").date("d")."_";
        $path_up     = $this->_path . $file_name;
       
        if (file_exists($path_up)) {
            $fileup = file_get_contents($path_up);
			$newname = $this->_path.$datetime.$file_name;
            //---convert charset;
            $data = mb_convert_encoding($fileup,'UTF-8', $charsetform);	
			if ($changeHeaderToEn) {
				//change header to English
				$data = explode("\n", $data);
				$data[0] = $this->_fileConfig->csv->index->printer->download->upload->columns->en;
				//Rename
				file_put_contents($newname, implode("\n", $data));			
			} else {
				//remove BOM
				$data = $this->removeUTF8BOM($data);
				//Rename
				file_put_contents($newname, $data);
			}
			
            return $newname;
        } else {
            return 0;
        }
   }
   
   /**
     * @action: Create file and rename upload csv file with charset
     *
     * @return void
     * @author Phan Quoc Bao
     * @since 2014/05/08
     * @param: $file_name (upload file's name)
     */
    public function copyCategoryFileCsvWithCharSet($file_name, $charsetform = 'SJIS', $changeHeaderToEn = false)
    {
        $datetime = date("h").date("i").date("s").date("y").date("m").date("d")."_";
        $path_up     = $this->_path . $file_name;
       
        if (file_exists($path_up)) {
            $fileup = file_get_contents($path_up);
			$newname = $this->_path.$datetime.$file_name;
            //---convert charset;
            $data = mb_convert_encoding($fileup,'UTF-8', $charsetform);	
			if ($changeHeaderToEn) {
				//change header to English
				$data = explode("\n", $data);
				$data[0] = $this->_fileConfig->csv->category->printer->download->upload->columns->en;
				//Rename
				file_put_contents($newname, implode("\n", $data));			
			} else {
				//remove BOM
				$data = $this->removeUTF8BOM($data);
				//Rename
				file_put_contents($newname, $data);
			}
			
            return $newname;
        } else {
            return 0;
        }
   }
   
   /** Check chaerset file upload
     * @return msg
     * @author Phan Quoc Bao
     * @since 2014/05/08
     * @param: $file_name (upload file's name)
     */
    public function checkCharsetFileCsv($file_name,$charsetform = 'SJIS')
    {
        $path_up     = $this->_path . $file_name;
        $msg = '';
        if (file_exists($path_up)) {
            $fileup = file_get_contents($path_up);
            //---check charset;
            if ( !(mb_check_encoding($fileup, $charsetform)) ) {
				Globals::log($this->msgConfig->C007_Chaset_invalid);
                $msg = 'ERROR';
            }                      
        }
        return $msg;
   }
   
    /**
     * @action: To do upload - Insert new data and overwrite the same data in the index.csv
     *
     * @return void
     * @author Nguyen Thi Tho
     * @since 2012/07/30
     * @param: $file_name (upload file's name)
     */
    public function uploadCsvIndex($data_imp, $data_del)
    {
        // Define some columns with do not update data
        $skipColumns = explode(',', $this->_fileConfig->csv->index->skipColumns);
        
        $csvIndex = new Application_Model_Index();
        $orgData = $csvIndex->getData();

        if ($data_del) {
            $csvIndex->deleteRow($data_del);
        }
        $maxNo = $csvIndex->getMaxNo();
        
        foreach ($data_imp as $key => $value) {
            // Don't update some columns data
            if (array_key_exists($key, $orgData)) {
                foreach ($skipColumns as $column) {
                    if (isset($orgData[$key][$column])) {
                        // Get orginal data
                        $data_imp[$key][$column] = $orgData[$key][$column];
                    }
                }
            }
            $maxNo++;
            $data_imp[$key]['no'] = $maxNo;
       }
       
       try {
            $csvIndex->insert($data_imp);
       } catch (Exception $e) {
            Globals::log($e);
       }
   } 

   /**
     * @action: To do upload - Insert new data and overwrite the same data in the category.csv
     *
     * @return void
     * @author Nguyen Thi Tho
     * @since 2012/08/02
     *@param: $file_name (upload file's name)
     */
   public function uploadCsvCategory($data_imp, $data_del)
   {
	   // Define some columns with do not update data
        $skipColumns = explode(',', $this->_fileConfig->csv->category->skipColumns);
        
        $csvCategory = new Application_Model_Category();
        $orgData = $csvCategory->getData();

        if ($data_del) {
            $csvCategory->deleteRow($data_del);
        }
        
        foreach ($data_imp as $key => $value) {
            // Don't update some columns data
            if (array_key_exists($key, $orgData)) {
                foreach ($skipColumns as $column) {
                    if (isset($orgData[$key][$column])) {
                        // Get orginal data
                        $data_imp[$key][$column] = $orgData[$key][$column];
                    }
                }
            }
       }
	   
       try {
            $csvCategory->insert($data_imp);
       } catch (Exception $e) {
           Globals::log($e);
       }
   }

   /**
     * @action: To do upload - Insert new data and overwrite the same data in the subcomment.csv
     *
     * @return void
     * @author Nguyen Thi Tho
     * @since 2012/08/03
     *@param: $file_name (upload file's name)
     */
    public function uploadCsvSubcomment($data_imp, $data_del)
    {
        $result = 1;
        $csvSubcomment = new Application_Model_Subcomment();
        if ($data_del) {
            $csvSubcomment->deleteRow($data_del);
        }
        try {
            $csvSubcomment->insert($data_imp);
        } catch (Exception $e) {
            Globals::log($e);
            $result = 'E300_FileUploadInvalid';
        }
        return $result;
    }

    
    /**
     * Get csv import type config
     * 
     * @return array
     * @throws Zend_Config_Exception
     * @author Nguyen Huu Taam
     * @since 2013/02/25
     */
    public static function getImportType($type)
    {
        $uploadConfig = Globals::getApplicationConfig('upload');
        $importType = $uploadConfig->csv->importType->$type->options;
        if (!is_null($importType)
            && is_object($importType)
        ) {
            return $importType->toArray();
        } else {
            $msgConfig = Zend_Registry::get('MsgConfig');
            require_once 'Zend/Config/Exception.php';
            throw new Zend_Config_Exception(
                sprintf($msgConfig->E200_Invalid_ConfigInfo, 'upload', "csv.importType.{$type}.options")
            );
        }
    }
    
    
    /**
     * Get csv import type config
     * 
     * @return array
     * @throws Zend_Config_Exception
     * @author Nguyen Huu Taam
     * @since 2013/02/25
     */
    public static function getImportTypeDefault($type)
    {
        $uploadConfig = Globals::getApplicationConfig('upload');
        $default = $uploadConfig->csv->importType->$type->defaultOption;

        if (!is_null($default)) {
            return $default;
        } else {
            $msgConfig = Zend_Registry::get('MsgConfig');
            require_once 'Zend/Config/Exception.php';
            throw new Zend_Config_Exception(
                sprintf($msgConfig->E200_Invalid_ConfigInfo, 'upload', "csv.importType.{$type}.defaultOption")
            );
        }
    }
}
