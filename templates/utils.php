<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2015 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

function encodeURIComponent(string $str): string {
	$revert = ['%21' => '!', '%2A' => '*', '%27' => "'", '%28' => '(', '%29' => ')'];
	return strtr(rawurlencode($str), $revert);
}
