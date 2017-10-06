<?php
if (count(get_included_files()) <= 1 && !defined('__main__'))
	define('__main__', __FILE__);
/**
 * Minimal complete JSON generator and parser for FusionForge/Evolvis
 * and SimKolab, including for debugging output serialisation
 *
 * Copyright © 2010, 2011, 2012, 2014, 2016, 2017
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
 *-
 * Do *not* use PHP’s json_encode because it is broken.
 * Note that JSON is case-sensitive and not binary-safe. My notes at:
 * http://www.mirbsd.org/cvs.cgi/contrib/hosted/tg/code/MirJSON/json.txt?rev=HEAD
 *
 * Call as CLI script to filter input as JSON pretty-printer. Options
 * are -c (compact output, no indentation or spaces), -d depth (parse
 * depth defaulting to 32), -r (pretty-print resources as string) and
 * -t truncsz (truncation size).
 */

/*-
 * I was really, really bad at writing parsers.
 * I still am really bad at writing parsers.
 *  -- Rasmus Lerdorf
 */

/**
 * Encodes an array (indexed or associative) or any value as JSON.
 *
 * in:	array	x (Value to be encoded)
 * in:	string	indent or bool false to skip beautification
 * in:	integer	(optional) recursion depth (default: 32)
 * out:	string	encoded
 */
function minijson_encode($x, $ri='', $depth=32) {
	return (minijson_encode_internal($x, $ri, $depth, 0, false));
}

/**
 * Encodes a string as JSON. NUL terminates strings; strings
 * not comprised of only valid UTF-8 are interpreted as latin1.
 *
 * in:	string	x (Value to be encoded)
 * in:	integer	truncation size (0 to not truncate), makes output not JSON
 * out:	string	encoded
 */
function minijson_encode_string($x, $truncsz=0) {
	if (!is_string($x))
		$x = strval($x);
	if (!($Sx = strlen($x)))
		return '""';

	if (($dotrunc = ($truncsz && ($Sx > $truncsz))))
		$Sx = $truncsz;

	$rs = '';	/* result */
	$Sp = 0;	/* position */

	/* assume UTF-8 first, for sanity */
 minijson_encode_string_utf8:
	/* read next octet */
	$c = ord(($ch = $x[$Sp++]));
	/* ASCII? */
	if ($c < 0x80) {
		if ($c >= 0x20 && $c < 0x7F) {
			if ($c === 0x22 || $c === 0x5C)
				$rs .= "\\" . $ch;
			else
				$rs .= $ch;
		} else switch ($c) {
		case 0x08:
			$rs .= '\b';
			break;
		case 0x09:
			$rs .= '\t';
			break;
		case 0x0A:
			$rs .= '\n';
			break;
		case 0x0C:
			$rs .= '\f';
			break;
		case 0x0D:
			$rs .= '\r';
			break;
		default:
			$rs .= sprintf('\u%04X', $c);
			break;
		case 0x00:
			goto minijson_encode_string_done;
		}
		if ($Sp < $Sx)
			goto minijson_encode_string_utf8;
		goto minijson_encode_string_done;
	}
	/* lead byte */
	if ($c < 0xC2 || $c >= 0xF8) {
		goto minijson_encode_string_latin1;
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
	/* trail bytes */
	if ($Sp + $Ss > $Sx)
		goto minijson_encode_string_latin1;
	while ($Ss--)
		if (($c = ord($x[$Sp++]) ^ 0x80) <= 0x3F)
			$wc |= $c << (6 * $Ss);
		else
			goto minijson_encode_string_latin1;
	/* complete wide character */
	if ($wc < $wmin)
		goto minijson_encode_string_latin1;

	if ($wc < 0x00A0)
		$rs .= sprintf('\u%04X', $wc);
	elseif ($wc < 0x0800)
		$rs .= chr(0xC0 | ($wc >> 6)) .
		    chr(0x80 | ($wc & 0x3F));
	elseif ($wc > 0xFFFD || ($wc >= 0xD800 && $wc <= 0xDFFF) ||
	    ($wc >= 0x2028 && $wc <= 0x2029)) {
		if ($wc > 0xFFFF) {
			if ($wc > 0x10FFFF)
				goto minijson_encode_string_latin1;
			/* UTF-16 */
			$wc -= 0x10000;
			$rs .= sprintf('\u%04X\u%04X',
			    0xD800 | ($wc >> 10),
			    0xDC00 | ($wc & 0x03FF));
		} else
			$rs .= sprintf('\u%04X', $wc);
	} else
		$rs .= chr(0xE0 | ($wc >> 12)) .
		    chr(0x80 | (($wc >> 6) & 0x3F)) .
		    chr(0x80 | ($wc & 0x3F));

	/* process next char */
	if ($Sp < $Sx)
		goto minijson_encode_string_utf8;
	goto minijson_encode_string_done;

 minijson_encode_string_latin1:
	/* failed, interpret as sorta latin1 but display only ASCII */

	$rs = '';	/* result */
	$Sp = 0;	/* position */

	while ($Sp < $Sx && ($c = ord(($ch = $x[$Sp++])))) {
		if ($c >= 0x20 && $c < 0x7F) {
			if ($c === 0x22 || $c === 0x5C)
				$rs .= "\\" . $ch;
			else
				$rs .= $ch;
		} else switch ($c) {
		case 0x08:
			$rs .= '\b';
			break;
		case 0x09:
			$rs .= '\t';
			break;
		case 0x0A:
			$rs .= '\n';
			break;
		case 0x0C:
			$rs .= '\f';
			break;
		case 0x0D:
			$rs .= '\r';
			break;
		default:
			$rs .= sprintf('\u%04X', $c);
			break;
		}
	}
 minijson_encode_string_done:
	return ($dotrunc ? 'TOO_LONG_STRING_TRUNCATED:"' : '"') . $rs . '"';
}

/**
 * Encodes content as JSON for debugging (not round-trip safe).
 *
 * in:	array	x (Value to be encoded)
 * in:	string	indent or bool false to skip beautification
 * in:	integer	recursion depth
 * in:	integer	truncation size (0 to not truncate), makes output not JSON
 * in:	bool	whether to pretty-print resources
 * out:	string	encoded
 */
function minijson_encode_internal($x, $ri, $depth, $truncsz, $dumprsrc) {
	if (!$depth-- || !isset($x) || is_null($x) || (is_float($x) &&
	    (is_nan($x) || is_infinite($x))))
		return 'null';

	if ($x === true)
		return 'true';
	if ($x === false)
		return 'false';

	if (is_int($x)) {
		$y = (int)$x;
		$z = strval($y);
		$x = strval($x);
		if ($x === $z)
			return $z;
	}

	if (is_float($x)) {
		$rs = sprintf('%.14e', $x);
		$v = explode('e', $rs);
		$rs = rtrim($v[0], '0');
		if (substr($rs, -1) === '.')
			$rs .= '0';
		if ($v[1] !== '-0' && $v[1] !== '+0')
			$rs .= 'E' . $v[1];
		return $rs;
	}

	/* strings or unknown scalars */
	if (is_string($x) ||
	    (!is_array($x) && !is_object($x) && is_scalar($x)))
		return minijson_encode_string($x, $truncsz);

	/* arrays, objects, resources, unknown non-scalars */

	if ($ri === false) {
		$si = false;
		$xi = '';
		$xr = '';
		$Sd = ':';
	} else {
		$si = $ri . '  ';
		$xi = "\n" . $si;
		$xr = "\n" . $ri;
		$Sd = ': ';
	}
	$Si = ',' . $xi;

	/* arrays, potentially empty or non-associative */
	if (is_array($x)) {
		if (!($n = count($x)))
			return '[]';
		$rs = '[';
		for ($v = 0; $v < $n; ++$v) {
			if (!array_key_exists($v, $x))
				goto minijson_encode_object;
			$rs .= $xi . minijson_encode_internal($x[$v],
			    $si, $depth, $truncsz, $dumprsrc);
			$xi = $Si;
		}
		return $rs . $xr . ']';
	}

	/* http://de2.php.net/manual/en/function.is-resource.php#103942 */
	if (!is_null($rsrctype = @get_resource_type($x))) {
		if (!$dumprsrc)
			return minijson_encode_string($x, $truncsz);
		$rs = array(
			'_strval' => strval($x),
			'_type' => $rsrctype,
		);
		if ($rsrctype === 'stream')
			$rs['stream_meta'] = stream_get_meta_data($x);
		return '{' . $xi . '"\u0000resource"' . $Sd .
		    minijson_encode_internal($rs, $si, $depth + 1,
		    $truncsz, $dumprsrc) . $xr . '}';
	}

	/* treat everything else as Object */

	/* PHP objects are mostly like associative arrays */
	if (!($x = (array)$x))
		return '{}';
 minijson_encode_object:
	$s = array();
	foreach (array_keys($x) as $k) {
		$v = $k;
		if (!is_string($v))
			$v = strval($v);
		/* protected and private members have NULs there */
		if (strpos($v, "\0") !== false)
			$v = str_replace("\0", "\\", $v);
		$s[$k] = $v;
	}
	asort($s, SORT_STRING);
	$rs = '{';
	foreach ($s as $k => $v) {
		$rs .= $xi . minijson_encode_string($v, $truncsz) . $Sd .
		    minijson_encode_internal($x[$k],
		    $si, $depth, $truncsz, $dumprsrc);
		$xi = $Si;
	}
	return $rs . $xr . '}';
}

/**
 * Decodes a UTF-8 string from JSON (ECMA 262).
 *
 * in:	string	json
 * in:	reference output-variable (or error string)
 * in:	integer	(optional) recursion depth (default: 32)
 * out:	boolean	false if an error occured, true = output is valid
 */
function minijson_decode($s, &$ov, $depth=32) {
	if (!isset($s))
		$s = '';
	elseif (!is_string($s))
		$s = strval($s);

	$Sp = 0;
	$Sx = strlen($s);
	$rv = false;

	/* skip Byte Order Mark if present */
	if (substr($s, 0, 3) === "\xEF\xBF\xBD")
		$Sp = 3;

	/* skip leading whitespace */
	minijson_skip_wsp($s, $Sp, $Sx);

	/* recursively parse input */
	if ($Sp < $Sx)
		$rv = minijson_decode_value($s, $Sp, $Sx, $ov, $depth);
	else
		$ov = 'empty input';

	/* skip trailing whitespace */
	if ($rv) {
		minijson_skip_wsp($s, $Sp, $Sx);
		/* end of string? */
		if ($Sp < $Sx) {
			$ov = 'expected EOS';
			$rv = false;
		}
	}

	/* amend errors by erroring offset */
	if (!$rv)
		$ov = sprintf('%s at offset 0x%0' . strlen(dechex($Sx)) . 'X',
		    $ov, $Sp);
	return $rv;
}

/* skip all characters that are JSON whitespace */
function minijson_skip_wsp($s, &$Sp, $Sx) {
	while ($Sp < $Sx)
		switch (ord($s[$Sp])) {
		default:
			return;
		case 0x09:
		case 0x0A:
		case 0x0D:
		case 0x20:
			++$Sp;
		}
}

function minijson_get_hexdigit($s, &$Sp, &$v, $i) {
	switch (ord($s[$Sp++])) {
	case 0x30:			  return true;
	case 0x31:		$v +=  1; return true;
	case 0x32:		$v +=  2; return true;
	case 0x33:		$v +=  3; return true;
	case 0x34:		$v +=  4; return true;
	case 0x35:		$v +=  5; return true;
	case 0x36:		$v +=  6; return true;
	case 0x37:		$v +=  7; return true;
	case 0x38:		$v +=  8; return true;
	case 0x39: 		$v +=  9; return true;
	case 0x41: case 0x61:	$v += 10; return true;
	case 0x42: case 0x62:	$v += 11; return true;
	case 0x43: case 0x63:	$v += 12; return true;
	case 0x44: case 0x64:	$v += 13; return true;
	case 0x45: case 0x65:	$v += 14; return true;
	case 0x46: case 0x66:	$v += 15; return true;
	}
	$ov = "invalid hex in unicode escape sequence ($i)";
	return false;
}

function minijson_decode_array($s, &$Sp, $Sx, &$ov, $depth) {
	$ov = array();

	/* skip optional whitespace between tokens */
	minijson_skip_wsp($s, $Sp, $Sx);

	/* check for end of array or first member */
	if ($Sp >= $Sx) {
 minijson_decode_array_eos:
		$ov = 'unexpected EOS in Array';
		return false;
	}
	switch ($s[$Sp]) {
	case ',':
		$ov = 'unexpected leading comma in Array';
		return false;
	case ']':
		++$Sp;
		return true;
	}

	goto minijson_decode_array_member;

 minijson_decode_array_loop:
	/* skip optional whitespace between tokens */
	minijson_skip_wsp($s, $Sp, $Sx);

	/* check for end of array or next member */
	if ($Sp >= $Sx)
		goto minijson_decode_array_eos;
	switch ($s[$Sp++]) {
	case ']':
		return true;
	case ',':
		break;
	default:
		--$Sp;
		$ov = 'missing comma in Array';
		return false;
	}

 minijson_decode_array_member:
	/* parse the member value */
	$v = NULL;
	if (!minijson_decode_value($s, $Sp, $Sx, $v, $depth)) {
		/* pass through error code */
		$ov = $v;
		return false;
	}
	/* consume, rinse, repeat */
	$ov[] = $v;
	goto minijson_decode_array_loop;
}

function minijson_decode_object($s, &$Sp, $Sx, &$ov, $depth) {
	$ov = array();
	/* skip optional whitespace between tokens */
	minijson_skip_wsp($s, $Sp, $Sx);

	/* check for end of object or first member */
	if ($Sp >= $Sx) {
 minijson_decode_object_eos:
		$ov = 'unexpected EOS in Object';
		return false;
	}
	switch ($s[$Sp]) {
	case ',':
		$ov = 'unexpected leading comma in Object';
		return false;
	case '}':
		++$Sp;
		return true;
	}

	goto minijson_decode_object_member;

 minijson_decode_object_loop:
	/* skip optional whitespace between tokens */
	minijson_skip_wsp($s, $Sp, $Sx);

	/* check for end of object or next member */
	if ($Sp >= $Sx)
		goto minijson_decode_object_eos;
	switch ($s[$Sp++]) {
	case '}':
		return true;
	case ',':
		break;
	default:
		--$Sp;
		$ov = 'missing comma in Object';
		return false;
	}

 minijson_decode_object_member:
	/* skip optional whitespace between tokens */
	minijson_skip_wsp($s, $Sp, $Sx);

	/* look for the member key */
	if ($Sp >= $Sx)
		goto minijson_decode_object_eos;
	if ($s[$Sp++] !== '"') {
		--$Sp;
		$ov = 'expected key string for Object member';
		return false;
	}
	$k = NULL;
	if (!minijson_decode_string($s, $Sp, $Sx, $k)) {
		/* pass through error code */
		$ov = $k;
		return false;
	}

	/* skip optional whitespace between tokens */
	minijson_skip_wsp($s, $Sp, $Sx);

	/* check for separator between key and value */
	if ($Sp >= $Sx)
		goto minijson_decode_object_eos;
	if ($s[$Sp++] !== ':') {
		--$Sp;
		$ov = 'expected colon in Object member';
		return false;
	}

	/* parse the member value */
	$v = NULL;
	if (!minijson_decode_value($s, $Sp, $Sx, $v, $depth)) {
		/* pass through error code */
		$ov = $v;
		return false;
	}
	/* consume, rinse, repeat */
	$ov[$k] = $v;
	goto minijson_decode_object_loop;
}

function minijson_decode_value($s, &$Sp, $Sx, &$ov, $depth) {
	/* skip optional whitespace between tokens */
	minijson_skip_wsp($s, $Sp, $Sx);

	/* parse begin of Value token */
	if ($Sp >= $Sx) {
		$ov = 'unexpected EOS, Value expected';
		return false;
	}
	$c = $s[$Sp++];

	/* style: falling through exits with false */
	if ($c === 'n') {
		/* literal null? */
		if (substr($s, $Sp, 3) === 'ull') {
			$Sp += 3;
			$ov = NULL;
			return true;
		}
		$ov = 'expected “ull” after “n”';
	} elseif ($c === 't') {
		/* literal true? */
		if (substr($s, $Sp, 3) === 'rue') {
			$Sp += 3;
			$ov = true;
			return true;
		}
		$ov = 'expected “rue” after “t”';
	} elseif ($c === 'f') {
		/* literal false? */
		if (substr($s, $Sp, 4) === 'alse') {
			$Sp += 4;
			$ov = false;
			return true;
		}
		$ov = 'expected “alse” after “f”';
	} elseif ($c === '[') {
		if (--$depth > 0)
			return minijson_decode_array($s, $Sp, $Sx, $ov, $depth);
		$ov = 'recursion limit exceeded by Array';
	} elseif ($c === '{') {
		if (--$depth > 0)
			return minijson_decode_object($s, $Sp, $Sx, $ov, $depth);
		$ov = 'recursion limit exceeded by Object';
	} elseif ($c === '"') {
		return minijson_decode_string($s, $Sp, $Sx, $ov);
	} elseif ($c === '-' || (ord($c) >= 0x30 && ord($c) < 0x39)) {
		--$Sp;
		return minijson_decode_number($s, $Sp, $Sx, $ov);
	} elseif (ord($c) >= 0x20 && ord($c) <= 0x7E) {
		--$Sp;
		$ov = "unexpected “{$c}”, Value expected";
	} else {
		--$Sp;
		$ov = sprintf('unexpected 0x%02X, Value expected', ord($c));
	}
	return false;
}

function minijson_decode_string($s, &$Sp, $Sx, &$ov) {
	/* XXX incorrect, just for testing */
	$se = strpos($s, '"', $Sp);
	if ($se === false || $se < $Sp) {
		$ov = 'cannot find end of string';
		return false;
	}
	$ov = substr($s, $Sp, $se - $Sp);
	$Sp = $se + 1;
	return true;

	/* XXX old code: */

	/* UTF-16LE string buffer */
	$s = '';

	while (true) {
		$wc = $j[$p];
		unset($j[$p]);
		++$p;
		if ($wc < 0x20) {
			$ov = 'unescaped control character $wc at wchar #' . $p;
			return false;
		} elseif ($wc == 0x22) {
			/* regular exit point for the loop */

			/* convert to UTF-8, then re-check against UTF-16 */
			$ov = mb_convert_encoding($s, 'UTF-8', 'UTF-16LE');
			$tmp = mb_convert_encoding($ov, 'UTF-16LE', 'UTF-8');
			if ($tmp !== $s) {
				$ov = 'no Unicode string before wchar #' . $p;
				return false;
			}
			return true;
		} elseif ($wc == 0x5C) {
			$wc = $j[$p];
			unset($j[$p]);
			++$p;
			if ($wc == 0x22 ||
			    $wc == 0x2F ||
			    $wc == 0x5C) {
				$s .= chr($wc) . chr(0);
			} elseif ($wc == 0x62) {
				$s .= chr(0x08) . chr(0);
			} elseif ($wc == 0x66) {
				$s .= chr(0x0C) . chr(0);
			} elseif ($wc == 0x6E) {
				$s .= chr(0x0A) . chr(0);
			} elseif ($wc == 0x72) {
				$s .= chr(0x0D) . chr(0);
			} elseif ($wc == 0x74) {
				$s .= chr(0x09) . chr(0);
			} elseif ($wc == 0x75) {
				$v = 0;
				for ($tmp = 1; $tmp <= 4; $tmp++) {
					$v <<= 4;
					if (!minijson_get_hexdigit($j, $p,
					    $v, $tmp)) {
						/* pass through error code */
						return false;
					}
				}
				if ($v < 1 || $v > 0xFFFD) {
					$ov = 'non-Unicode escape $v before wchar #' . $p;
					return false;
				}
				$s .= chr($v & 0xFF) . chr($v >> 8);
			} else {
				$ov = 'invalid escape sequence at wchar #' . $p;
				return false;
			}
		} elseif ($wc > 0xD7FF && $wc < 0xE000) {
			$ov = 'surrogate $wc at wchar #' . $p;
			return false;
		} elseif ($wc > 0xFFFD) {
			$ov = 'non-Unicode char $wc at wchar #' . $p;
			return false;
		} else {
			$s .= chr($wc & 0xFF) . chr($wc >> 8);
		}
	}
}

function minijson_decode_number($s, &$Sp, $Sx, &$ov) {
	$matches = array('');
	if (!preg_match('/-?(?:0|[1-9][0-9]*)(?:\.[0-9]+)?(?:[Ee][+-]?[0-9]+)?/A',
	    $s, $matches, 0, $Sp) || strlen($matches[0]) < 1) {
		$ov = 'expected Number';
		return false;
	}
	$Sp += strlen($matches[0]);
	if (strpos($matches[0], '.') === false) {
		/* possible integer */
		$ov = (int)$matches[0];
		if (strval($ov) === $matches[0])
			return true;
	}
	$ov = (float)$matches[0];
	return true;
}

if (defined('__main__') && constant('__main__') === __FILE__) {
	function usage($rc=1) {
		fwrite(STDERR,
		    "Syntax: minijson.php [-cr] [-d depth] [-t truncsz]\n");
		exit($rc);
	}

	$indent = '';
	$depth = 32;
	$truncsz = 0;
	$rsrc = false;
	array_shift($argv);	/* argv[0] */
	while (count($argv)) {
		$arg = array_shift($argv);
		/* only options, no arguments (Unix filter) */
		if ($arg[0] !== '-')
			usage();
		if ($arg === '--' && count($argv))
			usage();
		if ($arg === '-')
			usage();
		$arg = str_split($arg);
		array_shift($arg);	/* initial ‘-’ */
		/* parse select arguments */
		while (count($arg)) {
			switch ($arg[0]) {
			case 'c':
				$indent = false;
				break;
			case 'd':
				if (!count($argv))
					usage();
				$depth = array_shift($argv);
				if (!preg_match('/^[1-9][0-9]*$/', $depth))
					usage();
				if (strval((int)$depth) !== $depth)
					usage();
				$depth = (int)$depth;
				break;
			case 'h':
			case '?':
				usage(0);
			case 'r':
				$rsrc = true;
				break;
			case 't':
				if (!count($argv))
					usage();
				$truncsz = array_shift($argv);
				if (!preg_match('/^[1-9][0-9]*$/', $truncsz))
					usage();
				if (strval((int)$truncsz) !== $truncsz)
					usage();
				$truncsz = (int)$truncsz;
				break;
			default:
				usage();
			}
			array_shift($arg);
		}
	}

	$idat = file_get_contents('php://stdin');
	$odat = '';
	if (!minijson_decode($idat, $odat, $depth)) {
		fwrite(STDERR, 'JSON decoding of input failed: ' .
		    minijson_encode(array(
			'input' => $idat,
			'message' => $odat,
		    )) . "\n");
		exit(1);
	}
	fwrite(STDOUT, minijson_encode_internal($odat, $indent, $depth,
	    $truncsz, $rsrc) . "\n");
	exit(0);
}
