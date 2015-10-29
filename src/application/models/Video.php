<?php
/**
 * Class Video
 *
 * @author Nguyen Huu Tam
 * @copyright Kobe Digital Labo, Inc
 * @since 2012/07/20
 */

class Application_Model_Video
{
	protected $_path    = null;
    protected $_handle  = null;

    /**
     * 
     * 
     * @author Nguyen Huu Tam
     * @since 2013/03/11
     */
    public function __construct()
    {
        $this->msgConfig = Zend_Registry::get('MsgConfig');
        $this->videoConfig = Globals::getApplicationConfig('video');
        
        $session = Globals::getSession();
        $dataConf = Globals::getApplicationConfig('data');
        
        $this->_dataFolderPath  = $session->company_link . '/video/';
    }
    
    
    /**
     * Get stored menuset folder
     * 
     * @return type
     * @author Nguyen Huu Tam
     * @since 2013/03/11
     */
    public function getFolderPath()
    {
        if (!is_dir($this->_dataFolderPath)) {
            mkdir($this->_dataFolderPath);
        }
        
        return $this->_dataFolderPath;
    }
    
    
    /**
     * Get all menusets
     * 
     * @return array
     * @author Nguyen Huu Tam
     * @since 2013/03/11
     */
    public function getData()
    {
        $list = array();
        // Get all video files
        $videos = @scandir($this->getFolderPath());
        if ($videos) {
            foreach ($videos as $video) {
                if ($video === '.' || $video === '..') {
                    continue;
                }
                $list[] = $video;
            }
        }

        return $list;
    }
    
    
    /**
     * Get list of videos
     * 
     * @param array $postData
     * @return array
     * @author Nguyen Huu Tam
     * @since 2013/03/11
     */
    public function getList($postData = null)
    {
        $list = array();
        $path = $this->getFolderPath();
        $videos = $this->getData();
        foreach ($videos as $video) {
            $fileInfo = stat($path.$video);
            $size = self::getFileSize($fileInfo['size']);
            $list[] = array(
                'name' => $video,
                'size' => $size,
                "date" => date('Y/m/d H:i:s', $fileInfo['mtime'])
            );
        }
        
        return array(
            'total' => count($list),
            'rows'  => $list
        );
    }
	
	/**
     * Get list of videos
     * 
     * @param string $keyword
     * @return array
     * @author Nguyen Quang Trung
     * @since 2014/05/09
     */
    public function getFilteredList($keyword = null)
    {
        $path = $this->getFolderPath();
        $data = $this->getData();
		$videos = array();
        foreach ($data as $video) {
			if (strlen($keyword) > 0 && !preg_match('/' . $keyword . '/i', $video, $matches)) {
				continue;
			}
            $videos[] = array('id' => '', 'code' => '', 'name' => $video, 'type' => 'video');
        }
        
        return $videos;
    }
    
    /**
     * Get list of videos
     * 
     * @param array $postData
     * @param array $page
     * @param array $limit
     * @return array
     * @author Phan Quoc Bao
     * @since 2014/04/24
     */
    public function getDataViewList($postData, $page, $limit = NULL)
    {
        $list = array();
        $path = $this->getFolderPath();
        $videos = $this->getData();
        $stt = 1;
        foreach ($videos as $video) {
            $fileInfo = stat($path.$video);
            $size = self::getFileSize($fileInfo['size']);
            $list[] = array(
                'name' => $video,
                'size' => $size,
                "date" => date('Y/m/d H:i', $fileInfo['mtime'])
            );
            $stt++;
        }
        $data = $list;
        asort($data);
        $keySort = array('name','size','date');
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
    
    
    
    public function upload($postData)
    {
        $session = Globals::getSession();
        $files = null;
        $messages = array();
        
        if (isset($postData['videos']['name'])
            && (!empty($postData['videos']['name']))
        ) {
            $files = $postData['videos']['name'];
        }
        //---check no file
        if(isset($files[0]) && $files[0] == ''){
            return array($this->msgConfig->E300_RequireFileUpload);
        }

        //check if existed
        foreach ($files as $key => $file) {
                if (file_exists($session->company_link . '/video/' . $file) && $session->action != 'edit') {
                    unset($files[$key]);
                    $messages[] = sprintf($this->msgConfig->E701_Error_Upload, $file );
                }
        }
        $adapter = new Zend_File_Transfer_Adapter_Http();
        if ($adapter->isUploaded($files)) { 
            // Set destination folder
            $adapter->setDestination($this->getFolderPath());
            
            // File size validate
            $sizeValidator = Application_Model_Video::setValidatorMessage(
                'Zend_Validate_File_Size',
                array('max' => $this->videoConfig->upload->maxSize)
            );

            // Extension validate
            $allowExtensions = explode(',', $this->videoConfig->upload->allowExtensions);
            $extensionValidator = Application_Model_Video::setValidatorMessage(
                'Zend_Validate_File_Extension',
                $allowExtensions
            );
            // Count Validate
            $countValidator = Application_Model_Video::setValidatorMessage(
                'Zend_Validate_File_Count',
                array('max' => $this->videoConfig->upload->maxUpload)
            );
            $fileInfo = $adapter->getFileInfo();
            foreach ($fileInfo as $file => $info) {
//                if ($info['error']) {
//                    $messages[] = $info['name'].$this->msgConfig->E701_Error_Upload;
//                    continue;
//                }
                $adapter->addValidator($extensionValidator, false)
                    ->addValidator($sizeValidator, false)
                    ->addValidator($extensionValidator, false)
                    ->addValidator($countValidator, false);
                if ($adapter->isValid($file)) {
                    if( $session->action == 'edit' ){
                        //--delete file old
                        $this->deleteByKey($session->nameVideo);
                    }
                    $adapter->receive($file);
                    
                    // Copy video to preview folder
                    $videoPreviewPath = self::getPreviewPath();
                    if (!file_exists($videoPreviewPath)) {
                        mkdir($videoPreviewPath, 0777, true);
                    }
                    
                    $from = $this->getFolderPath() . $info['name'];
                    $to = $videoPreviewPath . $info['name'];              
                    
                    if (!copy($from, $to)) {
                        Globals::log(sprintf('Cannot copy video from %s to %s', $from, $to));
                    }

                } else {
                    foreach ($adapter->getMessages() as $message) {
                        $messages[] = $message;
                    }
                }
            }
        }
        return $messages;
    }
    
    // Delete Video
    public function deleteByKey($video)
    {
        try {
            unlink($this->getFolderPath() . $video);
            unlink(self::getPreviewPath() . $video);
        } catch (Exception $e) {
            Globals::logException($e);
        }
    }
    
    public static function getFileSize($filesize)
    {
        $s = array('B', 'KB', 'MB', 'GB', 'TB', 'PB');
        $e = floor(log($filesize)/log(1024));
        if ($e == 0 || $e == 1) {
            $format = '%d';
        } else {
            $format = '%.1f';
        }
        $filesize = sprintf($format.$s[$e], ($filesize/pow(1024, floor($e))));
        
        return $filesize;
    }

    /**
     *
     * @param string $validator
     * @param array $options
     * @return \validator 
     * @author Nguyen Huu Tam
     * @since 2012/11/08
     */
    public static function setValidatorMessage($validator, $options)
    {
        $msgConfig = Zend_Registry::get('MsgConfig');
        switch ($validator) {
            case 'Zend_Validate_File_Size':
                $message = array(
                    $validator::TOO_BIG    => $msgConfig->E701_FileSizeTooBig,
                    $validator::NOT_FOUND  => $msgConfig->E701_FileNotFound
                );
                break;
            case 'Zend_Validate_File_ImageSize':
                $message = array(
                    $validator::NOT_DETECTED    => $msgConfig->E701_FileVideoSizeNotDetected,
                    $validator::NOT_READABLE    => $msgConfig->E701_FileNotFound
                );
                break;
            case 'Zend_Validate_File_Extension':
                $message = array(
                    $validator::FALSE_EXTENSION => $msgConfig->E701_FileExtensionFalse,
                    $validator::NOT_FOUND       => $msgConfig->E701_FileNotFound
                );
                break;
            case 'Zend_Validate_File_Count':
                $message = array(
                    $validator::TOO_MANY => $msgConfig->E701_FileCountTooMany
                );
                break;
            default:
                 $message = array();
                break;
        }
        
        $validator = new $validator($options);
        $validator->setMessages($message);
        
        return $validator;
    }
    
    
    public static function getPreviewPath()
    {
        $videoConfig = Globals::getApplicationConfig('video');
        $session = Globals::getSession();
        
        return sprintf(
            $videoConfig->previewFolder,
            $session->company_name,
            $session->company_code
        );
    }
    
    public function getListNameData()
    {
        $list = array();
        // Get all video files
        $videos = @scandir($this->getFolderPath());
        if ($videos) {
            foreach ($videos as $video) {
                if ($video === '.' || $video === '..') {
                    continue;
                }
                $list[] = $video;
            }
        }

        return implode(',', $list);
    }
}
