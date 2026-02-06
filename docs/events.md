# Events

SqlAgent dispatches events you can listen to for custom behavior.

## SqlErrorOccurred

Dispatched when a SQL query fails.

```php
use Knobik\SqlAgent\Events\SqlErrorOccurred;

class SqlErrorListener
{
    public function handle(SqlErrorOccurred $event): void
    {
        Log::warning('SQL Agent error', [
            'sql' => $event->sql,
            'error' => $event->error,
            'question' => $event->question,
            'connection' => $event->connection,
        ]);
    }
}
```

## LearningCreated

Dispatched when a new learning is created.

```php
use Knobik\SqlAgent\Events\LearningCreated;

class LearningListener
{
    public function handle(LearningCreated $event): void
    {
        // Notify team about new learning
        Notification::send($admins, new NewLearningNotification($event->learning));
    }
}
```

## Registering Listeners

Register listeners in `EventServiceProvider`:

```php
protected $listen = [
    \Knobik\SqlAgent\Events\SqlErrorOccurred::class => [
        \App\Listeners\SqlErrorListener::class,
    ],
    \Knobik\SqlAgent\Events\LearningCreated::class => [
        \App\Listeners\LearningListener::class,
    ],
];
```
