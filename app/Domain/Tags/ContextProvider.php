<?php

namespace Notifal\Domain\Tags;

use Notifal\Domain\Products\ProductFetcherInterface;
use Notifal\Domain\Orders\OrderFetcherInterface;
use Notifal\Domain\Users\UserFetcherInterface;
use Notifal\Domain\Tags\Exceptions\MissingContextException;

defined('ABSPATH') || exit;

/**
 * Class ContextProvider
 *
 * Loads and prepares all required context data for tag resolution.
 *
 * @package Notifal\Domain\Tags
 * @author Hossein <hossein@notifal.com>
 * @since 2.0.0
 */
class ContextProvider
{
    /**
     * @var ProductFetcherInterface
     */
    private ProductFetcherInterface $productFetcher;

    /**
     * @var OrderFetcherInterface
     */
    private OrderFetcherInterface $orderFetcher;

    /**
     * @var UserFetcherInterface
     */
    private UserFetcherInterface $userFetcher;

    /**
     * ContextProvider constructor.
     *
     * @param ProductFetcherInterface $productFetcher
     * @param OrderFetcherInterface   $orderFetcher
     * @param UserFetcherInterface    $userFetcher
     * @since 2.0.0
     */
    public function __construct(
        ProductFetcherInterface $productFetcher,
        OrderFetcherInterface $orderFetcher,
        UserFetcherInterface $userFetcher
    ) {
        $this->productFetcher = $productFetcher;
        $this->orderFetcher   = $orderFetcher;
        $this->userFetcher    = $userFetcher;
    }

    /**
     * Provide all context data for tag resolution.
     *
     * @param int|null $productId Optional product ID
     * @param int|null $orderId   Optional order ID
     * @param int|null $userId    Optional user ID
     * @return array
     * @throws MissingContextException
     * @since 2.0.0
     */
    public function provide(?int $productId = null, ?int $orderId = null, ?int $userId = null): array
    {
        $context = [];

        if ($productId !== null) {
            $context['product'] = $this->productFetcher->findById($productId);
            if (!$context['product']) {
                throw new MissingContextException(sprintf(__('Product with ID %d not found.', 'notifal'), $productId));
            }
        }

        if ($orderId !== null) {
            $context['order'] = $this->orderFetcher->findById($orderId);
            if (!$context['order']) {
                throw new MissingContextException(sprintf(__('Order with ID %d not found.', 'notifal'), $orderId));
            }
        }

        if ($userId !== null) {
            $context['user'] = $this->userFetcher->findById($userId);
            if (!$context['user']) {
                throw new MissingContextException(sprintf(__('User with ID %d not found.', 'notifal'), $userId));
            }
        }

        return $context;
    }
}
