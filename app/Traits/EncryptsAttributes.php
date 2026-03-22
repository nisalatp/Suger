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
            try {
                return Crypt::decryptString($value);
            } catch (\Exception $e) {
                return $value; // Return raw if decryption fails (e.g., already decrypted)
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
