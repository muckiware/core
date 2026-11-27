<?php declare(strict_types=1);
/**
 * @category   Muckidrive Component
 * @package    Core
 * @copyright  Copyright (c) 2026 by Muckiware
 * @license    MIT
 * @author     Muckiware
 *
 */
namespace Muckiware\Core\Framework\Store\Services;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Promise\PromiseInterface;
use Psr\Http\Message\RequestInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Store\Services\StoreClientFactory as SWStoreClientFactory;
use Shopware\Core\Framework\Store\StoreException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 *
 * Muckidrive does not communicate with the Shopware Store (api.shopware.com). The HTTP clients
 * created by this factory (`shopware.store_client`, `shopware.frw_client`) reject every request
 * before it leaves the system, so no store login, download, tracking or in-app purchase call
 * can reach Shopware.
 */
#[Package('framework')]
final class StoreClientFactory extends SWStoreClientFactory
{
    public const ERROR_CODE = 'MUCKIWARE__STORE_DISABLED';

    /**
     * @param iterable<mixed> $middlewares unused, requests are rejected before any middleware
     */
    public function create(iterable $middlewares = []): ClientInterface
    {
        return new Client([
            'handler' => static fn (RequestInterface $request): PromiseInterface => Create::rejectionFor(
                self::storeDisabled($request)
            ),
        ]);
    }

    private static function storeDisabled(RequestInterface $request): StoreException
    {
        return new StoreException(
            Response::HTTP_SERVICE_UNAVAILABLE,
            self::ERROR_CODE,
            'The Shopware Store is disabled on Muckidrive. Request to "{{ uri }}" was blocked.',
            ['uri' => $request->getUri()->getPath()]
        );
    }
}
