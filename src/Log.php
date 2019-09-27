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
    public static function write($fname, $text, $data = [], $level = null)
    {
        $directory = realpath(dirname($fname));
        if (!file_exists($directory) || !is_dir($directory) || !is_writable($directory)) {
            throw new ErrorException(sprintf('The directory "%s" is not a vaild directory to write log files.', $directory));
        }

        if (is_null($level)) {
            $level = Logger::INFO;
        }

        // ---------------------------------------------------------------------------------------------
        if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $data['_ip'] = $_SERVER['HTTP_X_FORWARDED_FOR'] . (isset($_SERVER['REMOTE_ADDR']) ? ' (' . $_SERVER['REMOTE_ADDR'] . ')' : '');
        } else {
            $data['_ip'] = (isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : null);
        }

        // ---------------------------------------------------------------------------------------------
        $data['_referer'] = (isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : null);

        // ---------------------------------------------------------------------------------------------
        $agent = new Agent();

        if ($agent->isRobot()) {
            $data['_robot'] = $agent->robot();
        } else {
            if ($agent->isPhone()) {
                $data['_device'] = $agent->device() ?? 'phone';
            } elseif ($agent->isTablet()) {
                $data['_device'] = $agent->device() ?? 'tablet';
            } elseif ($agent->isDesktop()) {
                $data['_device'] = 'desktop';
            }

            $data['_platform'] = $agent->platform() . ' ' . $agent->version($agent->platform());
            $data['_browser'] = $agent->browser() . ' ' . $agent->version($agent->browser());
        }

        // ---------------------------------------------------------------------------------------------
        $auth = new AuthenticationService();
        $login = $auth->getIdentity();

        $data['_login'] = null;
        if (isset($_SESSION['uid']) && !empty($_SESSION['uid'])) {
            $data['_login'] = $login;
        }

        // ---------------------------------------------------------------------------------------------
        $logger = new Logger();
        $logger->addWriter(new Stream($fname));
        $logger->addProcessor(new PsrPlaceholder());

        $logger->log($level, $text, $data);
    }

    public static function read($fname)
    {
        $directory = realpath(dirname($fname));
        if (!file_exists($directory) || !is_dir($directory) || !is_readable($directory)) {
            throw new ErrorException(sprintf('The directory "%s" is not a vaild directory to read log files.', $directory));
        }

        $logs = [];

        $fp = fopen($fname, 'r');
        if ($fp) {
            while (($r = fgets($fp, 10240)) !== false) {
                // Zend\Log : %timestamp% %priorityName% (%priority%): %message% %extra%
                if (preg_match('/^(.+) (DEBUG|INFO|NOTICE|WARN|ERR|CRIT|ALERT|EMERG) \(([0-9])\): (.+) ({.+})$/', $r, $matches) == 1) {
                    $logs[] = [
                        'timestamp'     => strtotime($matches[1]), // ISO 8601
                        'priority_name' => $matches[2],
                        'priority'      => $matches[3],
                        'message'       => $matches[4],
                        'extra'         => json_decode($matches[5], true),
                    ];
                }
                // Other (olg) logs
                else {
                    $logs[] = $r;
                }
            }
        }
        fclose($fp);

        return $logs;
    }
}
