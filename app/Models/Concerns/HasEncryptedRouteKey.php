<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Support\IdCipher;
use Illuminate\Database\Eloquent\Casts\Attribute;

/**
 * Swaps the primary key for an opaque token in the routes that address the
 * model, so an address never carries an enumerable id.
 *
 * The token is what `getRouteKey()` returns, which is what makes `route()`
 * build the encrypted URL and the router hand the token back to the binding.
 * The same token is serialised as `key` beside the id, because the pages build
 * their own links from the payload they were given and need it there.
 */
trait HasEncryptedRouteKey
{
    public function getRouteKey(): string
    {
        return app(IdCipher::class)->encrypt($this->getKey());
    }

    /**
     * The token travels with the model as `key`, beside the id, because the
     * pages build their own links from the payload they were given.
     *
     * @return array<string, string>
     */
    protected function getArrayableAppends()
    {
        return parent::getArrayableAppends() + ['key' => 'key'];
    }

    /**
     * Resolve a bound token back to its record. A token nobody issued decrypts
     * to nothing, and a lookup on nothing matches no row, so the binding
     * answers 404 rather than reaching the wrong model.
     *
     * @param  \Illuminate\Database\Eloquent\Model|\Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Relations\Relation  $query
     * @return \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Relations\Relation
     */
    public function resolveRouteBindingQuery($query, $value, $field = null)
    {
        $id = is_string($value) ? app(IdCipher::class)->decrypt($value) : null;

        return $query->where($field ?? $this->getRouteKeyName(), $id);
    }

    protected function key(): Attribute
    {
        return Attribute::get(fn (): string => $this->getRouteKey());
    }
}
