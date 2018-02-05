<?php

namespace Alcaeus\ResultPrinter;

use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\Test;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\TestFailure;
use PHPUnit\Framework\TestListener;
use PHPUnit\Framework\TestResult;
use PHPUnit\Framework\TestSuite;
use PHPUnit\Framework\Warning;
use PHPUnit\Runner\PhptTestCase;
use PHPUnit\Util\Printer;
use SebastianBergmann\Timer\Timer;
use Symfony\Component\Console;

final class ProgressingResultPrinter extends Printer implements TestListener
{
    const TERMINAL_WIDTH = 80;

    const TYPE_ERROR = 0;
    const TYPE_FAILURE = 1;

    /**
     * @var Console\Output\OutputInterface
     */
    protected $output;

    /**
     * @var Console\Helper\ProgressBar
     */
    protected $progress;

    /**
     * @var int
     */
    protected $numTests = -1;

    /**
     * @var int
     */
    protected $numAssertions = 0;

    /**
     * @var TestResult
     */
    protected $result;

    public function __construct()
    {
        parent::__construct(null);

        $this->output = new Console\Output\ConsoleOutput();
        $this->result = new TestResult();
    }

    public function flush(): void
    {
    }

    public function incrementalFlush(): void
    {
    }

    public function write(string $buffer): void
    {
        if ($this->progress !== null) {
            $this->progress->clear();
            $this->output->writeln('');
        }

        $this->output->write($buffer);

        if ($this->progress !== null) {
            $this->progress->display();
        }
    }

    public function addError(Test $test, \Throwable $e, float $time): void
    {
        $this->result->addError($test, $e, $time);
        $this->printFailure(static::TYPE_ERROR, new TestFailure($test, $e));
    }

    public function addWarning(Test $test, Warning $e, float $time): void
    {
        $this->result->addError($test, $e, $time);
    }

    public function addFailure(Test $test, AssertionFailedError $e, float $time): void
    {
        $this->result->addFailure($test, $e, $time);
        $this->printFailure(static::TYPE_FAILURE, new TestFailure($test, $e));
    }

    public function addIncompleteTest(Test $test, \Throwable $e, float $time): void
    {
        $this->result->addError($test, $e, $time);
    }

    public function addRiskyTest(Test $test, \Throwable $e, float $time): void
    {
        $this->result->addError($test, $e, $time);
    }

    public function addSkippedTest(Test $test, \Throwable $e, float $time): void
    {
        $this->result->addError($test, $e, $time);
    }

    public function startTestSuite(TestSuite $suite): void
    {
        $this->result->startTestSuite($suite);
        if ($this->numTests == -1) {
            $this->numTests = count($suite);
        }

        if ($this->progress === null) {
            $this->progress = new Console\Helper\ProgressBar($this->output, $this->numTests);
            $this->progress->setFormat('debug');
            $this->progress->setBarWidth(static::TERMINAL_WIDTH);
        }
    }

    public function endTestSuite(TestSuite $suite): void
    {
        $this->result->endTestSuite($suite);
    }

    public function startTest(Test $test): void
    {
        $this->result->startTest($test);
    }

    public function endTest(Test $test, float $time): void
    {
        $this->result->endTest($test, $time);
        $this->progress->advance();

        if ($test instanceof TestCase) {
            $this->numAssertions += $test->getNumAssertions();
        } elseif ($test instanceof PhptTestCase) {
            $this->numAssertions++;
        }

        if (count($this->result) == $this->numTests) {
            $this->printResult($this->result);
        }
    }

    private function printFailure(int $type, TestFailure $failure): void
    {
        $this->progress->clear();
        $this->output->writeln('');

        switch ($type) {
            case static::TYPE_FAILURE:
                $typeString = 'Failed';
                break;

            default:
                $typeString = 'Errored';
        }

        $this->output->writeln(sprintf("<error>%s: %s</error>", $typeString, $failure->getTestName()));

        $e = $failure->thrownException();
        $message = [(string) $e];

        while ($e = $e->getPrevious()) {
            $message[] = "Caused by" . $e;
        }

        $message = implode("\n", $message);

        $this->writeIndented($message);

        $this->progress->display();
    }

    private function printResult(TestResult $result): void
    {
        $this->progress->clear();

        $this->output->writeln('');

        if ($result->failureCount() || $result->errorCount() || $result->riskyCount() || $result->notImplementedCount() || $result->skippedCount()) {
            $this->output->writeln(str_repeat('-', static::TERMINAL_WIDTH) . "\n");
            $this->output->writeln("Summary\n");
        }

        $this->printFailedTests($result);
        $this->printTestsWithWarnings($result);
        $this->printErroredTests($result);
        $this->printSkippedTests($result);
        $this->printIncompleteTests($result);
        $this->printRiskyTests($result);

        $this->printResultFooter($result);
    }

    private function printResultFooter(TestResult $result): void
    {
        if (count($result) === 0) {
            $this->output->writeln("<fg=black;bg=cyan>\n\n No tests executed!\n</>");
        } elseif ($result->wasSuccessful() &&
            $result->allHarmless() &&
            $result->allCompletelyImplemented() &&
            $result->noneSkipped()
        ) {
            $this->output->writeln("<fg=black;bg=green>\n\n Success!\n</>");
        } elseif ($result->wasSuccessful()) {
            $this->output->writeln("<fg=black;bg=green>\n\n OK, but incomplete, skipped, or risky tests!\n</>");
        } else {
            $this->output->writeln("<error>\n\n Failures!\n</error>");
        }

        $this->output->writeln(
            sprintf(
                '%d test%s, %d assertion%s',
                count($result),
                (count($result) == 1) ? '' : 's',
                $this->numAssertions,
                ($this->numAssertions == 1) ? '' : 's'
            )
        );

        $this->output->writeln(Timer::resourceUsage());
    }

    private function printUnstableTests(string $label, TestFailure ...$failures): void
    {
        $count = count($failures);
        if (!$count) {
            return;
        }

        $this->output->writeln(sprintf('%d %s test%s:', $count, $label, $count > 1 ? 's' : ''));
        foreach ($failures as $test) {
            $this->output->writeln(sprintf(' * %s: %s', $test->getTestName(), $test->exceptionMessage()));
        }
        $this->output->writeln('');
    }

    private function printFailedTests(TestResult $result): void
    {
        $this->printUnstableTests('failed', ...$result->failures());
    }

    private function printTestsWithWarnings(TestResult $result): void
    {
        $this->printUnstableTests('warned', ...$result->warnings());
    }

    private function printErroredTests(TestResult $result): void
    {
        $this->printUnstableTests('errored', ...$result->errors());
    }

    private function printSkippedTests(TestResult $result): void
    {
        $this->printUnstableTests('skipped', ...$result->skipped());
    }

    private function printIncompleteTests(TestResult $result): void
    {
        $this->printUnstableTests('incomplete', ...$result->notImplemented());
    }

    private function printRiskyTests(TestResult $result): void
    {
        $this->printUnstableTests('risky', ...$result->risky());
    }

    private function writeIndented(string $message, int $num = 4): void
    {
        $lines = explode("\n", $message);

        $message = implode("\n", array_map(function ($line) use ($num) {
            return str_repeat(' ', $num) . $line;
        }, $lines));

        $this->output->writeln($message);
    }
}
