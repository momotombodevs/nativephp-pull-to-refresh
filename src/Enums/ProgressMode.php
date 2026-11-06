<?php

namespace Momotombo\NativePhpPullToRefresh\Enums;

/** Whether progress follows the gesture, loops from the first pixel, or stays full. */
enum ProgressMode: string
{
    use ParsesInput;

    case Scrub = 'scrub';
    case Indeterminate = 'indeterminate';
    case None = 'none';
}
