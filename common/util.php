<?php
/**
 * Copyright © 2010, 2011, 2012, 2013, 2014, 2015, 2016, 2017
 * 	mirabilos <t.glaser@tarent.de>
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

require_once(dirname(__FILE__) . '/VERSION.php');

/* replace this with your own custom proper error handling */
function util_logerr($loglevel, $s) {
	$s = $loglevel . ': ' . str_replace("\n", "\nN: ", trim($s)) . "\n";
	echo $s;
	if (in_array(php_sapi_name(), array(
		/* all with separate log, NOT stdout/stderr */
		'apache',
		'apache2filter',
		'apache2handler',
		'litespeed',
		/* possibly more */
	    )))
		foreach (explode("\n", trim($s)) as $msg)
			error_log($msg, 4);
}
function util_debugJ() {
	$argc = func_num_args();
	$argv = func_get_args();
	$skip = 0;

	/* should not happen except extremely early */
	if (!function_exists('minijson_encdbg')) {
		print_r($argv);
		debug_print_backtrace();
		return;
	}

	$loglevel = 'D';
	if ($argc && $argv[0] === 'ERR') {
		$loglevel = 'E';
		/* skip loglevel */
		$argc--;
		array_shift($argv);
	}

	$moretrace = false;
	if ($argc && $argv[0] === true) {
		$moretrace = true;
		/* skip that… */
		$argc--;
		array_shift($argv);
	}

	while ($argc && $argv[0] === NULL) {
		/* skip backtrace levels, one for each leading NULL */
		$skip++;
		$argc--;
		array_shift($argv);
	}

	$bt = debug_backtrace();
	$cm = util_debug_backtrace_fmt($bt, $skip);

	if ($argc > 0) {
		/* is first argument 7bit ASCII string, no C0 ctrl chars? */
		if (is_string($argv[0]) && !preg_match('/[^ -~]/', $argv[0])) {
			/* shift it to front */
			$cm .= ': ' . array_shift($argv);
			$argc--;
		}
	}

	if ($argc == 1) {
		/* omit the [] for only one argument */
		$argv = $argv[0];
	}

	if ($argc != 0) {
		/* append any arguments left */
		$cm .= ': ' . minijson_encdbg($argv);
	}
	util_logerr($loglevel, $cm);
	if ($moretrace) {
		$loglevel = 'T';
		while (isset($bt[++$skip])) {
			$cm = util_debug_backtrace_fmt($bt, $skip);
			util_logerr($loglevel, $cm);
		}
	}
}

/* format a backtrace array member */
function util_debug_backtrace_fmt($bt, $ofs) {
	if (!isset($bt[$ofs]))
		return '(no backtrace)';

	/* calculate backtrace info: file and line */
	$rv = sprintf('%s[%d]: ',
	    util_ifsetor($bt[$ofs]['file'], '(no file)'),
	    util_ifsetor($bt[$ofs]['line'], -1));

	/* calculate backtrace info: surrounding function and args */
	if (isset($bt[$ofs + 1])) {
		/* calling method: if set, begin with class */
		$rv .= util_ifsetor($bt[$ofs + 1]['class'], '');
		/* calling type; / if not set but we have a class */
		$rv .= util_ifsetor($bt[$ofs + 1]['type']) ?
		    $bt[$ofs + 1]['type'] :
		    (util_ifsetor($bt[$ofs + 1]['class']) ? '/' : '');
		/* called function */
		$rv .= util_ifsetor($bt[$ofs + 1]['function'], '<unknown>');
		/* function arguments, JSON encoded but with () around */
		$rv .= '(' . substr(minijson_encdbg(
		    util_ifsetor($bt[$ofs + 1]['args'], array()), false),
		    1, -1) . ')';
	} else {
		$rv .= '(top-level)';
	}
	return $rv;
}

/* get a backtrace as string */
function debug_string_backtrace($skip=0) {
	ob_start();
	debug_print_backtrace();
	$trace = ob_get_contents();
	ob_end_clean();

	/* remove first item (this function, i.e. redundant) from backtrace */
	/*XXX remove the next $skip items, too… */
	$trace = preg_replace('/^#0\s+' . __FUNCTION__ . "[^\n]*\n/", '',
	    $trace, 1);

	/* renumber backtrace items */
	$trace = preg_replace_callback('/^#(\d+)/m', function ($match) {
		return sprintf('#%d', $match[1] - 1);
	    }, $trace);

	return $trace;
}

/* define if not yet defined */
function define_dfl($k, $v) {
	if (!defined($k))
		define($k, $v);
}

/* set global variable if not yet set */
function set_dfl($k, $v) {
	global $$k;

	if (!isset($$k))
		$$k = $v;
}

/**
 * return $1 if $1 is set, ${2:-false} otherwise
 *
 * Shortcomings: may create $$val = NULL in the
 * current namespace; see the (rejected — but
 * then, with PHP, you know where you stand…)
 * https://wiki.php.net/rfc/ifsetor#userland_2
 * proposal for details and a (rejected) fix.
 *
 * Do not use this function if $val is “magic”,
 * for example, an overloaded \ArrayAccess.
 */
function util_ifsetor(&$value, $default=false) {
	return (isset($value) ? $value : $default);
}

/* return $1 is $1 is a scalar, ${2:-false} if unset, ${3:-false} otherwise */
/* same shortcomings util_ifsetor() has */
function util_ifscalaror(&$val, $default=false, $ifarray=false) {
	return (isset($val) ? (is_array($val) ? $ifarray : $val) : $default);
}

/* convert !is_array into sole array */
function util_mkarray($v) {
	return is_array($v) ? $v : array($v);
}

/* escape a string into HTML for safe output */
function util_html_encode($s) {
	return htmlspecialchars(strval($s), ENT_QUOTES, 'UTF-8');
}

/* unconvert a string converted with util_html_encode() or htmlspecialchars() */
function util_unconvert_htmlspecialchars($s) {
	return html_entity_decode(strval($s), ENT_QUOTES | ENT_XHTML, 'UTF-8');
}

/* secure a (possibly already HTML encoded) string */
function util_html_secure($s) {
	$r = util_html_encode(util_unconvert_htmlspecialchars($s));
	return is_string($s) && ($r === $s) ? $s : $r;
}

/* split text by newlines: ASCII CR-LF / Unix LF; if not found, Macintosh CR */
function util_split_newlines($text, &$trailing=false, $mop=true) {
	if (is_array($text))
		$text = implode("\n", $text);
	/*
	 * First, convert all ASCII CR-LF pairs into ASCII LF, so we
	 * then have either Unix (one LF) or Macintosh (one CR) line
	 * endings; any extra CR characters are retained (payload).
	 */
	$text = str_replace("\015\012", "\012", strval($text));
	/*
	 * Now, detect which of the two line ending conventions are
	 * actually used after the above, with preference on Unix (or
	 * converted ASCII) over Macintosh: split by ASCII LF if one
	 * exists, otherwise split by CR; in either case, ignore the
	 * other completely (i.e. either CR or LF may be contained in
	 * the result array’s string members except if $mop is set
	 * (default) which removes them for consistency and security).
	 */
	$macintosh = strpos($text, "\012") === false;
	if ($mop && !$macintosh)
		$text = str_replace("\015", '', $text);
	$nlstr = $macintosh ? "\015" : "\012";
	/* remove trailing newline indicating in $trailing if one existed */
	if (($trailing = strlen($text) < 1 ? false :
	    ($text[strlen($text) - 1] === $nlstr)))
		$text = substr($text, 0, -1);
	return explode($nlstr, $text);
}

/* convert text to ASCII CR-LF by logical newlines, cf. above */
function util_sanitise_multiline_submission($text, &$lastnl=false) {
	$r = implode("\015\012", util_split_newlines($text, $lastnl));
	if ($lastnl)
		$r .= "\015\012";
	return is_string($text) && ($r === $text) ? $text : $r;
}

/* convert text to UTF-8 (from UTF-8 or cp1252 or question marks); nil⇒nil */
function util_fixutf8($s) {
	if ($s === NULL)
		return NULL;
	if (!is_string($s))
		$s = strval($s);
	$Sx = strlen($s);
	$Sp = 0;
 util_fixutf8_check:
	if ($Sp >= $Sx)
		return $s;
	/* read next octet */
	$c = ord(($ch = $s[$Sp++]));
	/* ASCII? */
	if ($c < 0x80)
		goto util_fixutf8_check;
	/* UTF-8 lead byte */
	if ($c < 0xC2 || $c >= 0xF8) {
		goto util_fixutf8_chkfail;
	} elseif ($c < 0xE0) {
		$wc = ($c & 0x1F) << 6;
		$wmin = 0x80;
		$Ss = 1;
	} elseif ($c < 0xF0) {
		$wc = ($c & 0x0F) << 12;
		$wmin = 0x800;
		$Ss = 2;
	} else {
		$wc = ($c & 0x07) << 18;
		$wmin = 0x10000;
		$Ss = 3;
	}
	/* UTF-8 trail bytes */
	if ($Sp + $Ss > $Sx)
		goto util_fixutf8_chkfail;
	while ($Ss--)
		if (($c = ord($s[$Sp++]) ^ 0x80) <= 0x3F)
			$wc |= $c << (6 * $Ss);
		else
			goto util_fixutf8_chkfail;
	/* complete wide character */
	if ($wc < $wmin)
		goto util_fixutf8_chkfail;

	/* process next char */
	goto util_fixutf8_check;

 util_fixutf8_chkfail:
	/* failed, convert using latin1/cp1252 mapping */
	ob_start();
	$Sp = 0;
	while ($Sp < $Sx) {
		$c = ord($s[$Sp++]);
		switch ($c) {
		case 0x80: $c = 0x20AC; break;
		case 0x81: $c = 0x003F; break;	/* not in cp1252 */
		case 0x82: $c = 0x201A; break;
		case 0x83: $c = 0x0192; break;
		case 0x84: $c = 0x201E; break;
		case 0x85: $c = 0x2026; break;
		case 0x86: $c = 0x2020; break;
		case 0x87: $c = 0x2021; break;
		case 0x88: $c = 0x02C6; break;
		case 0x89: $c = 0x2030; break;
		case 0x8A: $c = 0x0160; break;
		case 0x8B: $c = 0x2039; break;
		case 0x8C: $c = 0x0152; break;
		case 0x8D: $c = 0x003F; break;	/* not in cp1252 */
		case 0x8E: $c = 0x017D; break;
		case 0x8F: $c = 0x003F; break;	/* not in cp1252 */
		case 0x90: $c = 0x003F; break;	/* not in cp1252 */
		case 0x91: $c = 0x2018; break;
		case 0x92: $c = 0x2019; break;
		case 0x93: $c = 0x201C; break;
		case 0x94: $c = 0x201D; break;
		case 0x95: $c = 0x2022; break;
		case 0x96: $c = 0x2013; break;
		case 0x97: $c = 0x2014; break;
		case 0x98: $c = 0x02DC; break;
		case 0x99: $c = 0x2122; break;
		case 0x9A: $c = 0x0161; break;
		case 0x9B: $c = 0x203A; break;
		case 0x9C: $c = 0x0153; break;
		case 0x9D: $c = 0x003F; break;	/* not in cp1252 */
		case 0x9E: $c = 0x017E; break;
		case 0x9F: $c = 0x0178; break;
		}
		if ($c < 0x80)
			echo chr($c);
		elseif ($c < 0x0800)
			echo chr(0xC0 | ($c >> 6)) .
			    chr(0x80 | ($c & 0x3F));
		else /* no mapping outside BMP */
			echo chr(0xE0 | ($c >> 12)) .
			    chr(0x80 | (($c >> 6) & 0x3F)) .
			    chr(0x80 | ($c & 0x3F));
	}
	return ob_get_clean();
}
/* convert text to XML-safe UTF-8 (most strict) or question marks; nil⇒nil */
function util_xmlutf8($s, $didfix=false) {
	if ($s === NULL)
		return NULL;
	$r = preg_replace("/[^\x09\x0A\x0D -~\xC2\x85\xC2\xA0-\xED\x9F\xBF\xEE\x80\x80-\xEF\xB7\x8F\xEF\xB7\xB0-\xEF\xBF\xBD\xF0\x90\x80\x80-\xF0\x9F\xBF\xBD\xF0\xA0\x80\x80-\xF0\xAF\xBF\xBD\xF0\xB0\x80\x80-\xF0\xBF\xBF\xBD\xF1\x80\x80\x80-\xF1\x8F\xBF\xBD\xF1\x90\x80\x80-\xF1\x9F\xBF\xBD\xF1\xA0\x80\x80-\xF1\xAF\xBF\xBD\xF1\xB0\x80\x80-\xF1\xBF\xBF\xBD\xF2\x80\x80\x80-\xF2\x8F\xBF\xBD\xF2\x90\x80\x80-\xF2\x9F\xBF\xBD\xF2\xA0\x80\x80-\xF2\xAF\xBF\xBD\xF2\xB0\x80\x80-\xF2\xBF\xBF\xBD\xF3\x80\x80\x80-\xF3\x8F\xBF\xBD\xF3\x90\x80\x80-\xF3\x9F\xBF\xBD\xF3\xA0\x80\x80-\xF3\xAF\xBF\xBD\xF3\xB0\x80\x80-\xF3\xBF\xBF\xBD\xF4\x80\x80\x80-\xF4\x8F\xBF\xBD]/u",
	    '?', $didfix ? $s : util_fixutf8($s));
	return is_string($s) && ($r === $s) ? $s : $r;
}

/* prefix every line of a string */
function util_linequote($s, $pfx, $firstline=false) {
	if ($s instanceof \IteratorAggregate) {
		$a = array();
		foreach ($s as $v)
			$a[] = $v;
		unset($v);
		$s = $a;
		unset($a);
	}
	$s = util_split_newlines($s);
	if (!$s)
		return '';
	if ($firstline && count($s) == 1)
		return ' ' . $s[0];
	return ($firstline ? "\n" : '') . $pfx . implode("\n" . $pfx, $s);
}

/* return integral value (ℕ₀) of passed string if it matches, or false */
function util_nat0(&$s) {
	if (!isset($s)) {
		/* unset variable */
		return false;
	}
	if (is_array($s)) {
		if (count($s) == 1) {
			/* one-element array */
			return util_nat0($s[0]);
		}
		/* not one element, or element not at [0] */
		return false;
	}
	if (!is_numeric($s)) {
		/* not numeric */
		return false;
	}
	$num = (int)$s;
	if ($num >= 0) {
		/* number element of ℕ₀ */
		$text = strval($num);
		if ($text == $s) {
			/* number matches its textual representation */
			return ($num);
		}
		/* doesn’t match, like 0123 or 1.2 or " 1" */
	}
	/* or negative */
	return false;
}

/* better random numbers, yay! */
function util_randbytes($num=6) {
	$f = fopen('/dev/urandom', 'rb');
	$b = fread($f, $num);
	fclose($f);

	/*XXX check if the result is truly random (how?) */
	if ($b === false || strlen(strval($b)) != $num) {
		util_debugJ(true, 'Could not read from random device',
		    array('b' => $b, 'num' => $num));
		exit(1);
	}

	return strval($b);
}

function util_randnum($mask=0xFFFFFF, $lim=false) {
	if ($lim === false)
		$lim = $mask;
	/* due to PHP limitations, four octets can’t be used */
	if ($mask > 0xFFFFFF || $lim > $mask) {
		util_debugJ(true, NULL, "util_randnum($mask, $lim): " .
		    'arguments out of bounds');
		exit(1);
	}
	while (true) {
		$rnum = hexdec(bin2hex(util_randbytes(3))) & $mask;
		if ($rnum <= $lim)
			return $rnum;
	}
}

/* convert case-insensitive part of eMail address to lowercase */
function util_emailcase($s) {
	$matches = array();
	if (!preg_match('/^([^@]*@)(.*)$/', $s, $matches))
		return $s;
	$r = $matches[1] . strtolower($matches[2]);
	return is_string($s) && ($r === $s) ? $s : $r;
}

/*-
 * The correct way to send eMail from PHP. Do not bug the poor people
 * in #sendmail on Freenode IRC if you’re doing it wrong.
 *
 * Usage example:
 *	$res = util_sendmail('noreply@example.com',
 *	    array('user@example.com', 'ceo@example.com'),
 *	    array(
 *		'From' => 'Webserver <noreply@example.com>',
 *		'To' => 'Random L. User <user@example.com>',
 *		'Cc' => 'PHB <ceo@example.com>',
 *		'Subject' => 'Testmail äöüß € ☺',
 *		'MIME-Version' => '1.0',
 *		'Content-Type' => 'text/plain; charset=UTF-8',
 *		'Content-Transfer-Encoding' => '8bit',
 *	    ), array(
 *		'Hello!',
 *		'',
 *		'This is a test äöüß € ☺ mail.',
 *	    ));
 *
 *	echo $res[0] ? "Success\n" : ("Failure\n" . print_r($res, true));
 *
 * The body could have been passed as a string (with lines separated
 * by \n, \r\n or even \r) instead of as an array of lines, as well.
 * This is probably most useful when the text gets passed from other
 * code. For headers, this is not supported due to the mandatory en‐
 * coding of them this function performs, by the standards.
 *
 * Suggested further reading:
 * ‣ https://eev.ee/blog/2012/04/09/php-a-fractal-of-bad-design/
 * ‣ https://gynvael.coldwind.pl/?id=492
 * ‣ https://en.wikiquote.org/wiki/Rasmus_Lerdorf
 * ‣ http://www.rfc-editor.org/rfc/rfc822.txt and its successors
 * ‣ http://jkorpela.fi/rfc/822addr.html
 */

/**
 * util_sendmail_encode_hdr() - Encode an eMail header
 *
 * This function wraps the PHP mb_encode_mimeheader function,
 * permitting short headers (like Content-Type usually is) to
 * pass through unencoded (because if Content-Type is encoded
 * at least Postfix does not handle the eMail correctly).
 *
 * This is still not correct; see RFC2047 §5 for where these
 * are actually allowed, but it suffices for now, especially
 * as we currently have no way to line-fold non-MIME headers.
 * This should eventually be improved.
 *
 * @param	string	$fname
 *		The name of the eMail header to use, which
 *		must not preg_match /[^!-9;-~]/ (not checked)
 * @param	string	$ftext
 *		The unstructured field text to encode
 * @result	string
 *		The encoded header field, without trailing CRLF
 */
function util_sendmail_encode_hdr($fname, $ftext) {
	$old_encoding = mb_internal_encoding();
	mb_internal_encoding('UTF-8');
	$rv = util_sendmail_encode_hdr_int($fname, $ftext);
	mb_internal_encoding($old_encoding);
	return $rv;
}
function util_sendmail_encode_hdr_int($fname, $ftext) {
	$field = $fname . ': ' . $ftext;
	if (strlen($field) > 78 || preg_match('/[^ -~]/', $field) !== 0) {
		$field = mb_encode_mimeheader($field, 'UTF-8', 'Q', "\015\012");
	}
	return $field;
}

/**
 * util_sendmail_valid() - Check an eMail address for validity
 *
 * Check address syntax. For the localpart, we only
 * permit a dot-atom, not a quoted-string or any of
 * the obsolete forms, here, and the domain is mat‐
 * ched using the modern standard, allowing numeric
 * labels as per most zones including the root zone
 * but otherwise per DNS/DARPA. Domain literals and
 * whitespace are not permitted. The domain part is
 * expected to be an FQDN resolving to an MX, AAAA,
 * or A RR — the caller can verify that itself once
 * validity is established by a truthy return value
 * from this function.
 *
 * Eventually, we likely will want for a full RFC-compliant
 * address parser. Actually, a full header and message parser
 * and generator will be necessary to implement all details.
 *
 * @param	string	$adr
 *		The eMail address to check for validity
 * @result	1 if the address is a valid RFC822 addr-spec
 *		and RFC5321 Mailbox, 0 if not, or false
 *		if an error occurred (same as preg_match)
 */
function util_sendmail_valid($adr) {
	/*
	 * note for regex reuse: to check a domain, take the part
	 * after the ‘@’ but prepend: _^(?=.{1,255}\$)
	 */
	return preg_match(
	    "_^(?=.{1,254}\$)(?=.{1,64}@)[-!#-'*+/-9=?A-Z^-~]+(\.[-!#-'*+/-9=?A-Z^-~]+)*@[0-9A-Za-z]([-0-9A-Za-z]{0,61}[0-9A-Za-z])?(\.[0-9A-Za-z]([-0-9A-Za-z]{0,61}[0-9A-Za-z])?)*\$_",
	    $adr);
}

/**
 * util_sendmail() - Send an eMail
 *
 * This function should be used in place of the PHP mail() function.
 *
 * Note: the header handling here permits only one instance of each
 * header and does not guarantee retaining ordering. This suffices
 * for (simple) creation of new messages but is not enough to process
 * existing eMails due to e.g. the Received header trace requirement.
 * Note further that, in PHP, the order is retained in an array.
 *
 * @param	string	$sender
 *		The eMail address to use as envelope sender
 * @param	string|array(string+)	$recip
 *		The eMail address(es) of the recipient(s),
 *		that is, To: Cc: and Bcc:
 * @param	array(string=>string*)	$hdrs
 *		The headers, unencoded, as UTF-8 key/value pairs
 * @param	string|array(string*)	$body
 *		The eMail body. If an array, line by line.
 * @result	array(bool, int, string)
 *		On success, the first element is true, otherwise
 *		false, and the second element contains an error
 *		code (from the operating system, usually 0‥255,
 *		or PHP, usually -1) or false, then the third
 *		element contains a hint what went wrong.
 */
function util_sendmail($sender, $recip, $hdrs, $body) {
	$old_encoding = mb_internal_encoding();
	if (!mb_internal_encoding('UTF-8')) {
		mb_internal_encoding($old_encoding);
		return array(false, false,
		    'mb_internal_encoding("UTF-8") failed');
	}

	/* check eMail addresses and shellescape them */

	$adrs = array();
	$recip = util_mkarray($recip);
	/* the first address only */
	$what = 'Sender';
	array_unshift($recip, $sender);
	foreach ($recip as $i => $adr) {
		if (!is_string($adr)) {
			mb_internal_encoding($old_encoding);
			return array(false, false,
			    $what . ' not a string');
		}
		$adr = trim($adr);
		/* check addr-spec syntax */
		if (!util_sendmail_valid($adr)) {
			mb_internal_encoding($old_encoding);
			return array(false, false,
			    $what . ' not a valid address: ' . $adr);
		}
		$recip[$i] = $adr;
		/* quote for shell */
		$adrs[] = "'" . str_replace("'", "'\\''", $adr) . "'";
		/* all but the first address */
		$what = 'Recipient';
	}

	/* handle the mail header */

	$msg = array();
	$hdr_seen = array();
	foreach ($hdrs as $k => $v) {
		/* do some checks */
		if (strlen(($k = strval($k))) < 1) {
			mb_internal_encoding($old_encoding);
			return array(false, false,
			    'Empty header found');
		}
		if (preg_match('/[^!-9;-~]/', $k) !== 0) {
			mb_internal_encoding($old_encoding);
			return array(false, false,
			    'Illegal char in header: ' . $k);
		}
		/* lowercase, independent on the locale */
		$kf = strtr($k,
		    'QWERTYUIOPASDFGHJKLZXCVBNM',
		    'qwertyuiopasdfghjklzxcvbnm');
		if (isset($hdr_seen[$kf])) {
			mb_internal_encoding($old_encoding);
			return array(false, false,
			    'Duplicate header: ' . $kf);
		}
		$hdr_seen[$kf] = true;
		/* append to message */
		$msg[] = util_sendmail_encode_hdr_int($k, $v);
	}

	/* handle mandatory header fields */

	if (!isset($hdr_seen['date'])) {
		/* date() is locale-independent and thus correct here */
		$msg[] = util_sendmail_encode_hdr_int('Date', date('r'));
	}
	if (!isset($hdr_seen['from'])) {
		$msg[] = util_sendmail_encode_hdr_int('From', $recip[0]);
	}

	unset($hdr_seen);
	$msg[] = '';

	/* take care of the body */

	if (!is_array($body)) {
		$body = util_split_newlines($body);
	}

	foreach ($body as $v) {
		$v = strval($v);
		if (strlen($v) > 998) {
			mb_internal_encoding($old_encoding);
			return array(false, false,
			    'Line too long: ' . $v);
		}
		$msg[] = $v;
	}

	/* generate a mail message from that */

	$body = implode("\015\012", $msg) . "\015\012";
	unset($msg);

	/* this is only safe because $adrs is shell-escaped */
	$adrs[0] = '/usr/sbin/sendmail -f' . $adrs[0] . ' -i --';
	$cmd = implode(' ', $adrs);
	unset($adrs);

	if (($p = popen($cmd, 'wb')) === false) {
		mb_internal_encoding($old_encoding);
		return array(false, false,
		    "Could not popen($cmd, 'wb');");
	}
	if (!($i = fwrite($p, $body)) || $i != strlen($body)) {
		mb_internal_encoding($old_encoding);
		return array(false, false,
		    "Could not fwrite: $i");
	}
	mb_internal_encoding($old_encoding);
	if (($i = pclose($p)) == -1 ||
	    (function_exists('pcntl_wifexited') && !pcntl_wifexited($i))) {
		return array(false, -1);
	}
	if (!function_exists('pcntl_wexitstatus')) {
		return array(true, -1);
	}
	$i = pcntl_wexitstatus($i);
	return array(!$i, $i);
}

/* JSON stuff which lives separate for hysterical raisins */
require_once('minijson.php');

/* used by structured debugging, see above */
function minijson_encdbg($x, $ri='') {
	return (minijson_encode($x, $ri, 32,
	    defined('JSONDEBUG_TRUNCATE_SIZE') ?
	    constant('JSONDEBUG_TRUNCATE_SIZE') : 0, true));
}

/* autoloader */
spl_autoload_register(function ($cls) {
	static $classlist = NULL;
	if ($classlist === NULL) {
		require_once(dirname(__FILE__) . '/AUTOLDR.php');
	}
	if (isset($classlist[$cls])) {
		require_once('/usr/share/hello-php-world/' . $classlist[$cls]);
	} else {
		util_debugJ('ERR', NULL, NULL, 'cannot autoload class', $cls);
		util_logerr('T', debug_string_backtrace());
	}
    });

/* anything else? */
