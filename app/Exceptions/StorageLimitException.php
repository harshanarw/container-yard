<?php

namespace App\Exceptions;

use Illuminate\Http\Request;

/**
 * Thrown when an upload would push total file storage over the company limit.
 * Self-renders: a 422 JSON for AJAX/JSON requests, a redirect-back with an error
 * for a normal request — so no upload path needs its own try/catch.
 */
class StorageLimitException extends \Exception
{
    public function __construct(
        public readonly int $usedBytes,
        public readonly int $limitBytes,
        string $message = ''
    ) {
        parent::__construct($message ?: $this->defaultMessage());
    }

    private function defaultMessage(): string
    {
        $fmt = fn (int $b) => number_format($b / 1048576, 1) . ' MB';

        return 'Storage limit reached — ' . $fmt($this->usedBytes) . ' of ' . $fmt($this->limitBytes)
             . ' used. Remove some files or raise the limit before uploading.';
    }

    public function render(Request $request)
    {
        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'message' => $this->getMessage(),
                'errors'  => ['file' => [$this->getMessage()]],
            ], 422);
        }

        return back()->withInput()->with('error', $this->getMessage());
    }
}
