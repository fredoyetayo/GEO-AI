<?php
/**
 * Encryption trait for secure data storage.
 *
 * @package GeoAI
 */

namespace GeoAI\Traits;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Trait for encrypting and decrypting sensitive data.
 */
trait Encryption {
    /**
     * Check if sodium extension is available.
     *
     * @return bool
     */
    private function is_sodium_available() {
        return function_exists( 'sodium_crypto_secretbox' );
    }

    /**
     * Get encryption key.
     *
     * @return string
     */
    private function get_encryption_key() {
        $key = get_option( 'geoai_encryption_key' );

        if ( ! $key ) {
            if ( $this->is_sodium_available() ) {
                $key = sodium_bin2hex( random_bytes( SODIUM_CRYPTO_SECRETBOX_KEYBYTES ) );
            } else {
                $key = bin2hex( random_bytes( 32 ) );
            }
            update_option( 'geoai_encryption_key', $key, false );
        }

        return $key;
    }

    /**
     * Encrypt a value.
     *
     * @param string $value Value to encrypt.
     * @return string
     */
    protected function encrypt( $value ) {
        if ( empty( $value ) ) {
            return '';
        }

        if ( $this->is_sodium_available() ) {
            return $this->encrypt_sodium( $value );
        }

        return $this->encrypt_fallback( $value );
    }

    /**
     * Decrypt a value.
     *
     * @param string $encrypted Encrypted value.
     * @return string
     */
    protected function decrypt( $encrypted ) {
        if ( empty( $encrypted ) ) {
            return '';
        }

        if ( $this->is_sodium_available() ) {
            return $this->decrypt_sodium( $encrypted );
        }

        return $this->decrypt_fallback( $encrypted );
    }

    /**
     * Encrypt using sodium.
     *
     * @param string $value Value to encrypt.
     * @return string
     */
    private function encrypt_sodium( $value ) {
        $key   = hex2bin( $this->get_encryption_key() );
        $nonce = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );

        $encrypted = sodium_crypto_secretbox( $value, $nonce, $key );
        $result    = base64_encode( $nonce . $encrypted );

        sodium_memzero( $key );

        return $result;
    }

    /**
     * Decrypt using sodium.
     *
     * @param string $encrypted Encrypted value.
     * @return string
     */
    private function decrypt_sodium( $encrypted ) {
        $decoded = base64_decode( $encrypted );
        if ( false === $decoded ) {
            return '';
        }

        $key        = hex2bin( $this->get_encryption_key() );
        $nonce      = mb_substr( $decoded, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES, '8bit' );
        $ciphertext = mb_substr( $decoded, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES, null, '8bit' );

        $decrypted = sodium_crypto_secretbox_open( $ciphertext, $nonce, $key );

        sodium_memzero( $key );

        return false !== $decrypted ? $decrypted : '';
    }

    /**
     * Fallback encryption using base64 and XOR (weak, but better than plaintext).
     *
     * @param string $value Value to encrypt.
     * @return string
     */
    private function encrypt_fallback( $value ) {
        $key    = $this->get_encryption_key();
        $result = '';

        for ( $i = 0; $i < strlen( $value ); $i++ ) {
            $result .= chr( ord( $value[ $i ] ) ^ ord( $key[ $i % strlen( $key ) ] ) );
        }

        return base64_encode( 'fallback:' . $result );
    }

    /**
     * Fallback decryption.
     *
     * @param string $encrypted Encrypted value.
     * @return string
     */
    private function decrypt_fallback( $encrypted ) {
        $decoded = base64_decode( $encrypted );
        if ( false === $decoded ) {
            return '';
        }

        if ( 0 !== strpos( $decoded, 'fallback:' ) ) {
            return '';
        }

        $decoded = substr( $decoded, 9 );
        $key     = $this->get_encryption_key();
        $result  = '';

        for ( $i = 0; $i < strlen( $decoded ); $i++ ) {
            $result .= chr( ord( $decoded[ $i ] ) ^ ord( $key[ $i % strlen( $key ) ] ) );
        }

        return $result;
    }
}
