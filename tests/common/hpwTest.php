<?php
/*-
 * Small test for hello-php-world’s demonstration class
 *
 * Copyright © 2020
 *	mirabilos <m@mirbsd.org>
 * Copyright © 2019
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

class hpwTest extends phpFUnit_TestCase {
	private function ob_wrap($instance, $method, $p=array()) {
		ob_start();
		call_user_func_array(array($instance, $method), $p);
		return (ob_get_clean());
	}

	public function testHPW() {
		$s = error_reporting(-1);
		require_once(dirname(__FILE__) . '/../../common/util.php');
		require_once(dirname(__FILE__) . '/../../common/hpw.php');

		/* rudimentary tests */
		$html = new HpwWeb();
		$this->assertNotNull($html);
		$this->{$this->aIsObject}($html);
		$this->{$this->aStringContainsString}('<title>untitled page</title>',
		    $this->ob_wrap($html, 'showHeader'));
		$html->setTitle('f&amp;o');
		$this->{$this->aStringContainsString}('<title>f&amp;amp;o</title>',
		    $this->ob_wrap($html, 'showHeader'));
		$this->{$this->aStringContainsString}('<title>meow</title>',
		    $this->ob_wrap($html, 'showHeader',
		    array(array('title' => 'meow'))));
		$this->{$this->aStringContainsString}('</body></html>',
		    $this->ob_wrap($html, 'showFooter'));

		error_reporting($s);
	}
}
