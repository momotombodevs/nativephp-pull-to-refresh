<?php

namespace Momotombo\NativePhpPullToRefresh\Enums;

/** How the built-in indicator draws progress. */
enum ProgressStyle: string
{
    use ParsesInput;

    case Ring = 'ring';
    case Arc = 'arc';
    case Wave = 'wave';
    case Dots = 'dots';
    case None = 'none';
}
