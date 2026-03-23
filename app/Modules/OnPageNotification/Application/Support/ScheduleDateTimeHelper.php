<?php
/**
 * Schedule datetime parsing and normalization for on-page notification timing.
 *
 * @package Notifal\Modules\OnPageNotification\Application\Support
 * @since   2.2.0
 */

namespace Notifal\Modules\OnPageNotification\Application\Support;

use DateTimeImmutable;
use DateTimeZone;
use Notifal\Infrastructure\WordPress\Hooks\ActionHooks;

defined( 'ABSPATH' ) || exit;

/**
 * Normalizes schedule boundary strings between admin input, storage, and eligibility checks.
 *
 * Admin `datetime-local` values have no timezone; they are interpreted as the WordPress
 * site timezone on save. Stored values use UTC with a `Z` suffix for unambiguous parsing.
 * Naive ISO values (no `Z` / offset) use the WordPress site timezone for instant math ({@see self::parseToUtc()}), matching
 * {@see self::formatStoredUtcForAdminDisplay()} and {@see self::storedToDatetimeLocalForAdmin()}.
 * Human-readable admin formatting ({@see self::formatStoredUtcForAdminDisplay()}) treats naive ISO strings as site-local
 * wall time so list/AJAX copy matches `datetime-local` + on-page JS pass-through.
 *
 * @since 2.2.0
 * @author Hossein <hossein@notifal.com>
 */
final class ScheduleDateTimeHelper {

	/**
	 * Parses a stored or API boundary string to a UTC instant.
	 *
	 * @param string $value Raw value from meta or request.
	 * @return DateTimeImmutable|null UTC instant, or null when empty or invalid.
	 */
	public static function parseToUtc( string $value ): ?DateTimeImmutable {
		$value = trim( $value );
		if ( $value === '' ) {
			return null;
		}

		if ( preg_match( '/Z$/i', $value ) || preg_match( '/[+-]\d{2}:?\d{2}$/', $value ) ) {
			$dt = date_create_immutable( $value );
			if ( ! $dt ) {
				return null;
			}
			return $dt->setTimezone( new DateTimeZone( 'UTC' ) );
		}

		foreach ( array( 'Y-m-d\TH:i:s', 'Y-m-d\TH:i' ) as $format ) {
			$dt = DateTimeImmutable::createFromFormat( $format, $value, wp_timezone() );
			if ( $dt instanceof DateTimeImmutable ) {
				return $dt->setTimezone( new DateTimeZone( 'UTC' ) );
			}
		}

		return null;
	}

	/**
	 * Converts an admin-submitted datetime string to canonical UTC storage (`Y-m-d\TH:i:s\Z`).
	 *
	 * Naive `Y-m-d\TH:i` (and related) strings are read in the site timezone via `wp_timezone()`.
	 * Strings that already include a timezone are normalized to UTC `Z` form.
	 *
	 * @param string $value Raw value (typically from POST after `sanitize_text_field`).
	 * @return string Empty string when invalid; otherwise UTC `Z` format.
	 */
	public static function sanitizeIncomingToStoredUtc( string $value ): string {
		$value = trim( $value );
		if ( $value === '' ) {
			return '';
		}

		// Browsers may send fractional seconds from datetime-local; strip for fixed-width parsing.
		$value = preg_replace( '/\.\d+$/', '', $value );

		if ( preg_match( '/Z$/i', $value ) || preg_match( '/[+-]\d{2}:?\d{2}$/', $value ) ) {
			$utc = self::parseToUtc( $value );
			return $utc ? $utc->format( 'Y-m-d\TH:i:s\Z' ) : '';
		}

		$tz = wp_timezone();
		// Longer patterns first: datetime-local often includes seconds even when the UI shows minutes only.
		foreach ( array( 'Y-m-d\TH:i:s', 'Y-m-d\TH:i', 'Y-m-d H:i:s', 'Y-m-d H:i' ) as $format ) {
			$dt = DateTimeImmutable::createFromFormat( $format, $value, $tz );
			if ( $dt instanceof DateTimeImmutable ) {
				return $dt->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d\TH:i:s\Z' );
			}
		}

		$localized = self::parseUsStyleSpacedDateTime( $value, $tz );
		if ( $localized instanceof DateTimeImmutable ) {
			return $localized->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d\TH:i:s\Z' );
		}

		do_action( ActionHooks::ONPAGE_TIMING_SCHEDULE_INCOMING_PARSE_FAILED, $value );

		return '';
	}

	/**
	 * Copies timing settings and rewrites stored UTC schedule boundaries to site-local `Y-m-d\TH:i` for admin inputs.
	 *
	 * @since 2.2.0
	 * @param array $timing_settings Timing settings as loaded from persistence.
	 * @return array Same structure; `start_date` / `end_date` adjusted when non-empty.
	 */
	public static function withScheduleBoundariesForAdminDatetimeInputs( array $timing_settings ): array {
		$out = $timing_settings;
		if ( ! empty( $out['start_date'] ) ) {
			$out['start_date'] = self::storedToDatetimeLocalForAdmin( (string) $out['start_date'] );
		}
		if ( ! empty( $out['end_date'] ) ) {
			$out['end_date'] = self::storedToDatetimeLocalForAdmin( (string) $out['end_date'] );
		}
		return $out;
	}

	/**
	 * Parses US-style date/time strings that some browsers expose (spaces around separators, 12-hour clock).
	 *
	 * Example: `03 / 21 / 2026 , 07 : 26 PM`. Interpreted as month/day/year in the given timezone.
	 *
	 * @param string       $value Raw input.
	 * @param DateTimeZone $tz    Site timezone.
	 * @return DateTimeImmutable|null Parsed local instant or null.
	 */
	private static function parseUsStyleSpacedDateTime( string $value, DateTimeZone $tz ): ?DateTimeImmutable {
		if ( ! preg_match(
			'#^\s*(\d{1,2})\s*/\s*(\d{1,2})\s*/\s*(\d{4})\s*,?\s*(\d{1,2})\s*:\s*(\d{2})\s*(AM|PM)\s*$#i',
			$value,
			$m
		) ) {
			return null;
		}

		$month   = (int) $m[1];
		$day     = (int) $m[2];
		$year    = (int) $m[3];
		$hour12  = (int) $m[4];
		$minute  = (int) $m[5];
		$is_pm   = strtoupper( $m[6] ) === 'PM';

		$hour24 = $hour12 % 12;
		if ( $is_pm ) {
			$hour24 += 12;
		}

		$dt = DateTimeImmutable::createFromFormat(
			'Y-n-j G:i',
			sprintf( '%d-%d-%d %d:%02d', $year, $month, $day, $hour24, $minute ),
			$tz
		);

		return $dt instanceof DateTimeImmutable ? $dt : null;
	}

	/**
	 * Formats a stored boundary for HTML `datetime-local` in the site timezone.
	 *
	 * Uses the same Z vs naive rules as {@see self::formatStoredUtcForAdminDisplay()} so admin inputs match list rows
	 * and `wp_date()` output (avoids relying on browser JS offset fallbacks).
	 *
	 * @param string $stored Value from notification or campaign meta.
	 * @return string `Y-m-d\TH:i` for the input, or empty when invalid.
	 */
	public static function storedToDatetimeLocalForAdmin( string $stored ): string {
		$stored = trim( $stored );
		if ( $stored === '' ) {
			return '';
		}

		if ( preg_match( '/Z$/i', $stored ) || preg_match( '/[+-]\d{2}:?\d{2}$/', $stored ) ) {
			$utc = self::parseToUtc( $stored );
			if ( ! $utc ) {
				return '';
			}

			return $utc->setTimezone( wp_timezone() )->format( 'Y-m-d\TH:i' );
		}

		foreach ( array( 'Y-m-d\TH:i:s', 'Y-m-d\TH:i' ) as $format ) {
			$local = DateTimeImmutable::createFromFormat( $format, $stored, wp_timezone() );
			if ( $local instanceof DateTimeImmutable ) {
				return $local->format( 'Y-m-d\TH:i' );
			}
		}

		return '';
	}

	/**
	 * Formats a stored boundary for admin list banners and AJAX copy using General → date/time formats and site timezone.
	 *
	 * Strings with `Z` or a numeric offset are interpreted as UTC instants. Naive ISO values (no `Z` or offset) are
	 * interpreted as site-local wall time, matching on-page `storedScheduleToDatetimeLocalInputValue` pass-through and
	 * campaign `data-stored-schedule` + JS so list rows match the edit screen.
	 *
	 * @param string $stored Value from campaign or notification meta.
	 * @return string Localized datetime or empty when invalid.
	 */
	public static function formatStoredUtcForAdminDisplay( string $stored ): string {
		$stored = trim( $stored );
		if ( $stored === '' ) {
			return '';
		}

		$date_format = get_option( 'date_format' );
		$time_format = get_option( 'time_format' );
		$combined    = $date_format . ' ' . $time_format;

		if ( preg_match( '/Z$/i', $stored ) || preg_match( '/[+-]\d{2}:?\d{2}$/', $stored ) ) {
			$utc = self::parseToUtc( $stored );
			if ( ! $utc ) {
				return '';
			}

			return wp_date( $combined, $utc->getTimestamp() );
		}

		foreach ( array( 'Y-m-d\TH:i:s', 'Y-m-d\TH:i' ) as $format ) {
			$local = DateTimeImmutable::createFromFormat( $format, $stored, wp_timezone() );
			if ( $local instanceof DateTimeImmutable ) {
				return wp_date( $combined, $local->getTimestamp() );
			}
		}

		return '';
	}

	/**
	 * Returns the Unix timestamp for a boundary string, or null when empty or invalid.
	 *
	 * @param string $value Stored or raw boundary.
	 * @return int|null Unix timestamp in UTC, or null.
	 */
	public static function boundaryToUnixTimestamp( string $value ): ?int {
		$utc = self::parseToUtc( $value );
		if ( ! $utc ) {
			return null;
		}
		return $utc->getTimestamp();
	}

	/**
	 * Whether the current instant lies within inclusive start/end boundaries.
	 *
	 * An empty start means no lower bound; an empty end means no upper bound.
	 * Comparison uses real UTC Unix time via `time()`.
	 *
	 * @param string $start Stored start string (may be empty).
	 * @param string $end   Stored end string (may be empty).
	 * @return bool True when within the window.
	 */
	public static function isNowWithinBoundaries( string $start, string $end ): bool {
		$now = time();
		$start_ts = self::boundaryToUnixTimestamp( $start );
		$end_ts   = self::boundaryToUnixTimestamp( $end );
		if ( $start_ts !== null && $now < $start_ts ) {
			return false;
		}
		if ( $end_ts !== null && $now > $end_ts ) {
			return false;
		}
		return true;
	}
}
