<?php

namespace Notifal\Modules\Templates\Domain\DTO;

use Notifal\Domain\Products\DTO\ProductDTO;

defined('ABSPATH') || exit;

/**
 * Class PreviewDataDTO
 *
 * Holds resolved preview data for template previews.
 *
 * @package Notifal\Modules\Templates\Domain\DTO
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */
class PreviewDataDTO
{
    /**
     * @var ProductDTO
     */
    private $product;

    /**
     * @var array
     */
    private $tags;

    /**
     * PreviewDataDTO constructor.
     *
     * @param ProductDTO $product
     * @param array $tags
     * @since 2.0.0
     */
    public function __construct(ProductDTO $product, array $tags)
    {
        $this->product = $product;
        $this->tags    = $tags;
    }

    /**
     * Get the product DTO.
     *
     * @return ProductDTO
     * @since 2.0.0
     */
    public function getProduct(): ProductDTO
    {
        return $this->product;
    }

    /**
     * Get resolved tags.
     *
     * @return array
     * @since 2.0.0
     */
    public function getTags(): array
    {
        return $this->tags;
    }
}
