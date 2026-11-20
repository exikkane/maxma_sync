<?php
/***************************************************************************
 *                             Larionov.tech                               *
 *                          https://larionov.tech                          *
 ***************************************************************************/

namespace Tygh\Addons\MaxmaSync;

use Pimple\Container;
use Pimple\ServiceProviderInterface;
use Tygh\Addons\MaxmaSync\Repository\QueueRepository;
use Tygh\Addons\MaxmaSync\Repository\ReturnRepository;
use Tygh\Addons\MaxmaSync\Service\ReturnService;

class ServiceProvider implements ServiceProviderInterface
{
    /**
     * Register services used by the MAXMA add-on hooks.
     */
    public function register(Container $app): void
    {
        $app['addons.maxma_sync.returns'] = static fn (): ReturnService => new ReturnService(
            new ReturnRepository(),
            new QueueRepository()
        );
    }
}
