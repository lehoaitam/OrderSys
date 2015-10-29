<?php
/**
 * A class that read data in CSV file
 * 
 * 
 * @author Nguyen Huu Tam
 * @copyright Kobe Digital Labo, Inc
 * @since 2012/06/27 
 */

class Kdl_Ipadso_Csv_Reader
{
    const FILE_MODE_READ    = 'rb';
    const FILE_MODE_WRITE   = 'wb';
    const FILE_MODE_APPEND  = 'ab';
    
    //Maximumn row length size
    const MAX_SIZE = 4096;
    
    /**
     * File resource handle
     *
     * @var resource
     */
    protected $_handle = null;
    
    /**
     * Current row's data
     *
     * @var array
     */
    protected $_current = null;
    
    /**
     * Current row number
     *
     * @var int
     */
    protected $_row = 0;
    
    /**
     * Delimiter of CSV field.
     *
     * @var string
     */
    protected $_delimiter = ',';
    
    /**
     * Header columns.
     *
     * @var array
     */
    protected $_header = array();
    
    protected $_hasHeader = true;
    
    public $msgConfig = null; 
    
    /**
     * All collection data array
     * Used for getData method
     *
     * @var array
     */
    protected $_data = null;
    
    protected function _init()
    {
        $this->msgConfig = Zend_Registry::get('MsgConfig');
    }

    /**
     * Contructor
     *
     * @param string $file csv file path.
     * @param string $hasHeader Has CSV header
     * @return void
     * @throw Exception If file not found.
     */
    public function __construct($file, $hasHeader = true)
    {
        $this->_init();
        if (file_exists($file)) {
            $this->_fileInfo = $this->analyseFile($file);
            $this->_delimiter = $this->_fileInfo['delimiter']['value'];
        
            $this->_handle = fopen($file, 'r');
            
            if (!$this->_handle) {
                require_once 'Kdl/Ipadso/Csv/Exception.php';
                throw new Kdl_Ipadso_Csv_Exception(
                    sprintf($this->msgConfig->C001_CanNotReadFile, $file)
                );
            }
            
            $this->_hasHeader = $hasHeader;
            if ($hasHeader == true && $this->next()) {
                //$this->_header = fgetcsv($this->_handle, self::MAX_SIZE, $this->_delimiter);
                $this->_header = $this->_fgetcsv($this->_handle, self::MAX_SIZE, $this->_delimiter);
                $this->rewind();
            }
        } else {
            require_once 'Kdl/Ipadso/Csv/Exception.php';
            throw new Kdl_Ipadso_Csv_Exception(
                sprintf($this->msgConfig->C000_FileNotFound, $file)
            );
        }
    }
    
    /**
     * @see Iterator::next()
     */
    public function next()
    {
        return !feof($this->_handle);
    }
    
    /**
     * @see Iterator::current()
     */
    public function current()
    {
        //$data = fgetcsv($this->_handle, self::MAX_SIZE, $this->_delimiter);
        $data = $this->_fgetcsv($this->_handle, self::MAX_SIZE, $this->_delimiter);
        if (is_array($data)) {
            if ($this->hasHeader()) {
                while (list($index, $value) = each($data)) {
                    $this->_current[$this->_header[$index]] = $value;
                }
            } else {
                $this->_current = $data;
            }
            $this->_row++;
            return $this->_current;
        }
        return array();
    }
    
    /**
     * @see Iterator::rewind()
     */
    public function rewind()
    {
        $this->_row = 0;
        rewind($this->_handle);
    }
     public function getData()
    {
        if ($this->_data === null) {
            $this->_data = $this->_fetchData();
        }
        return $this->_data;
    }

    protected function _fetchData()
    {
        $this->rewind();
        if ($this->hasHeader()) {
            $this->pass(); // pass header
        }
        $currentRow = 0;
        while ($this->next()) {
            
            $this->_data[] = $this->current();
            $currentRow++;
        }
        $this->_totalRecords = $currentRow;

        return $this->_data;
    }
    
    /**
     * Pass to next line and do nothing
     * @return void
     */
    public function pass($e = '"')
    {
        $d = preg_quote($this->_delimiter);
        $e = preg_quote($e);
        $_line = "";
        $eof = false;
        while ($eof != true) {
            $_line .= (empty($length) ? fgets($this->_handle) : fgets($this->_handle, $length));
            $itemcnt = preg_match_all('/'.$e.'/', $_line, $dummy);
            if ($itemcnt % 2 == 0) $eof = true;
        }
    }
    
    /**
     * Check if csv has header
     * @param boolean $value set value
     * @return boolean
     */
    public function hasHeader($value = NULL)
    {
        if ($value != NULL) {
            $this->_hasHeader = $value;
        }
        
        return $this->_hasHeader;
    }
    
    /**
     * Get csv header
     * @return array
     */
    public function getHeader()
    {
        return $this->_header;
    }
    
    /**
     * PHP fgetcsv replacement.
     *
     * @param $handle
     * @param $length
     * @param $d
     * @param $e
     * @return array
     */
    protected function _fgetcsv(&$handle, $length = null, $d = ',', $e = '"') 
    {
        $d = preg_quote($d);
        $e = preg_quote($e);
        $_line = "";
        $eof = false;
        while ($eof != true) {
            $_line .= (empty($length) ? fgets($handle) : fgets($handle, $length));
            $itemcnt = preg_match_all('/'.$e.'/', $_line, $dummy);
            if ($itemcnt % 2 == 0) $eof = true;
        }
        $_csv_line = preg_replace('/(?:\\r\\n|[\\r\\n])?$/', $d, trim($_line));
        $_csv_pattern = '/('.$e.'[^'.$e.']*(?:'.$e.$e.'[^'.$e.']*)*'.$e.'|[^'.$d.']*)'.$d.'/';
        preg_match_all($_csv_pattern, $_csv_line, $_csv_matches);
        $_csv_data = $_csv_matches[1];
        for($_csv_i=0;$_csv_i<count($_csv_data);$_csv_i++){
            $_csv_data[$_csv_i]=preg_replace('/^'.$e.'(.*)'.$e.'$/s','$1',$_csv_data[$_csv_i]);
            $_csv_data[$_csv_i]=str_replace($e.$e, $e, $_csv_data[$_csv_i]);
        }
        return empty($_line) ? false : $_csv_data;
    }
    
    /**
     * Analyse CSV file.
     *
     * @param string $file The csv file path.
     * @param int $capture_limit_in_kb
     * @return array
     */
    public function analyseFile($file, $capture_limit_in_kb = 10)
    {
        // capture starting memory usage
        $output['peak_mem']['start'] = memory_get_peak_usage(true);

        // log the limit how much of the file was sampled (in Kb)
        $output['read_kb'] = $capture_limit_in_kb;

        // read in file
        $fh = fopen($file, 'r');
            $contents = fread($fh, ($capture_limit_in_kb * 1024)); // in KB
        fclose($fh);

        // specify allowed field delimiters
        $delimiters = array(
            'comma'     => ',',
            'semicolon' => ';',
            'tab'       => "\t",
            'pipe'      => '|',
            'colon'     => ':'
        );

        // specify allowed line endings
        $line_endings = array(
            'rn'        => "\r\n",
            'n'         => "\n",
            'r'         => "\r",
            'nr'        => "\n\r"
        );

        // loop and count each line ending instance
        foreach ($line_endings as $key => $value) {
            $line_result[$key] = substr_count($contents, $value);
        }

        // sort by largest array value
        asort($line_result);

        // log to output array
        $output['line_ending']['results']   = $line_result;
        $output['line_ending']['count']     = end($line_result);
        $output['line_ending']['key']       = key($line_result);
        $output['line_ending']['value']     = $line_endings[$output['line_ending']['key']];
        $lines = explode($output['line_ending']['value'], $contents);

        // remove last line of array, as this maybe incomplete?
        array_pop($lines);

        // create a string from the legal lines
        $complete_lines = implode(' ', $lines);

        // log statistics to output array
        $output['lines']['count']   = count($lines);
        $output['lines']['length']  = strlen($complete_lines);

        // loop and count each delimiter instance
        foreach ($delimiters as $delimiter_key => $delimiter) {
            $delimiter_result[$delimiter_key] = substr_count($complete_lines, $delimiter);
        }

        // sort by largest array value
        asort($delimiter_result);

        // log statistics to output array with largest counts as the value
        $output['delimiter']['results'] = $delimiter_result;
        $output['delimiter']['count']   = end($delimiter_result);
        $output['delimiter']['key']     = key($delimiter_result);
        $output['delimiter']['value']   = $delimiters[$output['delimiter']['key']];

        // capture ending memory usage
        $output['peak_mem']['end'] = memory_get_peak_usage(true);
        return $output;
    }
    
    
    /**
     *
     * @param array $row
     * @return array
     * @author Nguyen Huu Tam
     * @since 2012/10/22 
     */
    public function checkDataRow($row)
    {
        $newline = array("\r", "\r\n", "\n");
        foreach ($row as $key => $value) {
            if (empty($value)) continue;
            
            if (strpos($value, ',') !== false) {
                $row['invalid'] = $key;
                break;
            }
            // Replace new line by space
            $row[$key] = (string)str_replace($newline, ' ', $value);
        }
        
        return $row;
    }

}