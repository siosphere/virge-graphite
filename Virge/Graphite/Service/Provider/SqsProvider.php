<?php
namespace Virge\Graphite\Service\Provider;

use Aws\Sqs\SqsClient;
use Aws\Exception\AwsException;
use Virge\Cli;
use Virge\Core\Config;
use Virge\Graphite\Component\Task;

/**
 * 
 * @author Michael Kramer
 */
class SqsProvider extends AbstractProvider 
{
    
    protected $client;

    /**
     * @param string $queue
     * @param Task $task
     */
    public function push($queueName, Task $task) : bool
    {
        $serializedTask = serialize($task);

        $params = [
            'MessageBody' => $serializedTask,
            'QueueUrl' => $this->getQueueUrl($queueName),
        ];

        try {
            $result = $this->getClient()->sendMessage($params);
            return true;
        } catch (AwsException $e) {
            error_log($e->getMessage());
            return false;
        }
    }

    /**
     * Get a task from the queue and dispatch it
     * @param type $queue
     */
    public function listen($queueName, $callback)
    {
        try {
            $result = $client->setQueueAttributes(array(
                'Attributes' => [
                    'ReceiveMessageWaitTimeSeconds' => 20
                ],
                'QueueUrl' => $this->getQueueUrl($queueName),
            ));

            foreach($result->get('Messages') as $message) {
                try {
                    $task = unserialize($message['Body']);
                    call_user_func($callback, $task);
                    $this->ack($message['ReceiptHandle']);
                } catch(\Throwable $err) {
                    Cli::output($err->getMessage());
                }
            }

            $this->listen($queueName, $callback);
        } catch(AwsException $ex) {
            Cli::error($ex->getMessage());
        }
    }

    protected function ack($queueName, $receiptHandle)
    {
        return $this->getClient()->deleteMessage([
            'QueueUrl' => $this->getQueueUrl($queueName),
            'ReceiptHandle' => $result->get('Messages')[0]['ReceiptHandle']
        ]);
    }

    protected function getClient() : SqsClient
    {
        if($this->client) {
            return $this->client;
        }

        return $this->client = new SqsClient([
            'profile' => Config::get('queue', 'aws.profile') ?? 'default',
            'region' => Config::get('queue', 'aws.region') ?? 'us-east-1',
            'version' => Config::get('queue', 'aws.version') ?? '2012-11-05',
            'credentials' => [
                'key'    => Config::get('queue', 'aws.key'),
                'secret' => Config::get('queue', 'aws.secret'),
            ],
        ]);
    }

    protected function getQueueUrl(string $queueName) : string
    {
        return 'https://sqs.'.Config::get('queue', 'aws.region').'.amazonaws.com/'.Config::get('queue', 'aws.account_id').'/' . $queueName;
    }
}