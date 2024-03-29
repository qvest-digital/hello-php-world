<?php
/*-
 * Small test for hello-php-world’s common utilities
 *
 * Copyright © 2020
 *	mirabilos <m@mirbsd.org>
 * Copyright © 2019, 2022
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

require_once(dirname(__FILE__) . '/../phpFUnit.php');

class utilTest extends phpFUnit_TestCase {
	public function testUtil() {
		$s = error_reporting(-1);
		require_once(dirname(__FILE__) . '/../../common/util.php');
		require_once(dirname(__FILE__) . '/../../common/db.php');

		/* very basic, sporadic, tests */

		$this->assertEquals('f&amp;o',
		    util_html_secure('f&amp;o'));
		$this->assertEquals('f&amp;o',
		    util_html_secure('f&o'));
		$this->assertEquals('f&amp;o&amp;bar',
		    util_html_secure('f&amp;o&bar'));

		$this->assertEquals("€\x0Ca",
		    util_fixutf8("\x80\x0Ca"));
		$this->assertEquals("€?a",
		    util_xmlutf8("\x80\x0Ca"));
		/* silent death for didfix wrongly given */
		$pcre_utfcheck = preg_replace('/u/u', 'x', "\xFF") === NULL;
		$this->assertEquals($pcre_utfcheck ? NULL : "\x80\x0Ca",
		    util_xmlutf8("\x80\x0Ca", true));
		/* test efficacy */
		$q = "\x00\x01\x02\x03\x04\x05\x06\x07\x08\x09\x0A\x0B\x0C\x0D\x0E\x0F" .
		    "\x10\x11\x12\x13\x14\x15\x16\x17\x18\x19\x1A\x1B\x1C\x1D\x1E\x1F" .
		    " \xEF\xBF\xBD\xEF\xBF\xBE\xEF\xBF\xBF\xF0\x90\x80\x80";
		$e = "?????????\x09\x0A??\x0D?????????????????? �??\xF0\x90\x80\x80";
		$this->assertEquals($e, util_xmlutf8($q));
		$this->assertEquals($e, util_xmlutf8($q, true));
		$this->assertEquals("ô?€€", util_fixutf8("\xF4\x90\x80\x80"));

		$rn = util_randnum(0x0F, 2);
		$this->assertTrue($rn === 0 || $rn === 1 || $rn === 2);

		$this->{$this->aStringContainsString}('"uri": "php://stdout",',
		    minijson_encdbg(STDOUT));

		unset($v);
		$this->assertFalse(util_nat0($v));
		$v = array();
		$this->assertFalse(util_nat0($v));
		$v = array(1, 1);
		$this->assertFalse(util_nat0($v));

		$k = '1a';
		$this->assertFalse(util_nat0($k));
		$a = array($k);
		$this->assertFalse(util_nat0($a));
		$k = '1';
		$this->assertEquals(1, util_nat0($k));
		$a = array($k);
		$this->assertEquals(1, util_nat0($a));
		$k = '1 ';
		$this->assertFalse(util_nat0($k));
		$a = array($k);
		$this->assertFalse(util_nat0($a));
		$k = ' 1';
		$this->assertFalse(util_nat0($k));
		$a = array($k);
		$this->assertFalse(util_nat0($a));
		$k = '0123';
		$this->assertFalse(util_nat0($k));
		$a = array($k);
		$this->assertFalse(util_nat0($a));
		$k = '1.2';
		$this->assertFalse(util_nat0($k));
		$a = array($k);
		$this->assertFalse(util_nat0($a));
		$k = 'a1';
		$this->assertFalse(util_nat0($k));
		$a = array($k);
		$this->assertFalse(util_nat0($a));

		$k = 1;
		$this->assertEquals(1, util_nat0($k));
		$a = array($k);
		$this->assertEquals(1, util_nat0($a));
		$k = 0;
		$this->assertEquals(0, util_nat0($k));
		$a = array($k);
		$this->assertEquals(0, util_nat0($a));
		$k = -1;
		$this->assertFalse(util_nat0($k));
		$a = array($k);
		$this->assertFalse(util_nat0($a));

		error_reporting($s);
	}

	public function testMailHeaderEncoding() {
		/* array of teststring to array of allowed result strings */
		/* in theory, the result can have many forms but PHP emits just one */
		$tests = array(
			"p\xC3\xB6stal ohfuu bar baz foo bar baz foo bar baz foo bar baz" => array(
				"=?UTF-8?Q?p=C3=B6stal=20ohfuu=20bar=20baz=20foo=20bar=20baz=20fo?=\r\n =?UTF-8?Q?o=20bar=20baz=20foo=20bar=20baz?=",
			),
			"[service-Aufgaben S&W-Team][#19415] VM''s aufsetzen mit unterschiedlichen" => array(
				"[service-Aufgaben S&W-Team][#19415] VM''s aufsetzen mit\r\n unterschiedlichen",
			),
		);
		foreach ($tests as $k => $vv) {
			$r = util_sendmail_encode_hdr(strlen('Subject'), $k);
			$s = util_sendmail_encode_hdr('Subject', $k);
			$this->assertEquals($s, 'Subject: ' . $r);
			$ok = false;
			foreach ($vv as $v) {
				if ($r === $v) {
					$ok = true;
					break;
				}
			}
			if (!$ok) {
				/* force failure but say where */
				$this->assertEquals($vv[0], $r);
			}
		}
	}
}
