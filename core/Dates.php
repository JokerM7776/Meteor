<?php
declare(strict_types=1);
namespace Core;

/**
 * Dates and times as the site shows them. Everything is stored in UTC; Settings → Date & time
 * picks the time zone, the date format and a 12- or 24-hour clock.
 */
final class Dates
{
    /** Date formats: ICU pattern => label. "locale" uses each language's own medium style. */
    public const FORMATS = [
        'dd MMM yyyy' => 'DD MMM YYYY', 'MMM d, yyyy' => 'MMM D, YYYY', 'd MMMM yyyy' => 'D MMMM YYYY',
        'MMMM d, yyyy' => 'MMMM D, YYYY', 'yyyy-MM-dd' => 'YYYY-MM-DD', 'dd/MM/yyyy' => 'DD/MM/YYYY',
        'MM/dd/yyyy' => 'MM/DD/YYYY', 'dd.MM.yyyy' => 'DD.MM.YYYY', 'locale' => 'Each language’s own style',
    ];
    public const DEFAULT_FORMAT = 'dd MMM yyyy';
    private static string $format = self::DEFAULT_FORMAT;
    private static bool $hour12 = false;
    private static string $zone = 'UTC';

    public static function configure(string $format = self::DEFAULT_FORMAT, string $clock = '24', string $zone = 'UTC'): void
    {
        self::$format = isset(self::FORMATS[$format]) ? $format : self::DEFAULT_FORMAT;
        self::$hour12 = $clock === '12';
        self::$zone = self::validZone($zone) ? $zone : 'UTC';
    }

    /** Reads the settings. A database without them (or without the table yet) keeps the defaults. */
    public static function load(Database $db): void
    {
        try {
            $rows = $db->all("SELECT `key`, value FROM settings WHERE locale_scope = '' AND `key` IN ('date_format', 'time_format', 'timezone')");
        } catch (\Throwable) { $rows = []; }
        $values = array_column($rows, 'value', 'key');
        self::configure($values['date_format'] ?? self::DEFAULT_FORMAT, $values['time_format'] ?? '24', $values['timezone'] ?? 'UTC');
    }

    public static function validZone(string $zone): bool { return $zone === 'UTC' || in_array($zone, \DateTimeZone::listIdentifiers(), true); }
    public static function zone(): \DateTimeZone { return new \DateTimeZone(self::$zone); }
    public static function format(): string { return self::$format; }
    public static function hour12(): bool { return self::$hour12; }

    /** A stored UTC time in the site's time zone. A bare date ("2026-10-01") is already a calendar day and is not moved. */
    public static function local(string $utc): \DateTimeImmutable
    {
        $utc = trim($utc);
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $utc)) { return new \DateTimeImmutable($utc, self::zone()); }
        return (new \DateTimeImmutable($utc, new \DateTimeZone('UTC')))->setTimezone(self::zone());
    }

    /** The date in the site format, or in an ICU $pattern, for $locale. */
    public static function date(string $utc, string $locale, ?string $pattern = null): string
    {
        $pattern ??= self::$format;
        $formatter = $pattern === 'locale'
            ? new \IntlDateFormatter($locale, \IntlDateFormatter::MEDIUM, \IntlDateFormatter::NONE, self::$zone)
            : new \IntlDateFormatter($locale, \IntlDateFormatter::NONE, \IntlDateFormatter::NONE, self::$zone, null, $pattern);
        return (string) $formatter->format(self::local($utc));
    }

    public static function time(string $utc, string $locale): string
    {
        return self::date($utc, $locale, self::$hour12 ? 'h:mm a' : 'HH:mm');
    }

    public static function dateTime(string $utc, string $locale): string
    {
        return self::date($utc, $locale) . ', ' . self::time($utc, $locale);
    }

    /** "2026-10-25T14:30" for a datetime-local field, in the site's time zone. */
    public static function toInput(string $utc): string { return self::local($utc)->format('Y-m-d\TH:i'); }

    /** A wall-clock time in the site's time zone → "Y-m-d H:i:s" in UTC. */
    public static function toUtc(\DateTimeImmutable $local): string
    {
        return $local->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }

    /** "UTC+08:00" for the current offset of a zone. */
    public static function offset(string $zone): string
    {
        $seconds = (new \DateTimeZone($zone))->getOffset(new \DateTimeImmutable('now'));
        return 'UTC' . ($seconds < 0 ? '−' : '+') . sprintf('%02d:%02d', intdiv(abs($seconds), 3600), intdiv(abs($seconds) % 3600, 60));
    }
}
