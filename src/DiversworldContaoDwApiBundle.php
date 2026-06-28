<?php

declare(strict_types=1);

/*
 * This file is part of Contao DW API Bundle.
 *
 * (c) Eckhard Becker <info@diversworld.eu>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/diversworld/contao-dw-api-bundle
 */

namespace Diversworld\ContaoDwApiBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;

class DiversworldContaoDwApiBundle extends Bundle
{
    public function getPath(): string
    {
        return \dirname(__DIR__);
    }

    public function getContainerExtension(): ?\Symfony\Component\DependencyInjection\Extension\ExtensionInterface
    {
        return new \Diversworld\ContaoDwApiBundle\DependencyInjection\DiversworldContaoDwApiExtension();
    }
}
