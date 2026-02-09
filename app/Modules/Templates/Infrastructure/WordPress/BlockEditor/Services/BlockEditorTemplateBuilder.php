<?php

namespace Notifal\Modules\Templates\Infrastructure\WordPress\BlockEditor\Services;

use Notifal\Modules\Templates\Contracts\TemplateBuilderInterface;
use Notifal\Modules\Templates\Application\Services\PreviewDataResolver;
use Notifal\Domain\Tags\TagManager;
use Notifal\Domain\Orders\OrderFetcherInterface;
use Notifal\Infrastructure\WordPress\Services\UserFetcher;

defined('ABSPATH') || exit;

/**
 * Provides rendering and preview logic for templates built with the Block Editor (Gutenberg).
 *
 * @package Notifal\Modules\Templates\Infrastructure\WordPress\BlockEditor\Services
 * @since 2.0.0
 * @author Hossein <hossein@notifal.com>
 */
class BlockEditorTemplateBuilder implements TemplateBuilderInterface
{
    /**
     * @var PreviewDataResolver
     */
    private $previewDataResolver;

    /**
     * @var TagManager
     */
    private $tagManager;

    /**
     * @var OrderFetcherInterface
     */
    private OrderFetcherInterface $orderFetcher;

    /**
     * @var UserFetcher
     */
    private UserFetcher $userFetcher;

    /**
     * BlockEditorTemplateBuilder constructor.
     *
     * @param PreviewDataResolver $previewDataResolver
     * @param OrderFetcherInterface $orderFetcher
     * @param UserFetcher $userFetcher
     * @since 2.0.0
     */
    public function __construct(
        PreviewDataResolver $previewDataResolver,
        OrderFetcherInterface $orderFetcher,
        UserFetcher $userFetcher
    )
    {
        $this->previewDataResolver = $previewDataResolver;
        $this->orderFetcher = $orderFetcher;
        $this->userFetcher = $userFetcher;
        $this->tagManager = notifal_app(TagManager::class);
    }

    /**
     * Build preview content by replacing tags with resolved data.
     *
     * @param string $content
     * @return string
     * @since 2.0.0
     */
    public function buildPreviewContent(string $content): string
    {
        // Use frontend notification context if active
        if (class_exists('\Notifal\Modules\OnPageNotification\Application\Services\Utility\WidgetContextProvider')) {
            if (\Notifal\Modules\OnPageNotification\Application\Services\Utility\WidgetContextProvider::isActive()) {
                $context = \Notifal\Modules\OnPageNotification\Application\Services\Utility\WidgetContextProvider::getContext();
                if ($context) {
                    return $this->tagManager->render($content, $context);
                }
            }
        }

        $previewData = $this->previewDataResolver->resolve($content);
        if ($previewData) {
            $currentUser = $this->userFetcher->getCurrent();
            $user = $currentUser ?: $this->userFetcher->getRandom();

            $context = [
                'product' => $previewData->getProduct(),
                'order' => $this->orderFetcher->getRandom(),
                'user' => $user,
                'is_preview' => true,
            ];
            return $this->tagManager->render($content, $context);
        }
        return $content;
    }

    /**
     * Render the block editor template by ID.
     *
     * @param int $templateId
     * @return string Rendered HTML output
     * @since 2.0.0
     */
    public function render(int $templateId): string
    {
        $post = get_post($templateId);
        if (!$post || $post->post_type !== 'notifal_template') {
            return '';
        }

        // Apply content filters to render blocks
        return apply_filters('the_content', $post->post_content);
    }

    /**
     * Build a preview of the template with resolved tag data.
     *
     * @param int $templateId
     * @return string Rendered HTML preview
     * @since 2.0.0
     */
    public function buildPreview(int $templateId): string
    {
        $post = get_post($templateId);
        if (!$post || $post->post_type !== 'notifal_template') {
            return '';
        }

        $content = apply_filters('the_content', $post->post_content);
        return $this->buildPreviewContent($content);
    }

    /**
     * Export template data from block editor.
     *
     * @param int $templateId
     * @return array Exported data array
     * @since 2.0.0
     */
    public function export(int $templateId): array
    {
        $post = get_post($templateId);
        if (!$post || $post->post_type !== 'notifal_template') {
            return [];
        }

        return [
            'id'      => $post->ID,
            'title'   => $post->post_title,
            'builder' => 'block-editor',
            'content' => $post->post_content,
        ];
    }

    /**
     * Import template data into a new block editor template.
     *
     * @param array $data
     * @return int Created template post ID
     * @since 2.0.0
     */
    public function import(array $data): int
    {
        $post_data = [
            'post_title'   => sanitize_text_field($data['title'] ?? ''),
            'post_content' => wp_kses_post($data['content'] ?? ''),
            'post_type'    => 'notifal_template',
            'post_status'  => 'draft',
        ];

        $post_id = wp_insert_post($post_data);
        if (is_wp_error($post_id)) {
            return 0;
        }

        return $post_id;
    }
} 
