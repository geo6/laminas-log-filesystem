<?php

namespace Geo6\Laminas\Log;

use ErrorException;
use Jenssegers\Agent\Agent;
use Laminas\Log\Logger;
use Laminas\Log\Processor\PsrPlaceholder;
use Laminas\Log\Writer\Stream;
use Psr\Http\Message\ServerRequestInterface;

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
        int $priority = Logger::INFO,
        ?ServerRequestInterface $request = null
    ): void {
        $directory = dirname($path);

        if (!file_exists($directory) || !is_dir($directory) || !is_writable($directory)) {
            throw new ErrorException(
                sprintf(
                    'The directory "%s" is not a vaild directory to write log files.',
                    $directory
                )
            );
        }

        // ---------------------------------------------------------------------------------------------
        $message = str_replace(["\r\n", "\r", "\n"], ' ', $message);

        // ---------------------------------------------------------------------------------------------
        if (!is_null($request)) {
            $server = $request->getServerParams();

            if (isset($server['HTTP_X_FORWARDED_FOR'])) {
                $extra['_ip'] = $server['HTTP_X_FORWARDED_FOR'];

                if (isset($server['REMOTE_ADDR'])) {
                    $extra['_ip'] .= ' (' . $server['REMOTE_ADDR'] . ')';
                }
            } elseif (isset($server['REMOTE_ADDR'])) {
                $extra['_ip'] = $server['REMOTE_ADDR'];
            }

            // ---------------------------------------------------------------------------------------------
            if (isset($server['HTTP_REFERER'])) {
                $extra['_referer'] = $server['HTTP_REFERER'];
            }

            // -----------------------------------------------------------------------------------------
            $agent = new Agent(
                $request->getServerParams(),
                $request->getHeaderLine('user-agent')
            );

            if ($agent->isRobot()) {
                $extra['_robot'] = $agent->robot();
            } else {
                $device = $agent->device();

                if ($agent->isPhone()) {
                    $extra['_device'] = $device !== false ? $device : 'phone';
                } elseif ($agent->isTablet()) {
                    $extra['_device'] = $device !== false ? $device : 'tablet';
                } elseif ($agent->isDesktop()) {
                    $extra['_device'] = 'desktop';
                }

                $platform = $agent->platform();
                $browser = $agent->browser();

                $extra['_platform'] = sprintf('%s %s', $platform, $agent->version($platform));
                $extra['_browser'] = sprintf('%s %s', $browser, $agent->version($browser));
            }

            // -----------------------------------------------------------------------------------------
            if (interface_exists('\Mezzio\Authentication\UserInterface')) {
                $user = $request->getAttribute(\Mezzio\Authentication\UserInterface::class);

                if (!is_null($user)) {
                    $extra['_identity'] = $user->getIdentity();
                }
            }
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
