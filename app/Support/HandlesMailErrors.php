<?php

namespace App\Support;

trait HandlesMailErrors
{
    /**
     * Translate an SMTP/transport exception into a friendly, actionable message.
     */
    protected function friendlyMailError(\Throwable $e): string
    {
        $msg = $e->getMessage() . ' ' . ($e->getPrevious()?->getMessage() ?? '');

        if (str_contains($msg, 'getaddrinfo') || str_contains($msg, 'No such host')
            || str_contains($msg, 'Name or service not known') || str_contains($msg, 'nodename nor servname')) {
            return 'Email could not be sent: the mail server hostname could not be resolved. Check the server address in Settings → Email Config.';
        }
        if (str_contains($msg, 'Connection refused') || str_contains($msg, 'Connection timed out')
            || str_contains($msg, 'connect()') || str_contains($msg, 'Network is unreachable')) {
            return 'Email could not be sent: unable to connect to the mail server. Verify the server address and port in Settings → Email Config.';
        }
        if (str_contains($msg, '535') || str_contains($msg, 'Authentication')
            || str_contains($msg, 'Invalid credentials') || str_contains($msg, 'Username and Password not accepted')) {
            return 'Email could not be sent: authentication failed. Check the email username and password in Settings → Email Config.';
        }
        if (str_contains($msg, '550') || str_contains($msg, 'Relay access denied') || str_contains($msg, 'Sender address rejected')) {
            return 'Email could not be sent: the mail server rejected the message. Verify the sender address in Settings → Email Config.';
        }

        return 'Email could not be sent: ' . $e->getMessage();
    }

    /**
     * True for transient transport failures (DNS / connection) that are worth a
     * quick automatic retry — a cold DNS lookup often fails once then succeeds.
     */
    protected function isTransientMailError(\Throwable $e): bool
    {
        $msg = strtolower($e->getMessage() . ' ' . ($e->getPrevious()?->getMessage() ?? ''));

        foreach ([
            'getaddrinfo', 'no such host', 'name or service not known',
            'temporary failure in name resolution', 'nodename nor servname',
            'could not be resolved', 'connection timed out', 'connection refused',
            'network is unreachable', 'connection reset',
        ] as $needle) {
            if (str_contains($msg, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Run a mail send, retrying once on a transient DNS/connection error.
     * $send must throw on failure; returns the last exception or null on success.
     */
    protected function sendMailWithRetry(callable $send, int $tries = 2): ?\Throwable
    {
        $last = null;
        for ($attempt = 1; $attempt <= $tries; $attempt++) {
            try {
                $send();
                return null;
            } catch (\Throwable $e) {
                $last = $e;
                if ($attempt < $tries && $this->isTransientMailError($e)) {
                    usleep(500000); // 0.5s before retrying
                    continue;
                }
                break;
            }
        }

        return $last;
    }
}
