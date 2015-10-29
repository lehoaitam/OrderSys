<?php
/**
 * A class that write data to CSV file
 * 
 * 
 * @author Nguyen Huu Tam
 * @copyright Kobe Digital Labo, Inc
 * @since 2012/06/27 
 */
class Kdl_Ipadso_Csv_Writer extends Kdl_Ipadso_Csv_Reader
{
    protected $_delimiter = ',';
    protected $_csvFileName = '';
    public $msgConfig = null;
    
    /**
     * Contructor
     *
     * @param type $file csv file path.
     * @param type $hasHeader Has CSV header
     * @return void
     * @throw Exception If file not found.
     */
    public function __construct($file, $hasHeader = true)
    {
        $this->msgConfig = Zend_Registry::get('MsgConfig');
        $this->_csvFileName = $file;
    }
    
    /**
     * Write row data to source file.
     *
     * @param array $data
     * @param string $mode appen|write
     * @throws Exception 
     */
    public function writeRow($data, $mode, $header = null)
    {
        $this->_handle = fopen($this->_csvFileName, $mode);
        
        if (!$this->_handle) {
            require_once 'Kdl/Ipadso/Csv/Exception.php';
            throw new Kdl_Ipadso_Csv_Exception(
                sprintf($this->msgConfig->C001_CanNotReadFile, $this->_csvFileName)
            );
        }
        
        // Add header
        $hasHeader = false;
        if (($header !== null) && (is_array($header))) {
            $hasHeader = true;
            $this->fputcsv($this->_handle, $header, $this->_delimiter);
        }
        
        foreach ($data as $dataRow) {
            if (!empty($dataRow)) {
                if ($hasHeader) {
                    // Sort data by header
                    $dataRow = self::sortArrayByArray($dataRow, $header);
                }
                $this->fputcsv($this->_handle, $dataRow, $this->_delimiter);
            }
        }
        
        fclose($this->_handle);
    }
    
    public function fputcsv(&$handle, $fields = array(), $delimiter = ',', $enclosure = '') {
        $str = '';
        $escape_char = '\\';
        foreach ($fields as $value) {
            if (strpos($value, $delimiter) !== false ||
                @strpos($value, $enclosure) !== false ||
                strpos($value, "\n") !== false ||
                strpos($value, "\r") !== false ||
                strpos($value, "\t") !== false ||
                strpos($value, ' ') !== false) {
                $str2 = $enclosure;
                $escaped = 0;
                $len = strlen($value);
                for ($i=0;$i<$len;$i++) {
                    if ($value[$i] == $escape_char) {
                        $escaped = 1;
                    } else if (!$escaped && $value[$i] == $enclosure) {
                        $str2 .= $enclosure;
                    } else {
                        $escaped = 0;
                    }
                        $str2 .= $value[$i];
                }
                $str2 .= $enclosure;
                $str .= $str2.$delimiter;
            } else {
                $str .= $enclosure.$value.$enclosure.$delimiter;
            }
        }
        $str = substr($str,0,-1);
        $str .= "\n";

        return fwrite($handle, $str);
    }
    
    /**
     * Sort an array by another array values
     * 
     * @param array $toSort
     * @param array $sortByValuesAsKeys
     * @return array 
     * @author Nguyen Huu Tam
     * @since 2012/09/06
     */
    static function sortArrayByArray(array $toSort, array $sortByValuesAsKeys)
    {
        $commonKeysInOrder = array_intersect_key(array_flip($sortByValuesAsKeys), $toSort);
        $commonKeysWithValue = array_intersect_key($toSort, $commonKeysInOrder);
        $sorted = array_merge($commonKeysInOrder, $commonKeysWithValue);
        return $sorted;
    }
}