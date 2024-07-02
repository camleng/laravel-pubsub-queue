<?php

namespace Kainxspirits\PubSubQueue;

use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\ServiceProvider;
use Kainxspirits\PubSubQueue\Connectors\PubSubConnector;
use ReflectionClass;

class PubSubQueueServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->app['queue']->addConnector('pubsub', function () {
            return new PubSubConnector;
        });

        Queue::after(function(JobProcessed $event) {
            [$pubsubQueue, $job, $queue] = $this->getProperties($event->job, ['pubsub', 'job', 'queue']);
            Log::info("Acknowledging message " . $event->job->getJobId() . ' on queue ' . $queue);
            $pubsubQueue->acknowledge($job, $queue);
        });
    }

    private function getProperties($job, array $props): array
    {
        $reflection = new ReflectionClass(get_class($job));

        $properties = [];
        foreach ($props as $prop) {
            if (!$reflection->hasProperty($prop)) {
                $properties[] = null;
            }
            $property = $reflection->getProperty($prop);
            $property->setAccessible('true');
            $properties[] = $property->getValue($job);
        }
        return $properties;
    }
}
