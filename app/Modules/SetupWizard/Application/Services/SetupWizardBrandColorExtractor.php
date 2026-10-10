<?php

namespace Notifal\Modules\SetupWizard\Application\Services;

defined('ABSPATH') || exit;

/**
 * Extracts likely brand palette colors from public home page HTML.
 *
 * Theme-agnostic: scores semantic CSS custom properties, meta theme colors,
 * interactive UI fills, and saturated frequent accents without vendor prefixes.
 *
 * @since 3.0.0
 * @author Hossein <hossein@notifal.com>
 */
class SetupWizardBrandColorExtractor
{
    /** @var int Maximum palette colors returned from one scrape. */
    private const MAX_PALETTE_COLORS = 3;

    /** @var SetupWizardBrandColorNormalizer Color normalizer shared by all signals. */
    private $normalizer;

    /**
     * Wire the color normalizer helper.
     *
     * @since 3.0.0
     * @param SetupWizardBrandColorNormalizer $normalizer Color normalizer helper.
     */
    public function __construct(SetupWizardBrandColorNormalizer $normalizer)
    {
        // Keep normalization logic reusable and unit-testable.
        $this->normalizer = $normalizer;
    }

    /**
     * Extract one primary hex brand color from raw home page HTML.
     *
     * @since 3.0.0
     * @param string $html Raw HTML body.
     * @return string Normalized hex color or empty string.
     */
    public function extractPrimaryColor(string $html): string
    {
        // First palette entry is the primary for backward compatibility.
        $palette = $this->extractPaletteColors($html);

        return $palette[0] ?? '';
    }

    /**
     * Extract up to three distinct brand colors from theme-agnostic signals.
     *
     * @since 3.0.0
     * @param string $html Raw HTML body.
     * @return string[] Normalized unique hex colors.
     */
    public function extractPaletteColors(string $html): array
    {
        // Accumulate scored candidates keyed by normalized hex.
        $scores = [];

        // Score semantic CSS custom properties across any --* variable name.
        $this->scoreCssVariableCandidates($html, $scores);

        // Score browser theme-color / tile-color meta tags.
        $this->scoreMetaThemeColor($html, $scores);

        // Score backgrounds used on interactive UI (buttons, CTA links).
        $this->scoreInteractiveUiColors($html, $scores);

        // Score frequent saturated accents from inline styles and style blocks.
        $this->scoreFrequentInlineColors($html, $scores);

        if ([] === $scores) {
            return [];
        }

        // Rank by total score so stronger brand signals win.
        $ranked = array_keys($scores);
        usort(
            $ranked,
            static function (string $left, string $right) use ($scores): int {
                return $scores[$right] <=> $scores[$left];
            }
        );

        // Cap palette size so wizard profile storage stays bounded.
        return array_slice($ranked, 0, self::MAX_PALETTE_COLORS);
    }

    /**
     * Add score for one candidate color when it is a usable brand accent.
     *
     * @since 3.0.0
     * @param array<string, int> $scores Score map keyed by hex.
     * @param string             $rawColor Raw CSS color token.
     * @param int                $baseScore Role-based base score.
     * @return void
     */
    private function addScore(array &$scores, string $rawColor, int $baseScore): void
    {
        // Normalize and reject neutrals / product defaults early.
        $color = $this->normalizer->normalize($rawColor);
        if (!$this->normalizer->isBrandCandidate($color)) {
            return;
        }

        // Start from the role score and add saturation / mid-lightness bonuses.
        $score = $baseScore;
        $score += (int) floor($this->normalizer->saturationDelta($color) / 8);
        $lightness = $this->normalizer->lightness($color);
        if ($lightness >= 60 && $lightness <= 200) {
            $score += 12;
        }

        // Soft-penalize common default link blues when better accents compete.
        if ($this->normalizer->isIncidentalBlue($color)) {
            $score -= 45;
        }

        // Drop candidates whose net score is non-positive after penalties.
        if ($score < 1) {
            return;
        }

        if (!isset($scores[$color])) {
            $scores[$color] = 0;
        }
        $scores[$color] += $score;
    }

    /**
     * Score CSS custom properties whose names contain semantic brand tokens.
     *
     * @since 3.0.0
     * @param string             $html Raw HTML body.
     * @param array<string, int> $scores Score map keyed by hex.
     * @return void
     */
    private function scoreCssVariableCandidates(string $html, array &$scores): void
    {
        // Capture every custom property declaration in markup or style blocks.
        if (!preg_match_all('/--([A-Za-z0-9_-]+)\s*:\s*([^;}\n]+)/', $html, $matches, PREG_SET_ORDER)) {
            return;
        }

        foreach ($matches as $match) {
            $name = strtolower((string) ($match[1] ?? ''));
            $value = (string) ($match[2] ?? '');
            // Map the variable name to a semantic role score (0 = ignore).
            $roleScore = $this->cssVariableRoleScore($name);
            if ($roleScore <= 0) {
                continue;
            }
            // Slightly prefer non-hover declarations when both exist.
            if (false !== strpos($name, 'hover')) {
                $roleScore -= 8;
            }
            $this->addScore($scores, $value, $roleScore);
        }
    }

    /**
     * Resolve a role score from a CSS custom property name (theme-agnostic).
     *
     * @since 3.0.0
     * @param string $name Lowercased custom property name without leading dashes.
     * @return int Base score, or 0 when the name is not semantic.
     */
    private function cssVariableRoleScore(string $name): int
    {
        // Highest priority: explicit primary / brand tokens in any vendor naming.
        if (preg_match('/primary|brand/', $name)) {
            return 120;
        }
        // Strong accents and theme-color tokens used for UI chrome.
        if (preg_match('/accent|theme[-_]?color/', $name)) {
            return 100;
        }
        // Button fills are reliable brand signals across themes.
        if (preg_match('/button|\bbtn\b|btn[-_]/', $name)) {
            return 90;
        }
        // Secondary / tertiary palette entries fill remaining slots.
        if (preg_match('/secondary|tertiary/', $name)) {
            return 55;
        }

        return 0;
    }

    /**
     * Score theme-color and tile-color meta tags.
     *
     * @since 3.0.0
     * @param string             $html Raw HTML body.
     * @param array<string, int> $scores Score map keyed by hex.
     * @return void
     */
    private function scoreMetaThemeColor(string $html, array &$scores): void
    {
        // Match theme-color / tile-color meta tags in either attribute order.
        $patterns = [
            '/<meta[^>]+name=["\']theme-color["\'][^>]+content=["\']([^"\']+)["\']/i',
            '/<meta[^>]+content=["\']([^"\']+)["\'][^>]+name=["\']theme-color["\']/i',
            '/<meta[^>]+name=["\']msapplication-TileColor["\'][^>]+content=["\']([^"\']+)["\']/i',
            '/<meta[^>]+content=["\']([^"\']+)["\'][^>]+name=["\']msapplication-TileColor["\']/i',
        ];

        foreach ($patterns as $pattern) {
            if (!preg_match($pattern, $html, $matches)) {
                continue;
            }
            // Meta theme colors are strong but below primary CSS variables.
            $this->addScore($scores, (string) ($matches[1] ?? ''), 70);
        }
    }

    /**
     * Score background colors used on buttons and CTA-like interactive elements.
     *
     * @since 3.0.0
     * @param string             $html Raw HTML body.
     * @param array<string, int> $scores Score map keyed by hex.
     * @return void
     */
    private function scoreInteractiveUiColors(string $html, array &$scores): void
    {
        // Inline backgrounds on button / CTA anchors and button elements.
        $inlinePattern = '/<(?:a|button)\b[^>]*class=["\'][^"\']*\b(?:btn|button|cta|wp-block-button__link)\b[^"\']*["\'][^>]*style=["\']([^"\']*)["\']/i';
        if (preg_match_all($inlinePattern, $html, $inlineMatches)) {
            foreach ($inlineMatches[1] as $styleAttr) {
                $this->scoreBackgroundTokens((string) $styleAttr, $scores, 65);
            }
        }

        // Style-block backgrounds under selectors that mention button / CTA classes.
        $rulePattern = '/[^{}]*\b(?:btn|button|cta|wp-block-button)\b[^{}]*\{([^}]+)\}/i';
        if (preg_match_all($rulePattern, $html, $ruleMatches)) {
            foreach ($ruleMatches[1] as $declarations) {
                $this->scoreBackgroundTokens((string) $declarations, $scores, 60);
            }
        }
    }

    /**
     * Score background / background-color tokens inside a CSS declaration blob.
     *
     * @since 3.0.0
     * @param string             $css Declarations or style attribute text.
     * @param array<string, int> $scores Score map keyed by hex.
     * @param int                $baseScore Role score for interactive fills.
     * @return void
     */
    private function scoreBackgroundTokens(string $css, array &$scores, int $baseScore): void
    {
        // Pull background fills rather than text color (often link blue).
        if (!preg_match_all('/background(?:-color)?\s*:\s*([^;]+)/i', $css, $matches)) {
            return;
        }

        foreach ($matches[1] as $rawValue) {
            $this->addScore($scores, (string) $rawValue, $baseScore);
        }
    }

    /**
     * Score frequent non-neutral colors found in markup and style blocks.
     *
     * @since 3.0.0
     * @param string             $html Raw HTML body.
     * @param array<string, int> $scores Score map keyed by hex.
     * @return void
     */
    private function scoreFrequentInlineColors(string $html, array &$scores): void
    {
        // Count hex literals as weak frequency signals.
        if (preg_match_all('/#([A-Fa-f0-9]{3}|[A-Fa-f0-9]{6})\b/', $html, $hexMatches)) {
            foreach ($hexMatches[0] as $rawColor) {
                $this->addScore($scores, (string) $rawColor, 8);
            }
        }

        // Count rgb()/rgba() literals the same way (common in theme CSS vars).
        if (preg_match_all('/rgba?\(\s*\d{1,3}\s*,\s*\d{1,3}\s*,\s*\d{1,3}(?:\s*,\s*[0-9.]+)?\s*\)/i', $html, $rgbMatches)) {
            foreach ($rgbMatches[0] as $rawColor) {
                $this->addScore($scores, (string) $rawColor, 8);
            }
        }

        // Count hsl()/hsla() literals for themes that store accents that way.
        if (preg_match_all('/hsla?\(\s*[0-9.]+\s*,\s*[0-9.]+%\s*,\s*[0-9.]+%(?:\s*,\s*[0-9.]+)?\s*\)/i', $html, $hslMatches)) {
            foreach ($hslMatches[0] as $rawColor) {
                $this->addScore($scores, (string) $rawColor, 8);
            }
        }
    }
}
