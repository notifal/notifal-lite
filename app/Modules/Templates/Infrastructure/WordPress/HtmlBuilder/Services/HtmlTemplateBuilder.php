<?php

namespace Notifal\Modules\Templates\Infrastructure\WordPress\HtmlBuilder\Services;

use Notifal\Domain\Tags\TagManager;
use Notifal\Modules\OnPageNotification\Application\Services\Template\FrontendTemplateRenderer;
use Notifal\Modules\Templates\Application\Services\PreviewDataResolver;
use Notifal\Modules\Templates\Application\Services\TemplateBuilderDetector;
use Notifal\Modules\Templates\Contracts\TemplateBuilderInterface;
use Notifal\Domain\Orders\OrderFetcherInterface;
use Notifal\Infrastructure\WordPress\Services\UserFetcher;

defined('ABSPATH') || exit;

/**
 * Template builder service for the HTML Builder.
 *
 * @since 2.4.0
 * @author Hossein <hossein@notifal.com>
 */
class HtmlTemplateBuilder implements TemplateBuilderInterface
{
    /**
     * Preview data resolver for tag substitution.
     *
     * @var PreviewDataResolver
     */
    private PreviewDataResolver $previewDataResolver;

    /**
     * Order fetcher for preview context.
     *
     * @var OrderFetcherInterface
     */
    private OrderFetcherInterface $orderFetcher;

    /**
     * User fetcher for preview context.
     *
     * @var UserFetcher
     */
    private UserFetcher $userFetcher;

    /**
     * Tag manager for preview rendering.
     *
     * @var TagManager
     */
    private TagManager $tagManager;

    /**
     * HtmlTemplateBuilder constructor.
     *
     * @param PreviewDataResolver   $previewDataResolver Preview resolver.
     * @param OrderFetcherInterface $orderFetcher        Order fetcher.
     * @param UserFetcher           $userFetcher         User fetcher.
     * @since 2.4.0
     */
    public function __construct(
        PreviewDataResolver $previewDataResolver,
        OrderFetcherInterface $orderFetcher,
        UserFetcher $userFetcher
    ) {
        $this->previewDataResolver = $previewDataResolver;
        $this->orderFetcher = $orderFetcher;
        $this->userFetcher = $userFetcher;
        $this->tagManager = notifal_app(TagManager::class);
    }

    /**
     * Build preview content by replacing tags with resolved sample data.
     *
     * @param string $content Raw HTML content.
     * @return string Preview HTML.
     * @since 2.4.0
     */
    public function buildPreviewContent(string $content): string
    {
        $context = $this->previewDataResolver->buildTagRenderContext($content);

        if (empty($context['product'])) {
            return $content;
        }

        return $this->tagManager->render($content, $context);
    }

    /**
     * Render stored HTML for a template ID.
     *
     * @param int $templateId Template post ID.
     * @return string Rendered HTML.
     * @since 2.4.0
     */
    public function render(int $templateId): string
    {
        $post = get_post($templateId);
        if (!$post || $post->post_type !== 'notifal_template') {
            return '';
        }

        return (string) $post->post_content;
    }

    /**
     * Build a full preview using the frontend renderer pipeline.
     *
     * @param int $templateId Template post ID.
     * @return string Rendered preview HTML.
     * @since 2.4.0
     */
    public function buildPreview(int $templateId): string
    {
        /** @var FrontendTemplateRenderer $renderer */
        $renderer = notifal_app(FrontendTemplateRenderer::class);
        $result = $renderer->renderForFrontend($templateId, ['is_preview' => true]);

        return (string) ($result['html'] ?? '');
    }

    /**
     * Export HTML Builder template data.
     *
     * @param int $templateId Template post ID.
     * @return array Export payload.
     * @since 2.4.0
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
            'builder' => TemplateBuilderDetector::BUILDER_HTML,
            'content' => $post->post_content,
        ];
    }

    /**
     * Import HTML Builder template data into a new draft post.
     *
     * @param array $data Import payload.
     * @return int Created template post ID or 0 on failure.
     * @since 2.4.0
     */
    public function import(array $data): int
    {
        $sanitized = HtmlTemplateSanitizer::sanitize((string) ($data['content'] ?? ''));

        $postId = wp_insert_post([
            'post_title'   => sanitize_text_field($data['title'] ?? ''),
            'post_content' => $sanitized['content'],
            'post_type'    => 'notifal_template',
            'post_status'  => 'draft',
        ]);

        if (is_wp_error($postId) || !$postId) {
            return 0;
        }

        update_post_meta($postId, '_notifal_builder', TemplateBuilderDetector::BUILDER_HTML);

        return (int) $postId;
    }
}
