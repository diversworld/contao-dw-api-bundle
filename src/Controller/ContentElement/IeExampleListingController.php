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

namespace Diversworld\ContaoExampleModuleBundle\Controller\ContentElement;

use Contao\ContentModel;
use Contao\CoreBundle\Controller\ContentElement\AbstractContentElementController;
use Contao\CoreBundle\DependencyInjection\Attribute\AsContentElement;
use Contao\CoreBundle\Twig\FragmentTemplate;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

#[AsContentElement(category: 'ie_example_listing')]
class IeExampleListingController extends AbstractContentElementController
{
    public const TYPE = 'ie_example_listing';

    protected function getResponse(FragmentTemplate $template, ContentModel $model, Request $request): Response
    {
        $template->set('text', $model->text);

        return $template->getResponse();
    }
}
