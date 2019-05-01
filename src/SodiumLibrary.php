<?php

namespace Layerworx\Phpsodium;

use Layerworx\Phpsodium\Exceptions\DecryptionException;
use Layerworx\Phpsodium\Exceptions\HashLengthException;
use Layerworx\Phpsodium\Exceptions\KeyTypeException;
use Layerworx\Phpsodium\Exceptions\SignatureException;
use Sodium;

class SodiumLibrary
{
    /**
     * Return an amount of binary entropy.
     *
     * @param int $amount The amount of entropy you want to generate
     *
     * @return string
     */
    public static function entropy($amount = SODIUM_CRYPTO_BOX_NONCEBYTES)
    {
        return random_bytes($amount);
    }

    /**
     * Return a hexadecimal string from binary.
     *
     * @param string $hexString The hexadecimal string being converted to binary
     *
     * @return string
     */
    public static function bin2hex($hexString)
    {
        return sodium_bin2hex($hexString);
    }

    /**
     * Return a binary string from hexadecimal.
     *
     * @param string $binString The binary string being converted to hexadecimal
     * @param string $ignore    Characters to ignore in the hexadecimal string
     *
     * @return string
     */
    public static function hex2bin($binString, $ignore = '')
    {
        return sodium_hex2bin($binString, $ignore);
    }

    /**
     * Wipe a variable from PHP's memory.
     *
     * @param $variable
     *
     * @return void
     */
    public static function wipeMemory($variable)
    {
        sodium_memzero($variable);
    }

    /**
     * The raw hash method simply hashes a string with specific options with Sodium.
     *
     * @param string $data   The message to be hashed
     * @param string $key    The key to hash the data to
     * @param int    $length The length of the cache to be returned
     *
     * @return string
     */
    public static function rawHash($data, $key = null, $length = SODIUM_CRYPTO_GENERICHASH_BYTES)
    {
        # Test to make sure the length is within bounds
        if (!($length >= SODIUM_CRYPTO_GENERICHASH_BYTES_MIN && $length <= SODIUM_CRYPTO_GENERICHASH_BYTES_MAX)) {
            throw new HashLengthException(sprintf('Hash length should be between %s and %s',
                SODIUM_CRYPTO_GENERICHASH_BYTES_MIN,
                SODIUM_CRYPTO_GENERICHASH_BYTES_MAX
            ));
        }

        # Test if a key is set, if it is, generate a key if true, or use a key if set
        if ($key !== null) {
            if ($key === true) {
                $key = random_bytes(SODIUM_CRYPTO_GENERICHASH_KEYBYTES);
            } else {
                $key = self::rawHash($key);
            }
        }

        return sodium_crypto_generichash($data, $key, $length);
    }

    /**
     * A drop in replacement for md5() hashing.
     *
     * @param string $data The data to be hashed
     *
     * @return string
     */
    public static function hash($data)
    {
        return self::rawHash($data, null, SODIUM_CRYPTO_GENERICHASH_BYTES_MIN);
    }

    /**
     * A longer more secure hash with less chance of collision.
     *
     * @param string $data The data to be hashed
     *
     * @return string
     */
    public static function secureHash($data)
    {
        return self::rawHash($data);
    }

    /**
     * A very long hash which is designed to be very unique with the least chance of collision.
     *
     * @param string $data The data to be hashed
     *
     * @return string
     */
    public static function veryUniqueHash($data)
    {
        return self::rawHash($data, null, SODIUM_CRYPTO_GENERICHASH_BYTES_MAX);
    }

    /**
     * The keyedHash method hashes a message with a key for salt, requiring verification to know the key.
     *
     * @param string $data   The data to be hashed
     * @param string $key    The key to hash the data against
     * @param int    $length The length of the hash to generate, defaults to SODIUM_CRYPTO_GENERICHASH_BYTES
     *
     * @return string
     */
    public static function keyedHash($data, $key, $length = SODIUM_CRYPTO_GENERICHASH_BYTES)
    {
        # Test to make sure the key is a string
        if (!is_string($key)) {
            throw new KeyTypeException('keyedHash expects a string as the key');
        }

        return self::rawHash($data, $key, $length);
    }

    /**
     * Hash a password using Sodium for later verification, optionally add slowness.
     *
     * @param string     $plaintext   The plaintext password to be hashed
     * @param bool|false $extraSecure Add additional slowness to the hashing technique for security
     *
     * @return string
     */
    public static function hashPassword($plaintext, $extraSecure = false)
    {
        # Create the password hash
        if ($extraSecure) {
            $passwordHash = sodium_crypto_pwhash_scryptsalsa208sha256_str(
                $plaintext,
                SODIUM_CRYPTO_PWHASH_SCRYPTSALSA208SHA256_OPSLIMIT_SENSITIVE,
                SODIUM_CRYPTO_PWHASH_SCRYPTSALSA208SHA256_MEMLIMIT_SENSITIVE
            );
        } else {
            $passwordHash = sodium_crypto_pwhash_scryptsalsa208sha256_str(
                $plaintext,
                SODIUM_CRYPTO_PWHASH_SCRYPTSALSA208SHA256_OPSLIMIT_INTERACTIVE,
                SODIUM_CRYPTO_PWHASH_SCRYPTSALSA208SHA256_MEMLIMIT_INTERACTIVE
            );
        }

        return $passwordHash;
    }

    /**
     * Verify if a password is correct by matching a plaintext password to a hash.
     *
     * @param string $password
     * @param string $hash
     *
     * @return bool
     */
    public static function checkPassword($password, $hash)
    {
        if (sodium_crypto_pwhash_scryptsalsa208sha256_str_verify($hash, $password)) {
            self::wipeMemory($password);

            return true;
        }
        self::wipeMemory($password);

        return false;
    }

    /**
     * Encrypt a message using a key.
     *
     * @param string $message A message in string format
     * @param string $key     A binary hashed key
     *
     * @return string
     */
    public static function encrypt($message, $key)
    {
        # Generate entropy to encrypt the data
        $nonce = self::entropy();

        # Encrypt the message
        $messageEncrypted = sodium_crypto_secretbox($message, $nonce, self::rawHash($key, null, SODIUM_CRYPTO_SECRETBOX_KEYBYTES));

        return sprintf('%s.%s', self::bin2hex($nonce), self::bin2hex($messageEncrypted));
    }

    /**
     * Decrypt a message using a key.
     *
     * @param string $message
     * @param string $key
     *
     * @return mixed
     */
    public static function decrypt($message, $key)
    {
        $payload = explode('.', $message);

        $decryption = sodium_crypto_secretbox_open(
            sodium_hex2bin($payload[1]),
            sodium_hex2bin($payload[0]),
            self::rawHash($key, null, SODIUM_CRYPTO_SECRETBOX_KEYBYTES)
        );

        if (!$decryption) {
            throw new DecryptionException('The key provided cannot decrypt the message');
        }

        return $decryption;
    }

    /**
     * Generate a public and private keypair for encryption.
     *
     * @return array
     */
    public static function genBoxKeypair()
    {
        $keypair = sodium_crypto_box_keypair();

        return [
            'pub' => sodium_crypto_box_publickey($keypair),
            'pri' => sodium_crypto_box_secretkey($keypair),
        ];
    }

    /**
     * Generate a public and private keypair for signing.
     *
     * @return array
     */
    public static function genSignKeypair()
    {
        $keypair = sodium_crypto_sign_keypair();

        return [
            'pub' => sodium_crypto_sign_publickey($keypair),
            'pri' => sodium_crypto_sign_secretkey($keypair),
        ];
    }

    /**
     * Encrypt a message to a recipient.
     *
     * @param string $receiving_pub The receiving user's public key.
     * @param string $sending_priv  The sending user's private key.
     * @param string $message       The message to the receiving user.
     *
     * @return string
     */
    public static function messageSendEncrypt($receiving_pub, $sending_priv, $message)
    {
        # Create a keypair to send an encrypted message
        $messageKey = sodium_crypto_box_keypair_from_secretkey_and_publickey(
            $sending_priv,
            $receiving_pub
        );

        # Create entropy for the message
        $nonce = self::entropy(SODIUM_CRYPTO_BOX_NONCEBYTES);

        $message = sodium_crypto_box(
            $message,
            $nonce,
            $messageKey
        );

        return sprintf('%s.%s', self::bin2hex($nonce), self::bin2hex($message));
    }

    /**
     * Decrypt a message from a recipient.
     *
     * @param string $receiving_priv The receiving user's private key.
     * @param string $sending_pub    The sending user's public key.
     * @param string $payload        The message payload.
     *
     * @return mixed
     */
    public static function messageReceiveEncrypt($receiving_priv, $sending_pub, $payload)
    {
        # Create a keypair to receive an encrypted message
        $messageKey = sodium_crypto_box_keypair_from_secretkey_and_publickey(
            $receiving_priv,
            $sending_pub
        );

        # Split the payload into it's parts
        $ciphertext = explode('.', $payload);

        # Decrypt the message
        $plaintext = sodium_crypto_box_open(
            self::hex2bin($ciphertext[1]),
            self::hex2bin($ciphertext[0]),
            $messageKey
        );

        # Toss an exception if the decryption failed for any reason
        if ($plaintext === false) {
            throw new DecryptionException('Malformed message or invalid MAC');
        }

        return $plaintext;
    }

    /**
     * Sign a message for authentication.
     *
     * @param string $sign_key A signing private key generated by genSignKeypair()
     * @param string $message  A message to be signed
     *
     * @return mixed
     */
    public static function signMessage($sign_key, $message)
    {
        return self::bin2hex(sodium_crypto_sign(
            $message,
            $sign_key
        ));
    }

    /**
     * Verify signature and read a message.
     *
     * @param string $pub_key The public key from the sender.
     * @param string $message The message from the sender.
     *
     * @return mixed
     */
    public static function verifySignature($pub_key, $message)
    {
        $original_msg = sodium_crypto_sign_open(
            self::hex2bin($message),
            $pub_key
        );
        if ($original_msg === false) {
            throw new SignatureException('Could not verify the signature of the message');
        } else {
            return $original_msg;
        }
    }
}
