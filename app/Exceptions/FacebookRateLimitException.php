<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Facebook Graph API ne rate-limit / spam-block diya
 * ("We limit how often you can post, comment or do other things…").
 *
 * Ise alag se pakadte hain taaki:
 *  - card ko 'failed' na karein (pending rehne do — baad me dobara try ho),
 *  - bulk loop turant ruk jaaye (aur post karke block na badhaye),
 *  - auto-post kuch ghante cooldown laga de.
 */
class FacebookRateLimitException extends RuntimeException
{
}
