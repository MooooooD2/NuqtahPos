<?php

declare(strict_types=1);

use Rector\CodeQuality\Rector\Class_\InlineConstructorDefaultToPropertyRector;
use Rector\CodingStyle\Rector\PostInc\PostIncDecToPreIncDecRector;
use Rector\Config\RectorConfig;
use Rector\Php80\Rector\Class_\ClassPropertyAssignToConstructorPromotionRector;
use Rector\Set\ValueObject\LevelSetList;
use Rector\Set\ValueObject\SetList;
use Rector\TypeDeclaration\Rector\ClassMethod\AddVoidReturnTypeWhereNoReturnRector;
use Rector\TypeDeclaration\Rector\StmtsAwareInterface\DeclareStrictTypesRector;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/app',
        __DIR__ . '/config',
        __DIR__ . '/database',
        __DIR__ . '/routes',
        __DIR__ . '/tests',
    ])
    ->withSkip([
        __DIR__ . '/app/Http/Middleware/EncryptCookies.php',
        // Skip generated files
        __DIR__ . '/vendor',
        __DIR__ . '/storage',
    ])
    ->withSets([
        SetList::CODE_QUALITY,
        SetList::CODING_STYLE,
        SetList::TYPE_DECLARATION,
        LevelSetList::UP_TO_PHP_82,
    ])
    ->withRules([
        InlineConstructorDefaultToPropertyRector::class,
        ClassPropertyAssignToConstructorPromotionRector::class,
        AddVoidReturnTypeWhereNoReturnRector::class,
        DeclareStrictTypesRector::class,
        PostIncDecToPreIncDecRector::class,
    ])
    ->withSkipPath(__DIR__ . '/database/migrations');
