<?php

namespace DrewM\MailChimp\Tests;

use DrewM\MailChimp\MailChimp;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class MailChimpTest extends TestCase
{
    /**
     * @throws RuntimeException
     */
    public function testInvalidAPIKey(): void
    {
        $this->expectException('RuntimeException');
        new MailChimp('abc');
    }

    /**
     */
    public function testInstantiation(): void
    {
        $MC_API_KEY = getenv('MC_API_KEY');

        $MailChimp = new MailChimp($MC_API_KEY, 'https://api.mailchimp.com/3.0');

        $this->assertSame('https://api.mailchimp.com/3.0', $MailChimp->getApiEndpoint());

        $this->assertFalse($MailChimp->success());

        $this->assertFalse($MailChimp->getLastError());

        $this->assertSame(['headers' => null, 'body' => null], $MailChimp->getLastResponse());

        $this->assertSame([], $MailChimp->getLastRequest());
    }

    /**
     */
    public function testSubscriberHash(): void
    {
        $email = 'Foo@Example.Com';
        $expected = md5(strtolower($email));
        $result = MailChimp::subscriberHash($email);

        $this->assertEquals($expected, $result);
    }

    public function testResponseState(): void
    {
        $MC_API_KEY = getenv('MC_API_KEY');

        $MailChimp = new MailChimp($MC_API_KEY);

        $MailChimp->get('lists');

        // Since we're using a fake key, it doesn't work
        $this->assertFalse($MailChimp->success());

        // But now we have an error message
        $this->assertSame(
            'Unknown error, call `getLastResponse()` to find out what happened.',
            $MailChimp->getLastError()
        );
    }
}
