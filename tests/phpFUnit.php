<?php
/* fuck PHPUnit */

if (!class_exists('PHPUnit_Framework_TestCase') &&
    class_exists('\\PHPUnit\\Framework\\TestCase')) {
	abstract class PHPUnit_Framework_TestCase extends \PHPUnit\Framework\TestCase {
	}
}
