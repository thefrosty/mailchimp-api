<?php

namespace DrewM\MailChimp;

/**
 * A MailChimp Batch operation.
 * http://developer.mailchimp.com/documentation/mailchimp/reference/batches/
 * @author Drew McLellan <drew.mclellan@gmail.com>
 */
class Batch
{
    private array $operations = [];

    public function __construct(private readonly MailChimp $MailChimp, private $batch_id = null)
    {
    }

    /**
     * Add an HTTP DELETE request operation to the batch - for deleting data
     * @param string $id ID for the operation within the batch
     * @param string $method URL of the API request method
     */
    public function delete(string $id, string $method): void
    {
        $this->queueOperation('DELETE', $id, $method);
    }

    /**
     * Add an HTTP GET request operation to the batch - for retrieving data
     * @param string $id ID for the operation within the batch
     * @param string $method URL of the API request method
     * @param array $args Assoc array of arguments (usually your data)
     */
    public function get(string $id, string $method, array $args = []): void
    {
        $this->queueOperation('GET', $id, $method, $args);
    }

    /**
     * Add an HTTP PATCH request operation to the batch - for performing partial updates
     * @param string $id ID for the operation within the batch
     * @param string $method URL of the API request method
     * @param array $args Assoc array of arguments (usually your data)
     */
    public function patch(string $id, string $method, array $args = []): void
    {
        $this->queueOperation('PATCH', $id, $method, $args);
    }

    /**
     * Add an HTTP POST request operation to the batch - for creating and updating items
     * @param string $id ID for the operation within the batch
     * @param string $method URL of the API request method
     * @param array $args Assoc array of arguments (usually your data)
     * @return  void
     */
    public function post(string $id, string $method, array $args = []): void
    {
        $this->queueOperation('POST', $id, $method, $args);
    }

    /**
     * Add an HTTP PUT request operation to the batch - for creating new items
     * @param string $id ID for the operation within the batch
     * @param string $method URL of the API request method
     * @param array $args Assoc array of arguments (usually your data)
     * @return  void
     */
    public function put(string $id, string $method, array $args = []): void
    {
        $this->queueOperation('PUT', $id, $method, $args);
    }

    /**
     * Execute the batch request
     * @param int $timeout Request timeout in seconds (optional)
     * @return array|false   Assoc array of API response, decoded from JSON
     */
    public function execute(int $timeout = 10): false|array
    {
        $req = ['operations' => $this->operations];

        $result = $this->MailChimp->post('batches', $req, $timeout);

        if ($result && isset($result['id'])) {
            $this->batch_id = $result['id'];
        }

        return $result;
    }

    /**
     * Check the status of a batch request. If the current instance of the Batch object
     * was used to make the request, the batch_id is already known and is therefore optional.
     * @param string|null $batch_id ID of the batch about which to enquire
     * @return array|false   Assoc array of API response, decoded from JSON
     */
    public function checkStatus(?string $batch_id = null): false|array
    {
        if ($batch_id === null && $this->batch_id) {
            $batch_id = $this->batch_id;
        }

        return $this->MailChimp->get('batches/' . $batch_id);
    }

    /**
     * Get operations
     * @return array
     */
    public function getOperations(): array
    {
        return $this->operations;
    }

    /**
     * Add an operation to the internal queue.
     * @param string $http_verb GET, POST, PUT, PATCH or DELETE
     * @param string $id ID for the operation within the batch
     * @param string $method URL of the API request method
     * @param array|null $args Assoc array of arguments (usually your data)
     */
    private function queueOperation(string $http_verb, string $id, string $method, ?array $args = null): void
    {
        $operation = [
            'operation_id' => $id,
            'method' => $http_verb,
            'path' => $method,
        ];

        if ($args) {
            if ($http_verb === 'GET') {
                $key = 'params';
                $operation[$key] = $args;
            } else {
                $key = 'body';
                $operation[$key] = json_encode($args);
            }
        }

        $this->operations[] = $operation;
    }
}
