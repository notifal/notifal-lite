<?php
namespace Notifal\Modules\SetupWizard\Domain\Constants;
defined('ABSPATH') || exit;
/**
 * Bounds for Setup Wizard hosted site analysis suggestion lists.
 *
 * Keep MIN/MAX in sync with WizardPromptBuilder suggestion constants in notifal-ai-gateway.
 *
 * @since 3.0.0
 * @author Hossein <hossein@notifal.com>
 */
final class SetupWizardAnalysisLimits
{
    /**
     * Minimum notification suggestions required from hosted analysis.
     *
     * @var int
     */
    public const MIN_SUGGESTIONS = 12;
    /**
     * Maximum notification suggestions accepted from hosted analysis.
     *
     * @var int
     */
    public const MAX_SUGGESTIONS = 20;
}
