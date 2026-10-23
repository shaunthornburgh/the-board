<?php

namespace App\Services\Analyst;

use RuntimeException;

/** The analyst could not be reached or gave an answer we cannot use. The message is shown to the operator. */
class AnalystException extends RuntimeException {}
