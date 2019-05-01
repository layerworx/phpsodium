<?php

use Layerworx\Phpsodium\SodiumLibrary;

class LibraryTest extends PHPUnit_Framework_TestCase
{
    /**
     * @requires                 extension sodium
     * @expectedException        Layerworx\Phpsodium\Exceptions\KeyTypeException
     * @expectedExceptionMessage keyedHash expects a string as the key
     */
    public function testSodiumKeyedHashBadKey()
    {
        SodiumLibrary::keyedHash('foo', 1);
    }

    /**
     * @requires                       extension sodium
     * @expectedException              Layerworx\Phpsodium\Exceptions\HashLengthException
     * @expectedExceptionMessageRegExp #Hash length should be between \d+ and \d+#
     */
    public function testSodiumRawHashLengthBounds()
    {
        SodiumLibrary::rawHash('foo', null, 1);
    }

    /**
     * @requires extension sodium
     */
    public function testSodiumPubPrivMessageEncryption()
    {
        $Adam = SodiumLibrary::genBoxKeypair();
        $Eve = SodiumLibrary::genBoxKeypair();
        $encryptedMessage = SodiumLibrary::messageSendEncrypt($Adam['pub'], $Eve['pri'], 'message');
        $this->assertEquals('message', SodiumLibrary::messageReceiveEncrypt($Adam['pri'], $Eve['pub'], $encryptedMessage));
    }

    /**
     * @requires extension sodium
     */
    public function testSodiumMessageSigning()
    {
        $Adam = SodiumLibrary::genSignKeypair();
        $signedMessage = SodiumLibrary::signMessage($Adam['pri'], 'message');
        $this->assertEquals('message', SodiumLibrary::verifySignature($Adam['pub'], $signedMessage));
    }
}
