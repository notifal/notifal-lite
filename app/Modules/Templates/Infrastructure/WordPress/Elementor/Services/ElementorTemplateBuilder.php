<?php

namespace Notifal\Modules\Templates\Infrastructure\WordPress\Elementor\Services;

use Elementor\Plugin;
use Notifal\Modules\Templates\Contracts\TemplateBuilderInterface;
use Notifal\Modules\Templates\Application\Services\PreviewDataResolver;
use Notifal\Modules\OnPageNotification\Application\Services\Settings\ContentSourceService;
use Notifal\Infrastructure\WordPress\Admin\Settings\Services\PostTypeDiscoveryService;
use Notifal\Domain\Tags\TagManager;
use Notifal\Domain\Orders\OrderFetcherInterface;
use Notifal\Infrastructure\WordPress\Services\UserFetcher;

defined('ABSPATH') || exit;

/**
 * Class ElementorTemplateBuilder
 *
 * Provides rendering, preview building, and import/export logic
 * for templates built with Elementor.
 *
 * @since 2.0.0
 * @package Notifal\Modules\Templates\Infrastructure\WordPress\Elementor\Services
 */
class ElementorTemplateBuilder implements TemplateBuilderInterface
{
    /**
     * @var PreviewDataResolver
     */
    private PreviewDataResolver $previewDataResolver;

    /**
     * @var TagManager
     */
    private TagManager $tagManager;

    /**
     * @var OrderFetcherInterface
     */
    private OrderFetcherInterface $orderFetcher;

    /**
     * @var UserFetcher
     */
    private UserFetcher $userFetcher;

    /**
     * @var ContentSourceService
     */
    private ContentSourceService $contentSourceService;

    /**
     * @var PostTypeDiscoveryService
     */
    private PostTypeDiscoveryService $postTypeDiscoveryService;

    /**
     * ElementorTemplateBuilder constructor.
     *
     * @param PreviewDataResolver $previewDataResolver
     * @param OrderFetcherInterface $orderFetcher
     * @param UserFetcher $userFetcher
     * @param ContentSourceService $contentSourceService
     * @param PostTypeDiscoveryService $postTypeDiscoveryService
     * @since 2.0.0
     */
    public function __construct(
        PreviewDataResolver $previewDataResolver,
        OrderFetcherInterface $orderFetcher,
        UserFetcher $userFetcher,
        ContentSourceService $contentSourceService,
        PostTypeDiscoveryService $postTypeDiscoveryService
    )
    {
        $this->previewDataResolver = $previewDataResolver;
        $this->orderFetcher = $orderFetcher;
        $this->userFetcher = $userFetcher;
        $this->contentSourceService = $contentSourceService;
        $this->postTypeDiscoveryService = $postTypeDiscoveryService;
        $this->tagManager = notifal_app(TagManager::class);
    }

    /**
     * Render the Elementor template by ID.
     *
     * @param int $templateId
     * @return string Rendered HTML output
     * @since 2.0.0
     */
    public function render(int $templateId): string
    {
        if (! Plugin::instance()->documents->get($templateId)) {
            return '';
        }

        ob_start();
        echo Plugin::instance()->frontend->get_builder_content_for_display($templateId);
        return ob_get_clean();
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
        $content = Plugin::instance()->frontend->get_builder_content_for_display($templateId);

        $previewData = $this->previewDataResolver->resolve();

        if ($previewData) {
            return $this->tagManager->render($content, $previewData->getTags());
        }

        return $content;
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
        // Check if we're in frontend notification context and use proper context
        if (class_exists('\Notifal\Modules\OnPageNotification\Application\Services\Utility\WidgetContextProvider')) {
            if (\Notifal\Modules\OnPageNotification\Application\Services\Utility\WidgetContextProvider::isActive()) {
                // Use the notification context for tag processing instead of preview data
                $context = \Notifal\Modules\OnPageNotification\Application\Services\Utility\WidgetContextProvider::getContext();
                if ($context) {
                    return $this->tagManager->render($content, $context);
                }
            }
        }

        $previewData = $this->previewDataResolver->resolve($content);

        if ($previewData) {
            $context = $this->buildPreviewContext($previewData);
            return $this->tagManager->render($content, $context);
        }

        return $content;
    }

    /**
     * Build context array for template preview with all necessary data.
     *
     * @param mixed $previewData Resolved preview data
     * @return array Context array for tag rendering
     * @since 2.0.0
     */
    private function buildPreviewContext($previewData): array
    {
        // Use current user for preview instead of random user for better UX
        $currentUser = $this->userFetcher->getCurrent();
        $user = $currentUser ?: $this->userFetcher->getRandom();

        $context = [
            'product' => $previewData->getProduct(),
            'order' => $this->orderFetcher->getRandom(),
            'user' => $user,
            'post' => $this->contentSourceService->getRandomPost([]),
            'page' => $this->contentSourceService->getRandomPage([]),
            'comment' => $this->contentSourceService->getRandomComment([]),
            'is_preview' => true, // Enable fallback values for empty fields
        ];

        // Add sample custom post types to context
        $customPostTypes = $this->postTypeDiscoveryService->getFilteredCustomPostTypeNames();

        foreach ($customPostTypes as $postType) {
            $sampleCustomPost = $this->contentSourceService->getRandomCustomPostType($postType, []);
            if ($sampleCustomPost) {
                $context[$postType] = $sampleCustomPost;
            }
        }

        return $context;
    }


    /**
     * Export template data from Elementor.
     *
     * @param int $templateId
     * @return array Exported data array
     * @throws \Exception
     * @since 2.0.0
     */
    public function export(int $templateId): array
    {
        $document = Plugin::instance()->documents->get($templateId);

        if (! $document) {
            return [];
        }

        return $document->get_elements_raw_data();
    }

    /**
     * Import template data into a new Elementor template.
     *
     * @param array $data Imported template data array
     * @return int New post ID or 0 on failure
     * @since 2.0.0
     */
    public function import(array $data): int
    {
        // Validate required data
        if (empty($data['title']) || empty($data['content'])) {
            return 0;
        }

        // Prepare post data for Elementor template
        $post_data = [
            'post_title'   => sanitize_text_field($data['title']),
            'post_content' => '', // Elementor templates store content in meta
            'post_type'    => 'notifal_template',
            'post_status'  => 'draft',
        ];

        // Create the post
        $post_id = wp_insert_post($post_data);
        if (is_wp_error($post_id)) {
            return 0;
        }

        // Save Elementor data to post meta
        if (isset($data['content']) && is_array($data['content'])) {
            update_post_meta($post_id, '_elementor_data', wp_slash(wp_json_encode($data['content'])));
        }

        // Set Elementor template type if specified
        if (isset($data['type'])) {
            update_post_meta($post_id, '_elementor_template_type', sanitize_text_field($data['type']));
        }

        return $post_id;
    }

}
