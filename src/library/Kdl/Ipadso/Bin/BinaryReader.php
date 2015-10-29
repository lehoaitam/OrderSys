<?php
/**
 * Class BinaryReader
 *
 * @author Nguyen Huu Tam
 * @copyright Kobe Digital Labo, Inc
 * @since 2012/09/08
 */

class BinaryReader
{
    const BYTE_BLOCK = 5;
    
    const MENU_ITEM_PER_BYTES       = 147;
    const CATE_1_ITEM_PER_BYTES     = 50;
    const CATE_2_ITEM_PER_BYTES     = 75;
    const SUBCOMMENT_ITEM_PER_BYTES = 53;

    const MENU_TYPE         = 'menu';
    const CATE_1_TYPE       = 'category1';
    const CATE_2_TYPE       = 'category2';
    const SUBCOMMENT_TYPE   = 'subcomment';
    
    protected $_byteTypes = null;

    protected static $_instance = null;

    /**
     * Construct
     * 
     * @author Nguyen Huu Tam
     * @since 2012/09/08
     */
    public function __construct()
    {
        $this->_byteTypes = array(
            'byte', 'name', 'ascii', 'pk', 'spk', 'bit', 'bin', 'word', 'short', 'dword', 'long'
        );
        
        $this->msgConfig = Zend_Registry::get('MsgConfig');
    }

    /**
     * Returns an instance of BinaryReader
     *
     * Singleton pattern implementation
     *
     * @return BinaryReader Provides a fluent interface
     * @author Nguyen Huu Tam
     * @since 2012/09/08
     */
    public static function getInstance()
    {
        if (null === self::$_instance) {
            self::$_instance = new self();
        }

        return self::$_instance;
    }
    
    /**
     * Read binary file
     * 
     * @param string $binFile
     * @return array
     * @author Nguyen Huu Tam
     * @since 2012/09/08
     */
    public function readBinFile($binFile, $type)
    {
        $buffer = file_get_contents($binFile);
        $length = filesize($binFile);

        if (!$buffer || !$length) {
            throw new Exception(sprintf($this->msgConfig->C001_CanNotReadFile, $binFile));
        }

        $binData = array();
        for ($i = 0; $i < $length; $i++) {
            $binData[] =  sprintf("%08b", ord($buffer[$i]));
        }
        
        switch ($type) {
            case self::MENU_TYPE:
                $itemBytes = self::MENU_ITEM_PER_BYTES;
                $byteFormat = $this->_getMenuByteFormat();
                break;
            case self::CATE_1_TYPE:
                $itemBytes = self::CATE_1_ITEM_PER_BYTES;
                $byteFormat = $this->_getCate1ByteFormat();
                break;
            case self::CATE_2_TYPE:
                $itemBytes = self::CATE_2_ITEM_PER_BYTES;
                $byteFormat = $this->_getCate2ByteFormat();
                break;
            case self::SUBCOMMENT_TYPE:
                $itemBytes = self::SUBCOMMENT_ITEM_PER_BYTES;
                $byteFormat = $this->_getSubcommentByteFormat();
                break;
            default:
                throw new Exception(
                    sprintf($this->msgConfig->E000_NotExist_Ctype, $matches['ctype'])
                );
                break;
        }
        
        $items = array_chunk($binData, $itemBytes);
        foreach ($items as $item) {
            $data[] = $this->prepareData($item, $byteFormat);
        }
        
        return $data;
    }

    /**
     * Get menu byte format
     * 
     * @return array
     * @author Nguyen Huu Tam
     * @since 2012/08/10
     */
    protected function _getMenuByteFormat()
    {
        return array(
            'menu_code:pk:2',
            'link_dp_code:pk:1',
            'plu_code:byte:13',
            'kana:name:20',
            'kanji_1:name:20',
            'kanji_2:name:20',
            'kanji_3:name:10',
            'unit_price:spk:5',
            'sub_price:spk:5',
            'price:spk:5',
            'status_1:bit:1',
            'status_2:bit:1',
            'status_3:bit:1',
            'kp_status:bit:4',
            'kp:bin:1',
            'ccp_status:bit:1',
            'status:bin:1',
            'scp_status1:bit:1',
            'scp_status2:bit:1',
            'scp_status3:bit:1',
            'scp_status4:bit:1',
            'scp_status5:bit:1',
            'scp_status6:bit:1',
            'scp_status7:bit:1',
            'scp_status8:bit:1',
            'scp_status9:bit:1',
            'scp_status10:bit:1',
            'scp_status11:bit:1',
            'scp_status12:bit:1',
            'kd:bit:1',
            'htl:bin:1',
            'kd_1:bin:1',
            'kd_2:bin:1',
            'point_daily:spk:5',
            'amount_daily:spk:5',
            'point_total:spk:5',
            'amount_total:spk:5'
        );
    }
    
    /**
     * Get category 1 byte format
     * 
     * @return array
     * @author Nguyen Huu Tam
     * @since 2012/08/10
     */
    protected function _getCate1ByteFormat()
    {
        return array(
            'gp_code:byte:2',
            'gp:name:20',
            'preliminary:ascii:28'
        );
    }
    
    /**
     * Get category 2 byte format
     * 
     * @return array
     * @author Nguyen Huu Tam
     * @since 2012/08/10
     */
    protected function _getCate2ByteFormat()
    {
        return array(
            'dp_code:pk:1',
            'link_dp_code:pk:1',
            'kana:name:16',
            'kanji:name:16',
            'price:spk:5',
            'status_1:bit:1',
            'status_2:bit:1',
            'status_3:bit:1',
            'kp_status:bit:4',
            'kp:bin:1',
            'ccp_status:bit:1',
            'status:bin:1',
            'kd:bit:1',
            'htl:bin:1',
            'kd_1:bin:1',
            'kd_2:bin:1',
            'event:spk:2',
            'point_daily:spk:5',
            'amount_daily:spk:5',
            'point_total:spk:5',
            'amount_total:spk:5'
        );
    }
    
    /**
     * Get subcomment byte format
     * 
     * @return array
     * @author Nguyen Huu Tam
     * @since 2012/08/10
     */
    protected function _getSubcommentByteFormat()
    {
        return array(
            'no:bin:1',
            'guidance:name:20',
            'menu1:pk:2',
            'menu2:pk:2',
            'menu3:pk:2',
            'menu4:pk:2',
            'menu5:pk:2',
            'menu6:pk:2',
            'menu7:pk:2',
            'menu8:pk:2',
            'menu9:pk:2',
            'menu10:pk:2',
            'menu11:pk:2',
            'menu12:pk:2',
            'menu13:pk:2',
            'menu14:pk:2',
            'menu15:pk:2',
            'menu16:pk:2'
        );
    }
   
    /**
     * Save data to file
     * 
     * @param array $data
     * @param string $filePath 
     * @author Nguyen Huu Tam
     * @since 2012/09/08
     */
    public static function saveData2File($data, $filePath)
    {
        file_put_contents($filePath, print_r($data, true));
    }

    /**
     * 
     * 
     * @param array $item
     * @return array 
     * @author Nguyen Huu Tam
     * @since 2012/09/08
     */
    public function prepareData($item, $byteFormat)
    {
        $data = array();
        
        $pattern = '/(?P<key>\w+):(?P<ctype>\w+):(?P<byte>\d+)/';
        $offset = 0;
        foreach ($byteFormat as $key => $format) {
            if (preg_match($pattern, $format, $matches)) {
                // If ctype no exist
                if (!in_array($matches['ctype'], $this->_byteTypes)) {
                    throw new Exception(
                        sprintf($this->msgConfig->E000_NotExist_Ctype, $matches['ctype'])
                    );
                }
                
                // Set method name by the ctype
                $method = '_set' . ucfirst($matches['ctype']);
                // Call the method
                $binData = call_user_func_array(
                    array(self::getInstance(), $method),
                    array($item, $offset, $matches['byte'])
                );
                $offset += $matches['byte'];
                $binData = mb_convert_encoding($binData, 'UTF-8', 'SJIS');
                
                $data[$matches['key']] = trim($binData);
            }
        }
        
        return $data;
    }
    
    /**
     * Convert binary data to hex data
     * 
     * @param array $item
     * @param int $offset
     * @param int $byte
     * @return array 
     * @author Nguyen Huu Tam
     * @since 2012/09/08
     */
    protected function _convertBin2Hex($item, $offset, $byte)
    {
        $data = array_slice($item, $offset, $byte);
        array_walk(
            $data,
            create_function(
                '&$val',
                '$val = base_convert($val, 2, 16);'
            )
        );
                
        return $data;
    }
    
    /**
     * Convert binary data to octal data
     * 
     * @param array $item
     * @param int $offset
     * @param int $byte
     * @return array 
     * @author Nguyen Huu Tam
     * @since 2013/02/19
     */
    protected function _convertBin2Oct($item, $offset, $byte)
    {
        $data = array_slice($item, $offset, $byte);
        array_walk(
            $data,
            create_function(
                '&$val',
                '$val = base_convert($val, 2, 10);'
            )
        );
                
        return $data;
    }

    /**
     * Byte data
     * 
     * @param array $item
     * @param int $offset
     * @param int $byte
     * @return string 
     * @author Nguyen Huu Tam
     * @since 2012/09/08
     */
    protected function _setByte($item, $offset, $byte)
    {
        return $this->_setName($item, $offset, $byte);
    }
    
    /**
     * Name data
     * 
     * @param array $item
     * @param int $offset
     * @param int $byte
     * @return string 
     * @author Nguyen Huu Tam
     * @since 2012/09/08
     */
    protected function _setName($item, $offset, $byte)
    {
        $data = $this->_convertBin2Hex($item, $offset, $byte);
        $str = pack('H*', implode('', $data));
        
        return $str;
    }
    
    /**
     * ASCII data
     *
     * @param array $item
     * @param int $offset
     * @param int $byte
     * @return string 
     * @author Nguyen Huu Tam
     * @since 2012/09/08
     */
    protected function _setAscii($item, $offset, $byte)
    {
        return $this->_setName($item, $offset, $byte);
    }
    
    /**
     * Pack data
     * 
     * @param array $item
     * @param int $offset
     * @param int $byte
     * @return string 
     * @author Nguyen Huu Tam
     * @since 2012/09/08
     */
    protected function _setPk($item, $offset, $byte)
    {
        $data = $this->_convertBin2Hex($item, $offset, $byte);
        array_walk(
            $data,
            create_function(
                '&$val',
                '$val = str_pad($val, 2 , 0, STR_PAD_LEFT);'
            )
        );
        
        return implode('', $data);
    }
    
    /**
     * Sign pack data
     * 
     * @param array $item
     * @param int $offset
     * @param int $byte
     * @return string 
     * @author Nguyen Huu Tam
     * @since 2012/09/08
     * @modified 2013/02/19
     */
    protected function _setSpk($item, $offset, $byte)
    {
        $data = $this->_convertBin2Hex($item, $offset, $byte);
        
        $sign = array_pop($data);
        $number = 0;
        
        for ($i = 0; $i < count($data); $i++) {
            $tmp = str_pad($data[$i], $byte-$i, '0');
            $number += $tmp;
        }

        if (strcasecmp($sign, 'c')) {
            $number = -$number;
        }

        return number_format(floatval($number), 2);
    }
    
    /**
     * Bit data
     * 
     * @param array $item
     * @param int $offset
     * @param int $byte
     * @return string 
     * @author Nguyen Huu Tam
     * @since 2012/09/08
     */
    protected function _setBit($item, $offset, $byte)
    {
        $data = $this->_convertBin2Oct($item, $offset, $byte);

        return intval(implode('', $data));
    }
    
    /**
     * Binary data
     * 
     * @param array $item
     * @param int $offset
     * @param int $byte
     * @return string 
     * @author Nguyen Huu Tam
     * @since 2012/09/08
     */
    protected function _setBin($item, $offset, $byte)
    {
        $data = $this->_convertBin2Hex($item, $offset, $byte);
        $str = implode('', $data);

        return base_convert($str, 16, 10);
    }
    
    protected function _setWord($item, $offset, $byte) {}
    protected function _setShort($item, $offset, $byte) {}
    protected function _setDword($item, $offset, $byte) {}
    protected function _setLong($item, $offset, $byte) {}
}