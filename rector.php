<?php

use Rector\Config\RectorConfig;
use Rector\DeadCode\Rector\StaticCall\RemoveParentCallWithoutParentRector;
use Rector\Set\ValueObject\SetList;
use Rector\TypeDeclaration\Rector\ClassMethod\ReturnTypeFromStrictTypedPropertyRector;
use Rector\TypeDeclaration\Rector\ClassMethod\ReturnUnionTypeRector;
use Rector\ValueObject\PhpVersion;

return function (RectorConfig $rectorConfig) {
	$rectorConfig->paths([
		__DIR__ . '/lib',
		__DIR__ . '/tests',
	]);
	$rectorConfig->phpVersion(PhpVersion::PHP_80);
	$rectorConfig->importNames();
	$rectorConfig->indent('	', 1);
	$rectorConfig->sets([
		SetList::PHP_74,
		SetList::PHP_80,
	]);
	$rectorConfig->rule(ReturnTypeFromStrictTypedPropertyRector::class);
	$rectorConfig->rule(ReturnUnionTypeRector::class);
	$rectorConfig->skip([
		RemoveParentCallWithoutParentRector::class,
	]);
};
