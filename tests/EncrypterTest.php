<?php

use Layerworx\Phpsodium\SodiumLibrary;

class EncrypterTest extends PHPUnit_Framework_TestCase
{
    /**
     * @requires extension libsodium
     */
    public function testSodiumEncryption()
    {
        $encrypted = SodiumLibrary::encrypt('foo', str_repeat('a', 16));
        $this->assertNotEquals('foo', $encrypted);
        $this->assertEquals('foo', SodiumLibrary::decrypt($encrypted, str_repeat('a', 16)));
    }

    /**
     * @requires                 extension libsodium
     * @expectedException        Layerworx\Phpsodium\Exceptions\DecryptionException
     * @expectedExceptionMessage The key provided cannot decrypt the message
     */
    public function testSodiumEncryptionFail()
    {
        $encrypted = SodiumLibrary::encrypt('foo', str_repeat('a', 16));
        SodiumLibrary::decrypt($encrypted, str_repeat('b', 16));
    }
}
