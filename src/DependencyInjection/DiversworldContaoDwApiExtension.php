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

namespace Diversworld\ContaoDwApiBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

class DiversworldContaoDwApiExtension extends Extension
{
    public function getAlias(): string
    {
        return Configuration::ROOT_KEY;
    }

    /**
     * @throws \Exception
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $this->processConfiguration(new Configuration(), $configs);

        $loader = new YamlFileLoader(
            $container,
            new FileLocator(__DIR__.'/../../config'),
        );

        $loader->load('parameters.yaml');
        $loader->load('services.yaml');
        $loader->load('listener.yaml');
        // Process the configuration tree even though the bundle currently has
        // no runtime options. This keeps the extension ready for future API
        // settings without exposing leftover example parameters.
    }
}
