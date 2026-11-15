<?php

declare(strict_types=1);

namespace App\Services\Setup;

/** An installation problem whose message is safe to show to the person installing (no credentials, no SQL). */
final class SetupException extends \RuntimeException
{
}
