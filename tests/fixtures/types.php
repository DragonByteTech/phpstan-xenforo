<?php

namespace DragonByteTech\PHPStanXenForo\Tests\Fixtures;

use XF\Entity\User;
use XF\Entity\UserOption;
use XF\Finder\UserFinder;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Manager;
use XF\Mvc\ParameterBag;

function shortNames(Manager $em): void
{
	\PHPStan\Testing\assertType('XF\Entity\User|null', $em->find('XF:User', 1));
	\PHPStan\Testing\assertType('XF\Finder\UserFinder', \XF::app()->finder('XF:User'));
	\PHPStan\Testing\assertType('XF\Repository\UserRepository', \XF::repository('XF:User'));
	\PHPStan\Testing\assertType('XF\Validator\Email', \XF::app()->validator('Email'));
}

function finderChain(): void
{
	$finder = \XF::finder(UserFinder::class)->where('user_id', 1)->order('username');
	\PHPStan\Testing\assertType('XF\Finder\UserFinder', $finder);
	\PHPStan\Testing\assertType('XF\Mvc\Entity\AbstractCollection<XF\Entity\User>', $finder->limitByPage(1, 20)->fetch());
}

function findByContentType(int $id): void
{
	\PHPStan\Testing\assertType('XF\Mvc\Entity\AbstractCollection<XF\Mvc\Entity\Entity>', \XF::app()->findByContentType('post', [1, 2]));
	\PHPStan\Testing\assertType('XF\Mvc\Entity\Entity|null', \XF::app()->findByContentType('post', $id));
}

/**
 * @return AbstractCollection<User>
 */
function emptyCollection(Manager $em): AbstractCollection
{
	\PHPStan\Testing\assertType('XF\Mvc\Entity\ArrayCollection<*NEVER*>', $em->getEmptyCollection());

	return $em->getEmptyCollection();
}

function classExtensionMembers(User $user, UserOption $option): void
{
	// Column added by DBTech\MemberMap\XF\Entity\UserOption::getStructure().
	\PHPStan\Testing\assertType('float', $option->dbtech_membermap_lat);
	\PHPStan\Testing\assertType('bool', $user->canViewDbtechMemberMap());
}

function options(): void
{
	\PHPStan\Testing\assertType('bool|null', \XF::options()->dbtech_membermap_all);
	// Typed by Options.stub in the XenForo root (xf-dev:generate-options-stub).
	\PHPStan\Testing\assertType('string|null', \XF::options()->boardTitle);
	\PHPStan\Testing\assertType('mixed', \XF::options()->noSuchOption);
	\PHPStan\Testing\assertType('float|int|string', \XF::options()->watchAlertActiveOnly['days']);
}

function magicGetters(ParameterBag $params): void
{
	\PHPStan\Testing\assertType('mixed', $params->thread_id);
	\PHPStan\Testing\assertType('mixed', \XF::session()->someKey);
}
