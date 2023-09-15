<?php

namespace app\admin\controller\traits;


class FileLog
{
    private static $instance = null;
    private $handler = null;
    private $level = 15;

    private function __construct() {
    }

    /**
     * @param string $file 文件名
     * @param null $path 附加目录名称  /logs/$path
     * @param int $level
     */
    public static function Init($file = "info", $path = null, $level = 15) {
        $log_path = dirname(APP_PATH) . "/logs/$path/";
        if (!file_exists($log_path)) {
            mkdir($log_path, 0777, true);
        }
        $handler = new CLogFileHandler($log_path . "$file-" . date('Y-m-d') . ".txt");
        if (!self::$instance instanceof self) {
            self::$instance = new self();
            self::$instance->__setHandle($handler);
            self::$instance->__setLevel($level);
        }
        return self::$instance;
    }

    private function __setHandle($handler) {
        $this->handler = $handler;
    }

    private function __setLevel($level) {
        $this->level = $level;
    }

    public static function DEBUG($msg) {
        self::$instance->write(1, $msg);
    }

    public static function WARN($msg) {
        self::$instance->write(4, $msg);
    }

    public static function ERROR($msg) {
        $debugInfo = debug_backtrace();
        $stack = "[";
        foreach ($debugInfo as $key => $val) {
            if (array_key_exists("file", $val)) {
                $stack .= ",file:" . $val["file"];
            }
            if (array_key_exists("line", $val)) {
                $stack .= ",line:" . $val["line"];
            }
            if (array_key_exists("function", $val)) {
                $stack .= ",function:" . $val["function"];
            }
        }
        $stack .= "]";
        self::$instance->write(8, $stack . $msg);
    }

    public static function INFO($msg) {
        self::$instance->write(2, $msg);
    }

    protected function write($level, $msg) {
        if (($level & $this->level) == $level) {
            $msg = '[' . date('Y-m-d H:i:s') . '][' . $this->getLevelStr($level) . '] ' . $msg . "\n";
            $this->handler->write($msg);
        }
    }

    private function getLevelStr($level) {
        switch ($level) {
            case 1:
                return 'debug';
                break;
            case 2:
                return 'info';
                break;
            case 4:
                return 'warn';
                break;
            case 8:
                return 'error';
                break;
            default:

        }
    }

    private function __clone() {
    }
}