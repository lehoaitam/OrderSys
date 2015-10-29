<?php
/**
 * A class that transform data in CSV to a set of record,
 * that can be SELECT, INSERT, UPDATE, DELETE in similar to SQL manner
 * 
 * @author Nguyen Huu Tam
 * @copyright Kobe Digital Labo, Inc
 * @since 2012/06/27 
 */

require_once('Reader.php');
require_once('MbReader.php');
require_once('Writer.php');

class Kdl_Ipadso_Csv_RecordSet 
{
    private $_csvFileName = '';
    private $_csvData = null;
    private $_csvReader;
    private $_csvWriter;
    private $_primaryKey = null;
    private $_hasHeader = false;
    private $_csvHeader = array();
    private $_csvEncoding = '';
    private $_totalRecords;
    public $msgConfig = null;
    
    protected function _init()
    {
        $this->msgConfig = Zend_Registry::get('MsgConfig');
    }
    
    /**
     * Contructor
     *
     * @param string $file csv file path.
     * @param string $primaryColumn primary column name
     * @param string $encoding
     * @param booleand $multiByte 
     * @return void
     */
    public function __construct($file, $primaryColumn, $encoding = 'UTF-8', $multiByte = true)
    {
        $this->_init();
        
        $this->_csvFileName = $file;        
        $this->_csvEncoding = $encoding;
        
        if (!empty($primaryColumn) 
            && is_string($primaryColumn)
        ) {
            $this->_primaryKey = $primaryColumn;
            $this->_hasHeader = true;
        }

        if ($multiByte == true) {
            $this->_csvReader = new Kdl_Ipadso_Csv_MbReader($this->_csvFileName, $this->_csvEncoding);
        } else {
            $this->_csvReader = new Kdl_Ipadso_Csv_Reader($this->_csvFileName, $this->_hasHeader);
        }
        
        $this->_checkColumnName($this->_primaryKey);
        $this->_csvHeader = array($this->_csvReader->getHeader());
        $this->getData();
        
        $this->_csvWriter = new Kdl_Ipadso_Csv_Writer($this->_csvFileName);
    }

    /**
     * Check column name in csv header
     * 
     * @param string $columnName column name
     * @return boolean
     * @throws Exception 
     */
    protected function _checkColumnName($columnName)
    {
        if (false === array_search($columnName, $this->_csvReader->getHeader())) {
            require_once 'Kdl/Ipadso/Csv/Exception.php';
            throw new Kdl_Ipadso_Csv_Exception(
                sprintf($this->msgConfig->C004_ColumnNameNotFound, $columnName)
            );
        }
        return true;
    }
    
    /**
     * Check row data format
     * 
     * @param array $data
     * @return array|boolean
     * @throws Exception 
     */
    protected function _checkData($data)
    {
        $result = array();
        if (!empty($data) && is_array($data)) {
            $totalFields = count($this->getHeader());
            foreach ($data as $line => $row) {
                if (is_array($row)
                    && array_key_exists($this->_primaryKey, $row)
                ) {
                    $numOfFields = count($row);
                    // If not enough fields, then add empty values
                    if ($totalFields > $numOfFields) {
                        $keysData = array_slice($this->getHeader(), $numOfFields, $totalFields);
                        $valuesData = array_pad(array(), ($totalFields - $numOfFields), '');
                        $row += array_combine($keysData, $valuesData);
                        
                        $msgLog = "Row with {$this->_primaryKey}[{$row[$this->_primaryKey]}] is not enough data.\n";
                        $msgLog.= "Total fields: $totalFields \n";
                        $msgLog.= "Current row has just $numOfFields field(s)\n";
                        Globals::log($msgLog);
                    }
                    
                    $row = $this->_csvReader->checkDataRow($row);
                    if (isset($row['invalid'])) {
                        $msgLog = sprintf(
                            $this->msgConfig->E501_Error_DataImport, 
                            $row[Application_Model_Index::$idCode], 
                            $row['invalid']
                        );
                        
                        $result['error'][] = $msgLog;
                        Globals::log($msgLog);
                    } else {
                        $result['data'][$row[$this->_primaryKey]] = $row;
                    }
                } else {
                    require_once 'Kdl/Ipadso/Csv/Exception.php';
                    throw new Kdl_Ipadso_Csv_Exception(
                        sprintf($this->msgConfig->C007_NoExist_PrimaryKey, $this->_primaryKey)
                    );
                    return;
                }
            }
            return $result;
        } else {
            require_once 'Kdl/Ipadso/Csv/Exception.php';
            throw new Kdl_Ipadso_Csv_Exception($this->msgConfig->C007_InvalidData);
            return;
        }
    }

    /**
     * Find row by primary key
     * 
     * @param string $primaryValue
     * @return array|boolean 
     */
    public function findRow($primaryValue)
    {
        if (@array_key_exists($primaryValue, $this->_csvData)) {
            return array($primaryValue => $this->_csvData[$primaryValue]);
        } 
        return false;
    }
       
    /**
     * find a row set that filterd by a value of one column 
     * Enter description here ...
     * @param string $columnName
     * @param string|int $columnValue
     * @return array
     */
    public function findRowSetByColumn($columnName, $columnValue)
    {
        $result = array();
        $this->_checkColumnName($columnName);
        if ($this->_csvData) {
            foreach ($this->_csvData as $key => $row) {
                if (empty($row)) continue;
                if ($row[$columnName] == $columnValue) {
                    $result[$key] = $row;
                }
            }
        }
        return $result;
    }
    /**
     * Find some row by some conditions
     *  
     * $args = array (
     * 		'column_name1' => array('equator' => 'searchvalue'),
     * 		'column_name2' => array('equator' => 'searchvalue'),
     * );
     * equator: eq, neq, gt, lt, like 
     * 
     * @param array $args
     * @return array|null 
     */
    public function findRowSet($args)
    {
        $result = null;
        if (!empty($args) && is_array($args)) {
            foreach ($args as $key => $value) {
                $result = $this->_seekRow($key, $value, $result);
            }
        }
        return $result;
    }
    
    protected function _seekRow($columnName, $columnConditon, &$result)
    {
        $this->_checkColumnName($columnName);
        $conditions = array_keys($columnConditon);
		
		foreach ($conditions as $condition) {
			$value = $columnConditon[$condition];
			$res = array();
			
			if (is_null($result)) {
				$result = $this->_csvData;
			}

			switch ($condition) {
				case 'eq':
				case '=':
					foreach ($result as $key => $row) {
						if (empty($row)) continue;
						if ($row[$columnName] == $value) {
							$res[$key] = $row;
						} else {
							unset($result[$key]);
						}
					}
					break;
					
				case 'neq':
				case '!=':
					foreach ($result as $key => $row) {
						if (empty($row)) continue;
						if ($row[$columnName] != $value) {
							$res[$key] = $row;
						} else {
							unset($result[$key]);
						}
					}
					break;
					
				case 'gt':
				case '>':
					foreach ($result as $key => $row) {
						if (empty($row)) continue;
						if ($row[$columnName] > $value) {
							$res[$key] = $row;
						} else {
							unset($result[$key]);
						}
					}
					break;
					
				case 'lt':
				case '<':
					foreach ($result as $key => $row) {
						if (empty($row)) continue;
						if ($row[$columnName] < $value) {
							$res[$key] = $row;
						} else {
							unset($result[$key]);
						}
					}
					break;
					
			   case 'gte':
				case '>=':
					foreach ($result as $key => $row) {
						if (empty($row)) continue;
						if ($row[$columnName] >= $value) {
							$res[$key] = $row;
						} else {
							unset($result[$key]);
						}
					}
					break;
					
				case 'lte':
				case '<=':
					foreach ($result as $key => $row) {
						if (empty($row)) continue;
						if ($row[$columnName] <= $value) {
							$res[$key] = $row;
						} else {
							unset($result[$key]);
						}
					}
					break;
					
				case 'like':
					foreach ($result as $key => $row) {
						if (empty($row)) continue;
						if (strpos($row[$columnName], $value) !== false) {
							$res[$key] = $row;
						} else {
							unset($result[$key]);
						}
					}
					break;
			}
		}

        return $res;
    }

    /**
     * 
     * Delete one row by primary key
     * @param string $primaryKeyValue
     * @return void
     */
    public function deleteByKey($primaryKeyValue)
    {
        $row = $this->findRow($primaryKeyValue);
        if ($row) {
            $this->delete($row);
        }
    }
    
    /**
     * Delete row(s)
     * 
     * @param array $rowData
     * @return void
     */
    public function delete($rowData)
    {
        $rowData = $this->_checkData($rowData);
        $data = $rowData['data'];
        $result = array();
        
        foreach ($data as $row) {
            if ($this->findRow($row[$this->_primaryKey])) {
                $result[$row[$this->_primaryKey]] = $row;
            } else {
                require_once 'Kdl/Ipadso/Csv/Exception.php';
                throw new Kdl_Ipadso_Csv_Exception(
                    sprintf($this->msgConfig->C003_RowNotFound, $row[$this->_primaryKey])
                );
            }
        }
        
        $result = array_diff_assoc($this->_csvData, $result);
        if (count($result) == count($this->_csvData)) {
            require_once 'Kdl/Ipadso/Csv/Exception.php';
            throw new Kdl_Ipadso_Csv_Exception($this->msgConfig->C005_RowNotFound);
        }
        
        $this->_csvData = $result;
        $result = array_merge($this->_csvHeader, $result);
        $this->_csvWriter->writeRow(
            $result, 
            Kdl_Ipadso_Csv_Reader::FILE_MODE_WRITE
        );
        
    }

    /**
     * 
     * Update row(s)
     * @param array $rowData
     */
    public function update($rowData)
    {
        $rowData = $this->_checkData($rowData);
        $data = $rowData['data'];
        foreach ($data as $row) {
            if ($this->findRow($row[$this->_primaryKey])) {
                $this->_csvData[$row[$this->_primaryKey]] = $row;
            } else {
                require_once 'Kdl/Ipadso/Csv/Exception.php';
                throw new Kdl_Ipadso_Csv_Exception(
                    sprintf($this->msgConfig->C003_RowNotFound, $row[$this->_primaryKey])
                );
            }
        }
        
        $result = $this->_csvData;
        $result = array_merge($this->_csvHeader, $result);
        $this->_csvWriter->writeRow(
            $result, 
            Kdl_Ipadso_Csv_Reader::FILE_MODE_WRITE
        );
    }

    /**
     * Insert new row(s)
     * 
     * @param array $rowData
     * @throws Exception 
     */
    public function insert($rowData)
    {
        $rowData = $this->_checkData($rowData);
        $data = $rowData['data'];
        foreach ($data as $row) {
            if ($this->findRow($row[$this->_primaryKey])) {
                require_once 'Kdl/Ipadso/Csv/Exception.php';
                throw new Kdl_Ipadso_Csv_Exception(
                    sprintf($this->msgConfig->C002_ExistedRow, $row[$this->_primaryKey])
                );
            }
        }
        
         $this->_csvWriter->writeRow(
            $data, 
            Kdl_Ipadso_Csv_Reader::FILE_MODE_APPEND
        );
         if ($this->_csvData) {
             $this->_csvData += $data;
         } else {
             $this->_csvData = $data;
         }

        
    }
    
    /**
     * Get total records in csv file
     * @return int 
     */
    public function getTotal()
    {
        return $this->_totalRecords;
    }


    /**
     * Get all data array
     *
     * @return array
     */
    public function getData()
    {
        if ($this->_csvData === null) {
            $this->_csvData = $this->_fetchData();
        }
        
        return $this->_csvData;
    }
    
    protected function _fetchData()
    {
        $this->_csvReader->rewind();
        if ($this->_csvReader->hasHeader()) {
            $this->_csvReader->pass(); // pass header
        }
        $currentRow = 0;
        while ($this->_csvReader->next()) {
            $current = $this->_csvReader->current();
            if (empty($current)) continue;
            if (array_key_exists($current[$this->_primaryKey], (array)$this->_csvData)) {
                require_once 'Kdl/Ipadso/Csv/Exception.php';
                throw new Kdl_Ipadso_Csv_Exception(
                    sprintf($this->msgConfig->C006_DuplicatePrimaryKey, $current[$this->_primaryKey])
                );
                return;
            }
            $this->_csvData[$current[$this->_primaryKey]] = $current;
            $currentRow++;
        }
       
        $this->_totalRecords = $currentRow;
        
        return $this->_csvData;
    }

    /**
     * Get csv header
     * @return array
     */
    public function getHeader()
    {
        return $this->_csvReader->getHeader();
    }
    
     
    /*
     * Prepare data
     *  Add multi-primarykey to array index
     * 
     * @return array
     * @author Nguyen Huu Tam
     * @since 2012/09/06
     */
    public function prepareData($rowsData)
    {
        $result = array();
        $data = $this->_checkData($rowsData);
        foreach ($data['data'] as $key => $row) {
            $result['data'][$row[$this->_primaryKey]] = $row;
        }
        
        // If has errors
        if (isset($data['error'])) {
            $result['error'] = $data['error'];
        }

        return $result;        
    }
    
    
    /*
     * Renew data
     *  Add all new data
     * 
     * @author Nguyen Huu Tam
     * @since 2012/09/06
     */
    public function renewData($file, $data)
    {
        $csvWriter = new Kdl_Ipadso_Csv_Writer($file);
        $csvWriter->writeRow(
            $data, 
            Kdl_Ipadso_Csv_Reader::FILE_MODE_APPEND,
            $this->getHeader()
        );
    }

    /*
     * clear all data (keep header)
     * 
     * @author nqtrung
     * @since 2014/05/27
     */
    public function clearAllRow() {
        $csvWriter = new Kdl_Ipadso_Csv_Writer($this->_csvFileName);
        $csvWriter->writeRow(
            array(), 
            Kdl_Ipadso_Csv_Reader::FILE_MODE_WRITE,
            $this->getHeader()
        );
        $this->_csvData = null;
    }
}