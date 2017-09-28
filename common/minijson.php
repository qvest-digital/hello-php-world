<?php
if (count(get_included_files()) === 1 && !defined('__main__'))
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
 * Note that JSON is case-sensitive.  My notes are at:
 * https://www.mirbsd.org/cvs.cgi/contrib/hosted/tg/json.txt?rev=HEAD
 *
 * Call as CLI script to filter input as JSON pretty-printer. Options
 * are -c (compact output, no indentation or spaces), -d depth (parse
 * depth defaulting to 32), -r (pretty-print resources as string) and
 * -t truncsz (truncation size).
 */

/*-
 * I was really, really bad at writing parsers. I still am really bad at
 * writing parsers.
 * -- Rasmus Lerdorf
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

const minijson_stringtable = array(
	'',       '\u0001', '\u0002', '\u0003',
	'\u0004', '\u0005', '\u0006', '\u0007',
	'\b',     '\t',     '\n',     '\u000B',
	'\f',     '\r',     '\u000E', '\u000F',
	'\u0010', '\u0011', '\u0012', '\u0013',
	'\u0014', '\u0015', '\u0016', '\u0017',
	'\u0018', '\u0019', '\u001A', '\u001B',
	'\u001C', '\u001D', '\u001E', '\u001F',
	' ',    '!',    '\"',   '#',    '$',    '%',    '&',    "'",
	'(',    ')',    '*',    '+',    ',',    '-',    '.',    '/',
	'0',    '1',    '2',    '3',    '4',    '5',    '6',    '7',
	'8',    '9',    ':',    ';',    '<',    '=',    '>',    '?',
	'@',    'A',    'B',    'C',    'D',    'E',    'F',    'G',
	'H',    'I',    'J',    'K',    'L',    'M',    'N',    'O',
	'P',    'Q',    'R',    'S',    'T',    'U',    'V',    'W',
	'X',    'Y',    'Z',    '[',    "\\\\", ']',    '^',    '_',
	'`',    'a',    'b',    'c',    'd',    'e',    'f',    'g',
	'h',    'i',    'j',    'k',    'l',    'm',    'n',    'o',
	'p',    'q',    'r',    's',    't',    'u',    'v',    'w',
	'x',    'y',    'z',    '{',    '|',    '}',    '~',    '\u007F',
	'\u0080', '\u0081', '\u0082', '\u0083',
	'\u0084', '\u0085', '\u0086', '\u0087',
	'\u0088', '\u0089', '\u008A', '\u008B',
	'\u008C', '\u008D', '\u008E', '\u008F',
	'\u0090', '\u0091', '\u0092', '\u0093',
	'\u0094', '\u0095', '\u0096', '\u0097',
	'\u0098', '\u0099', '\u009A', '\u009B',
	'\u009C', '\u009D', '\u009E', '\u009F',
	'\u00A0', '\u00A1', '\u00A2', '\u00A3',
	'\u00A4', '\u00A5', '\u00A6', '\u00A7',
	'\u00A8', '\u00A9', '\u00AA', '\u00AB',
	'\u00AC', '\u00AD', '\u00AE', '\u00AF',
	'\u00B0', '\u00B1', '\u00B2', '\u00B3',
	'\u00B4', '\u00B5', '\u00B6', '\u00B7',
	'\u00B8', '\u00B9', '\u00BA', '\u00BB',
	'\u00BC', '\u00BD', '\u00BE', '\u00BF',
	'\u00C0', '\u00C1', '\u00C2', '\u00C3',
	'\u00C4', '\u00C5', '\u00C6', '\u00C7',
	'\u00C8', '\u00C9', '\u00CA', '\u00CB',
	'\u00CC', '\u00CD', '\u00CE', '\u00CF',
	'\u00D0', '\u00D1', '\u00D2', '\u00D3',
	'\u00D4', '\u00D5', '\u00D6', '\u00D7',
	'\u00D8', '\u00D9', '\u00DA', '\u00DB',
	'\u00DC', '\u00DD', '\u00DE', '\u00DF',
	'\u00E0', '\u00E1', '\u00E2', '\u00E3',
	'\u00E4', '\u00E5', '\u00E6', '\u00E7',
	'\u00E8', '\u00E9', '\u00EA', '\u00EB',
	'\u00EC', '\u00ED', '\u00EE', '\u00EF',
	'\u00F0', '\u00F1', '\u00F2', '\u00F3',
	'\u00F4', '\u00F5', '\u00F6', '\u00F7',
	'\u00F8', '\u00F9', '\u00FA', '\u00FB',
	'\u00FC', '\u00FD', '\u00FE', '\u00FF',
    );

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

	if (($dotrunc = ($truncsz && ($Sx > $truncsz)))) {
		/* truncate very long texts */
		$x = substr($x, 0, $truncsz);
		$Sx = $truncsz;
	}

	$rs = '';	/* result */
	$Sp = 0;	/* position */

	/* assume UTF-8 first, for sanity */
 minijson_encode_string_utf8:
	/* read next octet */
	$c = ord($x[$Sp++]);
	/* ASCII? */
	if ($c < 0x80) {
		$rs .= minijson_stringtable[$c];
		if ($Sp < $Sx && $c)
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
	/* note JSON is not binary-safe */

	$rs = '';	/* result */
	$Sp = 0;	/* position */

	while ($Sp < $Sx && ($c = ord($x[$Sp++]))) {
		if ($c >= 0x20 && $c < 0x7F) {
			if ($c === 0x22 || $c === 0x5C)
				$rs .= "\\";
			$rs .= chr($c);
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
 * in:	bool	whether to pretty-print resources as strings
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
function minijson_decode($sj, &$ov, $depth=32) {
	if (!isset($sj) || !$sj) {
		$ov = 'empty input';
		return false;
	}

	/* mb_convert_encoding simply must exist for the decoder */
	$mb_encoding = mb_internal_encoding();
	mb_internal_encoding('UTF-8');

	/* see note about mb_check_encoding in the JSON encoder… */
	$wj = mb_convert_encoding($sj, 'UTF-16LE', 'UTF-8');
	$mj = mb_convert_encoding($wj, 'UTF-8', 'UTF-16LE');
	$rv = ($mj == $sj);
	unset($sj);
	unset($mj);

	if ($rv) {
		/* convert UTF-16LE string to array of wchar_t */
		$j = array();
		foreach (str_split($wj, 2) as $v) {
			$wc = ord($v[0]) | (ord($v[1]) << 8);
			$j[] = $wc;
		}
		$j[] = 0;
		unset($wj);

		/* skip Byte Order Mark if present */
		$p = 0;
		if ($j[$p] == 0xFEFF) {
			unset($j[$p]);
			$p++;
		}

		/* parse recursively */
		$rv = minijson_decode_value($j, $p, $ov, $depth);
	} else {
		$ov = 'input not valid UTF-8';
	}

	if ($rv) {
		/* skip optional whitespace after tokens */
		minijson_skip_wsp($j, $p);

		/* end of string? */
		if ($j[$p] !== 0) {
			/* no, trailing waste */
			$ov = 'expected EOS at wchar #' . $p;
			$rv = false;
		}
	}

	mb_internal_encoding($mb_encoding);
	return $rv;
}

function minijson_skip_wsp(&$j, &$p) {
	/* skip all wide characters that are JSON whitespace */
	do {
		$wc = $j[$p];
		unset($j[$p]);
		++$p;
	} while ($wc == 0x09 || $wc == 0x0A || $wc == 0x0D || $wc == 0x20);
	$p--;
	$j[$p] = $wc;
}

function minijson_get_hexdigit(&$j, &$p, &$v, $i) {
	$wc = $j[$p];
	unset($j[$p]);
	++$p;
	if ($wc >= 0x30 && $wc <= 0x39) {
		$v += $wc - 0x30;
	} elseif ($wc >= 0x41 && $wc <= 0x46) {
		$v += $wc - 0x37;
	} elseif ($wc >= 0x61 && $wc <= 0x66) {
		$v += $wc - 0x57;
	} else {
		$ov = sprintf('invalid hex in unicode escape' .
		    ' sequence (%d) at wchar #%u', $i, $p);
		return false;
	}
	return true;
}

function minijson_decode_array(&$j, &$p, &$ov, $depth) {
	$ov = array();
	$first = true;

	/* I wish there were a goto in PHP… */
	while (true) {
		/* skip optional whitespace between tokens */
		minijson_skip_wsp($j, $p);

		/* end of the array? */
		if ($j[$p] == 0x5D) {
			/* regular exit point for the loop */

			unset($j[$p]);
			$p++;
			return true;
		}

		/* member separator? */
		if ($j[$p] == 0x2C) {
			unset($j[$p]);
			$p++;
			if ($first) {
				/* no comma before the first member */
				$ov = 'unexpected comma at wchar #' . $p;
				return false;
			}
		} elseif (!$first) {
			/*
			 * all but the first member require a separating
			 * comma; this also catches e.g. trailing
			 * rubbish after numbers
			 */
			$ov = 'expected comma at wchar #' . $p;
			return false;
		}
		$first = false;

		/* parse the member value */
		$v = NULL;
		if (!minijson_decode_value($j, $p, $v, $depth)) {
			/* pass through error code */
			$ov = $v;
			return false;
		}
		$ov[] = $v;
	}
}

function minijson_decode_object(&$j, &$p, &$ov, $depth) {
	$ov = array();
	$first = true;

	while (true) {
		/* skip optional whitespace between tokens */
		minijson_skip_wsp($j, $p);

		/* end of the object? */
		if ($j[$p] == 0x7D) {
			/* regular exit point for the loop */

			unset($j[$p]);
			$p++;
			return true;
		}

		/* member separator? */
		if ($j[$p] == 0x2C) {
			unset($j[$p]);
			$p++;
			if ($first) {
				/* no comma before the first member */
				$ov = 'unexpected comma at wchar #' . $p;
				return false;
			}
		} elseif (!$first) {
			/*
			 * all but the first member require a separating
			 * comma; this also catches e.g. trailing
			 * rubbish after numbers
			 */
			$ov = 'expected comma at wchar #' . $p;
			return false;
		}
		$first = false;

		/* skip optional whitespace between tokens */
		minijson_skip_wsp($j, $p);

		/* parse the member key */
		if ($j[$p++] != 0x22) {
			$ov = 'expected key string at wchar #' . $p;
			return false;
		}
		$k = null;
		if (!minijson_decode_string($j, $p, $k)) {
			/* pass through error code */
			$ov = $k;
			return false;
		}

		/* skip optional whitespace between tokens */
		minijson_skip_wsp($j, $p);

		/* key-value separator? */
		if ($j[$p++] != 0x3A) {
			$ov = 'expected colon at wchar #' . $p;
			return false;
		}

		/* parse the member value */
		$v = NULL;
		if (!minijson_decode_value($j, $p, $v, $depth)) {
			/* pass through error code */
			$ov = $v;
			return false;
		}
		$ov[$k] = $v;
	}
}

function minijson_decode_value(&$j, &$p, &$ov, $depth) {
	/* skip optional whitespace between tokens */
	minijson_skip_wsp($j, $p);

	/* parse begin of Value token */
	$wc = $j[$p];
	unset($j[$p]);
	++$p;

	/* style: falling through exits with false */
	if ($wc == 0) {
		$ov = 'unexpected EOS at wchar #' . $p;
	} elseif ($wc == 0x6E) {
		/* literal null? */
		if ($j[$p++] == 0x75 &&
		    $j[$p++] == 0x6C &&
		    $j[$p++] == 0x6C) {
			$ov = NULL;
			return true;
		}
		$ov = 'expected ull after n near wchar #' . $p;
	} elseif ($wc == 0x74) {
		/* literal true? */
		if ($j[$p++] == 0x72 &&
		    $j[$p++] == 0x75 &&
		    $j[$p++] == 0x65) {
			$ov = true;
			return true;
		}
		$ov = 'expected rue after t near wchar #' . $p;
	} elseif ($wc == 0x66) {
		/* literal false? */
		if ($j[$p++] == 0x61 &&
		    $j[$p++] == 0x6C &&
		    $j[$p++] == 0x73 &&
		    $j[$p++] == 0x65) {
			$ov = false;
			return true;
		}
		$ov = 'expected alse after f near wchar #' . $p;
	} elseif ($wc == 0x5B) {
		if (--$depth > 0) {
			return minijson_decode_array($j, $p, $ov, $depth);
		}
		$ov = 'recursion limit exceeded at wchar #' . $p;
	} elseif ($wc == 0x7B) {
		if (--$depth > 0) {
			return minijson_decode_object($j, $p, $ov, $depth);
		}
		$ov = 'recursion limit exceeded at wchar #' . $p;
	} elseif ($wc == 0x22) {
		return minijson_decode_string($j, $p, $ov);
	} elseif ($wc == 0x2D || ($wc >= 0x30 && $wc <= 0x39)) {
		$p--;
		$j[$p] = $wc;
		return minijson_decode_number($j, $p, $ov);
	} else {
		$ov = sprintf('unexpected U+%04X at wchar #%u', $wc, $p);
	}
	return false;
}

function minijson_decode_string(&$j, &$p, &$ov) {
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

function minijson_decode_number(&$j, &$p, &$ov) {
	$s = '';
	$isint = true;

	/* check for an optional minus sign */
	$wc = $j[$p];
	unset($j[$p]);
	++$p;
	if ($wc == 0x2D) {
		$s = '-';
		$wc = $j[$p];
		unset($j[$p]);
		++$p;
	}

	if ($wc == 0x30) {
		/* begins with zero (0 or 0.x) */
		$s .= '0';
		$wc = $j[$p];
		unset($j[$p]);
		++$p;
		if ($wc >= 0x30 && $wc <= 0x39) {
			$ov = 'no leading zeroes please at wchar #' . $p;
			return false;
		}
	} elseif ($wc >= 0x31 && $wc <= 0x39) {
		/* begins with 1‥9 */
		while ($wc >= 0x30 && $wc <= 0x39) {
			$s .= chr($wc);
			$wc = $j[$p];
			unset($j[$p]);
			++$p;
		}
	} else {
		$ov = 'decimal digit expected at wchar #' . $p;
		if ($s[0] != '-') {
			/* we had none, so it’s allowed to prepend one */
			$ov = 'minus sign or ' . $ov;
		}
		return false;
	}

	/* do we have a fractional part? */
	if ($wc == 0x2E) {
		$s .= '.';
		$isint = false;
		$wc = $j[$p];
		unset($j[$p]);
		++$p;
		if ($wc < 0x30 || $wc > 0x39) {
			$ov = 'fractional digit expected at wchar #' . $p;
			return false;
		}
		while ($wc >= 0x30 && $wc <= 0x39) {
			$s .= chr($wc);
			$wc = $j[$p];
			unset($j[$p]);
			++$p;
		}
	}

	/* do we have an exponent, treat number as mantissa? */
	if ($wc == 0x45 || $wc == 0x65) {
		$s .= 'E';
		$isint = false;
		$wc = $j[$p];
		unset($j[$p]);
		++$p;
		if ($wc == 0x2B || $wc == 0x2D) {
			$s .= chr($wc);
			$wc = $j[$p];
			unset($j[$p]);
			++$p;
		}
		if ($wc < 0x30 || $wc > 0x39) {
			$ov = 'exponent digit expected at wchar #' . $p;
			return false;
		}
		while ($wc >= 0x30 && $wc <= 0x39) {
			$s .= chr($wc);
			$wc = $j[$p];
			unset($j[$p]);
			++$p;
		}
	}
	$p--;
	$j[$p] = $wc;

	if ($isint) {
		/* no fractional part, no exponent */

		$v = (int)$s;
		if (strval($v) == $s) {
			$ov = $v;
			return true;
		}
	}
	$ov = (float)$s;
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
