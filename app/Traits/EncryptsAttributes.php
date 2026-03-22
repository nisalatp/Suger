<?php

namespace App\Traits;

use Illuminate\Support\Facades\Crypt;

/**
 * Trait for models with app-level encrypted fields (_enc suffix).
 * Automatically encrypts on set and decrypts on get.
 */
trait EncryptsAttributes
{
    /**
     * Define encrypted attributes in the model:
     * protected array $encryptedAttributes = ['notes_enc', 'full_name_enc'];
     */

    public function getAttribute($key)
    {
        $value = parent::getAttribute($key);

        if ($this->isEncryptedAttribute($key) && !is_null($value) && $value !== '') {
            // Primary: Crypt::decrypt() — handles data stored via Crypt::encrypt() (with serialize).
            // This is what was used when the data was originally written.
            try {
                return Crypt::decrypt($value);
            } catch (\Exception) {
                // ignore
            }
            // Fallback: Crypt::decryptString() — for data stored via Crypt::encryptString() (no serialize).
            try {
                return Crypt::decryptString($value);
            } catch (\Exception) {
                // ignore
            }
            // Last resort: plain PHP serialize() strings stored directly
            if (is_string($value) && preg_match('/^(s|a|O|i|b):\d+[:{]/', $value)) {
                $unserialized = @unserialize($value);
                if ($unserialized !== false) {
                    return $unserialized;
                }
            }
        }

        return $value;
    }




    public function setAttribute($key, $value)
    {
        if ($this->isEncryptedAttribute($key) && !is_null($value) && $value !== '') {
            $value = Crypt::encryptString($value);
        }

        return parent::setAttribute($key, $value);
    }

    protected function isEncryptedAttribute(string $key): bool
    {
        return property_exists($this, 'encryptedAttributes')
            && in_array($key, $this->encryptedAttributes, true);
    }
}
