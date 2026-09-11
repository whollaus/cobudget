<?php

declare(strict_types=1);

namespace OCP {
	if (!interface_exists(IConfig::class, false)) {
		interface IConfig {
			public function getAppValue(string $appName, string $key, string $default = ''): string;
		}
	}
	if (!class_exists(Server::class, false)) {
		final class Server {
			public static \Closure $resolver;

			public static function get(string $service): mixed {
				return (self::$resolver)($service);
			}
		}
	}
}

namespace OCP\BackgroundJob {
	// Minimal Nextcloud boundaries: queue inspection must not construct or execute jobs.
	if (!interface_exists(IJob::class, false)) {
		interface IJob {}
	}
	if (!interface_exists(IJobList::class, false)) {
		interface IJobList {
			public function has(IJob|string $job, mixed $argument): bool;
			public function add(IJob|string $job, mixed $argument = null): void;
		}
	}
	if (!class_exists(TimedJob::class, false)) {
		abstract class TimedJob implements IJob {
			abstract public function getInterval(): int;
		}
	}
}

namespace CoBudget\Tests {
	use CoBudget\Tests\Support\TestRunner;
	use OCA\CoBudget\Service\BackgroundJobService;
	use OCP\BackgroundJob\IJob;
	use OCP\BackgroundJob\IJobList;
	use OCP\BackgroundJob\TimedJob;
	use OCP\IConfig;
	use OCP\Server;

	require_once dirname(__DIR__, 2) . '/lib/Service/BackgroundJobService.php';

	final class BackgroundJobTestQueue implements IJobList {
		public array $rows = [];
		public array $added = [];
		public ?string $readFailure = null;
		public ?string $writeFailure = null;

		public function has(IJob|string $job, mixed $argument): bool {
			if ($job === $this->readFailure) {
				throw new \RuntimeException('Queue read failed');
			}
			return isset($this->rows[$job][json_encode($argument)]);
		}

		public function add(IJob|string $job, mixed $argument = null): void {
			if ($job === $this->writeFailure) {
				throw new \RuntimeException('Queue registration failed');
			}
			$this->added[] = $job;
			$this->rows[$job][json_encode($argument)] = ['last_run' => 0, 'reserved_at' => 0];
		}
	}

	final class BackgroundJobTestConfig implements IConfig {
		public function __construct(private array $values = []) {}

		public function getAppValue(string $appName, string $key, string $default = ''): string {
			return $this->values[$appName][$key] ?? $default;
		}
	}

	final class BackgroundJobTestTimedJob extends TimedJob {
		public function getInterval(): int { return 300; }
	}

	$classes = array_map('strval', simplexml_load_file(dirname(__DIR__, 2) . '/appinfo/info.xml')->xpath('/info/background-jobs/job'));
	$fixture = static function () use ($classes): array {
		$queue = new BackgroundJobTestQueue();
		foreach ($classes as $class) {
			$queue->rows[$class]['null'] = ['last_run' => 100, 'reserved_at' => 200];
		}
		Server::$resolver = static fn (string $class): TimedJob => new BackgroundJobTestTimedJob();
		return [$queue, new BackgroundJobService($queue, new BackgroundJobTestConfig())];
	};

	return [
		'Background job checks cover the registered app jobs without changing their state' => function (TestRunner $t) use ($fixture, $classes): void {
			[$queue, $service] = $fixture();
			$before = $queue->rows;
			$report = $service->check();
			$t->assertSame($classes, array_column($report['jobs'], 'class'), 'Diagnostics should cover every job declared in app metadata');
			$t->assertTrue($report['healthy'], 'Loadable and registered jobs should pass');
			$t->assertSame($before, $queue->rows, 'Inspection must preserve arguments, last-run times and reservations');
			$t->assertSame([], $queue->added, 'Inspection must not register jobs');
			$t->assertSame('ajax', $report['backgroundMode'], 'The default scheduler mode should be reported separately from job health');
			$t->assertSame(0, $report['lastCron'], 'An instance without recorded cron runs should report zero');
		},

		'Background job checks report missing registrations without repairing implicitly' => function (TestRunner $t) use ($fixture, $classes): void {
			[$queue, $service] = $fixture();
			unset($queue->rows[$classes[0]]);
			$report = $service->check();
			$t->assertFalse($report['healthy'], 'A missing registration should fail the check');
			$t->assertTrue($report['jobs'][0]['loadable'], 'A missing registration is distinct from a loading error');
			$t->assertFalse($report['jobs'][0]['registered'], 'The missing job should be identified');
			$t->assertSame([], $queue->added, 'Default checks must remain read-only');
		},

		'Background job diagnostics retain the underlying class-loading exception' => function (TestRunner $t) use ($fixture, $classes): void {
			[$queue, $service] = $fixture();
			$before = $queue->rows;
			Server::$resolver = static function (string $class) use ($classes): TimedJob {
				if ($class === $classes[0]) {
					throw new \RuntimeException('Could not resolve job', 0, new \ReflectionException('Class was not found'));
				}
				return new BackgroundJobTestTimedJob();
			};
			$report = $service->check();
			$t->assertSame(['RuntimeException: Could not resolve job', 'ReflectionException: Class was not found'], $report['jobs'][0]['errors'], 'Both wrapper and original exceptions must be available without the Nextcloud log viewer');
			$t->assertTrue($report['jobs'][1]['loadable'], 'A failed job must not hide the results of later jobs');
			$t->assertFalse($report['healthy'], 'Class loading errors must fail the check');
			$t->assertSame($before, $queue->rows, 'A broken job must not be removed during inspection');
		},

		'Background job repair validates lazy constructors before registering any missing jobs' => function (TestRunner $t) use ($fixture, $classes): void {
			[$queue, $service] = $fixture();
			$queue->rows = [];
			Server::$resolver = static function (string $class) use ($classes): TimedJob {
				if ($class === $classes[3]) {
					return new class extends TimedJob {
						public function getInterval(): int {
							throw new \RuntimeException('Lazy constructor dependency failed');
						}
					};
				}
				return new BackgroundJobTestTimedJob();
			};
			$report = $service->check(true);
			$t->assertTrue($report['repairBlocked'], 'A lazy initialization failure must block repair');
			$t->assertFalse($report['jobs'][3]['loadable'], 'A lazy service is not loadable until its constructor succeeds');
			$t->assertSame([], $queue->added, 'Every job must pass before the first missing registration is restored');
			$t->assertSame([], $queue->rows, 'Failed preflight must not partially populate the queue');
		},

		'Background job repair restores only missing jobs and is idempotent' => function (TestRunner $t) use ($fixture, $classes): void {
			[$queue, $service] = $fixture();
			unset($queue->rows[$classes[1]], $queue->rows[$classes[2]]);
			$queue->rows['OtherAppJob']['null'] = ['last_run' => 300, 'reserved_at' => 400];
			$before = $queue->rows;
			$report = $service->check(true);
			$t->assertTrue($report['healthy'], 'Restored registrations should pass');
			$t->assertSame([$classes[1], $classes[2]], $queue->added, 'Only missing CoBudget jobs should be registered');
			$t->assertSame([false, true, true, false], array_column($report['jobs'], 'restored'), 'The report should identify exactly what was restored');
			foreach ($before as $class => $rows) {
				$t->assertSame($rows, $queue->rows[$class], 'Existing jobs must keep their timestamps and reservations');
			}
			$after = $queue->rows;
			$secondReport = $service->check(true);
			$t->assertSame($after, $queue->rows, 'Running repair twice must leave existing queue state intact');
			$t->assertSame([$classes[1], $classes[2]], $queue->added, 'A second repair must not re-add or reset jobs');
			$t->assertSame([false, false, false, false], array_column($secondReport['jobs'], 'restored'), 'An idempotent repair should report no new registrations');
		},

		'Background job repair preserves working legacy empty-array registrations' => function (TestRunner $t) use ($fixture, $classes): void {
			[$queue, $service] = $fixture();
			$queue->rows[$classes[0]]['[]'] = $queue->rows[$classes[0]]['null'];
			unset($queue->rows[$classes[0]]['null']);
			$before = $queue->rows;
			$report = $service->check(true);
			$t->assertTrue($report['healthy'], 'Legacy [] jobs can still execute and should count as registered');
			$t->assertSame($before, $queue->rows, 'Repair must not create a duplicate or reset the legacy schedule');
			$t->assertSame([], $queue->added, 'A working legacy registration must not be added again');
		},

		'Background job repair does not treat queue read errors as missing jobs' => function (TestRunner $t) use ($fixture, $classes): void {
			[$queue, $service] = $fixture();
			unset($queue->rows[$classes[0]]);
			$queue->readFailure = $classes[3];
			$report = $service->check(true);
			$t->assertTrue($report['repairBlocked'], 'Queue read errors must block writes');
			$t->assertSame(['RuntimeException: Queue read failed'], $report['jobs'][3]['errors'], 'The database error should be reported directly');
			$t->assertSame([], $queue->added, 'A failed read must not trigger registration');
		},

		'Background job repair reports failed registrations accurately' => function (TestRunner $t) use ($fixture, $classes): void {
			[$queue, $service] = $fixture();
			unset($queue->rows[$classes[0]]);
			$queue->writeFailure = $classes[0];
			$report = $service->check(true);
			$t->assertFalse($report['healthy'], 'Registration failures must fail the result');
			$t->assertFalse($report['jobs'][0]['restored'], 'A failed registration must not be reported as restored');
			$t->assertFalse($report['jobs'][0]['registered'], 'The missing registration should remain visible');
			$t->assertSame(['RuntimeException: Queue registration failed'], $report['jobs'][0]['errors'], 'Repair errors must retain their cause');
		},

		'Background job checks reject a non-job service from the container' => function (TestRunner $t) use ($fixture): void {
			[$queue, $service] = $fixture();
			Server::$resolver = static fn (string $class): object => new \stdClass();
			$report = $service->check(true);
			$t->assertFalse($report['healthy'], 'An invalid container service must fail validation');
			$t->assertTrue($report['repairBlocked'], 'An invalid job type must prevent registration');
			$t->assertSame([], $queue->added, 'Invalid services must not be queued');
		},
	];
}
