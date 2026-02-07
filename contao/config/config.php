<?php

/*
 * This file is part of Module Sample.
 *
 * (c) Eckhard Becker <info@diversworld.eu>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/diversworld/contao-example-module-bundle
 */

use Diversworld\ContaoExampleModuleBundle\Model\ExampleModel;

/**
 * Backend modules
 */
$GLOBALS['BE_MOD']['be_example_module']['be_example_collection'] = array(
    'tables' => array('tl_example')
);

/**
 * Models
 */
$GLOBALS['TL_MODELS']['tl_exsample'] = ExampleModel::class;
