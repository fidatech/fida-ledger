<?php

namespace App\WhatsApp;

use RuntimeException;

/** RelayWA could not be reached, or it refused the request. The message is safe to show to the user. */
class RelayWaError extends RuntimeException
{
}
