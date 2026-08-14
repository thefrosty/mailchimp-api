<?php

namespace DrewM\MailChimp\Tests;

use DrewM\MailChimp\Batch;
use DrewM\MailChimp\MailChimp;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;

#[CoversClass(Batch::class)]
#[CoversClass(MailChimp::class)]
class BatchTest extends TestCase
{

    public function testNewBatch(): void
    {
        $MC_API_KEY = getenv('MC_API_KEY');

        $MailChimp = new MailChimp($MC_API_KEY);
        $Batch = $MailChimp->newBatch('1');
        $reflection = new ReflectionClass($Batch);

        $this->assertInstanceOf(MailChimp::class, $reflection->getProperty('MailChimp')->getValue($Batch));
        $this->assertSame([], $Batch->getOperations());
    }

    /**
     * Test queuing all HTTP verb types and verifying the operation structure.
     */
    public function testQueuedOperations(): void
    {
        $fakeMailChimp = $this->createStub(MailChimp::class);
        $Batch = new Batch($fakeMailChimp);

        // Queue one of each HTTP verb type
        $Batch->get('op-get', 'lists', ['fields' => 'id,name']);
        $Batch->post('op-post', 'lists/list-id/members', ['email_address' => 'test@example.com', 'status' => 'subscribed']);
        $Batch->put('op-put', 'lists/list-id/members/hash', ['email_address' => 'test2@example.com', 'status' => ' unsubscribed']);
        $Batch->patch('op-patch', 'lists/list-id/members/hash', ['merge_fields' => ['FNAME' => 'Test']]);
        $Batch->delete('op-delete', 'lists/list-id/members/hash');

        $operations = $Batch->getOperations();
        $this->assertCount(5, $operations);

        // Verify GET operation uses 'params' key
        $getOp = $operations[0];
        $this->assertSame('op-get', $getOp['operation_id']);
        $this->assertSame('GET', $getOp['method']);
        $this->assertSame('lists', $getOp['path']);
        $this->assertArrayHasKey('params', $getOp);
        $this->assertSame(['fields' => 'id,name'], $getOp['params']);
        $this->assertArrayNotHasKey('body', $getOp);

        // Verify POST operation uses 'body' key with json-encoded value
        $postOp = $operations[1];
        $this->assertSame('op-post', $postOp['operation_id']);
        $this->assertSame('POST', $postOp['method']);
        $this->assertSame('lists/list-id/members', $postOp['path']);
        $this->assertArrayHasKey('body', $postOp);
        $this->assertArrayNotHasKey('params', $postOp);
        $this->assertSame(
            json_encode(['email_address' => 'test@example.com', 'status' => 'subscribed']),
            $postOp['body']
        );

        // Verify PUT operation
        $putOp = $operations[2];
        $this->assertSame('op-put', $putOp['operation_id']);
        $this->assertSame('PUT', $putOp['method']);
        $this->assertArrayHasKey('body', $putOp);

        // Verify PATCH operation
        $patchOp = $operations[3];
        $this->assertSame('op-patch', $patchOp['operation_id']);
        $this->assertSame('PATCH', $patchOp['method']);
        $this->assertArrayHasKey('body', $patchOp);

        // Verify DELETE operation (no body/params since DELETE takes no args)
        $deleteOp = $operations[4];
        $this->assertSame('op-delete', $deleteOp['operation_id']);
        $this->assertSame('DELETE', $deleteOp['method']);
        $this->assertArrayNotHasKey('body', $deleteOp);
        $this->assertArrayNotHasKey('params', $deleteOp);
    }

    /**
     * Test queuing operations without arguments.
     */
    public function testQueuedOperationWithoutArgs(): void
    {
        $fakeMailChimp = $this->createStub(MailChimp::class);
        $Batch = new Batch($fakeMailChimp);

        $Batch->get('op-1', 'lists');
        $Batch->post('op-2', 'lists/list-id/members');
        $Batch->put('op-3', 'lists/list-id/members/hash');
        $Batch->patch('op-4', 'lists/list-id/members/hash');
        $Batch->delete('op-5', 'lists/list-id/members/hash');

        $operations = $Batch->getOperations();

        foreach ($operations as $op) {
            $this->assertArrayHasKey('operation_id', $op);
            $this->assertArrayHasKey('method', $op);
            $this->assertArrayHasKey('path', $op);
            $this->assertArrayNotHasKey('body', $op);
            $this->assertArrayNotHasKey('params', $op);
        }
    }

    /**
     * Test that execute() dispatches to the parent MailChimp's post() with the correct payload,
     * and that the batch_id is set from the response.
     */
    public function testExecute(): void
    {
        $fakeMailChimp = $this->createStub(MailChimp::class);
        $fakeMailChimp->method('post')
            ->willReturnOnConsecutiveCalls(
                ['id' => 'batch-123', 'status' => 'queued']
            );

        $Batch = new Batch($fakeMailChimp);
        $Batch->post('op-1', 'lists/list-id/members', ['email_address' => 'test@example.com', 'status' => 'subscribed']);

        $result = $Batch->execute();
        $this->assertSame(['id' => 'batch-123', 'status' => 'queued'], $result);

        // Verify batch_id was set on the Batch
        $reflection = new ReflectionClass($Batch);
        $batchIdProp = $reflection->getProperty('batch_id');
        $this->assertSame('batch-123', $batchIdProp->getValue($Batch));
    }

    /**
     * Test execute with a response that lacks 'id' — batch_id should remain null.
     */
    public function testExecuteWithoutIdInResponse(): void
    {
        $fakeMailChimp = $this->createStub(MailChimp::class);
        $fakeMailChimp->method('post')
            ->willReturnOnConsecutiveCalls(
                ['status' => 'failed', 'message' => 'error']
            );

        $Batch = new Batch($fakeMailChimp);

        $result = $Batch->execute();
        $this->assertSame(['status' => 'failed', 'message' => 'error'], $result);

        // batch_id should still be null
        $reflection = new ReflectionClass($Batch);
        $batchIdProp = $reflection->getProperty('batch_id');
        $this->assertNull($batchIdProp->getValue($Batch));
    }

    /**
     * Test checkStatus with an explicit batch_id argument.
     */
    public function testCheckStatusWithExplicitId(): void
    {
        $fakeMailChimp = $this->createStub(MailChimp::class);
        $fakeMailChimp->method('get')
            ->willReturnOnConsecutiveCalls(
                ['id' => 'batch-456', 'status' => 'finished', 'result_url' => 'https://example.com/result.json']
            );

        $Batch = new Batch($fakeMailChimp, null);

        $result = $Batch->checkStatus('batch-456');
        $this->assertSame(['id' => 'batch-456', 'status' => 'finished', 'result_url' => 'https://example.com/result.json'], $result);

        // Note: checkStatus does NOT update batch_id — it only reads it.
        // So batch_id should remain null when passed as argument.
        $reflection = new ReflectionClass($Batch);
        $batchIdProp = $reflection->getProperty('batch_id');
        $this->assertNull($batchIdProp->getValue($Batch));
    }

    /**
     * Test checkStatus that uses the batch_id from the constructor.
     */
    public function testCheckStatusUsesStoredBatchId(): void
    {
        $fakeMailChimp = $this->createStub(MailChimp::class);
        $fakeMailChimp->method('get')
            ->willReturnOnConsecutiveCalls(
                ['id' => 'stored-789', 'status' => 'expired']
            );

        $Batch = new Batch($fakeMailChimp, 'stored-789');

        // Calling checkStatus without argument should use the stored batch_id
        $result = $Batch->checkStatus();
        $this->assertSame(['id' => 'stored-789', 'status' => 'expired'], $result);
    }
}
