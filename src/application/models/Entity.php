<?php
/**
 * Class Entity
 *
 * @author Nguyen Huu Tam
 * @copyright Kobe Digital Labo, Inc
 * @since 2012/07/13
 */
class Application_Model_Entity 
{
    public function __call($methodName, $args) 
    {
        if (preg_match('~^(set|get)([A-Z])(.*)$~', $methodName, $matches)) {
            $property = strtolower($matches[2]) . $matches[3];
            if (!property_exists($this, $property)) {
                throw new Exception('Property ' . $property . ' not exists');
            }
            switch($matches[1]) {
                case 'set':
                    $this->checkArguments($args, 1, 1, $methodName);
                    return $this->set($property, $args[0]);
                case 'get':
                    $this->checkArguments($args, 0, 0, $methodName);
                    return $this->get($property);
                case 'default':
                    throw new Exception('Method ' . $methodName . ' not exists');
            }
        }
    }

    public function get($property) 
    {
        return $this->$property;
    }

    public function set($property, $value) 
    {
        if (!empty($property)) {
            $this->$property = $value;
        }
        return $this;
    }

    protected function checkArguments(array $args, $min, $max, $methodName) 
    {
        $argc = count($args);
        if ($argc < $min || $argc > $max) {
            throw new Exception('Method ' . $methodName . ' needs minimaly ' . $min . ' and maximaly ' . $max . ' arguments. ' . $argc . ' arguments given.');
        }
    }
    
    public function setData($data)
    {
        if (is_array($data) && !empty($data)) {
            foreach ($data as $key => $value) {
                $this->set($key, $value);
            }
            
            return $this;
        }
    }
	
	public static function natksort(&$array) {
		$keys = array_keys($array);
		natcasesort($keys);

		$new_array = array();
		foreach ($keys as $k) {
			$new_array[$k] = $array[$k];
		}

		if (count($new_array) > 0) {
			$array = $new_array;
		}
		return true;
	}
}
