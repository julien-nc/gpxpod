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
 * @method string|null getUserId()
 * @method void setUserId(?string $userId)
 * @method int getType()
 * @method void setType(int $type)
 * @method string getName()
 * @method void setName(string $name)
 * @method string getUrl()
 * @method void setUrl(string $url)
 * @method int|null getMinZoom()
 * @method void setMinZoom(?int $minZoom)
 * @method int|null getMaxZoom()
 * @method void setMaxZoom(?int $maxZoom)
 * @method string getAttribution()
 * @method void setAttribution(string $attribution)
 */
class TileServer extends Entity implements \JsonSerializable {

	/** @var string|null */
	protected $userId;
	/** @var int */
	protected $type;
	/** @var string */
	protected $name;
	/** @var string */
	protected $url;
	/** @var int|null */
	protected $minZoom;
	/** @var int|null */
	protected $maxZoom;
	/** @var string */
	protected $attribution;

	public function __construct() {
		$this->addType('user_id', 'string');
		$this->addType('type', 'integer');
		$this->addType('name', 'string');
		$this->addType('url', 'string');
		$this->addType('min_zoom', 'integer');
		$this->addType('max_zoom', 'integer');
		$this->addType('attribution', 'string');
	}

	#[\ReturnTypeWillChange]
	public function jsonSerialize() {
		return [
			'id' => $this->id,
			'user_id' => $this->userId,
			'type' => $this->type,
			'name' => $this->name,
			'url' => $this->url,
			'min_zoom' => $this->minZoom,
			'max_zoom' => $this->maxZoom,
			'attribution' => $this->attribution,
		];
	}
}
