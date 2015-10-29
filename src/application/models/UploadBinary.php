<?php
/**
 * Class UploadBinary
 *
 * @author Nguyen Thi Tho
 * @copyright Kobe Digital Labo, Inc
 * @since 2012/08/07
 */
require_once('Kdl/Ipadso/Bin/BinaryReader.php');

class Application_Model_UploadBinary
{
    const MENUCODELEN = 4;
    
    const POS_TMP_FOLDER = 'pos_data';

    protected $_data = null;

    public function __construct($file = null)
    {
        $session    = Globals::getSession();

        $this->_pathSaveFile  = Globals::getTmpUploadFolder();
        $this->_uploadConfig  = Globals::getApplicationConfig('upload');
//        $this->_file          = realpath(APPLICATION_PATH . '/../data/POS/'.$file);
        $this->_file          = $this->_pathSaveFile . $file;
        $this->_fileSaveTxt   = '_' . date('YmdHsi') . '.txt';
        $this->_fileSaveCsv   = '_' . date('YmdHsi') . '.csv';
        $this->_allowTypesPos = str_split($this->_uploadConfig->allowTypesPos, 3);
        $this->binReader      = new BinaryReader();
        
        $this->msgConfig = Zend_Registry::get('MsgConfig');
    }

    public function makeIndexFile()
    {
        $binReader = new BinaryReader();
        $csvIndex  = new Application_Model_Index();
        $header    = $csvIndex->getHeader();
        $contents  = implode(',', $header);

        $data = $binReader->readBinFile($this->_file, BinaryReader::MENU_TYPE);
        if ($data) {
            $i = 0;
            foreach ($data as $value) {
                $strValue = '';
                $i++;
                foreach ($header as $k => $v) {
                    switch ($v) {
                        case 'no':
                            $strValue = $i;
                            break;
                        case 'menuCode':
                            $strValue .= ','.$value['menu_code'];
                            break;
                        case 'itemName':
                            $strValue .= ','.$value['kanji_1'];
                            break;
                        case 'price':
                            $price = str_replace(',', '', $value['unit_price']);
                            $strValue .= ','.$price;
                            break;
                        case 'subprice':
                            $price = str_replace(',', '', $value['sub_price']);
                            $strValue .= ','.$price;
                            break;
                        case 'SCP1':
                            $strValue .= ','.$value['scp_status1'];
                            break;
                        case 'SCP2':
                            $strValue .= ','.$value['scp_status2'];
                            break;
                        case 'SCP3':
                            $strValue .= ','.$value['scp_status3'];
                            break;
                        case 'SCP4':
                            $strValue .= ','.$value['scp_status4'];
                            break;
                        case 'SCP5':
                            $strValue .= ','.$value['scp_status5'];
                            break;
                        case 'SCP6':
                            $strValue .= ','.$value['scp_status6'];
                            break;
                        case 'SCP7':
                            $strValue .= ','.$value['scp_status7'];
                            break;
                        case 'SCP8':
                            $strValue .= ','.$value['scp_status8'];
                            break;
                        case 'SCP9':
                            $strValue .= ','.$value['scp_status9'];
                            break;
                        case 'SCP10':
                            $strValue .= ','.$value['scp_status10'];
                            break;
                        case 'SCP11':
                            $strValue .= ','.$value['scp_status11'];
                            break;
                        case 'SCP12':
                            $strValue .= ','.$value['scp_status12'];
                            break;
                        default:
                            $strValue .= ',';
                            break;
                    }
                }
                $contents .= PHP_EOL . $strValue;
            }

            // Create CSV file
            $csvFile = self::getPosTmpFolder() . Application_Model_Index::MAIN_FILE;
            $binReader->saveData2File($contents, $csvFile);
            
            return $csvFile;
        } else {
            return false;
        }
    }

    public function makeSubcommentFile()
    {
        $binReader      = new BinaryReader();
        $csvSubcomment  = new Application_Model_SubComment();
        $header         = $csvSubcomment->getHeader();
        $contents       = implode(',', $header);

        $data = $this->binReader->readBinFile($this->_file, BinaryReader::SUBCOMMENT_TYPE);
        if ($data) {
            foreach ($data as $key => $value) {
                $contents .= PHP_EOL.implode(',', $value);
            }
            
            // Set product with value 0000 to null
            $contents = str_replace('0000', '', $contents);
            // Create CSV file
            $csvFile = self::getPosTmpFolder() . Application_Model_SubComment::MAIN_FILE;
            $binReader->saveData2File($contents, $csvFile);

            return $csvFile;
        } else {
            return false;
        }
    }


    /**
     *
     * @param array $uploadData
     * @return string|boolean 
     * @author Nguyen Huu Tam
     * @since 2012/10/10
     */
    public function makeCategoryFile($uploadData)
    {
        $binReader      = new BinaryReader();
        $csvCategory    = new Application_Model_Category();
        $header         = $csvCategory->getHeader();
        $contents       = implode(',', $header);
        
        // カテゴリ１
        $file_1 = $this->_pathSaveFile . $uploadData['page_pos_1']['name'];
        $cate_1 = $binReader->readBinFile($file_1, BinaryReader::CATE_1_TYPE);
        foreach ($cate_1 as $key => $value) {
            $contents .= PHP_EOL 
                . Application_Model_Category::CATE_TYPE_1 
                . ',' 
                . implode(',', $value);
        }    

        // カテゴリ２
        $file_2 = $this->_pathSaveFile . $uploadData['page_pos_2']['name'];
        $cate_2 = $binReader->readBinFile($file_2, BinaryReader::CATE_2_TYPE);
        foreach ($cate_2 as $key => $value) {
            $contents .= PHP_EOL
                . Application_Model_Category::CATE_TYPE_2
                . ',' 
                . $value['dp_code']
                . ','
                . $value['kanji']
                . ',';
        }
        
        // Create csv file
        $csvFile = self::getPosTmpFolder() . Application_Model_Category::MAIN_FILE;
        $binReader->saveData2File($contents, $csvFile);

        if (empty($cate_1)) {
            $msgLog = sprintf($this->msgConfig->E305_Failed_Import, 'カテゴリ１');
            Globals::log($msgLog);
        }
        
        if (empty($cate_2)) {
            $msgLog = sprintf($this->msgConfig->E305_Failed_Import, 'カテゴリ２');
            Globals::log($msgLog);
        }
        
        if ((!empty($cate_1)) || (!empty($cate_2))) {
            return $csvFile;
        } else {
            return false;
        }
    }
    

    /**
     *
     * @return string|boolean
     * @author Nguyen Huu Tam
     * @since 2012/10/10 
     */
    protected function _createCategoryZipFile()
    {
        $dirPath = self::getPosTmpFolder();
        $zipFile = $this->_pathSaveFile . 'category_' . date('YmdHis') . '.zip';
        
        $zip = new ZipArchive;
        $res = $zip->open($zipFile, ZipArchive::CREATE);
        
        if ($res === true) {
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dirPath));

            foreach ($iterator as $path => $splFileInfo) {
                // If not a file (is a directory) then continue
                if (!$splFileInfo->isFile()) continue;
                
                $localName = str_replace($dirPath, 'category' . DIRECTORY_SEPARATOR, $path);

                if (!($zip->addFile(realpath($path), $localName))) {
                    Globals::log("Could not add file: $path");
                }
            }
            Globals::log($zip);
            $zip->close();

            return $zipFile;
        } else {
            Globals::log($res);
        }
        
        return false;
    }

    /**
     *
     * @return array|true 
     * @author Nguyen Huu Tam
     * @since 2012/10/10
     */
    public function checkFileUpload() 
    {
        $upload = new Zend_File_Transfer();
        // Add validate rule
        $upload->addValidator('Size', false, $this->_uploadConfig->maxSize);
        // Set folder to save uploaded file
        $upload->setDestination($this->_pathSaveFile);
        
        $files = $upload->getFileInfo();
        // Remove csv input data post
        if (isset($files['page_csv'])) {
            unset($files['page_csv']);
        }
        
        $errors = array();
        foreach ($files as $file => $info) {
            if (!$upload->isUploaded($file)) {
                $errors['no_file'] = $this->msgConfig->E304_Require_PosFile;
                continue;
            }

            if (!$upload->receive($file)) {
                $messages = $upload->getMessages();
                Globals::log($messages);
                $errors['invalid'][] = sprintf($this->msgConfig->E304_Failed_Upload, $info['name']);
            }
        }

        return (count($errors) ? $errors : true);
    }
   
    
    /**
     * Get POS temporary folder
     *
     * @return string 
     * @author Nguyen Huu Tam
     * @since 2012/10/10
     */
    static public function getPosTmpFolder()
    {
        $tmpPath = Globals::getTmpUploadFolder() 
                . self::POS_TMP_FOLDER 
                . DIRECTORY_SEPARATOR;
        
        if (!is_dir($tmpPath)) {
            mkdir($tmpPath, 0777);
        }
        
        return $tmpPath;
    }    
	
    /**
     * Create download csv file
     * 
     * @param type $object
     * @param string $filePath 
     * @param string $fileName 
     * @author Nguyen Huu Tam
     * @since 2012/10/24
     */
    static public function createDownloadCsvFile($object, $filePath, $fileName, $charset = 'UTF-8', $newHeader = null)
    {
        $file = realpath($filePath);
        if (file_exists($file)) {
            $fileDown = file_get_contents($file);
			//change header
			if (!is_null($newHeader) && is_array($newHeader)) {
				$data = explode("\n", $fileDown);
				//add header
				$newData = implode(',', $newHeader);
				$header = explode(',', $data[0]);
				$keyHeader = array();
				foreach ($header as $k => $value) {
					if (isset($newHeader[$value])) {
						$keyHeader[$value] = $k;
					}
				}
				for ($i = 1; $i < count($data); $i++) {
					$row = explode(',', $data[$i]);
					if (count($row) !== count($header)) {
						continue;
					}
					$newRow = array();
					foreach ($newHeader as $key => $title) {
						$newRow[] = $row[$keyHeader[$key]];
					}
					//add row
					$newData .= "\n" . implode(',', $newRow);
				}
				
				$fileDown = $newData;
			}
            
            //---convert charset;
            $fileDown = mb_convert_encoding($fileDown, $charset, "UTF-8");
            
            $object->getResponse()->clearBody();
            
            $object->getResponse()->setHeader('Content-Type', 'application/octet-stream', true)
                ->setHeader('Content-Transfer-Encoding', 'binary')
                ->setHeader('Expires', 0)
                ->setHeader('Content-Disposition', 'attachment; filename=' . $fileName);
            
            $object->getResponse()->setBody($fileDown);
        }
    }
	
	/**
     * Create download csv file
     * 
     * @param type $object
     * @param string $filePath 
     * @param string $fileName 
     * @author Nguyen Huu Tam
     * @since 2012/10/24
     */
    static public function createDownloadCsvFileWithOrder($model, $object, $filePath, $fileName, $charset = 'UTF-8', $newHeader = null)
    {
		$dataCSV = $model->getData();
		Application_Model_Entity::natksort($dataCSV);
		
        $file = realpath($filePath);
        if (file_exists($file)) {
            $fileDown = file_get_contents($file);
			$data = explode("\n", $fileDown);
			//change header
			if (!is_null($newHeader) && is_array($newHeader)) {				
				//add header
				$newData = implode(',', $newHeader);
				$header = explode(',', $data[0]);
				$keyHeader = array();
				foreach ($header as $k => $value) {
					if (isset($newHeader[$value])) {
						$keyHeader[$value] = $k;
					}
				}
				foreach ($dataCSV as $row) {
					if (count($row) !== count($header)) {
						continue;
					}
					$newRow = array();
					foreach ($newHeader as $key => $title) {
						$newRow[] = $row[$key];
					}
					//add row
					$newData .= "\n" . implode(',', $newRow);
				}
				
				$fileDown = $newData;
			} else {
				$newHeader = explode(',', $data[0]);
				$newData = $data[0];
				foreach ($dataCSV as $row) {
					$newRow = array();
					foreach ($newHeader as $key) {
						$newRow[] = $row[$key];
					}
					//add row
					$newData .= "\n" . implode(',', $newRow);
				}
				
				$fileDown = $newData;
			}
            
            //---convert charset;
            $fileDown = mb_convert_encoding($fileDown, $charset, "UTF-8");

            $object->getResponse()->clearBody();
            
            $object->getResponse()->setHeader('Content-Type', 'application/octet-stream', true)
                ->setHeader('Content-Transfer-Encoding', 'binary')
                ->setHeader('Expires', 0)
                ->setHeader('Content-Disposition', 'attachment; filename=' . $fileName);
            
            $object->getResponse()->setBody($fileDown);
        }
    }
}