<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2022, Julien Veyssier <julien-nc@posteo.net>
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
use OCP\DB\Types;

/**
 * @method \string getUser()
 * @method \void setUser(string $user)
 * @method \string getTrackpath()
 * @method \void setTrackpath(string $trackpath)
 * @method \string getContenthash()
 * @method \void setContenthash(string $contenthash)
 * @method \string getMarker()
 * @method \void setMarker(string $marker)
 * @method \int getIsEnabled()
 * @method \void setIsEnabled(int $isEnabled)
 * @method \string|\null getColor()
 * @method \void setColor(?string $color)
 * @method \int getColorCriteria()
 * @method \void setColorCriteria(int $colorCriteria)
 * @method \int getDirectoryId()
 * @method \void setDirectoryId(int $directoryId)
 */
class Track extends Entity implements \JsonSerializable {

	protected $user;
	protected $trackpath;
	protected $contenthash;
	protected $marker;
	protected $isEnabled;
	protected $color;
	protected $colorCriteria;
	protected $directoryId;

	public function __construct() {
		$this->addType('user', Types::STRING);
		$this->addType('trackpath', Types::STRING);
		$this->addType('contenthash', Types::STRING);
		$this->addType('marker', Types::STRING);
		$this->addType('isEnabled', Types::INTEGER);
		$this->addType('color', Types::STRING);
		$this->addType('colorCriteria', Types::INTEGER);
		$this->addType('directoryId', Types::INTEGER);
	}

	#[\ReturnTypeWillChange]
	public function jsonSerialize() {
		return [
			'id' => $this->getId(),
			'user' => $this->getUser(),
			'trackpath' => $this->getTrackpath(),
			'contenthash' => $this->getContenthash(),
			'marker' => $this->getMarker(),
			'isEnabled' => $this->getIsEnabled() === 1,
			'color' => $this->getColor(),
			'colorCriteria' => $this->getColorCriteria(),
			'directoryId' => $this->getDirectoryId(),
		];
	}
}
