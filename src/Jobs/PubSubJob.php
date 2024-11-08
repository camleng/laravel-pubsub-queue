<?php

namespace Kainxspirits\PubSubQueue\Jobs;

use Google\Cloud\PubSub\Message;
use Illuminate\Container\Container;
use Illuminate\Contracts\Queue\Job as JobContract;
use Illuminate\Queue\Jobs\Job;
use Kainxspirits\PubSubQueue\PubSubQueue;

class PubSubJob extends Job implements JobContract
{
    /**
     * The PubSub queue.
     *
     * @var \Kainxspirits\PubSubQueue\PubSubQueue
     */
    protected $pubsub;

    /**
     * The job instance.
     *
     * @var Message
     */
    protected $job;

    /**
     * The decoded payload.
     *
     * @var array
     */
    protected $decoded;

    /**
     * When popping a message off the queue, do not immediately acknowledge the message; rather, only acknowledge the message after the job has finished successfully.
     * If a job has not succeeded, do not release the job back to the queue, but, instead, rely on PubSub to retry the message.
     *
     * @var bool
     */
    protected $usePubsubRetries;

    protected $maxDeliveryAttempts;

    /**
     * Create a new job instance.
     *
     * @param  \Illuminate\Container\Container  $container
     * @param  \Kainxspirits\PubSubQueue\PubSubQueue  $sqs
     * @param  \Google\Cloud\PubSub\Message  $job
     * @param  string  $connectionName
     * @param  string  $queue
     */
    public function __construct(Container $container, PubSubQueue $pubsub, Message $job, $connectionName, $queue, $maxDeliveryAttempts, $usePubsubRetries = false)
    {
        $this->pubsub = $pubsub;
        $this->job = $job;
        $this->queue = $queue;
        $this->container = $container;
        $this->connectionName = $connectionName;
        $this->maxDeliveryAttempts = $maxDeliveryAttempts;
        $this->usePubsubRetries = $usePubsubRetries;

        $this->decoded = $this->payload();
    }

    /**
     * Get the job identifier.
     *
     * @return string
     */
    public function getJobId()
    {
        return $this->decoded['id'] ?? null;
    }

    /**
     * Get the raw body of the job.
     *
     * @return string
     */
    public function getRawBody()
    {
        return base64_decode($this->job->data());
    }

    /**
     * Get the number of times the job has been attempted.
     *
     * @return int
     */
    public function attempts()
    {
        if ($this->usePubsubRetries) {
            return $this->job->deliveryAttempt();
        }
        return ((int) $this->job->attribute('attempts') ?? 0) + 1;
    }

    public function maxTries()
    {
        return $this->maxDeliveryAttempts ?? parent::maxTries();
    }

    /**
     * Release the job back into the queue.
     *
     * @param  int  $delay
     * @return void
     */
    public function release($delay = 0)
    {
        if ($this->usePubsubRetries) {
            return;
        }

        parent::release($delay);

        $attempts = $this->attempts();
        $this->pubsub->republish(
            $this->job,
            $this->queue,
            ['attempts' => (string) $attempts],
            $delay
        );
    }
}
