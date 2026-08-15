<?php

namespace DrewM\MailChimp\Tests\unit;

use DrewM\MailChimp\Webhook;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

#[CoversClass(Webhook::class)]
class WebhookTest extends TestCase
{
    /**
     * Reset static state before each test to ensure test isolation.
     */
    protected function setUp(): void
    {
        $subscriptionsProp = new ReflectionProperty(Webhook::class, 'eventSubscriptions');
        $subscriptionsProp->setValue(null, []);
        $receivedProp = new ReflectionProperty(Webhook::class, 'receivedWebhook');
        $receivedProp->setValue(null, null);
    }

    /**
     * Test that subscribing to events registers callbacks in the static eventSubscriptions array.
     */
    public function testSubscribeRegistersCallbacks(): void
    {
        $called = false;
        Webhook::subscribe('unsubscribe', static function () use (&$called) {
            $called = true;
        });

        // Verify the callback was registered by triggering a matching webhook
        $input = 'type=unsubscribe&email=test@example.com';
        $result = Webhook::receive($input);

        $this->assertTrue($called);
        $this->assertIsArray($result);
        $this->assertSame('unsubscribe', $result['type']);
    }

    /**
     * Test subscribing to multiple different event types.
     */
    public function testSubscribeMultipleEventTypes(): void
    {
        $subscribeCalled = false;
        $unsubscribeCalled = false;

        Webhook::subscribe('subscribe', static function () use (&$subscribeCalled) {
            $subscribeCalled = true;
        });
        Webhook::subscribe('unsubscribe', static function () use (&$unsubscribeCalled) {
            $unsubscribeCalled = true;
        });

        // Trigger a subscribe event
        $result = Webhook::receive('type=subscribe&email=test@example.com');
        $this->assertTrue($subscribeCalled);
        $this->assertFalse($unsubscribeCalled);

        // Trigger an unsubscribe event
        $subscribeCalled = false;
        $result = Webhook::receive('type=unsubscribe&email=test@example.com');
        $this->assertTrue($unsubscribeCalled);
        $this->assertFalse($subscribeCalled);
    }

    /**
     * Test that receive() with an empty string returns false.
     */
    public function testReceiveEmpty(): void
    {
        $result = Webhook::receive('');

        $this->assertFalse($result);
    }

    /**
     * Test that receive(null) without php://input returns false.
     */
    public function testReceiveNull(): void
    {
        $result = Webhook::receive(null);

        $this->assertFalse($result);
    }

    /**
     * Test that receive() with input missing the 'type' key returns false.
     */
    public function testReceiveNoType(): void
    {
        // Input has data but no 'type' key
        $result = Webhook::receive('email=test@example.com&fields=email');

        $this->assertFalse($result);
    }

    /**
     * Test that a valid webhook dispatches the correct data to callbacks.
     */
    public function testReceiveDispatchesCorrectData(): void
    {
        $capturedData = null;

        Webhook::subscribe('subscribe', static function ($data) use (&$capturedData) {
            $capturedData = $data;
        });

        $input = 'type=subscribe&email=new@example.com&fname=Test+User';
        $result = Webhook::receive($input);

        $this->assertIsArray($result);
        $this->assertSame('subscribe', $result['type']);
        $this->assertSame('new@example.com', $result['email']);
        $this->assertSame('Test User', $result['fname']);
        $this->assertIsArray($capturedData);
        $this->assertSame('new@example.com', $capturedData['email']);
        $this->assertSame('Test User', $capturedData['fname']);
    }

    /**
     * Test that callbacks receive the data from the parsed POST string.
     */
    public function testCallbackReceivesParsedFormData(): void
    {
        $receivedFields = [];

        Webhook::subscribe('campaign', static function ($data) use (&$receivedFields) {
            $receivedFields = $data;
        });

        $input = 'type=campaign&uuid=abc-123&mailing_id=42&listed_before=1&unsubscribed=&unsubscribed_reason=&confirmed=true';
        $result = Webhook::receive($input);

        $this->assertIsArray($result);
        $this->assertSame('abc-123', $receivedFields['uuid']);
        $this->assertSame('42', $receivedFields['mailing_id']);
        $this->assertSame('true', $receivedFields['confirmed']);
    }

    /**
     * Test that multiple callbacks for the same event all get called.
     */
    public function testMultipleCallbacksForSameEvent(): void
    {
        $callOrder = [];

        Webhook::subscribe('unsubscribe', static function () use (&$callOrder) {
            $callOrder[] = 'first';
        });
        Webhook::subscribe('unsubscribe', static function () use (&$callOrder) {
            $callOrder[] = 'second';
        });

        $result = Webhook::receive('type=unsubscribe&email=test@example.com');

        $this->assertIsArray($result);
        $this->assertSame(['first', 'second'], $callOrder);
    }

    /**
     * Test that subscriptions are reset after dispatch (webhook events are one-time).
     */
    public function testSubscriptionsResetAfterDispatch(): void
    {
        $callCount = 0;

        Webhook::subscribe('subscribe', static function () use (&$callCount) {
            $callCount++;
        });

        // First call — callback should fire
        Webhook::receive('type=subscribe&email=test@example.com');
        $this->assertSame(1, $callCount);

        // Second call — subscriptions for 'subscribe' were reset, so callback should NOT fire
        Webhook::receive('type=subscribe&email=test2@example.com');
        $this->assertSame(1, $callCount);

        // Subscribe again and call once more
        Webhook::subscribe('subscribe', static function () use (&$callCount) {
            $callCount++;
        });
        Webhook::receive('type=subscribe&email=test3@example.com');
        $this->assertSame(2, $callCount);
    }

    /**
     * Test that receive() with no matching subscriptions returns the parsed result without error.
     */
    public function testReceiveNoSubscribedCallback(): void
    {
        // No callbacks registered for 'subscribe'
        $result = Webhook::receive('type=subscribe&email=test@example.com');

        $this->assertIsArray($result);
        $this->assertSame('subscribe', $result['type']);
    }
}
