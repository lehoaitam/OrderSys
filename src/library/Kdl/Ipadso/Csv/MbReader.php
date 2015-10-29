<?php
/**
 * A class that read data in CSV file with specified encode
 * 
 * 
 * @author Nguyen Huu Tam
 * @copyright Kobe Digital Labo, Inc
 * @since 2012/06/27 
 */

class Kdl_Ipadso_Csv_MbReader extends Kdl_Ipadso_Csv_Reader
{
    /**
     * CSV Encoding
     * @var string
     */
    protected $_encoding = 'SJIS';
    
    /**
     * Constructor
     *
     * @param $file CSV full file path
     * @param $encode CSV encoding
     * @return void
     * @author Nguyen Huu Tam
     * @since 2012/06/27
     */
    public function __construct($file, $encode = 'SJIS')
    {
        //mb_internal_encoding($encode);
        if ($encode != '') {
            $this->_encoding = $encode;
        }
        parent::__construct($file);
        
        $colname_tmp = array();
        foreach ($this->_header as $key => $value) {
            //pqbao
            $col_name = mb_convert_encoding($value, 'UTF-8', $this->_encoding);
            if(isset($colname_tmp[$col_name])){
                $col_name .= count($colname_tmp[$col_name]) + 1;
            }            
            $this->_header[$key] = $col_name;
            $colname_tmp[$col_name][] = $col_name;
        }        
    }
    
    /**
     * @return void
     * @author Nguyen Huu Tam
     * @since 2012/06/27
     */
    public function current()
    {
        
        //$data = fgetcsv($this->_handle, self::MAX_SIZE, $this->_delimiter);
        $data = $this->_fgetcsv($this->_handle, self::MAX_SIZE, $this->_delimiter);
        if (is_array($data)) {
            while (list($index, $value) = each($data)) {
                if (isset($this->_header[$index])) {
                    $this->_current[$this->_header[$index]] = mb_convert_encoding($value, 'UTF-8', $this->_encoding);
                }
            }
            $this->_row++;
            return $this->_current;
        }
        return array();
    }
}