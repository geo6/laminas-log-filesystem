<?php

namespace Geo6\Zend\Log;

use ErrorException;
use Jenssegers\Agent\Agent;
use Zend\Authentication\AuthenticationService;
use Zend\Log\Logger;
use Zend\Log\Processor\PsrPlaceholder;
use Zend\Log\Writer\Stream;

class Log
{
    /**
     * Write PSR-3 log in a file (on disk).
     *
     * @param string $path     Path where to store the log file.
     * @param string $message  Message (can contain placeholders).
     * @param array  $extra    Extra data (will be used to fill placeholders).
     * @param int    $priority Priority.
     *
     * @throws ErrorException if the directory doesn't exist or is not writable.
     *
     * @return void
     */
    public static function write(
        string $path,
        string $message,
        array $extra = [],
        int $priority = Logger::INFO
    ): void {
        $directory = realpath(dirname($path));

        if (!file_exists($directory) || !is_dir($directory) || !is_writable($directory)) {
            throw new ErrorException(
                sprintf(
                    'The directory "%s" is not a vaild directory to write log files.',
                    $directory
                )
            );
        }

        // ---------------------------------------------------------------------------------------------
        if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $extra['_ip'] = $_SERVER['HTTP_X_FORWARDED_FOR'];

            if (isset($_SERVER['REMOTE_ADDR'])) {
                $extra['_ip'] .= ' ('.$_SERVER['REMOTE_ADDR'].')';
            }
        } elseif (isset($_SERVER['REMOTE_ADDR'])) {
            $extra['_ip'] = $_SERVER['REMOTE_ADDR'];
        }

        // ---------------------------------------------------------------------------------------------
        if (isset($_SERVER['HTTP_REFERER'])) {
            $extra['_referer'] = $_SERVER['HTTP_REFERER'];
        }

        // ---------------------------------------------------------------------------------------------
        $agent = new Agent();

        if ($agent->isRobot()) {
            $extra['_robot'] = $agent->robot();
        } else {
            if ($agent->isPhone()) {
                $extra['_device'] = $agent->device() ?? 'phone';
            } elseif ($agent->isTablet()) {
                $extra['_device'] = $agent->device() ?? 'tablet';
            } elseif ($agent->isDesktop()) {
                $extra['_device'] = 'desktop';
            }

            $extra['_platform'] = $agent->platform().' '.$agent->version($agent->platform());
            $extra['_browser'] = $agent->browser().' '.$agent->version($agent->browser());
        }

        // ---------------------------------------------------------------------------------------------
        $auth = new AuthenticationService();

        if ($auth->hasIdentity()) {
            $extra['_identity'] = $auth->getIdentity();
        }

        // ---------------------------------------------------------------------------------------------
        $logger = new Logger();
        $logger->addWriter(new Stream($path));
        $logger->addProcessor(new PsrPlaceholder());

        $logger->log($priority, $message, $extra);
    }

    /**
     * Read PSR-3 log from file (on disk).
     *
     * @param string $path Path of the log file to read.
     *
     * @throws ErrorException if the directory doesn't exist or is not readable.
     * @throws ErrorException if the log format is not PSR-3.
     *
     * @return array
     */
    public static function read(string $path): array
    {
        $directory = realpath(dirname($path));

        if (!file_exists($directory) || !is_dir($directory) || !is_readable($directory)) {
            throw new ErrorException(
                sprintf(
                    'The directory "%s" is not a vaild directory to read log files.',
                    $directory
                )
            );
        }

        $logs = [];

        $fp = fopen($path, 'r');
        if ($fp) {
            while (($r = fgets($fp, 1024)) !== false) {
                if (preg_match('/^(.+) (DEBUG|INFO|NOTICE|WARN|ERR|CRIT|ALERT|EMERG) \(([0-9])\): (.+) ({.+})$/', $r, $matches) == 1) {
                    $logs[] = [
                        'timestamp'     => strtotime($matches[1]), // ISO 8601
                        'priority_name' => $matches[2],
                        'priority'      => $matches[3],
                        'message'       => $matches[4],
                        'extra'         => json_decode($matches[5], true),
                    ];
                } else {
                    fclose($fp);

                    throw new ErrorException('Invalid format (not PSR-3).');
                }
            }
        }
        fclose($fp);

        return $logs;
    }
}
