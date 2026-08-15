<?php
/**
 * Shared long-polling primitive. Used by:
 *  - public/pair.php   (mobile waits for its onboarding code to be claimed... )
 *    actually pairing is claimed by the mobile app itself; the *web* on-board
 *    page polls this to learn when pairing succeeded.
 *  - public/poll.php   (mobile app blocks here waiting for a new command)
 *
 * Keeps the HTTP connection open, re-checking $check() every $interval
 * seconds until it returns non-null or $timeout is reached.
 */
class OC_LongPoll
{
    public static function wait(callable $check, int $timeout = 25, int $interval = 1)
    {
        $deadline = time() + $timeout;
        while (true) {
            $result = $check();
            if ($result !== null) {
                return $result;
            }
            if (time() >= $deadline || connection_aborted()) {
                return null;
            }
            sleep($interval);
        }
    }
}
