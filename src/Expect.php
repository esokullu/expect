<?php

namespace Yuloh\Expect;

class Expect
{
    const EXPECT = 0;
    const SEND = 1;

    private $cmd;

    private $cwd;

    private $debug = false;

    private function __construct($cmd, $cwd)
    {
        $this->cmd = $cmd;
        $this->cwd = $cwd;
    }

    public static function spawn($cmd, $cwd = null)
    {
        return new self($cmd, $cwd);
    }

    public function expect($output)
    {
        $this->steps[] = [self::EXPECT, $output];

        return $this;
    }

    public function send($input)
    {
        if (stripos(strrev($input), PHP_EOL) === false) {
            $input = $input . PHP_EOL;
        }

        $this->steps[] = [self::SEND, $input];

        return $this;
    }

    public function run()
    {
        $descriptorSpec = [
            ['pipe', 'r'], // stdin
            ['pipe', 'w'], // stdout
            ['pipe', 'r']  // stderr
        ];

        $process = proc_open($this->cmd, $descriptorSpec, $pipes, $this->cwd);

        if (!is_resource($process)) {
            throw new \RuntimeException('Could not create the process.');
        }

        foreach ($this->steps as $step) {

            if ($step[0] === self::EXPECT) {
                $expectation = $step[1];
                $this->log('getting response...');

                $response = null;
                $buffer = '';
                while (is_null($response) || !fnmatch($expectation, $response)) {
                    $buffer .= fread($pipes[1],4096);
                    $response = static::trimAnswer($buffer);
                    $this->log("expected '{$expectation}', got '{$response}'");

                    if (!$this->isRunning($process)) {
                        $this->log('process id dead.');
                        return;
                    }
                }
            } else {
                $input = $step[1];
                $this->log("sending '{$input}'");
                fwrite($pipes[0], $input);
            }
        }

        fclose($pipes[0]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        $output = proc_close($process);
        $this->log("Output: {$output}");
    }

    public function unbuffer()
    {
        if (empty(shell_exec('which script'))) {
            throw new \RuntimeException('Unbuffering requires script, which was not found.');
        }

        switch (PHP_OS) {
            case 'FreeBSD':
            case 'Darwin':
                $this->cmd = 'script -q /dev/null ' . $this->cmd;
                break;
            case 'Linux':
                $this->cmd = "script -c '{$this->cmd}' /dev/null";
                break;
            default:
                throw new \RuntimeException(
                    'Unable to automatically unbuffer for your OS.  ' .
                    'You will need to modify your shell command manually.'
                );
        }

        $this->unbuffered = true;

        return $this;
    }

    public function debug()
    {
        $this->debug = true;

        return $this;
    }

    private static function trimAnswer($str)
    {
        return preg_replace('{\r?\n$}D', '', $str);
    }

    private function isRunning($process)
    {
        if (!is_resource($process)) {
            return false;
        }

        $status = proc_get_status($process);

        return $status['running'];
    }

    private function log($str)
    {
        if (!$this->debug) {
            return;
        }

        echo $str . PHP_EOL;
    }
}
