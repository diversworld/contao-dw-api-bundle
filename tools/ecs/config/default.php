<?php

declare(strict_types=1);

return call_user_func(['Symplify\EasyCodingStandard\Config\ECSConfig', 'configure'])
    ->withSets([constant('Contao\EasyCodingStandard\Set\SetList::CONTAO')])
	->withPaths([
		__DIR__ . '/../../src',
	])
	->withSkip([
        'Contao\EasyCodingStandard\Fixer\CommentLengthFixer' => ['*.php'],
        'PhpCsFixer\Fixer\Whitespace\MethodChainingIndentationFixer' => [
			'*/DependencyInjection/Configuration.php',
		],
	])
	->withRootFiles()
	->withParallel()
    ->withSpacing(constant('Symplify\EasyCodingStandard\ValueObject\Option::INDENTATION_SPACES'), "\n")
    ->withConfiguredRule('PhpCsFixer\Fixer\Comment\HeaderCommentFixer', [
        'header' => "This file is part of Contao DW API Bundle.\n\n(c) Eckhard Becker &lt;info@diversworld.eu&gt;\n@license GPL-3.0-or-later\nFor the full copyright and license information,\nplease view the LICENSE file that was distributed with this source code.\n@link https://github.com/diversworld/contao-dw-api-bundle",
	])
    ->withCache(sys_get_temp_dir() . '/ecs/diversworld/contao-dw-api-bundle');
