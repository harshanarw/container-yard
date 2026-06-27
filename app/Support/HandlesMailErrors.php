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
}
