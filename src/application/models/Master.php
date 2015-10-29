<?php
/**
 * Create class to create folder and unzip file from admin.csv
 *
 * @author Nguyen Thi Tho
 * @copyright Kobe Digital Labo, Inc
 * @since 2012/06/27
 */

class Application_Model_General 
{

    /**
     * Get path file zip
     * @return path
     */
    public function getPathFileZip($url)
    {
        $arr_url   = parse_url($url);
        $path      = str_replace($arr_url['host'],'',$_SERVER['DOCUMENT_ROOT']);
        return $path.$arr_url['path'];
    }

    /**
     * Create folder to contain zip file that is unzip from admin.csv
     * @return path
     */
    public function createFolder($name, $chmod = 0777)
    {
        $path = realpath(APPLICATION_PATH . '/../data/');
        chown($path, 775);
        mkdir($path."/".$name, $chmod);
       return(realpath(APPLICATION_PATH . '/../data/'.$name.'/'));
    }
    /**
     * Unzip file
     * @return bool
     */
    public function unZipFile($url,$zip_name)
    {
        if (realpath(APPLICATION_PATH . '/../data/'.$zip_name.'/')) {
             rmdir(realpath(APPLICATION_PATH . '/../data/'.$zip_name.'/'));
        }
        $path  = $this->createFolder($zip_name);
        $file  = $this->getPathFileZip($url);
        $zip   = new ZipArchive;
        $res   = $zip->open($file);
        //echo $path;exit;
        if ($res === TRUE) {
            $zip->extractTo($path);
            $zip->close();
            return 1;
        } else {
            return 0;
        }
    }

}