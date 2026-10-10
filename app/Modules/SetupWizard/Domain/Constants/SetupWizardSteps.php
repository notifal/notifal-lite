<?php
namespace Notifal\Modules\SetupWizard\Domain\Constants;
defined('ABSPATH') || exit;
/**
 * Setup Wizard step slugs shared by PHP and JavaScript.
 *
 * @since 3.0.0
 * @author Hossein <hossein@notifal.com>
 */
final class SetupWizardSteps
{
    /**
     * Scanning home page content and active plugins.
     *
     * @var string
     */
    public const SCAN = 'scan';
    /**
     * Showing Noti welcome pitch after site analysis.
     *
     * @var string
     */
    public const INTRO = 'intro';
    /**
     * Confirming detected industry and ecommerce flag.
     *
     * @var string
     */
    public const INDUSTRY = 'industry';
    /**
     * Reviewing AI notification suggestions in the chat wizard.
     *
     * @var string
     */
    public const SUGGESTIONS = 'suggestions';
    /**
     * Building selected notifications after connect.
     *
     * @var string
     */
    public const BUILD = 'build';
    /**
     * Selecting a conversion goal or use case.
     *
     * @var string
     */
    public const GOAL = 'goal';
    /**
     * Reviewing marketplace recommendations or custom build.
     *
     * @var string
     */
    public const RECOMMENDATIONS = 'recommendations';
    /**
     * Connecting a Notifal account.
     *
     * @var string
     */
    public const CONNECT = 'connect';
    /**
     * Asking the admin to accept free Notifal Pro setup (license + install).
     *
     * @var string
     */
    public const PRO_ACCEPT = 'pro_accept';
    /**
     * Creating the free Pro license and installing or activating Notifal Pro.
     *
     * @var string
     */
    public const TRIAL = 'trial';
    /**
     * Final success screen.
     *
     * @var string
     */
    public const SUCCESS = 'success';
}
