<?php

declare(strict_types=1);

namespace app\queue\redis_slow;

class_alias(\app\queue\redis_cron\CronJobConsumer::class, __NAMESPACE__ . '\\CronJobConsumer');
