<?php
/**
 * Cipher class
 *
 * @author     Takuya Motoshima <https://www.facebook.com/takuya.motoshima.7>
 * @license    MIT License
 * @copyright  2017 Takuya Motoshima
 */
namespace X\Util;
use \X\Util\Loader;
use \X\Util\Logger;

final class Cipher {

  /**
   * 
   * Encode SHA-256
   *
   * @param  string $plaintext
   * @param  string $key
   * @return string
   */
  public static function encode_sha256(string $plaintext, string $key = null): string {
    if (empty($key))
      $key = Loader::config('config', 'encryption_key');
    if (empty($key))
      throw new \RuntimeException('Cant find encryption_key in application/config/config.php file');
    return hash('sha256', $plaintext . $key);
  }

  /**
   * Generate initial vector.
   * 
   * @param string $method
   * @return string
   */
  public static function generateInitialVector(string $method = 'AES-256-CTR'): string {
    $length = openssl_cipher_iv_length($method);
    return openssl_random_pseudo_bytes($length);
  }

  /**
   * Encrypt.
   *
   * ```php
   * use \X\Util\Cipher;
   *
   * // Get the initialization vector. This should be changed every time to make it difficult to predict.
   * $iv = Cipher::generateInitialVector();
   *
   * // Plaintext.
   * $plaintext = 'Hello, World.';
   *
   * // Encrypted key.
   * $key = 'key';
   *
   * // Encrypt.
   * $encrypted = Cipher::encrypt($plaintext, $key, $iv);// UHLY5PckT7Da02e42g==
   *
   * // Decrypt.
   * $decrypted = Cipher::decrypt($encrypted, $key, $iv);// Hello, World.
   * ```
   *
   * @param  string $plaintext
   * @param  string $key
   * @param  string $iv
   * @param  string $method
   * @return string
   */
  public static function encrypt(string $plaintext, string $key, string $iv, string $method = 'AES-256-CTR'): string {
    $options = 0;
    return openssl_encrypt($plaintext, $method, $key, $options, $iv);
  }

  /**
   * Decrypt.
   *
   * ```php
   * use \X\Util\Cipher;
   *
   * // Get the initialization vector. This should be changed every time to make it difficult to predict.
   * $iv = Cipher::generateInitialVector();
   *
   * // Plaintext.
   * $plaintext = 'Hello, World.';
   *
   * // Encrypted key.
   * $key = 'key';
   *
   * // Encrypt.
   * $encrypted = Cipher::encrypt($plaintext, $key, $iv);// UHLY5PckT7Da02e42g==
   *
   * // Decrypt.
   * $decrypted = Cipher::decrypt($encrypted, $key, $iv);// Hello, World.
   * ```
   *
   * @param  string $encrypted
   * @param  string $key
   * @param  string $iv
   * @param  string $method
   * @return string
   */
  public static function decrypt(string $encrypted, string $key, string $iv, string $method = 'AES-256-CTR'): string {
    $options = 0;
    return openssl_decrypt($encrypted, $method, $key, $options, $iv);
  }

  /**
   * Generate a random key.
   * 
   * @param  int $length
   * @return string
   */
  public static function generateKey(int $length = 32): string {
    if ($length < 1) throw new RuntimeException('Key length must be 1 or more');
    return base64_encode(random_bytes($length));
  }

  /**
   * Generate key pair.
   *
   * ```php
   * use \X\Util\Cipher;
   * 
   * // Generate 4096bit long RSA key pair.
   * Cipher::generateKeyPair($privKey, $pubKey, [
   *   'digest_alg' => 'sha512',
   *   'private_key_bits' => 4096,
   *   'private_key_type' => OPENSSL_KEYTYPE_RSA
   * ]);
   *
   * // Debug private key.
   * echo $privKey;
   *
   * // Debug public key.
   * echo $pubKey;
   *
   * // OpenSSH encode the public key.
   * $pubKey = Cipher::encodeOpenSshPublicKey($privKey);
   * 
   * // Debug OpenSSH-encoded public key.
   * echo $pubKey;
   * ```
   * 
   * @param  string &$privKey                    The generated private key is set.
   * @param  string &$pubKey                     The generated public key is set.
   * @param  string $options[digest_alg]         Digest method or signature hash, usually one of openssl_get_md_methods().
   *                                             The default value is "sha512".
   * @param  string $options[x509_extensions]    Selects which extensions should be used when creating an x509 certificate.
   *                                             The default value is none.
   * @param  string $options[req_extensions]     Selects which extensions should be used when creating a CSR.
   *                                             The default value is none.
   * @param  int    $options[private_key_bits]   Specifies how many bits should be used to generate a private key.
   *                                             The default value is 4096.
   * @param  int    $options[private_key_type]   Specifies the type of private key to create. This can be one of OPENSSL_KEYTYPE_DSA, OPENSSL_KEYTYPE_DH, OPENSSL_KEYTYPE_RSA or OPENSSL_KEYTYPE_EC.
   *                                             The default value is OPENSSL_KEYTYPE_RSA.
   * @param  bool   $options[encrypt_key]        Should an exported key (with passphrase) be encrypted?
   * @param  int    $options[encrypt_key_cipher] One of cipher constants.
   *                                             The default value is none.
   * @param  string $options[curve_name]         One of openssl_get_curve_names().
   *                                             The default value is none.
   * @param  string $options[config]             Path to your own alternative openssl.conf file.
   *                                             The default value is none.
   */
  public static function generateKeyPair(&$privKey, &$pubKey, array $options = []) {
    // Init options.
    $options = array_merge([
      'digest_alg' => 'sha512',
      'private_key_bits' => 4096,
      'private_key_type' => OPENSSL_KEYTYPE_RSA
    ], $options);

    // Create the private and public key
    $privKeyResource = openssl_pkey_new($options);

    // Extract the private key from $res to $privKey
    openssl_pkey_export($privKeyResource, $privKey);

    // Extract the public key from $res to $pubKey
    $pubKey = openssl_pkey_get_details($privKeyResource)['key'];
  }

  /**
   * OpenSSH encoding the public meeting.
   * 
   * @param  string $privKey Public key content
   * @return string          SSH-encoded public key
   */
  public static function encodeOpenSshPublicKey(string $privKey): string {
    $privKeyResource = openssl_pkey_get_private($privKey);
    $keyInfo = openssl_pkey_get_details($privKeyResource);
    $buffer  = pack('N', 7) . 'ssh-rsa' . self::encodeOpenSshBuffer($keyInfo['rsa']['e']) . self::encodeOpenSshBuffer($keyInfo['rsa']['n']);
    return 'ssh-rsa ' . base64_encode($buffer);
  }

  /**
   * OpenSSH encode the buffer.
   * 
   * @param  string $buffer buffer
   * @return string         SSH encoded buffer
   */
  private static function encodeOpenSshBuffer(string $buffer): string {
    $len = strlen($buffer);
    if (ord($buffer[0]) & 0x80) {
      $len++;
      $buffer = "\x00" . $buffer;
    }
    return pack('Na*', $len, $buffer);
  }
}