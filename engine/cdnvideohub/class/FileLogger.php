<?php
/**
 * Логгер
 *
 * @link https://lazydev.pro/
 * @author LazyDev <email@lazydev.pro>
 **/

namespace LazyDev\CDNVideoHub;
use DateTimeImmutable;
use RuntimeException;
use Throwable;

class FileLogger
{
    private string $file;
    private bool $daily;
    private int $maxBytes;
    private int $maxFiles;

    public function __construct($file, $options = [])
    {
        $this->file = $file;
        $this->daily = isset($options['daily']) ? (bool)$options['daily'] : false;
        $this->maxBytes = isset($options['max_bytes']) ? (int)$options['max_bytes'] : 0;
        $this->maxFiles = isset($options['max_files']) ? (int)$options['max_files'] : 0;

        $dir = dirname($this->resolvePath());
        if (!is_dir($dir)) {
            if (!mkdir($dir, 0775, true) && !is_dir($dir)) {
                throw new RuntimeException("Не удалось создать каталог логов: {$dir}");
            }
        }
    }

    public function info($message, $context = []) {
        $this->log('INFO', $message, $context);
    }

    public function warning($message, $context = []) {
        $this->log('WARNING', $message, $context);
    }

    public function error($message, $context = []) {
        $this->log('ERROR', $message, $context);
    }

    public function debug($message, $context = []) {
        $this->log('DEBUG', $message, $context);
    }

    public function log($level, $message, $context = [])
    {
        $path = $this->resolvePath();
        $line = $this->formatLine($level, $message, $context);

        $this->rotateIfNeeded($path, strlen($line));

        $fp = @fopen($path, 'ab');
        if ($fp === false) {
            throw new RuntimeException("Не удалось открыть файл лога: {$path}");
        }

        try {
            if (!flock($fp, LOCK_EX)) {
                throw new RuntimeException("Не удалось получить блокировку для лога: {$path}");
            }

            fwrite($fp, $line);
            fflush($fp);
        } finally {
            flock($fp, LOCK_UN);
            fclose($fp);
        }
    }

    private function resolvePath()
    {
        if (!$this->daily) {
            return $this->file;
        }

        $extPos = strrpos($this->file, '.');
        $date = date('Y-m-d');
        if ($extPos === false) {
            return $this->file . '-' . $date . '.txt';
        }

        $base = substr($this->file, 0, $extPos);
        $ext = substr($this->file, $extPos);
        return $base . '-' . $date . $ext;
    }

    private function rotateIfNeeded($path, $incomingLen)
    {
        if ($this->maxBytes <= 0 || $this->maxFiles <= 0) {
            return;
        }

        clearstatcache(true, $path);
        $size = is_file($path) ? (int)filesize($path) : 0;

        if ($size + $incomingLen <= $this->maxBytes) {
            return;
        }

        for ($i = $this->maxFiles - 1; $i >= 1; $i--) {
            $src = $path . '.' . $i;
            $dst = $path . '.' . ($i + 1);
            if (is_file($src)) {
                @rename($src, $dst);
            }
        }

        if (is_file($path)) {
            @rename($path, $path . '.1');
        }
    }

    private function formatLine($level, $message, $context)
    {
        $timestamp = $this->nowWithMicroseconds();
        $pid = getmypid();
        $origin = PHP_SAPI === 'cli' ? 'cli' : ($_SERVER['REMOTE_ADDR'] ?? 'web');

        $message = $this->interpolate($message, $context);
        $message = str_replace(["\r\n", "\r", "\n"], ' ', $message);

        $ctx = $this->contextToString($context);

        return sprintf("[%s] [%s] [pid:%d] [%s] %s%s\n",
            $timestamp, strtoupper($level), $pid, $origin, $message, $ctx !== '' ? " | {$ctx}" : ''
        );
    }

    private function nowWithMicroseconds()
    {
        $ts = microtime(true);
        $dt = DateTimeImmutable::createFromFormat('U.u', sprintf('%.6F', $ts));
        if ($dt === false) {
            return date('Y-m-d H:i:s');
        }

        return $dt->format('Y-m-d H:i:s.u');
    }

    private function interpolate($message, $context)
    {
        if (strpos($message, '{') === false) {
            return $message;
        }

        $replace = [];
        foreach ($context as $key => $val) {
            $token = '{' . $key . '}';
            if (is_null($val) || is_scalar($val) || (is_object($val) && method_exists($val, '__toString'))) {
                $replace[$token] = (string)$val;
            } elseif ($val instanceof Throwable) {
                $replace[$token] = $val->getMessage();
            } else {
                $replace[$token] = json_encode($val, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
        }

        return strtr($message, $replace);
    }

    private function contextToString($context)
    {
        if ($context === []) {
            return '';
        }

        $normalized = [];
        foreach ($context as $k => $v) {
            if ($v instanceof Throwable) {
                $normalized[$k] = [
                    'exception' => get_class($v),
                    'message' => $v->getMessage(),
                    'file' => $v->getFile() . ':' . $v->getLine(),
                ];
            } else {
                $normalized[$k] = $v;
            }
        }

        return json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
