<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Atlas\Core\Support;

use DateTimeImmutable;
use Stringable;

/**
 * A dataset stamp, split into the date it can be aged by and the source it came from.
 *
 * ## The bug this exists to prevent
 *
 * `doctor` asks three questions, and the second is "how old is this data?" — the
 * one no test can answer, because a catalogue nobody has regenerated since a
 * country changed its name is wrong in a way that still parses. It answered that
 * question with `strtotime($version) < strtotime('-1 year')`.
 *
 * The stamp was `rinvex/countries v9.1.0`. `strtotime()` returns **false** for
 * that, `false < anything` is never reached because the guard rejected it, and
 * the check therefore returned "not stale" for every dataset ever shipped. It
 * did not warn wrongly; it did not warn at all, which is worse — a health check
 * that cannot fail reads as a passing one.
 *
 * So the stamp now carries both, date first: `2025-07-14 rinvex/countries v9.1.0`.
 * The date is the **source release date** read from composer's `installed.json`,
 * not the build date, because regenerating from an unchanged source produces
 * identical data and must not reset its age. Parsing is deliberately narrow —
 * an exact leading `YYYY-MM-DD` or nothing — rather than another `strtotime()`
 * guess at free text, which is the failure being fixed.
 */
final readonly class DatasetVersion implements Stringable
{
    private const string DATE_FORMAT = 'Y-m-d';

    private function __construct(
        public string $raw,
        public ?string $date,
        public string $source,
    ) {}

    /**
     * Split a stamp into its date and its provenance.
     *
     * A stamp with no parseable leading date is not an error — it is every
     * dataset built before this format, and a custom source is free to stamp
     * whatever it likes. Those get a null date and the whole string as the
     * source, and {@see isDated()} is how a caller tells the difference. Guessing
     * a date for them is what the old check effectively did.
     */
    public static function parse(string $raw): self
    {
        $raw = trim($raw);

        [$candidate, $rest] = array_pad(explode(' ', $raw, 2), 2, '');

        if (! self::isCalendarDate($candidate)) {
            return new self($raw, null, $raw);
        }

        $source = trim($rest);

        return new self($raw, $candidate, $source === '' ? $raw : $source);
    }

    /**
     * Whether this stamp can be aged at all.
     *
     * Checked separately from {@see isOlderThan()} so an undatable stamp reports
     * as unknown rather than as current. They are different answers, and
     * collapsing them is precisely how the old check went quiet.
     */
    public function isDated(): bool
    {
        return $this->date !== null;
    }

    /**
     * Whether the source data predates a cutoff.
     *
     * The cutoff is passed in rather than computed here so the caller owns the
     * threshold and a test owns the clock. **False for an undatable stamp** — not
     * because it is current, but because this method cannot say; ask
     * {@see isDated()} first, which is what `doctor` does.
     */
    public function isOlderThan(DateTimeImmutable $cutoff): bool
    {
        $date = $this->toDate();

        return $date instanceof DateTimeImmutable && $date < $cutoff;
    }

    public function toDate(): ?DateTimeImmutable
    {
        if ($this->date === null) {
            return null;
        }

        $parsed = DateTimeImmutable::createFromFormat('!' . self::DATE_FORMAT, $this->date);

        return $parsed === false ? null : $parsed;
    }

    public function __toString(): string
    {
        return $this->raw;
    }

    /**
     * An exact `YYYY-MM-DD` that is also a real day.
     *
     * The round-trip is the point: `2025-13-45` matches any regex you would
     * write for this shape, and PHP would roll it forward into 2026 rather than
     * rejecting it. Re-formatting the parsed value and comparing catches that.
     */
    private static function isCalendarDate(string $candidate): bool
    {
        if ($candidate === '') {
            return false;
        }

        $parsed = DateTimeImmutable::createFromFormat('!' . self::DATE_FORMAT, $candidate);

        return $parsed !== false && $parsed->format(self::DATE_FORMAT) === $candidate;
    }
}
