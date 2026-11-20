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
use Tygh\Addons\MaxmaSync\Service\QueueLocks;
use Tygh\Addons\MaxmaSync\Service\QueueService;
use Tygh\Addons\MaxmaSync\Service\ReturnService;
use Tygh\Lock\Factory;
use Tygh\Registry;

class ServiceProvider implements ServiceProviderInterface
{
    /**
     * Register services used by the MAXMA add-on hooks.
     */
    public function register(Container $app): void
    {
        $app['addons.maxma_sync.locks'] = static function (Container $app): QueueLocks {
            $backend = Registry::get('config.lock_backend');
            /** @var Factory $factory */
            $factory = in_array($backend, ['database', 'redis'], true)
                ? $app['lock.factory']
                : new Factory($app['lock.provider.database']);

            return new QueueLocks($factory);
        };

        $app['addons.maxma_sync.queue'] = static fn (Container $app): QueueService => new QueueService(
            (array) Registry::get('addons.maxma_sync'),
            $app['addons.maxma_sync.locks']
        );

        $app['addons.maxma_sync.returns'] = static fn (Container $app): ReturnService => new ReturnService(
            new ReturnRepository(),
            new QueueRepository(),
            $app['addons.maxma_sync.locks']
        );
    }
}
