<?php

declare(strict_types=1);

/*
 * This file is part of Module Sample.
 *
 * (c) Eckhard Becker <info@diversworld.eu>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/diversworld/contao-example-module-bundle
 */

use Diversworld\ContaoExampleModuleBundle\Controller\FrontendModule\FeExampleListingController;

/**
 * Backend modules
 */
$GLOBALS['TL_LANG']['MOD']['be_exsample_module'] = 'Backend Kategorie';
$GLOBALS['TL_LANG']['MOD']['be_exsample_collection'] = ['Backend Typ', 'Backend Typ Beschreibung'];

/**
 * Frontend modules
 */
$GLOBALS['TL_LANG']['FMD']['fe_exsample_module'] = 'Frontent Kategorie';
$GLOBALS['TL_LANG']['FMD'][FeExampleListingController::TYPE] = ['Frontend Typ', 'Frontend Typ Beschreibung'];
