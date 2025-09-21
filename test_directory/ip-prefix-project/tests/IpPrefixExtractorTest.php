<?php

use PHPUnit\Framework\TestCase;

class IpPrefixExtractorTest extends TestCase
{
    protected $extractor;

    protected function setUp(): void
    {
        $this->extractor = new IpPrefixExtractor();
    }

    public function testValidIPv4Prefix()
    {
        $ip = '192.168.1.1';
        $expectedPrefix = '192.168';
        $this->assertEquals($expectedPrefix, $this->extractor->getPrefix($ip));
    }

    public function testValidIPv6Prefix()
    {
        $ip = '2001:db8:85a3:0000:0000:8a2e:0370:7334';
        $expectedPrefix = '2001:db8:85a3';
        $this->assertEquals($expectedPrefix, $this->extractor->getPrefix($ip));
    }

    public function testInvalidIP()
    {
        $ip = 'invalid_ip';
        $this->assertEquals('', $this->extractor->getPrefix($ip));
    }

    public function testIPv4MappedIPv6()
    {
        $ip = '::ffff:192.0.2.128';
        $expectedPrefix = '192.0.2';
        $this->assertEquals($expectedPrefix, $this->extractor->getPrefix($ip));
    }

    public function testEmptyIP()
    {
        $this->assertEquals('', $this->extractor->getPrefix(null));
    }
}