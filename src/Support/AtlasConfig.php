<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Atlas\Support;

use Illuminate\Contracts\Config\Repository as ConfigRepository;

/**
 * Typed reads of this package's config, under one prefix.
 *
 * Exists so that `config('laranail.atlas.…')` appears once rather than in every
 * class, and so callers get a `string` instead of a `mixed` that PHPStan then
 * has to be argued with. The prefix is a constant here and nowhere else — if
 * the namespacing convention ever moves, this is the single edit.
 */
final readonly class AtlasConfig
{
    public const string PREFIX = 'laranail.atlas';

    public function __construct(
        private ConfigRepository $config,
    ) {}

    public function string(string $key, string $default = ''): string
    {
        $value = $this->raw($key);

        return is_string($value) && $value !== '' ? $value : $default;
    }

    public function int(string $key, int $default = 0): int
    {
        $value = $this->raw($key);

        return is_numeric($value) ? (int) $value : $default;
    }

    public function bool(string $key, bool $default = false): bool
    {
        $value = $this->raw($key);

        return is_bool($value) ? $value : $default;
    }

    /**
     * @param  array<array-key, mixed>  $default
     * @return array<array-key, mixed>
     */
    public function array(string $key, array $default = []): array
    {
        $value = $this->raw($key);

        return is_array($value) ? $value : $default;
    }

    /**
     * A nullable string, for keys where "unset" and "empty" mean the same thing
     * and both mean "fall back to the framework" — the cache store name and the
     * presentation locale are both like this.
     */
    public function nullableString(string $key): ?string
    {
        $value = $this->raw($key);

        return is_string($value) && $value !== '' ? $value : null;
    }

    private function raw(string $key): mixed
    {
        return $this->config->get(self::PREFIX.'.'.$key);
    }
}
