<?php

if (!defined('CAKEPHP_UNIT_TEST_EXECUTION')) {
	define('CAKEPHP_UNIT_TEST_EXECUTION', 1);
}

class Post extends CakeTestModel {
    var $name = 'Post';
    var $actsAs = array("Containable", "SphinxSearchable.SphinxSearchable" => array());
}

?>
