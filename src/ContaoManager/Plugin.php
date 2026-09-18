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

namespace Diversworld\ContaoDwApiBundle\ContaoManager;

use Diversworld\ContaoDwApiBundle\DiversworldContaoDwApiBundle;
use Contao\CoreBundle\ContaoCoreBundle;
use Diversworld\ContaoDiveclubBundle\DiversworldContaoDiveclubBundle;
use Contao\ManagerPlugin\Bundle\BundlePluginInterface;
use Contao\ManagerPlugin\Bundle\Config\BundleConfig;
use Contao\ManagerPlugin\Bundle\Parser\ParserInterface;
use Contao\ManagerPlugin\Routing\RoutingPluginInterface;
use Symfony\Component\Config\Loader\LoaderResolverInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\RouteCollection;

class Plugin implements BundlePluginInterface, RoutingPluginInterface
{
    public function getBundles(ParserInterface $parser): array
    {
        return [
            BundleConfig::create(DiversworldContaoDwApiBundle::class)
                ->setLoadAfter([
                    ContaoCoreBundle::class,
                    DiversworldContaoDiveclubBundle::class,
                ]),
        ];
    }

    public function getRouteCollection(LoaderResolverInterface $resolver, KernelInterface $kernel): ?RouteCollection
    {
        // API routes live inside the bundle, so they still have to be exposed
        // through the Contao manager plugin instead of the app-level route loader.
        return $resolver
            ->resolve(__DIR__ . '/../Controller', 'attribute')
            ->load(__DIR__ . '/../Controller', 'attribute');
    }
}
