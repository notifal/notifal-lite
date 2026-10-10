<?php

namespace Notifal\Modules\Forms\Application\Services\Security;

defined('ABSPATH') || exit;

/**
 * Prefix spreadsheet formula-injection characters in CSV cells.
 *
 * @since 3.0.0
 * @author Hossein <hossein@notifal.com>
 */
class CsvFormulaEscaper
{
    /**
     * Escape a single CSV cell value.
     *
     * @param string $value Raw cell.
     * @return string Safe cell text.
     * @since 3.0.0
     */
    public static function escape(string $value): string
    {
        if ($value === '') {
            return '';
        }

        $first = $value[0];
        if (in_array($first, ['=', '+', '-', '@'], true)) {
            return "'" . $value;
        }

        return $value;
    }
}
