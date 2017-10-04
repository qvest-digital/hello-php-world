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
	$c = ord($x[$Sp++]);
	/* ASCII? */
	if ($c < 0x80) {
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
function minijson_decode($sj, &$ov, $depth=32) {
	$decoder = new MiniJSON_decoder($sj, $depth);
	$rv = $decoder->status;
	$ov = $decoder->output;
	return $rv;
}

class MiniJSON_decoder {
	function __construct($sj, $depth) {
		$this->status = false;

		if (!isset($sj) || !$sj) {
			$this->output = 'empty input';
			return;
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
			$this->j = array();
			foreach (str_split($wj, 2) as $v) {
				$wc = ord($v[0]) | (ord($v[1]) << 8);
				$this->j[] = $wc;
			}
			$this->j[] = 0;
			unset($wj);

			/* skip Byte Order Mark if present */
			$this->p = 0;
			if ($this->j[$this->p] == 0xFEFF) {
				unset($this->j[$this->p]);
				$this->p++;
			}

			/* parse recursively */
			$rv = $this->decode_value($this->output, $depth);
		} else {
			$this->output = 'input not valid UTF-8';
		}

		if ($rv) {
			/* skip optional whitespace after tokens */
			$this->skip_wsp();

			/* end of string? */
			if ($this->j[$this->p] !== 0) {
				/* no, trailing waste */
				$this->output = 'expected EOS at wchar #' . $this->p;
				$rv = false;
			}
		}

		mb_internal_encoding($mb_encoding);
		$this->status = $rv;
	}

	function skip_wsp() {
		/* skip all wide characters that are JSON whitespace */
		do {
			$wc = $this->j[$this->p];
			unset($this->j[$this->p]);
			++$this->p;
		} while ($wc == 0x09 || $wc == 0x0A || $wc == 0x0D || $wc == 0x20);
		$this->p--;
		$this->j[$this->p] = $wc;
	}

	function get_hexdigit(&$v, $i) {
		$wc = $this->j[$this->p];
		unset($this->j[$this->p]);
		++$this->p;
		if ($wc >= 0x30 && $wc <= 0x39) {
			$v += $wc - 0x30;
		} elseif ($wc >= 0x41 && $wc <= 0x46) {
			$v += $wc - 0x37;
		} elseif ($wc >= 0x61 && $wc <= 0x66) {
			$v += $wc - 0x57;
		} else {
			$ov = sprintf('invalid hex in unicode escape' .
			    ' sequence (%d) at wchar #%u', $i, $this->p);
			return false;
		}
		return true;
	}

	function decode_array(&$ov, $depth) {
		$ov = array();
		$first = true;

		/* I wish there were a goto in PHP… */
		while (true) {
			/* skip optional whitespace between tokens */
			$this->skip_wsp();

			/* end of the array? */
			if ($this->j[$this->p] == 0x5D) {
				/* regular exit point for the loop */

				unset($this->j[$this->p]);
				$this->p++;
				return true;
			}

			/* member separator? */
			if ($this->j[$this->p] == 0x2C) {
				unset($this->j[$this->p]);
				$this->p++;
				if ($first) {
					/* no comma before the first member */
					$ov = 'unexpected comma at wchar #' . $this->p;
					return false;
				}
			} elseif (!$first) {
				/*
				 * all but the first member require a separating
				 * comma; this also catches e.g. trailing
				 * rubbish after numbers
				 */
				$ov = 'expected comma at wchar #' . $this->p;
				return false;
			}
			$first = false;

			/* parse the member value */
			$v = NULL;
			if (!$this->decode_value($v, $depth)) {
				/* pass through error code */
				$ov = $v;
				return false;
			}
			$ov[] = $v;
		}
	}

	function decode_object(&$ov, $depth) {
		$ov = array();
		$first = true;

		while (true) {
			/* skip optional whitespace between tokens */
			$this->skip_wsp();

			/* end of the object? */
			if ($this->j[$this->p] == 0x7D) {
				/* regular exit point for the loop */

				unset($this->j[$this->p]);
				$this->p++;
				return true;
			}

			/* member separator? */
			if ($this->j[$this->p] == 0x2C) {
				unset($this->j[$this->p]);
				$this->p++;
				if ($first) {
					/* no comma before the first member */
					$ov = 'unexpected comma at wchar #' . $this->p;
					return false;
				}
			} elseif (!$first) {
				/*
				 * all but the first member require a separating
				 * comma; this also catches e.g. trailing
				 * rubbish after numbers
				 */
				$ov = 'expected comma at wchar #' . $this->p;
				return false;
			}
			$first = false;

			/* skip optional whitespace between tokens */
			$this->skip_wsp();

			/* parse the member key */
			if ($this->j[$this->p++] != 0x22) {
				$ov = 'expected key string at wchar #' . $this->p;
				return false;
			}
			$k = null;
			if (!$this->decode_string($k)) {
				/* pass through error code */
				$ov = $k;
				return false;
			}

			/* skip optional whitespace between tokens */
			$this->skip_wsp();

			/* key-value separator? */
			if ($this->j[$this->p++] != 0x3A) {
				$ov = 'expected colon at wchar #' . $this->p;
				return false;
			}

			/* parse the member value */
			$v = NULL;
			if (!$this->decode_value($v, $depth)) {
				/* pass through error code */
				$ov = $v;
				return false;
			}
			$ov[$k] = $v;
		}
	}

	function decode_value(&$ov, $depth) {
		/* skip optional whitespace between tokens */
		$this->skip_wsp();

		/* parse begin of Value token */
		$wc = $this->j[$this->p];
		unset($this->j[$this->p]);
		++$this->p;

		/* style: falling through exits with false */
		if ($wc == 0) {
			$ov = 'unexpected EOS at wchar #' . $this->p;
		} elseif ($wc == 0x6E) {
			/* literal null? */
			if ($this->j[$this->p++] == 0x75 &&
			    $this->j[$this->p++] == 0x6C &&
			    $this->j[$this->p++] == 0x6C) {
				$ov = NULL;
				return true;
			}
			$ov = 'expected ull after n near wchar #' . $this->p;
		} elseif ($wc == 0x74) {
			/* literal true? */
			if ($this->j[$this->p++] == 0x72 &&
			    $this->j[$this->p++] == 0x75 &&
			    $this->j[$this->p++] == 0x65) {
				$ov = true;
				return true;
			}
			$ov = 'expected rue after t near wchar #' . $this->p;
		} elseif ($wc == 0x66) {
			/* literal false? */
			if ($this->j[$this->p++] == 0x61 &&
			    $this->j[$this->p++] == 0x6C &&
			    $this->j[$this->p++] == 0x73 &&
			    $this->j[$this->p++] == 0x65) {
				$ov = false;
				return true;
			}
			$ov = 'expected alse after f near wchar #' . $this->p;
		} elseif ($wc == 0x5B) {
			if (--$depth > 0) {
				return $this->decode_array($ov, $depth);
			}
			$ov = 'recursion limit exceeded at wchar #' . $this->p;
		} elseif ($wc == 0x7B) {
			if (--$depth > 0) {
				return $this->decode_object($ov, $depth);
			}
			$ov = 'recursion limit exceeded at wchar #' . $this->p;
		} elseif ($wc == 0x22) {
			return $this->decode_string($ov);
		} elseif ($wc == 0x2D || ($wc >= 0x30 && $wc <= 0x39)) {
			$this->p--;
			$this->j[$this->p] = $wc;
			return $this->decode_number($ov);
		} else {
			$ov = sprintf('unexpected U+%04X at wchar #%u', $wc, $this->p);
		}
		return false;
	}

	function decode_string(&$ov) {
		/* UTF-16LE string buffer */
		$s = '';

		while (true) {
			$wc = $this->j[$this->p];
			unset($this->j[$this->p]);
			++$this->p;
			if ($wc < 0x20) {
				$ov = 'unescaped control character $wc at wchar #' . $this->p;
				return false;
			} elseif ($wc == 0x22) {
				/* regular exit point for the loop */

				/* convert to UTF-8, then re-check against UTF-16 */
				$ov = mb_convert_encoding($s, 'UTF-8', 'UTF-16LE');
				$tmp = mb_convert_encoding($ov, 'UTF-16LE', 'UTF-8');
				if ($tmp !== $s) {
					$ov = 'no Unicode string before wchar #' . $this->p;
					return false;
				}
				return true;
			} elseif ($wc == 0x5C) {
				$wc = $this->j[$this->p];
				unset($this->j[$this->p]);
				++$this->p;
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
						if (!$this->get_hexdigit($v, $tmp)) {
							/* pass through error code */
							return false;
						}
					}
					if ($v < 1 || $v > 0xFFFD) {
						$ov = 'non-Unicode escape $v before wchar #' . $this->p;
						return false;
					}
					$s .= chr($v & 0xFF) . chr($v >> 8);
				} else {
					$ov = 'invalid escape sequence at wchar #' . $this->p;
					return false;
				}
			} elseif ($wc > 0xD7FF && $wc < 0xE000) {
				$ov = 'surrogate $wc at wchar #' . $this->p;
				return false;
			} elseif ($wc > 0xFFFD) {
				$ov = 'non-Unicode char $wc at wchar #' . $this->p;
				return false;
			} else {
				$s .= chr($wc & 0xFF) . chr($wc >> 8);
			}
		}
	}

	function decode_number(&$ov) {
		$s = '';
		$isint = true;

		/* check for an optional minus sign */
		$wc = $this->j[$this->p];
		unset($this->j[$this->p]);
		++$this->p;
		if ($wc == 0x2D) {
			$s = '-';
			$wc = $this->j[$this->p];
			unset($this->j[$this->p]);
			++$this->p;
		}

		if ($wc == 0x30) {
			/* begins with zero (0 or 0.x) */
			$s .= '0';
			$wc = $this->j[$this->p];
			unset($this->j[$this->p]);
			++$this->p;
			if ($wc >= 0x30 && $wc <= 0x39) {
				$ov = 'no leading zeroes please at wchar #' . $this->p;
				return false;
			}
		} elseif ($wc >= 0x31 && $wc <= 0x39) {
			/* begins with 1‥9 */
			while ($wc >= 0x30 && $wc <= 0x39) {
				$s .= chr($wc);
				$wc = $this->j[$this->p];
				unset($this->j[$this->p]);
				++$this->p;
			}
		} else {
			$ov = 'decimal digit expected at wchar #' . $this->p;
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
			$wc = $this->j[$this->p];
			unset($this->j[$this->p]);
			++$this->p;
			if ($wc < 0x30 || $wc > 0x39) {
				$ov = 'fractional digit expected at wchar #' . $this->p;
				return false;
			}
			while ($wc >= 0x30 && $wc <= 0x39) {
				$s .= chr($wc);
				$wc = $this->j[$this->p];
				unset($this->j[$this->p]);
				++$this->p;
			}
		}

		/* do we have an exponent, treat number as mantissa? */
		if ($wc == 0x45 || $wc == 0x65) {
			$s .= 'E';
			$isint = false;
			$wc = $this->j[$this->p];
			unset($this->j[$this->p]);
			++$this->p;
			if ($wc == 0x2B || $wc == 0x2D) {
				$s .= chr($wc);
				$wc = $this->j[$this->p];
				unset($this->j[$this->p]);
				++$this->p;
			}
			if ($wc < 0x30 || $wc > 0x39) {
				$ov = 'exponent digit expected at wchar #' . $this->p;
				return false;
			}
			while ($wc >= 0x30 && $wc <= 0x39) {
				$s .= chr($wc);
				$wc = $this->j[$this->p];
				unset($this->j[$this->p]);
				++$this->p;
			}
		}
		$this->p--;
		$this->j[$this->p] = $wc;

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
