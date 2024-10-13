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

/**
 * @method string getUser()
 * @method void setUser(string $user)
 * @method string getPath()
 * @method void setPath(string $path)
 * @method int getIsOpen()
 * @method void setIsOpen(int $isOpen)
 * @method int getSortOrder()
 * @method void setSortOrder(int $sortOrder)
 * @method int getSortAscending()
 * @method void setSortAscending(int $sortAscending)
 * @method int getDisplayRecursive()
 * @method void setDisplayRecursive(int $displayRecursive)
 */
class Directory extends Entity implements \JsonSerializable {

	protected $user;
	protected $path;
	protected $isOpen;
	protected $sortOrder;
	protected $sortAscending;
	protected $displayRecursive;

	public function __construct() {
		$this->addType('user', 'string');
		$this->addType('path', 'string');
		$this->addType('is_open', 'integer');
		$this->addType('sort_order', 'integer');
		$this->addType('sort_ascending', 'boolean');
		$this->addType('display_recursive', 'boolean');
	}

	#[\ReturnTypeWillChange]
	public function jsonSerialize() {
		return [
			'id' => $this->getId(),
			'user' => $this->getUser(),
			'path' => $this->getPath(),
			'isOpen' => $this->getIsOpen() === 1,
			'sortOrder' => $this->getSortOrder(),
			'sortAscending' => $this->getSortAscending() === 1,
			'displayRecursive' => $this->getDisplayRecursive() === 1,
		];
	}
}
