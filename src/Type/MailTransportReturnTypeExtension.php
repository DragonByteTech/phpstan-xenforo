<?php

namespace DragonByteTech\PHPStanXenForo\Type;

use PhpParser\Node\Expr\StaticCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\DynamicStaticMethodReturnTypeExtension;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;

/**
 * Mailer::getTransportFromOption() is documented as returning TransportInterface, but every
 * transport it builds is an AbstractTransport, which XenForo passes on to Mailer and Mail
 * methods that require one.
 */
final class MailTransportReturnTypeExtension implements DynamicStaticMethodReturnTypeExtension
{
	public function getClass(): string
	{
		return 'XF\Mail\Mailer';
	}

	public function isStaticMethodSupported(MethodReflection $methodReflection): bool
	{
		return $methodReflection->getName() === 'getTransportFromOption';
	}

	public function getTypeFromStaticMethodCall(MethodReflection $methodReflection, StaticCall $methodCall, Scope $scope): Type
	{
		return new ObjectType('Symfony\Component\Mailer\Transport\AbstractTransport');
	}
}
