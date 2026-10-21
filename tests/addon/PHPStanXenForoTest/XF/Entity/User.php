<?php

namespace PHPStanXenForoTest\XF\Entity;

/**
 * @extends \XF\Entity\User
 */
class User extends XFCP_User
{
	public function canViewPhpstanXenForoTest(): bool
	{
		return $this->hasPermission('general', 'view');
	}
}
