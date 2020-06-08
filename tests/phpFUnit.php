<?php
/* fuck PHPUnit */

if (!class_exists('PHPUnit_Framework_TestCase') &&
    class_exists('\\PHPUnit\\Framework\\TestCase')) {
	require_once(dirname(__FILE__) . '/phpFUniT.php');
}
