<?php
/*-
 * Workaround for deficiencies in PHPUnit, if one m̲u̲s̲t̲ use it
 *
 * Copyright © 2020
 *	mirabilos <m@mirbsd.org>
 * Copyright © 2018
 *	mirabilos <t.glaser@tarent.de>
 *
 * Provided that these terms and disclaimer and all copyright notices
 * are retained or reproduced in an accompanying document, permission
 * is granted to deal in this work without restriction, including un‐
 * limited rights to use, publicly perform, distribute, sell, modify,
 * merge, give away, or sublicence.
 *
 * This work is provided “AS IS” and WITHOUT WARRANTY of any kind, to
 * the utmost extent permitted by applicable law, neither express nor
 * implied; without malicious intent or gross negligence. In no event
 * may a licensor, author or contributor be held liable for indirect,
 * direct, other damage, loss, or other issues arising in any way out
 * of dealing in the work, even if advised of the possibility of such
 * damage or existence of a defect, except proven that it results out
 * of said person’s immediate fault when using the work as intended.
 */

if (!class_exists('PHPUnit_Framework_TestCase') &&
    class_exists('\\PHPUnit\\Framework\\TestCase')) {
	eval('
		abstract class PHPUnit_Framework_TestCase
		    extends \PHPUnit\Framework\TestCase {
		}
	');
}

abstract class phpFUnit_TestCase extends PHPUnit_Framework_TestCase {
	protected function rplIsObject($v) {
		return $this->assertTrue(is_object($v));
	}

	protected function rplStringContainsString($e, $a) {
		return $this->assertFalse(strpos($a, $e) === false);
	}

	function __construct() {
		if (is_callable(array(get_parent_class(), '__construct')))
			parent::__construct();
		foreach (array(
			'IsObject',
			'StringContainsString',
		    ) as $suff) {
			$prop = 'a' . $suff;
			$this->$prop = method_exists($this, 'assert' . $suff) ?
			    'assert' . $suff : 'rpl' . $suff;
		}
	}
}
