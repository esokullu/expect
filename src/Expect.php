<?php

namespace Yuloh\Expect;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class Expect
{
    const EXPECT = 0;
    const SEND   = 1;

    /**
     * @var string
     */
    private $cmd;

    /**
     * @var string
     */
    private $cwd;

    /**
     * @var resource[]
     */
    private $pipes;

    /**
     * @var resource
     */
    private $process;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    private $logger;

    /**
     * @param string $cmd
     * @param string $cwd
     * @param LoggerInterface $logger
     */
    private function __construct($cmd, $cwd = null, LoggerInterface $logger = null)
    {
        $this->cmd    = $cmd;
        $this->cwd    = $cwd;
        $this->logger = $logger ?: new NullLogger();
    }

    /**
     * Spawn a new instance of Expect for the given command.
     * You can optionally specify a working directory and a
     * PSR compatible logger to use.
     *
     * @param  string $cmd
     * @param  string $cwd
     * @param LoggerInterface $logger
     *
     * @return $this
     */
    public static function spawn($cmd, $cwd = null, LoggerInterface $logger = null)
    {
        return new self($cmd, $cwd, $logger);
    }

    /**
     * Register a step to expect the given text to show up on stdout.
     * Expect will block and keep checking the stdout buffer until your expectation shows up.
     *
     * @param  string $output
     * @return $this
     */
    public function expect($output)
    {
        $this->steps[] = [self::EXPECT, $output];

        return $this;
    }

    /**
     * Register a step to send the given text on stdin.
     * A newline is added to each string to simulate pressing enter.
     *
     * @param  string $input
     * @return $this
     */
    public function send($input)
    {
        if (stripos(strrev($input), PHP_EOL) === false) {
            $input = $input . PHP_EOL;
        }

        $this->steps[] = [self::SEND, $input];

        return $this;
    }

    /**
     * Run the process and execute the registered steps.
     *
     * @return null
     */
    public function run()
    {
        $this->createProcess();

        foreach ($this->steps as $step) {

            if ($step[0] === self::EXPECT) {
                $expectation = $step[1];
                $this->waitForExpectedResponse($expectation);
            } else {
                $input = $step[1];
                $this->sendInput($input);
            }
        }

        $this->closeProcess();
    }

    /**
     * Create the process.
     *
     * @return null
     * @throws \RuntimeException If the process can not be created.
     */
    private function createProcess()
    {
        $descriptorSpec = [
            ['pipe', 'r'], // stdin
            ['pipe', 'w'], // stdout
            ['pipe', 'r']  // stderr
        ];

        $this->process = proc_open($this->cmd, $descriptorSpec, $this->pipes, $this->cwd);

        if (!is_resource($this->process)) {
            throw new \RuntimeException('Could not create the process.');
        }
    }

    /**
     * Close the process.
     *
     * @return null
     */
    private function closeProcess()
    {
        fclose($this->pipes[0]);
        fclose($this->pipes[1]);
        fclose($this->pipes[2]);
        proc_close($this->process);
    }

    /**
     * Wait for the given response to show on stdout.
     *
     * @param  string $expectation The expected output.  Will be glob matched.
     * @return null
     */
    private function waitForExpectedResponse($expectation)
    {
        $response    = null;
        $buffer      = '';
        while (is_null($response) || !fnmatch($expectation, $response)) {
            $buffer .= fread($this->pipes[1],4096);
            $response = static::trimAnswer($buffer);

            $this->logger->info("Expected '{$expectation}', got '{$response}'");

            if (!$this->isRunning()) {
                // @todo
                return;
            }
        }
    }

    /**
     * Send the given input on stdin.
     *
     * @param  string $input
     * @return null
     */
    private function sendInput($input)
    {
        $this->logger->info("Sending '{$input}'");

        fwrite($this->pipes[0], $input);
    }

    /**
     * Returns a string with any newlines trimmed.
     *
     * @param  string $str
     * @return string
     */
    private static function trimAnswer($str)
    {
        return preg_replace('{\r?\n$}D', '', $str);
    }

    /**
     * Determine if the process is running.
     *
     * @return boolean
     */
    private function isRunning()
    {
        if (!is_resource($this->process)) {
            return false;
        }

        $status = proc_get_status($this->process);

        return $status['running'];
    }
}
