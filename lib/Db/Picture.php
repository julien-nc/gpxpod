<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2023, Julien Veyssier <julien-nc@posteo.net>
 *
 * @author Julien Veyssier <julien-nc@posteo.net>
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
 * @method string getPath()
 * @method void setPath(string $path)
 * @method string getContenthash()
 * @method void setContenthash(string $contenthash)
 * @method float getLat()
 * @method void setLat(float $lat)
 * @method float getLon()
 * @method void setLon(float $lon)
 * @method int getDateTaken()
 * @method void setDateTaken(int $dateTaken)
 * @method int getDirection()
 * @method void setDirection(int $direction)
 * @method int getDirectoryId()
 * @method void setDirectoryId(int $directoryId)
 */
class Picture extends Entity implements \JsonSerializable {

	/** @var string */
	protected $user;
	/** @var string */
	protected $path;
	/** @var string */
	protected $contenthash;
	/** @var float */
	protected $lat;
	/** @var float */
	protected $lon;
	/** @var int */
	protected $dateTaken;
	/** @var int */
	protected $direction;
	/** @var int */
	protected $directoryId;

	public function __construct() {
		$this->addType('user', 'string');
		$this->addType('path', 'string');
		$this->addType('contenthash', 'string');
		$this->addType('lat', 'float');
		$this->addType('lon', 'float');
		$this->addType('date_taken', 'integer');
		$this->addType('direction', 'integer');
		$this->addType('directory_id', 'integer');
	}

	#[\ReturnTypeWillChange]
	public function jsonSerialize() {
		return [
			'id' => $this->id,
			'user' => $this->user,
			'path' => $this->path,
			'contenthash' => $this->contenthash,
			'lat' => (float)$this->lat,
			'lon' => (float)$this->lon,
			'date_taken' => (int)$this->dateTaken,
			'direction' => (int)$this->direction,
			'directory_id' => (int)$this->directoryId,
		];
	}
}
