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
 * Frontend modules
 */
$GLOBALS['TL_DCA']['tl_module']['palettes'][FeExampleListingController::TYPE] = '{title_legend},name,headline,type;{template_legend:hide},customTpl;{protected_legend:hide},protected;{expert_legend:hide},guests,cssID';
