<?php
if (!defined('__main__') && count(get_included_files()) <= 1 && count(debug_backtrace()) < 1)
	define('__main__', __FILE__);
/**
 * Minimal stub for PHPUnit to run our tests
 *
 * Copyright © 2020
 *	mirabilos <m@mirbsd.org>
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

error_reporting(-1);

class FakeUnitException extends Exception {
	public function go($v) {
		$t = $this->getTrace();
		$f = $t[1]['file'];
		$wd = getcwd() . '/';
		if (strncmp($f, $wd, strlen($wd)) === 0)
			$f = substr($f, strlen($wd));
		printf("E: %s:%d:%s%s%s(): %s: %s\n",
		    $f, $t[1]['line'],
		    $t[2]['class'], $t[2]['type'], $t[2]['function'],
		    $this->getMessage(), var_export($v, true));
		throw $this;
	}
}

abstract class PHPUnit_Framework_TestCase {
	private static function c2($e, $a, $msg, $t) {
		if ($t) {
			$x = new FakeUnitException($msg);
			$x->go(array(
				'expect' => $e,
				'actual' => $a,
			    ));
		}
	}
	private static function chk($v, $msg, $t) {
		if ($t) {
			$x = new FakeUnitException($msg);
			$x->go($v);
		}
	}
	protected function assertTrue($v) {
		$this->chk($v, "not true", !$v);
	}
	protected function assertFalse($v) {
		$this->chk($v, "not false", !!$v);
	}
	protected function assertNotNull($v) {
		$this->chk($v, "null", is_null($v));
	}
	protected function assertIsObject($v) {
		$this->chk($v, "not object", !is_object($v));
	}
	protected function assertStringContainsString($e, $a) {
		$this->c2($e, $a, "substring not contained",
		    strpos($a, $e) === false);
	}
	protected function assertEquals($e, $a) {
		$this->c2($e, $a, "not equal", $a != $e);
	}
}

if (defined('__main__') && constant('__main__') === __FILE__) {
	if (count($argv) < 2) {
		chdir(dirname(__FILE__));
		unset($lns);
		$rc = 1;
		exec('find . -type f -a -name \*Test.php', $lns, $rc);
		if ($rc) {
			echo "E: find\n";
			exit($rc);
		}
		$fls = array();
		foreach ($lns as $f) {
			if (strncmp($f, './', 2) === 0 &&
			    file_exists($f))
				$fls[] = $f;
		}
	} else {
		$fls = $argv;
		array_shift($fls);
	}
	foreach ($fls as $f) {
		if (file_exists($f))
			require_once($f);
		else {
			echo "E: not found: $f\n";
			exit(1);
		}
	}
	$npass = 0;
	$nfail = 0;
	foreach (get_declared_classes() as $cls) {
		$clsr = new ReflectionClass($cls);
		if (!$clsr->isSubclassOf('PHPUnit_Framework_TestCase'))
			continue;
		if ($clsr->isAbstract())
			continue;
		echo "I: <<<< $cls\n";
		$obj = $clsr->newInstance();
		foreach ($clsr->getMethods() as $m) {
			if (!$m->isPublic())
				continue;
			if (strncmp($m->name, "test", 4) !== 0)
				continue;
			echo "I: test $cls::{$m->name}\n";
			try {
				call_user_func(array($obj, $m->name));
				echo "\rI: pass $cls::{$m->name}\n";
				++$npass;
			} catch (FakeUnitException $e) {
				echo "\rI: FAIL $cls::{$m->name}\n";
				++$nfail;
			}
		}
		echo "I: >>>> $cls\n";
	}
	echo "I: $nfail failed, $npass ok, " . ($npass + $nfail) . " total\n";
	exit($nfail ? 1 : 0);
}
