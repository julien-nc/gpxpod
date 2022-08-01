<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2022, Julien Veyssier <eneiluj@posteo.net>
 *
 * @author Julien Veyssier <eneiluj@posteo.net>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\GpxPod\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method string getUser()
 * @method void setUser(string $user)
 * @method string getTrackpath()
 * @method void setTrackpath(string $trackpath)
 * @method string getContenthash()
 * @method void setContenthash(string $contenthash)
 * @method string getMarker()
 * @method void setMarker(string $marker)
 * @method int getEnabled()
 * @method void setEnabled(int $enabled)
 * @method string|null getColor()
 * @method void setColor(?string $color)
 * @method int getColorCriteria()
 * @method void setColorCriteria(int $colorCriteria)
 * @method int getDirectoryId()
 * @method void setDirectoryId(int $directoryId)
 */
class Track extends Entity implements \JsonSerializable {

	/** @var string */
	protected $user;

	/** @var string */
	protected $trackpath;

	/** @var string */
	protected $contenthash;

	/** @var string */
	protected $marker;

	/** @var int */
	protected $enabled;

	/** @var string|null */
	protected $color;

	/** @var int */
	protected $colorCriteria;

	/** @var int */
	protected $directoryId;

	public function __construct() {
		$this->addType('user', 'string');
		$this->addType('trackpath', 'string');
		$this->addType('contenthash', 'string');
		$this->addType('marker', 'string');
		$this->addType('enabled', 'integer');
		$this->addType('color', 'string');
		$this->addType('color_criteria', 'integer');
		$this->addType('directory_id', 'integer');
	}

	#[\ReturnTypeWillChange]
	public function jsonSerialize() {
		return [
			'id' => $this->id,
			'user' => $this->user,
			'trackpath' => $this->trackpath,
			'contenthash' => $this->contenthash,
			'marker' => $this->marker,
			'enabled' => $this->enabled === 1,
			'color' => $this->color,
			'color_criteria' => (int)$this->colorCriteria,
			'directory_id' => (int)$this->directoryId,
		];
	}
}
