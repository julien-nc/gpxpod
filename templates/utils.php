<?php

function encodeURIComponent(string $str): string {
	$revert = ['%21' => '!', '%2A' => '*', '%27' => "'", '%28' => '(', '%29' => ')'];
	return strtr(rawurlencode($str), $revert);
}
