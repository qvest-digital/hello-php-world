<?php
if (count(get_included_files()) === 1) define('__main__', __FILE__);
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
 * Encodes an array (indexed or associative) as JSON.
 * Strings not comprised of only valid UTF-8 are interpreted as latin1;
 * NUL terminates strings.
 *
 * in:	array x (Value to be encoded)
 * in:	string indent or bool false to skip beautification
 * in:	integer	(optional) recursion depth (default: 32)
 * out:	string encoded
 */
function minijson_encode($x, $ri='', $depth=32) {
	return (minijson_encode_internal($x, $ri, $depth, 0, false));
}

/**
 * Encodes content as JSON for debugging (not round-trip safe).
 *
 * in:	array x (Value to be encoded)
 * in:	string indent or bool false to skip beautification
 * in:	integer	recursion depth
 * in:	integer truncation size (0 to not truncate), makes output not JSON
 * in:	bool whether to pretty-print resources as strings
 * out:	string encoded
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

	if (is_string($x)) {
		if ($truncsz && (strlen($x) > $truncsz)) {
			/* truncate very long texts */
			$rs = 'TOO_LONG_STRING_TRUNCATED:"';
			$x = substr($x, 0, $truncsz);
		} else
			$rs = '"';
		/* NUL terminates */
		$x .= "\0";
		/* assume UTF-8 first, for sanity */
		$Ss = 0; /* state */
		$Sp = 0; /* position */
		$wc = 0; /* wide character */
		$wnext = 0;
 minijson_encode_string_utf8:
		/* read next octet */
		if (($c = ord($x[$Sp++])) === 0) {
			/* NUL */
			if ($Ss !== 0)
				goto minijson_encode_string_latin1;
			return $rs.'"';
		}
		if ($Ss === 0) {
			/* lead byte */
			if ($c < 0x80) {
				$wc = $c;
				$wmin = 0;
			} elseif ($c < 0xC2 || $c >= 0xF8) {
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
		} else if (($c ^= 0x80) > 0x3F) {
			goto minijson_encode_string_latin1;
		} else {
			/* trail byte */
			--$Ss;
			$wc |= $c << (6 * $Ss);
		}
		if ($Ss !== 0)
			goto minijson_encode_string_utf8;
		/* complete wide character */
		if ($wc < $wmin || $wc > 0x10FFFF)
			goto minijson_encode_string_latin1;
		/* UTF-16 */
		if ($wc > 0xFFFF) {
			$wc -= 0x10000;
			$wnext = 0xDC00 | ($wc & 0x03FF);
			$wc = 0xD800 | ($wc >> 10);
		} else
			$wnext = 0;
 minijson_encode_string_utf16:
		/* process UTF-16 char */
		switch ($wc) {
		case 8:
			$rs .= '\b';
			break;
		case 9:
			$rs .= '\t';
			break;
		case 10:
			$rs .= '\n';
			break;
		case 12:
			$rs .= '\f';
			break;
		case 13:
			$rs .= '\r';
			break;
		case 34:
			$rs .= '\"';
			break;
		case 92:
			$rs .= '\\\\';
			break;
		default:
			if ($wc < 0x20 || ($wc > 0x7E && $wc < 0xA0) ||
			    ($wc >= 0xD800 && $wc <= 0xDFFF) || $wc > 0xFFFD)
				$rs .= sprintf('\u%04X', $wc);
			elseif ($wc < 0x0080)
				$rs .= chr($wc);
			elseif ($wc < 0x0800)
				$rs .= chr(0xC0 | ($wc >> 6)) .
				    chr(0x80 | ($wc & 0x3F));
			else
				$rs .= chr(0xE0 | ($wc >> 12)) .
				    chr(0x80 | (($wc >> 6) & 0x3F)) .
				    chr(0x80 | ($wc & 0x3F));
			break;
		}
		/* process next UTF-16 char */
		if ($wnext !== 0) {
			$wc = $wnext;
			$wnext = 0;
			goto minijson_encode_string_utf16;
		}
		goto minijson_encode_string_utf8;

 minijson_encode_string_latin1:
		/* failed, interpret as sorta latin1 but binary */
		/* note JSON is not binary-safe */
		$rs = $rs[0] === 'T' ? 'TOO_LONG_STRING_TRUNCATED:"' : '"';
		$Sp = 0;
		while (($wc = ord($x[$Sp++])) !== 0) switch ($wc) {
		case 8:
			$rs .= '\b';
			break;
		case 9:
			$rs .= '\t';
			break;
		case 10:
			$rs .= '\n';
			break;
		case 12:
			$rs .= '\f';
			break;
		case 13:
			$rs .= '\r';
			break;
		case 34:
			$rs .= '\"';
			break;
		case 92:
			$rs .= '\\\\';
			break;
		default:
			if ($wc < 0x20 || $wc > 0x7E)
				$rs .= sprintf('\u%04X', $wc);
			else
				$rs .= chr($wc);
			break;
		}
		return $rs.'"';
	}

	$si = $ri === false ? false : $ri . '  ';

	if (is_array($x)) {
		if (!($k = array_keys($x)))
			return '[]';

		$isnum = true;
		foreach ($k as $v) {
			if (is_int($v)) {
				$y = (int)$v;
				$z = strval($y);
				if ($v != $z) {
					$isnum = false;
					break;
				}
			} else {
				$isnum = false;
				break;
			}
		}

		if ($isnum) {
			/* all array keys are integers */
			$s = $k;
			sort($s, SORT_NUMERIC);
			/* test keys for order and delta */
			$y = 0;
			foreach ($s as $v) {
				if ($v !== $y) {
					$isnum = false;
					break;
				}
				$y++;
			}
		}

		if ($isnum) {
			/* all array keys are integers 0‥n */
			if ($ri === false) {
				$rs = '[';
				$Si = ',';
			} else {
				$rs = "[\n";
				$Si = ",\n" . $si;
			}
			$xi = '';
			foreach ($s as $v) {
				$rs .= $xi . minijson_encode_internal($x[$v],
				    $si, $depth, $truncsz, $dumprsrc);
				$xi = $Si;
			}
			if ($ri !== false)
				$rs .= "\n" . $ri;
			return $rs.']';
		}

		sort($k, SORT_STRING);
		if ($ri === false) {
			$rs = '{';
			$Si = ',';
			$Sd = ':';
		} else {
			$rs = "{\n";
			$Si = ",\n" . $si;
			$Sd = ': ';
		}
		$xi = '';
		foreach ($k as $v) {
			$rs .= $xi . minijson_encode_internal(strval($v),
			    false, $depth, $truncsz, $dumprsrc) .
			    $Sd . minijson_encode_internal($x[$v],
			    $si, $depth, $truncsz, $dumprsrc);
			$xi = $Si;
		}
		if ($ri !== false)
			$rs .= "\n" . $ri;
		return $rs.'}';
	}

	if (is_object($x)) {
		/* PHP objects are mostly like associative arrays */
		$x = (array)$x;
		$k = array();
		foreach (array_keys($x) as $v) {
			/* protected and private members have NULs there */
			$k[$v] = preg_replace('/^\0([a-zA-Z_\x7F-\xFF][a-zA-Z0-9_\x7F-\xFF]*|\*)\0(.)/',
			    '\\\\$1\\\\$2', strval($v));
		}
		if (!$k) {
			return '{}';
		}
		asort($k, SORT_STRING);
		if ($ri === false) {
			$rs = '{';
			$Si = ',';
			$Sd = ':';
		} else {
			$rs = "{\n";
			$Si = ",\n" . $si;
			$Sd = ': ';
		}
		$xi = '';
		foreach ($k as $v => $s) {
			$rs .= $xi . minijson_encode_internal($s,
			    false, $depth, $truncsz, $dumprsrc) .
			    $Sd . minijson_encode_internal($x[$v],
			    $si, $depth, $truncsz, $dumprsrc);
			$xi = $Si;
		}
		if ($ri !== false)
			$rs .= "\n" . $ri;
		return $rs.'}';
	}

	/* http://de2.php.net/manual/en/function.is-resource.php#103942 */
	if ($dumprsrc && !is_null($rsrctype = @get_resource_type($x))) {
		$k = strval($rsrctype);
		$rs = '{';
		if ($ri !== false)
			$rs .= "\n" . $ri . '  ';
		$rs .= '"\u0000resource":';
		if ($ri !== false)
			$rs .= ' ';
		$rs .= minijson_encode_internal($k,
		    false, $depth, $truncsz, $dumprsrc);
		if ($ri !== false)
			$rs .= "\n" . $ri;
		return $rs.'}';
	}

	/* treat everything else as array or string */
	return minijson_encode_internal(is_scalar($x) ? strval($x) : (array)$x,
	    $ri, $depth, $truncsz, $dumprsrc);
}

/**
 * Decodes a UTF-8 string from JSON (ECMA 262).
 *
 * in:	string json
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
