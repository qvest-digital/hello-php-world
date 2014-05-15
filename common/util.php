<?php

function util_ifsetor(&$value, $default=false) {
	return (ifset($value) ? $value : $default);
}
