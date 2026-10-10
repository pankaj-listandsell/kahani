<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Card abhi isi platform par kahin aur (button / cron) se post ho raha hai.
 * Isse double-post nahi hota — card 'failed' mark nahi karte, bas skip.
 */
class CardBusyException extends RuntimeException
{
}
