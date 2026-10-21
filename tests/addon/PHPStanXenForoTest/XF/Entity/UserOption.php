<?php

namespace PHPStanXenForoTest\XF\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * @extends \XF\Entity\UserOption
 */
class UserOption extends XFCP_UserOption
{
	public static function getStructure(Structure $structure)
	{
		$structure = parent::getStructure($structure);

		$structure->columns['phpstan_xenforo_test_lat'] = ['type' => Entity::FLOAT, 'default' => 0];

		return $structure;
	}
}
