<?php

use Layerworx\Phpsodium\SodiumLibrary;

class HasherTest extends PHPUnit_Framework_TestCase
{
    /**
     * @requires extension sodium
     */
    public function testBasicHashing()
    {
        $value = SodiumLibrary::hashPassword('password');
        $this->assertNotSame('password', $value);
        $this->assertTrue(SodiumLibrary::checkPassword('password', $value));
    }

    /**
     * @requires extension sodium
     */
    public function testSlowHashing()
    {
        $value = SodiumLibrary::hashPassword('password', true);
        $this->assertNotSame('password', $value);
        $this->assertTrue(SodiumLibrary::checkPassword('password', $value));
    }
}
