<?php

namespace App\Commands;

use Generator;
use Illuminate\Console\Scheduling\Schedule;
use LaravelZero\Framework\Commands\Command;
use Spatie\Fork\Fork;

class LoadCommand extends Command
{
	/**
	 * The signature of the command.
	 */
	protected $signature = 'load {URL} ' .
	'{--r|requests=100 : Number of requests to send}' .
	'{--c|concurrency=10 : Number of parallel processes}' .
	'{--t|timeout=2.0 : Timeout of HTTP request in seconds}' .
	'{--b|body= : HTTP body to send in every request}' .
	'{--histogram=20 : Timing histogram bars count (0 to disable)}';

	/**
	 * The description of the command.
	 */
	protected $description = 'Load an URL with HTTP requests';

	private string $url = '';
	private int $requests = 100;
	private int $concurrency = 10;
	private float $timeout = 2;
	private bool $progress = true;
	private string $body = '';
	private int $bars = 20;

	/**
	 * Execute the console command.
	 */
	public function handle(): void
	{
		$timeStart = microtime(true);

		$this->url = $this->argument('URL');

		$this->requests = (int)($this->option('requests') ?: 100);
		if ($this->requests < 1) {
			$this->requests = 100;
		}
		$this->concurrency = (int)($this->option('concurrency') ?: 10);
		if ($this->concurrency < 1) {
			$this->concurrency = 1;
		}
		$this->timeout = (float)($this->option('timeout') ?: 2.0);
		if ($this->timeout < 0.001) {
			$this->timeout = 2.0;
		}
		$this->body = (string)($this->option('body') ?: '');
		$this->bars = (int)($this->option('histogram') ?: 20);
		if ($this->bars < 0) {
			$this->bars = 0;
		}

		//
		$this->info(
			'Running ' . $this->requests .
			' requests with concurrency of ' . $this->concurrency .
			' to URL: ' . $this->url . ' ...'
		);

		$fork = Fork::new()->concurrent($this->concurrency);

		$results = [];
		$chunkSize = 0;
		$chunk = [];
		foreach ($this->tasksGenerator($this->requests) as $index => $task) {
			$chunkSize++;
			$chunk[] = $task;
			if ($chunkSize >= $this->concurrency * 2) {
				array_push($results, ...$fork->run(...$chunk));
				$chunk = [];
				$chunkSize = 0;

				if ($this->progress) {
					$progressLength = 100;
					$progress = (int)round($progressLength * $index / $this->requests);
					$pad = str_repeat(' ', $progressLength - $progress);
					echo "\r", '[', str_repeat('#', $progress), $pad, ']';
				}
			}
		}

		if ($chunkSize > 0) {
			// Process the last chunk with the remaining requests
			array_push($results, ...$fork->run(...$chunk));
			if ($this->progress) {
				$progressLength = 100;
				$progress = $progressLength;
				$pad = str_repeat(' ', $progressLength - $progress);
				echo "\r", '[', str_repeat('#', $progress), $pad, ']';
			}
		}

		$statuses2xx = 0;
		$statusesNon2xx = 0;
		$timeouts = 0;
		$bytesUploaded = 0;
		$bytesDownloaded = 0;
		$timesTtfb = [];
		$timesTotal = [];
		$httpCodes = [];
		foreach ($results as $result) {
			$timeout = $result['timeout'];
			$timeouts += $timeout;
			$bytesUploaded += $result['bytes_uploaded'];
			$bytesDownloaded += $result['bytes_downloaded'];

			if (!$timeout && $result['code'] >= 200 && $result['code'] <= 299) {
				$statuses2xx++;
			} else {
				$statusesNon2xx++;
			}

			if (!$timeout) {
				$timesTtfb[] = $result['ttfb'];
				$timesTotal[] = $result['time'];
				$httpCodes[$result['code']] = ($httpCodes[$result['code']] ?? 0) + 1;
			} else {
				$httpCodes[0] = ($httpCodes[0] ?? 0) + 1;
			}
		}

		echo PHP_EOL;

		$count = count($timesTotal);
		if ($count === 0) {
			// All requests failed by timeout
			echo 'Requests sent:  ', count($results), PHP_EOL;
			echo '  Concurrency:  ', $this->concurrency, PHP_EOL;
			echo '     Timeouts:  ', $timeouts, PHP_EOL;
			$this->error('All requests failed by timeout of ' . self::formatSeconds($this->timeout, false));
			return;
		}

		$ttfbTimeStats = self::calcStats($timesTtfb);
		$totalTimeStats = self::calcStats($timesTotal);
		$timeTable = [
			['', 'TTFB', 'Total'],
			[
				'        minimum:',
				self::formatSeconds($ttfbTimeStats['min']),
				self::formatSeconds($totalTimeStats['min']),
				'(fastest response)',
			],
			[
				'  25 percentile:',
				self::formatSeconds($ttfbTimeStats['percentiles'][25]),
				self::formatSeconds($totalTimeStats['percentiles'][25]),
				'(first quartile)',
			],
			[
				'  50 percentile:',
				self::formatSeconds($ttfbTimeStats['percentiles'][50]),
				self::formatSeconds($totalTimeStats['percentiles'][50]),
				'(median)'
			],
			[
				'  75 percentile:',
				self::formatSeconds($ttfbTimeStats['percentiles'][75]),
				self::formatSeconds($totalTimeStats['percentiles'][75]),
				'(third quartile)',
			],
			[
				'  90 percentile:',
				self::formatSeconds($ttfbTimeStats['percentiles'][90]),
				self::formatSeconds($totalTimeStats['percentiles'][90]),
			],
			[
				'  95 percentile:',
				self::formatSeconds($ttfbTimeStats['percentiles'][95]),
				self::formatSeconds($totalTimeStats['percentiles'][95]),
			],
			[
				'  99 percentile:',
				self::formatSeconds($ttfbTimeStats['percentiles'][99]),
				self::formatSeconds($totalTimeStats['percentiles'][99]),
			],
			[
				'        maximum:',
				self::formatSeconds($ttfbTimeStats['max']),
				self::formatSeconds($totalTimeStats['max']),
				'(slowest response)',
			],
		];

		$avgTime = $totalTimeStats['average'];
		$stdDev = $totalTimeStats['std_dev'];

		$requestsSent = count($results);
		$timeDiff = microtime(true) - $timeStart;

		echo '             URL:  ', $this->url, PHP_EOL;
		echo '   Requests sent:  ', $requestsSent, PHP_EOL;
		echo '     Concurrency:  ', $this->concurrency, PHP_EOL;
		echo '        Timeouts:  ', $timeouts, PHP_EOL;
		echo '    2xx statuses:  ', $statuses2xx, PHP_EOL;
		echo 'Non-2xx statuses:  ', $statusesNon2xx, PHP_EOL;
		echo '         Timings:  ';
		echo sprintf('%s ± %s  (avg ± std dev)', self::formatSeconds($avgTime), self::formatSeconds($stdDev));
		echo PHP_EOL;
		self::printTable($timeTable);
		echo '     Total bytes:  ', $bytesDownloaded, ' B received, ', $bytesUploaded, ' B sent', PHP_EOL;
		echo '      HTTP codes:', PHP_EOL;
		arsort($httpCodes);
		foreach ($httpCodes as $code => $count) {
			if ($code === 0) {
				echo '         timeout:  ', $count, PHP_EOL;
			} else {
				echo '             ', $code, ':  ', $count, PHP_EOL;
			}
		}

		if ($this->bars > 1) {
			self::printHistogram($timesTotal, $this->bars);
		}

		$this->info(sprintf(
			'%d requests done in %s (%.0f average RPS).',
			$requestsSent,
			self::formatSeconds($timeDiff),
			$requestsSent / $timeDiff
		));
	}

	/**
	 * Define the command's schedule.
	 */
	public function schedule(Schedule $schedule): void
	{
		// $schedule->command(static::class)->everyMinute();
	}

	private function tasksGenerator(int $count): Generator
	{
		if ($count < 0) {
			$count = -$count;
		}
		for ($i = 1; $i <= $count; $i++) {
			yield function () use ($i) {
				$timeStart = microtime(true);

				$curl = curl_init();
				curl_setopt_array($curl, [
					CURLOPT_URL => $this->url,
					CURLOPT_FOLLOWLOCATION => false,
					CURLOPT_RETURNTRANSFER => true,
					CURLOPT_TIMEOUT_MS => 1000 * $this->timeout,
				]);
				if ($this->body !== '') {
					curl_setopt($curl, CURLOPT_POSTFIELDS, $this->body);
				}
				curl_exec($curl);
				$info = curl_getinfo($curl);
				$error = curl_errno($curl);
				curl_close($curl);

				$timeDiff = microtime(true) - $timeStart;
				return [
					'task' => $i,
					'wall_time' => $timeDiff,
					'time' => $info['total_time'],
					'ttfb' => $info['starttransfer_time'],
					'bytes_uploaded' => $info['size_upload'],
					'bytes_downloaded' => $info['size_download'],
					'code' => $info['http_code'],
					'ok' => $error === CURLE_OK,
					'timeout' => $error === CURLE_OPERATION_TIMEDOUT,
				];
			};
		}
	}

	private static function formatSeconds(float $seconds, $micro = true): string
	{
		if ($seconds >= 10) {
			return sprintf('%.1f s', $seconds);
		}
		if ($seconds >= 1) {
			return sprintf('%.2f s', $seconds);
		}
		if ($seconds >= 0.1 || !$micro) {
			return sprintf('%.0f ms', $seconds * 1_000);
		}
		if ($seconds >= 0.01) {
			return sprintf('%.1f ms', $seconds * 1_000);
		}
		if ($seconds >= 0.001) {
			return sprintf('%.2f ms', $seconds * 1_000);
		}
		if ($seconds >= 0.0001) {
			return sprintf('%.0f µs', $seconds * 1_000_000);
		}
		if ($seconds >= 0.0_0001) {
			return sprintf('%.1f µs', $seconds * 1_000_000);
		}
		if ($seconds >= 0.0_000_0001) {
			return sprintf('%.2f µs', $seconds * 1_000_000);
		}
		return '< 10 ns';
	}

	private static function calcStats(array $times): array
	{
		sort($times);
		$count = count($times);
		$halfCount = (int)($count / 2);
		$median = $count % 2 === 0
			? ($times[$halfCount] + $times[$halfCount - 1]) / 2
			: $times[$halfCount];

		$percentileCount = $count / 100;
		$percentile25Index = (int)($percentileCount * 25);
		$percentile75Index = (int)($percentileCount * 75);
		$percentile90Index = (int)($percentileCount * 90);
		$percentile95Index = (int)($percentileCount * 95);
		$percentile99Index = (int)($percentileCount * 99);

		$avgTime = array_sum($times) / $count;
		$stdDevSum = 0;
		foreach ($times as $item) {
			$stdDevSum += ($item - $avgTime) ** 2;
		}
		return [
			'average' => $avgTime,
			'std_dev' => sqrt($stdDevSum / ($count - 1)),
			'min' => $times[0],
			'max' => $times[$count - 1],
			'median' => $median,
			'percentiles' => [
				25 => ($times[$percentile25Index] + $times[$percentile25Index - 1]) / 2,
				50 => $median,
				75 => ($times[$percentile75Index] + $times[$percentile75Index - 1]) / 2,
				90 => ($times[$percentile90Index] + $times[$percentile90Index - 1]) / 2,
				95 => ($times[$percentile95Index] + $times[$percentile95Index - 1]) / 2,
				99 => ($times[$percentile99Index] + $times[$percentile99Index - 1]) / 2,
			],
		];
	}

	private static function printHistogram(array $times, $bins = 20): void
	{
		sort($times);
		$count = count($times);
		$min = $times[0];
		$max = $times[$count - 1];

		$step = ($max - $min) / $bins;
		$binStarts = [];
		$binEnds = [];
		$binCounts = [];
		for ($bin = 0; $bin < $bins; $bin++) {
			$binStarts[$bin] = $min + ($bin * $step);
			$binEnds[$bin] = $min + (($bin + 1) * $step);
			$binCounts[$bin] = 0;
		}

		foreach ($times as $time) {
			for ($bin = 0; $bin < $bins; $bin++) {
				if ($bin === $bins - 1) {
					// Последняя корзина, включаем конец диапазона
					if ($time >= $binStarts[$bin] && $time <= $binEnds[$bin]) {
						$binCounts[$bin]++;
					}
				} elseif ($time >= $binStarts[$bin] && $time < $binEnds[$bin]) {
					$binCounts[$bin]++;
				}
			}
		}
		$maxCount = max($binCounts);
		$printWidth = 50;
		$table = [];
		foreach ($binCounts as $bin => $item) {
			$binWidth = (int)round($item * $printWidth / $maxCount);
			$table[] = [
				self::formatSeconds($binStarts[$bin]) . ' .. ' . self::formatSeconds($binEnds[$bin]),
				str_repeat('#', $binWidth),
				$item,
			];
		}

		echo 'Timing histogram:' . PHP_EOL;
		self::printTable($table);
	}

	private static function printTable(array $table): void
	{
		$maxColWidth = [];
		foreach ($table as $row) {
			foreach ($row as $col => $value) {
				$maxColWidth[$col] = max(mb_strlen($value), $maxColWidth[$col] ?? 0);
			}
		}
		foreach ($table as $row) {
			foreach ($row as $col => $value) {
				echo ' ', str_pad($value, $maxColWidth[$col] + 1);
			}
			echo PHP_EOL;
		}
	}
}
