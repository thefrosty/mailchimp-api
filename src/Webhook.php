<?php

namespace DrewM\MailChimp;

/**
 * A MailChimp Webhook request.
 * How to Set Up Webhooks: http://eepurl.com/bs-j_T
 * @author Drew McLellan <drew.mclellan@gmail.com>
 */
class Webhook
{
    private static array $eventSubscriptions = [];
    private static ?string $receivedWebhook = null;

    /**
     * Subscribe to an incoming webhook request. The callback will be invoked when a matching webhook is received.
     * @param string $event Name of the webhook event, e.g. subscribe, unsubscribe, campaign
     * @param callable $callback A callable function to invoke with the data from the received webhook
     * @return void
     */
    public static function subscribe(string $event, callable $callback): void
    {
        if (!isset(self::$eventSubscriptions[$event])) {
            self::$eventSubscriptions[$event] = [];
        }
        self::$eventSubscriptions[$event][] = $callback;

        self::receive();
    }

    /**
     * Retrieve the incoming webhook request as sent.
     * @param string|null $input An optional raw POST body to use instead of php://input - mainly for unit testing.
     * @return array|false    An associative array containing the details of the received webhook
     */
    public static function receive(?string $input = null): false|array
    {
        if (is_null($input)) {
            $input = self::$receivedWebhook ?? file_get_contents("php://input");
        }

        if (!is_null($input) && $input !== '') {
            return self::processWebhook($input);
        }

        return false;
    }

    /**
     * Process the raw request into a PHP array and dispatch any matching subscription callbacks
     * @param string $input The raw HTTP POST request
     * @return array|false    An associative array containing the details of the received webhook
     */
    private static function processWebhook(string $input): false|array
    {
        self::$receivedWebhook = $input;
        parse_str($input, $result);
        if ($result && isset($result['type'])) {
            self::dispatchWebhookEvent($result['type'], $result['data']);
            return $result;
        }

        return false;
    }

    /**
     * Call any subscribed callbacks for this event
     * @param string $event The name of the callback event
     * @param array $data An associative array of the webhook data
     * @return void
     */
    private static function dispatchWebhookEvent(string $event, array $data): void
    {
        if (isset(self::$eventSubscriptions[$event])) {
            foreach (self::$eventSubscriptions[$event] as $callback) {
                $callback($data);
            }
            // Reset subscriptions.
            self::$eventSubscriptions[$event] = [];
        }
    }
}
