<?php
/**
 * Pack all data bacth
 *
 * @author Nguyen Huu Tam
 * @copyright Kobe Digital Labo, Inc
 * @since 2012/08/08
 */

require_once('init.php');
require_once('Kdl/Ipadso/DataPacker.php');

if (isset($argc)) {
    // If have not any param
    if ($argc < 2) {
        // Pack all data
        DataPacker::packAllData();
    } else {
        $params = array_combine(array('class', 'path'), $argv);
        // Pack direct path data
        DataPacker::packData($params['path']);
    }
}

?>
