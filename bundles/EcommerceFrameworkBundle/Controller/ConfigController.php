<?php

/**
 * Pimcore
 *
 * This source file is available under two different licenses:
 * - GNU General Public License version 3 (GPLv3)
 * - Pimcore Commercial License (PCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 *  @license    http://www.pimcore.org/license     GPLv3 and PCL
 */

namespace Pimcore\Bundle\EcommerceFrameworkBundle\Controller;

use Pimcore\Bundle\AdminBundle\Controller\AdminController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\RouterInterface;

/**
 * Class ConfigController
 *
 * @Route("/config")
 *
 * @internal
 */
class ConfigController extends AdminController
{
    /**
     * @var RouterInterface
     */
    private $router;

    /**
     * ConfigController constructor.
     *
     * @param RouterInterface $router
     */
    public function __construct(RouterInterface $router)
    {
        $this->router = $router;
    }

    /**
     * @Route("/js-config", name="pimcore_ecommerceframework_config_jsconfig", methods={"GET"})
     *
     * @return Response
     */
    public function jsConfigAction()
    {
        $config = $this->getParameter('pimcore_ecommerce.pimcore.config');

        $orderList = $config['menu']['order_list'];
        if (isset($orderList['route']) && !empty($orderList['route'])) {
            $orderList['route'] = $this->router->generate($orderList['route']);
        } elseif (isset($orderList['path']) && !empty($orderList['path'])) {
            $orderList['route'] = $orderList['path'];
        }

        if (array_key_exists('path', $orderList)) {
            unset($orderList['path']);
        }

        $config['menu']['order_list'] = $orderList;

        $javascript = 'pimcore.registerNS("pimcore.bundle.EcommerceFramework.config");' . PHP_EOL;

        $javascript .= 'pimcore.bundle.EcommerceFramework.config = ';
        $javascript .= json_encode($config) . ';';

        $response = new Response($javascript);
        $response->headers->set('Content-Type', 'application/javascript');

        return $response;
    }
}
